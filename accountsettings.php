<?php
if (empty($user)) showErrorPage(HTTP_UNAUTHORIZED);

if (!empty($_POST["save"])) {	
	$data = array(
		"timezone" => array_keys($timezones)[intval($_POST["timezone"])],
	);
	
	update("user", $user["userid"], $data);
	addMessage(MSG_CLASS_OK, 'New settings saved.');

	$user = array_merge($user, $data);
	$view->assign("user", $user);
}

$followedMods = $con->getAll('
	select follow.modid, asset.name, follow.flags, `mod`.urlalias, asset.assetid
	from follow
	join `mod` on `mod`.modid = follow.modid
	join asset on asset.assetid = `mod`.assetid
	where follow.userid = ?
', [$user['userid']]);


$view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);
$view->assign("followedMods", $followedMods);
$view->assign("timezones", array_keys($timezones));
$view->display("accountsettings.tpl");
