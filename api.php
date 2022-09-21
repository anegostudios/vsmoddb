<?php
header('Content-Type: application/json');

//$basefileurl = "http://mods.vintagestory.at/files/";

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
		$rows = $con->getAll("select tagid, name, text, color from tag where assettypeid=2");
		$tags = array();
		$rows = sortTags(2, $rows);
		foreach ($rows as $row) {
			$tags[] = array(
				"tagid" => intval($row["tagid"]),
				"name" => $row['name'],
				"color" => $row["color"]
			);
		}
		good(array("statuscode" => 200, "gameversions" => $tags));
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
		$rows = $con->getAll("select userid, name from user");
		$authors = array();
		foreach ($rows as $row) {
			$authors[] = array(
				"userid" => intval($row["userid"]),
				"name" => $row['name']
			);
		}
		good(array("statuscode" => 200, "authors" => $authors));
		break;

	case "comments":
		$wheresql = '';
		$wherevalue = array();
		$limit = 'limit 100';

		if (intval($urlparts[1] ?? 0) > 0) {
			$wheresql = 'where assetid=?';
			$wherevalue = array(intval($urlparts[1]));
			$limit = '';
		}

		$rows = $con->getAll("select commentid, assetid, userid, text, created, lastmodified from comment $wheresql order by lastmodified $limit", $wherevalue);
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
		$wheresql = '';
		$wherevalue = array();
		$limit = 'limit 100';

		if (intval($urlparts[1] ?? 0) > 0) {
			$wheresql = 'where assetid=?';
			$wherevalue = array(intval($urlparts[1]));
			$limit = '';
		}

		$rows = $con->getAll("select changelogid, assetid, userid, text, created, lastmodified from changelog $wheresql order by lastmodified $limit", $wherevalue);
		$changelogs = array();
		foreach ($rows as $row) {
			$changelogs[] = array(
				"changelogid" => intval($row["changelogid"]),
				"assetid" => intval($row["assetid"]),
				"userid" => intval($row["userid"]),
				"text" => $row['text'],
				"created" => $row['created'],
				"lastmodified" => $row['lastmodified']
			);
		}
		good(array("statuscode" => 200, "changelogs" => $changelogs));
		break;
}


fail("400");


function fail($statuscode)
{
	exit(json_encode(array("statuscode" => $statuscode)));
}

function good($data)
{
	$data["statuscode"] = "200";
	exit(json_encode($data));
}

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
			`mod`.*
		from 
			`mod` 
			join asset on (`mod`.assetid = asset.assetid)
			join user on (`asset`.createdbyuserid = user.userid)
		where
			asset.statusid=2
			and modid=?
	", array($modid));

	if (empty($row)) fail("404");

	$rrows = $con->getAll("
		select 
			`release`.*,
			asset.*
		from 
			`release` 
			join asset on (asset.assetid = `release`.assetid)
		where modid=?
		order by release.created desc
	", array($row['modid']));

	$releases = array();
	foreach ($rrows as $release) {
		$tags = resolveTags($release["tagscached"]);
		$file = $con->getRow("select * from file where assetid=? limit 1", array($release['assetid']));

		$releases[] = array(
			"releaseid" => intval($release['releaseid']),
			"mainfile" => "files/asset/{$file['assetid']}/" . $file["filename"],
			"filename" => $file["filename"],
			"fileid" => $file['fileid'] ? intval($file['fileid']) : null,
			"downloads" => intval($file["downloads"]),
			"tags" => $tags,
			"modidstr" => $release['modidstr'],
			"modversion" => $release['modversion'],
			"created" => $release["created"]
		);
	}

	$srows = $con->getAll("
		select 
			fileid,
			assetid,
			filename,
			thumbnailfilename,
			created
		from 
			`file` 
		where assetid=?
	", array($modid));

	$screenshots = array();
	foreach ($srows as $screenshot) {
		$screenshots[] = array(
			"fileid" => intval($screenshot["fileid"]),
			"mainfile" => "files/asset/{$screenshot["assetid"]}/" . $screenshot["filename"],
			"filename" => $screenshot["filename"],
			"thumbnailfilename" => $screenshot["thumbnailfilename"],
			"created" => $screenshot["created"]
		);
	}

	$mod = array(
		"modid" => intval($row["modid"]),
		"assetid" => intval($row["assetid"]),
		"name" => $row['name'],
		"text" => $row['text'],
		"author" => $row['author'],
		"urlalias" => $row['urlalias'],
		"logo" => $row['logofilename'] ? "files/asset/{$row['assetid']}/" . $row['logofilename'] : null,
		"homepageurl" => $row['homepageurl'],
		"sourcecodeurl" => $row['sourcecodeurl'],
		"trailervideourl" => $row['trailervideourl'],
		"issuetrackerurl" => $row['issuetrackerurl'],
		"wikiurl" => $row['wikiurl'],
		"downloads" => intval($row['downloads']),
		"follows" => intval($row['follows']),
		"trendingpoints" => intval($row['trendingpoints']),
		"comments" => intval($row['comments']),
		"side" => $row['side'],
		"type" => $row['type'],
		"created" => $row['created'],
		"lastmodified" => $row['lastmodified'],
		"tags" => resolveTags($row['tagscached']),
		"releases" => $releases,
		"screenshots" => $screenshots
	);

	good(array("statuscode" => 200, "mod" => $mod));
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
		$orderDirection = $_GET['orderdirection'] === 'asc' ? $_GET['orderdirection'] : 'desc';
	}

	if (!empty($_GET["text"])) {
		$wheresql[] = "(asset.name like ? or asset.text like ?)";
		$wherevalues[] = "%" . $_GET["text"] . "%";
		$wherevalues[] = "%" . $_GET["text"] . "%";
	}

	if (!empty($_GET["tagids"])) {
		foreach ($_GET["tagids"] as $tagid) {
			$wheresql[] = "exists (select assettag.tagid from assettag where assettag.assetid=asset.assetid and assettag.tagid=?)";
			$wherevalues[] = $tagid;
		}
	}

	if (!empty($_GET["author"])) {
		$wheresql[] = "userid=?";
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
		$gamevers = array();
		foreach ($gvs as $gameversion) {
			$gamevers[] = intval($gameversion);
		}
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
			logofilename, 
			downloads, 
			follows,
			comments, 
			tagscached,
			group_concat(DISTINCT `release`.modidstr ORDER BY `release`.modidstr SEPARATOR ',') as modidstrs,
			user.name as author,
			`mod`.lastreleased,
			`mod`.trendingpoints
		from 
			`mod` 
			join asset on (`mod`.assetid = asset.assetid)
			join user on (`asset`.createdbyuserid = user.userid)
			left join `release` on `release`.modid = `mod`.modid
		" . (count($wheresql) ? "where " . implode(" and ", $wheresql) : "") . "
		group by `mod`.modid
		order by $orderBy $orderDirection
	", $wherevalues);
	$mods = array();
	foreach ($rows as $row) {

		$tags = resolveTags($row["tagscached"]);



		$mods[] = array(
			"modid" => intval($row['modid']),
			"assetid" => intval($row['assetid']),
			"downloads" => intval($row['downloads']),
			"follows" => intval($row['follows']),
			"trendingpoints" => intval($row['trendingpoints']),
			"comments" => intval($row['comments']),
			"name" => $row['name'],
			"modidstrs" => !empty($row['modidstrs']) ? explode(",", $row['modidstrs']) : array(),
			"author" => $row['author'],
			"urlalias" => $row['urlalias'],
			"side" => $row['side'],
			"type" => $row['type'],
			"logo" => $row['logofilename'] ? "files/asset/{$row['assetid']}/" . $row['logofilename'] : null,
			"tags" => $tags,
			"lastreleased" => $row['lastreleased']
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
