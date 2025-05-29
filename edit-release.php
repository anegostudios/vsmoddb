<?php
if (empty($user))   showErrorPage(HTTP_UNAUTHORIZED);
if ($user['isbanned'])  showErrorPage(HTTP_FORBIDDEN, 'You are currently banned.');


include($config['basepath'] . 'lib/edit-release.php');

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
					addMessage(MSG_CLASS_ERROR, "This modid ('{$newData['modidstr']}') is reserved.");
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
			$ok = updateRelease($targetMod, $existingRelease, $newData, $newCompatibleGameVersions, $currentFiles[0]);
			if($ok) {
				if(!empty($_POST['saveandback'])) forceRedirect(formatModPath($targetMod).'#tab-files');
				else                              forceRedirectAfterPOST();
				exit();
			}
		}
		else { // adding a new release, no $existingRelease
			$assetId = createNewRelease($targetMod, $newData, $newCompatibleGameVersions, $currentFiles[0]);
			if($assetId) {
				if(!empty($_POST['saveandback'])) forceRedirect(formatModPath($targetMod).'#tab-files');
				else                              forceRedirect('/edit/release/?assetid='.$assetId);
				exit();
			}
		}
	}
}
else if($existingRelease && !empty($_POST['delete'])) {
	validateActionToken();

	$ok = deleteRelease($targetMod['modid'], $existingRelease);
	if($ok) {
		forceRedirect(formatModPath($targetMod).'#tab-files');
		exit();
	}
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
