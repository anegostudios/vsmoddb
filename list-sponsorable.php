<?php

if ($user['rolecode'] != 'admin') exit("noprivilege");

const EXTEND_MATCHES_BY = 20;

$rawData = $con->getAll("
	SELECT a.createdbyuserid, u.created, u.name as username, m.assetid, m.urlalias, a.name, f.cdnpath, m.donateurl, a.text
	FROM `mod` m
	     JOIN  asset a ON a.assetid = m.assetid
	     JOIN  user  u ON u.userid = a.createdbyuserid
	LEFT JOIN `file` f ON f.fileid = m.embedlogofileid
	WHERE m.donateurl <> '' OR a.text LIKE '%co-fi%' OR a.text LIKE '%patreon%'
");

$dataByUser = [];
foreach($rawData as $row) {
	$ownerId = $row['createdbyuserid'];

	$matchesHTML = '';
	if($row['donateurl']) {
		$matchesHTML = '<span>Donate URL: <mark>'.htmlspecialchars($row['donateurl']).'</mark></span>';
	}

	$rawText = $row['text'] ?? '';
	preg_match_all('/(?:ko-fi)|(?:patreon)/', $rawText, $matches, PREG_OFFSET_CAPTURE);
	foreach($matches[0] as $match) {
		list($matchText, $matchOffset) = $match;

		$startIndex = max(0, $matchOffset - EXTEND_MATCHES_BY);
		$before = htmlspecialchars(substr($rawText, $startIndex, $matchOffset - $startIndex));
		$after = htmlspecialchars(substr($rawText, $matchOffset + strlen($matchText), EXTEND_MATCHES_BY));

		// @security: The $matchText cannot contain html because the match pattern is plain text and does not capture unknown characters.
		$matchesHTML .= "<span>In Description: {$before}<mark>{$matchText}</mark>{$after}</span>";
	}

	$modData = [
		'name'      => $row['name'],
		'path'      => formatModPath($row),
		'logourl'   => $row['cdnpath'] ? formatCdnUrl($row) : null,
		'matchhtml' => $matchesHTML,
	];

	if(array_key_exists($ownerId, $dataByUser)) {
		$dataByUser[$ownerId]['mods'][] = $modData;
		if($row['donateurl']) $dataByUser[$ownerId]['confirmedurls'][htmlspecialchars($row['donateurl'])] = 1;
	}
	else {
		$dataByUser[$ownerId] = [
			'userhash'      => getUserHash($ownerId, $row['created']),
			'username'      => $row['username'],
			'confirmedurls' => $row['donateurl'] ? [htmlspecialchars($row['donateurl']) => 1] : [],
			'mods'          => [$modData],
		];
	}
}


$view->assign('dataByUser', $dataByUser, null, true);
$view->display("list-sponsorable");
