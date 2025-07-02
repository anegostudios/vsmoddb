<?php
showErrorPage(HTTP_NOT_FOUND);

$view->assign("columns", array(array("cssclassname" => "", "code" => "code", "title" => "Code"), array("cssclassname" => "", "code" => "Name", "title" => "Name")));
$view->assign("entrycode", "tag");
$view->assign("entryplural", "Releases");
$view->assign("entrysingular", "Release");

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
		$file["url"] = formatDownloadTrackingUrl($file);
	}
	unset($file);
	$view->assign("files", $files);
	
	$view->assign("comments", []);
} else {
	$asset = array("modid" => 0, "name" => "", "text" => "", "color" => "", "assettypeid" => "");
}

$view->assign("assettypes", $con->getAll("select * from assettype order by name"));

$view->assign("asset", $asset);
$view->display("show-mod");
