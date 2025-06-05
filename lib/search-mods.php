<?php

const VALID_ORDER_BY_COLUMNS = [
	// key/cursor selector   column name         desc pretty text    asc pretty text
	'trendingpoints'    => ['m.trendingpoints', 'Most trending'   , 'Least trending'  ],
	'downloads'         => ['m.downloads'     , 'Most downloaded' , 'Least downloaded'],
	'comments'          => ['m.comments'      , 'Most comments'   , 'Least comments'  ],
	'name'              => ['a.name'          , 'Name descending' , 'Name ascending'  ],
	'lastreleased'      => ['m.lastreleased'  , 'Recently updated', 'Least updated'   ],
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
		'order'   => ['lastreleased', 'desc'],
		'filters' => [],
		'limit'   => 0,
		'cursor'  => [],
	];

	if(!empty($_REQUEST['sortby'])) {
		$orderBy = $_REQUEST['sortby'];

		if(!array_key_exists($orderBy, VALID_ORDER_BY_COLUMNS)) {
			return "Invalid sortby: '$orderBy'.";
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
		$majorversion = compileMajorVersion($_REQUEST['mv']);
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
		$outParams['filters']['a.statusid'] = 2;
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
		$mod['modpath'] = formatModPath($mod);
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
				$joinClauses .= 'JOIN assettag t ON t.assetid = a.assetid AND t.tagid IN ('.implode(',', $value).')'; // @security: value must be filtered
				break;

			case 'gameversions':
				$joinClauses .= 'JOIN ModCompatibleGameVersionsCached mcv ON mcv.modId = m.modid AND mcv.gameVersion IN ('.implode(',', $value).')'; // @security: value must be filtered
				break;

			case 'majorversion':
				$joinClauses .= 'JOIN ModCompatibleMajorGameVersionsCached mcmv ON mcmv.modId = m.modid AND mcmv.majorGameVersion = ?';
				array_unshift($sqlParams, $value); // This needs to be in front of others because JOIN happens before WHERE.
				break;

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

		$orderBy .= ', m.modid '.$searchParams['order'][1];

		if($searchParams['cursor']) {
			// Format a condition like
			//   WHERE (m.downloads > 10 OR (m.downloads = 10 AND m.modid > 5))
			// The idea here is to always order the results by some metric _AND_ the modid, so we have a unique column to follow with our cursor.
			// This is also the reason why we add ORDER BY modid if when fetching with limits.
			// This allows us to avoid the ungodly slow LIMIT OFFSET.

			$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
			$col = VALID_ORDER_BY_COLUMNS[$searchParams['order'][0]][0];
			$comparison = $searchParams['order'][1] === 'asc' ? '>' : '<';
			list($colCursor, $idCursor) = $searchParams['cursor'];
			$whereClauses .= "($col $comparison ? OR ($col = ? AND m.modid $comparison $idCursor))"; // @security: value must be filtered
			$sqlParams[] = $colCursor; $sqlParams[] = $colCursor;
		}
	}

	$currentUserId = $user['userid'] ?? 0;

	return $con->getAll("
		SELECT DISTINCT
			a.createdbyuserid,
			a.name,
			a.created,
			a.lastmodified,
			a.tagscached,
			m.*,
			l.cdnpath AS logocdnpath,
			l.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS legacylogo,
			c.name AS `from`,
			s.code AS statuscode,
			f.userid AS following
		FROM `mod` m
		JOIN asset a ON a.assetid = m.assetid
		LEFT JOIN user c ON c.userid = a.createdbyuserid
		LEFT JOIN status s ON s.statusid = a.statusid
		LEFT JOIN follow f ON f.modid = m.modid and f.userid = $currentUserId
		LEFT JOIN file l ON l.fileid = m.cardlogofileid
		$joinClauses
		$whereClauses
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
	$modid = rawurlencode($lastMod['modid']);

	return "&cursor[]={$cursorVal}&cursor[]={$modid}";
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
