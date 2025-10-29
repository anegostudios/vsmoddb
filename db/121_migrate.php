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


$con->execute("SET NAMES 'utf8mb4'");

{
	echo 'Table assets: ';
	echo 'Setting up temp table... ';
	$con->execute("CREATE TEMPORARY TABLE `_conv_assets` (
		`assetId` INT          NOT NULL,
		`name`    VARCHAR(255)     NULL,
		`text`    TEXT             NULL
	) CHARACTER SET utf8mb4");

	$con->execute('LOCK TABLES `assets` WRITE');

	$con->execute("SET CHARACTER SET latin1");
	$rows = $con->execute('SELECT name, text, assetId FROM assets');
	$con->execute("SET CHARACTER SET utf8mb4");

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_assets (name, text, assetId) VALUES (?, ?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['name'], 'UTF-8') && mb_check_encoding($row['text'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['name'], $row['text'], $row['assetId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute('UPDATE assets JOIN _conv_assets c on assets.assetId = c.assetId SET assets.name = NULL, assets.text = NULL'); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE assets MODIFY `name` VARCHAR(255) CHARACTER SET utf8mb4 NULL');
	$con->execute('ALTER TABLE assets MODIFY `text` TEXT         CHARACTER SET utf8mb4 NULL');

	echo 'Moving converted data back to og table... ';
	$rows = $con->execute('SELECT * FROM _conv_assets');
	$preparedInsert = $con->prepare('UPDATE assets SET name = ?, text = ? WHERE assetId = ?');
	foreach($rows as $row) {
		$con->execute($preparedInsert, [ $row['name'], $row['text'], $row['assetId'] ]);
	}

	$con->execute('UNLOCK TABLES');

	$con->completeTrans();
	echo "done.\n";
}

{
	echo 'Table comments: ';
	echo 'Setting up temp table... ';
	$con->execute("CREATE TEMPORARY TABLE `_conv_comments` (
		`commentId` INT          NOT NULL,
		`text`      TEXT         NOT NULL
	) CHARACTER SET utf8mb4");

	$con->execute('LOCK TABLES `comments` WRITE');

	$con->execute("SET CHARACTER SET latin1");
	$rows = $con->execute('SELECT text, commentId FROM comments');
	$con->execute("SET CHARACTER SET utf8mb4");

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_comments (text, commentId) VALUES (?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['text'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['text'], $row['commentId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute("UPDATE comments JOIN _conv_comments c on comments.commentId = c.commentId SET comments.text = ''"); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE comments MODIFY `text` TEXT         CHARACTER SET utf8mb4 NOT NULL');

	echo 'Moving converted data back to og table... ';
	$rows = $con->execute('SELECT * FROM _conv_comments');
	$preparedInsert = $con->prepare('UPDATE comments SET text = ? WHERE commentId = ?');
	foreach($rows as $row) {
		$con->execute($preparedInsert, [ $row['text'], $row['commentId'] ]);
	}

	$con->execute('UNLOCK TABLES');

	$con->completeTrans();
	echo "done.\n";
}

{
	echo 'Table modPeekResults: ';
	echo 'Setting up temp table... ';
	$con->execute("CREATE TEMPORARY TABLE `_conv_modPeekResults` (
		`fileId`          INT     NOT NULL,
		`errors`          TEXT        NULL,
		`description`     TEXT        NULL,
		`rawAuthors`      TEXT        NULL,
		`rawContributors` TEXT        NULL
	) CHARACTER SET utf8mb4");

	$con->execute('LOCK TABLES `modPeekResults` WRITE');

	$con->execute("SET CHARACTER SET latin1");
	$rows = $con->execute('SELECT errors, description, rawAuthors, rawContributors, fileId FROM modPeekResults');
	$con->execute("SET CHARACTER SET utf8mb4");

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_modPeekResults (errors, description, rawAuthors, rawContributors, fileId) VALUES (?, ?, ?, ?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['errors'], 'UTF-8') && mb_check_encoding($row['description'], 'UTF-8') && mb_check_encoding($row['rawAuthors'], 'UTF-8') && mb_check_encoding($row['rawContributors'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['errors'], $row['description'], $row['rawAuthors'], $row['rawContributors'], $row['fileId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute('UPDATE modPeekResults JOIN _conv_modPeekResults c on modPeekResults.fileId = c.fileId SET modPeekResults.errors = NULL, modPeekResults.description = NULL, modPeekResults.rawAuthors = NULL, modPeekResults.rawContributors = NULL'); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE modPeekResults MODIFY `errors`          TEXT CHARACTER SET utf8mb4 NULL');
	$con->execute('ALTER TABLE modPeekResults MODIFY `description`     TEXT CHARACTER SET utf8mb4 NULL');
	$con->execute('ALTER TABLE modPeekResults MODIFY `rawAuthors`      TEXT CHARACTER SET utf8mb4 NULL');
	$con->execute('ALTER TABLE modPeekResults MODIFY `rawContributors` TEXT CHARACTER SET utf8mb4 NULL');

	echo 'Moving converted data back to og table... ';
	$rows = $con->execute('SELECT * FROM _conv_modPeekResults');
	$preparedInsert = $con->prepare('UPDATE modPeekResults SET errors = ?, description = ?, rawAuthors = ?, rawContributors = ? WHERE fileId = ?');
	foreach($rows as $row) {
		$con->execute($preparedInsert, [  $row['errors'], $row['description'], $row['rawAuthors'], $row['rawContributors'], $row['fileId'] ]);
	}

	$con->execute('UNLOCK TABLES');

	$con->completeTrans();
	echo "done.\n";
}

{
	echo 'Table mods: ';
	echo 'Setting up temp table... ';
	$con->execute("CREATE TEMPORARY TABLE `_conv_mods` (
		`modId`           INT          NOT NULL,
		`summary`         VARCHAR(100) NOT NULL
	) CHARACTER SET utf8mb4");

	$con->execute('LOCK TABLES `mods` WRITE');

	$con->execute("SET CHARACTER SET latin1");
	$rows = $con->execute('SELECT summary, modId FROM mods');
	$con->execute("SET CHARACTER SET utf8mb4");

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_mods (summary, modId) VALUES (?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['summary'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['summary'], $row['modId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute("UPDATE mods JOIN _conv_mods c on mods.modId = c.modId SET mods.summary = ''"); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE mods MODIFY `summary` VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL');

	echo 'Moving converted data back to og table... ';
	$rows = $con->execute('SELECT * FROM _conv_mods');
	$preparedInsert = $con->prepare('UPDATE mods SET summary = ? WHERE modId = ?');
	foreach($rows as $row) {
		$con->execute($preparedInsert, [ $row['summary'], $row['modId'] ]);
	}

	$con->execute('UNLOCK TABLES');

	$con->completeTrans();
	echo "done.\n";
}

{
	echo 'Table users: ';
	echo 'Setting up temp table... ';
	$con->execute("CREATE TEMPORARY TABLE `_conv_users` (
		`userId` INT  NOT NULL,
		`bio`    TEXT     NULL
	) CHARACTER SET utf8mb4");

	$con->execute('LOCK TABLES `users` WRITE');

	$con->execute("SET CHARACTER SET latin1");
	$rows = $con->execute('SELECT bio, userId FROM users');
	$con->execute("SET CHARACTER SET utf8mb4");

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_users (bio, userId) VALUES (?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['bio'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['bio'], $row['userId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute("UPDATE users JOIN _conv_users c on users.userId = c.userId SET users.bio = NULL"); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE users MODIFY `bio` TEXT CHARACTER SET utf8mb4 NULL');

	echo 'Moving converted data back to og table... ';
	$rows = $con->execute('SELECT * FROM _conv_users');
	$preparedInsert = $con->prepare('UPDATE users SET bio = ? WHERE userId = ?');
	foreach($rows as $row) {
		$con->execute($preparedInsert, [ $row['bio'], $row['userId'] ]);
	}

	$con->execute('UNLOCK TABLES');

	$con->completeTrans();
	echo "done.\n";
}