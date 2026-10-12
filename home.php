<?php

if (!empty($user)) {
	$ownMods = $con->getAll("
		SELECT
			a.assetId, a.name, a.created, m.status, a.createdByUserId,
			m.modId, m.urlAlias, m.summary, m.downloads, m.comments,
			logo.cdnPath AS logoCdnPath,
			logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo
		FROM
			assets a
			JOIN mods m ON m.assetId = a.assetId
			LEFT JOIN files AS logo ON logo.fileId = m.cardLogoFileId
			LEFT JOIN modTeamMembers tm ON tm.modId = m.modId
		WHERE
			(a.createdByUserId = ? OR tm.userId = ?)
		GROUP BY a.assetId
		ORDER BY a.created DESC
	", [$user['userId'], $user['userId']]);

	foreach($ownMods as &$mod) {
		$mod['tags'] = [];
		$mod['from'] = $user['name'];
		$mod['dbPath'] = formatModPath($mod);
	}
	unset($mod);

	$view->assign('mods', $ownMods);


	//TODO(Rennorb) @cleanup
	$followedMods = $con->getAll("
		SELECT
			a.assetId, a.name, a.created, m.status, a.createdByUserId,
			m.modId, m.urlAlias, m.summary, m.downloads, m.comments,
			logo.cdnPath AS logoCdnPath,
			logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
			u.name AS `from`,
			rd.created AS releaseDate,
			rd.version AS releaseVersion
		FROM
			assets a
			JOIN mods m ON m.assetId = a.assetId AND m.status = ".MOD_STATUS_RELEASED."
			JOIN users u ON u.userId = a.createdByUserId
			JOIN userFollowedMods f ON f.modId = m.modId AND f.userId = ?
			LEFT JOIN files AS logo ON logo.fileId = m.cardLogoFileId
			LEFT JOIN modReleases rd ON rd.releaseId = (SELECT releaseId FROM modReleases WHERE modId = m.modId AND rd.status = ".RELEASE_STATUS_RELEASED." ORDER BY created DESC LIMIT 1)
		ORDER BY
			releaseDate DESC
	", [$user['userId']]);

	foreach($followedMods as &$mod) {
		$mod['dbPath'] = formatModPath($mod);
	}
	unset($mod);

	$view->assign('followedmods', $followedMods);
} else {
	$view->assign('followedmods', []);
}


$latestMods = $con->getAll("
	SELECT
		a.assetId, a.name, a.created, m.status, a.createdByUserId,
		m.modId, m.urlAlias, m.summary, m.downloads, m.comments, m.category,
		logo.cdnPath AS logoCdnPath,
		logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
		u.name AS `from`
	FROM
		assets a
		join mods m ON m.assetId = a.assetId AND m.status = ".MOD_STATUS_RELEASED." AND m.category != ".CATEGORY_SERVER_TWEAK." AND m.created > DATE_SUB(NOW(), INTERVAL 30 DAY)
		join users u ON u.userId = a.createdByUserId
		left join files AS logo ON logo.fileId = m.cardLogoFileId
	ORDER BY
		a.created DESC
	LIMIT 10
");

foreach($latestMods as &$mod) {
	$mod['dbPath'] = formatModPath($mod);
}
unset($mod);

$view->assign('latestMods', $latestMods);

$latestComments = $con->getAll('
	SELECT
		c.assetId, a.name AS assetName,
		c.commentId, c.text, c.created,
		u.name AS username, IFNULL(u.bannedUntil >= NOW(), 0) AS isBanned
	FROM
		comments c
		join users u ON u.userId = c.userId
		join mods m ON m.assetId = c.assetId AND m.status = '.MOD_STATUS_RELEASED.'
		join assets a ON a.assetId = c.assetId
	WHERE
		!c.deleted
		AND c.created > DATE_SUB(NOW(), INTERVAL 14 DAY)
	ORDER BY
		c.created DESC
	LIMIT 20
');

$view->assign('latestComments', $latestComments, null, true);

$view->assign('headerHighlight', HEADER_HIGHLIGHT_HOME, null, true);
$view->display("home.tpl");
