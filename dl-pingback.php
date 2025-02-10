<?php

// expects to be called as  dl-pingback/some-file-id.png

$fileid = intval($urlparts[1]);

$file = $con->getRow("select * from file where fileid=?", array($fileid));
if (!$file) exit("file not found");

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

