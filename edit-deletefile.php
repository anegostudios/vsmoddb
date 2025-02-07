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

$ext = pathinfo($file['filename'], PATHINFO_EXTENSION);
deleteFromCdn("{$file['cdnpath']}.{$ext}");
if($file['hasthumbnail']) deleteFromCdn("{$file['cdnpath']}_55_60.{$ext}");

$con->Execute("delete from file where fileid=?", array($fileid));

logAssetChanges(array("Deleted file '{$file['filename']}'"), $assetid);

exit(json_encode(array("status" => "ok")));
