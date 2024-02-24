<?php
$usertoken = $urlparts[2] ?? null;
$shownuser = null;

if (empty($usertoken) || empty($shownuser = getUserByHash($usertoken, $con))) {
	$view->display("404");
	exit();
}

if (!canEditProfile($shownuser, $user))  {
	$view->display("403");
	exit();
}

if (!empty($_POST["save"])) {	
	$data = array(
		"bio" => sanitizeHtml($_POST["bio"]),
	);
	
	update("user", $shownuser["userid"], $data);
	$view->assign("okmessage", "New profile information saved.");
	
	$shownuser = array_merge($shownuser, $data);
}

$view->assign("usertoken", $usertoken);
$view->assign("bio", $shownuser['bio']);
$view->display("edit-profile.tpl");