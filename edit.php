<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}

if (!$user['roleid'])  showErrorPage(HTTP_FORBIDDEN);

if ($user['isbanned'])  showErrorPage(HTTP_FORBIDDEN, 'You are currently banned.');

$classname = ucfirst($assettype) . "Editor";

if (class_exists($classname)) {
	$asseteditor = new $classname;
} else {
	$asseteditor = new AssetEditor($assettype);
}

$asseteditor->load();
$asseteditor->display();
