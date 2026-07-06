# mcp/

**Vestigial** — a folded snapshot of the *old Node* MCP implementation. The **running** MCP is the
zero-dependency **Python** stack under `~/.MCP` (gate → coupler → `congruency-mcp/server.py` …);
nothing live references this folded copy.

| dir | what it is | status |
|---|---|---|
| `congruency-mcp/` (`server.js`, `tools/telemetry.php`, `package.json`) | Node MCP server exposing `run_verify` / `list_bugs` / `reproduce_bug` / `query_telemetry`; resolves `CONGRUENCY_ROOT` to the tree (tolerant of the `congruencey-*` spelling). | Superseded by `~/.MCP/congruency-mcp/server.py`. |
| `mcp-coupler/` (`coupler.js`, `coupler.config.json`) | Node MCP aggregator that fronts N downstream servers as one namespaced server. | Superseded by `~/.MCP/coupler.py`. |

## Status
Kept only as a reference snapshot; slated for removal (a later "circle-back" crank). The live MCP
config + servers are in `~/.MCP` (outside this repo).
