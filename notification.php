<?php

if (empty($user)) {
	header("Location: /");
	exit();
}


$not = $con->getRow("select * from notification where notificationid=?", array($urlparts[1]));

if (empty($not)) {
	header("Location: /");
	exit();
}

$cmt = $con->getRow("
	select 
		commentid,
		`mod`.urlalias as modalias
	from
		comment 
		join `mod` on (comment.assetid = `mod`.assetid)
	where commentid=?
", array($not['recordid']));

$con->Execute("update notification set `read`=1 where notificationid=?", array($not['notificationid']));

$url = $cmt['modalias'] ? "/" . $cmt['modalias'] : "show/mod/" . $cmt['assetid'];



header("Location: {$url}#cmt-{$cmt['commentid']}");