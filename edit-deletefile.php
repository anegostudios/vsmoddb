<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}
if (!$user['roleid']) {
	$view->display("403");
	exit();
}

if ($user['actiontoken'] != $_REQUEST['at']) {
	$view->assign("reason", "Invalid action token. To prevent CSRF, you can only submit froms directly on the site. If you believe this is an error, please contact Tyron");
	$view->display("400");
	exit();
}

if (empty($_POST["fileid"])) {
	exit(json_encode(array("status" => "error")));
}

$fileid = $_POST["fileid"];
$file = $con->getRow("select * from file where fileid=?", array($fileid));

if (!$file) {
	exit(json_encode(array("status" => "error")));
}


$assetid = $file["assetid"];

if ($assetid) {
	$asset = $con->getRow("select * from asset where assetid=?", array($assetid));
	if (!canEditAsset($asset, $user)) {
		exit(json_encode(array("status" => "error", "errormessage" => 'No privilege to delete files from this asset. You may need to login again'))); 
	}

} else {
	if ($file['userid'] != $user['userid']  && $user['rolecode'] != 'admin') {
		exit(json_encode(array("status" => "error", "errormessage" => 'No privilege to delete files from this asset. You may need to login again')));
	}
}

splitOffExtension($file['cdnpath'], $noext, $ext);

$con->Execute("update `mod` set logofileid=NULL where logofileid=?", array($fileid));
$con->Execute("delete from modpeek_result where fileid=?", array($fileid));
$con->Execute("delete from file where fileid=?", array($fileid));
$con->Execute("delete from `file` where cdnpath = ?", array("{$noext}_480_320.{$ext}")); // logo

//TODO(Rennorb) @correctness: Could try and figure out if there is a difference between a "generic error" response and "this file does not exist" and then decided on whether or not this should be an error.
// For now we ignore errors here, even if we fail to delete from cdn we still deleted the table entry because we otherwise block user interaction because of third party issues (no-go).
deleteFromCdn($file['cdnpath']);
if($file['hasthumbnail']) deleteFromCdn("{$noext}_55_60.{$ext}");
deleteFromCdn("{$noext}_480_320.{$ext}"); // logos

logAssetChanges(array("Deleted file '{$file['filename']}'"), $assetid);

exit(json_encode(array("status" => "ok")));
