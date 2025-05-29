<?php

/**
 * @security: Does not perform validation!
 * @param array{modid:int, type:'mod'|'tool'|'other'} $mod The mod the release is to be associated with.
 * @param array{text:string, modidstr?:string, modversion:int} $newData
 * @param int[] $newCompatibleGameVersions
 * @param array{assetid:int, fileid:int} $file
 * @return int The assetId of the newly created release. Zero on failure, very unlikely to fail.
 */
function createNewRelease($mod, $newData, $newCompatibleGameVersions, $file)
{
	global $con, $user;

	$con->startTrans();

	$con->execute('
		INSERT INTO asset (assettypeid, numsaved, statusid, created, text, createdbyuserid, editedbyuserid)
		VALUES(2, 1, 2, NOW(), ?, ?, ?)
	', [$newData['text'], $user['userid'], $user['userid']]);
	$assetId = $con->insert_ID();
	
	$con->execute('INSERT INTO `release` (created, modid, assetid, modidstr, modversion) VALUES(NOW(), ?, ?, ?, ?)', [$mod['modid'], $assetId, $newData['modidstr'] ?? NULL, $newData['modversion']]);
	$releaseId = $con->insert_ID();

	// attach hovering files
	if($file['assetid'] == 0) {
		$con->execute('UPDATE file SET assetid = ? WHERE fileid = ?', [$assetId, $file['fileid']]);
	}

	$changeToLog = 'Created new release v'.formatSemanticVersion($newData['modversion']);

	if($mod['type'] === 'mod') {
		$folded = implode(',', array_map(fn($v) => "($releaseId, $v)", $newCompatibleGameVersions));
		// @security: Version numbers and releaseIds are numeric and therefore SQL Inert.
		$con->execute("INSERT INTO ModReleaseCompatibleGameVersions (releaseId, gameVersion) VALUES $folded");

		$changeToLog .= " for {$newData['modidstr']} with compatible game versions ".formatGrammaticallyCorrectEnumeration(array_map('formatSemanticVersion', $newCompatibleGameVersions));
	}

	logAssetChanges([$changeToLog], $assetId);

	updateGameVersionsCached($mod['modid']);
	$con->execute('UPDATE `mod` set lastreleased = NOW() WHERE modid = ?', [$mod['modid']]);

	$con->Execute("
		INSERT INTO notification (userid, type, recordid)
		SELECT userid, 'newrelease', ?
		FROM follow
		WHERE modid = ? AND flags & ".FOLLOW_FLAG_CREATE_NOTIFICATIONS."
	", [$mod['modid'], $mod['modid']]);

	return $con->completeTrans() ? $assetId : 0;
}

/**
 * @security: Does not perform validation!
 * @param array{modid:int, type:'mod'|'tool'|'other'} $mod The mod the release is to be associated with.
 * @param array{releaseid:int, assetid:int, text:string, modidstr:string|null, modversion:int} $existingRelease
 * @param array{text:string, modidstr?:string, modversion:int} $newData
 * @param int[] $newCompatibleGameVersions
 * @param array{assetid:int, fileid:int} $file Unused for now
 * @return bool Indicates if the release did in fact get created. Very unlikely to not succeed.
 */
function updateRelease($mod, $existingRelease, $newData, $newCompatibleGameVersions, $file)
{
	global $con, $user;

	$actualChanges = [];
	foreach($newData as $k => $newVal) {
		if($existingRelease[$k] != $newVal) $actualChanges[$k] = $newVal;
	}

	$compatibleGameVersionsChange = false;
	if($mod['type'] === 'mod') {
		$oldCompatibleGameVersions = array_map('intval', $con->getCol('SELECT gameVersion FROM ModReleaseCompatibleGameVersions WHERE releaseId = ? ORDER BY gameVersion', [$existingRelease['releaseid']]));
		sort($newCompatibleGameVersions); // Order the arrays the same way for the comparison.
		$compatibleGameVersionsChange = $newCompatibleGameVersions !== $oldCompatibleGameVersions;
	}

	$ok = true;
	if($actualChanges || $compatibleGameVersionsChange) {
		$changesToLog = [];

		$con->startTrans();

		if(isset($actualChanges['text'])) {
			$con->execute('UPDATE asset SET text = ? WHERE assetid = ?', [$actualChanges['text'], $existingRelease['assetid']]);

			$changesToLog[] = 'Updated description.';
		}
		if(isset($actualChanges['modidstr']) || isset($actualChanges['modversion'])) {
			$con->execute('UPDATE `release` SET modidstr = ?, modversion = ? WHERE releaseid = ?', [
				$actualChanges['modidstr']   ?? $existingRelease['modidstr'],
				$actualChanges['modversion'] ?? $existingRelease['modversion'],
				$existingRelease['releaseid']],
			);

			if(isset($actualChanges['modidstr'])) $changesToLog[] = "Updated modid: {$existingRelease['modidstr']} -> {$actualChanges['modidstr']}.";
			if(isset($actualChanges['modversion'])) $changesToLog[] = 'Updated modversion: '.formatSemanticVersion($existingRelease['modversion']).' -> '.formatSemanticVersion($actualChanges['modversion']).'.';
		}

		if($compatibleGameVersionsChange) {
			$releaseId = intval($existingRelease['releaseid']);
			$folded = implode(',', array_map(fn($v) => "($releaseId, $v)", $newCompatibleGameVersions));

			$con->execute('DELETE FROM ModReleaseCompatibleGameVersions WHERE releaseId = ?', [$releaseId]);
			// @security: Version numbers and releaseIds are numeric and therefore SQL Inert.
			$con->execute("INSERT INTO ModReleaseCompatibleGameVersions (releaseId, gameVersion) VALUES $folded");

			$removedCompat = array_values(array_diff($oldCompatibleGameVersions, $newCompatibleGameVersions));
			$addedCompat = array_values(array_diff($newCompatibleGameVersions, $oldCompatibleGameVersions));

			$change = 'Modified game version compat: ';
			if($removedCompat) $change .= 'removed '.formatGrammaticallyCorrectEnumeration(array_map('formatSemanticVersion', $removedCompat));
			if($addedCompat) {
				if($removedCompat) $change .= ', ';
				$change .= 'added '.formatGrammaticallyCorrectEnumeration(array_map('formatSemanticVersion', $addedCompat));
			}
			$changesToLog[] = $change;
		}

		$con->execute('UPDATE asset SET numsaved = numsaved + 1, editedbyuserid = ? WHERE assetid = ?', [$user['userid'], $existingRelease['assetid']]);

		logAssetChanges($changesToLog, $existingRelease['assetid']);

		updateGameVersionsCached($mod['modid']);
		$con->execute('UPDATE `mod` set lastreleased = NOW() WHERE modid = ?', [$mod['modid']]);

		$ok = $con->completeTrans();
	}
	return $ok;
}


/**
 * @security: Does not perform validation!
 * @param int $modId
 * @param array{assetid:int, releaseid:int} $release
 * @return bool Indicates if the release did in fact get deleted. Very unlikely to not succeed.
 */
function deleteRelease($modId, $release)
{
	global $con;

	$con->startTrans();

	$usedFiles = $con->getAssoc('SELECT fileid, cdnpath FROM file WHERE assetid = ?', [$release['assetid']]);
	// @perf: This could be merged into less queries, but in theory a release can only have one fiel either way, so this should not matter.
	foreach($usedFiles as $fileId => $cdnpath) {
		if($con->getOne('SELECT COUNT(*) FROM file WHERE cdnpath = ?', [$cdnpath]) == 1) {
			// Only delete abandoned files! Unlikely to not be the case for release files, but might aswell be safe.
			deleteFromCdn($cdnpath);
		}
		$con->execute('DELETE FROM file WHERE fileid = ?', [$fileId]);
	}

	$con->execute('DELETE FROM `release` where releaseid = ?', [$release['releaseid']]);
	$con->execute('DELETE FROM asset where assetid = ?', [$release['assetid']]);

	//TODO(Rennorb) @correctness: Remove / hide unread release notifications for deleted releases.
	// We cannot remove notifications for deleted releases trivially like we do with comment notifications because release notifications are tracked by modid, not by releaseid.
	// Since we only have the modid in the notification entry we could run into the following scenario:
	// 1. new release 1 for mod 1 -> notification 1 (unread)
	// 2. new release 2 for mod 1 -> notification 2 (unread)
	// 3. delete release 2 -> we would delete both notifications even though only one should be removed, because both of them are tracked by the same modid
	// I think it is possible to figure out a solution to this using the creation dates for releases and notifications, or change the notifications to be tracking releaseid instead of modid.
	// Both of those would however be a larger change, and right now I'm just supplying a small fix for notifications.
	// For now we just let these "invalid" notifications exist, as to not potentially remove valid ones which would be a lot worse.

	updateGameVersionsCached($modId);

	// Reset lastreleased to the last release, or the mod creation date if there is no other release.
	$con->execute('
		UPDATE `mod`
		SET lastreleased = IFNULL(
			(SELECT created FROM `release` WHERE modid = `mod`.modid ORDER BY created DESC LIMIT 1),
			`mod`.created
		)
		WHERE modid = ?;
	', [$modId]);

	return $con->completeTrans();
}

/** @param int $modId */
function updateGameVersionsCached($modId)
{
	global $con;

	$modId = intval($modId);

	$con->startTrans();

	$con->execute('DELETE FROM ModCompatibleGameVersionsCached WHERE modId = ?', [$modId]);
	$con->execute('DELETE FROM ModCompatibleMajorGameVersionsCached WHERE modId = ?', [$modId]);

	// @security: modId is numeric and therefore SQL inert.
	$con->execute("INSERT INTO ModCompatibleGameVersionsCached (modId, gameVersion)
		SELECT DISTINCT {$modId}, cgv.gameVersion
		FROM `release` r
		JOIN ModReleaseCompatibleGameVersions cgv
		where r.modid = {$modId}
	");

	$con->execute("INSERT INTO ModCompatibleMajorGameVersionsCached (modId, majorGameVersion)
		SELECT DISTINCT {$modId}, cgv.gameVersion & 0xffffffff00000000
		FROM `release` r
		JOIN ModReleaseCompatibleGameVersions cgv
		where r.modid = {$modId}
	");

	$con->completeTrans();
}
