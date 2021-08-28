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
		where
			asset.assetid = ?
	", array($assetid));

	if (!$asset) {
		$view->display("404");
		exit();
	}
	
	$files = $con->getAll("select * from file where assetid=?", array($assetid));
	
	foreach ($files as &$file) {
		$file["ending"] = substr($file["filename"], strrpos($file["filename"], ".")+1);
		$file["created"] = date("M jS Y, H:i:s", strtotime($file["created"]));
	}
	unset($file);
	$view->assign("files", $files);
	
	$comments = $con->getAll("
		select 
			comment.*,
			user.name as username
		from 
			comment 
			join user on (comment.userid = user.userid)
		where assetid=?
		order by comment.created desc
	", array($assetid));
	
	$view->assign("comments", $comments, null, true);
	
	$tags = array();
	$tagscached = trim($asset["tagscached"]);
	if (!empty($tagscached)) {
		$tagdata = explode("\r\n", $tagscached);
		foreach($tagdata as $tagrow) {
			$row = explode(",", $tagrow);
			$tags[] = array('name' => $row[0], 'color' => $row[1], 'tagid' => $row[2]);
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

	foreach ($releases as $idx=>$release) {
		$tags = array();
		$tagscached = trim($release["tagscached"]);
		if (!empty($tagscached)) {
			$tagdata = explode("\r\n", $tagscached);
			foreach($tagdata as $tagrow) {
				$row = explode(",", $tagrow);
				$tags[] = array('name' => $row[0], 'color' => $row[1], 'tagid' => $row[2]);
			}
		}
		if (count($tags)) {
			usort($tags, 'rcmpVersionTag');
			$releases[$idx]['highestver'] = $tags[count($tags)-1]['name'];
		} else {
			$releases[$idx]['highestver'] = ""; 
		}
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
$view->display("show-mod");


function cmpReleases($r1, $r2) {
	$val = cmpVersion($r2['highestver'], $r1['highestver']);
	if ($r2['highestver'] == $r1['highestver']) { $val = cmpVersion($r2['modversion'], $r1['modversion']); }
	return $val;
}

