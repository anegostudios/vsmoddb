<?php
if (DB_READONLY) {
	http_response_code(HTTP_SERVICE_UNAVAILABLE);
	exit('{"status": "error", "errormessage": "We are currently in readonly mode."}');
}

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

$file = $con->getRow(<<<SQL
	SELECT f.fileId, f.name, f.assetId, f.userId, f.cdnPath, d.hasThumbnail, a.assetTypeId, r.retractionReason IS NOT NULL AS releaseRetracted
	FROM files f
	LEFT JOIN fileImageData d ON d.fileId = f.fileId
	LEFT JOIN assets a ON a.assetId = f.assetId
	LEFT JOIN modReleases r ON r.assetId = f.assetId
	WHERE f.fileId = ?
SQL, [$_POST['fileid']]);

if (!$file) {
	http_response_code(HTTP_NOT_FOUND);
	exit(json_encode(['status' => 'error']));
}

if ($file['releaseRetracted']) {
	http_response_code(HTTP_BAD_REQUEST);
	exit(json_encode(['status' => 'error', 'reason' => 'Associated release has been retracted.']));
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

include_once $config['basepath'].'lib/file.php';

tryDeleteFiles([$file]);

exit(json_encode(['status' => 'ok']));
