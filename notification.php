<?php

if (empty($user)) {
	header("Location: /");
	exit();
}

if ($urlparts[1] == 'clearall') {
	$con->Execute("update notification set `read`=1 where userid=?", array($user['userid']));
	header("Location: /");
	exit();
}

$not = $con->getRow("select * from notification where notificationid=?", array($urlparts[1]));

if (empty($not)) {
	header("Location: /");
	exit();
}

$con->Execute("update notification set `read`=1 where notificationid=? and userid=?", array($not['notificationid'], $user['userid']));

if ($not['type'] == "newrelease") { 
	
	$row = $con->getRow("
		select 
			`mod`.assetid,
			`mod`.urlalias as modalias
		from
			`mod`
		where modid=?
	", array($not['recordid']));

	$url = $row['modalias'] ? "/" . $row['modalias'] : "/show/mod/" . $row['assetid'];
	header("Location: {$url}#tab-files");
} else {

	$cmt = $con->getRow("
		select 
			commentid,
			`mod`.urlalias as modalias
		from
			comment 
			join `mod` on (comment.assetid = `mod`.assetid)
		where commentid=?
	", array($not['recordid']));

	$url = $cmt['modalias'] ? "/" . $cmt['modalias'] : "/show/mod/" . $cmt['assetid'];
	header("Location: {$url}#cmt-{$cmt['commentid']}");
}


