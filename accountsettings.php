<?php
if (empty($user)) showErrorPage(HTTP_UNAUTHORIZED);

if (!empty($_POST['save'])) {
	validateActionToken();

	$newTimezone = array_keys($timezones)[intval($_POST['timezone'])];
	$e = $con->execute('UPDATE `user` SET `timezone` = ? WHERE userid = ?', [$newTimezone, $user['userid']]);

	addMessage(MSG_CLASS_OK, 'New settings saved.');
	forceRedirectAfterPOST();
	exit();
}

$followedMods = $con->getAll('
	SELECT follow.modid, asset.name, follow.flags, `mod`.urlalias, asset.assetid
	FROM follow
	JOIN `mod` ON `mod`.modid = follow.modid
	JOIN asset ON asset.assetid = `mod`.assetid
	WHERE follow.userid = ?
', [$user['userid']]);


$view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);
$view->assign('followedMods', $followedMods);
$view->assign('timezones', array_keys($timezones));
$view->display('accountsettings.tpl');
