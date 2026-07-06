# Congruency CMS — session additions

Everything below is served by the CMS itself, zero-JavaScript, against the unified DB
`~/.jazz/congruency.sqlite` (the app reads it via `checkouts/current/install.json` →
`CONGRUENCY_SQLITE`). Dev server: `python3 tools/serve.py` (`0.0.0.0:8899`).

## Pages (Documents)
A page is a `Documents` row `(DocumentID, TemplateID, Title, Description, ContentID)` joined to a
`Document_Templates` row `(TemplateID, Content)`; the template's `Content` is HTML with `<<<Tag>>>`
markers the engine executes. New pages this session: `tickets`, `memories`, `pages`, `tags`,
`ticketDone`, `tagDone`. The order-wizard pages (`config`/`order`/`thanks`) were removed.

## Tags (invocators)
Server-rendered `Tag_Interface` classes. New:

| Tag | Page | What it does |
|---|---|---|
| `<<<TicketList>>>` | `?page=tickets` | renders the `tickets` table (OPEN first) |
| `<<<MemoryList>>>` | `?page=memories` | **top-level** tag; the controller (Claude) tool-use log — merges the `memories` table + the live gate log `~/.MCP/gate-memories.json`, reverse-chronological, **paginated** (`?p=`) |
| `<<<SiteMap>>>` | nav | the nav, generated from `Documents` (Pages · Tags · …) |
| `<<<PageList>>>` | `?page=pages` | index of every page |
| `<<<TagList>>>` | `?page=tags` | gallery of every tag; `?tag=NAME` renders that tag (re-entrancy guarded) |
| `<<<CategoryPages>>>` | `?page=pages` | browse pages by category (`?category=NAME`) |
| `<<<TicketForm>>>` via `<<<FormTag(TicketForm)>>>` / `<<<TicketLogger>>>` | `?page=tickets` → `?page=ticketDone` | native ticket submission |
| `<<<FormTag(TagPageForm)>>>` / `<<<TagPageHandler>>>` | `?page=pages` → `?page=tagDone` | tag a page into a category |

## Forms (native engine)
Forms are rows in the `forms` table sharing a `formName`; an `FCE`/`FormConfigElement` row carries
`<action='...'><oncomplete='...'>`. The form self-posts; on validating **complete** the FCE `action`
fires (→ a handler page) and the handler reads results via `FORM_MANAGER->getResults()` (carried across
requests by the POM-in-session — `router.php` does `session_start()`; the POM now persists form state as
**JSON** via each element/form's `to_array()`/`from_array()`, #45). New form **elements** (ticket #44):
`DbSelect` (DB-backed `<select>`, options from `pages`/`categories`, option-set = allowlist), `Checkbox`
(boolean; TicketForm's `urgent` → `severity=high`), `NumberField` + `DateField` (typed inputs with min/max
validation), and `MultiSelect` (array-valued checkbox group). All live in
`lib/Modules/Constructs/Form/FormElements/BasicElements/` and JSON-round-trip through the POM.

## Categories as page tags
`Categories(key,name,description)` + the new link table `Page_Categories(DocumentID, category_key)`
(many-to-many). First category: `specifications`. Browse via `CategoryPages`; tag via the `TagPageForm`
UI on `?page=pages`.

## REST (`boot/rest.php`, dispatched by `router.php` on `?api=`)
Generic CRUD over **every** table, table name allowlisted against `sqlite_master`, columns validated:
- `GET  ?api=tables` — discovery
- `GET  ?api=<table>[&p=&per=]` — paginated rows; `&id=<pk>` — one row
- `POST ?api=<table>` — create from JSON body
- `PUT|PATCH ?api=<table>&id=` — update (pk protected)
- `DELETE ?api=<table>&id=` — delete one row

## Self-hosting — the CMS renders its own running source + docs
`?page=source` and `?page=docs` browse the CMS's own source and documentation, mirrored into the DB on
every crank. They are **admin-only by design** (the `ADMIN_GATED` const on `SourceList`/`DocList`, checked
against `UserPrivilegeSet::logged_in()`) — currently **ungated** (`ADMIN_GATED=false`) during shakeout; flip
it to `true` to require the login. `tools/ingest_self.py` (run by the post-commit hook)
walks the git tree and UPSERTs each file **content-addressed by git blob hash** into two table pairs:
`code_blobs(hash,lang,bytes,body)` + `code_refs(hash,path,commit_sha,is_current)`, and the matching
`doc_blobs`/`doc_refs`. Blobs dedupe by hash; the `*_refs` are the **reverse lookup** (hash → path@commit,
and path → its version history); `is_current=1` marks the running-source version. Two tags render it:
`<<<SourceList>>>` (index + `?file=<hash>` view — escaped `<pre>` + line numbers + version history) and
`<<<DocList>>>` (index + `?doc=<hash>` view — a small **escape-first markdown subset**, so `<<<Tag>>>`
examples in docs stay literal). Scope = the app PHP + the python tooling + docs + `main:deploy.py`/`install.py`;
frozen/vendored trees excluded. The four archive tables (and the `Login_Password`/privilege tables) are
**denylisted from the public REST** (admin-only). Admin auth is the 2006 `UserPrivilegeSet`/`Login` path.

## Tooling (`tools/`)
- `ingest_self.py` — mirrors the running source + docs into the DB each crank (see Self-hosting, above).
- `doc_watch.py` — files a `documentation` ticket when a commit changes code but no doc (post-commit hook).
- `serve.py` — the dev server. `crawl.py` — BFS site spider → broken-link report (uses `?api=Documents`
  as the page oracle; expect exactly **1** broken = the deliberate `?page=nope` demo on `about`).
- `tagcheck.py` — renders every tag and asserts 200 + no PHP fatal **or warning/notice** (**24/24 pass**).
- `formelement_roundtrip.php` — form-element JSON round-trip + validation gate (#44/#45);
  `deploy_lifecycle_check.py` — production deploy on/off/redeploy gate (#26); `tostring_check.py` /
  `gpl_stamp.py` — the `__toString`-null and GPL-header gates.
- `doc_watch.py` — a commit observer: files a `documentation` ticket when code changes without a doc
  update (self-installs a `post-commit` hook via `--install-hook`). Together these are the regression
  gates the ratchet loop runs.

## Unified DB shape (13 CMS/ops tables + the self-hosting archive)
`events, tickets, signals, heartbeat, memories` (ops/tracker) + `Documents, Document_Templates, Products,
forms, orders, Categories, Store_Content_Blocks, Page_Categories` (CMS) + `_merge_conflicts` (merge audit)
+ `code_blobs, code_refs, doc_blobs, doc_refs` (the content-addressed source/doc archive — see Self-hosting).

## Status (as of this session)
**Closed:** #25 (mysql_*→native PDO), #26 (deploy on/off/redeploy lifecycle + gate), #28 (README PDO
section), #29 (GPL-header stamp), #44 (form elements: number/date/multiselect), #45 (POM serialize→JSON),
#47 (generic REST). **Bugs fixed:** the BUG-01…07 catalog — SQLi, ProductDAO connection, `OrderDAO::updateRow`,
`returnAllBeans` null, missing-page crash, tag-recursion DoS, undefined constants — plus #58 (shim
`STDERR`), #60 (`Content::__toString` null), #108 (`sql_assoc_array` null-deref). **BUG-08** (LIFO command
queue) is deliberately left live — see `?page=bugs`. **Remaining:** #43/#46 increments, and the live
doc-stale backlog auto-filed by `doc_watch.py`. (#17–24 are historical tournament artifacts.)
