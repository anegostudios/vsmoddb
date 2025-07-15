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

$sqlWhereExt = (isset($user) && $shownUser['userId'] == $user['userId']) || canModerate($shownUser, $user) ? '' : ' and asset.statusid = 2'; // show drafts if owner or mod
$userMods = $con->getAll("
	SELECT
		a.*,
		m.*,
		logo.cdnpath AS logocdnpath,
		logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS legacylogo,
		s.code AS statusCode
	FROM
		asset a
		JOIN `mod` m ON m.assetid = a.assetid
		LEFT JOIN Status s ON s.statusId = a.statusid
		LEFT JOIN file AS logo ON logo.fileid = m.cardlogofileid
		LEFT JOIN ModTeamMembers t ON t.modId = m.modid
	WHERE
		(a.createdbyuserid = ? OR t.userId = ?) $sqlWhereExt
	GROUP BY a.assetid
	ORDER BY a.created DESC
", array($shownUser['userId'], $shownUser['userId']));

foreach ($userMods as &$row) {
	unset($row['text']);
	$row['tags'] = [];
	$row['from'] = $shownUser['name'];
	$row['modpath'] = formatModPath($row);

	$tagsCached = trim($row['tagscached']);
	if (empty($tagsCached)) continue;

	$tagData = explode("\r\n", $tagsCached);
	$tags = array();

	foreach ($tagData as $tagRow) {
		$parts = explode(',', $tagRow);
		$tags[] = array('name' => $parts[0], 'color' => $parts[1], 'tagId' => $parts[2]);
	}

	$row['tags'] = $tags;
}
unset($row);

if (canModerate($shownUser, $user)) {
	$changelog = $con->getAll('SELECT text, assetId, created FROM Changelogs WHERE userId = ? ORDER BY created DESC LIMIT 100', [$shownUser['userId']]);
	$view->assign('changelog', $changelog);
}

if($shownUser['userId'] == $user['userId']) $view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);

$view->assign('mods', $userMods);
$view->assign('user', $user);
$view->assign('shownUser', $shownUser, null, true);
$view->display('show-user');
