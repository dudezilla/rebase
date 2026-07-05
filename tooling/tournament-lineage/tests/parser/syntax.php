<?php
/* The tag-syntax constants the parser needs, isolated so the corpus runs with
 * no DB/app config. Mirrors etc/Constants.php (underscore-permitting variant). */
if (!defined('KEY_PREFIX'))        define("KEY_PREFIX","<<<");
if (!defined('KEY_SUFFIX'))        define("KEY_SUFFIX",">>>");
if (!defined('FUNCTION_ARGUMENT')) define("FUNCTION_ARGUMENT","/\([A-Za-z0-9_]*\)/");
if (!defined('GET_TAG_IDENTIFIER'))define("GET_TAG_IDENTIFIER","/([a-zA-Z0-9_]+\s?(?=\(\s?[a-zA-Z0-9_]*\s?\)))|(\s?[a-zA-Z0-9_]+\s?)/");
// Content-scanner parts (Tag_Wrapper::identify_tag composes these).
if (!defined('TAG_KEY_PREFIX'))    define("TAG_KEY_PREFIX","/<<<");
if (!defined('TAG_KEY_SUFFIX'))    define("TAG_KEY_SUFFIX","\>>>/");
if (!defined('FUNCTION_NAME'))     define("FUNCTION_NAME","[a-zA-Z_]+");        // NB: no digits in scanned name
if (!defined('FUNCTION_ARGUMENTS'))define("FUNCTION_ARGUMENTS","(\([A-Za-z0-9_]*\))*");
