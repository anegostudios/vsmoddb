<?php
global $config;

$config["authserver"] = "auth.vintagestory.at";

// If you want to set up a local installation, I recommend
// adding "127.0.0.1	stage.mods.vintagestory.at"  to your hosts file
if (strstr($_SERVER["SERVER_NAME"], "stage.mods.vintagestory.at")) {
	$config["database"] = "moddb";
	$config["databasehost"] = "localhost";
	$config["databaseuser"] = "root";
	$config["databasepassword"] = "";
	$config["serverurl"] = "http://stage.mods.vintagestory.at:8080";
	define("DEBUG", 1);
	define("DEBUGUSER", 1);
} else {
	$config["database"] = "moddb";
	$config["serverurl"] = "https://mods.vintagestory.at";
	
	// Added this way so I can .gitignore this file.
	$filepath = $config["basepath"] . "lib/config.db.priv.php";
	if (file_exists($filepath)) {
		include($filepath); 
	}
	
	if (!defined("DEBUG")) define("DEBUG", 0);
	define("DEBUGUSER", 0);
}
