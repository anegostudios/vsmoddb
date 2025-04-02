<?php
if (empty($user)) showErrorPage(HTTP_UNAUTHORIZED);

$view->assign("user", $user);

if (!empty($_POST["save"])) {	
	$data = array(
		//"name" => strip_tags($_POST["name"]),
		//"email" =>strip_tags($_POST["email"]),
		"timezone" => array_keys($timezones)[intval($_POST["timezone"])],
	);
	
	update("user", $user["userid"], $data);
	addMessage(MSG_CLASS_OK, 'New settings saved.');
	
	$user = array_merge($user, $data);
}


$view->assign("timezones", array_keys($timezones));

$view->assign("user", $user);
$view->display("accountsettings.tpl");