<?php

$config = array();
$config["basepath"] = getcwd() . '/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.at";
define("DEBUG", 1);

include("lib/core.php");

$files = $con->getAll("select file.*, `release`.releaseid from `release` join asset on (`release`.assetid = asset.assetid) join file on (asset.assetid = file.assetid)");

echo count($files). " files\r\n";

foreach ($files as $file) {
	$filepath = "files/asset/{$file['assetid']}/{$file['filename']}";
	$returncode = null;
	$idver = exec("mono util/modpeek.exe -i -f ".escapeshellarg($filepath), $unused, $returncode);
	if ($returncode == 0) {
		echo $file['filename']." --- {$idver}\r\n";
		$parts = explode(":", $idver);
		if (strstr($idver, "expandedfoods") || strstr($idver, "bettercrates") || strstr($idver, "cobbandits") || strstr($idver, "lichen") || strstr($idver, "extrachests")) continue;
		$con->Execute("update `release` set modidstr=?, detectedmodidstr=?, modversion=? where releaseid=?", array($parts[0], $parts[0], $parts[1], $file['releaseid']));
	} else {
		echo $file['filename']." --- ERROR\r\n";
	}
}
