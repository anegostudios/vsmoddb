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
		$modId = $urlparts[1];
		if (empty($modId)) {
			fail("400");
		}
		if ($urlparts[2] == "releases") {
			if ($_SERVER['REQUEST_METHOD'] != "POST") fail("400");
			createRelease($modId);
		}
		listMod($modId);
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

		$rows = $con->getAll("select commentid, assetid, userid, text, created, lastmodified from comment $wheresql order by lastmodified DESC $limit", $wherevalue);
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
			$modidStrToVersionMap[$modidStr] = $modVersion;
		}

		listOutOfDateMods($modidStrToVersionMap);
		break;
}


fail("400");


function fail($statuscode)
{
	exit(json_encode(array("statuscode" => $statuscode)));
}

function failWithMsg($statuscode, $message)
{
	exit(json_encode(array("statuscode" => $statuscode, "message" => $message)));
}

function good($data)
{
	$data["statuscode"] = "200";
	exit(json_encode($data));
}

function createRelease($modId)
{
	global $con, $user;
	$assettypeid = 2;

	if (empty($user)) fail("401");
	if (!$user['roleid']) fail("403");

	if ($modId != "" . intval($modId)) {
		$modId = $con->getOne("select modid from `release` where `release`.modidstr=?", array($modId));
	}

	$modAuthor = $con->getOne("select
			asset.createdbyuserid
		from
			`mod`
			join asset on (`mod`.assetid = asset.assetid)
		where
			modid=?
	", array($modId));
	if (empty($modAuthor)) fail("404");
	if ($user["userid"] != $modAuthor) fail("403");
	if ($_SERVER["HTTP_CONTENT_TYPE"] != "multipart/form-data") fail("400");
	if (empty($_POST["json"])) fail("400");

	$data = json_decode($_POST["json"]);
	$fileObject = $_FILES["file"];
	$fileSize = $fileObject["size"];
	if (empty($fileObject)) fail("400");
	if ($fileSize > file_upload_max_size()) failWithMsg("400", "File size is too big");

	$res = processFileUpload($fileObject, $assettypeid, $modId);
	if ($res["status"] == "error") failWithMsg("400", $res["errormessage"]);
	$uploadedFile = $con->getRow("select * from file where assetid is null and assettypeid=? and userid=?", array($assettypeid, $user['userid']));
	if (!$uploadedFile) fail("500");

	$filepath = "tmp/{$user['userid']}/{$fileObject['filename']}";
	$modinfo = getModInfo($filepath);
	if ($modinfo['modparse'] != 'ok') failWithMsg("400", "Mod id or version are incorrect");
	$modidstr = $modinfo['modid'];
	$modversion = $modinfo['modversion'];
	if (preg_match("/[^0-9a-zA-Z\-_]+/", $modidstr)) failWithMsg("400", "Mod id is incorrect");
	if (!preg_match("/^[0-9]{1,5}\.[0-9]{1,4}\.[0-9]{1,4}(-(rc|pre|dev)\.[0-9]{1,4})?$/", $modversion)) failWithMsg("400", "Mod version is incorrect");
	$releaseIdDupl = $con->getOne("select assetid from `release` where modidstr=? and assetid!=?", array($modidstr, 0));
	if ($releaseIdDupl) fail("400");
	$idIsTaken = $con->getOne("select count() from `asset` join `release` on (asset.assetid = `release`.assetid) where modidstr=? and createdbyuserid!=?", array($modidstr, $user['userid']));
	if ($idIsTaken) failWithMsg("400", "Mod ID taken");
	if ($modidstr == "game" || $modidstr == "creative" || $modidstr == "survival") fail("400");

	$assetId = insert("asset");
	$releaseId = insert("release");
	$assetData = array(
		"createdbyuserid" => $user["userid"],
		"editedbyuserid" => $user["userid"],
		"assettypeid" => $assettypeid,
		"numsaved" => 0
	);
	$releaseData = array(
		"assetid" => $assetId,
		"modid" => $modId,
		"modidstr" => $modidstr,
		"modversion" => $modversion,
		"detectedmodidstr" => $modidstr,
		"detailtext" => $data->description
	);
	update("asset", $assetId, $assetData);
	update("release", $releaseId, $releaseData);
	$con->Execute("update `mod` set lastreleased=now() where modid=?", array($modId));

	foreach ($data->versions as $gameVersion) {
		$tag = $con->getRow("select * from tag where name=? and tagtypeid=1", array($gameVersion));
		$tagId = $tag["id"];

		$assettagid = $con->getOne("select assettagid from assettag where assetid=? and tagid=?", array($assetId, $tagId));
		if (!$assettagid) {
			$assettagid = insert("assettag");
			update("assettag", $assettagid, array("assetid" => $assetId, "tagid" => $tagId));
		}

		unset($data->versions[$gameVersion]);
	}

	$followersIds = $con->getCol("select userid from `follow` where modid=?", array($modId));
	foreach ($followersIds as $userId) {
		$con->Execute("insert into notification (userid, type, recordid, created) values (?,?,?, now())", array($userId, 'newrelease', $modId));
	}
	updateGameVersionsCached($modId);

	good($releaseData);
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
		"logofilename" => $row['logofilename'] ? "files/asset/{$row['assetid']}/" . $row['logofilename'] : null, // deprecated
		"logofile" => $row['logofilename'] ? "files/asset/{$row['assetid']}/" . $row['logofilename'] : null,
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

function listOutOfDateMods($modidStrToVersionMap) {
	global $con;

	$modIdStrs = array_keys($modidStrToVersionMap);
	$modIdStrParams = implode(",", array_fill(0, count($modIdStrs), "?"));

	$outOfDateMods = array();
	$mods = $con->getAll("
		select
			`release`.modid,
			`release`.releaseid,
			`release`.modidstr,
			`release`.modversion,
			`release`.created,
			`release`.assetid,
			asset.tagscached
		from
		`release`
			join asset on (asset.assetid = `release`.assetid)
		where `release`.modidstr in ($modIdStrParams)
		order by `release`.modversion desc
	", $modIdStrs);

	$modidToReleasesMap = arrayGroupBy($mods, "modid");

	$modidToVersionMap = convertVersionMap($modidToReleasesMap, $modidStrToVersionMap);

	foreach($modidToReleasesMap as $modid => $modReleases) {
		$latestRelease = getLatestRelease($modid, $modReleases, $modidToVersionMap, $con);
		if ($latestRelease == null) {
			continue;
		}
		$outOfDateMods[$latestRelease['modidstr']] = $latestRelease;
	}

	good(array("statuscode" => 200, "updates" => $outOfDateMods));
}

function getLatestRelease($modid, $modReleases, $modidToVersionMap, $con) {
	usort($modReleases, "compareVersions");
	$release = $modReleases[0];

	$latestVersion = $release['modversion'];
	$queryStringVersion = $modidToVersionMap[$modid];
	if (cmpVersion($queryStringVersion, $latestVersion) != 1 || $queryStringVersion == $latestVersion) {
		return;
	}

	$tags = resolveTags($release["tagscached"]);
	$file = $con->getRow("select * from file where assetid=? limit 1", array($release['assetid']));

	return array(
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

function arrayGroupBy($array, $key) {
    $return = array();
    foreach($array as $val) {
        $return[$val[$key]][] = $val;
    }
    return $return;
}

function compareVersions($releaseA, $releaseB) {
	$isversion = splitVersion($releaseA['modversion']);
	$reqversion = splitVersion($releaseB['modversion']);

	$cnt = max($isversion, $reqversion);

	for ($i = 0; $i < $cnt; $i++) {
		if ($i >= count($isversion)) return 1;

		if (intval($isversion[$i]) > intval($reqversion[$i])) return -1;
		if (intval($isversion[$i]) < intval($reqversion[$i])) return 1;
	}

	return 0;
}

// Convert ModIdStr VersionMap => ModId VersionMap
function convertVersionMap($modidToReleasesMap, $modidStrToVersionMap) {
	$modidToVersionMap = array();
	$modidToModidStrMap = array_map("listModidStrsPerModid", $modidToReleasesMap);
	foreach($modidToModidStrMap as $modid => $modidStrs) {
		foreach($modidStrs as $modidStr) {
			if (array_key_exists($modidStr, $modidStrToVersionMap)) {
				$modidToVersionMap[$modid] = $modidStrToVersionMap[$modidStr];
			}
		}
	}

	return $modidToVersionMap;
}

function listModidStrsPerModid($releases) {
	$modIdStrs = array_map("getModidStrs", $releases);
	return array_unique($modIdStrs);
}

function getModidStrs($release) {
	return $release['modidstr'];
}