<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}
if (!$user['roleid']) {
	$view->display("403");
	exit();
}

$classname = ucfirst($assettype) . "Editor";

if (class_exists($classname)) {
	$asseteditor = new $classname;
} else {
	$asseteditor = new AssetEditor($assettype);
}

$asseteditor->load();
$asseteditor->display();
