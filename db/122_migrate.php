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
	echo 'Table mods: ';
	echo 'Setting up temp table... ';
	$con->execute("CREATE TEMPORARY TABLE `_conv_mods` (
		`modId`                 INT  NOT NULL,
		`descriptionSearchable` TEXT     NULL
	) CHARACTER SET utf8mb4");

	$con->execute('LOCK TABLES `mods` WRITE');

	$rows = $con->execute('SELECT descriptionSearchable, modId FROM mods');

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_mods (descriptionSearchable, modId) VALUES (?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['descriptionSearchable'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['descriptionSearchable'], $row['modId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute("UPDATE mods INNER JOIN _conv_mods c on mods.modId = c.modId SET mods.descriptionSearchable = NULL"); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE mods MODIFY `descriptionSearchable` TEXT CHARACTER SET utf8mb4 NULL');

	echo 'Moving converted data back to og table... ';
	$con->execute(<<<SQL
		UPDATE mods a
		INNER JOIN _conv_mods c ON c.modId = a.modId
		SET a.descriptionSearchable = c.descriptionSearchable
	SQL);

	$con->execute('UNLOCK TABLES');

	$con->completeTrans();
	echo "done.\n";
}
