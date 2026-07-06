# Congruency CMS — architecture (checkouts/current/)

Steven Peterson's 2006 PHP CMS, © GPLv2, resurrected on a static PHP 8 binary. Every class file is
guarded by `if(!class_exists(...))`. The whole render is **string-substitution recursion**:
`Controller::display` -> `Document::__toString` -> the `<<<Tag>>>` engine -> each tag may emit more
`<<<...>>>` that get re-scanned and replaced -> queued `Command`s execute.

## 1. Boot / front controller
Two entry paths: the **modern relocatable** `boot/` (what `php -S` runs) and the original 2006 `www/`.

`boot/router.php` (live): `require shim.php` (now just a `get_magic_quotes_gpc()` polyfill, sec 4);
`require configure.php` (**config-as-data**: merges `constants.default.json` + `install.json`, `define()`s
every constant incl. `CONGRUENCY_SQLITE`, then derives the relocatable `ABS_PATH`/path constants from
`__DIR__` — this re-adds the store/order/form DB constants the 2006 code never defined, the BUG-07 fatal,
now resolved); dispatches `?api=` to `require rest.php` (generic REST over every table, #47);
`set_include_path` to **shadow the app's illegal `AutoLoader.php`**; `require CLASS_LOADER_HEADER`;
`session_start`; `getClassLoader()` + `spl_autoload_register(loadClassByName)` (replaces the removed
`__autoLoad()`); `getPOM()` -> `include(BIN.Initialize_POM.php)` -> `Controller::control()` -> `POM::pack()`.
`?fresh` unsets `$_SESSION['POM']`.

- `www/index.php` -> `Constants.php` + `bin/Execute.php` = the un-shimmed 2006 sequence (reference only).
- `boot/AutoLoader.php` is **neutralized** (empty) — exists only to shadow `lib/ClassLoader/AutoLoader.php`.
- `bin/Initialize_POM.php` pre-seeds app state (FormManager, TAG_LOADER, Store) — the hook to set the
  app at any state before first client load.
- `Controller` (`lib/Controller/Controller.php`, all-static): `control()` reads validated `$_GET['page']`
  (default `catalog`); `display($id)` -> `DocumentManager::get_document` -> POM `WORKING_PAGE` ->
  `set_display_string($document->__toString())` (fires the tag engine) -> `POM::executeCommands()`.

## 2. Tag engine — `<<<Name>>>` / `<<<Name(arg)(arg)>>>`
`lib/TagLoader/{Tag/Tag_Wrapper, Parser/Tag_Parser, Arguments/TagArguments}.php`; interface
`lib/Modules/taglib/Tag_Interface` (`get_document()` -> string); tag classes under `invocators/tags/**`
loaded by a dedicated `TAG_LOADER` (ClassLoader rooted at `TAGS_DIR`).
- `Tag_Parser` strips `<<<`/`>>>`, pulls the fn name (`GET_TAG_IDENTIFIER` regex), builds `TagArguments`
  (regex `(...)` groups, reversed; `top`/`pop`/`finished`).
- `Tag_Wrapper::execute_all_tags` is the **core recursion**: run `tag->get_document()`, regex-scan the
  output for more `<<<...>>>`, recurse + `str_replace`. Recursion is **capped at depth 64** (BUG-06 fixed;
  `BodyTag` deliberately emits `<<<ContentTag(..)>>>`, which used to loop forever).
- Built-ins (`invocators/tags/**`): `TitleTag`, `BodyTag`, `ContentTag`; store `Catalog_Controller`,
  `ProductView`, `CategoryView`, `ItemList`; `Login`/`Logout`/`ToggleLogin`; `FormTag` (renders a `forms`
  row); and the CMS/dev tags added this era: `TicketList`+`TicketLogger`, `MemoryList`, `PageList`,
  `TagList`, `CategoryPages`, `TagPageHandler`, `SiteMap`, `BugReport`/`BugDemo` (the order-wizard tags removed).
- **Hack:** `etc/Constants.php` allows `[a-zA-Z_]+` tag names but the *live* config (`constants.default.json`
  via `configure.php`, mirroring `www/Constants.php`) allows only `[a-zA-Z]+`, so the underscore invocators
  (`Config_Form_Invocator`, `Catalog_Controller`, …) are **unreachable** — hence the non-underscore dev
  tags that delegate to the same singletons.

## 3. Persistent Object Manager (POM)
`lib/PersistanceObjectManager/PersistentObjectManager.php` — static singleton (`$data`, `$classes`,
`$classLoader`, `$commandQueue`) consolidating all session-persistent state. `getPOM()` unpacks
`$_SESSION['POM']` or constructs anew; `pack()` writes it back. **`$data` now persists as JSON, not
`serialize()` (#45):** `pack`/`unpack` encode it as a registry of `{t,v}` envelopes — `FormManager` and
`StandardForm` serialize to inspectable JSON via their own `to_array()`/`from_array()` (form state now
survives class changes and no longer relies on `StandardForm::__wakeup`, which was removed), while any
other object (`ClassLoader`, `Document`, `UserPrivilegeSet`, …) falls back to `base64(serialize())` inside
the JSON. `setData/getData` keyed store guards `get_class` against non-objects (= BUG-05 fix). Key
entries: `WORKING_PAGE`, `FORM_MANAGER`, `TAG_LOADER`, `STORE_CONTAINER`, `USER_ID`.

## 4. DAO layer — native PDO (the mysql_* shim retired, #25)
The DAO layer runs on **native PDO**. `lib/DatabaseDrivers/MySQL/DataConnection.php` opens a native
`PDO('sqlite:'.CONGRUENCY_SQLITE)`; its `query()` returns a `MysqlShimResult` whose public `->rows` is an
array of associative rows that `AbstractDAO` + the concrete DAOs iterate directly. The 2006 `ext/mysql`
API was **fully migrated to PDO — zero `mysql_*` calls remain** anywhere; `boot/shim.php` survives only as
a `get_magic_quotes_gpc()` polyfill (removed in PHP 8.0), and `MysqlShimResult` was relocated out of the
shim to `lib/DatabaseDrivers/MySQL/MysqlShimResult.php`. `DataConnection` is driver-agnostic — a `mysql:`
DSN runs the same DAOs against MySQL; SQLite is just this deployment's target (all DBs collapse to one
file). Concrete DAOs' historical defects are **fixed**: **BUG-01** CatalogDAO SQLi (now interpolates the
validated `$itemKey`), **BUG-02** ProductDAO opens its STORE connection, **BUG-03** `OrderDAO::updateRow`
uses `deleteRow`/`insertRow`, **BUG-04** `AbstractDAO::returnAllBeans` returns `[]` on empty. Seed schema
is fabricated by `state/seed.php`; the unified DB is `~/.jazz/congruency.sqlite`.

## 5. Modules (`lib/Modules/`)
- **StoreModule**: `Container/Store` (POM-backed container); `Catalog/` (Category/Product + List views +
  DAOs); `Order/OrderSystem/` (the chained wizard, below); `Admin/` (the "Maker" back-office:
  ProductMaker, PageMaker, ConfigFormFCE, …).
- **Constructs/Form** — the form system: `FormManager` (POM `FORM_MANAGER`; `runForm` -> `StandardForm`);
  `StandardForm` (one hidden marker + **one submit button**); `FormElements/*` — `TextField`, `TextBox`,
  `BigTextBox`, `RadioSelect`, `Checkbox`, `DbSelect` (DB-backed `<select>`, option-set = allowlist),
  `NumberField`, `DateField`, `MultiSelect` (#44), plus `FormConfigElement`/FCE which parses
  `<action=..><oncomplete=..>` out of its element string and data-drives the form; every element carries
  `to_array()`/`from_array()` for JSON POM persistence (#45); `Beans/FormElementBean` maps a `forms`-table
  row (its `implements` column) to an element.
  **Single-submit-chained design:** each step is one `StandardForm`; its FCE sets `action` to the next
  page. Live example = the **ticket form**: `TicketForm (?page=tickets)` Continue -> review -> Continue ->
  FCE `action=?page=ticketDone` -> `TicketLogger` inserts the ticket into the jazz `tickets` table. (The
  order wizard was removed.) See the [[congruency-form-chaining]] rationale.
- **IOControl**: `Validators/ValidateFields` (`validatePageKey`/`validateNumericKey`/`validateFormKey`).
- **Commands** (`lib/CommandQueues`, `lib/Commands`): `Command` interface, `CommandInterfaceObject`
  (the POM-held queue), concrete `Redirect`/`DestroySession`. Tags enqueue during render;
  `Controller::display` drains them after `__toString`. **Hack:** the queue drains with `array_pop()` =
  **LIFO** (BUG-08). `UserAuthentication/UserPrivilegeSet` gates admin (`SKELETON_KEY`).

## 6. ClassLoader (`lib/ClassLoader/`)
`ClassLoader::loaderFactory($modRoot)` **recursively scans the tree** at construction, building
`fileList[classname] = path` (class name **must equal file basename**; `-4` assumes a 4-char extension);
`loadClassByName` -> `include_once`. Two loaders coexist in the POM: one rooted at `LIB` (app classes),
one at `TAGS_DIR` (`TAG_LOADER`). `lib/ClassLoader/AutoLoader.php` declares the illegal `__autoLoad()` —
shadowed on the live path by the empty `boot/AutoLoader.php` + the `spl_autoload_register` in `router.php`.

## Notable hacks (self-catalogued by `invocators/tags/dev/BugReport.php`, rendered *through* the CMS at `?page=bugs`)
**Still live:** neutered-AutoLoader + include_path shadowing (the load-bearing PHP-8 trick) · tag-name
regex mismatch makes underscore invocators dead · autoloader = full-tree scan, name==filename · **LIFO
command queue** (BUG-08, deliberately left live). **Fixed this era:** CatalogDAO SQLi (BUG-01) · ProductDAO
never connects (BUG-02) · `OrderDAO::updateRow` (BUG-03) · `returnAllBeans` null-on-empty (BUG-04) · missing
-page crash (BUG-05) · unbounded tag recursion (BUG-06) · undefined config constants (BUG-07) — the
`?page=bugs` showcase marks these RESOLVED live. Repros live in `tooling/congruencey-bugs/`.
