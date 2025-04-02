<?php
$usertoken = $urlparts[2] ?? null;
if(empty($usertoken)) showErrorPage(HTTP_BAD_REQUEST, 'Missing usertoken.');

$shownuser = getUserByHash($usertoken, $con);
if (empty($shownuser)) showErrorPage(HTTP_NOT_FOUND, 'User not found.');

if (!canEditProfile($shownuser, $user)) showErrorPage(HTTP_FORBIDDEN);

if (!empty($_POST["save"])) {	
	$data = array(
		"bio" => sanitizeHtml($_POST["bio"]),
	);
	
	update("user", $shownuser["userid"], $data);
	addMessage(MSG_CLASS_OK, 'New profile information saved.');
	
	$shownuser = array_merge($shownuser, $data);
}

$view->assign("usertoken", $usertoken);
$view->assign("bio", $shownuser['bio']);
$view->display("edit-profile.tpl");