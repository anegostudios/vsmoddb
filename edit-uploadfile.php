<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}
if (!$user['roleid']) {
	$view->display("403");
	exit();
}

if (!empty($_POST["upload"]) && @$_FILES["file"]) {
	$file = $_FILES["file"];
	
	switch($_FILES['file']['error']) {
		case 0: break;
		case 1: 
		case 2: 
			exit(json_encode(array("status" => "error", "errormessage" => 'File too large! Limit is ' . (file_upload_max_size() / 1024 / 1024) . "MB")));
			break;
		case 7: exit(json_encode(array("status" => "error", "errormessage" => 'Cannot write file to temporary files folder. No free space left?'))); break;
		default: exit(json_encode(array("status" => "error", "errormessage" => sprintf(__('A unexpected error occurend while uploading. Error number %s'), $_FILES['fiupl']['error'])))); break;
		break;
	}	
	
	
	if (empty($_REQUEST['assettypeid'])) {
		exit(json_encode(array("status" => "error", "errormessage" => 'Missing assettypeid')));
	}

	$assettypeid = $_REQUEST['assettypeid'];

	if (!$file["tmp_name"]) exit('{ "status": "error", "errormessage" => "unknown error"}');
	
	$assettype = $con->getRow("
		select
			maxfiles, 
			maxfilesizekb,
			allowedfiletypes,
			code
		from assettype
		where assettypeid=?
	", array($assettypeid));

	
	if ($_REQUEST["assetid"]) {
		$createdbyuserid = $con->getOne("select createdbyuserid from asset where assetid=?", array($_REQUEST["assetid"]));
		
		if (!$createdbyuserid) {
			exit(json_encode(array("status" => "error", "errormessage" => 'Asset does not exist (anymore)'))); 
		}
		
		if ($createdbyuserid != $user['userid'] && $user['rolecode'] != 'admin') {
			exit(json_encode(array("status" => "error", "errormessage" => 'No privilege to upload files to this asset. You may need to login again'))); 
		}
	}
	
	if ($file['size'] / 1024 > $assettype['maxfilesizekb']) {
		exit(json_encode(array("status" => "error", "errormessage" => 'File too large! Limit is ' . $asset['maxfilesizekb'] . " KB")));
	}
	
	$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	$exts = explode("|", $assettype["allowedfiletypes"]);
	
	if (!in_array($ext, $exts)) {
		exit(json_encode(array("status" => "error", "errormessage" => 'Not allowed file type! Allowed is ' . implode(", ", $exts))));
	}
	
	if ($_REQUEST["assetid"]) {
		$quantityfiles = $con->getOne("select count(*) from file where assetid=?", array($_REQUEST['assetid']));
	} else {
		$quantityfiles = $con->getOne("select count(*) from file where assetid is null and assettypeid=? and userid=?", array($assettypeid, $user['userid']));
	}
	
	if ($quantityfiles + 1 > $assettype['maxfiles']) {
		exit(json_encode(array("status" => "error", "errormessage" => 'Too many files! The limit is ' . $assettype['maxfiles'] . " for this asset")));
	}
	
	$ismod = $assettype['code'] == 'release';
	
	if ($_REQUEST["assetid"]) {
		uploadFile($file, $_REQUEST["assetid"], $ismod);
	} else {
		uploadFileTemporary($file, $assettypeid, $ismod);
	}
}

function uploadFileTemporary($file, $assettypeid, $ismod) {
	global $con, $user;
	
	$dir = "tmp/" . $user['userid']."/";	
	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}
	
	$filename = urldecode($file["name"]);
	move_uploaded_file(
		$file["tmp_name"], 
		$dir . $filename
	);
	
	$data = array("filename" => $filename, "assettypeid" => $assettypeid, "userid" => $user['userid']);

	list($width, $height, $type, $attr) = getimagesize($dir . $filename);
	
	if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_JPEG || $type == IMAGETYPE_PNG) {
		
		if ($width > 1920 || $height > 1080) {
			exit(json_encode(array("status" => "error", "errormessage" => 'Image too large! Limit is 1920x1080 pixels')));
			unlink($dir . $filename);
		}
	
		$filename = copyImageResized($dir . $filename, 55, 60);
		$data["thumbnailfilename"] = basename($filename);
	}
	
	
	$fileid = insert("file");
	update("file", $fileid, $data);
		
	$data = array(
		"status" => "ok",
		"fileid" => $fileid,
		"thumbnailfilepath" => empty($data["thumbnailfilename"]) ? null : "/" . $dir . $data["thumbnailfilename"],
		"filename" => $filename,
		"uploaddate" => date("M jS Y, H:i:s")
	);

	if ($ismod) {
		$info = getModInfo($dir . $filename);
		$data = array_merge($data, $info);
	}

	exit(json_encode($data));	
}


function uploadFile($file, $assetid, $ismod) {
	global $con, $user;
	
	$dir = "files/asset/{$assetid}/";
	if (!is_dir($dir)) {
		mkdir($dir, 0755, true);
	}
	
	$filename = urldecode($file["name"]);
	
	move_uploaded_file(
		$file["tmp_name"], 
		$dir . $filename
	);
	
	$data = array("assetid" => $assetid, "filename" => $filename);
	

	
	list($width, $height, $type, $attr) = getimagesize($dir . $filename);
	if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_JPEG || $type == IMAGETYPE_PNG) {
		if ($width > 1920 || $height > 1080) {
			exit(json_encode(array("status" => "error", "errormessage" => 'Image too large! Limit is 1920x1080 pixels')));
			unlink($dir . $filename);
		}
	
		$filename = copyImageResized($dir . $filename, 55, 60);
		$data["thumbnailfilename"] = basename($filename);
	}
	
	$fileid = insert("file");
	update("file", $fileid, $data);
	
	logAssetChanges(array("Uploaded file '{$filename}'"), $assetid);
		
	$data = array(
		"status" => "ok",
		"fileid" => $fileid,
		"thumbnailfilepath" => empty($data["thumbnailfilename"]) ? null : "/" . $dir . $data["thumbnailfilename"],
		"filename" => $filename,
		"uploaddate" => date("M jS Y, H:i:s")
	);

	if ($ismod) {
		$info = getModInfo($dir . $filename);
		$data = array_merge($data, $info);
	}

	exit(json_encode($data));
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
