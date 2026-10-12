<?php

// expects to be called as  download/132465[/somefile.png]
// but the game client downloads it using ?fileid=1231 so we need to remain backwards compatible

$fileId = intval($_GET['fileid'] ?? $urlparts[1] ?? 0);
if(!$fileId) showErrorPage(HTTP_BAD_REQUEST, 'Missing fileid.');

$file = $con->getRow("
	SELECT f.assetId, f.cdnPath, f.name, r.modId, rr.reason AS retractionReason
	FROM files f
	LEFT JOIN modReleases r ON r.assetId = f.assetId AND r.status IN (".RELEASE_STATUS_RELEASED.", ".RELEASE_STATUS_RETRACTED.")
	LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
	WHERE f.fileId = $fileId
");
if(!$file) showErrorPage(HTTP_NOT_FOUND, 'File not found.');
if($file['retractionReason'] && (empty($user) || !canModerate(null, $user))) showErrorPage(HTTP_GONE, '<h4>This release has been retracted. Reason:</h4>'.$file['retractionReason'], false, true);

if(!DB_READONLY) {
	// do download tracking
	$identifier = [$fileId, $_SERVER['REMOTE_ADDR'], $user['userId'] ?? 0];

	$lastDownload = $con->getOne('SELECT UNIX_TIMESTAMP(lastDownload) FROM fileDownloadTracking WHERE (fileId, ipAddress, userId) = (?, ?, ?) ORDER BY lastDownload DESC LIMIT 1', $identifier);
	if (!$lastDownload || (time() - $lastDownload) > DOWNLOAD_DEDUPLICATION_TS_SECS) {
		$con->startTrans();

		$con->execute('INSERT INTO fileDownloadTracking (fileId, ipAddress, userId) VALUES (?, ?, ?)', $identifier);

		$con->execute('UPDATE files SET downloads = downloads + 1 WHERE fileId = ?', [$fileId]);
		if($file['modId']) {
			$con->execute('UPDATE mods SET downloads = downloads + 1 WHERE modId = ?', [$file['modId']]);
		}

		$con->completeTrans();
	}
}


// redirect to actual download
header('Location: '. formatCdnDownloadUrl($file), true, HTTP_FOUND);
