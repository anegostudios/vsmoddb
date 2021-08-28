<?php

if (empty($assettype)) {
	$view->display("404.tpl");
	exit();
}

$classname = ucfirst($assettype) . "List";

if (class_exists($classname)) {
	$assetlist = new $classname;
} else {
	$assetlist = new AssetList($assettype);
}

$assetlist->load();
$assetlist->display();
