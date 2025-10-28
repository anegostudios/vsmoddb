<?php

// expects to be called as  download/132465[/somefile.png]
// but the game client downloads it using ?fileid=1231 so we need to remain backwards compatible

$fileId = intval($_GET['fileid'] ?? $urlparts[1] ?? 0);
if($fileId === 0) showErrorPage(HTTP_BAD_REQUEST, 'Missing fileid.');

$file = $con->getRow('SELECT * FROM files WHERE fileId = ?', [$fileId]);
if (!$file) showErrorPage(HTTP_NOT_FOUND, 'File not found.');

if(!DB_READONLY) {
	// do download tracking
	$identifier = [$fileId, $_SERVER['REMOTE_ADDR']];

	$lastDownload = $con->getOne('SELECT lastDownload FROM fileDownloadTracking WHERE fileId = ? AND ipAddress = ?', $identifier);

	$countAsSeparateDownload = false;
	if (!$lastDownload) {
		$countAsSeparateDownload = true;
		$con->execute('INSERT INTO fileDownloadTracking (fileId, ipAddress) VALUES (?, ?)', $identifier);
	} else if (strtotime($lastDownload) - time() > 24*3600) {
		$countAsSeparateDownload = true;
		//TODO(Rennorb) @correctness: This does not produce the correct result for trending points.
		$con->execute('UPDATE fileDownloadTracking SET lastDownload = NOW() WHERE fileId = ? and ipAddress = ?', $identifier);
	}

	if ($countAsSeparateDownload) {
		$con->execute('UPDATE files SET downloads = downloads + 1 WHERE fileId = ?', [$fileId]);
		$con->execute('UPDATE mods  SET downloads = downloads + 1 WHERE modId = (SELECT r.modId FROM modReleases r WHERE r.assetId = ?)', [$file['assetId']]);
	}
}


// redirect to actual download
header('Location: '. formatCdnDownloadUrl($file), true, HTTP_FOUND);
