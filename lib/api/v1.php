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
		$rows = $con->getAll("select tagid, name, text, color from tag where assettypeid=1");
		$rows = sortTags(1, $rows);
		$tags = array();
		foreach ($rows as $row) {
			$tags[] = array(
				"tagid" => intval($row["tagid"]),
				"name" => $row['name'],
				"color" => $row["color"]
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
			$rows = $con->getAll("select userid, name from user where (banneduntil is null or banneduntil < now()) and name like ? limit 10", "%".escapeStringForLikeQuery(substr($_GET["name"], 0, 20))."%");
		} else {		
			$rows = $con->getAll("select userid, name from user");
		}
		
		$authors = array_map(fn($row) => [
			"userid" => intval($row["userid"]),
			"name"   => $row['name'],
		], $rows);

		good(array("statuscode" => 200, "authors" => $authors));
		break;

	case "comments":
		$wheresql = '';
		$limit = 'limit 100';

		if (intval($urlparts[1] ?? 0) > 0) {
			$wheresql = 'AND assetid='.intval($urlparts[1]);
			$limit = '';
		}

		$rows = $con->getAll("
			select commentid, assetid, userid, text, created, lastmodified 
			from comment where !deleted $wheresql 
			order by lastmodified DESC $limit");
		$comments = array();
		foreach ($rows as $row) {
			$comments[] = array(
				"commentid" => intval($row["commentid"]),
				"assetid" => intval($row["assetid"]),
				"userid" => intval($row["userid"]),
				"text" => $row['text'],
				"created" => $row['created'],
				"lastmodified" => $row['lastmodified']
			);
		}
		good(array("statuscode" => 200, "comments" => $comments));
		break;

	case "changelogs":
		$error = 'This information was previously available, but is no longer distributed. Version 2 of the api might provide this information at some point in the future.';
		header('Cache-Control: no-cache, no-store');
		header('Clear-Site-Data: "cache"');
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
		$modid = $con->getOne("select modid from `release` where `release`.modidstr=?", array($modid));
	}

	$row = $con->getRow("select 
			asset.assetid, 
			asset.name,
			asset.text,
			asset.tagscached,
			user.name as author,
			`mod`.*,
			logofile_external.cdnpath as logocdnpath_external,
			logofile_db.cdnpath as logocdnpath_db
		from 
			`mod` 
			join asset on (`mod`.assetid = asset.assetid)
			join user on (`asset`.createdbyuserid = user.userid)
			left join file as logofile_external on (`mod`.embedlogofileid = logofile_external.fileid)
			left join file as logofile_db on (`mod`.cardlogofileid = logofile_db.fileid)
		where
			asset.statusid=2
			and modid=?
	", array($modid));

	if (empty($row)) fail("404");

	$rrows = $con->getAll("
		select 
			`release`.*,
			asset.*,
			GROUP_CONCAT(cgv.gameVersion SEPARATOR ';') as compatibleGameVersions
		from 
			`release` 
			join asset on (asset.assetid = `release`.assetid)
			join ModReleaseCompatibleGameVersions cgv on cgv.releaseId = `release`.releaseid
		where modid=?
		group by `release`.releaseid
		order by release.created desc
	", array($row['modid']));

	$releases = array();
	foreach ($rrows as $release) {
		$file = $con->getRow("select * from file where assetid=? limit 1", array($release['assetid']));

		$releases[] = array(
			"releaseid"  => intval($release['releaseid']),
			"mainfile"   => empty($file) ? "" : formatCdnDownloadUrl($file),
			"filename"   => empty($file) ? 0 : $file["filename"],
			"fileid"     => isset($file['fileid']) ? intval($file['fileid']) : null,
			"downloads"  => empty($file) ? 0 : intval($file["downloads"]),
			"tags"       => array_map(fn($s) => formatSemanticVersion(intval($s)), explode(';', $release["compatibleGameVersions"])),
			"modidstr"   => $release['modidstr'],
			"modversion" => formatSemanticVersion(intval($release['modversion'])),
			"created"    => $release['created'],
			"changelog"  => $release['text'],
		);
	}

	$srows = $con->getAll("
		select 
			fileid,
			assetid,
			filename,
			hasthumbnail,
			cdnpath,
			created
		from 
			`file` 
		where assetid = ? and fileid not in (?, ?)
	", array($modid, $row['cardlogofileid'] ?? 0, $row['embedlogofileid'] ?? 0)); /* sql cant compare against null */

	$screenshots = array();
	foreach ($srows as $screenshot) {
		$screenshots[] = array(
			"fileid"            => intval($screenshot["fileid"]),
			"mainfile"          => formatCdnUrl($screenshot),
			"filename"          => $screenshot["filename"],
			"thumbnailfilename" => $screenshot["hasthumbnail"] ? formatCdnUrl($screenshot, '_55_60') : null,
			"created"           => $screenshot["created"]
		);
	}

	$logourlExternal = $row['logocdnpath_external'] ? formatCdnUrlFromCdnPath($row['logocdnpath_external']) : null;
	$logourlDb = $row['logocdnpath_db'] ? formatCdnUrlFromCdnPath($row['logocdnpath_db']) : null;
	$mod = array(
		"modid"           => intval($row["modid"]),
		"assetid"         => intval($row["assetid"]),
		"name"            => $row['name'],
		"text"            => $row['text'],
		"author"          => $row['author'],
		"urlalias"        => $row['urlalias'],
		"logofilename"    => $logourlExternal, // @obsolete //NOTE(Rennorb): This is not the filename, but just the link again.
		"logofile"        => $logourlExternal,
		"logofiledb"      => $logourlDb,
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
			$wheresql[] = "exists (select assettag.tagid from assettag where assettag.assetid=asset.assetid and assettag.tagid=?)";
			$wherevalues[] = $tagid;
		}
	}

	if (!empty($_GET["author"])) {
		$wheresql[] = "user.userid=?";
		$wherevalues[] = intval($_GET["author"]);
	}

	if (!empty($_GET["gameversion"])) {
		$wheresql[] = "exists (select assettag.tagid from assettag where assettag.assetid in (select assetid from `release` where `mod`.modid =`release`.modid) and assettag.tagid=?)";
		$wherevalues[] = intval($_GET["gameversion"]);
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
		$wheresql[] = "exists (select 1 from modversioncached where `mod`.modid =`modversioncached`.modid and modversioncached.tagid in (" . implode(",", $gamevers) . "))";
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
			logofile_external.cdnpath as logocdnpath_external,
			mod.downloads,
			follows,
			comments, 
			tagscached,
			summary,
			group_concat(DISTINCT `release`.modidstr ORDER BY `release`.modidstr SEPARATOR ',') as modidstrs,
			user.name as author,
			`mod`.lastreleased,
			`mod`.trendingpoints
		from 
			`mod` 
			join asset on (`mod`.assetid = asset.assetid)
			join user on (`asset`.createdbyuserid = user.userid)
			left join `release` on `release`.modid = `mod`.modid
			left join file as logofile_external on mod.embedlogofileid = logofile_external.fileid
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
			"logo"           => $row['logocdnpath_external'] ? formatCdnUrlFromCdnPath($row['logocdnpath_external']) : null,
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
			r.modid,
			r.releaseid,
			r.modidstr,
			r.modversion,
			r.created,
			r.assetid,
			GROUP_CONCAT(cgv.gameVersion SEPARATOR ';') as compatibleGameVersions
		from `release` r
		join ModReleaseCompatibleGameVersions cgv on cgv.releaseId = r.releaseid
		where r.modidstr in ($modIdStrParams)
		group by r.releaseid
		order by r.modidstr, r.modversion desc
	", $modIdStrs);

	$outOfDateMods = [];
	$lastModidstr = null;
	foreach($releases as $release) {
		// The list is ordered by modversion coming from the db, and releases with the same modidstr are grouped 
		// (not grouped in the sql sense, grouped as in right next to each other).
		// Every time that field changes we know we are looking at the latest version of that modidstr.
		if($lastModidstr === $release['modidstr'])  continue;
		$lastModidstr = $release['modidstr'];

		if($currentModVersions[$release['modidstr']] >= $release['modversion'])  continue; // already has the latest version

		$file = $con->getRow('select * from file where assetid = ? limit 1', [$release['assetid']]);
		$outOfDateMods[$release['modidstr']] = [
			'releaseid'  => intval($release['releaseid']),
			'mainfile'   => formatCdnDownloadUrl($file),
			'filename'   => $file['filename'],
			'fileid'     => $file['fileid'] ? intval($file['fileid']) : null,
			'downloads'  => intval($file['downloads']),
			'tags'       => array_map(fn($s) => formatSemanticVersion(intval($s)), explode(';', $release["compatibleGameVersions"])),
			'modidstr'   => $release['modidstr'],
			'modversion' => formatSemanticVersion(intval($release['modversion'])),
			'created'    => $release['created'],
		];
	}

	good(array("statuscode" => 200, "updates" => $outOfDateMods));
}
