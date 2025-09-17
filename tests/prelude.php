<?php

$config = [];
$config["basepath"] = dirname(__DIR__).'/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.stage";
$_SERVER["REQUEST_URI"] = "/";
define("DEBUG", 1);
define("TESTING", 1);

include($config['basepath'] . "lib/config.php");
include($config['basepath'] . "lib/core.php");
