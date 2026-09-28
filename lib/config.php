<?php

define('SRC_ROOT', dirname(__DIR__));

/**
 * @param string $name
 * @param mixed $default
 */
function _define_default($name, $default)
{
	if(!defined($name)) define($name, $default);
}

// To complete the setup, create lib/config.priv.php and put your config in there. That file is automatically ignored by version control.
// Have a look at lib/cdn/bunny.php for relevant CDN config options.

// If you want to set up a local installation, I recommend adding "127.0.0.1 mods.vintagestory.stage" to your hosts file.

if (!file_exists(__DIR__.'/config.priv.php')) {
	exit("See '".SRC_ROOT."/lib/config.php' and create '".SRC_ROOT."/lib/config.priv.php'.");
}

/** Required defines in .priv:
 * 
 * string      DB_HOST
 * string      DB_DATABASE
 * string      DB_USER
 * string|null DB_PASS
 * string      CSP_SALT
 * 
 * string      WH_SECRET_GV (if you want the gameversion creation webhook to work)
 */
require(__DIR__.'/config.priv.php');

_define_default('DEBUG', 0);
_define_default('DEBUGUSER', 0);
_define_default('DB_READONLY', false);

_define_default('CDN', 'none');
_define_default('CDN_ASSETSERVER_BASE_URL', '');

_define_default('AUTHSERVER_BASE_URL', 'auth.vintagestory.at');

_define_default("MOD_SEARCH_INITIAL_RESULTS", 200);
_define_default("MOD_SEARCH_PAGE_SIZE", 200);

_define_default("DOWNLOAD_DEDUPLICATION_TS_SECS", 24*3600);



_define_default('DISABLE_USER_TAGS', true);
define("TAG_MODAUTHOR_VOTES", 1); // Not yet fully implemented, keep this at one.
_define_default("TAG_DOWNVOTED_THRESHOLD", 0);
_define_default("TAG_HIDE_THRESHOLD", -20);

_define_default("MOD_SEARCH_VALIDATE_LIMIT_MIN", 1);
_define_default("MOD_SEARCH_VALIDATE_LIMIT_MAX", 20);

_define_default("MOD_REPORT_DEDUPLICATION_TS_DAYS", 7); // days
_define_default("MOD_REPORT_LIMIT_PER_WEEK", 10); // counts open and dismissed reports, but not accepted ones.
_define_default("MOD_REPORT_LIMIT_LOW_EFFORT_WEIGHT", .1); // The amount of reports a "low effort ai" report is counted as for the sake of rate limiting.

_define_default("COMMENT_REPORT_DEDUPLICATION_TS_DAYS", 7); // days
_define_default("COMMENT_REPORT_LIMIT_PER_WEEK", 50); // counts open and dismissed reports, but not accepted ones.
