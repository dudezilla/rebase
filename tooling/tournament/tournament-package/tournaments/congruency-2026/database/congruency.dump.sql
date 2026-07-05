-- deterministic dump of congruency.sqlite
PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE Document_Templates (TemplateID INTEGER PRIMARY KEY, Content TEXT);
INSERT INTO "Document_Templates" ("TemplateID","Content") VALUES ('1','<!DOCTYPE html>
<html>
<head>
<<<TitleTag>>>
<style>body{font-family:Georgia,serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#222;background:#f7f4ee}a{color:#8a5a1a}h1{font-weight:normal}code{background:#eae5d8;padding:1px 4px}</style>
</head>
<body>
<nav style="margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:1px solid #ccc"><a href="?page=catalog">home</a> &nbsp;&middot;&nbsp; <a href="?page=about">about</a> &nbsp;&middot;&nbsp; <a href="?page=bugs">bug report</a> &nbsp;&middot;&nbsp; <a href="?page=forms">forms</a> &nbsp;&middot;&nbsp; <a href="?page=config">order wizard</a> &nbsp;&middot;&nbsp; <a href="?page=nope">broken link</a></nav>
<h1>Congruency v0 &mdash; it executes (2026)</h1><p>Nineteen years after <code>Install.txt</code> said <em>"this version does not execute"</em>, you are looking at it execute &mdash; served by PHP&#39;s built-in server, rendered through the original <code>Controller::control()</code> front controller, its custom autoloader, the persistent-object manager, and the &lt;&lt;&lt;tag&gt;&gt;&gt; engine.</p><p>The <code>&lt;title&gt;</code> above was produced by <code>TitleTag</code>, loaded on demand by your tag loader.</p>
</body>
</html>
');
INSERT INTO "Document_Templates" ("TemplateID","Content") VALUES ('2','<!DOCTYPE html>
<html>
<head>
<<<TitleTag>>>
<style>body{font-family:Georgia,serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#222;background:#f7f4ee}a{color:#8a5a1a}h1{font-weight:normal}code{background:#eae5d8;padding:1px 4px}</style>
</head>
<body>
<nav style="margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:1px solid #ccc"><a href="?page=catalog">home</a> &nbsp;&middot;&nbsp; <a href="?page=about">about</a> &nbsp;&middot;&nbsp; <a href="?page=bugs">bug report</a> &nbsp;&middot;&nbsp; <a href="?page=forms">forms</a> &nbsp;&middot;&nbsp; <a href="?page=config">order wizard</a> &nbsp;&middot;&nbsp; <a href="?page=nope">broken link</a></nav>
<h1>About this resurrection</h1><p>Original code: Steven Peterson, &copy; 2006, GPLv2.</p><p>Running unmodified on a static PHP 8.3 binary. The only concessions: a <code>mysql_*</code>&rarr;SQLite shim, a <code>get_magic_quotes_gpc()</code> stub, and a neutered <code>AutoLoader.php</code> (PHP 8 forbids declaring <code>__autoLoad()</code>). Everything else is 2006 code.</p><p>Click <a href="?page=nope">the broken link</a> to watch the DAO&#39;s "invalid key" fallback path fire.</p>
</body>
</html>
');
INSERT INTO "Document_Templates" ("TemplateID","Content") VALUES ('3','<!DOCTYPE html>
<html>
<head>
<<<TitleTag>>>
<style>body{font-family:Georgia,serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#222;background:#f7f4ee}a{color:#8a5a1a}h1{font-weight:normal}code{background:#eae5d8;padding:1px 4px}</style>
</head>
<body>
<nav style="margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:1px solid #ccc"><a href="?page=catalog">home</a> &nbsp;&middot;&nbsp; <a href="?page=about">about</a> &nbsp;&middot;&nbsp; <a href="?page=bugs">bug report</a> &nbsp;&middot;&nbsp; <a href="?page=forms">forms</a> &nbsp;&middot;&nbsp; <a href="?page=config">order wizard</a> &nbsp;&middot;&nbsp; <a href="?page=nope">broken link</a></nav>
<h1>No such page</h1><p>The <code>DocumentID</code> you asked for isn&#39;t in the database, so <code>DocumentDAO</code> fell back to the <code>"invalid"</code> document &mdash; exactly as the 2006 code intended.</p><p><a href="?page=catalog">&larr; back home</a></p>
</body>
</html>
');
INSERT INTO "Document_Templates" ("TemplateID","Content") VALUES ('4','<!DOCTYPE html>
<html>
<head>
<<<TitleTag>>>
<style>body{font-family:Georgia,serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#222;background:#f7f4ee}a{color:#8a5a1a}h1{font-weight:normal}code{background:#eae5d8;padding:1px 4px}</style>
</head>
<body>
<nav style="margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:1px solid #ccc"><a href="?page=catalog">home</a> &nbsp;&middot;&nbsp; <a href="?page=about">about</a> &nbsp;&middot;&nbsp; <a href="?page=bugs">bug report</a> &nbsp;&middot;&nbsp; <a href="?page=forms">forms</a> &nbsp;&middot;&nbsp; <a href="?page=config">order wizard</a> &nbsp;&middot;&nbsp; <a href="?page=nope">broken link</a></nav>
<h1>congruency, reviewed by congruency</h1><p>This page is served by the very CMS it documents. Everything below the rule is emitted by two components dropped into <code>invocators/tags/dev/</code> and invoked from this document&#39;s stored content with <code>&lt;&lt;&lt;BugDemo&gt;&gt;&gt;</code> and <code>&lt;&lt;&lt;BugReport&gt;&gt;&gt;</code> tags.</p><h2 style="font-weight:normal">Two bugs, running live</h2><p>The tags below don&#39;t <em>describe</em> these bugs &mdash; they execute the real 2006 code and show what happens:</p><<<BugDemo(sqli)>>><<<BugDemo(lifo)>>><h2 style="font-weight:normal">The full catalog</h2><<<BugReport>>><p style="margin-top:1rem;font-size:.8rem;color:#777">Rendered through Controller &rarr; DocumentManager &rarr; Tag_Wrapper::execute_all_tags &rarr; BugReport / BugDemo. Turtles all the way down.</p>
</body>
</html>
');
INSERT INTO "Document_Templates" ("TemplateID","Content") VALUES ('5','<!DOCTYPE html>
<html>
<head>
<<<TitleTag>>>
<style>body{font-family:Georgia,serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#222;background:#f7f4ee}a{color:#8a5a1a}h1{font-weight:normal}code{background:#eae5d8;padding:1px 4px}</style>
</head>
<body>
<nav style="margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:1px solid #ccc"><a href="?page=catalog">home</a> &nbsp;&middot;&nbsp; <a href="?page=about">about</a> &nbsp;&middot;&nbsp; <a href="?page=bugs">bug report</a> &nbsp;&middot;&nbsp; <a href="?page=forms">forms</a> &nbsp;&middot;&nbsp; <a href="?page=config">order wizard</a> &nbsp;&middot;&nbsp; <a href="?page=nope">broken link</a></nav>
<h1>Forms, live</h1><p>These are real forms assembled by the resurrected form builder: <code>FormManager</code> &rarr; <code>StandardForm</code> &rarr; the form elements, all read from a <code>forms</code> table and rendered by a <code>&lt;&lt;&lt;FormTag&gt;&gt;&gt;</code> component. Submit them and they validate and remember your input.</p><h2 style="font-weight:normal">Contact</h2><<<FormTag(contact)>>><h2 style="font-weight:normal">Flavour poll <span style="font-size:.7em;color:#888">(RadioSelect &mdash; the <code>split()</code>-fixed path)</span></h2><<<FormTag(poll)>>>
</body>
</html>
');
INSERT INTO "Document_Templates" ("TemplateID","Content") VALUES ('6','<!DOCTYPE html>
<html>
<head>
<<<TitleTag>>>
<style>body{font-family:Georgia,serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#222;background:#f7f4ee}a{color:#8a5a1a}h1{font-weight:normal}code{background:#eae5d8;padding:1px 4px}</style>
</head>
<body>
<nav style="margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:1px solid #ccc"><a href="?page=catalog">home</a> &nbsp;&middot;&nbsp; <a href="?page=about">about</a> &nbsp;&middot;&nbsp; <a href="?page=bugs">bug report</a> &nbsp;&middot;&nbsp; <a href="?page=forms">forms</a> &nbsp;&middot;&nbsp; <a href="?page=config">order wizard</a> &nbsp;&middot;&nbsp; <a href="?page=nope">broken link</a></nav>
<h1>Configure your widget <span style="font-size:.6em;color:#888">step 1 of 3</span></h1><p>Pick your options and press Continue. The form has a single submit button; its <code>action</code> chains straight to the order form &mdash; one form per step, chained one after the next.</p><<<ConfigFormTag(6)>>>
</body>
</html>
');
INSERT INTO "Document_Templates" ("TemplateID","Content") VALUES ('7','<!DOCTYPE html>
<html>
<head>
<<<TitleTag>>>
<style>body{font-family:Georgia,serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#222;background:#f7f4ee}a{color:#8a5a1a}h1{font-weight:normal}code{background:#eae5d8;padding:1px 4px}</style>
</head>
<body>
<nav style="margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:1px solid #ccc"><a href="?page=catalog">home</a> &nbsp;&middot;&nbsp; <a href="?page=about">about</a> &nbsp;&middot;&nbsp; <a href="?page=bugs">bug report</a> &nbsp;&middot;&nbsp; <a href="?page=forms">forms</a> &nbsp;&middot;&nbsp; <a href="?page=config">order wizard</a> &nbsp;&middot;&nbsp; <a href="?page=nope">broken link</a></nav>
<h1>Your details <span style="font-size:.6em;color:#888">step 2 of 3</span></h1><p>You landed here because the config form&#39;s action pointed at this page. Your configuration is summarised above the form.</p><<<OrderFormTag>>>
</body>
</html>
');
INSERT INTO "Document_Templates" ("TemplateID","Content") VALUES ('8','<!DOCTYPE html>
<html>
<head>
<<<TitleTag>>>
<style>body{font-family:Georgia,serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#222;background:#f7f4ee}a{color:#8a5a1a}h1{font-weight:normal}code{background:#eae5d8;padding:1px 4px}</style>
</head>
<body>
<nav style="margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:1px solid #ccc"><a href="?page=catalog">home</a> &nbsp;&middot;&nbsp; <a href="?page=about">about</a> &nbsp;&middot;&nbsp; <a href="?page=bugs">bug report</a> &nbsp;&middot;&nbsp; <a href="?page=forms">forms</a> &nbsp;&middot;&nbsp; <a href="?page=config">order wizard</a> &nbsp;&middot;&nbsp; <a href="?page=nope">broken link</a></nav>
<h1>Order complete <span style="font-size:.6em;color:#888">step 3 of 3</span></h1><p>The order form&#39;s action chained to this page. <code>Orderer</code> logged the order to the database, read it back, and would have emailed it. Confirmation:</p><<<OrdererTag>>>
</body>
</html>
');
CREATE TABLE Documents (
    DocumentID  TEXT PRIMARY KEY,
    TemplateID  INTEGER,
    Title       TEXT,
    Description TEXT,
    ContentID   INTEGER
);
INSERT INTO "Documents" ("DocumentID","TemplateID","Title","Description","ContentID") VALUES ('about','2','About &middot; Congruency','About page','2');
INSERT INTO "Documents" ("DocumentID","TemplateID","Title","Description","ContentID") VALUES ('bugs','4','Bug report &middot; Congruency','Self-hosted bug catalog','4');
INSERT INTO "Documents" ("DocumentID","TemplateID","Title","Description","ContentID") VALUES ('catalog','1','Congruency Lives','Resurrected college CMS','1');
INSERT INTO "Documents" ("DocumentID","TemplateID","Title","Description","ContentID") VALUES ('config','6','Configure &middot; Congruency','Config step','6');
INSERT INTO "Documents" ("DocumentID","TemplateID","Title","Description","ContentID") VALUES ('forms','5','Forms &middot; Congruency','Live form demo','5');
INSERT INTO "Documents" ("DocumentID","TemplateID","Title","Description","ContentID") VALUES ('invalid','3','Page not found','404 fallback','3');
INSERT INTO "Documents" ("DocumentID","TemplateID","Title","Description","ContentID") VALUES ('order','7','Order &middot; Congruency','Order step','7');
INSERT INTO "Documents" ("DocumentID","TemplateID","Title","Description","ContentID") VALUES ('thanks','8','Order complete &middot; Congruency','Order confirmation','8');
CREATE TABLE Products (`key` INTEGER, category INTEGER, name TEXT, description TEXT, page TEXT, picture TEXT);
INSERT INTO "Products" ("key","category","name","description","page","picture") VALUES ('1','5','Widget','a','','');
INSERT INTO "Products" ("key","category","name","description","page","picture") VALUES ('2','5','Gadget','b','','');
INSERT INTO "Products" ("key","category","name","description","page","picture") VALUES ('3','9','Secret Prototype','c','','');
CREATE TABLE forms (
    `key` INTEGER PRIMARY KEY, name TEXT, formName TEXT, elementString TEXT,
    implements TEXT, selection TEXT, required INTEGER, `order` INTEGER);
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('1','fullName','contact','','TextField','Your name:','1','1');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('10','email','OrderForm','','TextField','Email:','1','2');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('11','phoneNumber','OrderForm','','TextField','Phone:','0','3');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('12','comments','OrderForm','','TextBox','Notes:','0','4');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('2','email','contact','','TextField','Email address:','1','2');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('3','message','contact','','TextBox','Your message:','0','3');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('4','flavour','poll','<<Vanilla>><<Chocolate>><<Strawberry>>','RadioSelect','Pick a flavour:','1','1');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('5','FCE','ConfigForm-6','<action=''?page=order''><oncomplete=''Configured. Enter your contact details below.''><incomplete=''Choose your options, then press Continue.''>','FormConfigElement','','0','0');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('6','IVE','ConfigForm-6','<<## Price=100.00 ## Description=Base Widget ## >>','ConfigFormInitialValue','Base item:','0','1');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('7','colour','ConfigForm-6','<<## Price=10.00 ## Description=Racing Red ## >><<## Price=20.00 ## Description=Deep Blue ## >>','ConfigFormRadioSelect','Choose a colour:','1','2');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('8','FCE','OrderForm','<action=''?page=thanks''><oncomplete=''Thanks. Review your confirmed order:''><incomplete=''Please fill in the required fields.''>','FormConfigElement','','0','0');
INSERT INTO "forms" ("key","name","formName","elementString","implements","selection","required","order") VALUES ('9','nameField','OrderForm','','TextField','Your name:','1','1');
CREATE TABLE orders (
    orderNumber INTEGER PRIMARY KEY AUTOINCREMENT,
    clientName TEXT, itemDescription TEXT, clientPhone TEXT, comment TEXT,
    clientEmail TEXT, unixKey INTEGER, date TEXT);
COMMIT;
