<?php

include($config["basepath"] . "lib/search-mods.php");

// Mod ID search: identifier uniquely identifies a mod, so redirect directly to its page.
if(!empty($_REQUEST['modid'])) {
	$modid = trim($_REQUEST['modid']);
	$retractionFilter = canModerate(null, $user) ? '' : 'AND mrr.releaseId IS NULL';
	$assetId = $con->getOne("
		SELECT m.assetId
		FROM modReleases mr
		JOIN mods m ON m.modId = mr.modId
		LEFT JOIN modReleaseRetractions mrr ON mrr.releaseId = mr.releaseId
		WHERE mr.identifier = ?
		$retractionFilter
		LIMIT 1
	", [$modid]);

	if($assetId) {
		header("Location: /show/mod/$assetId");
		exit();
	}

	addMessage(MSG_CLASS_ERROR, "No mod found with identifier '$modid'.", true);
}

if(isset($_GET['paging'])) {
	if($paramError = validateModSearchInputs($searchParams, true)) {
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



if($paramError = validateModSearchInputs($searchParams, true)) {
	addMessage(MSG_CLASS_ERROR, $paramError, true);
}
$searchParams['limit'] = MOD_SEARCH_INITIAL_RESULTS;
$mods = queryModSearchForModCards($searchParams);

$filters = &$searchParams['filters'];
$selectedParams = [
	'order'   => $searchParams['order'],
	'side'    => $filters['side'] ?? '',
	'type'    => $filters['type'] ?? '',
	'category'=> $filters['category'] ?? '',
	'text'    => htmlSpecialChars($filters['text'] ?? ''),
	'contributor' => !empty($filters['contributor'])
		? [$filters['contributor'], $con->getOne('SELECT `name` FROM users WHERE `hash` = UNHEX(?)', [$filters['contributor']])]
		: [],
	'majorversion' => $filters['majorversion'] ?? '',
	'gameversions' => !empty($filters['gameversions']) ? array_flip($filters['gameversions']) : [],
	'tags'  => [],
	'stati' => !empty($filters['stati']) ? array_flip($filters['stati']) : [ STATUS_RELEASED => true, STATUS_LOCKED => true ],
];

if(!empty($filters['tags'])) {
	$foldedTagIds = implode(',', $filters['tags']); // @security: $filters['tags'] are filtered to be integers, therefore sql inert.
	$selectedParams['tags'] = $con->getAll("SELECT tagId, `name`, `text` FROM tags WHERE tagId IN ($foldedTagIds) ORDER BY `name`");
}

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


cspReplaceAllowedFetchSources("{$_SERVER['HTTP_HOST']}/list/mod {$_SERVER['HTTP_HOST']}/api/v2/users/by-name/ {$_SERVER['HTTP_HOST']}/api/v2/tags/by-name/");

$view->assign('headerHighlight', $selectedParams['category'] === 's' ? HEADER_HIGHLIGHT_TWEAKS : HEADER_HIGHLIGHT_MODS, null, true);
$view->assign('selectedParams', $selectedParams, null, true);
$view->assign('strippedQuery', $strippedQuery, null, true);
$view->assign('fetchCursorJS', $fetchCursorJS, null, true);
$view->assign('sortOptions', VALID_ORDER_BY_COLUMNS, null, true);
$view->assign('gameVersions', $gameVersions);
$view->assign('majorGameVersions', $majorGameVersions);
$view->assign('mods', $mods);
$view->display('list-mod');
