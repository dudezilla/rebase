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
requests by the POM-in-session — `router.php` does `session_start()`). New form **elements**
(ticket #44): `DbSelect` (a DB-backed `<select>`, options from `pages`/`categories`) and `Checkbox`
(boolean; TicketForm's optional `urgent` → `severity=high`). Both live in
`lib/Modules/Constructs/Form/FormElements/BasicElements/`.

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

## Tooling (`tools/`)
- `serve.py` — the dev server. `crawl.py` — BFS site spider → broken-link report (uses `?api=Documents`
  as the page oracle; expect exactly **1** broken = the deliberate `?page=nope` demo on `about`).
- `tagcheck.py` — renders every tag and asserts 200 + no PHP fatal (**29/29 pass**). These two are the
  regression gates used by the ratchet loop.

## Unified DB shape (13 tables)
`events, tickets, signals, heartbeat, memories` (ops/tracker) + `Documents, Document_Templates, Products,
forms, orders, Categories, Store_Content_Blocks, Page_Categories` (CMS) + `_merge_conflicts` (merge audit).

## Known / remaining backlog (open tickets)
Bugs fixed by the ratchet: **#58** (shim.php `STDERR` under `php -S`), **#60** (`Content::__toString`
null). Remaining: **#25** PDO migration, **#26** test-deploy lifecycle, **#29** GPL-header stamp, **#30**
dead-code audit, **#37** one-command MCP installer, **#42** order-wizard *code* removal, **#43** categories
for threads + admin, **#44** more elements (number/date/multiselect), **#45** forms deserialization (POM
serialize→JSON), **#46** predicted-output bets per tag. (#17–24 are historical tournament artifacts.)
