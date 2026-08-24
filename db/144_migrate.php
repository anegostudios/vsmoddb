<?php

// Backfill: parse existing modPeekResults.rawDependencies into modRelations rows with origin='auto'.
// Skips identifier 'game' (already tracked by modReleaseCompatibleGameVersions) and wildcard '*'.
//
// Run inside the dev container:
//   docker compose -f docker/docker-compose.yml exec php php db/144_migrate.php

$config = [];
$config["basepath"] = dirname(__DIR__).'/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.stage";
$_SERVER["REQUEST_URI"] = "/";
define('DEBUG', 1);
include($config["basepath"]."lib/config.php");
include($config["basepath"]."lib/core.php");
include_once($config["basepath"]."lib/relations.php");

global $con, $user;

$rows = $con->getAll(<<<SQL
	SELECT mpr.fileId, mpr.rawDependencies, mr.releaseId
	FROM modPeekResults mpr
	JOIN files f        ON f.fileId  = mpr.fileId
	JOIN modReleases mr ON mr.assetId = f.assetId
	WHERE mpr.rawDependencies IS NOT NULL
SQL);

if (!$rows) {
	echo "Backfilled 0 releases.\n";
	exit(0);
}

// The sync function records createdByUserId; pick the first admin (roleId = 1) as a placeholder.
$adminUserId = (int)$con->getOne("SELECT userId FROM users WHERE roleId = 1 ORDER BY userId LIMIT 1");
if (!$adminUserId) { fwrite(STDERR, "No admin user found; aborting.\n"); exit(1); }
$user = ['userId' => $adminUserId];

$processed = 0;
foreach ($rows as $row) {
	syncAutoRelationsForRelease((int)$row['releaseId'], $row['rawDependencies']);
	$processed++;
}
echo "Backfilled $processed releases.\n";
