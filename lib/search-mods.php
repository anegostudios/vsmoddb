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
 *   'contributor'?:int,
 *   'side'?:'client'|'server'|'both',
 *   gameversions?:int[],
 *   stati?:int[],
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
		$tags = forceArrayOfInts($_REQUEST['tagids']);
		if($tags === false) {
			$f = print_r($_REQUEST['tagids'], true);
			return "Invalid tagids: $f.";
		}
		$outParams['filters']['tags'] = $tags;
	}

	if(!empty($_REQUEST['a'])) {
		$contributorHash = filter_var($_REQUEST['a'], FILTER_UNSAFE_RAW | FILTER_FLAG_STRIP_LOW);
		if($contributorHash !== $_REQUEST['a']) {
			return "Invalid contributor hash: '{$_REQUEST['a']}'.";
		}
		$outParams['filters']['contributor'] = $contributorHash;
	}
	else if(!empty($_REQUEST['userid'])) { // @legacy
		$contributorId = intval($_REQUEST['userid']);
		if(!$contributorId) {
			return "Invalid userid: '{$_REQUEST['userid']}'.";
		}
		global $con;
		$contributorHash = $con->getOne("SELECT hash FROM users WHERE userId = $contributorId"); // @security: $contributorId is known to be an integer, therefore sql inert.
		$outParams['filters']['contributor'] = $contributorHash;
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
		if(!in_array($rCat, ['m', 's', 'e', 'o'], true)) {
			return "Invalid category: '$rCat'.";
		}

		$outParams['filters']['category'] = $rCat;
	}
	else {
		$outParams['filters']['category'] = 'a'; // default to no server tweaks
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
		if($cursor === false || count($cursor) !== 3 || intval($cursor[1]) != $cursor[1] || intval($cursor[2]) != $cursor[2]) {
			return "Invalid cursor: '{$_REQUEST['cursor']}'.";
		}
		$outParams['cursor'] = $cursor;
	}
	

	global $user;
	if(canModerate(null, $user)) {
		$s = getInputArrayOfInts(INPUT_REQUEST, 'stati');
		if($s === false) {
			return "Invalid stati: {$_REQUEST['stati']}";
		}

		if($s !== null) $outParams['filters']['stati'] = $s;
	}
	else {
		$outParams['filters']['stati'] = [STATUS_RELEASED];
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
	$matchScoreSelect = '0 as matchScore,';
	$sqlParams = [];
	// Join Params need to be inserted between previous join params and where params because JOIN happens before WHERE.
	// This is the index in the params array where the next join param goes.
	$joinParamsOffset = 0;

	$orderBy = VALID_ORDER_BY_COLUMNS[$searchParams['order'][0]][0].' '.$searchParams['order'][1];

	foreach($searchParams['filters'] as $name => $value) {
		switch($name) {
			case 'text':
				$v = '%'.escapeStringForLikeQuery($value).'%';

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
				$matchScoreFormular = '(3 - ((LEAST(LOCATE(LOWER(?), LOWER(a.name)), 2) - 1) & 0b11)) * 5 + (m.summary LIKE ?) * 5 + (m.descriptionSearchable LIKE ?)';
				$matchScoreSelect = $matchScoreFormular.' as matchScore,';
				array_unshift($sqlParams, $value, $v, $v);
				$joinParamsOffset += 3;

				$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
				$whereClauses .= '(a.name LIKE ? OR m.summary LIKE ? OR m.descriptionSearchable LIKE ?)';
				array_push($sqlParams, $v, $v, $v);

				$orderBy = 'matchScore DESC, '.$orderBy;

				break;

			case 'tags':
				$joinClauses .= 'JOIN modTags t ON t.modId = m.modId AND t.tagId IN ('.implode(',', $value).')'; // @security: $value must be sql safe (validateModSearchInputs does that)
				break;

			case 'gameversions':
				$joinClauses .= 'JOIN modCompatibleGameVersionsCached mcv ON mcv.modId = m.modId AND mcv.gameVersion IN ('.implode(',', $value).')'; // @security: $value must be sql safe (validateModSearchInputs does that)
				break;

			case 'majorversion':
				$joinClauses .= 'JOIN modCompatibleMajorGameVersionsCached mcmv ON mcmv.modId = m.modId AND mcmv.majorGameVersion = ?';
				array_splice($sqlParams, $joinParamsOffset, 0, $value);
				$joinParamsOffset++;
				break;

			case 'contributor':
				// team members
				$joinClauses .= 'LEFT JOIN modTeamMembers tm ON tm.modId = m.modId AND tm.userId = (SELECT userId FROM users tu WHERE tu.hash = UNHEX(?))';
				array_splice($sqlParams, $joinParamsOffset, 0, $value);
				$joinParamsOffset++;

				$whereClauses .= $whereClauses ? ' AND ' : 'WHERE '; // direct author
				$whereClauses .= "(c.hash = UNHEX(?) OR tm.userId IS NOT NULL)";
				$sqlParams[] = $value;
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
				SQL; // @security: $value can only be one of the specified strings, therefore its sql inert.
				break;

			case 'stati':
				$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
				$foldedIds = implode(',', array_map('intval', $value));
				// @security: $foldedIds only contains numbers, and is therefore sql inert.
				$whereClauses .= "a.statusId IN ($foldedIds)";
				break;

			case 'category':
				switch($value) {
					case 'a':
						$whereClauses .= $whereClauses ? ' AND ' : 'WHERE ';
						$whereClauses .= 'm.category != '.CATEGORY_SERVER_TWEAK;
						break 2;

					case 'm': $value = CATEGORY_GAME_MOD; break;
					case 's': $value = CATEGORY_SERVER_TWEAK; break;
					case 'e': $value = CATEGORY_EXTERNAL_TOOL; break;
					case 'o': $value = CATEGORY_OTHER; break;
					default: assert(false, "Invalid category: $value");
				}
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
				$v = '%'.escapeStringForLikeQuery($value).'%';

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

	return $con->getAll("
		SELECT DISTINCT
			$matchScoreSelect
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

	$lastMod = last($mods);

	$cursorVal = $lastMod[$searchParams['order'][0]];
	// prevent header injection just in case
	$cursorVal = rawurlencode($cursorVal);
	$modId = rawurlencode($lastMod['modId']);
	$score = rawurlencode($lastMod['matchScore']);

	return "&cursor[]={$cursorVal}&cursor[]={$modId}&cursor[]={$score}";
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
