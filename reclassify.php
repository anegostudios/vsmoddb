<?php

$asset = $con->getRow("select * from asset where assetid=?",array($_GET["assetid"]));
$currentassettype = $con->getRow("select assettype.* from assettype where assettypeid=?", array($asset["assettypeid"]));
$tablename = $currentassettype["code"];


if (!empty($_POST["save"])) {
	$prevassettypeid = $currentassettype["assettypeid"];
	$nowassettypeid = $_POST["assettypeid"];
	$nowassettype = $con->getRow("select * from assettype where assettypeid=?", array($nowassettypeid));
	
	$newtablename = $nowassettype["code"];
	
	$typedasset = $con->getRow("select * from {$tablename} where assetid=?", array($_GET["assetid"]));
	
	if (!$typedasset) exit("Asset not found?");
	if (!$newtablename) exit("New asset type not found?");
	
	$sql = "delete from {$tablename} where {$tablename}id=" . $typedasset["{$tablename}id"];
	
	$con->Execute($sql);
	
	$recordid = insert($newtablename);
	update($newtablename, $recordid, array(
		"assetid" => $_GET["assetid"],
		"created" => $asset["created"]
	));

	update("asset", intval($_GET["assetid"]), array("assettypeid" => $nowassettypeid));
	
	logAssetChanges(array("Reclassified from {$tablename} to {$newtablename}"), $_GET["assetid"]);
	
	header("Location: /edit/{$newtablename}?assetid=" . intval($_GET["assetid"]));
	exit();
}


$assettypes = $con->getAll("select * from assettype");
$view->assign("assettypes", $assettypes);

$view->assign("asset", $asset);
$view->assign("nowassettype", $currentassettype["name"]);

$view->display("reclassify.tpl");

