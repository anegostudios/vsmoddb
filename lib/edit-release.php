<?php

const RESERVED_MOD_IDENTIFIERS = ['game', 'creative', 'survival'];

function canIgnoreRetraction($release)
{
	// Allow overwriting retractions only if it wasn't recrated by a moderator, unless that moderator is also the mod owner:
	return ($release['retractedByRoleId'] !== ROLE_ADMIN && $release['retractedByRoleId'] !== ROLE_MODERATOR) || $release['retractedByUserId'] === $release['createdByUserId'];
}

/** Fetches a single release in the shape returned by `GET /api/v2/mods/{modId}/releases/{releaseId}`.
 * Also used for the response of the release upload endpoint `POST /api/v2/mods/{modId}/releases`.
 * If multiple releases match, the one with the highest version is returned.
 * @param string $queryWhere Condition on `r` (modReleases), `a` (the release asset), `rr` (retraction) and `ru` (retracting user). @security: Must be sql inert, pass user input via $queryParams.
 * @param array $queryParams
 * @return array|null null if no release matches.
 */
function getApiRelease($queryWhere, $queryParams = [])
{
	global $con;

	$release = $con->getRow(<<<SQL
		SELECT r.releaseId, r.identifier, r.version, UNIX_TIMESTAMP(r.created) AS created,
			f.fileId, f.name,
			rr.reason AS retractionReason, ru.roleId AS retractedByRoleId, rr.lastModifiedBy AS retractedByUserId, a.createdByUserId,
			GROUP_CONCAT(cgv.gameVersion ORDER BY cgv.gameVersion DESC SEPARATOR ';') AS compatibleGameVersions
		FROM modReleases r
		JOIN assets a ON a.assetId = r.assetId
		LEFT JOIN files f ON f.assetId = r.assetId
		LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
		LEFT JOIN users ru ON ru.userId = rr.lastModifiedBy
		LEFT JOIN modReleaseCompatibleGameVersions cgv ON cgv.releaseId = r.releaseId
		WHERE $queryWhere
		GROUP BY r.releaseId
		ORDER BY r.version DESC
		LIMIT 1
	SQL, $queryParams);

	if(!$release) return null;

	$response = [
		'releaseId'  => intval($release['releaseId']),
		'identifier' => $release['identifier'],
		'version'    => formatSemanticVersion(intval($release['version'])),
		'compatibleGameVersions' => $release['compatibleGameVersions']
			? array_map(fn($v) => formatSemanticVersion(intval($v)), explode(';', $release['compatibleGameVersions']))
			: [],
		'created'    => intval($release['created']),
	];

	if($release['retractionReason']) {
		$response['retractionReason'] = $release['retractionReason'];
	}

	if(!$release['retractionReason'] || canIgnoreRetraction($release)) {
		$response['fileName'] = $release['name'];
		$response['fileUrl']  = $release['fileId'] ? formatDownloadTrackingUrl($release) : null;
	}

	return $response;
}

/**
 * Validates the mod identifier and version of a game-mod release against reserved identifiers and the releases already in the database.
 * Used both when creating a new release and when changing an existing one.
 * @param array{modId:int} $mod The mod the release belongs to.
 * @param string|null $identifier
 * @param int $version Compiled version.
 * @param int|null $ignoreReleaseId The release to exclude from the checks, e.g. the release currently being edited.
 * @return array{ok:true}|array{ok:false, status:int, error:string, errorHtml:string} `error` is plain text, `errorHtml` is the same message with links for the web form.
 */
function validateGameModReleaseIdentity($mod, $identifier, $version, $ignoreReleaseId = null)
{
	global $con;

	if (in_array($identifier, RESERVED_MOD_IDENTIFIERS)) {
		$error = "This modid ('$identifier') is reserved.";
		return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => $error, 'errorHtml' => $error];
	}

	$sqlIgnoreExistingRelease = $ignoreReleaseId ? 'r.releaseId != '.intval($ignoreReleaseId).' AND' : ''; // @security: intval makes this sql inert.
	$inUseBy = $con->getRow(<<<SQL
		SELECT a.assetId, r.modId, r.version, m.assetId as modAssetId, m.urlAlias
		FROM modReleases r
		JOIN assets a ON a.assetId = r.assetId
		JOIN mods m ON m.modId = r.modId
		WHERE $sqlIgnoreExistingRelease r.identifier = ? AND (r.modId != ? || r.version = ?)
		LIMIT 1
	SQL, [$identifier, $mod['modId'], $version]);

	if (!$inUseBy) return ['ok' => true];

	if($inUseBy['modId'] == $mod['modId'] && $inUseBy['version'] == $version) {
		$rv = formatSemanticVersion(intval($version));
		return ['ok' => false, 'status' => HTTP_CONFLICT,
			'error'     => "This version ($rv) of the mod has already been released.",
			'errorHtml' => "This version ($rv) of the mod has already been released (<a href='/edit/release/?assetid={$inUseBy['assetId']}'>link</a>).",
		];
	}
	else {
		$mpath = formatModPath(['urlAlias' => $inUseBy['urlAlias'], 'assetId' => $inUseBy['modAssetId']]);
		$mid = escapeHtml($identifier);
		return ['ok' => false, 'status' => HTTP_CONFLICT,
			'error'     => "This modid ('$identifier') is already in use by another mod.",
			'errorHtml' => "This modid ('$mid') is already in use by another mod (<a href='$mpath' target='_blank'>link</a>).",
		];
	}
}

/**
 * Compiles a list of game version strings (e.g. '1.20.4') and makes sure all of them exist in the gameVersions table.
 * Duplicates are collapsed.
 * @param mixed[] $versionStrings Usually user input.
 * @return array{ok:true, versions:int[]}|array{ok:false, status:int, error:string}
 */
function compileAndValidateGameVersions($versionStrings)
{
	global $con;

	if(!is_array($versionStrings) || !$versionStrings) {
		return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Missing compatible game versions.'];
	}

	$versions = [];
	foreach($versionStrings as $versionString) {
		$version = is_string($versionString) ? compileSemanticVersion($versionString) : false;
		if($version === false) {
			return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Malformed game version'.(is_string($versionString) ? " '$versionString'" : '').'.'];
		}
		$versions[$version] = $versionString;
	}

	// @security: Compiled versions are numeric and therefore SQL inert.
	$foldedVersions = implode(',', array_keys($versions));
	$knownVersions = array_map('intval', $con->getCol("SELECT version FROM gameVersions WHERE version IN ($foldedVersions)"));
	$unknownVersions = array_diff_key($versions, array_flip($knownVersions));
	if($unknownVersions) {
		return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Unknown game version(s): '.implode(', ', $unknownVersions).'.'];
	}

	return ['ok' => true, 'versions' => array_keys($versions)];
}

/**
 * @security: Does not perform validation!
 * @param array{modId:int, type:int} $mod The mod the release is to be associated with.
 * @param array{text:string, identifier?:string, version:int} $newData
 * @param int[] $newCompatibleGameVersions
 * @param array{assetId:int, fileId:int}|null $file A hovering file to attach to the release, or null if the caller attaches the file itself.
 * @param int $auditFlags Additional flags for the AUDIT_LOG_KIND_RELEASE_CREATE entry.
 * @return int The assetId of the newly created release. Zero on failure, very unlikely to fail.
 */
function createNewRelease($mod, $newData, $newCompatibleGameVersions, $file, $auditFlags = 0)
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
	if($file && $file['assetId'] == 0) {
		$con->execute('UPDATE files SET assetId = ? WHERE fileId = ?', [$assetId, $file['fileId']]);
	}

	$logInfo = 'v'.formatSemanticVersion($newData['version']);

	if(($mod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD) {
		$folded = implode(',', array_map(fn($v) => "($releaseId, $v)", $newCompatibleGameVersions));
		// @security: Version numbers and releaseIds are numeric and therefore SQL Inert.
		$con->execute("INSERT INTO modReleaseCompatibleGameVersions (releaseId, gameVersion) VALUES $folded");

		$logInfo .= " for {$newData['identifier']} with compatible game versions ".formatGrammaticallyCorrectEnumeration(array_map('formatSemanticVersion', $newCompatibleGameVersions));
	}

	logAuditEvent(AUDIT_LOG_KIND_RELEASE_CREATE, $releaseId, $logInfo, $auditFlags);

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
 * Creates a new game-mod release from an uploaded mod file. Meant for non-interactive callers (the api), the web form uses processFileUpload() + createNewRelease().
 * Everything is validated before the file gets uploaded to the cdn, and the file row is created already attached to the new release, together with the release, in one transaction.
 * A rejected upload therefore leaves nothing behind: no files row and no cdn object. The file never "hovers", so the web form's hovering file of the same user neither blocks this nor gets picked up.
 * Does not delete the local file, that is up to the caller (php deletes uploaded temp files at the end of the request).
 *
 * Uses global $user as the acting user.
 *
 * @security: Performs NO permission checks. The caller must have verified that $user may create releases for $mod,
 * that $user is not banned, that the mod is not locked and that we are not in readonly mode.
 * $changelogHtml must already be sanitized (sanitizeHtml + trimHtml), $compatibleGameVersions should come from compileAndValidateGameVersions().
 *
 * @param array{modId:int, category:int, uploadLimitOverwrite:int|null} $mod
 * @param array{name:string, tmp_name:string, size:int, error:int} $file One entry in $_FILES format. `tmp_name` may be any readable local path.
 * @param int[] $compatibleGameVersions Compiled versions.
 * @param string $changelogHtml
 * @param int $auditFlags Additional flags for the AUDIT_LOG_KIND_RELEASE_CREATE entry.
 * @return array{ok:true, releaseId:int, assetId:int, fileId:int}|array{ok:false, status:int, error:string}
 */
function createReleaseFromUploadedFile($mod, $file, $compatibleGameVersions, $changelogHtml, $auditFlags = 0)
{
	global $con, $user;

	if(($mod['category'] & CATEGORY__MASK) !== CATEGORY_GAME_MOD) {
		return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Creating releases this way is not supported yet for this mod category.'];
	}

	$uploadError = checkFileUploadError($file);
	if($uploadError) return $uploadError;

	if(empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
		return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Missing file.'];
	}

	$limits = UPLOAD_LIMITS[ASSETTYPE_RELEASE];
	if($mod['uploadLimitOverwrite'] !== null) $limits['individualSize'] = $mod['uploadLimitOverwrite'];

	$sizeOrTypeError = checkFileSizeAndType($file, $limits, $fileBasename, $ext);
	if($sizeOrTypeError) return $sizeOrTypeError;

	if(!$compatibleGameVersions) {
		return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Missing compatible game versions.'];
	}

	if(strlen($changelogHtml) > 65535) { // TEXT column max length in assets.text
		return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Changelog is too large.'];
	}

	$localPath = $file['tmp_name'];

	if(!modpeek($localPath, $modInfo) || !$modInfo['id']) {
		return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Failed to parse modinfo: '.($modInfo['errors'] ?? 'Missing modid.')];
	}

	$identityValidation = validateGameModReleaseIdentity($mod, $modInfo['id'], $modInfo['version']);
	if(!$identityValidation['ok']) {
		unset($identityValidation['errorHtml']);
		return $identityValidation;
	}

	//
	// Validation done, from here on we need to clean up the cdn object in case anything goes wrong.
	//

	$cdnFilePath = generateCdnFileBasenameWithPath($user['userId'], $localPath, $fileBasename).".{$ext}";
	$uploadResult = uploadToCdn($localPath, $cdnFilePath);
	if($uploadResult['error']) {
		return ['ok' => false, 'status' => HTTP_INTERNAL_ERROR, 'error' => 'CDN Error: '.$uploadResult['error']];
	}

	$transDepth = $con->transOff;
	try {
		$con->startTrans();

		$newData = ['text' => $changelogHtml, 'identifier' => $modInfo['id'], 'version' => $modInfo['version']];
		$assetId = createNewRelease($mod, $newData, $compatibleGameVersions, null, $auditFlags);
		$releaseId = intval($con->getOne('SELECT releaseId FROM modReleases WHERE assetId = ?', [$assetId]));

		$con->execute('INSERT INTO files (assetId, assetTypeId, userId, name, cdnPath, size, `order`) VALUES (?, ?, ?, ?, ?, ?, 0)',
			[$assetId, ASSETTYPE_RELEASE, $user['userId'], $file['name'], $cdnFilePath, $file['size']]
		);
		$fileId = intval($con->insert_ID());

		insertModPeekResults($fileId, $modInfo);
		logAuditEvent(AUDIT_LOG_KIND_FILE_CREATE, $fileId, "{$file['name']}");

		$ok = $con->completeTrans();
	}
	catch(Throwable $ex) {
		//NOTE: adodb throws on sql errors even inside smart transactions, so we have to unwind the (potentially nested) transaction ourselves.
		$con->failTrans();
		while($con->transOff > $transDepth) $con->completeTrans();
		tryDeleteUnusedCdnFile($cdnFilePath);

		// Two concurrent uploads of the same version can both pass validation, the unique index on modReleases catches that.
		if($ex instanceof ADODB_Exception && $ex->getCode() == 1062 /* ER_DUP_ENTRY */) {
			return ['ok' => false, 'status' => HTTP_CONFLICT, 'error' => 'This version ('.formatSemanticVersion($modInfo['version']).') of the mod has already been released.'];
		}
		throw $ex;
	}

	if(!$ok) {
		tryDeleteUnusedCdnFile($cdnFilePath);
		return ['ok' => false, 'status' => HTTP_INTERNAL_ERROR, 'error' => 'Failed to create release.'];
	}

	return ['ok' => true, 'releaseId' => $releaseId, 'assetId' => intval($assetId), 'fileId' => $fileId];
}

/**
 * Deletes a cdn object, unless a files row still references it.
 * Cdn paths are derived from user and file contents, so the same object may already be in use by a different file row (e.g. the same file uploaded through the web form).
 * @param string $cdnPath
 */
function tryDeleteUnusedCdnFile($cdnPath)
{
	global $con;

	if($con->getOne('SELECT COUNT(*) FROM files WHERE cdnPath = ?', [$cdnPath]) == 0) {
		deleteFromCdn($cdnPath);
	}
}

/**
 * @security: Does not perform validation!
 * @param array{modId:int, type:int} $mod The mod the release is to be associated with.
 * @param array{releaseId:int, assetId:int, text:string, identifier:string|null, version:int} $existingRelease
 * @param array{text:string, identifier?:string, version:int} $newData
 * @param int[] $newCompatibleGameVersions
 * @return bool Indicates if the release did in fact get created. Very unlikely to not succeed.
 */
function updateRelease($mod, $existingRelease, $newData, $newCompatibleGameVersions)
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
