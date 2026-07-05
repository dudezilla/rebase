<?php
/* auto_prepend override: point the harness router's ABS_PATH (app code root) at
 * THIS submission's snapshot, so the CMS is served from the tournament entry.
 * define() wins over the harness Constants_patched.php's later (duplicate) define. */
if (!defined('ABS_PATH')) {
    define('ABS_PATH', '/home/notificationsforsteven/congruencey/2a2e4f8c142eafaac061fe1cebc3c93cf27849b3/');
}
