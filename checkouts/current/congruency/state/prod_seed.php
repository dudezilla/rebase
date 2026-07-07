<?php
/* prod_seed.php — build a FRESH PRODUCTION STUB database.

   A functional but empty starter site in the current Georgia styling: a landing/intro (keyed
   `catalog`, the Controller default) + the mandatory `invalid` 404 fallback. Empty store tables
   (Products/Categories/Store_Content_Blocks) for forward-compat. NO dev content (no bug pages,
   no BugDemo/BugReport, no SQLi products, no order-wizard demo rows).

   DB path: $CONGRUENCY_SQLITE env, else __DIR__/congruency.sqlite. A new deploy calls this once.
   Distinct from state/seed.php (the demo/dev DB used by install.py / the ratchet).
*/
$db = getenv('CONGRUENCY_SQLITE');
if (!$db) {
    $db = __DIR__ . '/congruency.sqlite';
}
@unlink($db);
$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE Document_Templates (TemplateID INTEGER PRIMARY KEY, Content TEXT)");
$pdo->exec("CREATE TABLE Documents (DocumentID TEXT PRIMARY KEY, TemplateID INTEGER, Title TEXT, Description TEXT, ContentID INTEGER)");
// empty store tables so the store DAOs never fatal if the storefront is wired later
$pdo->exec("CREATE TABLE Products (`key` INTEGER, category INTEGER, name TEXT, description TEXT, page TEXT, picture TEXT)");
$pdo->exec("CREATE TABLE Categories (`key` INTEGER, name TEXT, description TEXT)");
$pdo->exec("CREATE TABLE Store_Content_Blocks (ContentID INTEGER, Content TEXT)");

// --- current styling (verbatim from the demo) + a production nav (dev links dropped) ---
$nav = '<nav style="margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:1px solid #ccc">'
     . '<a href="?page=catalog">home</a></nav>';
$style = 'body{font-family:Georgia,serif;max-width:960px;margin:3rem auto;padding:0 1rem;'
       . 'line-height:1.6;color:#222;background:#f7f4ee}a{color:#8a5a1a}'
       . 'h1{font-weight:normal}code{background:#eae5d8;padding:1px 4px}';

function page($nav, $style, $body, $prose = false) {
    // The one stylesheet is <<<Style>>> and the nav is <<<SiteMap>>>; $nav/$style are legacy params, ignored.
    // Reading pages pass $prose=true to constrain the text to a comfortable measure (class="prose").
    $inner = $prose ? '<div class="prose">' . $body . '</div>' : $body;
    return "<!DOCTYPE html>\n<html>\n<head>\n<<<TitleTag>>>\n<<<Style>>>\n</head>\n"
         . "<body>\n<nav><<<SiteMap>>></nav>\n$inner\n</body>\n</html>\n";
}

$home = page($nav, $style,
    '<h1>Welcome</h1>'
  . '<p>This is a fresh Congruency site. Add your pages, catalog, and features here.</p>'
  . '<p>It is rendered server-side by the congruency <code>&lt;&lt;&lt;tag&gt;&gt;&gt;</code> engine; '
  . 'the <code>&lt;title&gt;</code> above was produced by <code>TitleTag</code> from this page&rsquo;s title.</p>', true);

$notfound = page($nav, $style,
    '<h1>Page not found</h1><p>That page does not exist. <a href="?page=catalog">Return home</a>.</p>', true);

$tpl = $pdo->prepare("INSERT INTO Document_Templates (TemplateID, Content) VALUES (?, ?)");
$tpl->execute(array(1, $home));
$tpl->execute(array(99, $notfound));

$doc = $pdo->prepare("INSERT INTO Documents (DocumentID, TemplateID, Title, Description, ContentID) VALUES (?,?,?,?,?)");
$doc->execute(array('catalog', 1, 'Welcome', 'home', 0));     // landing (Controller default page)
$doc->execute(array('invalid', 99, 'Page not found', '404', 0));  // mandatory 404 fallback

// Deploy-time admin (optional): if CONGRUENCY_ADMIN_LOGIN + CONGRUENCY_ADMIN_PASSWORD are in the environment,
// provision that admin so a fresh production deploy can use the write forms. NO credential ships in git — the
// deployer injects one at deploy time, e.g.
//   CONGRUENCY_ADMIN_LOGIN=admin CONGRUENCY_ADMIN_PASSWORD=... python3 deploy.py --target /srv/site --version X
// deploy.py runs this script with env=dict(os.environ, ...), so the vars pass through. Stored plaintext (the
// 2006 auth compares plaintext); rotate later with tools/set_admin.py.
$adminLogin = getenv('CONGRUENCY_ADMIN_LOGIN');
$adminPass  = getenv('CONGRUENCY_ADMIN_PASSWORD');
$adminNote  = 'none (set CONGRUENCY_ADMIN_LOGIN/PASSWORD to inject one)';
if ($adminLogin !== false && $adminLogin !== '' && $adminPass !== false && $adminPass !== '') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS Login_Password (Login TEXT, Password TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS User_Group_Mappings (Login TEXT, Group_ID INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS Group_Privileges (Group_ID INTEGER, Module TEXT, Value TEXT)");
    $pdo->prepare("INSERT INTO Login_Password (Login, Password) VALUES (?, ?)")->execute([$adminLogin, $adminPass]);
    $pdo->prepare("INSERT INTO User_Group_Mappings (Login, Group_ID) VALUES (?, 1)")->execute([$adminLogin]);
    $pdo->exec("INSERT INTO Group_Privileges (Group_ID, Module, Value) VALUES (1, 'admin', '1')");
    $adminNote = $adminLogin;
}

echo json_encode(array("ok" => true, "db" => $db, "admin" => $adminNote,
                       "documents" => array("catalog", "invalid"),
                       "tables" => array("Document_Templates", "Documents", "Products", "Categories", "Store_Content_Blocks")));
