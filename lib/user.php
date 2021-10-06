<?php
	
$sessiontoken = empty($_COOKIE['vs_websessionkey']) ? null : $_COOKIE['vs_websessionkey'];

$user = null;
$cnt = 0;

if ($sessiontoken) {
	$user = $con->getRow("select user.*, role.code as rolecode from user left join role on (user.roleid = role.roleid) where sessiontoken=? and sessiontokenvaliduntil > now()", array($_COOKIE['vs_websessionkey']));
}

if (DEBUGUSER == 1) {
	$user = $con->getRow("select user.*, role.code as rolecode from user left join role on (user.roleid = role.roleid)");
}

if ($user) {
	loadNotifications();

	$view->assign("user", $user);
} else {
	$view->assign("notificationcount", 0);
}



function loadNotifications() {
	global $con, $view, $user;
	
	$view->assign("notificationcount", $con->getOne("select count(*) from notification where userid=? and `read`=0", array($user['userid'])));
	
	$notifications = $con->getAll("select * from notification where userid=? and `read`=0 order by created desc limit 10", array($user['userid']));
	foreach ($notifications as &$notification) {
		if ($notification['type']=="newcomment") {
			$cmt = $con->getRow("
				select 
					asset.assetid,
					asset.name as modname,
					user.name as username,
					`mod`.urlalias as modalias
				from
					comment 
					join asset on (comment.assetid = asset.assetid)
					join `mod` on (comment.assetid = `mod`.assetid)
					join user on (comment.userid = user.userid)
				where commentid=?
			", $notification['recordid']);
			
			$notification['text'] = "{$cmt['username']} commented on {$cmt['modname']}";
			$notification['link'] = "/notification/{$notification['notificationid']}"; // $cmt['modalias'] ? "/" . $cmt['modalias'] : "show/mod/" . $cmt['assetid'];
		}
	}
	$view->assign("notifications", $notifications);
}