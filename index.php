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



$urlpath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$urlpath = trim($urlpath, " \n\r\t\v\0/"); // Strip spaces and slashes from start / end.
if(empty($urlpath))  $urlpath = 'home';

// @security: Filter out directory traversal segments.
// Just discard them completely, they are not used in any actual application.
$urlparts = array_filter(explode('/', $urlpath), fn($s) => !startsWith($s, '.'));

if($urlparts[0] === 'api') { // :ReservedUrlPrefixes
	array_shift($urlparts);
	if(count($urlparts) > 0 && $urlparts[0] === 'v2') {
		array_shift($urlparts);
		include("lib/api/v2.php");
	}
	else {
		include("lib/api/v1/entry.php");
	}
	exit();
}

include("lib/csp.php");

//TODO(Rennorb) @cleanup @perf: Move view initialization here, after api branch.
$view->assign('headerHighlight', null, null, true);


switch($urlparts[0]) { // :ReservedUrlPrefixes
	case 'home':
	case 'terms':
	case 'accountsettings':
	case 'login':
	case 'logout':
	case 'edit-uploadfile':
	case 'edit-deletefile':

	case 'download':
	case 'notifications':
	case 'updateversiontags':
		include($urlparts[0].'.php');
		exit();
	
	case 'notification':
		include("lib/notification.php");
		exit();

	case 'webhooks':
		array_shift($urlparts);
		include("lib/webhook-handlers.php");
		exit();

	case 'list':
	case 'show':
	case 'edit':
	case 'moderate':
	case 'cmd':
		// Try to compose filename from the first two segemnts of the url:
		// edit/profile -> edit-profile.php 
		$filename = implode("-", array_slice($urlparts, 0, 2)) . ".php";
		if (file_exists($filename)) {
			include($filename);
			exit();
		}

		$filename = $urlparts[0].'.php';
		if (file_exists($filename)) {
			$assettype = $urlparts[1];
			include($filename);
			exit();
		}

		break;

	default: // @security: Check for url-aliases last. Don't allow mods to overwrite urls.
		if ($assetId = $con->getOne('select assetId from mods where urlAlias = ?', [$urlparts[0]])) {
			$urlparts = ['show', 'mod', $assetId]; // Update $urlparts for selected header highlighting in the header template.
			include('show-mod.php');
			exit();
		}
}

showErrorPage(HTTP_NOT_FOUND);
