# Congruency CMS — session additions

Everything below is served by the CMS itself, zero-JavaScript, against the unified DB
`~/.jazz/congruency.sqlite` (the app reads it via `checkouts/current/install.json` →
`CONGRUENCY_SQLITE`). Dev server: `python3 tools/serve.py` (`0.0.0.0:8899`).

## Pages (Documents)
A page is a `Documents` row `(DocumentID, TemplateID, Title, Description, ContentID)` joined to a
`Document_Templates` row `(TemplateID, Content)`; the template's `Content` is HTML with `<<<Tag>>>`
markers the engine executes. New pages this session: `tickets`, `memories`, `pages`, `tags`,
`ticketDone`, `tagDone`, `source`, `docs`, `annotations`, `categoryDone`, `annotateDone`. The order-wizard
pages (`config`/`order`/`thanks`) and the contact/flavour-poll form demos were removed.

## Tags (invocators)
Server-rendered `Tag_Interface` classes. New:

| Tag | Page | What it does |
|---|---|---|
| `<<<TicketList>>>` | `?page=tickets` | renders the `tickets` table (OPEN first) |
| `<<<MemoryList>>>` | `?page=memories` | **top-level** tag; the controller (Claude) tool-use log — merges the `memories` table + the live gate log `~/.MCP/gate-memories.json`, reverse-chronological, **paginated** (`?p=`) |
| `<<<SiteMap>>>` | nav | the nav, generated from `Documents` (Pages · Tags · …) |
| `<<<DatabaseInfo>>>` | `?page=database` | the DB self-described — every table, row count, and a `?api=<table>` browse link (admin-only tables marked) |
| `<<<Style>>>` | every page `<head>` | **the single site stylesheet** — one place for the whole look; full-width (no max-width), nav all-caps, code/pre/table styling. Templates embed `<<<Style>>>` not an inline `<style>`. Reading pages (catalog/about/invalid, and the DocList doc view) opt into `class="prose"` (`max-width:70ch`) for a comfortable measure; data/code pages stay full-width |
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

**Admin forms** (`?page=forms`, "Admin forms" — the contact/flavour-poll *demos* were removed): every form
now does real admin work. **NewCategoryForm** → `NewCategoryHandler` (`?page=categoryDone`) creates a
`Categories` row (grows the tag vocabulary); **AnnotateForm** → `AnnotateHandler` (`?page=annotateDone`)
writes the `annotations` layer for any `source`/`page`/`doc`/`ticket` target (the general front door the
inline flag + page-tagger are presets of). Plus the existing **TicketForm** (`?page=tickets`) and
**TagPageForm** (`?page=pages`). Handler pages (`categoryDone`/`annotateDone`) are nav-blocked like
`ticketDone`/`tagDone`.

## Categories as page tags (on the abstract `annotations` layer)
`Categories(key,name,description)` is the tag **vocabulary**; the page→category links now live in the
general `annotations` table as `tag=<category name>`, `target="page:<DocumentID>"` — the old
`Page_Categories` join table was migrated onto it and **dropped**. First category: `specifications`. Browse
via `CategoryPages` (reads annotations); tag via the `TagPageForm` UI on `?page=pages` (`TagPageHandler`
writes an annotation). Same model the source `⚑ flag` uses (`tag=flag`, `target="source:<hash>"`). Browse
everything tagged at `?page=annotations` (`<<<Annotations>>>`) — one table over flags + categories, filter
by `tag` or target-`kind` (source/page/doc/ticket), each target linked back to where it lives.

## REST (`boot/rest.php`, dispatched by `router.php` on `?api=`)
Generic CRUD over every table (name allowlisted against `sqlite_master`, columns validated) **minus an
admin denylist** — the self-hosting archive (`code_blobs`/`code_refs`/`doc_blobs`/`doc_refs`) and the auth
tables (`Login_Password`/`User_Group_Mappings`/`Group_Privileges`) are excluded; a request for one returns
404 as if it didn't exist. Everything else (`Documents`, `forms`, `tickets`, `annotations`, `Categories`, …)
is reachable:
- `GET  ?api=tables` — discovery (lists only the exposed tables)
- `GET  ?api=<table>[&p=&per=]` — paginated rows; `&id=<pk>` — one row (`404` if absent)
- `POST ?api=<table>` — create from a JSON object body (`201`; `400` on bad body / no valid columns)
- `PUT|PATCH ?api=<table>&id=` — update (needs a single-column pk; the pk itself is protected)
- `DELETE ?api=<table>&id=` — delete one row (needs a single-column pk + `?id=`)

**Reads are public; writes (`POST`/`PUT`/`PATCH`/`DELETE`) require the admin login** — an unauthenticated
write returns `401`. REST now dispatches from `router.php` *after* the session/POM boot (output-buffered so
it can still send clean JSON headers), so it can check `UserPrivilegeSet::logged_in()`.

## Self-hosting — the CMS renders its own running source + docs
`?page=source` and `?page=docs` browse the CMS's own source and documentation, mirrored into the DB on
every crank. They are **admin-only by design** (the `ADMIN_GATED` const on `SourceList`/`DocList`, checked
against `UserPrivilegeSet::logged_in()`) — currently **ungated** (`ADMIN_GATED=false`) during shakeout; flip
it to `true` to require the login. `tools/ingest_self.py` (run by the post-commit hook)
walks the git tree and UPSERTs each file **content-addressed by git blob hash** into two table pairs:
`code_blobs(hash,lang,bytes,body)` + `code_refs(hash,path,commit_sha,is_current)`, and the matching
`doc_blobs`/`doc_refs`. Blobs dedupe by hash; the `*_refs` are the **reverse lookup** (hash → path@commit,
and path → its version history); `is_current=1` marks the running-source version. Two tags render it:
`<<<SourceList>>>` (index + `?file=<hash>` view — escaped `<pre>` + line numbers + version history + a
**flag-for-follow-up** form: writes an abstract `annotations` row — a `tag` on an opaque `target` ref
(`source:<hash>`; the same shape can tag `page:<id>`/`doc:<hash>`/`ticket:<id>`) — and files a linked
`refactor` ticket as the follow-up projection) and
`<<<DocList>>>` (index + `?doc=<hash>` view — a small **escape-first markdown subset**, so `<<<Tag>>>`
examples in docs stay literal). Scope = the app PHP + the python tooling + docs + `main:deploy.py`/`install.py`;
`.md` anywhere but `.txt` **only** from `checkouts/current/doc/` (the legacy CMS docs — not stray prompts or
curl cookie jars); frozen/vendored trees excluded. The four archive tables (and the `Login_Password`/privilege tables) are
**denylisted from the public REST** (admin-only). Admin auth is the 2006 `UserPrivilegeSet`/`Login` path.

## Tooling (`tools/`)
- `ingest_self.py` — mirrors the running source + docs into the DB each crank (see Self-hosting, above).
- `db_export.py` — dump the DB to `state/seed.sql` (git-viewable text) + `.xz`; excludes `events` by default
  (`--keep-auth` keeps the auth tables, `--keep-events`/`--all` also available). **Manual — not run on any
  crank.** `--last-n N` cuts the tail off the self-hosting history (keeps the last N commits' refs + always
  the running source). The committed `state/seed.sql` is the default showcase DB, built with
  `--keep-auth --last-n 25` (demo admin baked in, history bounded to the last 25 commits ~1.9 MB).
- `db_import.py` — rebuild a DB from the seed (`--to <path>`, `--verify`); refuses to clobber without `--force`,
  never touches the live DB implicitly.
- `set_admin.py` — provision/rotate an admin login in a DB (`--db --login --password|--generate`); for
  deploy-time injection (or `prod_seed.php` picks up `CONGRUENCY_ADMIN_LOGIN`/`_PASSWORD` at deploy) so no
  credential ships in git.
- `db_verify.py` — integrity linter: every stored blob body must hash to its git blob id
  (`git_blob_sha(body) == hash`); `--manifest` checks the running source (`is_current`) against **git HEAD**,
  falling back to the shipped **`state/manifest.json`** (path→hash, written by `db_export`) when git is
  absent — so a production box can verify its DB against what was shipped. Exit 1 on any mismatch (gate-able).
  Blobs are stored **byte-exact** (raw bytes, no newline translation) so this holds. `SourceList` also shows
  a live **✓ verified / ✗ mismatch** badge per file (recomputes the blob hash in PHP on view).
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

## Unified DB shape (CMS/ops tables + the self-hosting archive + annotations)
`events, tickets, signals, heartbeat, memories` (ops/tracker) + `Documents, Document_Templates, Products,
forms, orders, Categories, Store_Content_Blocks` (CMS) + `_merge_conflicts` (merge audit)
+ `code_blobs, code_refs, doc_blobs, doc_refs` (content-addressed source/doc archive — see Self-hosting)
+ `annotations` (abstract `tag`→`target`: source flags + page categories; `Page_Categories` migrated here).

## Status (as of this session)
**Closed:** #25 (mysql_*→native PDO), #26 (deploy on/off/redeploy lifecycle + gate), #28 (README PDO
section), #29 (GPL-header stamp), #44 (form elements: number/date/multiselect), #45 (POM serialize→JSON),
#47 (generic REST). **Bugs fixed:** the BUG-01…07 catalog — SQLi, ProductDAO connection, `OrderDAO::updateRow`,
`returnAllBeans` null, missing-page crash, tag-recursion DoS, undefined constants — plus #58 (shim
`STDERR`), #60 (`Content::__toString` null), #108 (`sql_assoc_array` null-deref). **BUG-08** (LIFO command
queue) is deliberately left live — see `?page=bugs`. **Remaining:** #43/#46 increments, and the live
doc-stale backlog auto-filed by `doc_watch.py`. (#17–24 are historical tournament artifacts.)
