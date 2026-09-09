<?php

include_once $config['basepath'] . 'lib/modinfo.php';

/**
 * @param array $file
 * @param int   $assetTypeId
 * @param int   $parentAssetId
 * @param int   $parentModId
 * @return array{status:'error', errormessage:string}|(
 *   array{status:'ok', fileid:int, thumbnailfilepath:string, filename:string, uploaddate:string, releaseid?:int}
 *  &(array{modparse:'error', parsemsg:string}|array{modparse:'ok', modid:string, modversion:int})
 * )
 */
function processFileUpload($file, $assetTypeId, $parentAssetId, $parentModId) {
	global $con, $user;
	
	switch($file['error']) {
		case UPLOAD_ERR_OK:
			break;

		case UPLOAD_ERR_INI_SIZE: 
		case UPLOAD_ERR_FORM_SIZE: 
			return array("status" => "error", "errormessage" => 'File too large! Limit is ' . (parseMaxUploadSizeFromIni() / MB) . "MB");

		case UPLOAD_ERR_CANT_WRITE:
			return array("status" => "error", "errormessage" => 'Cannot write file to temporary files folder. No free space left?');

		default:
			return array("status" => "error", "errormessage" => sprintf('A unexpected error occurred while uploading. Error number %s', $file['error']));
	}	
	
	if (empty($assetTypeId)) {
		return array("status" => "error", "errormessage" => 'Missing assettypeid');
	}

	if (!$file["tmp_name"]) return array("status" =>"error", "errormessage" => "unknown error");

	$limits = UPLOAD_LIMITS[$assetTypeId];

	if($assetTypeId === ASSETTYPE_RELEASE) { // adding / editing mod releases
		$mod = $con->getRow(<<<SQL
			SELECT m.modId, a.createdByUserId, m.uploadLimitOverwrite
			FROM mods m
			JOIN assets a ON a.assetId = m.assetId
			WHERE m.modId = ?
		SQL, [$parentModId]);

		if (!$mod) {
			return array("status" => "error", "errormessage" => 'Asset does not exist (anymore)'); 
		}

		if (!canEditMod($mod, $user)) {
			return array("status" => "error", "errormessage" => 'Missing permissions to upload files to this asset. You may need to login again'); 
		}

		if($mod['uploadLimitOverwrite'] !== null) $limits['individualSize'] = $mod['uploadLimitOverwrite'];
	}
	else {
		$mod = $con->getRow('SELECT m.modId, a.createdByUserId FROM mods m JOIN assets a ON a.assetId = m.assetId WHERE m.modId = ?', [$parentModId]);
	}

	if ($parentAssetId) { // Editing existing releases or adding mod images
		if($assetTypeId === ASSETTYPE_RELEASE) {
			$release = $con->getRow('SELECT r.releaseId, rr.reason FROM modReleases r LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId WHERE r.assetId = ?', [$parentAssetId]);
			if($release && $release['reason']) {
				return array("status" => "error", "errormessage" => 'Release has been retracted: '.textContent($release['reason'])); 
			}
		}

		if (!$mod) {
			return array("status" => "error", "errormessage" => 'Asset does not exist (anymore)'); 
		}
		
		if (!canEditMod($mod, $user)) {
			return array("status" => "error", "errormessage" => 'Missing permissions to upload files to this asset. You may need to login again'); 
		}
	}
	
	if ($file['size'] > $limits['individualSize']) {
		return array("status" => "error", "errormessage" => 'File too large! Limit is ' . formatByteSize($limits['individualSize']));
	}

	splitOffExtension($file["name"], $filebasename, $ext);
	$allowedExts = $limits['allowedTypes'];
	
	if (!in_array($ext, $allowedExts)) {
		return array("status" => "error", "errormessage" => 'File type not allowed! Allowed are ' . formatGrammaticallyCorrectEnumeration($allowedExts).'.');
	}
	
	if ($parentAssetId) {
		$quantityfiles = $con->getOne("select count(*) from files where assetId = ?", array($parentAssetId));
	} else {
		$quantityfiles = $con->getOne("select count(*) from files where assetId is null and assetTypeId = ? and userId = ?", array($assetTypeId, $user['userId']));
	}
	
	if ($quantityfiles + 1 > $limits['attachmentCount']) {
		return array("status" => "error", "errormessage" => 'Too many files! The limit is ' . $limits['attachmentCount'] . " for this asset");
	}


	$localPath = $file["tmp_name"];
	$cdnBasePath = generateCdnFileBasenameWithPath($user['userId'], $localPath, $filebasename);
	$cdnFilePath = "{$cdnBasePath}.{$ext}";

	$data = array("name" => $file['name'], "cdnPath" => $cdnFilePath, "assetTypeId" => $assetTypeId, "userId" => $user['userId'], "order" => $quantityfiles, "size" => $file['size']);
	if($parentAssetId) $data["assetId"] = $parentAssetId;

	$acceptedImage = false;
	$hasThumbnail = false;

	list($width, $height, $type, $attr) = getimagesize($file["tmp_name"]);
	if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_JPEG || $type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
		if ($width > 1920 || $height > 1080) {
			unlink($localPath);
			return array("status" => "error", "errormessage" => 'Image too large! Limit is 1920x1080 pixels');
		}

		// GD is entirely incapable of handling animated WebP, so we omit generating an editor thumbnail. Everything else just works.
		if ($type != IMAGETYPE_WEBP || !isAnimatedWebp(file_get_contents($localPath))) {
			$thumbStatus = createThumbnailAndUploadToCDN($localPath, $cdnBasePath, $ext);
			if($thumbStatus['status'] !== 'ok') {
				unlink($localPath);
				return $thumbStatus;
			}
			$hasThumbnail = true;
		}

		$acceptedImage = true;
	}

	// Do this upload after analyzing the image, that way we don't needlessly upload files should resizing fail.
	$uploadresult = uploadToCdn($localPath, $cdnFilePath);
	if($uploadresult['error']) {
		unlink($localPath);
		return array("status" => "error", "errormessage" => 'CDN Error: '.$uploadresult['error']);
	}

	$foldedKeys = implode('`, `', array_keys($data));
	$placeholders = substr(str_repeat(',?', count($data)), 1);
	$con->execute("INSERT INTO files (`$foldedKeys`) VALUES ($placeholders)", array_values($data));
	$fileId = $con->Insert_ID();
	if($acceptedImage) {
		$con->execute("INSERT INTO fileImageData (fileId, hasThumbnail, size) VALUES (?, ?, POINT(?, ?))", [$fileId, intval($hasThumbnail), $width, $height]);
	}

	$logFlagsGeneral = canModerate(null, $user) ? AUDIT_LOG_FLAG_MODACTION : 0; // @correctness: filter out team members.
	if($parentAssetId) {
		if($assetTypeId === ASSETTYPE_RELEASE) {
			logAuditEvent(AUDIT_LOG_KIND_RELEASE_CHANGE_FILE, $release['releaseId'], "$fileId", $logFlagsGeneral);
		}
		else {
			logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_IMAGES, $parentModId, "$fileId", $logFlagsGeneral);
		}
	}
	else {
		logAuditEvent(AUDIT_LOG_KIND_FILE_CREATE, $fileId, "{$file['name']}");
	}

	$data = array(
		"status" => "ok",
		"fileid" => $fileId,
		"filepath" => formatCdnUrlFromCdnPath($cdnFilePath),
		"thumbnailfilepath" => isset($thumbStatus) ? formatCdnUrlFromCdnPath($thumbStatus['cdnthumbnailpath']) : null,
		"filename" => $file["name"],
		"uploaddate" => date("M jS Y, H:i:s")
	);
	if(isset($width)) $data['imagesize'] = "{$width}x{$height}";

	if ($assetTypeId === ASSETTYPE_RELEASE) {
		$ok = modpeek($localPath, $modInfo);
		$con->Execute('INSERT INTO modPeekResults (fileId, errors, modIdentifier, modVersion, type, side, requiredOnClient, requiredOnServer, networkVersion, description, website, iconPath, rawAuthors, rawContributors, rawBackgroundPaths, rawDependencies) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
			[$fileId, $modInfo['errors'], $modInfo['id'], $modInfo['version'], $modInfo['type'], $modInfo['side'], $modInfo['requiredOnClient'], $modInfo['requiredOnServer'], $modInfo['networkVersion'], $modInfo['description'], $modInfo['website'], $modInfo['iconPath'], $modInfo['rawAuthors'], $modInfo['rawContributors'], $modInfo['rawBackgroundPaths'], $modInfo['rawDependencies']]
		);

		$minCompat = findMinCompatibleGameVersion($modInfo['rawDependencies']);
		if($minCompat !== null) $data['gameversiondep'] = $minCompat;

		// array{modparse:'error', parsemsg:string}|array{modparse:'ok', modid:string, modversion:int}
		if($ok) {
			$data['modparse']   = 'ok';
			$data['modid']      = $modInfo['id'];
			$data['modversion'] = $modInfo['version'];
		}
		else {
			$data['modparse'] = 'error';
			$data['parsemsg'] = $modInfo['errors'];
		}
	}

	unlink($localPath);

	return $data;
}
