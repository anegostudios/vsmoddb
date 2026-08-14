<?php

header_remove('X-Powered-By');

$config = array();
$config["basepath"] = getcwd() . '/';
include("lib/config.php");

// The none cdn does request handling for assets directly, so it needs ty bypass this check.
if(CDN === 'none') include("lib/core.php");

if (!empty($_SERVER['HTTP_ACCEPT']) && $_SERVER['REQUEST_METHOD'] == "GET") {
	if(!str_contains($_SERVER['HTTP_ACCEPT'], "text/html") && !str_contains($_SERVER['HTTP_ACCEPT'], "application/json") && $_SERVER['HTTP_ACCEPT'] != "*/*") exit("not an image");
}

// This is the more desirable point to initialize.
if(CDN !== 'none') include("lib/core.php");



$urlpath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$urlpath = trim($urlpath, " \n\r\t\v\0/"); // Strip spaces and slashes from start / end.
if(empty($urlpath))  $urlpath = 'home';

// @security: Filter out directory traversal segments.
// Just discard them completely, they are not used in any actual application.
$urlparts = array_filter(explode('/', $urlpath), fn($s) => !str_starts_with($s, '.'));

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

//NOTE(Rennorb): Technically we should only count the public mods, but in reality this probably doesn't matter for production and just counting all mods makes the query simpler.
$view->assign('totalModCount', $con->getOne('SELECT COUNT(*) from mods'), null, true);
$view->assign('headerHighlight', null, null, true);
$view->assign("assetserver", $config['assetserver']);

if(DB_READONLY) addMessage(MSG_CLASS_OK.' permanent', 'We are currently in readonly mode. All editing is disabled, but you can still browse and download.');


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
		exit(require($urlparts[0].'.php'));
	
	case 'notification':
		exit(require("lib/notification.php"));

	case 'webhooks':
		array_shift($urlparts);
		exit(require("lib/webhook-handlers.php"));

	case 'list':
	case 'show':
	case 'edit':
	case 'moderate':
	case 'cmd':
		// Try to compose filename from the first two segemnts of the url:
		// edit/profile -> edit-profile.php 
		$filename = implode("-", array_slice($urlparts, 0, 2)) . ".php";
		if (file_exists($filename)) {
			exit(require($filename));
		}

		// If we get here its 404 not found. Ignore the aliases, these prefixes are reserved.
		break;

	case 't':
		if(is_numeric($urlparts[1] ?? null))
			exit(require('ticket.php'));
		else if($urlpath == 't')
			exit(require('ticket-list-for-moderators.php'));
		else
			exit(require('ticket-list.php'));

	default: // @security: Check for url-aliases last. Don't allow mods to overwrite urls.
		if ($assetId = $con->getOne('select assetId from mods where urlAlias = ?', [$urlparts[0]])) {
			$urlparts = ['show', 'mod', $assetId]; // Update $urlparts to supply the correct assetId to the handler.
			exit(require('show-mod.php'));
		}
}

showErrorPage(HTTP_NOT_FOUND);
