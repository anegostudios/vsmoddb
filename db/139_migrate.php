<?php

// This might require excessive amounts of memory
// use `php -d memory_limit=8G db/139_migrate.php`

$config = [];
$config["basepath"] = dirname(__DIR__).'/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.stage";
$_SERVER["REQUEST_URI"] = "/";
define('DEBUG', 1);
include($config["basepath"]."lib/config.php");
include($config["basepath"]."lib/core.php");

{
	echo 'Translating changelogs... ';

	//$con->execute('LOCK TABLES `_changelogs` READ, `auditLogs` WRITE');

	$rows = $con->execute('SELECT assetId, userId, UNIX_TIMESTAMP(created) AS `created`, `text` FROM _changelogs');

	$preparedInsert = $con->prepare('INSERT INTO auditLogs (kind, flags, referenceId, initiatorUserId, created, info) VALUES ('.AUDIT_LOG_KIND_LEGACY.', 0, ?, ?, FROM_UNIXTIME(?), ?)');
	foreach($rows as $i => $row) {
		$individualChanges = preg_split('/\r\n|\n\r/', $row['text']);

		foreach($individualChanges as $change) {
			$con->execute($preparedInsert, [$row['assetId'] ?? 0, $row['userId'], $row['created'], $change]); // does not line up with other log kinds
		}

		if(($i++ % 1000) === 0) echo ".";
	}

	//$con->execute('UNLOCK TABLES');

	echo "done.\n";
}
