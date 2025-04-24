<?php

header_remove('X-Powered-By');

$config = array();
$config["basepath"] = getcwd() . '/';
include("lib/config.php");

// The none cdn does request handling for assets directly, so it needs ty bypass this check.
if(CDN === 'none') include("lib/core.php");

if (!empty($_SERVER['HTTP_ACCEPT']) && $_SERVER['REQUEST_METHOD'] == "GET") {
	if(!strstr($_SERVER['HTTP_ACCEPT'], "text/html") && !strstr($_SERVER['HTTP_ACCEPT'], "application/json") && $_SERVER['HTTP_ACCEPT'] != "*/*") exit("not an image");
}

// This is the more desirable point to initialize.
if(CDN !== 'none') include("lib/core.php");



$urlpath = getURLPath();
$target = explode("?", $urlpath)[0];



$view->assign("urltarget", $target);

if (empty($target)) {
	$target = "home";
}

$urlparts = explode("/", $target);

//TODO(Rennorb) @cleanup: This routing mess...
if ($urlparts[0] == "download") {
	include("download.php");
	exit();
}

if ($urlparts[0] == "api") {
	array_shift($urlparts);
	if(count($urlparts) > 0 && $urlparts[0] === 'v2') {
		array_shift($urlparts);
		include("lib/api/v2.php");
	}
	else {
		include("lib/api/v1.php");
	}
	exit();
}

if ($urlparts[0] == "notification") {
	include("lib/notification.php");
	exit();
}
if ($urlparts[0] == "notifications") {
	include("notifications.php");
	exit();
}

$typewhitelist = array("terms", "updateversiontags", "files", "show", "edit", "edit-uploadfile", "edit-deletefile", "list", "accountsettings", "logout", "login", "home", "get-assetlist", "moderate");

if (!in_array($urlparts[0], $typewhitelist)) {
	$modid = $con->getOne("select assetid from `mod` where urlalias=?", array($urlparts[0]));
	if ($modid) {
		$urlparts = array("show", "mod", $modid);
	} else {
		showErrorPage(HTTP_NOT_FOUND);
	}
}

// Try to compose filename from the first two segemnts of the url:
// edit/profile -> edit-profile.php 
$filename = implode("-", array_slice($urlparts, 0, 2)) . ".php";

if (file_exists($filename)) {
	include($filename);
	exit();
} 


//TODO(Rennorb) @cleanup: All of this can only happen for 'mod' and 'release', since those are the only two asset types.
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

showErrorPage(HTTP_NOT_FOUND);
