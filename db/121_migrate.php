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
	echo 'Table assets: ';
	echo 'Setting up temp table... ';
	$con->execute("CREATE TEMPORARY TABLE `_conv_assets` (
		`assetId` INT          NOT NULL,
		`name`    VARCHAR(255)     NULL,
		`text`    TEXT             NULL
	) CHARACTER SET utf8mb4");

	$con->execute('LOCK TABLES `assets` WRITE');

	$rows = $con->execute('SELECT name, text, assetId FROM assets');

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_assets (name, text, assetId) VALUES (?, ?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['name'], 'UTF-8') && mb_check_encoding($row['text'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['name'], $row['text'], $row['assetId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute('UPDATE assets INNER JOIN _conv_assets c on assets.assetId = c.assetId SET assets.name = NULL, assets.text = NULL'); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE assets MODIFY `name` VARCHAR(255) CHARACTER SET utf8mb4 NULL');
	$con->execute('ALTER TABLE assets MODIFY `text` TEXT         CHARACTER SET utf8mb4 NULL');

	echo 'Moving converted data back to og table... ';
	$con->execute(<<<SQL
		UPDATE assets a
		INNER JOIN _conv_assets c ON c.assetId = a.assetId
		SET a.name = c.name, a.text = c.text
	SQL);

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

	$rows = $con->execute('SELECT text, commentId FROM comments');

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_comments (text, commentId) VALUES (?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['text'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['text'], $row['commentId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute("UPDATE comments INNER JOIN _conv_comments c on comments.commentId = c.commentId SET comments.text = ''"); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE comments MODIFY `text` TEXT         CHARACTER SET utf8mb4 NOT NULL');

	echo 'Moving converted data back to og table... ';
	$con->execute(<<<SQL
		UPDATE comments a
		INNER JOIN _conv_comments c ON c.commentId = a.commentId
		SET a.text = c.text
	SQL);
	
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

	$rows = $con->execute('SELECT errors, description, rawAuthors, rawContributors, fileId FROM modPeekResults');

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_modPeekResults (errors, description, rawAuthors, rawContributors, fileId) VALUES (?, ?, ?, ?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['errors'], 'UTF-8') && mb_check_encoding($row['description'], 'UTF-8') && mb_check_encoding($row['rawAuthors'], 'UTF-8') && mb_check_encoding($row['rawContributors'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['errors'], $row['description'], $row['rawAuthors'], $row['rawContributors'], $row['fileId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute('UPDATE modPeekResults INNER JOIN _conv_modPeekResults c on modPeekResults.fileId = c.fileId SET modPeekResults.errors = NULL, modPeekResults.description = NULL, modPeekResults.rawAuthors = NULL, modPeekResults.rawContributors = NULL'); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE modPeekResults MODIFY `errors`          TEXT CHARACTER SET utf8mb4 NULL');
	$con->execute('ALTER TABLE modPeekResults MODIFY `description`     TEXT CHARACTER SET utf8mb4 NULL');
	$con->execute('ALTER TABLE modPeekResults MODIFY `rawAuthors`      TEXT CHARACTER SET utf8mb4 NULL');
	$con->execute('ALTER TABLE modPeekResults MODIFY `rawContributors` TEXT CHARACTER SET utf8mb4 NULL');

	echo 'Moving converted data back to og table... ';
	$con->execute(<<<SQL
		UPDATE modPeekResults a
		INNER JOIN _conv_modPeekResults c ON c.fileId = a.fileId
		SET a.errors = c.errors, a.description = c.description, a.rawAuthors = c.rawAuthors, a.rawContributors = c.rawContributors
	SQL);

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

	$rows = $con->execute('SELECT summary, modId FROM mods');

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_mods (summary, modId) VALUES (?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['summary'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['summary'], $row['modId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute("UPDATE mods INNER JOIN _conv_mods c on mods.modId = c.modId SET mods.summary = ''"); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE mods MODIFY `summary` VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL');

	echo 'Moving converted data back to og table... ';
	$con->execute(<<<SQL
		UPDATE mods a
		INNER JOIN _conv_mods c ON c.modId = a.modId
		SET a.summary = c.summary
	SQL);

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

	$rows = $con->execute('SELECT bio, userId FROM users');

	$con->startTrans();

	echo 'Transcoding data... ';
	$preparedInsert = $con->prepare('INSERT INTO _conv_users (bio, userId) VALUES (?, ?)');
	foreach($rows as $row) {
		if(mb_check_encoding($row['bio'], 'UTF-8')) {
			$con->execute($preparedInsert, [ $row['bio'], $row['userId'] ]);
		}
	}

	echo 'Clearing og table... ';
	$con->execute("UPDATE users INNER JOIN _conv_users c on users.userId = c.userId SET users.bio = NULL"); // clear to make the conversion faster
	echo 'Changing character set on og table... ';
	$con->execute('ALTER TABLE users MODIFY `bio` TEXT CHARACTER SET utf8mb4 NULL');

	echo 'Moving converted data back to og table... ';
	$con->execute(<<<SQL
		UPDATE users a
		INNER JOIN _conv_users c ON c.userId = a.userId
		SET a.bio = c.bio
	SQL);

	$con->execute('UNLOCK TABLES');

	$con->completeTrans();
	echo "done.\n";
}