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
			createduser.userid as createduserid,
			createduser.created as createduserjoindate,
			createduser.name as createdusername,
			editeduser.userid as editeduserid,
			editeduser.name as editedusername,
			status.code as statuscode,
        	(select json_arrayagg(json_object('userid', user.userid, 'name', user.name)) 
        		from teammembers 
        		join user on teammembers.userid = user.userid 
        		where teammembers.modid = `mod`.modid and teammembers.accepted = 1) as teammembers
		from 
			asset 
			join `mod` on asset.assetid=`mod`.assetid
			left join user as createduser on asset.createdbyuserid = createduser.userid
			left join user as editeduser on asset.editedbyuserid = editeduser.userid
			left join status on asset.statusid = status.statusid
		where
			asset.assetid = ?
	", array($assetid));

	if (!$asset) {
		$view->display("404");
		exit();
	}

	if ($asset['teammembers'] > 0) {
		$asset['teammembers'] = json_decode($asset['teammembers'], true);

		foreach ($asset['teammembers'] as $idx => $teammember) {
			$asset['teammembers'][$idx]['usertoken'] = getUserHash($teammember['userid'], $asset['createduserjoindate']);
		}

		$view->assign("teammembers", $asset['teammembers']);
	}

	$createdusertoken = getUserHash($asset['createduserid'], $asset['createduserjoindate']);
	$view->assign("createdusertoken", $createdusertoken);
	$files = $con->getAll("select * from file where assetid=?", array($assetid));

	foreach ($files as &$file) {
		$file["ending"] = substr($file["filename"], strrpos($file["filename"], ".") + 1);
		$file["created"] = date("M jS Y, H:i:s", strtotime($file["created"]));
	}

	unset($file);
	$view->assign("files", $files);

	$comments = $con->getAll("
		select 
			comment.*,
			user.name as username,
			user.roleid as roleid,
			substring(sha2(concat(user.userid, user.created), 512), 1, 20) as usertoken,
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
			$comments[$idx]["flairname"] = "Author";
		}

		// player, player_nc
		if ($comment["roleid"] != 3 && $comment["roleid"] != 4) {
			$comments[$idx]["flaircode"] = $comment["rolecode"];
			$comments[$idx]["flairname"] = $comment["rolename"];
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

$accepted = $con->getOne("select accepted from teammembers where accepted = 0 and modid=? and userid=?", array($asset['modid'], $user['userid']));

if ($accepted === 0) {
	$view->assign("teaminvite", 1);
}

if (isset($_GET['acceptteaminvite'])) {
	$available = $con->getOne("select accepted from teammembers where accepted = 0 and modid=? and userid=?", array($asset['modid'], $user['userid']));

	if ($available === 0) {
		switch ($_GET['acceptteaminvite']) {
			case 1:
				$con->Execute("update teammembers set accepted=1 where modid=? and userid=?", array($asset['modid'], $user['userid']));
				$con->Execute("update notification set `read` = 1 where userid=? and type='teaminvite' and recordid=?", array($user['userid'], $asset['modid']));
				header('Location: /' . $asset['urlalias']);
				break;
			case 0:
				$con->Execute("delete from teammembers where modid=? and userid=?", array($asset['modid'], $user['userid']));
				$con->Execute("update notification set `read` = 1 where userid=? and type='teaminvite' and recordid=?", array($user['userid'], $asset['modid']));
				header('Location: /' . $asset['urlalias']);
				break;
			default:
				break;
		}
	}
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
