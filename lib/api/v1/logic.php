<?php

if (empty($urlparts)) {
	fail("404");
}

$action = $urlparts[0];

switch ($action) {
	case "tags":
		$rows = $con->getAll("select tagId, name, text, color from tags");
		$rows = sortTags(1, $rows);
		$tags = array();
		foreach ($rows as $row) {
			$tags[] = array(
				"tagid" => intval($row["tagId"]),
				"name" => $row['name'],
				"color" => '#'.str_pad($row["color"], 8, '0', STR_PAD_LEFT),
			);
		}
		good(array("statuscode" => 200, "tags" => $tags));
		break;
		
	case "gameversions":
		$versions = $con->getAll('select version from gameVersions order by version');
		foreach ($versions as &$version) {
			$v = intval($version['version']);
			$version = array(
				"tagid" => -$v,
				"name"  => formatSemanticVersion($v),
				"color" => '#CCCCCC',
			);
		}
		unset($version);
		good(array("statuscode" => 200, "gameversions" => $versions));
		break;

	case "mods":
		listMods();
		break;

	case "mod";
		if (empty($urlparts[1])) {
			fail("400");
		}
		listMod($urlparts[1]);
		break;

	case "authors":
		if (isset($_GET["name"])) {
			$rows = $con->getAll("select userId, name from users where (banneduntil is null or banneduntil < now()) and name like ? limit 10", "%".escapeStringForLikeQuery(substr($_GET["name"], 0, 20))."%");
		} else {		
			$rows = $con->getAll("select userId, name from users");
		}
		
		$authors = array_map(fn($row) => [
			"userid" => intval($row["userId"]),
			"name"   => $row['name'],
		], $rows);

		good(array("statuscode" => 200, "authors" => $authors));
		break;

	case "comments":
		$whereSql = '';
		$limitSql = 'limit 100';

		if (intval($urlparts[1] ?? 0) > 0) {
			$whereSql = 'AND assetId='.intval($urlparts[1]);
			$limitSql = '';
		}

		$rows = $con->getAll(<<<SQL
			select commentId, assetId, userId, text, created, lastModified
			from comments
			where !deleted $whereSql
			order by lastModified DESC $limitSql
		SQL);
		$comments = array();
		foreach ($rows as $row) {
			$comments[] = array(
				"commentid" => intval($row["commentId"]),
				"assetid" => intval($row["assetId"]),
				"userid" => intval($row["userId"]),
				"text" => $row['text'],
				"created" => $row['created'],
				"lastmodified" => $row['lastModified']
			);
		}
		good(array("statuscode" => 200, "comments" => $comments));
		break;

	case "changelogs":
		$error = 'This information was previously available, but is no longer distributed. Version 2 of the api might provide this information at some point in the future.';
		header('Cache-Control: max-age=604800, immutable');
		good(array(
			"changelogs" => [[
				'changelogid' => 0, 'assetid' => 0, 'userid' => 0, 'text' => $error, 'created' => '0000-00-00 00:00:00', 'lastmodified' => '0000-00-00 00:00:00',
			]],
			"reason" => $error,
		), "410");
		break;

	case "updates":
		if (empty($_GET["mods"])) {
			fail("400");
		}
		$modsQueryString = explode(',', $_GET["mods"]);
		$modidStrToVersionMap = array();
		foreach($modsQueryString as $modWithVersion) {
			$modVersionInfo = explode('@', $modWithVersion);
			if (count($modVersionInfo) != 2) {
				fail("400");
			}
			[$modidStr, $modVersion] = $modVersionInfo;
			$modidStrToVersionMap[$modidStr] = compileSemanticVersion($modVersion);
		}

		listOutOfDateMods($modidStrToVersionMap);
		break;
}

fail("400");
