<?php

$config = [];
$config["basepath"] = dirname(__DIR__).'/';
$_SERVER["SERVER_NAME"] = "stage.mods.vintagestory.at";
$_SERVER["REQUEST_URI"] = "/";
include($config["basepath"]."lib/config.php");
include($config["basepath"]."lib/core.php");

$mods = $con->execute('
	SELECT m.modid, a.`text`
	FROM `mod` m
	JOIN asset a ON a.assetid = m.assetid
');
$preparedInsert = $con->prepare('UPDATE `mod` SET descriptionsearchable = ? WHERE modid = ?');
foreach($mods as $mod) {
	$con->execute($preparedInsert, [textContent($mod['text']), $mod['modid']]);
}

echo "OK\n";
