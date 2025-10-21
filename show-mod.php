<?php

include $config['basepath']. 'lib/recommend-release.php';

$assetId = $urlparts[2] ?? 0;

if (!$assetId) {
	showErrorPage(HTTP_NOT_FOUND);
}

$asset = $con->getRow("
	SELECT
		a.*,
		m.*,
		logo.cdnPath AS logoUrl,
		logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
		HEX(creator.hash) AS creatorHash,
		creator.name AS creatorName,
		s.code AS statusCode
	FROM 
		assets a
		JOIN mods m ON m.assetId = a.assetId
		LEFT JOIN users creator ON creator.userId = a.createdByUserId
		LEFT JOIN status s ON s.statusId = a.statusId
		LEFT JOIN files AS logo ON logo.fileId = m.embedLogoFileId
	WHERE
		a.assetId = ?
", [$assetId]);

if (!$asset) showErrorPage(HTTP_NOT_FOUND);

$teamMembers = $con->getAll(<<<SQL
	SELECT u.userId, u.name, HEX(u.hash) AS userHash
	FROM modTeamMembers t
	JOIN users u ON u.userId = t.userId
	WHERE t.modId = ?
SQL, [$asset['modId']]);
$view->assign('teamMembers', $teamMembers);

$files = $con->getAll('SELECT * FROM files WHERE assetId = ? AND fileId NOT IN (?, ?) ORDER BY `order`', 
	[$assetId, $asset['cardLogoFileId'] ?? 0, $asset['embedLogoFileId'] ?? 0]);  /* sql cant compare against null */

//NOTE(Rennorb): There was a time where we rescaled images for logos. We no longer do that, but in ~140 cases there are still two images for the logo: the actual logo image, and the original one that was uploaded.
// Since we don't show the logo in the slideshow anymore, we also need to remove that second file that got uploaded, without removing it from the database so it stays downloadable for the mod author until they replace it.
// Here is a sql query to get a list of such mods:
/*
	select modId, urlAlias, users.name from mods
	join file f on f.fileId = mods.cardLogoFileId
	join file f2 on f2.cdnPath = concat(substr(f.cdnPath, 1, length(f.cdnPath) - 12), substr(f.cdnPath, -4))
	join assets on mods.assetId = asset.assetId
	join users on users.userId = asset.createdByUserId;
*/
if($asset['hasLegacyLogo']) {
	splitOffExtension($asset['logoUrl'], $base, $ext);
	if(endsWith($base, '_480_320')) {
		$legacyLogoPath = substr($base, 0, strlen($base) - 8).'.'.$ext;
		foreach ($files as $k => $file) {
			if($file['cdnPath'] === $legacyLogoPath) {
				unset($files[$k]);
				break;
			}
		}
	}
}

if(!empty($asset['logoUrl'])) {
	$asset['logoUrl'] = formatCdnUrlFromCdnPath($asset['logoUrl']);
}

foreach ($files as &$file) {
	$file['created'] = date('M jS Y, H:i:s', strtotime($file['created']));
	$file['ext'] = substr($file['name'], strrpos($file['name'], '.')+1); // no clue why pathinfo doesnt work here
	$file['url'] = formatCdnUrl($file);
}
unset($file);

$view->assign('files', $files);

$deletedFilter = canModerate(null, $user) ? '' : 'AND !c.deleted';
$comments = $con->getAll(<<<SQL
	SELECT 
		c.*,
		mr.kind as lastModaction,
		u.name AS username,
		HEX(u.hash) AS userHash,
		IFNULL(u.banneduntil >= NOW(), 0) AS isBanned,
		r.code AS roleCode
	FROM 
		comments c 
		JOIN users u ON u.userId = c.userId
		LEFT JOIN roles r ON r.roleId = u.roleid
		LEFT JOIN moderationRecords mr ON mr.actionId = c.lastModaction
	WHERE c.assetId = ? $deletedFilter
	ORDER BY c.created DESC
SQL, [$assetId]);

foreach ($comments as &$comment) {
	if ($asset['createdByUserId'] == $comment['userId']) {
		$comment['flairCode'] = 'author';
	}

	if ($comment['roleCode'] != 'player' && $comment['roleCode'] != 'player_nc') {
		$comment['flairCode'] = $comment['roleCode'];
	}
}
unset($comment);

$view->assign("comments", $comments, null, true);

$allTags = $con->getAssoc('SELECT tagId, name FROM tags');
$view->assign("tags", unwrapCachedTagsWithText($asset["tagsCached"], $allTags));

$releases = $con->getAll(<<<SQL
	SELECT
		r.*,
		a.text,
		GROUP_CONCAT(cgv.gameVersion ORDER BY cgv.gameVersion ASC SEPARATOR ',') AS compatibleGameVersions,
		GROUP_CONCAT(gv.sortIndex   ORDER BY cgv.gameVersion ASC SEPARATOR ',') AS compatibleGameVersionsIndices
	FROM modReleases r
	JOIN assets a ON a.assetId = r.assetId
	LEFT JOIN modReleaseCompatibleGameVersions cgv ON cgv.releaseId = r.releaseId
	LEFT JOIN gameVersions gv ON gv.version = cgv.gameVersion
	WHERE modId = ?
	GROUP BY r.releaseId
	ORDER BY r.version DESC, MAX(cgv.gameVersion) DESC, r.created DESC
SQL, [$asset['modId']]);

$releaseFiles = [];
if(count($releases)) {
	$foldedAssetIds = implode(',', array_map(fn($r) => $r['assetId'], $releases));
	// @security: assetid's come from the database and are numeric, and are therefore sql inert.
	//NOTE(Rennorb): Select the assetId for the first column so we can use getAssoc and use it as the key.
	$releaseFiles = $con->getAssoc("SELECT assetId, f.* FROM files f WHERE assetId IN ($foldedAssetIds)");
}

foreach ($releases as &$release) {
	$release['file'] = $releaseFiles[$release['assetId']];
	
	if($release['compatibleGameVersions']) {
		$compatibleGameVersions        = array_map('intval', explode(',', $release['compatibleGameVersions'])); // sorted ascending
		$compatibleGameVersionsIndices = array_map('intval', explode(',', $release['compatibleGameVersionsIndices'])); // sorted ascending
		$release['maxCompatibleGameVersion'] = last($compatibleGameVersions);
		$release['compatibleGameVersions'] = $compatibleGameVersions;
		$release['compatibleGameVersionsFolded'] = foldSequentialVersionRanges($compatibleGameVersions, $compatibleGameVersionsIndices);
	}
	else {
		// Set the value to "stable release", so the recommendation show it as "latest release", and not as "for testers" (because just 0 implies some kind of prerelease version).
		$release['maxCompatibleGameVersion'] = 0x0000_0000_0000_f000;
		$release['compatibleGameVersions'] = [];
		$release['compatibleGameVersionsFolded'] = [];
	}
}
unset($release);


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

$allGameVersions = array_map('intval', $con->getCol('SELECT version FROM gameVersions ORDER BY version DESC'));

$mv = null;
$gvs = null;

if(!empty($_SERVER['HTTP_REFERER'])) {
	parse_str(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY), $refererQuerryArgs);

	if(!empty($refererQuerryArgs['mv'])) $mv = compilePrimaryVersion($refererQuerryArgs['mv']);
	if(isset($refererQuerryArgs['gv']) && is_array($refererQuerryArgs['gv'])) {
		$gvs = array_filter(array_map('compileSemanticVersion', $refererQuerryArgs['gv']));
	}
}

$recommendationIsInfluencedBySearch = selectDesiredVersions($allGameVersions, $mv, $gvs, $highestTargetVersion, $tagetRecommendedGameVersionStable, $tagetRecommendedGameVersionUnstable);

recommendReleases($releases, $tagetRecommendedGameVersionStable, $tagetRecommendedGameVersionUnstable, $recommendedReleaseStable, $recommendedReleaseUnstable, $fallbackRelease);


$view->assign("releases", $releases, null, true);

$view->assign("recommendedReleaseStable", $recommendedReleaseStable, null, true);
$view->assign("recommendedReleaseUnstable", $recommendedReleaseUnstable, null, true);
$view->assign("fallbackRelease", $fallbackRelease, null, true);
$view->assign("recommendationIsInfluencedBySearch", $recommendationIsInfluencedBySearch, null, true);
$view->assign("highestTargetVersion", $highestTargetVersion, null, true);

$view->assign("asset", $asset);

$oneClickInstallWorks = !preg_match('/macintosh|mac os x|mac_powerpc|iphone|ipod|ipad|android|blackberry|webos|mobile/i', $_SERVER['HTTP_USER_AGENT']);
$view->assign("shouldShowOneClickInstall", $oneClickInstallWorks && $asset['type'] === 'mod', null, false);
$view->assign("shouldListCompatibleGameVersion", $asset['type'] === 'mod', null, false);
$view->assign("changelogColspan", 5 + ($asset['type'] === 'mod' ? ($oneClickInstallWorks ? 2 : 1) : 0), null, false);
$view->assign("isFollowing", empty($user) ? 0 : $con->getOne('SELECT modId FROM userFollowedMods WHERE modId = ? AND userId = ?', [$asset['modId'], $user['userId']]));

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

	$invite = $con->getRow("SELECT notificationId, recordId FROM notifications WHERE kind = ".NOTIFICATION_TEAM_INVITE." AND !`read` AND userId = ? AND (recordId & ((1 << 30) - 1)) = ?", [$user['userId'], $asset['modId']]); // :InviteEditBit
	$pending = !empty($invite);
	$view->assign("teaminvite", $pending);
	if(!$pending) return;


	if (!isset($_GET['acceptteaminvite'])) return;

	switch ($_GET['acceptteaminvite']) {
		case 1:
			$canEdit = (intval($invite['recordId']) & (1 << 30)) ? 1 : 0; // :InviteEditBit
			$con->Execute('INSERT INTO modTeamMembers (modId, userId, canEdit) values (?, ?, ?)', [$asset['modId'], $user['userId'], $canEdit]);

			$con->Execute('UPDATE notifications SET `read` = 1 WHERE notificationId = ?', [$invite['notificationId']]);

			logAssetChanges([$user['name'].' acepted team invitation'], $asset['assetId']);

			$url = parse_url($_SERVER['REQUEST_URI']);
			$url['query'] = stripQueryParam($url['query'], 'acceptteaminvite');
			forceRedirect($url);
			exit();

		case 0:
			$con->Execute('UPDATE notifications SET `read` = 1 WHERE notificationId = ?', [$invite['notificationId']]);

			logAssetChanges([$user['name'].' rejected team invitation'], $asset['assetId']);


			$url = parse_url($_SERVER['REQUEST_URI']);
			$url['query'] = stripQueryParam($url['query'], 'acceptteaminvite');
			forceRedirect($url);
			exit();
	}
}

function processOwnershipTransfer($asset, $user)
{
	global $con, $view;

	$pendingInvitationId = $con->getOne("SELECT notificationId FROM notifications WHERE kind = ? AND !`read` AND userId = ? AND recordId = ?", [NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST, $user['userId'], $asset['modId']]);
	$view->assign("transferownership", $pendingInvitationId);
	if(!$pendingInvitationId) return;


	if(!isset($_GET['acceptownershiptransfer'])) return;

	switch ($_GET['acceptownershiptransfer']) {
		case 1:
			$con->startTrans();

			$oldOwnerData = $con->getRow('SELECT createdByUserId, created FROM assets WHERE assetId = ?', [$asset['assetId']]); // @perf
			// swap owner and teammember that accepted in the teammembers table
			$con->execute(<<<SQL
				UPDATE modTeamMembers
				SET userId = ?, canEdit = 1, created = ?
				WHERE modId = ? AND userId = ?
			SQL, [$oldOwnerData['createdByUserId'], $oldOwnerData['created'], $asset['modId'], $user['userId']]);
			$con->execute('UPDATE assets SET createdByUserId = ? WHERE assetId = ?', [$user['userId'], $asset['assetId']]);
			$con->execute(<<<SQL
				UPDATE assets a
				JOIN mods m ON m.modId = ?
				JOIN modReleases r ON r.modId = m.modId AND r.assetId = a.assetId
				set a.createdByUserId = ?
			SQL, [$asset['modId'], $user['userId']]);

			$con->execute('UPDATE notifications SET `read` = 1 WHERE notificationId = ?', [$pendingInvitationId]);
			// Send notification to the original author:
			// Use the 32nd bit of the modId to indicate success :PackedTransferSuccess
			$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES ('.NOTIFICATION_MOD_OWNERSHIP_TRANSFER_RESOLVED.', ?, ?) ', [$oldOwnerData['createdByUserId'], $asset['modId'] | (1 << 31)]);

			logAssetChanges(['Ownership migrated to '.$user['name']], $asset['assetId']);

			$con->completeTrans();

			$url = parse_url($_SERVER['REQUEST_URI']);
			$url['query'] = stripQueryParam($url['query'], 'acceptownershiptransfer');
			forceRedirect($url);
			exit();

		case 0:
			$con->startTrans();

			$oldOwner = $con->getOne('SELECT createdByUserId FROM assets WHERE assetId = ?', [$asset['assetId']]); // @perf

			$con->execute('UPDATE notifications SET `read` = 1 WHERE notificationId = ?', [$pendingInvitationId]);

			// Send notification to the original author:
			$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES ('.NOTIFICATION_MOD_OWNERSHIP_TRANSFER_RESOLVED.', ?, ?) ', [$oldOwner, $asset['modId'] | (0 << 31)]); // :PackedTransferSuccess

			logAssetChanges(['Ownership migration rejected by '.$user['name']], $asset['assetId']);

			$con->completeTrans();

			$url = parse_url($_SERVER['REQUEST_URI']);
			$url['query'] = stripQueryParam($url['query'], 'acceptownershiptransfer');
			forceRedirect($url);
			exit();
	}
}

/**
 * @param array $release
 * @param int $referenceVersion The version originally searched for.
 * @return string
 */
function formatVersionsAndWarning($release, $referenceVersion)
{
	$text = formatGrammaticallyCorrectEnumeration($release['compatibleGameVersionsFolded']);
	if($release['maxCompatibleGameVersion'] >= $referenceVersion) return $text;

	$ver = formatSemanticVersion($referenceVersion);
	if((($release['maxCompatibleGameVersion'] ^ $referenceVersion) & VERSION_MASK_PRIMARY) === 0) {
		$text .= ", <abbr title='While it is likely that this mod works with game version {$ver} it does not explicitly specify that it does.'>potentially outdated</abbr>";
	}
	else {
		$text .= ", <abbr style='color:#b00;' title='This mod did not specify that is is compatible with gameversion {$ver}, nor with any patch of that major version in general.'><i class='ico alert'></i> outdated</abbr>";
	}
	return $text;
}

/**
 * @param string $text
 * @param bool $recommendationIsInfluencedBySearch
 * @param int $referenceVersion
 * @return string
 */
function formatRecommendationAdjustedHint($text, $recommendationIsInfluencedBySearch, $referenceVersion)
{
	if(!$recommendationIsInfluencedBySearch) return $text;

	$ver = formatSemanticVersion($referenceVersion);
	return "<abbr title='Based on coming here from a search for game version {$ver}.&#010;This is temporary and will reset on your next visit.'>{$text}*</abbr>";
}
