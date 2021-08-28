<?php

$view->assign("searchvalues", array());
$stati = $con->getAll("select * from status order by sortorder");
$view->assign("stati", $stati);

if (!empty($_GET["search"])) {
	
	$wheresql = array();
	$wherevalues = array();
	$joins = array();
	$searchvalues = array("text" => "", "statusid" => null);
	
	if (!empty($_GET["statusid"])) {
		$wheresql[] = "asset.statusid=?";
		$wherevalues[] = $_GET["statusid"];
		$searchvalues["statusid"] = $_GET["statusid"];
	}

	if (!empty($_GET["text"])) {
		$wheresql[] = "(asset.name like ? or asset.text like ?)";
		$wherevalues[] = "%" . $_GET["text"] . "%";
		$wherevalues[] = "%" . $_GET["text"] . "%";
		
		$searchvalues["text"] = $_GET["text"];
	}
	
	$view->assign("searchvalues", $searchvalues);
	
	$sql = "
		select 
			asset.*, 
			user.name as `createdbyusername`,
			status.name as statusname,
			assettype.name as assettypename,
			assettype.code as assettypecode
		from 
			asset 
			join assettype on asset.assettypeid = assettype.assettypeid
			left join user on asset.createdbyuserid = user.userid
			left join status on asset.statusid = status.statusid
		" . (count($wheresql) ? "where " . implode(" and ", $wheresql) : "") . "
	";
	
	$rows = $con->getAll($sql, $wherevalues);
	
	foreach($rows as &$row) {
		$tagdata = explode("\r\n", $row["tagscached"]);
		$row["tags"] = array();
		
		foreach($tagdata as $tagrow) {
			$row["tags"][] = explode(",", $tagrow);
		}
	}
	unset($row);
	
	$view->assign("foundassets", $rows);
	$view->display("searchresults.tpl");
	exit();
}




$featurereleases = $con->getAll("
	select
		asset.*,
		featurerelease.*
	from 
		asset
		join featurerelease on (featurerelease.assetid = asset.assetid)
	where inprogress=1
	order by releaseorder 
");
$view->assign("featurereleases", $featurereleases, null, true);

$unrespondedentries = $con->getAll("
	select 
		asset.assetid,
		asset.name,
		asset.lastmodified,
		user.name as createdbyusername,
		assettype.name as assettypename,
		assettype.code as assettypecode
	from 
		asset
		join user on (asset.createdbyuserid = user.userid)
		join status on (asset.statusid = status.statusid)
		join assettypesubscription on (asset.assettypeid = assettypesubscription.assettypeid and assettypesubscription.userid = ?)
		join assettype on (asset.assettypeid = assettype.assettypeid)
	where 
		not exists (select responseid from response where response.assetid = asset.assetid)
		and (status.code = 'draft' or status.code = 'ready')
		and (asset.createdbyuserid != ? or asset.editedbyuserid != ?)
", array($user["userid"], $user["userid"], $user["userid"]));

$view->assign("unrespondedentries", $unrespondedentries);


$changelogs = $con->getAll("
	select 
		changelog.*,
		user.name as username,
		asset.name as assetname,
		assettype.code as assettypecode
	from
		changelog
		join user on (changelog.userid = user.userid)
		join asset on (changelog.assetid = asset.assetid)
		join assettype on (assettype.assettypeid = asset.assettypeid)
	order by created desc
	limit 40
");

$view->assign("changelogs", $changelogs);


$latestentries = $con->getAll("
	select 
		asset.assetid,
		asset.name,
		asset.lastmodified,
		assettype.name as assettypename,
		assettype.code as assettypecode,
		user.name as createdbyusername
	from 
		asset
		join user on (asset.createdbyuserid = user.userid)
		join status on (asset.statusid = status.statusid)
		join assettype on (asset.assettypeid = assettype.assettypeid)
	order by
		asset.lastmodified desc
	limit 20
");

$view->assign("latestentries", $latestentries);

$latestcomments = $con->getAll("
	select 
		comment.*,
		asset.name as assetname,
		assettype.name as assettypename,
		assettype.code as assettypecode,
		user.name as username
	from 
		comment
		join user on (comment.userid = user.userid)
		join asset on (comment.assetid = asset.assetid)
		join assettype on (asset.assettypeid = assettype.assettypeid)
	order by
		comment.lastmodified desc
	limit 20
");

$view->assign("latestcomments", $latestcomments, null, true);

$view->display("dashboard.tpl");
