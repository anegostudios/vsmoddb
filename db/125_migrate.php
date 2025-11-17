<?php

// This might require excessive amounts of memory
// use `php -d memory_limit=8G db/121_migrate.php`

$config = [];
$config["basepath"] = dirname(__DIR__).'/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.stage";
$_SERVER["REQUEST_URI"] = "/";
define('DEBUG', 1);
include($config["basepath"]."lib/config.php");
include($config["basepath"]."lib/core.php");

$con->execute("SET character_set_results = 'latin1'"); // we want to read as latin1
$con->execute("SET character_set_client = 'utf8mb4'"); // but write back as utf8mb4

{
	echo 'Table changelogs: ';
	echo 'Setting up temp table... ';
	$con->execute("CREATE TEMPORARY TABLE `_conv_changelogs` (
		`changelogId`                 INT  NOT NULL,
		`text` TEXT     NOT NULL
	) CHARACTER SET utf8mb4");

	$con->execute('LOCK TABLES `changelogs` WRITE');

	$rows = $con->execute('SELECT text, changelogId FROM changelogs');

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_changelogs (text, changelogId) VALUES (?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['text'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['text'], $row['changelogId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute("UPDATE changelogs INNER JOIN _conv_changelogs c on changelogs.changelogId = c.changelogId SET changelogs.text = ''"); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE changelogs MODIFY `text` TEXT CHARACTER SET utf8mb4 NOT NULL');

	echo 'Moving converted data back to og table... ';
	$con->execute(<<<SQL
		UPDATE changelogs a
		INNER JOIN _conv_changelogs c ON c.changelogId = a.changelogId
		SET a.text = c.text
	SQL);

	$con->execute('UNLOCK TABLES');

	$con->completeTrans();
	echo "done.\n";
}
