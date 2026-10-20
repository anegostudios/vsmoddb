<?php

/**
 * @var object $con
 * @var object $view
 */

require(SRC_ROOT.'/lib/search-mods.php');

if(isset($_GET['paging'])) {
	if($paramError = validateModSearchInputs($searchParams, true)) {
		http_response_code(HTTP_BAD_REQUEST);
		exit(escapeHtml($paramError));
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
function _prepareFilter($filters, $name) {
	if(empty($filters[$name])) return  ['in' => [], 'ex' => []];

	$filter = $filters[$name];
	return [
		'in' => array_flip($filter['in']),
		'ex' => array_flip($filter['ex']),
	];
}
$selectedParams = [
	'order'   => $searchParams['order'],
	'category'=> $filters['category'] ?? '',
	'text'    => escapeHtml($filters['text'] ?? ''),
	'contributor' => !empty($filters['contributor'])
		? [
			'hash' => $filters['contributor'],
			'name' => escapeHtml($con->getOne('SELECT `name` FROM users WHERE `hash` = UNHEX(?)', [$filters['contributor']])),
		]
		: [
		],
	'gameversions' => _prepareFilter($filters, 'gameversions'),
	'tags'         => _prepareFilter($filters, 'tags'),
	'sides'        => _prepareFilter($filters, 'sides'),
	'types'        => _prepareFilter($filters, 'types'),
	'stati'        => _prepareFilter($filters, 'stati'),
];
unset($filters);

$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
$params = array_map('escapeHtml', explode('&', $query));
$params = array_filter($params, function($p) {
	$pname = strchr($p, '=', true);
	return !in_array($pname, ['sortby', 'sortdir']);
});
$strippedQuery = implode('&', $params);

$fetchCursorJS = getNextFetchCursor($searchParams, $mods);

$gameVersions = $con->getAll('SELECT `version` FROM gameVersions ORDER BY `version` desc');
$gameVersionOptions = [];
$majorGameVersions = [];
foreach($gameVersions as &$version) {
	$version['version'] = intval($version['version']);
	$version['name'] = formatSemanticVersion($version['version']);
	// Shape for list-mod-filter template
	$gameVersionOptions[$version['version']] = ['label' => $version['name'], 'value' => $version['name']];

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

switch($selectedParams['category']) {
	case 's': $headerHighlight = HEADER_HIGHLIGHT_TWEAKS;        break;
	case 'l': $headerHighlight = HEADER_HIGHLIGHT_LIBRARY;       break;
	case 'e': $headerHighlight = HEADER_HIGHLIGHT_EXTERNAL_TOOL; break;
	case 'o': $headerHighlight = HEADER_HIGHLIGHT_OTHER;         break;
	default:  $headerHighlight = HEADER_HIGHLIGHT_MODS;
}

$view->assign('headerHighlight', $headerHighlight, null, true);
$view->assign('selectedParams', $selectedParams, null, true);
$view->assign('strippedQuery', $strippedQuery, null, true);
$view->assign('fetchCursorJS', $fetchCursorJS, null, true);
$view->assign('sortOptions', VALID_ORDER_BY_COLUMNS, null, true);
$view->assign('gameVersionOptions', $gameVersionOptions);
$view->assign('majorGameVersions', $majorGameVersions);
$view->assign('mods', $mods);


// fetch all tags for the tag filter sidebar.
$tagOptions = array_column(
	$con->getAll(<<<SQL
		SELECT t.tagId, t.name AS label, t.parentTagId IS NOT NULL AS sub
		FROM tags t
		LEFT JOIN tags p ON p.tagId = t.parentTagId
		ORDER BY COALESCE(p.name, t.name), t.parentTagId IS NOT NULL, t.name
		SQL),
	null,
	'tagId'
);

$view->assign('tagOptions', $tagOptions);
$view->assign('sideOptions', MOD_LIST_FILTER_SIDES);
$view->assign('typeOptions', MOD_LIST_FILTER_TYPES);
$view->assign('statusOptions', MOD_LIST_FILTER_STATI);

$tagsListExpanded = ($_COOKIE['modlist_tags_expanded'] ?? '') === '1';
$view->assign('tagsListExpanded', $tagsListExpanded, null, true);

$view->display('list-mod');
