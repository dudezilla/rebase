# mcp-coupler

A **coupling device for any number of MCP servers** — one MCP server that fronts N
downstream MCP servers, merging all their tools into a single namespaced list and
routing each call to the owning server. Register the coupler once; it fans out.

```
host ──stdio──► coupler ──stdio──► server A   (a1, a2 → A__a1, A__a2)
                        ──stdio──► server B   (b1     → B__b1)
                        ──stdio──► …any number…
```

Zero dependencies — MCP stdio is just newline-delimited JSON-RPC 2.0, implemented
directly on Node's stdlib. Nothing to `npm install`.

## Configure

`$COUPLER_CONFIG` (or `./coupler.config.json`). The `mcpServers` key is also
accepted, so a Claude Code config block drops in verbatim:

```json
{
  "servers": {
    "congruency": { "command": "node", "args": ["/home/notificationsforsteven/congruency-mcp/server.js"] },
    "some-other": { "command": "npx", "args": ["-y", "@vendor/mcp"], "env": { "TOKEN": "…" } }
  }
}
```

## Tool naming

Every downstream tool is exposed as `<server>__<tool>` (double-underscore), so two
servers can each have a `search` tool without colliding. Descriptions are prefixed
with `[server]`. Results pass through unchanged.

## Built-in meta tools

- `coupler__status` — health + tool count of every downstream.
- `coupler__reconnect` — tear down and re-spawn all downstreams, re-aggregate.

## Behaviour

- **Lazy + warm**: downstreams are spawned on the first `tools/list`/`tools/call`
  (and warmed on `initialize`), then cached.
- **Resilient**: a downstream that fails to start or dies is isolated and marked
  down in `coupler__status`; the others keep working. It never sinks the coupler.
- **Timeouts**: 30 s handshake, 5 min per downstream `tools/call`.
- **Clean shutdown**: on stdin close / SIGTERM it drains in-flight calls, kills all
  child servers, and exits.

## Install into Claude Code

Register **only the coupler** (remove the individual servers so tools aren't
duplicated):

```bash
claude mcp add coupler -s local -- node /home/notificationsforsteven/mcp-coupler/coupler.js
```

Then edit `coupler.config.json` to list the servers you want coupled. New tools
appear at the next session start (`/mcp` reconnect).
