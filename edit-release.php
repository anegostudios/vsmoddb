<?php
if(DB_READONLY) showReadonlyPage();

if (empty($user))   showErrorPage(HTTP_UNAUTHORIZED);
if ($user['isBanned'])  showErrorPage(HTTP_FORBIDDEN, 'You are currently banned.');


include($config['basepath'] . 'lib/edit-release.php');

$existingRelease = null;
$targetMod = null;
$pushedErrorForCurrentFile = false; // This is here so we can push the errors even if the file is not submitted, but dont duplicate the error message in case it is.

// /edit/release/?assetid=32 (edit existing release)
if(!empty($_REQUEST['assetid'])) {
	$existingRelease = $con->getRow(<<<SQL
		SELECT a.*, r.*, createdBy.name as createdByUsername, lastEditedBy.name as lastEditedByUsername
		FROM modReleases r
		JOIN assets a ON a.assetId = r.assetId
		LEFT JOIN users createdBy ON createdBy.userId = a.createdbyuserid 
		LEFT JOIN users lastEditedBy ON lastEditedBy.userId = a.editedbyuserid 
		WHERE r.assetId = ?
	SQL, [$_REQUEST['assetid']]);

	if($existingRelease) {
		$targetMod = $con->getRow(<<<SQL
			SELECT a.*, m.*
			FROM mods m
			JOIN assets a ON a.assetId = m.assetId
			WHERE m.modId = ?
		SQL, [$existingRelease['modId']]);
	}
}
// /edit/release/?modid=32  (add new release)
else if(!empty($_REQUEST['modid'])) {
	$targetMod = $con->getRow(<<<SQL
		SELECT a.*, m.*
		FROM mods m
		JOIN assets a ON a.assetId = m.assetId
		WHERE m.modId = ?
	SQL, [$_REQUEST['modid']]);
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
		$currentFiles = $con->getAll(<<<SQL
			SELECT f.assetId, f.fileId, mpr.modIdentifier, mpr.modVersion
			FROM files f
			LEFT JOIN modPeekResults mpr ON mpr.fileId = f.fileId
			WHERE f.assetId = ?
		SQL, [$existingRelease['assetId']]);
	}
	else {
		// hovering files
		$currentFiles = $con->getAll(<<<SQL
			SELECT f.assetId, f.fileId, mpr.modIdentifier, mpr.modVersion, mpr.errors
			FROM files f
			LEFT JOIN modPeekResults mpr ON mpr.fileId = f.fileId
			WHERE f.assetId IS NULL AND f.assetTypeId = 2 AND f.userId = ?
		SQL, [$user['userId']]);

		if(!empty($currentFiles[0]['errors']) && ($targetMod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD) {
			addMessage(MSG_CLASS_ERROR, 'There are issues with the current file: '.$currentFiles[0]['errors'], true);
			$pushedErrorForCurrentFile = true;
		}
	}
	/** @var array{'assetId':int, 'fileId':int, 'modIdentifier':string|null, 'modVersion':string|null}[] $currentFiles */

	//TODO(Rennorb) @cleanup: This exists for the case that the user used the "Browse" button instead of drag and drop, that doesn't immediately upload the file. 
	if(!empty($_FILES['newfile']) && $_FILES['newfile']['error'] != 4) {
		if($currentFiles) {
			addMessage(MSG_CLASS_ERROR, 'Only one file can be attached to a release.');
		}
		else {
			$assetId = $existingRelease['assetId'] ?? 0;
			$processedFile = processFileUpload($_FILES['newfile'], 2, $assetId);

			if($processedFile['status'] === 'error') {
				addMessage(MSG_CLASS_ERROR, 'Failed to process uploaded file: '.$processedFile['errormessage'], true);
			}
			else if(($targetMod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD) {
				if($processedFile['modparse'] === 'error') {
					addMessage(MSG_CLASS_ERROR, 'Failed to parse modinfo: '.$processedFile['parsemsg'], true);
					$pushedErrorForCurrentFile = true;
				}
				else {
					$currentFiles[] = [
						'assetId'            => $assetId,
						'fileId'             => $processedFile['fileid'],
						'modIdentifier'      => $processedFile['modid'],
						'modVersion'         => $processedFile['modversion'],
					];
				}
			}
			else {
				$currentFiles[] = [
					'assetId' => $assetId,
					'fileId'  => $processedFile['fileid'],
				];
			}
		}
	}

	if(!$currentFiles && (empty($_FILES['newfile']) || $_FILES['newfile']['error'] === 4)) { // Release needs a file, but don't emit the message if the parsing failed.
		addMessage(MSG_CLASS_ERROR, 'Release is missing a file.');
	}


	if(isset($_POST['text'])) {
		$newData['text'] = sanitizeHtml($_POST['text']);

		$textLen = strlen($newData['text']);
		if($textLen > 65535) { // TEXT column max length in assets.text
			$sizeKb = floor($textLen / 1024);
			$reason = "Excessive size ({$sizeKb}KB).";
			if(contains($newData['text'], 'src="data:image')) $reason .= " You cannot paste large images directly. If you need a large image, upload it to an external site and link to that.";
			addMessage(MSG_CLASS_ERROR, $reason);
		}
	}

	$newCompatibleGameVersions = null;

	if(($targetMod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD) {
		// Mods take modid and version from the attached file. We no longer allow manual entry.
		if($currentFiles) {
			$newData['identifier'] = $currentFiles[0]['modIdentifier'];
			$newData['version']    = $currentFiles[0]['modVersion'];

			if (in_array($newData['identifier'], ["game", "creative", "survival"])) { // Reserve special mod ids
				addMessage(MSG_CLASS_ERROR, "This modid ('{$newData['identifier']}') is reserved.");
			}
			else {
				$sqlIgnoreExistingRelease = $existingRelease ? "r.releaseId != {$existingRelease['releaseId']} AND" : ''; // @security $existingRelease['releaseId'] comes from the database and is numeric, therefore sql inert.
				$inUseBy = $con->getRow(<<<SQL
					SELECT a.*, r.modId, r.version, m.assetId as modAssetId, m.urlAlias
					FROM modReleases r
					JOIN assets a ON a.assetId = r.assetId
					JOIN mods m ON m.modId = r.modId
					WHERE $sqlIgnoreExistingRelease r.identifier = ? AND (r.modId != ? || r.version = ?)
					LIMIT 1
				SQL, [$newData['identifier'], $targetMod['modId'], $newData['version']]);

				if ($inUseBy) {
					if($inUseBy['modId'] == $targetMod['modId'] && $inUseBy['version'] == $newData['version']) {
						$rv = formatSemanticVersion(intval($newData['version']));
						addMessage(MSG_CLASS_ERROR, "This version ($rv) of the mod has already been released (<a href='/edit/release/?assetid={$inUseBy['assetId']}'>link</a>).");
					}
					else {
						$mid = htmlspecialchars($newData['identifier']);
						$mpath = formatModPath(['urlAlias' => $inUseBy['urlAlias'], 'assetId' => $inUseBy['modAssetId']]);
						addMessage(MSG_CLASS_ERROR, "This modid ('$mid') is already in use by another mod (<a href='$mpath' target='_blank'>link</a>).");
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
				$newData['version'] = $version;
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

	$ok = deleteRelease($targetMod['modId'], $existingRelease);
	if($ok) {
		forceRedirect(formatModPath($targetMod).'#tab-files');
		exit();
	}
}


//
// Prepare data for display
//

if($existingRelease) {
	$files = $con->getAll(<<<SQL
		SELECT f.*, i.hasThumbnail, CONCAT(ST_X(i.size), 'x', ST_Y(i.size)) AS imageSize
		FROM files f
		LEFT JOIN fileImageData i ON i.fileId = f.fileId
		WHERE f.assetId = ?
	SQL, [$existingRelease['assetId']]);

	$compatibleGameVersions = $con->getCol('SELECT gameVersion FROM modReleaseCompatibleGameVersions WHERE releaseId = ?', $existingRelease['releaseId']);
	$existingRelease['compatibleGameVersions'] = array_flip(array_map('intval', $compatibleGameVersions));
}
else {
	// hovering files
	$files = $con->getAll(<<<SQL
		SELECT f.*, i.hasThumbnail, CONCAT(ST_X(i.size), 'x', ST_Y(i.size)) AS imageSize, mpr.modIdentifier, mpr.modVersion, mpr.rawDependencies, mpr.errors
		FROM files f
		LEFT JOIN modPeekResults mpr ON mpr.fileId = f.fileId
		LEFT JOIN fileImageData i ON i.fileId = f.fileId
		WHERE f.assetId IS NULL AND f.assetTypeId = 2 AND f.userId = ?
	SQL, [$user['userId']]);

	if(!$pushedErrorForCurrentFile && !empty($files[0]['errors'])) {
		addMessage(MSG_CLASS_ERROR, 'There are issues with the current file: '.$files[0]['errors'], true);
	}
}

foreach($files as &$file) {
	$file['created'] = date('M jS Y, H:i:s', strtotime($file['created']));

	$file['ext'] = substr($file['name'], strrpos($file['name'], '.')+1); // no clue why pathinfo doesnt work here
	$file['url'] = maybeFormatDownloadTrackingUrlDependingOnFileExt($file);
}
unset($file);



$allGameVersions = $con->getAll('SELECT version FROM gameVersions ORDER BY version DESC');
foreach($allGameVersions as &$gameVersion) {
	$gameVersion['version'] = intval($gameVersion['version']);
	$gameVersion['name'] = formatSemanticVersion($gameVersion['version']);
}
unset($gameVersion);


$assetChangelog = $existingRelease ? $con->getAll(<<<SQL
	SELECT ch.text, ch.lastModified, u.name AS username
	FROM changelogs ch
	JOIN users u ON u.userId = ch.userId
	WHERE ch.assetId = ?
	ORDER BY ch.created DESC
	LIMIT 20
SQL, [$existingRelease['assetId']]) : [];



if(!$existingRelease) {
	$existingRelease = [
		'assetId'    => 0,
		'text'       => $_POST['text'] ?? '',
		'numSaved'   => 0,
		'compatibleGameVersions' => empty($_POST['cgvs']) ? [] : array_flip(array_filter(array_map('compileSemanticVersion', $_POST['cgvs']))),
	];

	if(($targetMod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD) {
		// Pre-select values from hovering file:
		$existingRelease['identifier'] = $files ? $files[0]['modIdentifier'] : '';
		$existingRelease['version']    = $files ? formatSemanticVersion(intval($files[0]['modVersion'])) : '';

		if(!$existingRelease['compatibleGameVersions'] && $files && ($minCompat = findMinCompatibleGameVersion($files[0]['rawDependencies'])) && $minCompat !== null) {
			$detectedCompat = [];
			foreach($allGameVersions as $version) { // Order is descending, so the filter is trivial.
				if($version['version'] >= $minCompat) $detectedCompat[] = $version['version'];
				else break;
			}
			$existingRelease['compatibleGameVersions'] = array_flip($detectedCompat);
		}
	}
	else {
		$existingRelease['identifier'] = '';
		$existingRelease['version']    = $_POST['modversion'] ?? '';
	}
}
else {
	$existingRelease['version'] = formatSemanticVersion(intval($existingRelease['version']));
}

$maxFileUploadSize = min($maxFileUploadSize, UPLOAD_LIMITS[ASSETTYPE_RELEASE]['individualSize']);

cspAllowTinyMceFull();
cspPushAllowedInlineHandlerHash('sha256-nTlTeikEEupAQmSPlHWFcoJvMdPCIBu+Zu+G64E7uC4='); // javascript:submitForm(0)
cspPushAllowedInlineHandlerHash('sha256-XKuSPEJjbu3T+mAY9wlP6dgYQ4xJL1rP4m3GrDwZ68c='); // javascript:submitForm(1)
cspPushAllowedInlineHandlerHash('sha256-HAn3sRp/DKqkhccjWmi+mt2NzQCTHLzeSlhZ0T2cDuY='); // javascript:submitDelete()
cspReplaceAllowedFetchSources("{$_SERVER['HTTP_HOST']}/edit-deletefile {$_SERVER['HTTP_HOST']}/edit-uploadfile");

$view->assign('allGameVersions', $allGameVersions);

$view->assign('mod', $targetMod);
$view->assign('doFileValidation', ($targetMod["category"] & CATEGORY__MASK) === CATEGORY_GAME_MOD, null, false);
$view->assign('release', $existingRelease);
$view->assign('files', $files);

$view->assign('assetChangelog', $assetChangelog);

$view->display('edit-release');
