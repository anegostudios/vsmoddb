<?php
if (empty($user))   showErrorPage(HTTP_UNAUTHORIZED);

$existingRelease = null;
$targetMod = null;

// /edit/release/?assetid=32 (edit existing release)
if(!empty($_REQUEST['assetid'])) {
	$existingRelease = $con->getRow('
		SELECT a.*, r.*, createdBy.name as createdByUsername, lastEditedBy.name as lastEditedByUsername
		FROM `release` r
		JOIN asset a ON a.assetid = r.assetid
		LEFT JOIN user createdBy ON createdBy.userid = a.createdbyuserid 
		LEFT JOIN user lastEditedBy ON lastEditedBy.userid = a.editedbyuserid 
		WHERE r.assetid = ?
	', [$_REQUEST['assetid']]);

	if($existingRelease) {
		$targetMod = $con->getRow('
			SELECT a.*, m.*
			FROM `mod` m
			JOIN asset a ON a.assetid = m.assetid
			WHERE m.modid = ?
		', [$existingRelease['modid']]);
	}
}
// /edit/release/?modid=32  (add new release)
else if(!empty($_REQUEST['modid'])) {
	$targetMod = $con->getRow('
		SELECT a.*, m.*
		FROM `mod` m
		JOIN asset a ON a.assetid = m.assetid
		WHERE m.modid = ?
	', [$_REQUEST['modid']]);
}

//NOTE(Rennorb): Do as little work as possible before this permission check, but don't unnecessarily split queries.
if(!$targetMod)   showErrorPage(HTTP_NOT_FOUND, 'Target mod was not found.');
if(!canEditAsset($targetMod, $user))   showErrorPage(HTTP_FORBIDDEN);

//
// Actions
//

if(!empty($_POST['save'])) {
	validateActionToken();

	$oldMsgCount = count($messages /* global */);
	$newData = [];

	//
	// Validate
	//

	//TODO(Rennorb) @cleanup @correctness: Attach files on save instead of on upload.
	if($existingRelease) {
		$currentFiles = $con->getAll('
			SELECT file.assetid, file.fileid, mpr.detectedmodidstr, mpr.detectedmodversion
			FROM file
			LEFT JOIN modpeek_result mpr ON mpr.fileid = file.fileid
			WHERE assetid = ?
		', [$existingRelease['assetid']]);
	}
	else {
		// hovering files
		$currentFiles = $con->getAll('
			SELECT file.assetid, file.fileid, mpr.detectedmodidstr, mpr.detectedmodversion
			FROM file
			LEFT JOIN modpeek_result mpr ON mpr.fileid = file.fileid
			WHERE assetid IS NULL AND assettypeid = 2 AND userid = ?
		', [$user['userid']]);
	}

	//TODO(Rennorb) @cleanup: This exists for the case that the user used the "Browse" button instead of drag and drop, that doesn't immediately upload the file. 
	if(!empty($_FILES['newfile']) && $_FILES['newfile']['error'] != 4) {
		if($currentFiles) {
			addMessage(MSG_CLASS_ERROR, 'Only one file can be attached to a release.');
		}
		else {
			$assetId = $existingRelease['assetid'] ?? 0;
			$processedFile = processFileUpload($_FILES['newfile'], 2, $assetId);

			if($processedFile['status'] === 'error') {
				addMessage(MSG_CLASS_ERROR, 'Failed to process uploaded file: '.$processedFile['errormessage'], true);
			}
			else if($targetMod['type'] === 'mod') {
				if($processedFile['modparse'] === 'error') {
					addMessage(MSG_CLASS_ERROR, 'Failed to parse modinfo: '.$processedFile['parsemsg'], true);
				}
				else {
					$currentFiles[] = [
						'assetid'            => $assetId,
						'fileid'             => $processedFile['fileid'],
						'detectedmodidstr'   => $processedFile['modid'],
						'detectedmodversion' => $processedFile['modversion'],
					];
				}
			}
			else {
				$currentFiles[] = [
					'assetid' => $assetId,
					'fileid'  => $processedFile['fileid'],
				];
			}
		}
	}

	if(!$currentFiles && empty($_FILES['newfile'])) { // Release needs a file, but don't emit the message if the parsing failed.
		addMessage(MSG_CLASS_ERROR, 'Release is missing a file.');
	}


	if(isset($_POST['text'])) {
		$newData['text'] = sanitizeHtml($_POST['text']);
	}

	$newCompatibleGameVersions = null;

	if($targetMod['type'] === 'mod') {
		// Mods take modid and version from the attached file. We no longer allow manual entry.
		if($currentFiles) {
			$newData['modidstr']   = $currentFiles[0]['detectedmodidstr'];
			$newData['modversion'] = $currentFiles[0]['detectedmodversion'];

			if (!preg_match('/^[0-9a-zA-Z]+$/', $newData['modidstr'])) {
				addMessage(MSG_CLASS_ERROR, "Detected modid '{$newData['modidstr']}' is not valid.", true); // @cleanup once modpeek is better
			}
			else {
				if (in_array($newData['modidstr'], ["game", "creative", "survival"])) { // Reserve special mod ids
					addMessage(MSG_CLASS_ERROR, "This modid ('$mid') is reserved.");
				}
				else {
					$inUseBy = $con->getRow('
						SELECT a.*, r.modid, r.modversion, m.assetid as modassetid, m.urlalias
						FROM `release` r
						JOIN asset a ON a.assetid = r.assetid
						JOIN `mod` m ON m.modid = r.modid
						WHERE r.modidstr = ? AND (r.modid != ? || r.modversion = ?)
						LIMIT 1
					', [$targetMod['modid'], $newData['modidstr'], $newData['modversion']]);

					if ($inUseBy) {
						if($inUseBy['modid'] == $targetMod['modid'] && $inUseBy['modversion'] == $newData['modversion']) {
							$rv = formatSemanticVersion(intval($newData['modversion']));
							addMessage(MSG_CLASS_ERROR, "This version ($rv) of the mod has already been released (<a href='/edit/release/?assetid={$inUseBy['assetid']}'>link</a>).");
						}
						else {
							$mid = htmlspecialchars($newData['modidstr']);
							$mpath = formatModPath(['urlalias' => $inUseBy['urlalias'], 'assetid' => $inUseBy['modassetid']]);
							addMessage(MSG_CLASS_ERROR, "This modid ('$mid') is already in use by another mod (<a href='$mpath'>link</a>).");
						}
					}
				}
			}
		}

		// modversion is already validated by the ModPeek wrapper.

		if(!empty($_POST['cgvs'])) {
			$newCompatibleGameVersions = array_filter(array_map('compileSemanticVersion', $_POST['cgvs']));
		}
		if(!$newCompatibleGameVersions) {
			addMessage(MSG_CLASS_ERROR, 'Missing compatible game versions.');
		}
	}
	else {
		// Non-mods must have their version manually entered.
		if(empty($_POST['modversion'])) {
			addMessage(MSG_CLASS_ERROR, 'Missing version field.');
		}
		else {
			$version = compileSemanticVersion($_POST['modversion']);
			if($version === false) {
				addMessage(MSG_CLASS_ERROR, 'Malformed version.<br/>Version numbers must follow semantic versioning, formatted as <code>n.n.n[-{rc|pre|dev}.n]</code><br/>Examples: <code>1.0.1</code> or <code>1.5.2-rc.1</code>');
			}
			else {
				$newData['modversion'] = $version;
			}
		}
	}


	if(count($messages /* global */) === $oldMsgCount) { // no errors occurred
		//
		// Save
		//

		if($existingRelease) {
			$actualChanges = [];
			foreach($newData as $k => $newVal) {
				if($existingRelease[$k] != $newVal) $actualChanges[$k] = $newVal;
			}
			
			$compatibleGameVersionsChange = false;
			if($targetMod['type'] === 'mod') {
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

				logAssetChanges($changesToLog, $existingRelease['assetid']);

				updateGameVersionsCached($targetMod['modid']);

				$ok = $con->completeTrans();
			}

			if($ok) {
				if(!empty($_POST['saveandback'])) forceRedirect(formatModPath($targetMod).'#tab-files');
				else                              forceRedirectAfterPOST();
				exit();
			}
		}
		else { // adding a new release, no $existingRelease
			$con->startTrans();

			$con->execute('INSERT INTO asset (numsaved, text) VALUES(1, ?)', [$newData['text']]);
			$assetId = $con->insert_ID();
			
			$con->execute('INSERT INTO `release` (modid, assetid, modidstr, modversion) VALUES(?, ?, ?, ?)', [$targetMod['modid'], $assetId, $newData['modidstr'] ?? NULL, $newData['modversion']]);
			$releaseId = $con->insert_ID();

			// attach hovering files
			$file = $currentFiles[0];
			if($file['assetid'] == 0) {
				$con->execute('UPDATE file SET assetid = ? WHERE fileid = ?', [$assetId, $file['fileid']]);
			}

			$changeToLog = 'Created new release v'.formatSemanticVersion($newData['modversion']);

			if($targetMod['type'] === 'mod') {
				$folded = implode(',', array_map(fn($v) => "($releaseId, $v)", $newCompatibleGameVersions));
				// @security: Version numbers and releaseIds are numeric and therefore SQL Inert.
				$con->execute("INSERT INTO ModReleaseCompatibleGameVersions (releaseId, gameVersion) VALUES $folded");

				$changeToLog .= " for {$newData['modidstr']} with compatible game versions ".formatGrammaticallyCorrectEnumeration(array_map('formatSemanticVersion', $newCompatibleGameVersions));
			}

			logAssetChanges([$changeToLog], $assetId);

			updateGameVersionsCached($targetMod['modid']);

			if($con->completeTrans()) {
				if(!empty($_POST['saveandback'])) forceRedirect(formatModPath($targetMod).'#tab-files');
				else                              forceRedirect('/edit/release/?assetid='.$assetId);
				exit();
			}
		}
	}
}
else if($existingRelease && !empty($_POST['delete'])) {
	validateActionToken();

	$con->startTrans();
	$con->execute('DELETE FROM asset where assetid = ?', [$existingRelease['assetid']]);
	$con->execute('DELETE FROM `release` where releaseid = ?', [$existingRelease['releaseid']]);

	$usedFiles = $con->getAll('SELECT fileid, cdnpath FROM file WHERE assetid = ?', [$existingRelease['assetid']]);
	foreach($usedFiles as $file) {
		
	}

	//TODO(Rennorb) @correctness: Remove / hide unread release notifications for deleted releases.
	// We cannot remove notifications for deleted releases trivially like we do with comment notifications because release notifications are tracked by modid, not by releaseid.
	// Since we only have the modid in the notification entry we could run into the following scenario:
	// 1. new release 1 for mod 1 -> notification 1 (unread)
	// 2. new release 2 for mod 1 -> notification 2 (unread)
	// 3. delete release 2 -> we would delete both notifications even though only one should be removed, because both of them are tracked by the same modid
	// I think it is possible to figure out a solution to this using the creation dates for releases and notifications, or change the notifications to be tracking releaseid instead of modid.
	// Both of those would however be a larger change, and right now I'm just supplying a small fix for notifications.
	// For now we just let these "invalid" notifications exist, as to not potentially remove valid ones which would be a lot worse.

	updateGameVersionsCached($targetMod['modid']);

	// Reset lastreleased to the last release, or the mod creation date if there is no other release.
	$con->execute('
		UPDATE `mod`
		SET lastreleased = IFNULL(
			(SELECT created FROM `release` WHERE modid = `mod`.modid ORDER BY created DESC LIMIT 1),
			`mod`.created
		)
		WHERE modid = ?;
	', [$targetMod['modid']]);

	$con->completeTrans();

	forceRedirect(formatModPath($targetMod).'#tab-files');
	exit();
}


//
// Prepare data for display
//

if($existingRelease) {
	$files = $con->getAll("
		SELECT *, CONCAT(ST_X(imagesize), 'x', ST_Y(imagesize)) AS imagesize
		FROM file
		WHERE assetid = ?
	", [$existingRelease['assetid']]);

	$compatibleGameVersions = $con->getCol('SELECT gameVersion FROM ModReleaseCompatibleGameVersions WHERE releaseId = ?', $existingRelease['releaseid']);
	$existingRelease['compatibleGameVersions'] = array_flip(array_map('intval', $compatibleGameVersions));
}
else {
	// hovering files
	$files = $con->getAll("
		SELECT *, file.fileid, CONCAT(ST_X(imagesize), 'x', ST_Y(imagesize)) AS imagesize, mpr.detectedmodidstr, mpr.detectedmodversion
		FROM file
		LEFT JOIN modpeek_result mpr ON mpr.fileid = file.fileid
		WHERE assetid IS NULL AND assettypeid = 2 AND userid = ?
	", [$user['userid']]);
}

foreach($files as &$file) {
	$file["created"] = date("M jS Y, H:i:s", strtotime($file["created"]));

	$file["ext"] = substr($file["filename"], strrpos($file["filename"], ".")+1); // no clue why pathinfo doesnt work here
	$file["url"] = maybeFormatDownloadTrackingUrlDependingOnFileExt($file);
}
unset($file);



$allGameVersions = $con->getAll('SELECT version FROM GameVersions ORDER BY version DESC');
foreach($allGameVersions as &$gameVersion) {
	$gameVersion['version'] = intval($gameVersion['version']);
	$gameVersion['name'] = formatSemanticVersion($gameVersion['version']);
}
unset($gameVersion);


$assetChangelog = $existingRelease ? $con->getAll('
	SELECT changelog.*, user.name as username
	FROM changelog
	JOIN user ON changelog.userid = user.userid
	WHERE changelog.assetid = ?
	ORDER BY created DESC
	LIMIT 20
', [$existingRelease['assetid']]) : [];



if(!$existingRelease) {
	$existingRelease = [
		'assetid'    => 0,
		'text'       => $_POST['text'] ?? '',
		'numsaved'   => 0,
		'compatibleGameVersions' => empty($_POST['cgvs']) ? [] : array_flip(array_filter(array_map('compileSemanticVersion', $_POST['cgvs']))),
	];

	if($targetMod['type'] === 'mod') {
		$existingRelease['modidstr']   = $files ? $files[0]['detectedmodidstr'] : '';
		$existingRelease['modversion'] = $files ? $files[0]['detectedmodversion'] : '';
	}
	else {
		$existingRelease['modidstr']   = '';
		$existingRelease['modversion'] = $_POST['modversion'] ?? '';
	}
}
else {
	$existingRelease['modversion'] = formatSemanticVersion(intval($existingRelease['modversion']));
}

$view->assign('allGameVersions', $allGameVersions);

$view->assign('mod', $targetMod);
$view->assign('release', $existingRelease);
$view->assign('files', $files);

$view->assign('assetChangelog', $assetChangelog);

$view->display('edit-release');
