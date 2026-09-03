<?php

// This might require excessive amounts of memory
// use `php -d memory_limit=8G db/132_migrate.php`

$config = [];
$config["basepath"] = dirname(dirname(__DIR__)).'/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.stage";
$_SERVER["REQUEST_URI"] = "/";
define('DEBUG', 1);
include($config["basepath"]."lib/config.php");
include($config["basepath"]."lib/core.php");

{
	echo 'Generating short comment texts... ';

	$con->execute('LOCK TABLES `comments` WRITE');

	$rows = $con->execute('SELECT commentId, text FROM comments');

	$preparedInsert = $con->prepare('UPDATE comments SET textShort = ? WHERE commentId = ?');
	foreach($rows as $i => $row) {
		$commentTextShort = mb_substr(textContent($row['text']), 0, 255); // stored for comment replies
		$con->execute($preparedInsert, [$commentTextShort, $row['commentId']]);

		if(($i++ % 1000) === 0) echo ".";
	}

	$con->execute('UNLOCK TABLES');

	echo "done.\n";
}
