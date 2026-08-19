<?php

/**
 * @security: Does not perform validation!
 * @param array{modId:int, type:int} $mod The mod the release is to be associated with.
 * @param array{text:string, identifier?:string, version:int} $newData
 * @param int[] $newCompatibleGameVersions
 * @param array{assetId:int, fileId:int} $file
 * @return int The assetId of the newly created release. Zero on failure, very unlikely to fail.
 */
function createNewRelease($mod, $newData, $newCompatibleGameVersions, $file)
{
	global $con, $user;

	$con->startTrans();

	$con->execute(<<<SQL
		INSERT INTO assets (assetTypeId, numSaved, statusId, created, text, createdByUserId, editedByUserId)
		VALUES(2, 1, 2, NOW(), ?, ?, ?)
	SQL, [$newData['text'], $user['userId'], $user['userId']]);
	$assetId = $con->insert_ID();
	
	$con->execute('INSERT INTO modReleases (modId, assetId, identifier, version) VALUES(?, ?, ?, ?)', [$mod['modId'], $assetId, $newData['identifier'] ?? NULL, $newData['version']]);
	$releaseId = $con->insert_ID();

	// attach hovering files
	if($file['assetId'] == 0) {
		$con->execute('UPDATE files SET assetId = ? WHERE fileId = ?', [$assetId, $file['fileId']]);
	}

	$logInfo = 'v'.formatSemanticVersion($newData['version']);

	if(($mod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD) {
		$folded = implode(',', array_map(fn($v) => "($releaseId, $v)", $newCompatibleGameVersions));
		// @security: Version numbers and releaseIds are numeric and therefore SQL Inert.
		$con->execute("INSERT INTO modReleaseCompatibleGameVersions (releaseId, gameVersion) VALUES $folded");

		$logInfo .= " for {$newData['identifier']} with compatible game versions ".formatGrammaticallyCorrectEnumeration(array_map('formatSemanticVersion', $newCompatibleGameVersions));
	}

	logAuditEvent(AUDIT_LOG_KIND_RELEASE_CREATE, $releaseId, $logInfo);

	updateGameVersionsCached($mod['modId']);
	$con->execute('UPDATE mods set lastReleased = NOW() WHERE modId = ?', [$mod['modId']]);

	$con->Execute("
		INSERT INTO notifications (userId, kind, recordId)
		SELECT userId, ".NOTIFICATION_NEW_RELEASE.", ?
		FROM userFollowedMods
		WHERE modId = ? AND flags & ".FOLLOW_FLAG_CREATE_NOTIFICATIONS."
	", [$mod['modId'], $mod['modId']]);

	return $con->completeTrans() ? $assetId : 0;
}

/**
 * @security: Does not perform validation!
 * @param array{modId:int, type:int} $mod The mod the release is to be associated with.
 * @param array{releaseId:int, assetId:int, text:string, identifier:string|null, version:int} $existingRelease
 * @param array{text:string, identifier?:string, version:int} $newData
 * @param int[] $newCompatibleGameVersions
 * @param array{assetId:int, fileId:int} $file Unused for now
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
	if(($mod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD) {
		$oldCompatibleGameVersions = array_map('intval', $con->getCol('SELECT gameVersion FROM modReleaseCompatibleGameVersions WHERE releaseId = ? ORDER BY gameVersion', [$existingRelease['releaseId']]));
		sort($newCompatibleGameVersions); // Order the arrays the same way for the comparison.
		$compatibleGameVersionsChange = $newCompatibleGameVersions !== $oldCompatibleGameVersions;
	}

	$ok = true;
	if($actualChanges || $compatibleGameVersionsChange) {
		$releaseId = intval($existingRelease['releaseId']);
		$changesToLog = [];

		$con->startTrans();

		if(isset($actualChanges['text'])) {
			$con->execute('UPDATE assets SET text = ?, editedByUserId = ? WHERE assetId = ?',
				[$actualChanges['text'], $user['userId'], $existingRelease['assetId']]
			);

			array_push($changesToLog, AUDIT_LOG_KIND_RELEASE_CHANGE_CHANGELOG, createAuditLogDiff($existingRelease['text'], $actualChanges['text']));
		}
		if(isset($actualChanges['identifier']) || isset($actualChanges['version'])) {
			$con->execute('UPDATE modReleases SET identifier = ?, version = ? WHERE releaseId = ?', [
				$actualChanges['identifier'] ?? $existingRelease['identifier'],
				$actualChanges['version']    ?? $existingRelease['version'],
				$existingRelease['releaseId']],
			);

			if(isset($actualChanges['identifier']))
				array_push($changesToLog, AUDIT_LOG_KIND_RELEASE_CHANGE_IDENTIFIER, createAuditLogDiff($existingRelease['identifier'], $actualChanges['identifier']));
			if(isset($actualChanges['version'])) 
				array_push($changesToLog, AUDIT_LOG_KIND_RELEASE_CHANGE_VERSION, createAuditLogDiff(formatSemanticVersion($existingRelease['version']), formatSemanticVersion($actualChanges['version'])));
		}

		if($compatibleGameVersionsChange) {
			$folded = implode(',', array_map(fn($v) => "($releaseId, $v)", $newCompatibleGameVersions));

			$con->execute('DELETE FROM modReleaseCompatibleGameVersions WHERE releaseId = ?', [$releaseId]);
			// @security: Version numbers and releaseIds are numeric and therefore SQL Inert.
			$con->execute("INSERT INTO modReleaseCompatibleGameVersions (releaseId, gameVersion) VALUES $folded");

			$old = implode("\n", array_map('formatSemanticVersion', $oldCompatibleGameVersions));
			$new = implode("\n", array_map('formatSemanticVersion', $newCompatibleGameVersions));
			array_push($changesToLog, AUDIT_LOG_KIND_RELEASE_CHANGE_COMPAT, createAuditLogDiff($old, $new));
		}

		$con->execute('UPDATE assets SET numSaved = numSaved + 1, editedByUserId = ? WHERE assetId = ?', [$user['userId'], $existingRelease['assetId']]);

		$logFlags = canModerate(null, $user) ? AUDIT_LOG_FLAG_MODACTION : 0; // @correctness this check needs to filter out team members.
		$logPlaceholders = substr(str_repeat("({$logFlags}, {$releaseId}, {$user['userId']}, ?, ?),", count($changesToLog) / 2), 0, -1);
		$con->execute('INSERT INTO auditLogs (flags, referenceId, initiatorUserId, kind, info) VALUES '.$logPlaceholders, $changesToLog);

		updateGameVersionsCached($mod['modId']);
		$con->execute('UPDATE mods set lastReleased = NOW() WHERE modId = ?', [$mod['modId']]);

		$ok = $con->completeTrans();
	}
	return $ok;
}


/** @param int $modId */
function updateGameVersionsCached($modId)
{
	global $con;

	$modId = intval($modId);

	$con->startTrans();

	$con->execute('DELETE FROM modCompatibleGameVersionsCached WHERE modId = ?', [$modId]);
	$con->execute('DELETE FROM modCompatibleMajorGameVersionsCached WHERE modId = ?', [$modId]);

	// @security: modId is numeric and therefore SQL inert.
	$con->execute(<<<SQL
		INSERT INTO modCompatibleGameVersionsCached (modId, gameVersion)
		SELECT DISTINCT $modId, cgv.gameVersion
		FROM modReleases r
		JOIN modReleaseCompatibleGameVersions cgv ON cgv.releaseId = r.releaseId
		LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
		where r.modId = $modId AND rr.reason IS NULL
	SQL);

	$con->execute(<<<SQL
		INSERT INTO modCompatibleMajorGameVersionsCached (modId, majorGameVersion)
		SELECT DISTINCT $modId, cgv.gameVersion & 0xffffffff00000000 -- :VERSION_MASK_PRIMARY
		FROM modReleases r
		JOIN modReleaseCompatibleGameVersions cgv ON cgv.releaseId = r.releaseId
		LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
		where r.modId = $modId AND rr.reason IS NULL
	SQL);

	$con->completeTrans();
}
