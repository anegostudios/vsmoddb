<?php
global $config;

$config["authserver"] = "auth.vintagestory.at";

/**
 * @param string $name
 * @param mixed $default
 */
function _define_default($name, $default)
{
	if(!defined($name)) define($name, $default);
}

// For local development purposes create lib/config.dev.php and put your config in there. That file is automatically ignored by version control.
// Have a look at lib/cdn/bunny.php for relevant CDN config options.

// If you want to set up a local installation, I recommend
// adding "127.0.0.1	mods.vintagestory.stage"  to your hosts file
if (strstr($_SERVER["SERVER_NAME"], "mods.vintagestory.stage")) {
	$filepath = $config["basepath"] . "lib/config.dev.php";
	if (file_exists($filepath)) {
		include($filepath);
	} else {
		define("CDN", "none");
		$config["assetserver"] = "";
		$config["database"] = "moddb";
		$config["databasehost"] = "db";
		$config["databaseuser"] = "vsmoddb";
		$config["databasepassword"] = "vsmoddb";
	}
	$config['noncesalt'] = 'xzy';

	_define_default("DEBUG", 1);
	_define_default("DEBUGUSER", 1);

	_define_default("MOD_SEARCH_INITIAL_RESULTS", 10);
	_define_default("MOD_SEARCH_PAGE_SIZE", 10);
	_define_default("MOD_SEARCH_MAX_LIMIT", 20);

	_define_default("DOWNLOAD_DEDUPLICATION_TIMESPAN", 60); // seconds
} else {
	$config["database"] = "moddb";
	define("CDN", "bunny");

	// Added this way so I can .gitignore this file.
	$filepath = $config["basepath"] . "lib/config.db.priv.php";
	if (file_exists($filepath)) {
		include($filepath);
	}

	_define_default("DEBUG", 0);
	define("DEBUGUSER", 0);

	_define_default("MOD_SEARCH_INITIAL_RESULTS", 200);
	_define_default("MOD_SEARCH_PAGE_SIZE", 200);
	_define_default("MOD_SEARCH_MAX_LIMIT", 20);

	_define_default("DOWNLOAD_DEDUPLICATION_TIMESPAN", 24*3600); // seconds
}

_define_default("DB_READONLY", false);

_define_default('DISABLE_USER_TAGS', true);
define("TAG_MODAUTHOR_VOTES", 1); // Not yet fully implemented, keep this at one.
_define_default("TAG_DOWNVOTED_THRESHOLD", 0);
_define_default("TAG_HIDE_THRESHOLD", -20);
