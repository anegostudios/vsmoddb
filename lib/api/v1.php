<?php

header('Content-Type: application/json');

/** @param string $statuscode */
function fail($statuscode)
{
	exit(json_encode(array("statuscode" => $statuscode)));
}

/** @param array $data */
function good($data)
{
	$data["statuscode"] = "200";
	exit(json_encode($data));
}

if (empty($urlparts)) {
	fail("404");
}

$action = $urlparts[0];

switch ($action) {
	case "tags":
		$rows = $con->getAll("select tagId, name, text, color from Tags");
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
		$versions = $con->getAll('select version from GameVersions order by version');
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
			$rows = $con->getAll("select userId, name from Users where (banneduntil is null or banneduntil < now()) and name like ? limit 10", "%".escapeStringForLikeQuery(substr($_GET["name"], 0, 20))."%");
		} else {		
			$rows = $con->getAll("select userId, name from Users");
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
			from Comment
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
		exit(json_encode(array(
			"statuscode" => "410",
			"changelogs" => [
				'changelogid' => 0, 'assetid' => 0, 'userid' => 0, 'text' => $error, 'created' => '0000-00-00 00:00:00', 'lastmodified' => '0000-00-00 00:00:00',
			],
			"reason" => $error,
		)));
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


function listMod($modid)
{
	global $con;

	if ($modid != "" . intval($modid)) {
		$modid = $con->getOne("select modId from ModReleases where identifier = ?", array($modid));
	}

	$row = $con->getRow(<<<SQL
		select 
			asset.assetid, 
			asset.name,
			asset.text,
			asset.tagscached,
			user.name as author,
			`mod`.*,
			logoFileExternal.cdnPath as logoCdnPathExternal,
			logoFileDb.cdnPath as logoCdnPathDb
		from 
			`mod` 
			join asset on (`mod`.assetid = asset.assetid)
			join Users user on (`asset`.createdbyuserid = user.userId)
			left join Files as logoFileExternal on (`mod`.embedlogofileid = logoFileExternal.fileId)
			left join Files as logoFileDb on (`mod`.cardlogofileid = logoFileDb.fileId)
		where
			asset.statusid=2
			and modid = ?
	SQL, array($modid));

	if (empty($row)) fail("404");

	$rrows = $con->getAll(<<<SQL
		select 
			r.*,
			asset.*,
			GROUP_CONCAT(cgv.gameVersion SEPARATOR ';') as compatibleGameVersions
		from 
			ModReleases r 
			join asset on (asset.assetid = r.assetId)
			left join ModReleaseCompatibleGameVersions cgv on cgv.releaseId = r.releaseId
		where modid = ?
		group by r.releaseId
		order by r.created desc
	SQL, array($row['modid']));

	$releases = array();
	foreach ($rrows as $release) {
		$file = $con->getRow("select * from Files where assetId = ? limit 1", array($release['assetId']));

		$releases[] = array(
			"releaseid"  => intval($release['releaseId']),
			"mainfile"   => empty($file) ? "" : formatCdnDownloadUrl($file),
			"filename"   => empty($file) ? 0 : $file["name"],
			"fileid"     => isset($file['fileId']) ? intval($file['fileId']) : null,
			"downloads"  => empty($file) ? 0 : intval($file["downloads"]),
			"tags"       => array_map(fn($s) => formatSemanticVersion(intval($s)), explode(';', $release["compatibleGameVersions"])),
			"modidstr"   => $release['identifier'],
			"modversion" => formatSemanticVersion(intval($release['version'])),
			"created"    => $release['created'],
			"changelog"  => $release['text'],
		);
	}

	$screenshots = $con->getAll(<<<SQL
		select 
			f.fileId,
			f.assetId,
			f.name,
			i.hasThumbnail,
			f.cdnPath,
			f.created
		from 
			Files f
		left join FileImageData i on i.fileId = f.fileId
		where f.assetId = ? and f.fileId not in (?, ?)
	SQL, array($row['assetid'], $row['cardlogofileid'] ?? 0, $row['embedlogofileid'] ?? 0)); /* sql cant compare against null */

	$screenshots = array();
	foreach ($screenshots as $screenshot) {
		$screenshots[] = array(
			"fileid"            => intval($screenshot["fileId"]),
			"mainfile"          => formatCdnUrl($screenshot),
			"filename"          => $screenshot["name"],
			"thumbnailfilename" => $screenshot["hasThumbnail"] ? formatCdnUrl($screenshot, '_55_60') : null,
			"created"           => $screenshot["created"]
		);
	}

	$logoUrlExternal = $row['logoCdnPathExternal'] ? formatCdnUrlFromCdnPath($row['logoCdnPathExternal']) : null;
	$logoUrlDb = $row['logoCdnPathDb'] ? formatCdnUrlFromCdnPath($row['logoCdnPathDb']) : null;
	$mod = array(
		"modid"           => intval($row["modid"]),
		"assetid"         => intval($row["assetid"]),
		"name"            => $row['name'],
		"text"            => $row['text'],
		"author"          => $row['author'],
		"urlalias"        => $row['urlalias'],
		"logofilename"    => $logoUrlExternal, // @obsolete //NOTE(Rennorb): This is not the filename, but just the link again.
		"logofile"        => $logoUrlExternal,
		"logofiledb"      => $logoUrlDb,
		"homepageurl"     => $row['homepageurl'],
		"sourcecodeurl"   => $row['sourcecodeurl'],
		"trailervideourl" => $row['trailervideourl'],
		"issuetrackerurl" => $row['issuetrackerurl'],
		"wikiurl"         => $row['wikiurl'],
		"downloads"       => intval($row['downloads']),
		"follows"         => intval($row['follows']),
		"trendingpoints"  => intval($row['trendingpoints']),
		"comments"        => intval($row['comments']),
		"side"            => $row['side'],
		"type"            => $row['type'],
		"created"         => $row['created'],
		"lastreleased"    => $row['lastreleased'],
		//NOTE(Rennorb): This field updates on download number changes and is therefore pretty much useless.
		// Removing it is however not a good idea becasue it's a public api, and changing it to work differently also isn't great because it would make the behaviour inconsistent between different tables.
		// We therefore simply keep it in this jank state for now, until a potential future breaking version.
		"lastmodified"    => $row['lastmodified'],
		"tags"            => resolveTags($row['tagscached']),
		"releases"        => $releases,
		"screenshots"     => $screenshots
	);

	good(array("mod" => $mod));
}

function listMods()
{
	global $con;

	$wheresql = array();
	$wherevalues = array();
	$orderBy = 'asset.created';
	$orderDirection = 'desc';
	$allowedOrderBy = ['asset.created', 'lastreleased', 'downloads', 'follows', 'comments', 'trendingpoints'];

	if (!empty($_GET["orderby"]) && in_array($_GET['orderby'], $allowedOrderBy, true)) {
		$orderBy = $_GET['orderby'];
	}

	if (!empty($_GET['orderdirection'])) {
		$orderDirection = $_GET['orderdirection'] === 'asc' ? 'asc' : 'desc';
	}

	if (!empty($_GET["text"])) {
		$wheresql[] = "(asset.name like ? or asset.text like ?)";
		$wherevalues[] = "%" . escapeStringForLikeQuery($_GET["text"]) . "%";
		$wherevalues[] = "%" . escapeStringForLikeQuery($_GET["text"]) . "%";
	}

	if (!empty($_GET["tagids"])) {
		foreach ($_GET["tagids"] as $tagid) {
			$wheresql[] = "exists (select 1 from ModTags where ModTags.modId = `mod`.modid and ModTags.tagId = ?)";
			$wherevalues[] = $tagid;
		}
	}

	if (!empty($_GET["author"])) {
		$wheresql[] = "user.userId=?";
		$wherevalues[] = intval($_GET["author"]);
	}

	if (!empty($_GET["gameversion"])) {
		$wheresql[] = "exists (select 1 from ModCompatibleMajorGameVersionsCached cmv where cmv.modId = `mod`.modid and cmv.majorGameVersion = ?)";
		$wherevalues[] = intval($_GET["gameversion"]) & VERSION_MASK_PRIMARY;
	}


	$gvs = null;
	if (!empty($_GET["gameversions"])) {
		$gvs = $_GET["gameversions"];
	}
	if (!empty($_GET["gv"])) {
		$gvs = array($_GET["gv"]);
	}

	if ($gvs) {
		$gamevers = array_map("intval", $gvs);
		$wheresql[] = "exists (select 1 from ModCompatibleGameVersionsCached cgv where cgv.modId = `mod`.modid and cgv.gameVersion in (" . implode(",", $gamevers) . "))";
	}


	$wheresql[] = "asset.statusid=2";


	$rows = $con->getAll("
		select 
			asset.assetid, 
			`mod`.modid, 
			`mod`.side,
			`mod`.type,
			`mod`.urlalias,
			asset.name,
			logofileExternal.cdnPath as logoCdnpathExternal,
			mod.downloads,
			follows,
			comments, 
			tagscached,
			summary,
			group_concat(DISTINCT r.identifier ORDER BY r.identifier SEPARATOR ',') as modidstrs,
			user.name as author,
			`mod`.lastreleased,
			`mod`.trendingpoints
		from 
			`mod` 
			join asset on (`mod`.assetid = asset.assetid)
			join Users user on (`asset`.createdbyuserid = user.userId)
			left join ModReleases r on r.modId = `mod`.modid
			left join Files as logofileExternal on logofileExternal.fileId = mod.embedlogofileid
		" . (count($wheresql) ? "where " . implode(" and ", $wheresql) : "") . "
		group by `mod`.modid
		order by $orderBy $orderDirection
	", $wherevalues);
	$mods = array();
	foreach ($rows as $row) {

		$tags = resolveTags($row["tagscached"]);



		$mods[] = array(
			"modid"          => intval($row['modid']),
			"assetid"        => intval($row['assetid']),
			"downloads"      => intval($row['downloads']),
			"follows"        => intval($row['follows']),
			"trendingpoints" => intval($row['trendingpoints']),
			"comments"       => intval($row['comments']),
			"name"           => $row['name'],
			"summary"        => $row['summary'],
			"modidstrs"      => !empty($row['modidstrs']) ? explode(",", $row['modidstrs']) : array(),
			"author"         => $row['author'],
			"urlalias"       => $row['urlalias'],
			"side"           => $row['side'],
			"type"           => $row['type'],
			"logo"           => $row['logoCdnpathExternal'] ? formatCdnUrlFromCdnPath($row['logoCdnpathExternal']) : null,
			"tags"           => $tags,
			"lastreleased"   => $row['lastreleased']
		);
	}

	good(array("statuscode" => 200, "mods" => $mods));
}

function resolveTags($tagscached)
{
	$tags = array();
	$tagscached = trim($tagscached);
	if (!empty($tagscached)) {
		$tagdata = explode("\r\n", $tagscached);
		foreach ($tagdata as $tagrow) {
			$parts = explode(",", $tagrow);
			$tags[] = $parts[0];
		}
	}

	return $tags;
}

/** Echo a (modidstr -> (release object)) map for each modidstr with a release thats newer than the version specified in currentModVersions.
 * @param int[] $currentModVersions
 */
function listOutOfDateMods($currentModVersions) {
	global $con;

	$modIdStrs = array_keys($currentModVersions);
	$modIdStrParams = implode(",", array_fill(0, count($modIdStrs), "?"));

	$releases = $con->getAll("
		select
			r.modId,
			r.releaseId,
			r.identifier,
			r.version,
			r.created,
			r.assetId,
			GROUP_CONCAT(cgv.gameVersion SEPARATOR ';') as compatibleGameVersions
		from ModReleases r
		join ModReleaseCompatibleGameVersions cgv on cgv.releaseId = r.releaseId
		where r.identifier in ($modIdStrParams)
		group by r.releaseId
		order by r.identifier, r.version desc
	", $modIdStrs);

	$outOfDateMods = [];
	$lastIdentifier = null;
	foreach($releases as $release) {
		// The list is ordered by modversion coming from the db, and releases with the same identifier are grouped 
		// (not grouped in the sql sense, grouped as in right next to each other).
		// Every time that field changes we know we are looking at the latest version of that identifier.
		if($lastIdentifier === $release['identifier'])  continue;
		$lastIdentifier = $release['identifier'];

		if($currentModVersions[$release['identifier']] >= $release['version'])  continue; // already has the latest version

		$file = $con->getRow('select * from Files where assetId = ? limit 1', [$release['assetId']]);
		$outOfDateMods[$release['identifier']] = [
			'releaseid'  => intval($release['releaseId']),
			'mainfile'   => formatCdnDownloadUrl($file),
			'filename'   => $file['name'],
			'fileid'     => $file['fileId'] ? intval($file['fileId']) : null,
			'downloads'  => intval($file['downloads']),
			'tags'       => array_map(fn($s) => formatSemanticVersion(intval($s)), explode(';', $release["compatibleGameVersions"])),
			'modidstr'   => $release['identifier'],
			'modversion' => formatSemanticVersion(intval($release['version'])),
			'created'    => $release['created'],
		];
	}

	good(array("statuscode" => 200, "updates" => $outOfDateMods));
}
