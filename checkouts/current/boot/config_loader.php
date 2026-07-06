<?php
/* config_loader.php — load a JSON install config and define() CMS CONSTANTS from it.

   Required FIRST by boot/router.php (before CONGRUENCY_SQLITE + Constants_patched.php), so its
   pre-define()s win (PHP is first-writer-wins; Constants_patched.php is now if(!defined())-guarded).
   Absent config -> no-op (the relocatable defaults in router.php + Constants_patched.php take over).

   Config path: $CONGRUENCY_CONFIG env, else <deploy-root>/install.json (deploy-root = parent of boot/).
   Recognised keys:
     "db"        : path to the sqlite file            -> define('CONGRUENCY_SQLITE', ...)   [the one that matters]
     "abs_path"  : deploy root override               -> define('ABS_PATH', .../)           [optional]
     "port"      : serving port                        -> define('CONGRUENCY_PORT', (int))   [pinned for the launcher]
     "host"      : serving host/interface              -> define('CONGRUENCY_HOST', ...)     [optional, default 0.0.0.0]
     "site"      : { "email": .., "order_subject": .. }-> EMAIL_RECIPIANTS / ORDER_SUBJECT_HEADER [optional]
     "constants" : { NAME: VALUE, ... }               -> define(NAME, VALUE) for a real MySQL deploy [optional]
*/
$__cfg = getenv('CONGRUENCY_CONFIG');
if (!$__cfg) {
    $__cfg = dirname(__DIR__) . '/install.json';
}
if (is_file($__cfg)) {
    $__c = json_decode(file_get_contents($__cfg), true);
    if (is_array($__c)) {
        if (!empty($__c['db']) && !defined('CONGRUENCY_SQLITE')) {
            define('CONGRUENCY_SQLITE', $__c['db']);
        }
        if (!empty($__c['abs_path']) && !defined('ABS_PATH')) {
            define('ABS_PATH', rtrim(str_replace('\\', '/', $__c['abs_path']), '/') . '/');
        }
        if (isset($__c['port']) && !defined('CONGRUENCY_PORT')) {
            define('CONGRUENCY_PORT', (int) $__c['port']);
        }
        if (!empty($__c['host']) && !defined('CONGRUENCY_HOST')) {
            define('CONGRUENCY_HOST', $__c['host']);
        }
        if (!empty($__c['site']) && is_array($__c['site'])) {
            if (!empty($__c['site']['email']) && !defined('EMAIL_RECIPIANTS')) {
                define('EMAIL_RECIPIANTS', $__c['site']['email']);
            }
            if (!empty($__c['site']['order_subject']) && !defined('ORDER_SUBJECT_HEADER')) {
                define('ORDER_SUBJECT_HEADER', $__c['site']['order_subject']);
            }
        }
        if (!empty($__c['constants']) && is_array($__c['constants'])) {
            foreach ($__c['constants'] as $__k => $__v) {
                if (is_string($__k) && !defined($__k)) {
                    define($__k, $__v);
                }
            }
        }
    }
}
