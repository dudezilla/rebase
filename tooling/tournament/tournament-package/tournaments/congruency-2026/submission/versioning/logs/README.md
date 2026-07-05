# versioning/logs — instrumentation record

Append-only git-operation logs emitted by the instrumented versioning apparatus
(see ../gitutil.py). Each `*.oplog.jsonl` line is one live git operation:
`{ts, cwd, argv, write, rc, stdout, stderr}` — proof that no git operation was
done from memory.

* `versioning.oplog.jsonl`  — the version_source.py run that created the version-2 tags
* `verify.oplog.jsonl`      — a verify_git_state.py run
* `verify_final.report.json` — the post-versioning verification report (VALID)
