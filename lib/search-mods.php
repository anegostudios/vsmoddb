<?php

const VALID_ORDER_BY_COLUMNS = [
	// key/cursor selector   column name         desc pretty text    asc pretty text
	'trendingPoints'    => ['m.trendingPoints', 'Most trending'   , 'Least trending'  ],
	'downloads'         => ['m.downloads'     , 'Most downloaded' , 'Least downloaded'],
	'comments'          => ['m.comments'      , 'Most comments'   , 'Least comments'  ],
	'name'              => ['a.name'          , 'Name descending' , 'Name ascending'  ],
	'lastReleased'      => ['m.lastReleased'  , 'Recently updated', 'Least updated'   ],
	'created'           => ['m.created'       , 'Recently added'  , 'First added'     ],
];

const MOD_LIST_FILTER_SIDES = [
	'both'   => ['label' => 'Both'],
	'client' => ['label' => 'Client side mod'],
	'server' => ['label' => 'Server side mod'],
];

const MOD_LIST_FILTER_TYPES = [
	'v' => ['label' => 'Theme Pack (purely visual)', 'peek' => 'Theme'],
	'd' => ['label' => 'Content Mod',                 'peek' => 'Content'],
	'c' => ['label' => 'Code Mod',                    'peek' => 'Code'],
];

const MOD_LIST_FILTER_STATI = [
	STATUS_DRAFT    => ['label' => 'Draft'],
	STATUS_RELEASED => ['label' => 'Released'],
	STATUS_LOCKED   => ['label' => 'Locked'],
];

/**
 * @param array<string>|array<int> $array
 * @param callable|null $predicate - return false to discard item, any other value to accept that transformed value. only used for string values
 * @return array{in: array, ex: array}|false
 */
function parseAsIncludeExclude($array, $predicate = null)
{
	if(empty($array)) return ['in' => [], 'ex' => []];

	$include = [];
	$exclude = [];

	if(is_string($array[0])) {
		foreach($array as $element) {
			$isExclude = str_starts_with($element, '-');

			$element = $isExclude ? substr($element, 1) : $element;
			$element = $predicate($element);
			if($element === false) return false;

			if($isExclude) $exclude[] = $element;
			else $include[] = $element;
		}
	}
	else {
		foreach($array as $element) {
			$isExclude = $element < 0;

			if($isExclude) $exclude[] = -$element;
			else $include[] = $element;
		}
	}

	return ['in' => $include, 'ex' => $exclude];
}

/** Validates current GET/POST inputs and creates a filter params object.
 * @param array|null &$outParams
 * @param-out array{
 *  order:array{0:string, 1:'asc'|'desc'},
 *  filters:array{
 *   text?:string,
 *   category?:'m'|'s'|'l'|'e'|'o'|'a',
 *   tags?:array{in:int[], ex:int[]},
 *   sides?:array{in:string[], ex:string[]},
 *   types?:array{in:string[], ex:string[]},
 *   stati?:array{in:int[], ex:int[]},
 *   contributor?:string,
 *   gameversions?:array{in:int[], ex:int[]},
 *   majorversion?:int,
 *  },
 *  limit:int,
 *  cursor:array{0:mixed, 1:int}
 * } $outParams
 * @param bool $trimText if set, the search text will be trimmed of leading / trailing whitespace and multiple whitespaces will be collapsed.
 * @return null|string error message
 */
function validateModSearchInputs(&$outParams, $trimText)
{
	$outParams = [
		'order'   => ['lastReleased', 'desc'],
		'filters' => [],
		'limit'   => 0,
		'cursor'  => [],
	];

	if(!empty($_REQUEST['sortby'])) {
		$orderBy = $_REQUEST['sortby'];

		if(!array_key_exists($orderBy, VALID_ORDER_BY_COLUMNS)) {
			switch($orderBy) {
				case 'trendingpoints': $orderBy = 'trendingPoints'; break; // @legacy
				case 'lastreleased':   $orderBy = 'lastReleased';   break; // @legacy

				default:
					return "Invalid sortby: '$orderBy'.";
			}
		}

		$outParams['order'][0] = $orderBy;
	}

	if(!empty($_REQUEST['sortdir'])) {
		$sortDir = $_REQUEST['sortdir'];
		if(!in_array($sortDir, ['a', 'asc', 'd', 'desc'], true)) {
			return "Invalid sortdir: '$sortDir'.";
		}

		$outParams['order'][1] = ($sortDir === 'a' || $sortDir === 'asc') ? 'asc' : 'desc';
	}

	if(!empty($_REQUEST['text'])) {
		$outParams['filters']['text'] = !$trimText ? $_REQUEST['text'] : preg_replace('/\s+/', ' ', trim($_REQUEST['text']));
	}

	if(!empty($_REQUEST['tg'])) {
		$tags = forceArrayOfInts($_REQUEST['tg']);
		if($tags === false) return 'Invalid tg: '.print_r($_REQUEST['tg'], true).'.';

		$outParams['filters']['tags'] = parseAsIncludeExclude($tags);
	}
	else if(!empty($_REQUEST['tagids'])) { // @legacy
		$tags = forceArrayOfInts($_REQUEST['tagids']);
		if($tags === false)  return 'Invalid tagids: '.print_r($_REQUEST['tagids'], true).'.';

		$outParams['filters']['tags'] = ['in' => $tags, 'ex' => []];
	}

	if(!empty($_REQUEST['a'])) {
		$contributorHash = filter_var($_REQUEST['a'], FILTER_UNSAFE_RAW | FILTER_FLAG_STRIP_LOW);
		if($contributorHash !== $_REQUEST['a']) {
			return "Invalid contributor hash: '{$_REQUEST['a']}'.";
		}
		$outParams['filters']['contributor'] = $contributorHash;
	}

	if(!empty($_REQUEST['sd'])) {
		$sides = filter_var($_REQUEST['sd'], FILTER_UNSAFE_RAW, FILTER_FORCE_ARRAY);
		$sides = parseAsIncludeExclude($sides, fn($v) => array_key_exists($v, MOD_LIST_FILTER_SIDES) ? $v : false);
		if($sides === false)  return 'Invalid sd: '.print_r($_REQUEST['sd'], true).'.';

		$outParams['filters']['sides'] = $sides;
	}
	else if(!empty($_REQUEST['side'])) { // @legacy
		$rSide = $_REQUEST['side'];
		if(!array_key_exists($rSide, MOD_LIST_FILTER_SIDES)) return "Invalid side: '".print_r($rSide, true)."'.";

		$outParams['filters']['side'] = $rSide;
	}

	if(!empty($_REQUEST['c'])) {
		$rCat = $_REQUEST['c'];
		if(!in_array($rCat, ['m', 's', 'e', 'o', 'l', 'a'], true)) {
			return "Invalid category: '$rCat'.";
		}

		$outParams['filters']['category'] = $rCat;
	}
	else {
		$outParams['filters']['category'] = 'm'; // default to game mods
	}

	if(!empty($_REQUEST['t'])) {
		$types = filter_var($_REQUEST['t'], FILTER_UNSAFE_RAW, FILTER_FORCE_ARRAY);
		$types = parseAsIncludeExclude($types, fn($v) => array_key_exists($v, MOD_LIST_FILTER_TYPES) ? $v : false);
		if($types === false)  return 'Invalid types: '.print_r($_REQUEST['t'], true).'.';

		$outParams['filters']['types'] = $types;

		// force the mod type to mod, since we are looking for a specific kind of mod
		if(!in_array($outParams['filters']['category'], ['m', 'l'], true)) $outParams['filters']['category'] = 'm';
	}
	
	if(!empty($_REQUEST['gv'])) {
		$gameVersions = filter_var($_REQUEST['gv'], FILTER_UNSAFE_RAW, FILTER_FORCE_ARRAY);
		$gameVersions = parseAsIncludeExclude($gameVersions, 'compileSemanticVersion');
		if($gameVersions === false)  return 'Invalid gvs: '.print_r($_REQUEST['gv'], true).'.';

		$outParams['filters']['gameversions'] = $gameVersions;
	}

	if(!empty($_REQUEST['mv'])) {
		$majorversion = compilePrimaryVersion($_REQUEST['mv']);
		if($majorversion === false) {
			return "Invalid majorversion: {$_REQUEST['mv']}.";
		}
		$outParams['filters']['majorversion'] = $majorversion;
	}

	if(!empty($_REQUEST['limit'])) {
		$limit = intval($_REQUEST['limit']);
		$clampedLimit = max(MOD_SEARCH_VALIDATE_LIMIT_MIN, min($limit, MOD_SEARCH_VALIDATE_LIMIT_MAX));
		if($limit !== $clampedLimit) {
			return "Invalid entry limit: '{$_REQUEST['limit']}'";
		}
		$outParams['limit'] = $clampedLimit;
	}

	if(!empty($_REQUEST['cursor'])) {
		$cursor = filter_var($_REQUEST['cursor'], FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);
		if($cursor === false || count($cursor) !== 3 || intval($cursor[1]) != $cursor[1] || intval($cursor[2]) != $cursor[2]) {
			return "Invalid cursor: '{$_REQUEST['cursor']}'.";
		}
		$outParams['cursor'] = $cursor;
	}
	

	global $user;
	if(canModerate(null, $user)) {
		$outParams['filters']['stati'] = ['in' => [STATUS_RELEASED, STATUS_LOCKED], 'ex' => []]; // default for moderators
		if(!empty($_REQUEST['st'])) {
			$stati = parseIncludeExcludeParam('st', fn($v) => ctype_digit($v) && array_key_exists(intval($v), MOD_LIST_FILTER_STATI) ? intval($v) : null);
			if($stati === false) return 'Invalid st: '.print_r($_REQUEST['st'], true);
			$outParams['filters']['stati'] = $stati;
		}
	}
	else {
		$outParams['filters']['stati'] = ['in' => [STATUS_RELEASED], 'ex' => []]; // locked to "released" for players
	}

	return null;
}

/** Execute a search query based on the provided search params.
 *  Params MUST BE SQL SAFE (with exception of the 'text' filter)!
 * @param array $searchParams Params created from validateModSearchInputs. MUST BE SQL SAFE!
 * @return array
 */
function queryModSearchForModCards($searchParams)
{
	$mods = queryModSearch($searchParams);
	foreach($mods as &$mod) {
		$mod['dbPath'] = formatModPath($mod);
	}
	unset($mod);

	return $mods;
}

/** Execute a search query based on the provided search params.
 *  Params MUST BE SQL SAFE (with exception of the 'text' filter)!
 * @param array{
 *  order:array{0:string, 1:'asc'|'desc'},
 *  filters:array{
 *   text?:string,
 *   category?:'m'|'s'|'l'|'e'|'o'|'a',
 *   tags?:array{in:int[], ex:int[]},
 *   sides?:array{in:string[], ex:string[]},
 *   types?:array{in:string[], ex:string[]},
 *   stati?:array{in:int[], ex:int[]},
 *   contributor?:string,
 *   gameversions?:array{in:int[], ex:int[]},
 *   majorversion?:int,
 *  },
 *  limit:int,
 *  cursor:array{0:mixed, 1:int}
 * } $searchParams Params created from validateInputs MUST BE SQL SAFE!
 * @return array
 */
function queryModSearch($searchParams)
{
	global $con, $user;

	$joinClauses = '';
	$whereClauses = '';
	$matchScoreSelect = '0 as matchScore,';
	$sqlParams = [];
	// Join Params need to be inserted between previous join params and where params because JOIN happens before WHERE.
	// This is the index in the params array where the next join param goes.
	$joinParamsOffset = 0;
	$havingClauses = '';
	$joinsMultiplyRows = false;

	if(!empty($user['genAiTolerance'])) {
		require(SRC_ROOT.'/lib/moderation.php');

		$joinClauses = 'LEFT JOIN moderationRequests req ON req.referenceId = m.modId AND (req.kind, req.category) = ('.MOD_REQUEST_KIND_REPORT_MOD.', '.REPORT_CATEGORY_MOD_LOW_EFFORT_AI.') AND resolved IS NULL ';
		$havingClauses = 'HAVING COUNT(req.requestId) < '.$user['genAiTolerance']; // @security: $user['genAiTolerance'] comes from the database and is numeric, therefore sql inert.

		$joinsMultiplyRows = true;
	}

	$orderBy = VALID_ORDER_BY_COLUMNS[$searchParams['order'][0]][0].' '.$searchParams['order'][1];

	if(!isset($searchParams['filters']['category'])) {
		$searchParams['filters']['category'] = 'm';
	}

	foreach($searchParams['filters'] as $name => $value) {
		switch($name) {
			case 'text':
				// We want to return results ordered by relevance, so we build a 'score' metric to order by.
				// This score metric is constructed as follows:
				//   score := 0
				//   if search in description then score += 1
				//   if search in summary then score += 5
				//   if search in name then score += 10
				//   if name starts with search then score += 5

				// While the first two checks are easily done, the later two are more complicated to do efficiently.
				// To realize those, we combine the LOCATE expression with a bit of bit twiddling to archive an ordering of
				// not found = 0 < found < found at the start.

				// LOCATE returns 0 for not found, 1 based index of the match otherwise.

				// The key insight here is that clamping to two, subtracting one and masking off the least two bits of the resulting -1, 0 and 1
				// produces 0b11, 0b00, 0b01 which is exactly the inverse of the order we are looking for (0b11 > 0b01 > 0b00).
				// This can then trivially be inverted to archive our desired priority.

				// 3 - (0 - 1) & 0b11 = 3 - (0xffffffff & 0x11) = 3 - 3 = 0   // search not found
				// 3 - (1 - 1) & 0b11 = 3 - (0x00000000 & 0x11) = 3 - 0 = 3   // match starts at first letter
				// 3 - (2 - 1) & 0b11 = 3 - (0x00000001 & 0x11) = 3 - 1 = 2   // match starts later

				$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
				if(strlen($value) >= 3) {
					$v = preprocessStringForFulltextQuery($value);

					// FULLTEXT search using MATCH .. AGAINST makes this fuzzy which catches partial words and order mismatches. LOCATE is still used to prioritize exact matches in the name.
					$matchScoreFormular = <<<SQL
						(3 - ((LEAST(LOCATE(LOWER(?), LOWER(a.name)), 2) - 1) & 0b11)) * 5 + (MATCH(a.name) AGAINST(? IN BOOLEAN MODE) > 0) * 5
						+ (MATCH(m.summary, m.descriptionSearchable) AGAINST(? IN BOOLEAN MODE) > 0)
					SQL; //NOTE(Rennorb) @correctness: Does not differentiate between summary and description for scoring... probably not the best idea.
					$matchScoreSelect = $matchScoreFormular.' as matchScore,';
					array_unshift($sqlParams, $value, $v, $v);
					$joinParamsOffset += 3;

					$whereClauses .= '(MATCH(a.name) AGAINST(? IN BOOLEAN MODE) OR MATCH(m.summary, m.descriptionSearchable) AGAINST(? IN BOOLEAN MODE))';
					array_push($sqlParams, $v, $v);
				}
				else {
					$v = '%'.escapeStringForLikeQuery($value).'%';

					$matchScoreFormular = <<<SQL
						(3 - ((LEAST(LOCATE(LOWER(?), LOWER(a.name)), 2) - 1) & 0b11)) * 5
						+ (m.summary LIKE ?) * 5
						+ (m.descriptionSearchable LIKE ?)
					SQL;
					$matchScoreSelect = $matchScoreFormular.' as matchScore,';
					array_unshift($sqlParams, $value, $v, $v);
					$joinParamsOffset += 3;

					// Below ft_min_word_len (default 3), FULLTEXT won't match. Fall back to LIKE.
					$whereClauses .= '(a.name LIKE ? OR m.summary LIKE ? OR m.descriptionSearchable LIKE ?)';
					array_push($sqlParams, $v, $v, $v);
				}

				$orderBy = 'matchScore DESC, '.$orderBy;

				break;

			case 'tags':
				// @security: $value only contains integers, therefore sql inert.
				if($value['in']) {
					$joinClauses .= 'JOIN modTags t ON t.modId = m.modId AND t.tagId IN ('.implode(',', $value['in']).') AND t.votes >= 0 ';
					$joinsMultiplyRows = true;
				}
				if($value['ex']) {
					$joinClauses .= 'LEFT JOIN modTags tex ON tex.modId = m.modId AND tex.tagId IN ('.implode(',', $value['ex']).') AND tex.votes >= 0 ';
					$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
					$whereClauses .= 'tex.modId IS NULL';
				}
				break;

			case 'gameversions':
				$joinClauses .= 'JOIN modCompatibleGameVersionsCached mcv ON mcv.modId = m.modId AND mcv.gameVersion IN ('.implode(',', $value['in']).') ';
				$joinsMultiplyRows = true;
				break;

			case 'majorversion':
				$joinClauses .= 'JOIN modCompatibleMajorGameVersionsCached mcmv ON mcmv.modId = m.modId AND mcmv.majorGameVersion = ? ';
				array_splice($sqlParams, $joinParamsOffset, 0, $value);
				$joinParamsOffset++;
				break;

			case 'contributor':
				// team members
				$joinClauses .= 'LEFT JOIN modTeamMembers tm ON tm.modId = m.modId AND tm.userId = (SELECT userId FROM users tu WHERE tu.hash = UNHEX(?)) ';
				array_splice($sqlParams, $joinParamsOffset, 0, $value);
				$joinParamsOffset++;

				$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
				$whereClauses .= "(c.hash = UNHEX(?) OR tm.userId IS NOT NULL)";
				$sqlParams[] = $value;
				break;

			case 'sides':
				// @security: $value only contains keys of MOD_LIST_FILTER_SIDES, therefore sql inert.
				if($value['in']) {
					$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
					$whereClauses .= "m.side IN ('".implode("','", $value['in'])."')";
				}
				if($value['ex']) {
					$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
					$whereClauses .= "m.side NOT IN ('".implode("','", $value['ex'])."')";
				}
				break;

			case 'types':
				// @security: peek names come from MOD_LIST_FILTER_TYPES, therefore sql inert.
				$toPeekList = fn($codes) => "'".implode("','", array_map(fn($code) => MOD_LIST_FILTER_TYPES[$code]['peek'], $codes))."'";
				if($value['in']) {
					$peekList = $toPeekList($value['in']);
					$joinClauses .= <<<SQL
						JOIN modReleases r ON r.modId = m.modId
						JOIN files fi ON fi.assetId = r.assetId
						JOIN modPeekResults mpr ON mpr.fileId = fi.fileId AND mpr.type IN ($peekList)

					SQL;
					$joinsMultiplyRows = true;
				}
				if($value['ex']) {
					$peekList = $toPeekList($value['ex']);
					$joinClauses .= <<<SQL
						LEFT JOIN (
							modReleases rex
							JOIN files fiex ON fiex.assetId = rex.assetId
							JOIN modPeekResults mprex ON mprex.fileId = fiex.fileId AND mprex.type IN ($peekList)
						) ON rex.modId = m.modId

					SQL;
					$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
					$whereClauses .= 'rex.releaseId IS NULL';
				}
				break;

			case 'stati':
				// @security: $value only contains integers, therefore sql inert.
				if($value['in']) {
					$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
					$whereClauses .= 'a.statusId IN ('.implode(',', $value['in']).')';
				}
				if($value['ex']) {
					$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
					$whereClauses .= 'a.statusId NOT IN ('.implode(',', $value['ex']).')';
				}
				break;

			case 'category':
				switch($value) {
					case 'a':
						// see all
						// TODO: "See all" tab.
						break 2;

					case 'm': $value = CATEGORY_GAME_MOD; break;
					case 's': $value = CATEGORY_SERVER_TWEAK; break;
					case 'l': $value = CATEGORY_LIBRARY; break;
					case 'e': $value = CATEGORY_EXTERNAL_TOOL; break;
					case 'o': $value = CATEGORY_OTHER; break;
					default: assert(false, "Invalid category: $value");
				}
				$name = 'm.category';
				/* fallthrough */

			default:
				$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
				$whereClauses .= "$name = ?";
				$sqlParams[] = $value;
				break;
		}
	}

	// It is somewhat important to not use offsets here. They are convenient, but also poor in performance.
	// The better approach is to isolate indexed limits for the current query and offset based on those.
	$limitClause = '';
	if($searchParams['limit']) {
		$limitClause = 'LIMIT '.$searchParams['limit'];

		$orderBy .= ', m.modId '.$searchParams['order'][1];

		if($searchParams['cursor']) {
			// Format a condition like
			//   WHERE (m.downloads > 10 OR (m.downloads = 10 AND m.modId > 5))
			// The idea here is to always order the results by some metric _AND_ the modId, so we have a unique column to follow with our cursor.
			// This is also the reason why we add ORDER BY modId when fetching with limits.
			// This allows us to avoid the ungodly slow LIMIT OFFSET.

			// This was the original idea at least, but unfortunately this optimization degrades massively when we are doing a text lookup at the same time.
			// Since that uses a calculated score metric that cannot be contained in any kind of index (because it depends on the search text), it almost inevitably forces a complete table scan.

			$orderByCol = VALID_ORDER_BY_COLUMNS[$searchParams['order'][0]][0];
			$comparison = $searchParams['order'][1] === 'asc' ? '>' : '<';
			list($colCursor, $idCursor, $score) = $searchParams['cursor'];

			$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
			if(!empty($searchParams['filters']['text'])) {
				//NOTE(Rennorb): The order of comparisons is important here, it must match the sorting order of the ORDER BY clause of the final query.
				$whereClauses .= <<<SQL
					(
						(($matchScoreFormular) < $score) OR
						(($matchScoreFormular) <= $score AND (
							$orderByCol $comparison ? OR
							($orderByCol = ? AND m.modId $comparison $idCursor)
						))
					)
				SQL; // @security: $idCursor and $score must be sql safe (validateModSearchInputs does that)

				$value = $searchParams['filters']['text'];

				array_push($sqlParams,
					$value, $v, $v, // inputs for $matchScoreFormular
					$value, $v, $v, // inputs for $matchScoreFormular
					$colCursor, $colCursor, // basic cursor inputs
				);
			}
			else {
				$whereClauses .= "($orderByCol $comparison ? OR ($orderByCol = ? AND m.modId $comparison $idCursor))"; // @security: $idCursor must be sql safe (validateModSearchInputs does that)
				array_push($sqlParams, $colCursor, $colCursor);
			}

		}
	}

	$currentUserId = $user['userId'] ?? 0;

	// Only filter joins (tags, gameversions, type) can multiply rows; base joins all hit PK/UNIQUE.
	$dedup = $joinsMultiplyRows ? 'DISTINCT' : '';
	$groupBy = $joinsMultiplyRows ? 'GROUP BY m.modId' : '';

	dump(["
		SELECT $dedup
			$matchScoreSelect
			a.createdByUserId,
			a.name,
			a.created,
			a.lastModified,
			m.modId,
			m.assetId,
			m.urlAlias,
			m.cardLogoFileId,
			m.summary,
			m.downloads,
			m.follows,
			m.trendingPoints,
			m.comments,
			m.side,
			m.category,
			m.lastReleased,
			l.cdnPath AS logoCdnPath,
			l.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
			c.name AS `from`,
			s.code AS statusCode,
			f.userId AS following
		FROM mods m
		JOIN assets a ON a.assetId = m.assetId
		LEFT JOIN users c ON c.userId = a.createdByUserId
		LEFT JOIN status s ON s.statusId = a.statusId
		LEFT JOIN userFollowedMods f ON f.modId = m.modId and f.userId = $currentUserId
		LEFT JOIN files l ON l.fileId = m.cardLogoFileId
		$joinClauses
		$whereClauses
		$groupBy
		$havingClauses
		ORDER BY $orderBy
		$limitClause
	", $sqlParams]);

	return $con->getAll("
		SELECT $dedup
			$matchScoreSelect
			a.createdByUserId,
			a.name,
			a.created,
			a.lastModified,
			m.modId,
			m.assetId,
			m.urlAlias,
			m.cardLogoFileId,
			m.summary,
			m.downloads,
			m.follows,
			m.trendingPoints,
			m.comments,
			m.side,
			m.category,
			m.lastReleased,
			l.cdnPath AS logoCdnPath,
			l.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
			c.name AS `from`,
			s.code AS statusCode,
			f.userId AS following
		FROM mods m
		JOIN assets a ON a.assetId = m.assetId
		LEFT JOIN users c ON c.userId = a.createdByUserId
		LEFT JOIN status s ON s.statusId = a.statusId
		LEFT JOIN userFollowedMods f ON f.modId = m.modId and f.userId = $currentUserId
		LEFT JOIN files l ON l.fileId = m.cardLogoFileId
		$joinClauses
		$whereClauses
		$groupBy
		$havingClauses
		ORDER BY $orderBy
		$limitClause
	", $sqlParams);
}

/** Formats a cursor query string that allows to obtain the next page of results
 * @param string|array{order:array{0:string, 1:'asc'|'desc'}, filters:array{text?:string, tags?:int[], 'a.createdbyuserid'?:int, 'side'?:'client'|'server'|'both', gameversions?:int[]}, limit:int, cursor:array{0:mixed, 1:int}} $searchParams Params created from validateInputs
 * @param $mods array
 * @return string might be empty if no mods are present
 */
function getNextFetchCursor($searchParams, $mods)
{
	if(empty($mods) || !$searchParams['limit'] || count($mods) < $searchParams['limit'])  return '';

	$lastMod = last($mods);

	$cursorVal = $lastMod[$searchParams['order'][0]];
	// prevent header injection just in case
	$cursorVal = rawurlencode($cursorVal);
	$modId = rawurlencode($lastMod['modId']);
	$score = rawurlencode($lastMod['matchScore']);

	return "&cursor[]={$cursorVal}&cursor[]={$modId}&cursor[]={$score}";
}
