#!/usr/bin/env node
/*
 * mcp-coupler — a coupling device for any number of MCP servers.
 *
 * It is itself one MCP server (stdio) that a host (e.g. Claude Code) connects to.
 * Downstream, it acts as an MCP *client* to N configured servers: it spawns each,
 * performs the MCP handshake, aggregates every downstream tool into a single
 * namespaced list, and routes each tools/call to the owning downstream.
 *
 *   host ──stdio──► coupler ──stdio──► server A  (tools a1, a2 → A__a1, A__a2)
 *                            ──stdio──► server B  (tools b1     → B__b1)
 *                            ──stdio──► ...any number...
 *
 * Zero dependencies: MCP stdio is newline-delimited JSON-RPC 2.0, implemented here
 * directly on Node's stdlib. Config: $COUPLER_CONFIG or ./coupler.config.json,
 * shaped { "servers": { name: { command, args?, env? } } }  (also accepts the
 * "mcpServers" key so a Claude Code config block can be dropped in verbatim).
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

const SERVER_INFO = { name: 'mcp-coupler', version: '1.0.0' };
const DEFAULT_PROTOCOL = '2024-11-05';
const NS_SEP = '__';                 // namespace separator: <server>__<tool>
const CALL_TIMEOUT = 300000;         // downstream tools/call ceiling (5 min)
const HANDSHAKE_TIMEOUT = 30000;

// ---- config ---------------------------------------------------------------

function loadServers() {
  const p = process.env.COUPLER_CONFIG || path.join(__dirname, 'coupler.config.json');
  let raw;
  try { raw = JSON.parse(fs.readFileSync(p, 'utf8')); }
  catch (e) { process.stderr.write(`[coupler] cannot read config ${p}: ${e.message}\n`); return {}; }
  return raw.servers || raw.mcpServers || {};
}

const sanitize = (s) => String(s).replace(/[^a-zA-Z0-9_-]/g, '_');

// ---- downstream MCP client -------------------------------------------------

class Downstream {
  constructor(name, spec) {
    this.name = name;
    this.spec = spec || {};
    this.proc = null;
    this.buf = '';
    this.nextId = 1;
    this.pending = new Map();   // id -> { resolve, reject, timer }
    this.ready = null;          // Promise<tools[]>
    this.tools = [];
    this.dead = false;
    this.error = null;
  }

  start() {
    if (this.ready) return this.ready;
    this.ready = new Promise((resolve, reject) => {
      const { command, args = [], env = {} } = this.spec;
      if (!command) { this.dead = true; this.error = 'missing "command"'; return reject(new Error(this.error)); }
      let proc;
      try {
        proc = spawn(command, args, { env: { ...process.env, ...env }, stdio: ['pipe', 'pipe', 'pipe'] });
      } catch (e) { this.dead = true; this.error = e.message; return reject(e); }
      this.proc = proc;
      proc.stdout.setEncoding('utf8');
      proc.stdout.on('data', (c) => this._onData(c));
      proc.stderr.setEncoding('utf8');
      proc.stderr.on('data', (c) => process.stderr.write(`[${this.name}] ${c.trimEnd()}\n`));
      proc.on('exit', (code) => { this.dead = true; this.error = this.error || `exited (code ${code})`; this._failAll(new Error(`downstream '${this.name}' ${this.error}`)); });
      proc.on('error', (e) => { this.dead = true; this.error = e.message; reject(e); this._failAll(e); });

      this._request('initialize', { protocolVersion: DEFAULT_PROTOCOL, capabilities: {}, clientInfo: SERVER_INFO }, HANDSHAKE_TIMEOUT)
        .then(() => { this._notify('notifications/initialized'); return this._request('tools/list', {}, HANDSHAKE_TIMEOUT); })
        .then((res) => { this.tools = (res && res.tools) || []; resolve(this.tools); })
        .catch((e) => { this.dead = true; this.error = e.message; reject(e); });
    });
    // Swallow rejection here so an unavailable downstream never crashes the coupler;
    // connectAll() inspects .status via Promise.allSettled.
    this.ready.catch(() => {});
    return this.ready;
  }

  _onData(chunk) {
    this.buf += chunk;
    let nl;
    while ((nl = this.buf.indexOf('\n')) >= 0) {
      const line = this.buf.slice(0, nl).trim();
      this.buf = this.buf.slice(nl + 1);
      if (!line) continue;
      let msg; try { msg = JSON.parse(line); } catch { continue; }
      if (msg.id !== undefined && this.pending.has(msg.id)) {
        const { resolve, reject, timer } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        clearTimeout(timer);
        if (msg.error) reject(new Error(msg.error.message || `downstream error ${msg.error.code}`));
        else resolve(msg.result);
      }
      // Server-initiated notifications are ignored (coupler exposes tools only).
    }
  }

  _request(method, params, timeout = CALL_TIMEOUT) {
    if (this.dead) return Promise.reject(new Error(`downstream '${this.name}' is not running (${this.error || 'dead'})`));
    const id = this.nextId++;
    const payload = JSON.stringify({ jsonrpc: '2.0', id, method, params }) + '\n';
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => { this.pending.delete(id); reject(new Error(`downstream '${this.name}' timed out on ${method}`)); }, timeout);
      this.pending.set(id, { resolve, reject, timer });
      try { this.proc.stdin.write(payload); }
      catch (e) { clearTimeout(timer); this.pending.delete(id); reject(e); }
    });
  }

  _notify(method, params) {
    try { this.proc.stdin.write(JSON.stringify({ jsonrpc: '2.0', method, params: params || {} }) + '\n'); } catch { /* ignore */ }
  }

  _failAll(err) {
    for (const { reject, timer } of this.pending.values()) { clearTimeout(timer); reject(err); }
    this.pending.clear();
  }

  callTool(name, args) { return this._request('tools/call', { name, arguments: args || {} }, CALL_TIMEOUT); }

  stop() { if (this.proc && !this.dead) { try { this.proc.kill('SIGTERM'); } catch { /* ignore */ } } }
}

// ---- aggregation -----------------------------------------------------------

let servers = loadServers();
let downstreams = new Map();
for (const [name, spec] of Object.entries(servers)) downstreams.set(name, new Downstream(name, spec));

let connectPromise = null;

const META_TOOLS = [
  {
    name: `coupler${NS_SEP}status`,
    description: '[coupler] Report health of every coupled downstream MCP server and its tool count.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
  {
    name: `coupler${NS_SEP}reconnect`,
    description: '[coupler] Tear down and re-spawn all downstream MCP servers, then re-aggregate their tools.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
];

async function connectAll() {
  if (connectPromise) return connectPromise;
  connectPromise = (async () => {
    const ds = [...downstreams.values()];
    const settled = await Promise.allSettled(ds.map((d) => d.start()));
    const toolMap = new Map();               // namespaced name -> { ds, original }
    const list = [...META_TOOLS];
    settled.forEach((r, i) => {
      const d = ds[i];
      if (r.status !== 'fulfilled') {
        process.stderr.write(`[coupler] '${d.name}' unavailable: ${r.reason && r.reason.message}\n`);
        return;
      }
      for (const t of d.tools) {
        const ns = `${sanitize(d.name)}${NS_SEP}${t.name}`;
        toolMap.set(ns, { ds: d, original: t.name });
        list.push({ ...t, name: ns, description: `[${d.name}] ${t.description || ''}`.trim() });
      }
    });
    return { toolMap, list };
  })();
  return connectPromise;
}

async function reconnectAll() {
  for (const d of downstreams.values()) d.stop();
  downstreams = new Map();
  servers = loadServers();
  for (const [name, spec] of Object.entries(servers)) downstreams.set(name, new Downstream(name, spec));
  connectPromise = null;
  return connectAll();
}

function statusText() {
  const lines = [...downstreams.values()].map((d) => {
    const state = d.dead ? `✗ down (${d.error || 'unknown'})` : d.proc ? `✓ up, ${d.tools.length} tools` : '· not started';
    return `- ${d.name}: ${state}`;
  });
  const total = [...downstreams.values()].reduce((n, d) => n + (d.dead ? 0 : d.tools.length), 0);
  return `## coupler status — ${downstreams.size} server(s), ${total} live tool(s)\n\n${lines.join('\n') || '(no servers configured)'}`;
}

// ---- MCP server front (host-facing) ---------------------------------------

function send(msg) { process.stdout.write(JSON.stringify(msg) + '\n'); }
function reply(id, result) { send({ jsonrpc: '2.0', id, result }); }
function replyError(id, code, message) { send({ jsonrpc: '2.0', id, error: { code, message } }); }
const textResult = (text, isError = false) => ({ content: [{ type: 'text', text }], isError });

async function dispatchCall(name, args) {
  // Await connectAll() first so tool counts reflect completed downstream handshakes.
  if (name === `coupler${NS_SEP}status`) { await connectAll(); return textResult(statusText()); }
  if (name === `coupler${NS_SEP}reconnect`) { await reconnectAll(); return textResult(statusText()); }

  const { toolMap } = await connectAll();
  const entry = toolMap.get(name);
  if (!entry) return textResult(`unknown tool "${name}". Call tools/list for the current set.`, true);
  if (entry.ds.dead) return textResult(`downstream '${entry.ds.name}' is down (${entry.ds.error}). Try coupler${NS_SEP}reconnect.`, true);
  try {
    const result = await entry.ds.callTool(entry.original, args);
    // Pass the downstream tool result through unchanged (content/isError/etc.).
    return result;
  } catch (e) {
    return textResult(`downstream '${entry.ds.name}' failed on ${entry.original}: ${e.message}`, true);
  }
}

async function handle(msg) {
  const { id, method, params } = msg;
  const isRequest = id !== undefined && id !== null;
  switch (method) {
    case 'initialize':
      connectAll().catch(() => {});   // warm downstreams without blocking the handshake
      return reply(id, {
        protocolVersion: (params && params.protocolVersion) || DEFAULT_PROTOCOL,
        capabilities: { tools: {} },
        serverInfo: SERVER_INFO,
      });
    case 'notifications/initialized':
    case 'initialized':
      return;
    case 'ping':
      return reply(id, {});
    case 'tools/list': {
      const { list } = await connectAll();
      return reply(id, { tools: list });
    }
    case 'tools/call': {
      const result = await dispatchCall(params && params.name, (params && params.arguments) || {});
      return reply(id, result);
    }
    default:
      if (isRequest) return replyError(id, -32601, `Method not found: ${method}`);
      return;
  }
}

// ---- stdio loop ------------------------------------------------------------

let buf = '';
let inflight = 0;
let ended = false;
function shutdown() {
  for (const d of downstreams.values()) d.stop();
  process.exit(0);
}
function maybeExit() { if (ended && inflight === 0) shutdown(); }

process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
  buf += chunk;
  let nl;
  while ((nl = buf.indexOf('\n')) >= 0) {
    const line = buf.slice(0, nl).trim();
    buf = buf.slice(nl + 1);
    if (!line) continue;
    let msg; try { msg = JSON.parse(line); } catch { continue; }
    inflight++;
    Promise.resolve(handle(msg))
      .catch((e) => process.stderr.write(`[coupler] handler error: ${e && e.stack || e}\n`))
      .finally(() => { inflight--; maybeExit(); });
  }
});
process.stdin.on('end', () => { ended = true; maybeExit(); });
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
process.stderr.write(`[coupler] up — coupling ${downstreams.size} server(s)\n`);
