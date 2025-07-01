<?php

include_once $config['basepath'] . 'lib/modinfo.php';

/**
 * @param array $file
 * @param int   $assettypeid
 * @param int   $parentassetid
 * @return array{status:'error', errormessage:string}|(
 *   array{status:'ok', fileid:int, thumbnailfilepath:string, filename:string, uploaddate:string, releaseid?:int}
 *  &(array{modparse:'error', parsemsg:string}|array{modparse:'ok', modid:string, modversion:int})
 * )
 */
function processFileUpload($file, $assettypeid, $parentassetid) {
	global $con, $user;
	
	switch($file['error']) {
		case 0: break;
		case 1: 
		case 2: 
			return array("status" => "error", "errormessage" => 'File too large! Limit is ' . (file_upload_max_size() / 1024 / 1024) . "MB");
			break;
		case 7: return array("status" => "error", "errormessage" => 'Cannot write file to temporary files folder. No free space left?'); break;
		default: return array("status" => "error", "errormessage" => sprintf('A unexpected error occurend while uploading. Error number %s', $file['error'])); break;
		break;
	}	
	
	if (empty($assettypeid)) {
		return array("status" => "error", "errormessage" => 'Missing assettypeid');
	}

	if (!$file["tmp_name"]) return array("status" =>"error", "errormessage" => "unknown error");

	$assettype = $con->getRow("
		select
			maxfiles, 
			maxfilesizekb,
			allowedfiletypes,
			code
		from assettype
		where assettypeid=?
	", array($assettypeid));

	
	if ($parentassetid) {
		$asset = $con->getRow("select * from asset where assetid=?", array($parentassetid));
		
		if (!$asset) {
			return array("status" => "error", "errormessage" => 'Asset does not exist (anymore)'); 
		}
		
		if (!canEditAsset($asset, $user)) {
			return array("status" => "error", "errormessage" => 'No privilege to upload files to this asset. You may need to login again'); 
		}
	}
	
	if ($file['size'] / 1024 > $assettype['maxfilesizekb']) {
		return array("status" => "error", "errormessage" => 'File too large! Limit is ' . $assettype['maxfilesizekb'] . " KB");
	}

	splitOffExtension($file["name"], $filebasename, $ext);
	$exts = explode("|", $assettype["allowedfiletypes"]);
	
	if (!in_array($ext, $exts)) {
		return array("status" => "error", "errormessage" => 'Not allowed file type! Allowed is ' . implode(", ", $exts));
	}
	
	if ($parentassetid) {
		$quantityfiles = $con->getOne("select count(*) from file where assetid=?", array($parentassetid));
	} else {
		$quantityfiles = $con->getOne("select count(*) from file where assetid is null and assettypeid=? and userid=?", array($assettypeid, $user['userid']));
	}
	
	if ($quantityfiles + 1 > $assettype['maxfiles']) {
		return array("status" => "error", "errormessage" => 'Too many files! The limit is ' . $assettype['maxfiles'] . " for this asset");
	}


	$localpath = $file["tmp_name"];
	$cdnbasepath = generateCdnFileBasenameWithPath($user['userid'], $localpath, $filebasename);
	$cdnfilepath = "{$cdnbasepath}.{$ext}";

	$data = array("filename" => $file['name'], "cdnpath" => $cdnfilepath, "assettypeid" => $assettypeid, "userid" => $user['userid']);
	if($parentassetid) $data["assetid"] = $parentassetid;

	list($width, $height, $type, $attr) = getimagesize($file["tmp_name"]);
	if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_JPEG || $type == IMAGETYPE_PNG) {
		if ($width > 1920 || $height > 1080) {
			unlink($localpath);
			return array("status" => "error", "errormessage" => 'Image too large! Limit is 1920x1080 pixels');
		}

		$thumbStatus = createThumbnailAndUploadToCDN($localpath, $cdnbasepath, $ext);
		if($thumbStatus['status'] !== 'ok') {
			unlink($localpath);
			return $thumbStatus;
		}

		$data['hasthumbnail'] = true;
	}

	// Do this upload after analyzing the image, that way we don't needlessly upload files should resizing fail.
	$uploadresult = uploadToCdn($localpath, $cdnfilepath);
	if($uploadresult['error']) {
		unlink($localpath);
		return array("status" => "error", "errormessage" => 'CDN Error: '.$uploadresult['error']);
	}

	$foldedKeys = implode(', ', array_keys($data));
	$placeholders = substr(str_repeat(',?', count($data)), 1);
	if(!isset($data['hasthumbnail'])) {
		$con->execute("insert into file ($foldedKeys) values ($placeholders)", array_values($data));
	}
	else {
		// :BrokenSqlPointType
		$con->execute("insert into file (imagesize, $foldedKeys) values (POINT($width, $height), $placeholders)", array_values($data));
	}
	$fileid = $con->Insert_ID();

	if($parentassetid) logAssetChanges(array("Uploaded file '{$file['name']}'"), $parentassetid);
		
	$data = array(
		"status" => "ok",
		"fileid" => $fileid,
		"filepath" => formatCdnUrlFromCdnPath($cdnfilepath),
		"thumbnailfilepath" => isset($thumbStatus) ? formatCdnUrlFromCdnPath($thumbStatus['cdnthumbnailpath']) : null,
		"filename" => $file["name"],
		"uploaddate" => date("M jS Y, H:i:s")
	);
	if(isset($width)) $data['imagesize'] = "{$width}x{$height}";

	if ($assettype['code'] == 'release') {
		$ok = modpeek($localpath, $modInfo);
		$con->Execute('insert into ModPeekResult (fileId, errors, modIdentifier, modVersion, type, side, requiredOnClient, requiredOnServer, networkVersion, description, website, iconPath, rawAuthors, rawContributors, rawDependencies) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
			[$fileid, $modInfo['errors'], $modInfo['id'], $modInfo['version'], $modInfo['type'], $modInfo['side'], $modInfo['requiredOnClient'], $modInfo['requiredOnServer'], $modInfo['networkVersion'], $modInfo['description'], $modInfo['website'], $modInfo['iconPath'], $modInfo['rawAuthors'], $modInfo['rawContributors'], $modInfo['rawDependencies']]
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

	//TODO(Rennorb) @perf: unlink $localpath here?

	return $data;
}


// Returns a file size limit in bytes based on the PHP upload_max_filesize
// and post_max_size
function file_upload_max_size() {
  static $max_size = -1;

  if ($max_size < 0) {
    // Start with post_max_size.
    $post_max_size = parse_size(ini_get('post_max_size'));
    if ($post_max_size > 0) {
      $max_size = $post_max_size;
    }

    // If upload_max_size is less, then reduce. Except if upload_max_size is
    // zero, which indicates no limit.
    $upload_max = parse_size(ini_get('upload_max_filesize'));
    if ($upload_max > 0 && $upload_max < $max_size) {
      $max_size = $upload_max;
    }
  }
  return $max_size;
}

function parse_size($size) {
  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
  $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
  if ($unit) {
    // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
    return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
  }
  else {
    return round($size);
  }
}
