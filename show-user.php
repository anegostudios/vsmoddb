<?php

$userHash = $urlparts[2] ?? null;
$shownUser = null;

if (strlen($userHash) > 20) {
	showErrorPage(HTTP_BAD_REQUEST);
	exit();
}

if (empty($userHash) || empty($shownUser = getUserByHash($userHash, $con))) {
	showErrorPage(HTTP_NOT_FOUND);
	exit();
}

$sqlWhereExt = (isset($user) && $shownUser['userId'] == $user['userId']) || canModerate($shownUser, $user) ? '' : ' and a.statusId = 2'; // show drafts if owner or mod
$userMods = $con->getAll("
	SELECT
		a.*,
		m.*,
		logo.cdnPath AS logoCdnPath,
		logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
		s.code AS statusCode
	FROM
		Assets a
		JOIN `mod` m ON m.assetid = a.assetId
		LEFT JOIN Status s ON s.statusId = a.statusId
		LEFT JOIN Files AS logo ON logo.fileId = m.cardlogofileid
		LEFT JOIN ModTeamMembers t ON t.modId = m.modid
	WHERE
		(a.createdByUserId = ? OR t.userId = ?) $sqlWhereExt
	GROUP BY a.assetId
	ORDER BY a.created DESC
", array($shownUser['userId'], $shownUser['userId']));

foreach ($userMods as &$mod) {
	unset($mod['text']);
	$mod['tags'] = [];
	$mod['from'] = $shownUser['name'];
	$mod['dbPath'] = formatModPath($mod);
	$mod['tags'] = unwrapCachedTags($mod['tagsCached']);
}
unset($mod);

if (canModerate($shownUser, $user)) {
	$changelog = $con->getAll('SELECT text, assetId, created FROM Changelogs WHERE userId = ? ORDER BY created DESC LIMIT 100', [$shownUser['userId']]);
	$view->assign('changelog', $changelog);
}

if($shownUser['userId'] == $user['userId']) $view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);

$view->assign('mods', $userMods);
$view->assign('user', $user);
$view->assign('shownUser', $shownUser, null, true);
$view->display('show-user');
