<?php
global $config;

$config["authserver"] = "auth.vintagestory.at";
$config["hashsalt"] = "randomizeme";

// If you want to set up a local installation, I recommend
// adding "127.0.0.1	stage.mods.vintagestory.at"  to your hosts file
if (strstr($_SERVER["SERVER_NAME"], "stage.mods.vintagestory.at")) {
	$config["database"] = "moddb";
	$config["databasehost"] = "127.0.0.1";
	$config["databaseuser"] = "vsmoddb";
	$config["databasepassword"] = "vsmoddb";

	// Disable debug for api endpoints
	if (strstr($_SERVER["REQUEST_URI"], "/api/")) {
		define("DEBUG", 0);
		define("DEBUGUSER", 0);
	} else {
		define("DEBUG", 0);
		define("DEBUGUSER", 1);
	}
} else {
	$config["database"] = "moddb";
	
	// Added this way so I can .gitignore this file.
	$filepath = $config["basepath"] . "lib/config.db.priv.php";
	if (file_exists($filepath)) {
		include($filepath); 
	}
	
	if (!defined("DEBUG")) define("DEBUG", 0);
	define("DEBUGUSER", 0);
}