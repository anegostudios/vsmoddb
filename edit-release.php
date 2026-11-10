<?php

/**
 * @var array|null $user
 * @var object $con
 * @var object $view
 * @var array $messages
 */

if(DB_READONLY) showReadonlyPage();

if (empty($user))   showErrorPage(HTTP_UNAUTHORIZED);
validateUserNotBanned();

require(SRC_ROOT.'/lib/modinfo.php');
require(SRC_ROOT.'/lib/edit-release.php');
require(SRC_ROOT.'/lib/file.php');

$targetMod = null;
$pushedErrorForCurrentFile = false; // This is here so we can push the errors even if the file is not submitted, but dont duplicate the error message in case it is.

// /edit/release/?assetid=32 (edit existing release)
if(!empty($_REQUEST['assetid'])) {
	$release = $con->getRow("
		SELECT a.assetId, a.text, a.numSaved, r.*,
			createdBy.name AS createdByUsername,
			lastEditedBy.name AS lastEditedByUsername,
			rr.reason AS retractionReason,
			lastRetractedBy.roleId IN (?,?) AS retractedByModerator,
			mr.requestId
		FROM modReleases r
		JOIN assets a ON a.assetId = r.assetId
		LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
		LEFT JOIN users createdBy ON createdBy.userId = a.createdbyuserid
		LEFT JOIN users lastEditedBy ON lastEditedBy.userId = a.editedbyuserid
		LEFT JOIN users lastRetractedBy ON lastRetractedBy.userId = rr.lastModifiedBy
		LEFT JOIN moderationRequests mr ON mr.kind = ".MOD_REQUEST_KIND_MANUAL_REVIEW." AND mr.referenceId = r.releaseId
		WHERE r.assetId = ?
	", [ROLE_ADMIN, ROLE_MODERATOR, $_REQUEST['assetid']]);

	if($release) {
		$targetMod = $con->getRow(<<<SQL
			SELECT a.assetId, a.createdByUserId, a.name, m.modId, m.category, m.uploadLimitOverwrite, m.urlAlias
			FROM mods m
			JOIN assets a ON a.assetId = m.assetId
			WHERE m.modId = ?
		SQL, [$release['modId']]);
	}
}
// /edit/release/?modid=32  (add new release)
else if(!empty($_REQUEST['modid'])) {
	$release = [
		'releaseId' => 0,
		'assetId'   => 0,
		'status'    => RELEASE_STATUS_RELEASED,
		'numSaved'  => 0,
		'compatibleGameVersions' => [],
		'retractionReason' => null,
		'identifier' => '',
		'version'    => null,
		'text'       => '',
	];

	$targetMod = $con->getRow(<<<SQL
		SELECT a.assetId, a.createdByUserId, a.name, m.modId, m.category, m.uploadLimitOverwrite, m.urlAlias
		FROM mods m
		JOIN assets a ON a.assetId = m.assetId
		WHERE m.modId = ?
	SQL, [$_REQUEST['modid']]);
}

//NOTE(Rennorb): Do as little work as possible before this permission check, but don't unnecessarily split queries.
if(!$targetMod)   showErrorPage(HTTP_NOT_FOUND, 'Target mod was not found.');
if(!canEditMod($targetMod, $user))   showErrorPage(HTTP_FORBIDDEN);

if($release['releaseId']) {
	$attachedFiles = $con->getAssoc(<<<SQL
		SELECT f.fileId, f.assetId, f.name, f.created, f.size, f.cdnPath, mpr.modIdentifier, mpr.modVersion, mpr.capabilities, mpr.rawDependencies
		FROM files f
		LEFT JOIN modPeekResults mpr ON mpr.fileId = f.fileId
		WHERE f.assetId = ?
	SQL, [$release['assetId']]);
}
else {
	$attachedFiles = [];
}

// hovering files
$foldedTypes = implode(',', UPLOAD_LIMITS[ASSETTYPE_RELEASE]['allowedTypes']);
$hoveringFiles = $con->getAssoc(<<<SQL
	SELECT f.fileId, f.assetId, f.name, f.created, f.size, f.cdnPath, mpr.modIdentifier, mpr.modVersion, mpr.capabilities, mpr.errors, mpr.rawDependencies
	FROM files f
	LEFT JOIN modPeekResults mpr ON mpr.fileId = f.fileId
	WHERE f.assetId IS NULL AND f.type IN ($foldedTypes) AND f.userId = ?
SQL, [$user['userId']]);

foreach($attachedFiles as $fileId => &$file) {
	$file['fileId'] = $fileId;
	$file['gameVersionDep'] = findMinCompatibleGameVersion($file['rawDependencies']);
}
unset($file); //TODO(Rennorb) @cleanup
foreach($hoveringFiles as $fileId => &$file) {
	$file['fileId'] = $fileId;
	$file['gameVersionDep'] = findMinCompatibleGameVersion($file['rawDependencies']);
}
unset($file); //TODO(Rennorb) @cleanup
$mergedFiles = array_replace($attachedFiles, $hoveringFiles); // replace instead of merge to preserve keys.


$canCurrentlyBeEdited = !$release['releaseId']; //TODO

//
// Actions
//

if(!empty($_POST['save'])) {
	validateActionToken();

	$oldReleaseData = $release;

	$oldMsgCount = count($messages);

	//
	// Validate
	//

	if(!$canCurrentlyBeEdited) {
		addMessage(MSG_CLASS_ERROR, 'This release is public and cannot be edited.');
	}

	if(isset($_POST['text'])) {
		$release['text'] = trimHtml(sanitizeHtml($_POST['text']));

		$textLen = strlen($release['text']);
		if($textLen > 65535) { // TEXT column max length in assets.text
			$sizeKb = floor($textLen / 1024);
			$reason = "Excessive size ({$sizeKb}KB).";
			if(str_contains($release['text'], 'src="data:image')) $reason .= " You cannot paste large images directly. If you need a large image, upload it to an external site and link to that.";
			addMessage(MSG_CLASS_ERROR, $reason);
		}
	}

	// Files:
	$release['attachedFile'] = reset($attachedFiles);
	$filesToAttachInOrder = getInputArrayOfInts(INPUT_POST, 'attachedFileIds');
	if($filesToAttachInOrder) do {
		assert(UPLOAD_LIMITS[ASSETTYPE_RELEASE]['attachmentCount'] === 1);
		
		if(count($filesToAttachInOrder) > UPLOAD_LIMITS[ASSETTYPE_RELEASE]['attachmentCount']) {
			addMessage(MSG_CLASS_ERROR, "Trying to attach too many files.");
			break;
		}

		if(count($filesToAttachInOrder) > count($mergedFiles)) {
			addMessage(MSG_CLASS_ERROR, "Error trying to attach files, mismatch between client and server.");
			break;
		}

		// Validate that the file ids supplied for ordering actually belong to this mod:
		$flippedOrder = array_flip($filesToAttachInOrder);
		$newFiles = array_intersect_key($mergedFiles, $flippedOrder);
		if(count($newFiles) !== count($filesToAttachInOrder)) {
			addMessage(MSG_CLASS_ERROR, "Error trying to attach files, mismatch between client and server.");
			break;
		}

		foreach($newFiles as $fileId => &$file) {
			$file['order'] = $flippedOrder[$fileId];
			$file['assetId'] = -1; // Needs to be fake, releases currently being created do not have a assetid to attach to.
		}
		unset($file);

		// uasort($newFiles, fn($f1, $f2) => $f1['order'] - $f2['order']);

		$attachedFiles = $newFiles;

		$mergedFiles = array_replace($mergedFiles, $attachedFiles); // replace instead of merge to preserve keys.
	} while(0);
	else {
		addMessage(MSG_CLASS_ERROR, "Missing release file.");
	}

	$clientFileSet = getInputArrayOfInts(INPUT_POST, 'fileIds');
	$filesToDelete = array_diff_key($mergedFiles, array_flip($clientFileSet));

	$firstAttachedFile = reset($attachedFiles);

	$newCompatibleGameVersions = null;

	if(($targetMod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD) {
		// Mods take modid and version from the attached file. We no longer allow manual entry.
		if($attachedFiles) {
			$release['identifier'] = $firstAttachedFile['modIdentifier'];
			$release['version']    = $firstAttachedFile['modVersion'];

			if (in_array($release['identifier'], ["game", "creative", "survival"])) { // Reserve special mod ids
				addMessage(MSG_CLASS_ERROR, "This modid ('{$release['identifier']}') is reserved.");
			}
			else {
				$sqlIgnoreExistingRelease = $release ? "r.releaseId != {$release['releaseId']} AND" : ''; // @security $release['releaseId'] comes from the database and is numeric, therefore sql inert.
				$inUseBy = $con->getRow(<<<SQL
					SELECT a.assetId, r.modId, r.version, m.assetId as modAssetId, m.urlAlias
					FROM modReleases r
					JOIN assets a ON a.assetId = r.assetId
					JOIN mods m ON m.modId = r.modId
					WHERE $sqlIgnoreExistingRelease r.identifier = ? AND (r.modId != ? || r.version = ?)
					LIMIT 1
				SQL, [$release['identifier'], $targetMod['modId'], $release['version']]);

				if ($inUseBy) {
					if($inUseBy['modId'] == $targetMod['modId'] && $inUseBy['version'] == $release['version']) {
						$rv = formatSemanticVersion(intval($release['version']));
						addMessage(MSG_CLASS_ERROR, "This version ($rv) of the mod has already been released (<a href='/edit/release/?assetid={$inUseBy['assetId']}'>link</a>).");
					}
					else {
						$mid = escapeHtml($release['identifier']);
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

		// If the release itself is ok, but the caps are extensive, hold the release for review:
		if(count($messages) === $oldMsgCount && $attachedFiles) {
			if($firstAttachedFile['capabilities'] & MOD_CAPS_REQUIRING_REVIEW) {
				$release['status'] = RELEASE_STATUS_AWAITING_REVIEW;
			}
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
				$release['version'] = $version;
			}
		}
	}

	if(count($messages) === $oldMsgCount) { // no errors occurred
		//
		// Save
		//

		if($filesToDelete) {
			tryDeleteFiles($filesToDelete);
		}

		if($oldReleaseData['releaseId']) {
			$ok = updateRelease($targetMod, $oldReleaseData, $release, $firstAttachedFile, $newCompatibleGameVersions);
			if($ok) {
				if(!empty($_POST['saveandback'])) forceRedirect(formatModPath($targetMod).'#tab-files');
				else                              forceRedirectAfterPOST();
				exit();
			}
		}
		else { // adding a new release, no $release
			$assetId = createNewRelease($targetMod, $release, $newCompatibleGameVersions, $firstAttachedFile);
			if($assetId) {
				if(!empty($_POST['saveandback'])) forceRedirect(formatModPath($targetMod).'#tab-files');
				else                              forceRedirect('/edit/release/?assetid='.$assetId);
				exit();
			}
		}
	}
}


//
// Prepare data for display
//

if($release['releaseId']) {
	switch($release['status']) {
		case RELEASE_STATUS_AWAITING_CHANGES: addMessage(MSG_CLASS_WARN.' permanent', "The reviewer has requested changes to this release."); break;
	}

	$compatibleGameVersions = $con->getCol('SELECT gameVersion FROM modReleaseCompatibleGameVersions WHERE releaseId = ?', $release['releaseId']);
	$release['compatibleGameVersions'] = array_flip(array_map('intval', $compatibleGameVersions));
}
else {
	if(!$pushedErrorForCurrentFile && !empty(reset($mergedFiles)['errors'])) {
		addMessage(MSG_CLASS_ERROR, 'There are issues with the current file: '.$mergedFiles[0]['errors'], true);
	}
}

foreach($mergedFiles as &$file) {
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


$auditLogs = $release ? $con->getAll(<<<SQL
	SELECT l.kind, l.info, l.created, u.name AS username, l.flags
	FROM auditLogs l
	JOIN users u ON u.userId = l.initiatorUserId
	WHERE l.referenceId = {$release['releaseId']}
	ORDER BY l.created DESC
	LIMIT 20
SQL) : [];



if(!$release['releaseId']) {
	if(($targetMod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD) {
		// Pre-select values from hovering file, but only if this is a post-bounce or we only have one file:
		$candidateFile = null;
		if(!empty($firstAttachedFile)) $candidateFile = $firstAttachedFile;
		else if(count($mergedFiles) === 1) $candidateFile = reset($mergedFiles);

		if($candidateFile) {
			if($candidateFile['capabilities'] & MOD_CAPS_REQUIRING_REVIEW) {
				addMessage(MSG_CLASS_WARN, 'This mod uses potentially dangerous features of the dotnet runtime, and will require manual review.<br>A request for review will be submitted for you automatically after completing the release.', false);
			}

			$release['identifier'] = $candidateFile['modIdentifier'];
			$release['version']    = intval($candidateFile['modVersion']);

			if(!$release['compatibleGameVersions'] && ($minCompat = findMinCompatibleGameVersion($candidateFile['rawDependencies'])) && $minCompat !== null) {
				$detectedCompat = [];
				foreach($allGameVersions as $version) { // Order is descending, so the filter is trivial.
					if($version['version'] >= $minCompat) $detectedCompat[] = $version['version'];
					else break;
				}
				$release['compatibleGameVersions'] = array_flip($detectedCompat);
			}
		}
	}
}

if($release['version'] !== null) $release['version'] = formatSemanticVersion($release['version']);

$allGameVersionsTree = [];
foreach($allGameVersions as $gameVersion) {
	$num = $gameVersion['version'];

	$major = ($num & VERSION_MASK_MAJOR) >> 48;
	$minor = ($num & VERSION_MASK_MINOR) >> 32;
	$patch = ($num & VERSION_MASK_PATCH) >> 16;
	$prerelease = $num & VERSION_MASK_PRERELEASE;
	$allGameVersionsTree[$major][$minor][$patch][$prerelease] = $gameVersion;
}

$maxUploadSizeLimit = parseMaxUploadSizeFromIni();
$uploadSizeLimitOfThisMod = $targetMod['uploadLimitOverwrite'] !== null ? $targetMod['uploadLimitOverwrite'] : UPLOAD_LIMITS[ASSETTYPE_RELEASE]['individualSize'];
$view->assign('uploadSizeLimit', min($maxUploadSizeLimit, $uploadSizeLimitOfThisMod), null, true);


cspAllowTinyMceFull();
cspPushAllowedInlineHandlerHash('sha256-nTlTeikEEupAQmSPlHWFcoJvMdPCIBu+Zu+G64E7uC4='); // javascript:submitForm(0)
cspPushAllowedInlineHandlerHash('sha256-XKuSPEJjbu3T+mAY9wlP6dgYQ4xJL1rP4m3GrDwZ68c='); // javascript:submitForm(1)
cspReplaceAllowedFetchSources("{$_SERVER['HTTP_HOST']}/api/v2/files {$_SERVER['HTTP_HOST']}/api/v2/files/ {$_SERVER['HTTP_HOST']}/api/v2/mods/{$targetMod['modId']}/releases/{$release['releaseId']}/retraction");


$view->assign('allGameVersionsTree', $allGameVersionsTree, null, true);

$view->assign('mod', $targetMod);
$view->assign('doFileValidation', ($targetMod["category"] & CATEGORY__MASK) === CATEGORY_GAME_MOD, null, true);
$view->assign('canCurrentlyBeEdited', $canCurrentlyBeEdited, null, true);
$view->assign('attachedFileRequiresReview', !empty($firstAttachedFile) && $firstAttachedFile['capabilities'] & MOD_CAPS_REQUIRING_REVIEW, null, true);
$view->assign('release', $release);
$view->assign('files', $mergedFiles);

$view->assign('auditLogs', $auditLogs, null, true);

$view->display('edit-release');
