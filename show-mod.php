<?php

$assetid = $urlparts[2] ?? 0;

if (!$assetid) {
	showErrorPage(404);
}

$asset = $con->getRow("
	select 
		asset.*, 
		`mod`.*,
		logofile.cdnpath as logourl,
		logofile.created < '".SQL_MOD_CARD_TRANSITION_DATE."' as legacylogo,
		createduser.userid as createduserid,
		createduser.created as createduserjoindate,
		createduser.name as createdusername,
		editeduser.userid as editeduserid,
		editeduser.name as editedusername,
		status.code as statuscode
	from 
		asset 
		join `mod` on asset.assetid=`mod`.assetid
		left join user as createduser on asset.createdbyuserid = createduser.userid
		left join user as editeduser on asset.editedbyuserid = editeduser.userid
		left join status on asset.statusid = status.statusid
		left join file as logofile on mod.embedlogofileid = logofile.fileid
	where
		asset.assetid = ?
", array($assetid));

if (!$asset) showErrorPage(HTTP_NOT_FOUND);

$teammembers = $con->getAll("
	select
		user.userid,
		user.name,
		substring(sha2(concat(user.userid, user.created), 512), 1, 20) as usertoken
	from 
		teammember
		join user on teammember.userid = user.userid 
	where 
		teammember.modid = ?
	", array($asset['modid']));
$view->assign("teammembers", $teammembers);

$createdusertoken = getUserHash($asset['createduserid'], $asset['createduserjoindate']);
$view->assign("createdusertoken", $createdusertoken);

$files = $con->getAll("select * from file where assetid = ? and fileid not in (?, ?)", 
	array($assetid, $asset['cardlogofileid'] ?? 0, $asset['embedlogofileid'] ?? 0));  /* sql cant compare against null */

//NOTE(Rennorb): There was a time where we rescaled images for logos. We no longer do that, but in ~140 cases there are still two images for the logo: the actual logo image, and the original one that was uploaded.
// Since we don't show the logo in the slideshow anymore, we also need to remove that second file that got uploaded, without removing it from the database so it stays downloadable for the mod author until they replace it.
// Here is a sql query to get a list of such mods:
/*
	select modid, urlalias, user.name from `mod`
	join file f on f.fileid = `mod`.cardlogofileid
	join file f2 on f2.cdnpath = concat(substr(f.cdnpath, 1, length(f.cdnpath) - 12), substr(f.cdnpath, -4))
	join asset on `mod`.assetid = asset.assetid
	join user on user.userid = asset.createdbyuserid;
*/
if($asset['legacylogo']) {
	splitOffExtension($asset['logourl'], $base, $ext);
	if(endsWith($base, '_480_320')) {
		$legacyLogoPath = substr($base, 0, strlen($base) - 8).'.'.$ext;
		foreach ($files as $k => $file) {
			if($file['cdnpath'] === $legacyLogoPath) {
				unset($files[$k]);
				break;
			}
		}
	}
}

if(!empty($asset['logourl'])) {
	$asset['logourl'] = formatCdnUrlFromCdnPath($asset['logourl']);
}

foreach ($files as &$file) {
	$file["created"] = date("M jS Y, H:i:s", strtotime($file["created"]));
	$file["ext"] = substr($file["filename"], strrpos($file["filename"], ".")+1); // no clue why pathinfo doesnt work here
	$file["url"] = formatCdnUrl($file);
}
unset($file);

$view->assign("files", $files);

$deletedFilter = canModerate(null, $user) ? '' : 'and comment.deleted = 0';
$comments = $con->getAll("
	select 
		comment.*,
		user.name as username,
		user.roleid as roleid,
		substring(sha2(concat(user.userid, user.created), 512), 1, 20) as usertoken,
		ifnull(user.banneduntil >= now(), 0) as `isbanned`,
		role.code as rolecode,
		role.name as rolename
	from 
		comment 
		join user on (comment.userid = user.userid)
		left join role on (user.roleid = role.roleid)
	where assetid=? $deletedFilter
	order by comment.created desc
", array($assetid));

foreach ($comments as $idx => $comment) {
	if ($asset['createduserid'] == $comment["userid"]) {
		$comments[$idx]["flaircode"] = "author";
	}

	// player, player_nc
	if ($comment["roleid"] != 3 && $comment["roleid"] != 4) {
		$comments[$idx]["flaircode"] = $comment["rolecode"];
	}
}

$view->assign("comments", $comments, null, true);

$alltags = $con->getAssoc("select tagid, name from tag where assettypeid=1");

$tags = array();
$tagscached = trim($asset["tagscached"]);
if (!empty($tagscached)) {
	$tagdata = explode("\r\n", $tagscached);
	foreach ($tagdata as $tagrow) {
		$row = explode(",", $tagrow);
		$tags[] = array('name' => $row[0], 'color' => $row[1], 'tagid' => $row[2], 'text' => $alltags[$row[2]]);
	}
}

$view->assign("tags", $tags);

$releases = $con->getAll("
	SELECT
		r.*,
		a.text,
		GROUP_CONCAT(cgv.gameVersion ORDER BY cgv.gameVersion ASC SEPARATOR ',') AS compatibleGameVersions,
		GROUP_CONCAT(gv.sortIndex   ORDER BY cgv.gameVersion ASC SEPARATOR ',') AS compatibleGameVersionsIndices
	FROM `release` r
	JOIN asset a ON a.assetid = r.assetid
	LEFT JOIN ModReleaseCompatibleGameVersions cgv ON cgv.releaseId = r.releaseid
	LEFT JOIN GameVersions gv ON gv.version = cgv.gameVersion
	WHERE modid = ?
	GROUP BY r.releaseid
	ORDER BY r.modversion DESC, MAX(cgv.gameVersion) DESC, r.created DESC
", [$asset['modid']]);

$releaseFiles = [];
if(count($releases)) {
	$foldedAssetIds = implode(',', array_map(fn($r) => $r['assetid'], $releases));
	// @security: assetid's come from the database and are numeric, and are therefore sql inert.
	$releaseFiles = $con->getAssoc("SELECT `file`.assetid, `file`.* FROM `file` WHERE assetid IN ($foldedAssetIds)");
}

foreach ($releases as &$release) {
	$release['file'] = $releaseFiles[$release['assetid']];
	
	$compatibleGameVersions      = array_map('intval', explode(',', $release['compatibleGameVersions'])); // sorted ascending
	$compatibleGameVersionsIndices = array_map('intval', explode(',', $release['compatibleGameVersionsIndices'])); // sorted ascending
	$release['maxCompatibleGameVersion'] = $compatibleGameVersions ? last($compatibleGameVersions) : 0;
	$release['compatibleGameVersions'] = $compatibleGameVersions;
	$release['compatibleGameVersionsFolded'] = foldSequentialVersionRanges($compatibleGameVersions, $compatibleGameVersionsIndices);
}
unset($release);


/*
	Determine the game versions of interest:
		stable and
		unstable (newer than current stable if any)

	Match mod releases:
		latest stable release that is for the stable version of the game -> recommend
		latest unstable version that is either
			for the unstable version of the game, or
			for the stable version of the game, if the release is a newer unstable version than the stable release (only if there is no release for the unstable game version)
		-> recommend for testers
		If there are not releases for for either of these, select the latest release -> latest release for an outdated version of the game

	Examples assuming current game version = 5, and a newer unstable version = 5p also exists,
	RV = mod release version, GV = game version required by the corresponding mod release version:
		GV  RV
		5   2.5
		5   3
		5   4.1  -> Recommended
		5p  4.2  -> For testers

		5   2.5
		5   3    -> Recommended
		5   4.p1
		5   4.p2 -> For testers

		5   2.5
		5   3    -> Recommended
		5   4.p1
		5p  4.p2 -> For testers

		2   2.5
		2   3
		3   4.p1
		3   4.p2 -> Latest outdated

		Assuming we came here by searching for mods for GV 2:
		2   2.5
		2   3    -> Recommended*
		3   4.p1
		3   4.p2
*/

/*
	NOTE(Rennorb): The mod list/search should pass information about the currently searched for game versions to this script, so we can recommend the correct release when user is searching with a specific gv in mind.
	This could be accomplished in one of three ways:
		Post parameter:
			pros:
			- Invisible (does not pollute url)
			cons:
			- Causes the browser to query for "are you sure you want to resend information" when the page gets reloaded.
			- Does not get transferred over if the link gets copy pasted to another user, potentially causing confusion because the site displays something different for the other user.
		Get parameter:
			pros:
			- Does get copied over to other users, preserving that specific recommendation in the process.
			cons:
			- Pollutes the page link when it gets copy pasted to be presented on some social media outlet -> discord mod showcase links would likely get polluted.
			- Recommendation is pinned with this link -> stored links 'degrade' since they won't recommend the latest release but the one for the specified game version.
		Cookie:
			pros:
			- Invisible (does not pollute url)
			cons:
			- Potential misinterpretation if the cookie is not correctly reset before the user navigates to the mod page without a specific version search.
			- Does not get transferred by copying the link, potentially causing confusion in a third party that will get a different recommendation.
			- Likely does not persist between page reloads, so reloading the page will reset the recommendation to the general recommendation.
		Referrer:
			pros:
			- Invisible (does not pollute url)
			- Persistent across reloads, but resets on navigation.
			- Does not require javascript.
			cons:
			- Does not get transferred by copying the link, potentially causing confusion in a third party that will get a different recommendation.
			- Sending a correct referrer is up to the client.


	I've decided that the referrer approach is best here, because;
	- of all the cons between all the options the unwanted recommendation pinning of the 'get' approach is the worst offender and should be avoided,
	- post navigation is complicated to implement, and
	- cookies have to decay between reloads to avoid other staleness issues or be a lot very complicated.
*/

$allGameVersions = array_map('intval', $con->getCol('SELECT version FROM GameVersions ORDER BY version DESC'));

$highestTargetVersion = $allGameVersions[0];

if(!empty($_SERVER['HTTP_REFERER'])) {
	parse_str(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY), $refererQuerryArgs);

	$mv = !empty($refererQuerryArgs['mv']) ? compileMajorVersion($refererQuerryArgs['mv']) : false;
	if(isset($refererQuerryArgs['gv']) && is_array($refererQuerryArgs['gv'])) {
		$gvs = array_filter(array_map('compileSemanticVersion', $refererQuerryArgs['gv']));
	}
	else {
		$gvs = false;
	}

	foreach($allGameVersions as $gameversion) {
		if(
		   ($mv && (($gameversion & (VERSION_MASK_MAJOR | VERSION_MASK_MINOR)) === $mv))
		|| ($gvs && in_array($gameversion, $gvs, true))
		) {
			$highestTargetVersion = $gameversion;
			break;
		}
	}
}

$recommendationIsInfluencedBySearch = $highestTargetVersion !== $allGameVersions[0];


$tagetRecommendedGameVersionStable = null;
$tagetRecommendedGameVersionUnstable = null;

{
	$highestTargetVersionReached = false;
	foreach($allGameVersions as $gameversion) {
		if(!$highestTargetVersionReached && $gameversion !== $highestTargetVersion) continue;
		else $highestTargetVersionReached = true;

		if(isPreReleaseVersion($gameversion)) {
			if(!$tagetRecommendedGameVersionUnstable) {
				$tagetRecommendedGameVersionUnstable = $gameversion;
			}
		}
		else {
			if(!$tagetRecommendedGameVersionStable) {
				$tagetRecommendedGameVersionStable = $gameversion;
			}
			break;
		}
	}
}

$recommendedReleaseStable = null;
$recommendedReleaseUnstable = null;
$fallbackRelease = null;

// Sort releases by max supproted game version to find the reccomendation.
//NOTE(Rennorb): This is not the default sorting from the db, because we want the releaes in mod version order (if we can) for the files tab.
// Could considder swapping this around for @perf.
$releasesByMaxGameVersion = $releases;
usort($releasesByMaxGameVersion, fn($a, $b) => $b['maxCompatibleGameVersion'] - $a['maxCompatibleGameVersion']);

foreach($releasesByMaxGameVersion as $release) {
	if(isPreReleaseVersion($release['modversion'])) {
		if(!$recommendedReleaseUnstable) {
			if(
				   in_array($tagetRecommendedGameVersionUnstable, $release['compatibleGameVersions']) // First try and get a release for a pre-release version of the game.
				|| in_array($tagetRecommendedGameVersionStable, $release['compatibleGameVersions'])  // If we cannot find such a release, look for a newer, unstable release of the mod for the current stable version of the game.
			) {
				$recommendedReleaseUnstable = $release;
			}
		}
	}
	else if(in_array($tagetRecommendedGameVersionStable, $release['compatibleGameVersions'])) {
		$recommendedReleaseStable = $release;
		break; // If there is a newer unstable version we already found it.
	}
	else if(!$fallbackRelease) {
		$fallbackRelease = $release;
	}
}


$view->assign("releases", $releases, null, true);

$view->assign("recommendedReleaseStable", $recommendedReleaseStable, null, true);
$view->assign("recommendedReleaseUnstable", $recommendedReleaseUnstable, null, true);
$view->assign("fallbackRelease", $fallbackRelease, null, true);
$view->assign("recommendationIsInfluencedBySearch", $recommendationIsInfluencedBySearch, null, true);

$view->assign("asset", $asset);

$view->assign("shouldShowOneClickInstall", !preg_match('/macintosh|mac os x|mac_powerpc|iphone|ipod|ipad|android|blackberry|webos|mobile/i', $_SERVER['HTTP_USER_AGENT']), null, false);
$view->assign("isfollowing", empty($user) ? 0 : $con->getOne("select modid from `follow` where modid=? and userid=?", array($asset['modid'], $user['userid'])));

if (!empty($user)) {
	processTeamInvitation($asset, $user);
	processOwnershipTransfer($asset, $user);
}

$view->display("show-mod");

/** Fold several sequential version tags, e.g. 1.2.3, 1.2.4, 1.2.5 into '1.2.3 - 1.2.5' with a description containing the original versions.
 * Folds accross all layers of versions.
 * @param int[] $versions       must be sorted for the algo to work
 * @param int[] $versionIndices must be sorted for the algo to work
 * @return string[]
 */
function foldSequentialVersionRanges($versions, $versionIndices)
{
	assert(count($versions) == count($versionIndices));

	$result = [];

	$startSortIndex = $versionIndices[0];
	$sequenceLength = 1;
	for($i = 1; $i < count($versionIndices); $i++) {
		if($versionIndices[$i] !== $startSortIndex + $sequenceLength) {
			mergeAndPush($result, $versions, $i - $sequenceLength, $sequenceLength);
			$startSortIndex = $versionIndices[$i];
			$sequenceLength = 1;
		}
		else {
			$sequenceLength++;
		}
	}
	mergeAndPush($result, $versions, $i - $sequenceLength, $sequenceLength);

	return $result;
}

function mergeAndPush(&$result, &$versions, $startIndex, $sequenceLength)
{
	switch($sequenceLength) {
		case  0: break;
		case  1: $result[] = formatSemanticVersion($versions[$startIndex]); break;
		default: $result[] = formatSemanticVersion($versions[$startIndex]).' - '.formatSemanticVersion($versions[$startIndex + $sequenceLength - 1]);
	}
}

function processTeamInvitation($asset, $user)
{
	global $con, $view;

	$invite = $con->getRow("select notificationid, recordid from notification where `type` = 'teaminvite' and `read` = 0 and userid = ? and (recordid & ((1 << 30) - 1)) = ?", array($user['userid'], $asset['modid'])); // :InviteEditBit
	$pending = !empty($invite);
	$view->assign("teaminvite", $pending);
	if(!$pending) return;


	if (!isset($_GET['acceptteaminvite'])) return;

	switch ($_GET['acceptteaminvite']) {
		case 1:
			$canedit = (intval($invite['recordid']) & (1 << 30)) ? 1 : 0; // :InviteEditBit
			$con->Execute("insert into teammember (modid, userid, created, canedit) values (?, ?, now(), ?)", array($asset['modid'], $user['userid'], $canedit));

			$con->Execute("update notification set `read` = 1 where notificationid = ?", array($invite['notificationid']));

			logAssetChanges([$user['name'].' acepted team invitation'], $asset['assetid']);

			$url = parse_url($_SERVER['REQUEST_URI']);
			$url['query'] = stripQueryParam($url['query'], 'acceptteaminvite');
			forceRedirect($url);
			exit();

		case 0:
			$con->Execute("update notification set `read` = 1 where notificationid = ?", array($invite['notificationid']));

			logAssetChanges([$user['name'].' rejected team invitation'], $asset['assetid']);


			$url = parse_url($_SERVER['REQUEST_URI']);
			$url['query'] = stripQueryParam($url['query'], 'acceptteaminvite');
			forceRedirect($url);
			exit();
	}
}

function processOwnershipTransfer($asset, $user)
{
	global $con, $view;

	$pendingInvitationId = $con->getOne("select notificationid from notification where `type` = 'modownershiptransfer' and `read` = 0 and userid = ? and recordid = ?", array($user['userid'], $asset['modid']));
	$view->assign("transferownership", $pendingInvitationId);
	if(!$pendingInvitationId) return;


	if(!isset($_GET['acceptownershiptransfer'])) return;

	switch ($_GET['acceptownershiptransfer']) {
		case 1:
			$con->startTrans();
			$oldOwnerData = $con->getOne("select createdbyuserid, created from `asset` where `assetid` = ?", array($asset['assetid'])); // @perf
			// swap owner and teammember that accepted in the teammembers table
			$con->execute("update teammember
				set userid = ?, canedit = 1, accepted = 1, created = ?
				where modid = ? and userid = ?
			", array($oldOwnerData['createdbyuserid'], $oldOwnerData['created'], $asset['modid'], $user['userid']));
			$con->execute("update asset set createdbyuserid=? where assetid=?", array($user['userid'], $asset['assetid']));
			$con->execute("update asset
				join `mod` on `mod`.modid = ?
				join `release` on `release`.modid = `mod`.modid and `release`.assetid = asset.assetid
				set asset.createdbyuserid = ?
			", array($asset['modid'], $user['userid']));

			$con->execute("update notification set `read` = 1 where notificationid = ?", array($pendingInvitationId));
			$ok = $con->completeTrans();
			if($ok) {
				logAssetChanges(['Ownership migrated to '.$user['name']], $asset['assetid']);
			}

			$url = parse_url($_SERVER['REQUEST_URI']);
			$url['query'] = stripQueryParam($url['query'], 'acceptownershiptransfer');
			forceRedirect($url);
			exit();

		case 0:
			$con->execute("update notification set `read` = 1 where notificationid = ?", array($pendingInvitationId));

			logAssetChanges(['Ownership migration rejected by '.$user['name']], $asset['assetid']);

			$url = parse_url($_SERVER['REQUEST_URI']);
			$url['query'] = stripQueryParam($url['query'], 'acceptownershiptransfer');
			forceRedirect($url);
			exit();
	}
}
