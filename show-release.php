<?php
$view->display("404.tpl");
exit();

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
			editeduser.name as editedusername
		from 
			asset 
			join `mod` on asset.assetid=`mod`.assetid
			left join user as createduser on asset.createdbyuserid = createduser.userid
			left join user as editeduser on asset.editedbyuserid = editeduser.userid
			left join status on asset.statusid = status.statusid
		where
			asset.assetid = ?
	", array($assetid));
	
	$files = $con->getAll("select * from file where assetid=?", array($assetid));
	
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
	

} else {
	$asset = array("modid" => 0, "name" => "", "text" => "", "color" => "", "assettypeid" => "", "tagtypeid" => "");
}

$view->assign("assettypes", $con->getAll("select * from assettype order by name"));
$view->assign("tagtypes", $con->getAll("select * from tagtype order by name"));

$view->assign("asset", $asset);
$view->display("show-mod");
