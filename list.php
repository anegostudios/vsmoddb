<?php

if (empty($assettype)) showErrorPage(HTTP_BAD_REQUEST, "Missing assettype.");

$classname = ucfirst($assettype) . "List";

if (class_exists($classname)) {
	$assetlist = new $classname;
} else {
	$assetlist = new AssetList($assettype);
}

$assetlist->load();
$assetlist->display();
