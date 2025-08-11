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

/** Validates current GET/POST inputs and creates a filter params object.
 * @param string|array{
 *  order:array{0:string, 1:'asc'|'desc'},
 *  filters:array{
 *   text?:string,
 *   tags?:int[],
 *   'a.createdbyuserid'?:int,
 *   'side'?:'client'|'server'|'both',
 *   gameversions?:int[]
 *  },
 *  limit:int,
 *  cursor:array{0:mixed, 1:int}
 * } &$outParams
 * @return null|string error message
 */
function validateModSearchInputs(&$outParams)
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
		$outParams['filters']['text'] = $_REQUEST['text'];
	}

	if(!empty($_REQUEST['tagids'])) {
		$tags = filter_var($_REQUEST['tagids'], FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY);
		if($tags === false) {
			$f = print_r($_REQUEST['tagids'], true);
			return "Invalid tagids: $f.";
		}
		$outParams['filters']['tags'] = $tags;
	}

	if(!empty($_REQUEST['userid'])) {
		$authorId = intval($_REQUEST['userid']);
		if(!$authorId) {
			return "Invalid userid: '{$_REQUEST['userid']}'.";
		}
		$outParams['filters']['a.createdbyuserid'] = $authorId;
	}

	if(!empty($_REQUEST['side'])) {
		$rSide = $_REQUEST['side'];
		if(!in_array($rSide, ['client', 'server', 'both'], true)) {
			return "Invalid side: '$rSide'.";
		}

		$outParams['filters']['side'] = $rSide;
	}

	if(!empty($_REQUEST['c'])) {
		$rCat = $_REQUEST['c'];
		if(!in_array($rCat, ['m', 'e', 'o'], true)) {
			return "Invalid category: '$rCat'.";
		}

		$outParams['filters']['category'] = $rCat;
	}

	if(!empty($_REQUEST['t'])) {
		$rType = $_REQUEST['t'];
		if(!in_array($rType, ['v', 'd', 'c'], true)) {
			return "Invalid type: '$rType'.";
		}

		$outParams['filters']['type'] = $rType;
		$outParams['filters']['category'] = 'm'; // force the mod type to mod, since we are looking for a specific kind of mod
	}

	if(!empty($_REQUEST['gv']) || !empty($_REQUEST['gameversions'])) {
		$rawGameversions = !empty($_REQUEST['gv']) ? $_REQUEST['gv'] : $_REQUEST['gameversions'];
		$gameversions = array_filter(array_map('compileSemanticVersion', $rawGameversions));
		if(count($gameversions) !== count($rawGameversions)) {
			$f = print_r($rawGameversions, true);
			return "Invalid gameversions: $f.";
		}
		$outParams['filters']['gameversions'] = $gameversions;
	}

	if(!empty($_REQUEST['mv'])) {
		$majorversion = compilePrimaryVersion($_REQUEST['mv']);
		if($majorversion === false) {
			return "Invalid majorversion: {$_REQUEST['mv']}.";
		}
		$outParams['filters']['majorversion'] = $majorversion;
	}

	//TODO(Rennorb) @cleanup: these limits are arbitrary and should probably be outside of this function.
	if(!empty($_REQUEST['limit'])) {
		$limit = intval($_REQUEST['limit']);
		$clampedLimit = max(1, min($limit, 20));
		if($limit !== $clampedLimit) {
			return "Invalid entry limit: '{$_REQUEST['limit']}'";
		}
		$outParams['limit'] = $clampedLimit;
	}

	if(!empty($_REQUEST['cursor'])) {
		$cursor = filter_var($_REQUEST['cursor'], FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);
		if($cursor === false || count($cursor) !== 2 || intval($cursor[1]) != $cursor[1]) {
			return "Invalid cursor: '{$_REQUEST['cursor']}'.";
		}
		$outParams['cursor'] = $cursor;
	}
	

	global $user;
	if(!canModerate(null, $user)) {
		$outParams['filters']['a.statusId'] = 2;
	}

	return null;
}

/** Execute a search query based on the provided search params.
 *  Params MUST BE SQL SAFE (with exception of the 'text' filter)!
 * @param string|array{order:array{0:string, 1:'asc'|'desc'}, filters:array{text?:string, tags?:int[], 'a.createdbyuserid'?:int, 'side'?:'client'|'server'|'both', gameversions?:int[]}, limit:int, cursor:array{0:mixed, 1:int}} $searchParams Params created from validateInputs MUST BE SQL SAFE!
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
 * @param string|array{order:array{0:string, 1:'asc'|'desc'}, filters:array{text?:string, tags?:int[], 'a.createdbyuserid'?:int, 'side'?:'client'|'server'|'both', gameversions?:int[]}, limit:int, cursor:array{0:mixed, 1:int}} $searchParams Params created from validateInputs MUST BE SQL SAFE!
 * @return array
 */
function queryModSearch($searchParams)
{
	global $con, $user;

	$joinClauses = '';
	$whereClauses = '';
	$sqlParams = [];
	
	foreach($searchParams['filters'] as $name => $value) {
		switch($name) {
			case 'text':
				$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
				$whereClauses .= '(a.name LIKE ? OR m.descriptionsearchable LIKE ? OR m.summary LIKE ?)';
				$v = '%'.escapeStringForLikeQuery($value).'%'; $sqlParams[] = $v; $sqlParams[] = $v; $sqlParams[] = $v;
				break;

			case 'tags':
				$joinClauses .= 'JOIN modTags t ON t.modId = m.modId AND t.tagId IN ('.implode(',', $value).')'; // @security: value must be filtered
				break;

			case 'gameversions':
				$joinClauses .= 'JOIN modCompatibleGameVersionsCached mcv ON mcv.modId = m.modId AND mcv.gameVersion IN ('.implode(',', $value).')'; // @security: value must be filtered
				break;

			case 'majorversion':
				$joinClauses .= 'JOIN modCompatibleMajorGameVersionsCached mcmv ON mcmv.modId = m.modId AND mcmv.majorGameVersion = ?';
				array_unshift($sqlParams, $value); // This needs to be in front of others because JOIN happens before WHERE.
				break;

			case 'side':
				$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
				$whereClauses .= "m.side = ?";
				$sqlParams[] = $value;
				break;

			case 'type':
				switch($value) {
					case 'v': $value = 'Theme'; break;
					case 'd': $value = 'Content'; break;
					case 'c': $value = 'Code'; break;
					default: assert(false, "Invalid kind: $value");
				}
				//TODO(Rennorb) @cleanup @legacy: Old mods don't have this information set, they wont match this filter.
				$joinClauses .= <<<SQL
					JOIN modReleases r ON r.modId = m.modId
					JOIN files fi ON fi.assetId = r.assetId
					JOIN modPeekResults mpr on mpr.fileId = fi.fileId AND mpr.type = '$value'
				SQL; // @security: $value can only be one of the specified strings, therefore its sql inert
				break;

			case 'category':
				$name = 'm.type';
				switch($value) {
					case 'm': $value = 'mod'; break;
					case 'e': $value = 'externaltool'; break;
					case 'o': $value = 'other'; break;
					default: assert(false, "Invalid type: $value");
				}
				/* fallthrough */

			default:
				$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
				$whereClauses .= "$name = ?";
				$sqlParams[] = $value;
				break;
		}
	}

	$orderBy = VALID_ORDER_BY_COLUMNS[$searchParams['order'][0]][0].' '.$searchParams['order'][1];

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
			// This is also the reason why we add ORDER BY modId if when fetching with limits.
			// This allows us to avoid the ungodly slow LIMIT OFFSET.

			$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
			$col = VALID_ORDER_BY_COLUMNS[$searchParams['order'][0]][0];
			$comparison = $searchParams['order'][1] === 'asc' ? '>' : '<';
			list($colCursor, $idCursor) = $searchParams['cursor'];
			$whereClauses .= "($col $comparison ? OR ($col = ? AND m.modId $comparison $idCursor))"; // @security: value must be filtered
			$sqlParams[] = $colCursor; $sqlParams[] = $colCursor;
		}
	}

	$currentUserId = $user['userId'] ?? 0;

	return $con->getAll("
		SELECT DISTINCT
			a.createdByUserId,
			a.name,
			a.created,
			a.lastModified,
			a.tagsCached,
			m.*,
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
		GROUP BY m.modId
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

	$lastMod = end($mods);
	reset($mods);


	$cursorVal = $lastMod[$searchParams['order'][0]];
	// prevent header injection just in case
	$cursorVal = rawurlencode($cursorVal);
	$modId = rawurlencode($lastMod['modId']);

	return "&cursor[]={$cursorVal}&cursor[]={$modId}";
}


//TODO(Rennorb): @completeness: Translate priority text matching to sql.
// This is the old ranking algorithm which ordered text searches b  "correctness" of the match (only in 5 layers, but still).
// This is really hard to replicate with paging. period. I need to have a really hard think on how to do this.
/* 
function getModMatchWeight($mod, $text) {
		if (empty($text)) return 5;
		
		// Exact mod name match
		if (strcasecmp($mod['name'], $text) == 0) return 1;
		$pos = stripos($mod['name'], $text);
		// Mod name starts with text
		if ($pos === 0) return 2;
		// Mod name contains text
		if ($pos > 0) return 3;
		// Summary contains text
		if (strstr($mod['summary'], $text)) return 4;
		// Contained somewhere
		return 5;
	}

*/
