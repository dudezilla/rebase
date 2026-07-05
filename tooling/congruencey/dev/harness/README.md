# congruency-harness

A small dev harness that boots the 2006 [`congruency`](https://github.com/dudezilla/congruency)
CMS on modern PHP and serves it. Not part of the app — this is scaffolding.

```bash
./serve                 # seed the demo DB + serve on http://localhost:8899
./serve --no-seed       # serve without reseeding
```

Expects the code under test at `/home/notificationsforsteven/congruency`
(set in `Constants_patched.php` via `ABS_PATH`).

## Pieces
```
php/php              static PHP 8.3 binary (no system install needed)
shim.php             emulates removed mysql_* over PDO+SQLite; get_magic_quotes_gpc() stub
AutoLoader.php       neutralized stub (PHP 8 forbids declaring __autoLoad())
Constants_patched.php the app's Constants.php with ABS_PATH repointed here
router.php           php -S router: mirrors Execute.php per request; supports ?fresh=1
seed.php             builds congruency.sqlite: documents, Products, and demo forms
serve                seed + launch
```

## Pages
`?page=catalog` · `about` · `bugs` (self-hosted bug catalog + live BugDemo) ·
`forms` (live contact + radio-poll forms).

## Dev loop
- Edit an **existing** `.php` in the app → just refresh (no opcache; re-parsed each request).
- Add a **new tag file / form / cached object** → append **`?fresh=1`** to any URL once.
  It drops `$_SESSION['POM']` so `Initialize_POM` re-scans `TAGS_DIR` and rebuilds the
  `FormManager`, then packs the fresh graph back (so it sticks). Response carries
  `X-Congruency-Fresh: rebuilt`. Dev-only convenience — do not ship it.
