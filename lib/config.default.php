<?php
global $config;

$config["authserver"] = "auth.vintagestory.at";
$config["hashsalt"] = "sakfjsahkdjfhas";

if (strstr($_SERVER["SERVER_NAME"], "stage.mods.vintagestory.at")) {
	$config["database"] = "moddb";
	$config["databasehost"] = "localhost";
	$config["databaseuser"] = "root";
	$config["databasepassword"] = "";
	define("DEBUG", 1);
	define("DEBUGUSER", 1);
	
} else {
	$config["database"] = "moddb";
	$config["databasehost"] = "localhost";
	$config["databaseuser"] = "";
	$config["databasepassword"] = "";
	if (!defined("DEBUG")) define("DEBUG", 0);
	define("DEBUGUSER", 0);
}
