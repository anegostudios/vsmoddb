<?php

$view->assign("columns", array(array("cssclassname" => "", "code" => "code", "title" => "Code"), array("cssclassname" => "", "code" => "Name", "title" => "Name")));
$view->assign("entrycode", "tag");
$view->assign("entryplural", "Connection types");
$view->assign("entrysingular", "Connection type");

$assetid = $urlparts[2];

if ($assetid) {
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

	if (!$asset) {
		$view->display("404");
		exit();
	}

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
		where assetid=? and comment.deleted = 0
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
		select 
			`release`.*,
			asset.*
		from 
			`release` 
			join asset on (asset.assetid = `release`.assetid)
		where modid=?
		order by release.created desc
	", array($asset['modid']));

	foreach ($releases as $idx => $release) {
		$tags = array();
		$tagscached = trim($release["tagscached"]);
		if (!empty($tagscached)) {
			$tagdata = explode("\r\n", $tagscached);
			foreach ($tagdata as $tagrow) {
				$row = explode(",", $tagrow);
				$tags[] = array('name' => $row[0], 'color' => $row[1], 'tagid' => $row[2]);
			}
		}
		if (count($tags)) {
			usort($tags, 'rcmpVersionTag');
			$releases[$idx]['highestver'] = $tags[count($tags) - 1]['name'];
		} else {
			$releases[$idx]['highestver'] = "";
		}

		$tags = groupMinorVersionTags($tags);

		$releases[$idx]['tags'] = $tags;
		$releases[$idx]['file'] = $con->getRow("select * from file where assetid=? limit 1", array($release['assetid']));
	}

	usort($releases, "cmpReleases");
	$releases = array_reverse($releases);

	$view->assign("releases", $releases, null, true);
} else {
	$asset = array("modid" => 0, "name" => "", "text" => "", "color" => "", "assettypeid" => "", "tagtypeid" => "");
}

$view->assign("assettypes", $con->getAll("select * from assettype order by name"));
$view->assign("tagtypes", $con->getAll("select * from tagtype order by name"));

$view->assign("asset", $asset);

$view->assign("isfollowing", empty($user) ? 0 : $con->getOne("select modid from `follow` where modid=? and userid=?", array($asset['modid'], $user['userid'])));

if (!empty($user)) {
	processTeamInvitation($asset, $user);
	processOwnershipTransfer($asset, $user);
}

$view->display("show-mod");

function cmpReleases($r1, $r2)
{
	$val = cmpVersion($r2['highestver'], $r1['highestver']);
	if ($r2['highestver'] == $r1['highestver']) {
		$val = cmpVersion($r2['modversion'], $r1['modversion']);
	}
	return $val;
}


function groupMinorVersionTags($tags)
{
	$mainvercnt = 0;
	$curver = "0";
	$gtags = array();
	foreach ($tags as $idx => $tag) {
		$parts = explode(".", $tag['name']);
		$mainver = $parts[0] . "." . $parts[1];

		if ($curver == $mainver) {
			$mainvercnt++;
		} else {
			$curver = $mainver;
			$mainvercnt = 1;
		}

		if ($mainvercnt == 3) {
			$otag1 = array_pop($gtags);
			$otag2 = array_pop($gtags);
			$otag3 = $tag;
			$gtags[] = array('name' => "Various " . $curver . ".x", 'desc' => $otag1['name'] . ", " . $otag2['name'] . ", " . $otag3['name'], 'color' => $tag['color'], 'tagid' => 0);
		}

		if ($mainvercnt > 3) {
			$gtags[count($gtags) - 1]['desc'] .= ", " . $tag['name'];
		}

		if ($mainvercnt < 3) {
			$gtags[] = $tag;
		}
	}

	foreach ($gtags as $idx => $tag) {
		if (strstr($tag["name"], "Various")) {
			$vers = explode(", ", $tag["desc"]);
			usort($vers, "cmpVersion");
			$gtags[$idx]["desc"] = implode(", ", array_reverse($vers));
		}
	}

	return $gtags;
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
