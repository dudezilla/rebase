#!/usr/bin/env node
/*
 * congruency-mcp — a zero-dependency Model Context Protocol server (stdio).
 *
 * Wraps the durable congruency toolchain living under $CONGRUENCY_ROOT:
 *   - run_verify       runs congruencey-tests/verify (the full suite)
 *   - list_bugs        lists the catalogued bugs from congruencey-bugs/bugs.json
 *   - reproduce_bug    runs one bug's reproduction and reports whether it fired
 *   - query_telemetry  reads the attack-stack table from the harness telemetry DB
 *
 * Transport: MCP stdio = newline-delimited JSON-RPC 2.0 (one message per line).
 * No SDK, no npm install — just Node's stdlib, so the install can't rot.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFile } = require('child_process');

const ROOT = process.env.CONGRUENCY_ROOT || '/home/notificationsforsteven';
const P = {
  verify:    path.join(ROOT, 'congruencey-tests', 'verify'),
  bugsRun:   path.join(ROOT, 'congruencey-bugs', 'run'),
  bugsJson:  path.join(ROOT, 'congruencey-bugs', 'bugs.json'),
  php:       path.join(ROOT, 'congruencey-harness', 'php', 'php'),
  telemetry: path.join(ROOT, 'congruencey-harness', 'telemetry.sqlite'),
  telQuery:  path.join(__dirname, 'tools', 'telemetry.php'),
};

const SERVER_INFO = { name: 'congruency', version: '1.0.0' };
const DEFAULT_PROTOCOL = '2024-11-05';

// ---- helpers ---------------------------------------------------------------

function run(cmd, args, { timeout = 60000, maxBuffer = 8 * 1024 * 1024 } = {}) {
  return new Promise((resolve) => {
    execFile(cmd, args, { timeout, maxBuffer, killSignal: 'SIGKILL' }, (err, stdout, stderr) => {
      resolve({
        code: err && typeof err.code === 'number' ? err.code : (err ? 1 : 0),
        timedOut: !!(err && err.killed),
        stdout: stdout || '',
        stderr: stderr || '',
      });
    });
  });
}

function clip(s, n = 6000) {
  if (s.length <= n) return s;
  return s.slice(0, n) + `\n…[truncated ${s.length - n} chars]`;
}

function textResult(text, isError = false) {
  return { content: [{ type: 'text', text }], isError };
}

// ---- tool implementations --------------------------------------------------

async function toolRunVerify() {
  if (!fs.existsSync(P.verify)) return textResult(`verify script not found at ${P.verify}`, true);
  const r = await run('bash', [P.verify], { timeout: 240000 });
  const out = (r.stdout + (r.stderr ? '\n' + r.stderr : ''));
  const m = out.match(/(\d+)\s+suites passed,\s+(\d+)\s+failed/);
  const summary = r.timedOut
    ? '⏱ verify timed out'
    : m ? `${m[1]} suites passed, ${m[2]} failed (exit ${r.code})`
        : `exit ${r.code}`;
  return textResult(`## verify — ${summary}\n\n\`\`\`\n${clip(out)}\n\`\`\``, r.code !== 0);
}

async function toolListBugs(args) {
  if (!fs.existsSync(P.bugsJson)) return textResult(`bugs.json not found at ${P.bugsJson}`, true);
  let bugs;
  try { bugs = JSON.parse(fs.readFileSync(P.bugsJson, 'utf8')); }
  catch (e) { return textResult(`could not parse bugs.json: ${e.message}`, true); }
  if (!Array.isArray(bugs)) bugs = bugs.bugs || [];
  const sev = (args && args.severity || '').toLowerCase();
  const filtered = sev ? bugs.filter(b => (b.severity || '').toLowerCase() === sev) : bugs;
  const lines = filtered.map(b =>
    `- **${b.id}** [${(b.severity || '?').toUpperCase()}] ${b.title}\n    ${b.file}  →  repro: ${b.repro}`);
  const header = `## Bug catalog — ${filtered.length}${sev ? ` ${sev}` : ''} of ${bugs.length}`;
  return textResult(`${header}\n\n${lines.join('\n')}`);
}

async function toolReproduceBug(args) {
  const id = String(args && args.id || '').trim().toUpperCase();
  if (!/^BUG-\d{2}$/.test(id)) return textResult(`invalid id "${id}"; expected form BUG-01`, true);
  if (!fs.existsSync(P.bugsRun)) return textResult(`bug runner not found at ${P.bugsRun}`, true);
  const r = await run('bash', [P.bugsRun, id], { timeout: 120000 });
  // The harness exit code = number of listed bugs that did NOT reproduce (0 = reproduced).
  const reproduced = !r.timedOut && r.code === 0;
  const verdict = r.timedOut ? '⏱ TIMED OUT' : reproduced ? '✅ REPRODUCED' : '❌ did NOT reproduce';
  const out = r.stdout + (r.stderr ? '\n' + r.stderr : '');
  return textResult(`## ${id} — ${verdict}\n\n\`\`\`\n${clip(out)}\n\`\`\``, !reproduced);
}

async function toolQueryTelemetry(args) {
  if (!fs.existsSync(P.php))    return textResult(`php interpreter not found at ${P.php}`, true);
  if (!fs.existsSync(P.telemetry)) return textResult(`telemetry DB not found at ${P.telemetry}`, true);
  const kind = String(args && args.kind || 'all');
  const limit = String(Math.max(1, Math.min(200, parseInt(args && args.limit, 10) || 20)));
  const r = await run(P.php, [P.telQuery, P.telemetry, kind, limit], { timeout: 20000 });
  let data;
  try { data = JSON.parse(r.stdout.trim()); }
  catch (e) { return textResult(`telemetry query failed (exit ${r.code}): ${clip(r.stdout + r.stderr, 2000)}`, true); }
  if (!data.ok) return textResult(`telemetry error: ${data.error}`, true);
  const tally = Object.entries(data.tally || {}).map(([k, v]) => `${k}=${v}`).join('  ') || '(empty)';
  const rows = (data.rows || []).map(x =>
    `#${x.id} [${x.verdict || 'PENDING'}] ${x.name} (${x.page})\n    predict: ${x.prediction}\n    result : ${x.result || '—'}${x.evidence ? `\n    evidence: ${x.evidence}` : ''}`);
  return textResult(`## Attack telemetry — tally: ${tally}\nshowing ${data.count} (filter: ${kind}, limit ${limit})\n\n${rows.join('\n\n') || '(no rows)'}`);
}

const TOOLS = [
  {
    name: 'run_verify',
    description: 'Run the full congruency verification suite (congruencey-tests/verify): PHPUnit, the 15-bug catalog, and branch coverage. Returns the pass/fail summary and output.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
    handler: toolRunVerify,
  },
  {
    name: 'list_bugs',
    description: 'List the catalogued congruency bugs (id, severity, title, file, repro path). Optional severity filter.',
    inputSchema: {
      type: 'object',
      properties: { severity: { type: 'string', description: 'Optional filter: critical | high | medium | low' } },
      additionalProperties: false,
    },
    handler: toolListBugs,
  },
  {
    name: 'reproduce_bug',
    description: 'Run a single catalogued bug\'s reproduction against the pinned app submodule and report whether it still reproduces.',
    inputSchema: {
      type: 'object',
      properties: { id: { type: 'string', description: 'Bug id, e.g. BUG-01' } },
      required: ['id'],
      additionalProperties: false,
    },
    handler: toolReproduceBug,
  },
  {
    name: 'query_telemetry',
    description: 'Query the adversarial attack-stack telemetry DB (predictions vs results). Optional verdict filter (CONFIRMED|REFUTED|SAFE|all) and row limit.',
    inputSchema: {
      type: 'object',
      properties: {
        kind: { type: 'string', description: 'Verdict filter: CONFIRMED | REFUTED | SAFE | all (default all)' },
        limit: { type: 'integer', description: 'Max rows to return (1-200, default 20)' },
      },
      additionalProperties: false,
    },
    handler: toolQueryTelemetry,
  },
];

// ---- JSON-RPC / MCP plumbing ----------------------------------------------

function send(msg) { process.stdout.write(JSON.stringify(msg) + '\n'); }
function reply(id, result) { send({ jsonrpc: '2.0', id, result }); }
function replyError(id, code, message) { send({ jsonrpc: '2.0', id, error: { code, message } }); }

async function handle(msg) {
  const { id, method, params } = msg;
  const isRequest = id !== undefined && id !== null;

  switch (method) {
    case 'initialize':
      return reply(id, {
        protocolVersion: (params && params.protocolVersion) || DEFAULT_PROTOCOL,
        capabilities: { tools: {} },
        serverInfo: SERVER_INFO,
      });
    case 'notifications/initialized':
    case 'initialized':
      return; // notification, no response
    case 'ping':
      return reply(id, {});
    case 'tools/list':
      return reply(id, { tools: TOOLS.map(({ name, description, inputSchema }) => ({ name, description, inputSchema })) });
    case 'tools/call': {
      const tool = TOOLS.find(t => t.name === (params && params.name));
      if (!tool) return replyError(id, -32602, `Unknown tool: ${params && params.name}`);
      try {
        const result = await tool.handler(params.arguments || {});
        return reply(id, result);
      } catch (e) {
        return reply(id, textResult(`tool "${tool.name}" threw: ${e && e.stack || e}`, true));
      }
    }
    default:
      if (isRequest) return replyError(id, -32601, `Method not found: ${method}`);
      return; // unknown notification: ignore
  }
}

// Read newline-delimited JSON from stdin. Track in-flight handlers so a stdin
// close (client disconnect / piped input EOF) drains pending calls before exit.
let buf = '';
let inflight = 0;
let ended = false;
function maybeExit() { if (ended && inflight === 0) process.exit(0); }

process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
  buf += chunk;
  let nl;
  while ((nl = buf.indexOf('\n')) >= 0) {
    const line = buf.slice(0, nl).trim();
    buf = buf.slice(nl + 1);
    if (!line) continue;
    let msg;
    try { msg = JSON.parse(line); }
    catch { continue; } // ignore unparseable lines
    inflight++;
    Promise.resolve(handle(msg))
      .catch((e) => process.stderr.write(`handler error: ${e}\n`))
      .finally(() => { inflight--; maybeExit(); });
  }
});
process.stdin.on('end', () => { ended = true; maybeExit(); });
process.stderr.write(`congruency-mcp up (root=${ROOT})\n`);
