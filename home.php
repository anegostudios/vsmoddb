<?php

if (!empty($user)) {
	$ownMods = $con->getAll("
		SELECT
			a.*,
			m.*,
			logo.cdnPath AS logoCdnPath,
			logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
			s.code AS statusCode
		FROM
			assets a
			JOIN mods m ON m.assetId = a.assetId
			LEFT JOIN status s ON s.statusId = a.statusId
			LEFT JOIN files AS logo ON logo.fileId = m.cardLogoFileId
			LEFT JOIN modTeamMembers tm ON tm.modId = m.modId
		WHERE
			(a.createdByUserId = ? OR tm.userId = ?)
		GROUP BY a.assetId
		ORDER BY a.created DESC
	", [$user['userId'], $user['userId']]);

	foreach($ownMods as &$mod) {
		unset($mod['text']);
		$mod['tags'] = [];
		$mod['from'] = $user['name'];
		$mod['dbPath'] = formatModPath($mod);
		$mod['tags'] = unwrapCachedTags($mod['tagsCached']);
	}
	unset($mod);

	$view->assign('mods', $ownMods);


	//TODO(Rennorb) @cleanup
	$followedMods = $con->getAll("
		SELECT
			a.*,
			m.*,
			logo.cdnPath AS logoCdnPath,
			logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
			u.name AS `from`,
			rd.created AS releaseDate,
			rd.version AS releaseVersion
		FROM
			assets a
			JOIN mods m ON m.assetId = a.assetId
			JOIN users u ON u.userId = a.createdByUserId
			JOIN userFollowedMods f ON f.modId = m.modId AND f.userId = ?
			LEFT JOIN files AS logo ON logo.fileId = m.cardLogoFileId
			LEFT JOIN modReleases rd ON rd.modId = m.modId
		WHERE
			a.statusId = ".STATUS_RELEASED."
			AND rd.created = (select max(created) from modReleases r where r.modId = m.modId)
		ORDER BY
			releaseDate DESC
	", [$user['userId']]);

	foreach($followedMods as &$mod) {
		$mod['statusCode'] = 'published';
		$mod['dbPath'] = formatModPath($mod);
	}
	unset($mod);

	$view->assign('followedmods', $followedMods);
} else {
	$view->assign('followedmods', []);
}


$latestMods = $con->getAll("
	SELECT
		a.*,
		m.*,
		logo.cdnPath AS logoCdnPath,
		logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
		u.name AS `from`
	FROM
		assets a
		join mods m ON m.assetId = a.assetId
		join users u ON u.userId = a.createdByUserId
		left join files AS logo ON logo.fileId = m.cardLogoFileId
	WHERE
		a.statusId = ".STATUS_RELEASED."
		AND m.created > DATE_SUB(NOW(), INTERVAL 30 DAY)
	ORDER BY
		a.created DESC
	LIMIT 10
");

foreach($latestMods as &$mod) {
	$mod['statusCode'] = 'published';
	$mod['dbPath'] = formatModPath($mod);
}
unset($mod);

$view->assign('latestMods', $latestMods);

$lastestComments = $con->getAll('
	SELECT
		c.assetId, a.name AS assetName,
		c.commentId, c.text, c.created,
		u.name AS username, IFNULL(u.bannedUntil >= NOW(), 0) AS isBanned
	FROM
		comments c
		join users u ON u.userId = c.userId
		join assets a ON a.assetId = c.assetId
	WHERE
		a.statusId = '.STATUS_RELEASED.'
		AND !c.deleted
		AND c.created > DATE_SUB(NOW(), INTERVAL 14 DAY)
	ORDER BY
		c.created DESC
	LIMIT 20
');

$view->assign('lastestComments', $lastestComments, null, true);

$view->assign('headerHighlight', HEADER_HIGHLIGHT_HOME, null, true);
$view->display("home.tpl");
