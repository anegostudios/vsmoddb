<?php

if (!empty($user)) {
	$ownMods = $con->getAll("
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
		$mod['modpath'] = formatModPath($mod);

		$tagsCached = trim($mod["tagscached"]);
		if (!empty($tagsCached)) {
			$tagData = explode("\r\n", $tagsCached);
			$tags = [];

			foreach($tagData as $tagrow) {
				$parts = explode(",", $tagrow);
				$tags[] = ['name' => $parts[0], 'color' => $parts[1], 'tagId' => $parts[2]];
			}

			$mod['tags'] = $tags;
		}
	}
	unset($mod);

	$view->assign('mods', $ownMods);



	$followedMods = $con->getAll("
		SELECT
			a.*,
			m.*,
			logo.cdnpath AS logocdnpath,
			logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS legacylogo,
			u.name AS `from`,
			rd.created AS releasedate,
			rd.modversion AS releaseversion
		FROM
			asset a
			JOIN `mod` m ON m.assetid = a.assetid
			JOIN Users u ON u.userId = a.createdbyuserid
			JOIN UserFollowedMods f ON f.modId = m.modid AND f.userId = ?
			LEFT JOIN file AS logo ON logo.fileid = m.cardlogofileid
			LEFT JOIN (select * from `release`) rd ON rd.modid = m.modid
		WHERE
			a.statusid = ".STATUS_RELEASED."
			AND rd.created IS NULL OR rd.created = (select max(created) from `release` where `release`.modid = m.modid)
		ORDER BY
			releasedate DESC
	", [$user['userId']]);

	foreach($followedMods as &$mod) {
		$mod['statusCode'] = 'published';
		$mod['modpath'] = formatModPath($mod);
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
		logo.cdnpath AS logocdnpath,
		logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS legacylogo,
		u.name AS `from`
	FROM
		asset a
		join `mod` m ON m.assetid = a.assetid
		join Users u ON u.userId = a.createdbyuserid
		left join file AS logo ON logo.fileid = m.cardlogofileid
	WHERE
		a.statusid = ".STATUS_RELEASED."
		AND m.created > DATE_SUB(NOW(), INTERVAL 30 DAY)
	ORDER BY
		a.created DESC
	LIMIT 10
");

foreach($latestMods as &$mod) {
	$mod['statusCode'] = 'published';
	$mod['modpath'] = formatModPath($mod);
}
unset($mod);

$view->assign('latestMods', $latestMods);

$lastestComments = $con->getAll('
	SELECT
		c.assetId, a.name AS assetName,
		c.commentId, c.text, c.created,
		u.name AS username, IFNULL(u.banneduntil >= NOW(), 0) AS isBanned
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
