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
	SELECT f.modId, asset.name, f.flags, `mod`.urlalias, asset.assetid
	FROM UserFollowedMods f
	JOIN `mod` ON `mod`.modid = f.modId
	JOIN asset ON asset.assetid = `mod`.assetid
	WHERE f.userId = ?
', [$user['userid']]);


$view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);
$view->assign('followedMods', $followedMods);
$view->assign('timezones', array_keys($timezones));
$view->display('accountsettings.tpl');
