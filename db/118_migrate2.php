<?php

// :LegacyMalformedModInfo

/*
#!/bin/bash

# script to estimate old file integrity

good=0
bad=0

printf "running ..."

find moddb_backup-25-03-03/files -type f -regex '.*\.\(dll\|cs\|zip\)' -print0 | while read -d $'\0' file; do
	printf "\rrunning ... working on $((good + bad)), $((bad)) were bad so far"
	dotnet moddb/util/modpeek.dll $file > /Dev/null 2>&1
	if [ $? -ne 0 ]; then
		((bad++))
	else
		((good++))
	fi
done

echo done
echo out of $((good + bad)) mods $((bad)) had issues, and $((good)) parsed properly

# working on 15989, 6946 were bad so far
*/

$config = [];
$config["basepath"] = dirname(__DIR__).'/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.stage";
$_SERVER["REQUEST_URI"] = "/";
include($config["basepath"]."lib/config.php");
include($config["basepath"]."lib/core.php");

$oldFilesPath = "{$config["basepath"]}/../moddb_backup-25-03-03/files/";


$fileRows = $con->execute(<<<SQL
	SELECT f.fileId, f.userId, f.assetId, f.name, f.cdnPath
	FROM files f
	LEFT JOIN modPeekResults mpr on mpr.fileId = f.fileId
	WHERE f.assetTypeId = 2 AND f.assetId IS NOT NULL AND mpr.fileId IS NULL -- missing modpeek result
SQL); // ASSETTYPE_RELEASE = 2
foreach($fileRows as $row) {
	echo "Looking for {$row['assetId']}/{$row['name']}... ";

	$localPath = "assets/{$row['assetId']}/{$row['name']}";
	if(file_exists($localPath)) {
		echo "found old file locally. ";
	}
	else {
		echo "did not find locally, fetching from cdn... ";
		$fileData = file_get_contents(formatCdnUrl($row));

		if(!$fileData) {
			echo "failed to find on cdn.\n";
			continue;
		}

		$localPath = tempnam(sys_get_temp_dir(), '');
		$ok = file_put_contents($localPath, $fileData);
		if($ok === false) {
			echo "failed to write local file, disk probably full. aborting.\n";
			break;
		}
	}


	echo "peeking...\n";
	$ok = modpeek($localPath, $modInfo);

	$con->execute('INSERT INTO modPeekResults (fileId, errors, modIdentifier, modVersion, type, side, requiredOnClient, requiredOnServer, networkVersion, description, website, iconPath, rawAuthors, rawContributors, rawDependencies) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
		[$row['fileId'], $modInfo['errors'], $modInfo['id'], $modInfo['version'], $modInfo['type'], $modInfo['side'], $modInfo['requiredOnClient'], $modInfo['requiredOnServer'], $modInfo['networkVersion'], $modInfo['description'], $modInfo['website'], $modInfo['iconPath'], $modInfo['rawAuthors'], $modInfo['rawContributors'], $modInfo['rawDependencies']]
	);

	if(!$ok) {
		// send notifications for broken files
		$con->execute(
			"INSERT INTO notifications (kind, userId, recordId) 
			VALUES (".NOTIFICATION_ONEOFF_MALFORMED_RELEASE.", {$row['userId']}, {$row['assetId']})"
		); // @security, all values are numeric ids and sql inert
	}
}