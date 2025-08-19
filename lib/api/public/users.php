<?php

if(empty($urlparts)) {
	fail(404);
}

switch($urlparts[0]) {
	case 'by-name':
		if(count($urlparts) !== 2)  fail(404);
		validateMethod('GET');

		$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
		if($limit === null) $limit = 10;
		else if(!$limit || $limit > 200)  fail(400, ['reason' => 'Invalid limit provided.']);

		$search = urldecode($urlparts[1]);
		if(strlen($search) === 0)  fail(400, ['reason' => 'Empty search phrase provided.']);

		$contributorsOnlyFilter = ($_GET['contributors-only'] ?? false) ? <<<SQL
			AND (
				EXISTS(SELECT * FROM assets a WHERE a.createdByUserId = users.userId) OR
				EXISTS(SELECT * FROM modTeamMembers tm WHERE tm.userId = users.userId)
			)
		SQL : '';

		//TODO(Rennorb) @correctness: This at least selects perfect matches, but something like order by levenshtein distance would be better.
		// Issue here is just performance, this needs a bit more thinking.
		$map = $con->getAssoc(<<<SQL
			SELECT HEX(hash), name
				FROM users
				WHERE name = ? $contributorsOnlyFilter
			UNION
				SELECT HEX(hash), name
				FROM users
				WHERE name LIKE ? $contributorsOnlyFilter
			LIMIT ?
		SQL, [$search, '%'.escapeStringForLikeQuery($search).'%', $limit]);

		good($map, JSON_FORCE_OBJECT);
}