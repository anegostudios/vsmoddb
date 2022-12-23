<?php

$showuserid = $urlparts[2];

if ($showuserid) {
	$showuser = $con->getRow("
		select 
			user.*, 
		from 
			user
		where
			user.userid = ?
	", array($showuserid));

	if (!$user) {
		$view->display("404");
		exit();
	}
	
	$commentcount = $con->getAll("
		select 
			count(*),
		from 
			comment 
		where userid=?
	", array($showuserid));
	
	$modcount = $con->getAll("
		select count(*)
		from
			asset
			join assettype on (asset.assetypeid = assetype.assetypeid)
		where 
			assert.userid=?
			and assetype.code = 'mod'
	", array($showuserid));
	
	$view->assign("commentcount", $commentcount, null, true);	
	$view->assign("modcount", $modcount, null, true);	
	$view->assign("showuser", $showuser, null, true);
	
} else {
	$showuser = array("modid" => 0, "name" => "", "text" => "", "color" => "", "assettypeid" => "", "tagtypeid" => "");
}

$view->assign("showuser", $showuser);
$view->display("user");

