# Congruencey bug catalog

Bugs found in [`congruencey`](https://github.com/dudezilla/congruencey) (Steven Peterson,
© 2006, GPLv2), each with an executable reproduction under [`repro/`](repro/) and an entry
in [`bugs.json`](bugs.json). Run them all with [`./run`](run).

Line references point into the pinned submodule at `vendor/congruencey`.

Two recurring root causes run through the list:

- **Copy-paste omissions** — a missing constructor line, a wrong method name, a validated
  variable aimed at the wrong name.
- **PHP 5 → 8 hardening** — behaviour that silently degraded in 2006 (`get_class(null)→false`,
  `current(null)→false`, undefined-constant→string-with-warning) is now a hard `Error`/`TypeError`.

## Reproduced bugs

| ID | Sev | Bug | Location |
|----|-----|-----|----------|
| BUG-01 | 🔴 critical | SQL injection: `select_products_by_category` validates into `$itemKey`, guards on `isset($key)`, and interpolates the **raw** `$key`. The validated value is dead code. Same shape in `get_product_details` / `get_category_details`. | `CatalogDAO.php:58` |
| BUG-02 | 🟠 high | `ProductDAO` constructor sets `$this->table` but never `CreateConnection(...)->open()`, unlike every sibling DAO. First query → method call on `null`. | `ProductDAO.php:25` |
| BUG-03 | 🟠 high | `OrderDAO::updateRow` calls `$this->insert()` — no such method exists — and `$this->delete($rowData['key'])` passes a bare key where a WHERE-clause is expected (`DELETE FROM orders 1`). | `OrderDAO.php:65` |
| BUG-04 | 🟡 medium | `returnAllBeans()` inits `$beansArray = NULL` and only fills it in-loop, so a zero-row query returns `null`; callers then `current($orders)` → `TypeError`. | `AbstractDAO.php:86` |
| BUG-05 | 🟡 medium | `Controller::display` passes a possibly-null document to `setData`, which does `get_class($data)`. A request for a missing page (with no `invalid` fallback row) hard-crashes on PHP 8. | `PersistentObjectManager.php:75` |
| BUG-06 | 🟡 medium | `Tag_Wrapper::execute_all_tags` recurses into a tag's rendered **output** with no visited-set/depth cap. A stored self-referential tag (e.g. a document whose Title is `<<<TitleTag>>>`) recurses forever → DoS. | `Tag_Wrapper.php:81` |
| BUG-07 | 🟠 high | Store/order/auth code references `MYSQL_STORE_DATABASE`, `STORE_LOGIN`, `MYSQL_ORDER_DATABASE`, `ETC`, … that `Constants.php` never defines. Undefined constants are fatal on PHP 8, so those whole subsystems fail at construction. (Matches the shipped `Install.txt`: "does not execute".) | `www/Constants.php` (missing) |
| BUG-08 | ⚪ low | `CommandInterfaceObject::execute` drains the queue with `array_pop()`, so commands run in reverse enqueue order. | `CommandInterfaceObject.php:38` |
| BUG-09 | 🟠 high | `FormElementUtils` parses radio options with `split()`, a POSIX-regex function **removed in PHP 7.0**. Direct/unit reproduction. | `FormElementUtils.php:47` |
| BUG-10 | 🟠 high | Same `split()` defect reached end-to-end: `RadioSelect::setElementString` → `parseRadioElementString` → `split()`, so any form with a radio element fatals. | `RadioSelect.php:61` |
| BUG-11 | 🟡 medium | `AbstractFormElement::getRequired()`/`isRequired()` are named like getters but take a mandatory `$bool`, assign it to `$required`, and return nothing — calling `isRequired()` throws `ArgumentCountError`; `getRequired($x)` silently overwrites the flag. | `AbstractFormElement.php:73` |
| BUG-12 | 🟠 high | `FormElementDAO` uses `MYSQL_FORM_DATABASE` / `FORM_LOGIN` / `FORM_PASSWORD`, none defined in `Constants.php` — fatal on PHP 8. Form-specific instance of the BUG-07 family. | `FormElementDAO.php:30` |
| BUG-13 | 🟡 medium | `ConfigForm::obtainPrice` decimal group `(.[0-9][0-9])?` has an **unescaped** `.`, so `12x99` parses as a valid price and gets summed into the estimate. | `ConfigForm.php:68` |
| BUG-14 | 🟡 medium | `ConfigForm::obtainDescription` uses a **greedy** `.*`, so with more than one element it matches through to the last `##` and swallows neighbouring elements' descriptions. | `ConfigForm.php:76` |
| BUG-15 | 🟠 high | `ConfigFormFCE::initFormArray` sorts a config form's elements into `$fCE`/`$iVE` then dereferences them unconditionally; a form missing its `IVE` marker → `getElementString()` on null. | `ConfigFormFCE.php:56` |

> BUG-13–15 are the **ConfigForm / RadioCreation admin builders** — a bespoke `## Price=.. ## Description=.. ##` mini-grammar parsed by hand-rolled regexes, plus "every config form has both an FCE and an IVE" assumptions. Reproduce against the pinned submodule and are independent of the `split()` defect.

> **ConfigForm fixes** for BUG-13–15 landed on the congruency `main` branch (commit `4ef5fc4`): the price regex's decimal point was escaped (`(.[0-9][0-9])` → `(\.[0-9][0-9])`), `obtainDescription`'s greedy `.*` was made non-greedy (`.*?`), and `ConfigFormFCE::initFormArray` now returns `NULL` when a config form is missing its `IVE`/`FCE` marker instead of dereferencing null. The catalog's submodule stays pinned at `2d584ff`, so they still reproduce here against the code as it shipped.

> **Forms fixes** for BUG-09–12 landed on the congruency `main` branch (commit `d27df51`): `split()`→`explode()`, real getters, and the missing config constants. The catalog's submodule stays pinned at `2d584ff`, so these still reproduce here against the code as it shipped.

> **BUG-05 fixed** on `main` (commit `bead971`): `setData()` now guards `get_class()` with `is_object()`, so storing a string (ConfigForm's estimate) or null (a missing page) is fine. Surfaced while wiring the config→order form chain live.

> **Exonerated:** `SortIterator` (the form-element merge sort, `lib/Sort/SortIterator.php`) *looks* broken — a Java-textbook merge sort transcribed into PHP with alarming `>=`/`<=` boundary conditions — but was fuzz-tested correct across every permutation of size 1–9. No bug; left off the list on purpose.

## Noted, not yet given an automated repro

- **`CommandRegistry::addClass`** (`CommandRegistry.php:41`) — the `isset()` branch is inverted
  (`array_push` runs on the `NULL` initial value → `TypeError`), it overwrites index `0` on the
  other branch, and it calls `$this->classLoader->loadClassByFilename()` which does not exist on
  `ClassLoader`.
- **`Login::authenticate`** (`Login.php:56`) — discards the return of `login_success()`, so the
  POST-login success path renders empty (the redirect still fires from the command queue).
- **`TagArguments::setArguments`** (`TagArguments.php:58`) — `foreach ($arguments as &$argument)`
  leaves a dangling reference to the last element; a classic PHP footgun if the array is later
  reused.
- **`DataConnection::query`** (`DataConnection.php:53`) — a failed `mysql_select_db()` silently
  returns `null` with no error surfaced.

## Not bugs (environment shims, for the record)

To run 2006 code on PHP 8 at all, the harness supplies a `mysql_*`→SQLite shim, a
`get_magic_quotes_gpc()` stub, and a neutralized `AutoLoader.php` (PHP 8 forbids declaring
`__autoLoad()`). These are scaffolding in `src/`, not defects in the original code — except that
BUG-07's missing constants and the removed-API usage are *why* it no longer runs unassisted.
