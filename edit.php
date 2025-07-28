<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}

if (!$user['roleId'])  showErrorPage(HTTP_FORBIDDEN);

if ($user['isBanned'])  showErrorPage(HTTP_FORBIDDEN, 'You are currently banned.');

$classname = ucfirst($assettype) . "Editor";

if (class_exists($classname)) {
	$asseteditor = new $classname;
} else {
	$asseteditor = new AssetEditor($assettype);
}

$asseteditor->load();
$asseteditor->display();
