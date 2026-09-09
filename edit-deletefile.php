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
	SELECT 
		f.fileId, f.name, f.userId, f.cdnPath, d.hasThumbnail, r.releaseId, rr.reason IS NOT NULL AS releaseRetracted,
		COALESCE(mr.modId, mf.modId) AS modId, am.createdByUserId,
		f.assetId, a.assetTypeId -- for the file deletion function
	FROM files f
	LEFT JOIN assets a ON a.assetId = f.assetId
	LEFT JOIN fileImageData d ON d.fileId = f.fileId
	LEFT JOIN modReleases r ON r.assetId = f.assetId
	LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
	LEFT JOIN mods mf ON mf.assetId = f.assetId
	LEFT JOIN mods mr ON mr.modId = r.modId
	LEFT JOIN assets am ON am.assetId = COALESCE(mr.assetId, f.assetId)
	WHERE f.fileId = ?
SQL, [$_POST['fileid']]);

if (!$file) {
	http_response_code(HTTP_NOT_FOUND);
	exit(json_encode(['status' => 'error']));
}

if ($file['releaseId']) {
	http_response_code(HTTP_BAD_REQUEST);
	exit(json_encode(['status' => 'error', 'reason' => 'Released files cannot be deleted.']));
}

if ($file['releaseRetracted']) {
	http_response_code(HTTP_BAD_REQUEST);
	exit(json_encode(['status' => 'error', 'reason' => 'Associated release has been retracted.']));
}


if ($file['modId']) {
	if (!canEditMod($file, $user)) {
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
