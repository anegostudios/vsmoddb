<?php
	
$sessiontoken = empty($_COOKIE['vs_websessionkey']) ? null : $_COOKIE['vs_websessionkey'];

$user = null;

if ($sessiontoken) {
	$user = $con->getRow("select user.*, role.code as rolecode from user left join role on (user.roleid = role.roleid) where sessiontoken=? and sessiontokenvaliduntil > now()", array($_COOKIE['vs_websessionkey']));
	$view->assign("user", $user);
}

if (DEBUGUSER == 1) {
	$user = $con->getRow("select user.*, role.code as rolecode from user left join role on (user.roleid = role.roleid)");
	$view->assign("user", $user);
}

