<?php

include_once $config['basepath'] . 'lib/modinfo.php';

/**
 * @param array $file
 * @param int   $assetTypeId
 * @param int   $parentAssetId
 * @return array{status:'error', errormessage:string}|(
 *   array{status:'ok', fileid:int, thumbnailfilepath:string, filename:string, uploaddate:string, releaseid?:int}
 *  &(array{modparse:'error', parsemsg:string}|array{modparse:'ok', modid:string, modversion:int})
 * )
 */
function processFileUpload($file, $assetTypeId, $parentAssetId) {
	global $con, $user;
	
	switch($file['error']) {
		case 0: break;
		case 1: 
		case 2: 
			return array("status" => "error", "errormessage" => 'File too large! Limit is ' . (parseMaxUploadSizeFromIni() / MB) . "MB");
			break;
		case 7: return array("status" => "error", "errormessage" => 'Cannot write file to temporary files folder. No free space left?'); break;
		default: return array("status" => "error", "errormessage" => sprintf('A unexpected error occurend while uploading. Error number %s', $file['error'])); break;
		break;
	}	
	
	if (empty($assetTypeId)) {
		return array("status" => "error", "errormessage" => 'Missing assettypeid');
	}

	if (!$file["tmp_name"]) return array("status" =>"error", "errormessage" => "unknown error");

	$limits = UPLOAD_LIMITS[$assetTypeId];

	if ($parentAssetId) {
		$asset = $con->getRow("select * from asset where assetid=?", array($parentAssetId));
		
		if (!$asset) {
			return array("status" => "error", "errormessage" => 'Asset does not exist (anymore)'); 
		}
		
		if (!canEditAsset($asset, $user)) {
			return array("status" => "error", "errormessage" => 'Missing permissions to upload files to this asset. You may need to login again'); 
		}
	}
	
	if ($file['size'] > $limits['individualSize']) {
		return array("status" => "error", "errormessage" => 'File too large! Limit is ' . ($limits['individualSize'] / KB) . " KB");
	}

	splitOffExtension($file["name"], $filebasename, $ext);
	$allowedExts = $limits['allowedTypes'];
	
	if (!in_array($ext, $allowedExts)) {
		return array("status" => "error", "errormessage" => 'File type not allowed! Allowed are ' . formatGrammaticallyCorrectEnumeration($allowedExts).'.');
	}
	
	if ($parentAssetId) {
		$quantityfiles = $con->getOne("select count(*) from Files where assetId = ?", array($parentAssetId));
	} else {
		$quantityfiles = $con->getOne("select count(*) from Files where assetId is null and assetTypeId = ? and userId = ?", array($assetTypeId, $user['userId']));
	}
	
	if ($quantityfiles + 1 > $limits['attachmentCount']) {
		return array("status" => "error", "errormessage" => 'Too many files! The limit is ' . $limits['attachmentCount'] . " for this asset");
	}


	$localPath = $file["tmp_name"];
	$cdnBasePath = generateCdnFileBasenameWithPath($user['userId'], $localPath, $filebasename);
	$cdnFilePath = "{$cdnBasePath}.{$ext}";

	$data = array("name" => $file['name'], "cdnPath" => $cdnFilePath, "assetTypeId" => $assetTypeId, "userId" => $user['userId']);
	if($parentAssetId) $data["assetId"] = $parentAssetId;

	$acceptedImage = false;

	list($width, $height, $type, $attr) = getimagesize($file["tmp_name"]);
	if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_JPEG || $type == IMAGETYPE_PNG) {
		if ($width > 1920 || $height > 1080) {
			unlink($localPath);
			return array("status" => "error", "errormessage" => 'Image too large! Limit is 1920x1080 pixels');
		}

		$thumbStatus = createThumbnailAndUploadToCDN($localPath, $cdnBasePath, $ext);
		if($thumbStatus['status'] !== 'ok') {
			unlink($localPath);
			return $thumbStatus;
		}

		$acceptedImage = true;
	}

	// Do this upload after analyzing the image, that way we don't needlessly upload files should resizing fail.
	$uploadresult = uploadToCdn($localPath, $cdnFilePath);
	if($uploadresult['error']) {
		unlink($localPath);
		return array("status" => "error", "errormessage" => 'CDN Error: '.$uploadresult['error']);
	}

	$foldedKeys = implode(', ', array_keys($data));
	$placeholders = substr(str_repeat(',?', count($data)), 1);
	$con->execute("INSERT INTO Files ($foldedKeys) VALUES ($placeholders)", array_values($data));
	$fileId = $con->Insert_ID();
	if($acceptedImage) {
		// :BrokenSqlPointType
		$con->execute("INSERT INTO FileImageData (fileId, hasThumbnail, size) VALUES ($fileId, 1, POINT($width, $height))");
	}

	if($parentAssetId) logAssetChanges(array("Uploaded file '{$file['name']}'"), $parentAssetId);
		
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
		$con->Execute('insert into ModPeekResult (fileId, errors, modIdentifier, modVersion, type, side, requiredOnClient, requiredOnServer, networkVersion, description, website, iconPath, rawAuthors, rawContributors, rawDependencies) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
			[$fileId, $modInfo['errors'], $modInfo['id'], $modInfo['version'], $modInfo['type'], $modInfo['side'], $modInfo['requiredOnClient'], $modInfo['requiredOnServer'], $modInfo['networkVersion'], $modInfo['description'], $modInfo['website'], $modInfo['iconPath'], $modInfo['rawAuthors'], $modInfo['rawContributors'], $modInfo['rawDependencies']]
		);

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

	//TODO(Rennorb) @perf: unlink $localPath here?

	return $data;
}
