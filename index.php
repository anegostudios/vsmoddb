<?php

header_remove('X-Powered-By');

if (!empty($_SERVER['HTTP_ACCEPT']) && $_SERVER['REQUEST_METHOD'] == "GET") {
	if(!strstr($_SERVER['HTTP_ACCEPT'], "text/html") && !strstr($_SERVER['HTTP_ACCEPT'], "application/json") && $_SERVER['HTTP_ACCEPT'] != "*/*") exit("not an image");
}

$config = array();
$config["basepath"] = getcwd() . '/';

include("lib/core.php");

$urlpath = getURLPath();
$target = explode("?", $urlpath)[0];

$view->assign("urltarget", $target);

if (preg_match("/[^-\/\w+]/", $target)) $target="dashboard";
if (empty($target)) {
	$target = "home";
}

$urlparts = explode("/", $target);

$typewhitelist = array("terms", "api", "updateversiontags", "files", "show", "download", "edit", "edit-comment", "delete-comment", "edit-uploadfile", "edit-deletefile", "list", "accountsettings", "logout", "login", "home", "get-assetlist", "get-usernames", "notification", "set-follow", "moderate");

if (!in_array($urlparts[0], $typewhitelist)) {
	$modid = $con->getOne("select assetid from `mod` where urlalias=?", array($urlparts[0]));
	if ($modid) {

		$urlparts = array("show", "mod", $modid);
	} else {
		$view->display("404.tpl");
		exit();
	}
}

if ($urlparts[0] == "api") {
	array_shift($urlparts);
	include("api.php");
	exit();
}
if ($urlparts[0] == "notification") {
	include("notification.php");
	exit();
}

// Try to compose filename from the first two segemnts of the url:
// edit/profile -> edit-profile.php 
$filename = implode("-", array_slice($urlparts, 0, 2)) . ".php";

if (file_exists($filename)) {
	include($filename);
	exit();
} 


$filename = $urlparts[0] . ".php";

if (count($urlparts) > 1) {
	$assettypeid = $con->getOne("select assettypeid from assettype where code=?", array($urlparts[1]));
	
	if ($assettypeid && file_exists($filename)) {
		$assettype = $urlparts[1];
		
		if (in_array($assettype, array('user', 'stati', 'assettype', 'tag')) && $user['rolecode'] != 'admin') exit("noprivilege");
		
		include($filename);
		exit();
	} 
} else {
	include($filename);
}



$view->display("404.tpl");
