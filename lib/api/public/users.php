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

		$search = $urlparts[1];
		if(strlen($search) === 0)  fail(400, ['reason' => 'Empty search phrase provided.']);

		$rows = $con->getAll("
			select
				name,
				substring(sha2(concat(userid, created), 512), 1, 20) as hash
			from user
			where name like ? limit ?
		", ['%'.$search.'%', $limit]);
		$map = [];
		foreach($rows as $row) {
			$map[$row['hash']] = $row['name'];
		}
		good($map, JSON_FORCE_OBJECT);
}