<?php

include($config["basepath"] . "lib/search-mods.php");

if(isset($_GET['paging'])) {
	if($paramError = validateModSearchInputs($searchParams)) {
		http_response_code(HTTP_BAD_REQUEST);
		exit(htmlspecialchars($paramError));
	}
	if(!$searchParams['limit']) $searchParams['limit'] = MOD_SEARCH_PAGE_SIZE;

	$mods = queryModSearchForModCards($searchParams);

	header('X-Fetch-Cursor: '.getNextFetchCursor($searchParams, $mods));

	$view->assign('mods', $mods);
	$view->display('mod-card-page');
	exit();
}



if($paramError = validateModSearchInputs($searchParams)) {
	addMessage(MSG_CLASS_ERROR, $paramError, true);
}
$searchParams['limit'] = MOD_SEARCH_INITIAL_RESULTS;
$mods = queryModSearchForModCards($searchParams);

$filters = &$searchParams['filters'];
$selectedParams = [
	'order'   => $searchParams['order'],
	'side'    => $filters['side'] ?? '',
	'text'    => htmlSpecialChars($filters['text'] ?? ''),
	'creator' => !empty($filters['a.createdbyuserid'])
		? [$filters['a.createdbyuserid'], $con->getOne('SELECT `name` FROM users WHERE userId = ?', [$filters['a.createdbyuserid']])]
		: [0, ''],
	'majorversion' => $filters['majorversion'] ?? '',
	'gameversions' => !empty($filters['gameversions']) ? array_flip($filters['gameversions']) : [],
	'tags'  => !empty($filters['tags']) ? array_flip($filters['tags']) : [],
];
unset($filters);

$strippedQuery = stripQueryParams(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), ['sortby', 'sortdir']);

$fetchCursorJS = getNextFetchCursor($searchParams, $mods);

$gameVersions = $con->getAll('SELECT `version` FROM gameVersions ORDER BY `version` desc');
$majorGameVersions = [];
foreach($gameVersions as &$version) {
	$version['version'] = intval($version['version']);
	$version['name'] = formatSemanticVersion($version['version']);

	$majorVersion = $version['version'] & VERSION_MASK_PRIMARY;
	foreach($majorGameVersions as $mv) {
		if($mv['version'] === $majorVersion) {
			continue 2;
		}
	}
	$majorGameVersions[] = ['version' => $majorVersion, 'name' => substr(formatSemanticVersion($majorVersion), 0, -2)];
}
unset($version);

$tags = $con->getAll('SELECT tagId, `name`, `text` FROM tags ORDER BY `name`');


$view->assign('headerHighlight', HEADER_HIGHLIGHT_MODS, null, true);
$view->assign('selectedParams', $selectedParams, null, true);
$view->assign('strippedQuery', $strippedQuery, null, true);
$view->assign('fetchCursorJS', $fetchCursorJS, null, true);
$view->assign('sortOptions', VALID_ORDER_BY_COLUMNS, null, true);
$view->assign('gameVersions', $gameVersions);
$view->assign('majorGameVersions', $majorGameVersions);
$view->assign('tags', $tags);
$view->assign('mods', $mods);
$view->display('list-mod');
