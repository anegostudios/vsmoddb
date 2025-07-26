<?php
if (empty($user)) showErrorPage(HTTP_UNAUTHORIZED);

if (!empty($_POST['save'])) {
	validateActionToken();

	$newTimezone = array_keys($timezones)[intval($_POST['timezone'])];
	$e = $con->execute('UPDATE Users SET timezone = ? WHERE userId = ?', [$newTimezone, $user['userId']]);

	addMessage(MSG_CLASS_OK, 'New settings saved.');
	forceRedirectAfterPOST();
	exit();
}

$followedMods = $con->getAll('
	SELECT f.modId, a.name, f.flags, m.urlAlias, a.assetId
	FROM UserFollowedMods f
	JOIN Mods m ON m.modId = f.modId
	JOIN Assets a ON a.assetId = m.assetId
	WHERE f.userId = ?
', [$user['userId']]);


$view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);
$view->assign('followedMods', $followedMods);
$view->assign('timezones', array_keys($timezones));
$view->display('accountsettings.tpl');
