<?php

function listMod($modid)
{
	global $con;

	if ($modid != "" . intval($modid)) {
		$modid = $con->getOne("select modId from modReleases where identifier = ?", array($modid));
	}

	$row = $con->getRow(<<<SQL
		select 
			asset.assetId,
			asset.name,
			asset.text,
			asset.tagsCached,
			user.name as author,
			`mod`.*,
			logoFileExternal.cdnPath as logoCdnPathExternal,
			logoFileDb.cdnPath as logoCdnPathDb
		from 
			mods `mod`
			join assets asset on asset.assetId = `mod`.assetId
			join users user on user.userId = asset.createdByUserId
			left join files as logoFileExternal on (`mod`.embedLogoFileId = logoFileExternal.fileId)
			left join files as logoFileDb on (`mod`.cardLogoFileId = logoFileDb.fileId)
		where
			asset.statusId = 2
			and modId = ?
	SQL, array($modid));

	if (empty($row)) fail("404");

	$rrows = $con->getAll(<<<SQL
		select 
			r.*, a.text,
			GROUP_CONCAT(cgv.gameVersion SEPARATOR ';') as compatibleGameVersions
		from 
			modReleases r 
		join assets a on a.assetId = r.assetId
		left join modReleaseCompatibleGameVersions cgv on cgv.releaseId = r.releaseId
		where modId = ?
		group by r.releaseId
		order by r.created desc
	SQL, array($row['modId']));

	$releases = array();
	foreach ($rrows as $release) {
		$file = $con->getRow("select * from files where assetId = ? limit 1", array($release['assetId']));

		$releases[] = array(
			"releaseid"  => intval($release['releaseId']),
			"mainfile"   => empty($file) ? "" : formatCdnDownloadUrl($file),
			"filename"   => $file['name'] ?? '',
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
			files f
		left join fileImageData i on i.fileId = f.fileId
		where f.assetId = ? and f.fileId not in (?, ?)
	SQL, array($row['assetId'], $row['cardLogoFileId'] ?? 0, $row['embedLogoFileId'] ?? 0)); /* sql cant compare against null */

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
		"modid"           => intval($row["modId"]),
		"assetid"         => intval($row["assetId"]),
		"name"            => $row['name'],
		"text"            => $row['text'],
		"author"          => $row['author'],
		"urlalias"        => $row['urlAlias'],
		"logofilename"    => $logoUrlExternal, // @obsolete //NOTE(Rennorb): This is not the filename, but just the link again.
		"logofile"        => $logoUrlExternal,
		"logofiledb"      => $logoUrlDb,
		"homepageurl"     => $row['homepageUrl'],
		"sourcecodeurl"   => $row['sourceCodeUrl'],
		"trailervideourl" => $row['trailerVideoUrl'],
		"issuetrackerurl" => $row['issueTrackerUrl'],
		"wikiurl"         => $row['wikiUrl'],
		"downloads"       => intval($row['downloads']),
		"follows"         => intval($row['follows']),
		"trendingpoints"  => intval($row['trendingPoints']),
		"comments"        => intval($row['comments']),
		"side"            => $row['side'],
		"type"            => $row['type'],
		"created"         => $row['created'],
		"lastreleased"    => $row['lastReleased'],
		//NOTE(Rennorb): This field updates on download number changes and is therefore pretty much useless.
		// Removing it is however not a good idea becasue it's a public api, and changing it to work differently also isn't great because it would make the behaviour inconsistent between different tables.
		// We therefore simply keep it in this jank state for now, until a potential future breaking version.
		"lastmodified"    => $row['lastModified'],
		"tags"            => unwrapTagNames($row['tagsCached']),
		"releases"        => $releases,
		"screenshots"     => $screenshots
	);

	good(array("mod" => $mod));
}

function parseVersion($rawValue)
{
	return startsWith($rawValue, '-') ? intval(substr($rawValue, 1)) : compileSemanticVersion($rawValue);
}
function parsePrimaryVersion($rawValue)
{
	return startsWith($rawValue, '-') ? intval(substr($rawValue, 1)) & VERSION_MASK_PRIMARY : compilePrimaryVersion($rawValue);
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
			$wheresql[] = "exists (select 1 from modTags where modTags.modId = `mod`.modId and modTags.tagId = ?)";
			$wherevalues[] = $tagid;
		}
	}

	if (!empty($_GET["author"])) {
		$wheresql[] = "user.userId=?";
		$wherevalues[] = intval($_GET["author"]);
	}

	if (!empty($_GET["gameversion"])) {
		$wheresql[] = "exists (select 1 from modCompatibleMajorGameVersionsCached cmv where cmv.modId = `mod`.modId and cmv.majorGameVersion = ?)";
		$wherevalues[] = parsePrimaryVersion($_GET["gameversion"]);
	}


	$gvs = null;
	if (!empty($_GET["gv"]))                $gvs = [$_GET["gv"]];
	else if (!empty($_GET["gameversions"])) $gvs = $_GET["gameversions"];

	if ($gvs) {
		$gamevers = array_map("parseVersion", $gvs);
		// @security: parseVersion produces integer values which are sql inert.
		$wheresql[] = "exists (select 1 from modCompatibleGameVersionsCached cgv where cgv.modId = `mod`.modId and cgv.gameVersion in (" . implode(",", $gamevers) . "))";
	}


	$wheresql[] = "asset.statusId = 2";


	$rows = $con->getAll("
		select 
			asset.assetId, 
			`mod`.modId, 
			`mod`.side,
			`mod`.type,
			`mod`.urlAlias,
			asset.name,
			logofileExternal.cdnPath as logoCdnpathExternal,
			mod.downloads,
			follows,
			comments, 
			tagsCached,
			summary,
			group_concat(DISTINCT r.identifier ORDER BY r.identifier SEPARATOR ',') as modidstrs,
			user.name as author,
			`mod`.lastReleased,
			`mod`.trendingPoints
		from 
			mods `mod` 
			join assets asset on (`mod`.assetId = asset.assetId)
			join users user on (asset.createdByUserId = user.userId)
			left join modReleases r on r.modId = `mod`.modId
			left join files as logofileExternal on logofileExternal.fileId = mod.embedLogoFileId
		" . (count($wheresql) ? "where " . implode(" and ", $wheresql) : "") . "
		group by `mod`.modId
		order by $orderBy $orderDirection
	", $wherevalues);
	$mods = array();
	foreach ($rows as $row) {

		$tags = unwrapTagNames($row["tagsCached"]);



		$mods[] = array(
			"modid"          => intval($row['modId']),
			"assetid"        => intval($row['assetId']),
			"downloads"      => intval($row['downloads']),
			"follows"        => intval($row['follows']),
			"trendingpoints" => intval($row['trendingPoints']),
			"comments"       => intval($row['comments']),
			"name"           => $row['name'],
			"summary"        => $row['summary'],
			"modidstrs"      => !empty($row['modidstrs']) ? explode(",", $row['modidstrs']) : array(),
			"author"         => $row['author'],
			"urlalias"       => $row['urlAlias'],
			"side"           => $row['side'],
			"type"           => $row['type'],
			"logo"           => $row['logoCdnpathExternal'] ? formatCdnUrlFromCdnPath($row['logoCdnpathExternal']) : null,
			"tags"           => $tags,
			"lastreleased"   => $row['lastReleased']
		);
	}

	good(array("statuscode" => 200, "mods" => $mods));
}

function unwrapTagNames($tagsCached)
{
	// cached tags are stored as name,color,id\r\nname2,color2,id2 ... 
	// This gets the names
	return array_map(fn($s) => explode(',', $s)[0], explode("\r\n", trim($tagsCached)));
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
		from modReleases r
		join modReleaseCompatibleGameVersions cgv on cgv.releaseId = r.releaseId
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

		$file = $con->getRow('select * from files where assetId = ? limit 1', [$release['assetId']]);
		$outOfDateMods[$release['identifier']] = [
			'releaseid'  => intval($release['releaseId']),
			'mainfile'   => formatCdnDownloadUrl($file),
			'filename'   => $file['name'] ?? "",
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