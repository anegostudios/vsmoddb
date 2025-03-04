<?php

function goBackOrRootFallback()
{
	forceRedirect(contains($_SERVER['HTTP_REFERER'] ?? '', 'notification') ? $_SERVER['HTTP_REFERER'] : '/');
}

if (empty($user)) {
	goBackOrRootFallback();
	exit();
}

if ($urlparts[1] == 'clearall') {
	$con->Execute("update notification set `read`=1 where userid=?", array($user['userid']));
	goBackOrRootFallback();
	exit();
}

$notification = $con->getRow("select * from notification where notificationid=?", array($urlparts[1]));

if (empty($notification)) {
	goBackOrRootFallback();
	exit();
}

switch($notification['type']) {
	case "newrelease":
		$con->execute("update notification set `read` = 1 where notificationid = ?", array($notification['notificationid']));

		$mod = $con->getRow("select assetid, urlalias from `mod` where modid = ?", array($notification['recordid']));

		forceRedirect([
			'path'     => formatModPath($mod),
			'fragment' => 'tab-files',
		]);
		exit();

	case "teaminvite": case "modownershiptransfer":
		$mod = $con->getRow("select assetid, urlalias from `mod` where modid = ?", array((intval($notification['recordid']) & ((1 << 30) - 1)))); // :InviteEditBit

		forceRedirect(['path' => formatModPath($mod)]);
		exit();

	case "newcomment": case "mentioncomment":
		$mod = $con->getRow("
			select `mod`.assetid, `mod`.urlalias
			from `mod`
			join comment on comment.assetid = `mod`.assetid
			where commentid = ?
		", array($notification['recordid']));

		$con->execute('update notification set `read` = 1 where notificationid = ?', [$notification['notificationid']]); // TODO @setting

		forceRedirect([
			'path'     => formatModPath($mod),
			'fragment' => 'cmt-'.$notification['recordid'],
		]);
		exit();
}
