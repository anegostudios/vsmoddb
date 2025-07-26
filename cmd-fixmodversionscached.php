<?php

$config = array();
$config["basepath"] = getcwd() . '/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.at";
define("DEBUG", 1);

include("lib/core.php");
include($config['basepath'] . 'lib/edit-release.php');

$modids = $con->getCol("select modId from Mods");

foreach ($modids as $modid) {
	updateGameVersionsCached($modid);
}
