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
			asset a
			JOIN `mod` m ON m.assetid = a.assetid
			LEFT JOIN Status s ON s.statusId = a.statusid
			LEFT JOIN Files AS logo ON logo.fileId = m.cardlogofileid
			LEFT JOIN ModTeamMembers tm ON tm.modId = m.modid
		WHERE
			(a.createdbyuserid = ? OR tm.userId = ?)
		GROUP BY a.assetid
		ORDER BY a.created DESC
	", [$user['userId'], $user['userId']]);

	foreach($ownMods as &$mod) {
		unset($mod['text']);
		$mod['tags'] = [];
		$mod['from'] = $user['name'];
		$mod['dbPath'] = formatModPath($mod);

		$tagsCached = trim($mod['tagscached']);
		if (!empty($tagsCached)) {
			$tagData = explode("\r\n", $tagsCached);
			$tags = [];

			foreach($tagData as $tagrow) {
				$parts = explode(',', $tagrow);
				$tags[] = ['name' => $parts[0], 'color' => $parts[1], 'tagId' => $parts[2]];
			}

			$mod['tags'] = $tags;
		}
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
			rd.created AS releasedate,
			rd.version AS releaseversion
		FROM
			asset a
			JOIN `mod` m ON m.assetid = a.assetid
			JOIN Users u ON u.userId = a.createdbyuserid
			JOIN UserFollowedMods f ON f.modId = m.modid AND f.userId = ?
			LEFT JOIN Files AS logo ON logo.fileId = m.cardlogofileid
			LEFT JOIN ModReleases rd ON rd.modId = m.modid
		WHERE
			a.statusid = ".STATUS_RELEASED."
			AND rd.created = (select max(created) from ModReleases r where r.modId = m.modid)
		ORDER BY
			releasedate DESC
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
		asset a
		join `mod` m ON m.assetid = a.assetid
		join Users u ON u.userId = a.createdbyuserid
		left join Files AS logo ON logo.fileId = m.cardlogofileid
	WHERE
		a.statusid = ".STATUS_RELEASED."
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
		Comments c
		join Users u ON u.userId = c.userId
		join asset a ON a.assetid = c.assetId
	WHERE
		a.statusid = '.STATUS_RELEASED.'
		AND !c.deleted
		AND c.created > DATE_SUB(NOW(), INTERVAL 14 DAY)
	ORDER BY
		c.created DESC
	LIMIT 20
');

$view->assign('lastestComments', $lastestComments, null, true);

$view->assign('headerHighlight', HEADER_HIGHLIGHT_HOME, null, true);
$view->display("home.tpl");
