<?php
	
$sessiontoken = empty($_COOKIE['vs_websessionkey']) ? null : $_COOKIE['vs_websessionkey'];

$user = null;
$cnt = 0;

// check `DEBUGUSER` first, $sessiontoken could be set by mods.vintagestory.at even if we're browsing stage.mods.vintagestory.at
if (DEBUGUSER === 1) {
	$user = $con->getRow("select user.*, role.code as rolecode from user left join role on (user.roleid = role.roleid)");
}

if ($sessiontoken) {
	$user = $con->getRow("select user.*, role.code as rolecode from user left join role on (user.roleid = role.roleid) where sessiontoken=? and sessiontokenvaliduntil > now()", array($_COOKIE['vs_websessionkey']));
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
		if ($notification['type']=="newrelease") {
			$cmt = $con->getRow("
				select 
					`asset`.name as modname,
					user.name as username
				from
					`mod`
					join asset on (`mod`.assetid = asset.assetid)
					join user on (asset.createdbyuserid = user.userid)
				where modid=?
			", $notification['recordid']);
			
			$notification['text'] = "{$cmt['username']} uploaded a new version of {$cmt['modname']}";
			
		} else {
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

			if ($notification['type']=="newcomment") {
				$notification['text'] = "{$cmt['username']} commented on {$cmt['modname']}";
			}
			if ($notification['type']=="mentioncomment") {
				$notification['text'] = "{$cmt['username']} mentioned you in a comment on {$cmt['modname']}";
			}
		}
		
		$notification['link'] = "/notification/{$notification['notificationid']}";
	}

	if (count($notifications)) {
		$notifications[] = array('type' => 'clearall', 'text' => 'Clear all notifications', 'recorid'=>'clear', 'link' => '/notification/clearall');
	}
	
	$view->assign("notifications", $notifications);
}
