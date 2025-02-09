<?php
global $config;

$config["authserver"] = "auth.vintagestory.at";

// For local development purposes create lib/config.dev.php and put your config in there. That file is automatically ignored by version control.
// Have a look at lib/cdn/bunny.php for relevant CDN config options.

// If you want to set up a local installation, I recommend
// adding "127.0.0.1	stage.mods.vintagestory.at"  to your hosts file
if (strstr($_SERVER["SERVER_NAME"], "stage.mods.vintagestory.at")) {
	$filepath = $config["basepath"] . "lib/config.dev.php";
	if (file_exists($filepath)) {
		include($filepath); 
	}
	else {
		define("CDN", "none");
		$config["assetserver"] = "";
		$config["database"] = "moddb";
		$config["databasehost"] = "db";
		$config["databaseuser"] = "vsmoddb";
		$config["databasepassword"] = "vsmoddb";
	}
	if (!defined("DEBUG")) define("DEBUG", 1);
	define("DEBUGUSER", 1);
} else {
	$config["database"] = "moddb";
	define("CDN", "bunny");
	
	// Added this way so I can .gitignore this file.
	$filepath = $config["basepath"] . "lib/config.db.priv.php";
	if (file_exists($filepath)) {
		include($filepath); 
	}
	
	if (!defined("DEBUG")) define("DEBUG", 0);
	define("DEBUGUSER", 0);
}