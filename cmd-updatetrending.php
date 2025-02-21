<?php
chdir(dirname(__FILE__));

$config = array();
$config["basepath"] = getcwd() . '/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.at";
define("DEBUG", 1);
include("lib/config.php");
include("lib/core.php");

$mods = $con->getAll("select * from `mod`");

foreach ($mods as $mod) {
	$dls = $con->getOne("select count(*) from downloadip join file on (downloadip.fileid = file.fileid) where file.assetid=? and downloadip.date > date_sub(now(), interval 72 hour)", array($mod['assetid']));
	$cms = $con->getOne("select count(*) from comment where assetid=? and created > date_sub(now(), interval 72 hour)", array($mod['assetid']));
	
	$con->Execute("update `mod` set trendingpoints=? where modid=?", array($dls + 5*$cms, $mod['modid']));
}
