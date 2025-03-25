<?php

// expects to be called as  download/132465[/somefile.png]
// but the game client downloads it using ?fileid=1231 so we need to remain backwards compatible

$fileid = intval($_GET["fileid"] ?? $urlparts[1] ?? 0);
if($fileid === 0) showErrorPage(HTTP_BAD_REQUEST, 'Missing fileid.');

$file = $con->getRow("select * from file where fileid=?", array($fileid));
if (!$file) showErrorPage(HTTP_NOT_FOUND, 'File not found.');

// do download tracking
$date = $con->getOne("select date from downloadip where fileid=? and ipaddress=?", array($fileid, $_SERVER['REMOTE_ADDR']));
$docount = false;
if (!$date) {
	$docount = true;
	$con->Execute("insert into downloadip values (?, ?, now())", array($_SERVER['REMOTE_ADDR'], $fileid));
} else if (strtotime($date) + 24*3600 < time()) {
	$docount = true;
	$con->Execute("update downloadip set date=now() where fileid=? and ipaddress=?", array($fileid, $_SERVER['REMOTE_ADDR']));
}

if ($docount) {
	$con->Execute("update file set downloads=downloads+1 where fileid=?", array($fileid));
	$con->Execute("update `mod` set downloads=downloads+1 where modid=(select `release`.modid from `release` where `release`.assetid=?)", array($file['assetid']));
}


// redirect to actual download
http_response_code(302);
header('Location: '. formatCdnDownloadUrl($file));
