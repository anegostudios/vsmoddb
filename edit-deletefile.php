<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}
if (!$user['roleId']) showErrorPage(HTTP_FORBIDDEN);

validateActionToken();

if (empty($_POST['fileid'])) {
	http_response_code(HTTP_BAD_REQUEST);
	exit(json_encode(['status' => 'error']));
}

$fileId = $_POST['fileid'];
$file = $con->getRow(<<<SQL
	SELECT name, assetId, userId, cdnPath, hasThumbnail
	FROM files f
	LEFT JOIN fileImageData d ON d.fileId = f.fileId
	WHERE f.fileId = ?
SQL, [$fileId]);

if (!$file) {
	http_response_code(HTTP_NOT_FOUND);
	exit(json_encode(['status' => 'error']));
}


$assetId = $file['assetId'];

if ($assetId) {
	$asset = $con->getRow('SELECT * FROM assets WHERE assetId = ?', [$assetId]);
	if (!canEditAsset($asset, $user)) {
		exit(json_encode(['status' => 'error', 'errormessage' => 'No privilege to delete files from this asset. You may need to login again'])); 
	}

} else {
	if ($file['userId'] != $user['userId']  && $user['roleCode'] != 'admin') {
		exit(json_encode(['status' => 'error', 'errormessage' => 'No privilege to delete files from this asset. You may need to login again']));
	}
}

splitOffExtension($file['cdnPath'], $noext, $ext);

$con->Execute('UPDATE mods SET cardLogoFileId = NULL WHERE cardLogoFileId = ?', [$fileId]);
$con->Execute('UPDATE mods SET embedLogoFileId = NULL WHERE embedLogoFileId = ?', [$fileId]);
$con->Execute('DELETE FROM files WHERE fileId = ?', [$fileId]);

$countOfFilesUsingThisCDNPath = $con->getOne('SELECT COUNT(*) FROM files WHERE cdnPath = ?', [$file['cdnPath']]);
if($countOfFilesUsingThisCDNPath < 2) {
$con->Execute('DELETE FROM files WHERE cdnPath = ?', ["{$noext}_480_320.{$ext}"]); // legacy logo
	//TODO(Rennorb) @correctness: Could try and figure out if there is a difference between a "generic error" response and "this file does not exist" and then decided on whether or not this should be an error.
	// For now we ignore errors here, even if we fail to delete from cdn we still deleted the table entry because we otherwise block user interaction because of third party issues (no-go).
	deleteFromCdn($file['cdnPath']);
	if($file['hasThumbnail']) deleteFromCdn("{$noext}_55_60.{$ext}"); // thumbnail
	deleteFromCdn("{$noext}_480_320.{$ext}"); // legacy logo

	logAssetChanges(["Deleted file '{$file['name']}' and underlying resources"], $assetId);
}
else {
	$others = $countOfFilesUsingThisCDNPath - 1;
	logAssetChanges(["Deleted file entry '{$file['name']}', $others other file(s) are still using the underlying resource"], $assetId);
}

exit(json_encode(['status' => 'ok']));
