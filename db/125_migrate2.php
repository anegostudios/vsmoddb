<?php

$config = [];
$config["basepath"] = dirname(__DIR__).'/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.stage";
$_SERVER["REQUEST_URI"] = "/";
define('DEBUG', 1);
include($config["basepath"]."lib/config.php");
include($config["basepath"]."lib/core.php");

$rows = $con->execute("SELECT fileId, rawDependencies FROM modPeekResults WHERE rawDependencies IS NOT NULL AND rawDependencies != ''");

$con->startTrans();

$preparedInsert = $con->prepare('INSERT INTO modReleaseFileDependencies (fileId, identifier, minVersion) VALUES (?, ?, ?)');
foreach($rows as $row) {
	foreach(explode(', ', $row['rawDependencies']) as $dep) {
		splitOnce($dep, '@', $depIdent, $depVer);
		$depVer = $depVer === '' ? 0 : compileSemanticVersion($depVer);
		$con->execute($preparedInsert, [ $row['fileId'], $depIdent, $depVer ]);
	}
}

$con->completeTrans();

