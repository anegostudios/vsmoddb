<?php

if (!empty($user)) {
	$ownMods = $con->getAll("
		SELECT
			asset.*,
			`mod`.*,
			logofile.cdnpath AS logocdnpath,
			logofile.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS legacylogo,
			status.code AS statuscode
		FROM
			asset
			JOIN `mod` ON asset.assetid = `mod`.assetid
			LEFT JOIN status ON asset.statusid = status.statusid
			LEFT JOIN file AS logofile ON `mod`.cardlogofileid = logofile.fileid
			LEFT JOIN ModTeamMembers ON `mod`.modid = ModTeamMembers.modId
		WHERE
			(asset.createdbyuserid = ? OR ModTeamMembers.userId = ?)
		GROUP BY asset.assetid
		ORDER BY asset.created DESC
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
			asset.*,
			`mod`.*,
			logofile.cdnpath AS logocdnpath,
			logofile.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS legacylogo,
			u.name AS `from`,
			rd.created AS releasedate,
			rd.modversion AS releaseversion
		FROM
			asset
			JOIN `mod` ON `mod`.assetid = asset.assetid
			JOIN Users u ON u.userId = asset.createdbyuserid
			JOIN UserFollowedMods f ON f.modId = `mod`.modid AND f.userId = ?
			LEFT JOIN file AS logofile ON logofile.fileid = `mod`.cardlogofileid
			LEFT JOIN (select * from `release`) rd ON (rd.modid = `mod`.modid)
		WHERE
			asset.statusid = 2
			AND rd.created IS NULL OR rd.created = (select max(created) from `release` where `release`.modid = mod.modid)
		ORDER BY
			releasedate DESC
	", [$user['userId']]);

	foreach($followedMods as &$mod) {
		$mod['statuscode'] = 'published';
		$mod['modpath'] = formatModPath($mod);
	}
	unset($mod);

	$view->assign('followedmods', $followedMods);
} else {
	$view->assign('followedmods', []);
}


$latestMods = $con->getAll("
	SELECT
		asset.*,
		`mod`.*,
		logofile.cdnpath AS logocdnpath,
		logofile.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS legacylogo,
		u.name AS `from`
	FROM
		asset
		join `mod` ON asset.assetid = `mod`.assetid
		join Users u ON u.userId = asset.createdbyuserid
		left join file AS logofile ON mod.cardlogofileid = logofile.fileid
	WHERE
		asset.statusid = 2
		AND `mod`.created > DATE_SUB(NOW(), INTERVAL 30 DAY)
	ORDER BY
		asset.created DESC
	LIMIT 10
");

foreach($latestMods as &$mod) {
	$mod['statuscode'] = 'published';
	$mod['modpath'] = formatModPath($mod);
}
unset($mod);

$view->assign('latestMods', $latestMods);

$lastestComments = $con->getAll(<<<SQL
	SELECT
		c.assetId, a.name AS assetName,
		c.commentId, c.text, c.created,
		u.name AS username, IFNULL(u.banneduntil >= NOW(), 0) AS isBanned
	FROM
		Comments c
		join Users u ON u.userId = c.userId
		join asset a ON a.assetid = c.assetId
	WHERE
		a.statusid = 2
		AND !c.deleted
		AND c.created > DATE_SUB(NOW(), INTERVAL 14 DAY)
	ORDER BY
		c.created DESC
	LIMIT 20
SQL);

$view->assign('lastestComments', $lastestComments, null, true);

$view->assign('headerHighlight', HEADER_HIGHLIGHT_HOME, null, true);
$view->display("home.tpl");
