<?php

/**
 * @var array<string> $urlparts
 * @var array $user
 * @var object $con
 */

require(SRC_ROOT.'/lib/file.php');
require(SRC_ROOT.'/lib/modinfo.php');

if(empty($urlparts)) { // POST api/v2/files -> create new
	validateMethod('POST');
	validateUserNotBanned();
	validateActionToken();

	if(empty($_FILES['file']))  fail(HTTP_BAD_REQUEST, 'Missing file.');

	$file = $_FILES['file'];

	switch($file['error']) {
		case UPLOAD_ERR_OK:
			break;

		case UPLOAD_ERR_INI_SIZE: 
		case UPLOAD_ERR_FORM_SIZE: 
			fail(HTTP_TOO_LARGE, 'File too large! Limit is '.(parseMaxUploadSizeFromIni() / MB).'MB.');

		case UPLOAD_ERR_CANT_WRITE:
			fail(HTTP_INTERNAL_ERROR, 'Cannot write file to temporary files folder. No free space left?');

		default:
			fail(HTTP_INTERNAL_ERROR, 'A unexpected error occurred while uploading. Error number '.$file['error']);
	}	
	
	if(!$file["tmp_name"]) fail(HTTP_INTERNAL_ERROR, "Unknown error.");

	$fileSize = filesize($file['tmp_name']);

	$usedFloatingSize = $con->getOne('SELECT SUM(`size`) FROM files WHERE assetId IS NULL AND userId = '.$user['userId']);
	if($usedFloatingSize + $fileSize > PER_USER_FLOATING_STORAGE_BUDGET)  fail(HTTP_TOO_LARGE, [
		'error'    => 'This file would exceed your storage limit for floating files.',
		'limit'    => PER_USER_FLOATING_STORAGE_BUDGET,
		'used'     => $usedFloatingSize,
		'fileSize' => $fileSize,
	]);

	$fstream = fopen($file['tmp_name'], 'rb');
	
	$type = extractFileType($file, $fstream);
	
	//NOTE(Rennorb): Don't bother closing the file if we terminate - the process will end and the file will be closed and deleted anyway.
	if(!$type)  fail(HTTP_BAD_REQUEST, 'File format not supported.');

	splitOffExtension($file["name"], $filebasename, $ext);
	
	$localPath = $file["tmp_name"];
	$cdnBasePath = generateCdnFileBasenameWithPath($user['userId'], $localPath, $filebasename);
	$cdnFilePath = "{$cdnBasePath}.{$ext}";


	$acceptedImage = false;
	$hasThumbnail = false;

	$con->startTrans();

	switch($type) {
		case FILE_TYPE_GIF:
		case FILE_TYPE_JPEG:
		case FILE_TYPE_PNG:
		case FILE_TYPE_WEBP_STATIC:
		case FILE_TYPE_WEBP_ANIM:
			list($width, $height) = getimagesize($file["tmp_name"]);
			if($width > 1920 || $height > 1080) {
				fail(HTTP_TOO_LARGE, 'Image too large! Limit is 1920x1080 pixels');
			}

			// GD is entirely incapable of handling animated WebP, so we omit generating an editor thumbnail. Everything else just works.
			if($type !== FILE_TYPE_WEBP_ANIM) {
				$thumbStatus = createThumbnailAndUploadToCDN($localPath, $cdnBasePath, $ext);
				if($thumbStatus['status'] !== 'ok') {
					fail(HTTP_INTERNAL_ERROR, $thumbStatus['errormessage']);
				}
				$hasThumbnail = true;
			}

			$acceptedImage = true;
	}

	// Do this upload after analyzing the image, that way we don't needlessly upload files should resizing fail.
	$uploadresult = uploadToCdn($localPath, $cdnFilePath);
	if($uploadresult['error']) {
		if(isset($thumbStatus))  deleteFromCdn($thumbStatus['cdnthumbnailpath']);
		fail(HTTP_INTERNAL_ERROR, 'CDN Error: '.$uploadresult['error']);
	}

	$assetTypeId = $acceptedImage ? ASSETTYPE_MOD : ASSETTYPE_RELEASE;
	$con->execute("INSERT INTO files (assetTypeId, userId, `name`, cdnPath, `size`, `type`, `order`) VALUES ($assetTypeId, {$user['userId']}, ?, ?, $fileSize, $type, 0x7f)", [$file['name'], $cdnFilePath]);
	$fileId = $con->Insert_ID();

	if($acceptedImage) {
		$con->execute("INSERT INTO fileImageData (fileId, hasThumbnail, size) VALUES (?, ?, POINT(?, ?))", [$fileId, intval($hasThumbnail), $width, $height]);
	}

	logAuditEvent(AUDIT_LOG_KIND_FILE_CREATE, $fileId, "{$file['name']}");

	$response = array(
		"id"   => $fileId,
		"name" => $file["name"],
		"url"  => formatCdnUrlFromCdnPath($cdnFilePath),
	);
	if(isset($thumbStatus)) {
		$response['thumbUrl'] = formatCdnUrlFromCdnPath($thumbStatus['cdnthumbnailpath']);
	}
	if(isset($width)) {
		$response['imgWidth']  = $width;
		$response['imgHeight'] = $height;
	}

	if($assetTypeId === ASSETTYPE_RELEASE) {
		$ok = modpeek($localPath, $modInfo);
		$con->execute('INSERT INTO modPeekResults (fileId, errors, modIdentifier, modVersion, type, side, requiredOnClient, requiredOnServer, networkVersion, capabilities, description, website, iconPath, rawAuthors, rawContributors, rawBackgroundPaths, rawDependencies, rawReflections) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
			[$fileId, $modInfo['errors'], $modInfo['id'], $modInfo['version'], $modInfo['type'], $modInfo['side'], $modInfo['requiredOnClient'], $modInfo['requiredOnServer'], $modInfo['networkVersion'], $modInfo['capabilities'], $modInfo['description'], $modInfo['website'], $modInfo['iconPath'], $modInfo['rawAuthors'], $modInfo['rawContributors'], $modInfo['rawBackgroundPaths'], $modInfo['rawDependencies'], $modInfo['rawReflections']]
		);

		$minCompat = findMinCompatibleGameVersion($modInfo['rawDependencies']);
		if($minCompat !== null) $response['gameVersionDep'] = $minCompat;

		$response['requiresReview'] = boolval($modInfo['capabilities'] & MOD_CAPS_REQUIRING_REVIEW);

		// array{modParse:'error', parseMsg:string}|array{modParse:'ok', modId:string, modVersion:int}
		if($ok) {
			$response['modParse']   = 'ok';
			$response['modId']      = $modInfo['id'];
			$response['modVersion'] = $modInfo['version'];
		}
		else {
			$response['modParse'] = 'error';
			$response['parseMsg'] = $modInfo['errors'];
		}
	}

	$con->completeTrans();

	header('Location: /api/v2/files/'.$fileId, true, HTTP_CREATED);
	good($response);
}

// else
$l1verb = array_shift($urlparts);
if($l1verb && ($fileId = intval($l1verb)) == $l1verb) {
	switch($_SERVER['REQUEST_METHOD']) {
		case 'DELETE':
			validateUserNotBanned();
			parseRequestBody();
			validateActionToken();

			$file = $con->getRow(<<<SQL
				SELECT 
					f.fileId, f.name, f.userId, f.cdnPath, d.hasThumbnail, r.releaseId, r.status,
					COALESCE(mr.modId, mf.modId) AS modId, am.createdByUserId,
					f.assetId, a.assetTypeId -- for the file deletion function
				FROM files f
				LEFT JOIN assets a ON a.assetId = f.assetId
				LEFT JOIN fileImageData d ON d.fileId = f.fileId
				LEFT JOIN modReleases r ON r.assetId = f.assetId
				LEFT JOIN mods mf ON mf.assetId = f.assetId
				LEFT JOIN mods mr ON mr.modId = r.modId
				LEFT JOIN assets am ON am.assetId = COALESCE(mr.assetId, f.assetId)
				WHERE f.fileId = $fileId
			SQL);

			if(!$file) fail(HTTP_NOT_FOUND);

			if($file['modId']) {
				if(!canEditMod($file, $user)) {
					fail(HTTP_FORBIDDEN, 'You are not allowed to delete this file.');
				}
			}
			else {
				if($file['userId'] !== $user['userId'] && $user['roleCode'] !== 'admin') {
					fail(HTTP_FORBIDDEN, 'You are not allowed to delete this file.');
				}
			}

			if($file['releaseId'] && $file['status'] !== RELEASE_STATUS_AWAITING_CHANGES) {
				fail(HTTP_BAD_REQUEST, 'Released files cannot be deleted.');
			}

			tryDeleteFiles([$file]);

			good();

		default:
			header('Allow: DELETE');
			fail(HTTP_WRONG_METHOD);
	}
}
