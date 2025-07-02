<?php

// expects to be called as  download/132465[/somefile.png]
// but the game client downloads it using ?fileid=1231 so we need to remain backwards compatible

$fileId = intval($_GET['fileid'] ?? $urlparts[1] ?? 0);
if($fileId === 0) showErrorPage(HTTP_BAD_REQUEST, 'Missing fileid.');

$file = $con->getRow('SELECT * FROM `file` WHERE fileid = ?', [$fileId]);
if (!$file) showErrorPage(HTTP_NOT_FOUND, 'File not found.');

// do download tracking
$identifier = [$fileId, $_SERVER['REMOTE_ADDR']];

$lastDownload = $con->getOne('SELECT lastDownload FROM FileDownloadTracking WHERE fileId = ? AND ipAddress = ?', $identifier);

$countAsSeparateDownload = false;
if (!$lastDownload) {
	$countAsSeparateDownload = true;
	$con->execute('INSERT INTO FileDownloadTracking (fileId, ipAddress) VALUES (?, ?)', $identifier);
} else if (strtotime($lastDownload) - time() > 24*3600) {
	$countAsSeparateDownload = true;
	//TODO(Rennorb) @correctness: This does not produce the correct result for trending points.
	$con->execute('UPDATE FileDownloadTracking SET lastDownload = NOW() WHERE fileId = ? and ipAddress = ?', $identifier);
}

if ($countAsSeparateDownload) {
	$con->execute('UPDATE `file` SET downloads = downloads + 1 WHERE fileId = ?', [$fileId]);
	$con->execute('UPDATE `mod`  SET downloads = downloads + 1 WHERE modid = (SELECT r.modid FROM `release` r WHERE r.assetid = ?)', [$file['assetid']]);
}


// redirect to actual download
header('Location: '. formatCdnDownloadUrl($file), true, HTTP_FOUND);
