# logs/

Runtime sinks (git-ignored). Holds `bug_reports.jsonl` — the Variant-A bug-report sink named by
`registry.json`'s `bug_reports` key and written by every tool on an unexpected outcome. De-anchored
here from `file-system-repair/` (bug #9) so code resolves the sink via the registry, not a hardcoded
folder.
