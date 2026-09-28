<?php


require(__DIR__.'/lib/config.php');
require(SRC_ROOT.'/lib/core.php');
require(SRC_ROOT.'/lib/edit-release.php');

$modids = $con->getCol("select modId from mods");

foreach ($modids as $modid) {
	updateGameVersionsCached($modid);
}
