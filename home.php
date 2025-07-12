<?php

if (!empty($user)) {
	$ownmods = $con->getAll("
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
	", array($user['userid'], $user['userid']));

	foreach($ownmods as &$row) {
		unset($row['text']);
		$row["tags"] = array();
		$row['from'] = $user['name'];
		$row['modpath'] = formatModPath($row);

		$tagscached = trim($row["tagscached"]);
		if (!empty($tagscached)) {
			$tagdata = explode("\r\n", $tagscached);
			$tags=array();

			foreach($tagdata as $tagrow) {
				$parts = explode(",", $tagrow);
				$tags[] = array('name' => $parts[0], 'color' => $parts[1], 'tagId' => $parts[2]);
			}

			$row['tags'] = $tags;
		}
	}
	unset($row);

	$view->assign("mods", $ownmods);
}

if (!empty($user)) {

	$followedmods = $con->getAll("
		SELECT
			asset.*,
			`mod`.*,
			logofile.cdnpath AS logocdnpath,
			logofile.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS legacylogo,
			user.name AS `from`,
			status.code AS statuscode,
			status.name AS statusname,
			rd.created AS releasedate,
			rd.modversion AS releaseversion
		FROM
			asset
			JOIN `mod` ON asset.assetid = `mod`.assetid
			JOIN user ON (asset.createdbyuserid = user.userid)
			JOIN status ON (asset.statusid = status.statusid)
			JOIN UserFollowedMods f ON (`mod`.modid = f.modId AND f.userId = ?)
			LEFT JOIN file AS logofile ON `mod`.cardlogofileid = logofile.fileid
			LEFT JOIN (select * from `release`) rd ON (rd.modid = `mod`.modid)
		WHERE
			asset.statusid=2
			AND rd.created IS NULL OR rd.created = (select max(created) from `release` where `release`.modid = mod.modid)
		ORDER BY
			releasedate DESC
	", array($user['userid']));

	foreach($followedmods as &$row) {
		$row['modpath'] = formatModPath($row);
	}
	unset($row);

	$view->assign("followedmods", $followedmods);
} else {
	$view->assign("followedmods", array());
}


$latestMods = $con->getAll(<<<SQL
	SELECT
		asset.*,
		`mod`.*,
		logofile.cdnpath AS logocdnpath,
		logofile.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS legacylogo,
		user.name AS `from`,
		status.code AS statuscode,
		status.name AS statusname
	FROM
		asset
		join `mod` ON asset.assetid = `mod`.assetid
		join user ON (asset.createdbyuserid = user.userid)
		join status ON (asset.statusid = status.statusid)
		left join file AS logofile ON mod.cardlogofileid = logofile.fileid
	WHERE
		asset.statusid = 2
		AND `mod`.created > DATE_SUB(NOW(), INTERVAL 30 DAY)
	ORDER BY
		asset.created DESC
	LIMIT 10
SQL);

foreach($latestMods as &$row) {
	$row['modpath'] = formatModPath($row);
}
unset($row);

$view->assign('latestMods', $latestMods);

$lastestComments = $con->getAll(<<<SQL
	SELECT
		c.assetId, a.name AS assetName,
		c.commentId, c.text, c.created,
		u.name AS username, IFNULL(u.banneduntil >= NOW(), 0) AS isBanned
	FROM
		Comments c
		join user u ON u.userid = c.userId
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
