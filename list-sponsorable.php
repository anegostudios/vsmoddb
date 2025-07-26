<?php

if ($user['roleCode'] != 'admin')  showErrorPage(HTTP_FORBIDDEN);

const EXTEND_MATCHES_BY = 20;

$rawData = $con->getAll(<<<SQL
	SELECT a.createdByUserId, HEX(u.hash) AS `hash`, u.name as username, m.assetId, m.urlAlias, a.name, f.cdnPath, m.donateUrl, a.text
	FROM Mods m
	     JOIN Assets a ON a.assetId = m.assetId
	     JOIN Users  u ON u.userId = a.createdByUserId
	LEFT JOIN Files  f ON f.fileId = m.embedLogoFileId
	WHERE m.donateUrl <> '' OR a.text LIKE '%co-fi%' OR a.text LIKE '%patreon%'
SQL);

$dataByUser = [];
foreach($rawData as $row) {
	$ownerId = $row['createdByUserId'];

	$matchesHTML = '';
	if($row['donateUrl']) {
		$matchesHTML = '<span>Donate URL: <mark>'.htmlspecialchars($row['donateUrl']).'</mark></span>';
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
		'logoUrl'   => $row['cdnPath'] ? formatCdnUrl($row) : null,
		'matchHtml' => $matchesHTML,
	];

	if(array_key_exists($ownerId, $dataByUser)) {
		$dataByUser[$ownerId]['mods'][] = $modData;
		if($row['donateUrl']) $dataByUser[$ownerId]['confirmedUrls'][htmlspecialchars($row['donateUrl'])] = 1;
	}
	else {
		$dataByUser[$ownerId] = [
			'userHash'      => $row['hash'],
			'username'      => $row['username'],
			'confirmedUrls' => $row['donateUrl'] ? [htmlspecialchars($row['donateUrl']) => 1] : [],
			'mods'          => [$modData],
		];
	}
}


$view->assign('dataByUser', $dataByUser, null, true);
$view->assign('headerHighlight', HEADER_HIGHLIGHT_ADMIN_TOOLS, null, true);
$view->display("list-sponsorable");
