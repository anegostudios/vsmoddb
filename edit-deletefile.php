<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}
if (!$user['roleId']) showErrorPage(HTTP_FORBIDDEN);

validateActionToken();

if (empty($_POST["fileid"])) {
	http_response_code(HTTP_BAD_REQUEST);
	exit(json_encode(array("status" => "error")));
}

$fileid = $_POST["fileid"];
$file = $con->getRow("select * from file where fileid=?", array($fileid));

if (!$file) {
	http_response_code(HTTP_NOT_FOUND);
	exit(json_encode(array("status" => "error")));
}


$assetid = $file["assetid"];

if ($assetid) {
	$asset = $con->getRow("select * from asset where assetid=?", array($assetid));
	if (!canEditAsset($asset, $user)) {
		exit(json_encode(array("status" => "error", "errormessage" => 'No privilege to delete files from this asset. You may need to login again'))); 
	}

} else {
	if ($file['userid'] != $user['userId']  && $user['roleCode'] != 'admin') {
		exit(json_encode(array("status" => "error", "errormessage" => 'No privilege to delete files from this asset. You may need to login again')));
	}
}

splitOffExtension($file['cdnpath'], $noext, $ext);

$con->Execute("update `mod` set cardlogofileid = NULL where cardlogofileid = ?", array($fileid));
$con->Execute("update `mod` set embedlogofileid = NULL where embedlogofileid = ?", array($fileid));
$con->Execute("delete from file where fileid = ?", array($fileid));

$countOfFilesUsingThisCDNPath = $con->getOne('select count(*) from `file` where cdnpath = ?', [$file['cdnpath']]);
if($countOfFilesUsingThisCDNPath < 2) {
$con->Execute("delete from `file` where cdnpath = ?", array("{$noext}_480_320.{$ext}")); // legacy logo
	//TODO(Rennorb) @correctness: Could try and figure out if there is a difference between a "generic error" response and "this file does not exist" and then decided on whether or not this should be an error.
	// For now we ignore errors here, even if we fail to delete from cdn we still deleted the table entry because we otherwise block user interaction because of third party issues (no-go).
	deleteFromCdn($file['cdnpath']);
	if($file['hasthumbnail']) deleteFromCdn("{$noext}_55_60.{$ext}"); // thumbnail
	deleteFromCdn("{$noext}_480_320.{$ext}"); // legacy logo

	logAssetChanges(array("Deleted file '{$file['filename']}' and underlying resources"), $assetid);
}
else {
	$others = $countOfFilesUsingThisCDNPath - 1;
	logAssetChanges(array("Deleted file entry '{$file['filename']}', $others other file(s) are still using the underlying resource"), $assetid);
}

exit(json_encode(array("status" => "ok")));
