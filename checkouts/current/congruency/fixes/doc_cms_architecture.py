#!/usr/bin/env python3
"""doc_cms_architecture.py — doc crank: checkouts/current/ARCHITECTURE.md (the 2006 CMS internals)."""
import json, os, sys, time, traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
FIXES = HERE if os.path.basename(HERE) == "fixes" else os.path.dirname(HERE)
SOURCE = os.path.dirname(FIXES)
INDEX = os.path.join(FIXES, "index.json")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()

ARCH = r"""# Congruency CMS — architecture (checkouts/current/)

Steven Peterson's 2006 PHP CMS, © GPLv2, resurrected on a static PHP 8 binary. Every class file is
guarded by `if(!class_exists(...))`. The whole render is **string-substitution recursion**:
`Controller::display` -> `Document::__toString` -> the `<<<Tag>>>` engine -> each tag may emit more
`<<<...>>>` that get re-scanned and replaced -> queued `Command`s execute.

## 1. Boot / front controller
Two entry paths: the **modern relocatable** `boot/` (what `php -S` runs) and the original 2006 `www/`.

`boot/router.php` (live): defines `CONGRUENCY_SQLITE` = `../state/congruency.sqlite`; `require shim.php`
(mysql_* -> PDO/sqlite, sec 4); `require Constants_patched.php` (relocatable `ABS_PATH` from `__DIR__`,
re-adds the store/order/form DB constants the 2006 code referenced but never defined = the BUG-07
fatal); `set_include_path` to **shadow the app's illegal `AutoLoader.php`**; `require CLASS_LOADER_HEADER`;
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
  output for more `<<<...>>>`, recurse + `str_replace`. **No depth/visited guard** (BUG-06; `BodyTag`
  deliberately emits `<<<ContentTag(..)>>>`).
- Built-ins (`invocators/tags/**`): `TitleTag`, `BodyTag`, `ContentTag`; store `Catalog_Controller`,
  `ProductView`, `CategoryView`, `ItemList`; `Login`/`Logout`/`ToggleLogin`; form invocators; dev tags
  (`FormTag`, `ConfigFormTag`, `OrderFormTag`, `OrdererTag`, `BugReport`, `BugDemo`).
- **Hack:** `etc/Constants.php` allows `[a-zA-Z_]+` tag names but the *live* `Constants_patched.php`
  (= `www/Constants.php`) allows only `[a-zA-Z]+`, so the underscore invocators
  (`Config_Form_Invocator`, `Catalog_Controller`, …) are **unreachable** — hence the non-underscore dev
  tags that delegate to the same singletons.

## 3. Persistent Object Manager (POM)
`lib/PersistanceObjectManager/PersistentObjectManager.php` — static singleton (`$data`, `$classes`,
`$classLoader`, `$commandQueue`) consolidating all session-persistent state into one serializable object.
`getPOM()` unpacks `$_SESSION['POM']` (reloading class defs via the loader) or constructs anew; `pack()`
serializes it back. `setData/getData` keyed store (records class names for reload; guards `get_class`
against non-objects = BUG-05 fix). Key entries: `WORKING_PAGE`, `FORM_MANAGER`, `TAG_LOADER`,
`STORE_CONTAINER`, `USER_ID`.

## 4. DAO layer + the mysql_* -> SQLite shim
`boot/shim.php` emulates `ext/mysql` over PDO+SQLite: `mysql_query` -> `MysqlShimResult`,
`mysql_real_escape_string` doubles quotes, `get_magic_quotes_gpc()` -> false. **All DBs collapse to one
SQLite file** (`mysql_select_db` is a no-op). `lib/DatabaseDrivers/MySQL/{DataConnection, AbstractDAO}.php`
provide the connection + SQL builders; DAOs `__sleep/__wakeup` close/reopen across serialization.
Concrete DAOs (`lib/Modules/StoreModule/**/DAO`, `Constructs/Form/DAO/FormElementDAO`): note **BUG-01**
CatalogDAO SQLi (validated value discarded), **BUG-02** ProductDAO never opens its connection,
**BUG-03** OrderDAO::updateRow calls undefined `insert()`, **BUG-04** `AbstractDAO::returnAllBeans`
returns `null` on empty. Seed schema is fabricated by `state/seed.php`.

## 5. Modules (`lib/Modules/`)
- **StoreModule**: `Container/Store` (POM-backed container); `Catalog/` (Category/Product + List views +
  DAOs); `Order/OrderSystem/` (the chained wizard, below); `Admin/` (the "Maker" back-office:
  ProductMaker, PageMaker, ConfigFormFCE, …).
- **Constructs/Form** — the form system: `FormManager` (POM `FORM_MANAGER`; `runForm` -> `StandardForm`);
  `StandardForm` (one hidden marker + **one submit button**); `FormElements/*` (TextField, RadioSelect,
  and `FormConfigElement`/FCE which parses `<action=..><oncomplete=..>` out of its element string and
  data-drives the form); `Beans/FormElementBean` (DAO row -> element).
  **Single-submit-chained design:** each step is one `StandardForm`; its FCE sets `action` to the next
  page. Wizard = `ConfigForm (?page=order) -> OrderForm (?page=thanks) -> Orderer` (`OrderDAO::insertRow`,
  `sendEmail` no-op). See the [[congruency-form-chaining]] rationale.
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

## Notable hacks (all self-catalogued by `invocators/tags/dev/BugReport.php`, rendered *through* the CMS)
All-DBs-one-file · neutered-AutoLoader + include_path shadowing (the load-bearing PHP-8 trick) ·
tag-name regex mismatch makes underscore invocators dead · autoloader = full-tree scan, name==filename ·
LIFO command queue · unbounded tag recursion · `returnAllBeans` null-on-empty · CatalogDAO SQLi ·
ProductDAO never connects. The 15 catalogued defects + repros live in `tooling/congruencey-bugs/`.
"""


def bug_report(exc, tb):
    reg = os.path.join(ROOT, "registry.json")
    rel = "logs/bug_reports.jsonl"
    if os.path.isfile(reg):
        try:
            rel = json.load(open(reg)).get("bug_reports", rel)
        except Exception:
            pass
    path = os.path.join(ROOT, rel)
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "doc crank: doc_cms_architecture"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "checkouts/current/ARCHITECTURE.md",
             "purpose": "doc: the 2006 CMS internals (boot/tag-engine/POM/DAO/modules/forms/classloader)",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    open(os.path.join(ROOT, "checkouts", "current", "ARCHITECTURE.md"), "w").write(ARCH)
    record()
    print(json.dumps({"ok": True, "wrote": "checkouts/current/ARCHITECTURE.md"}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
