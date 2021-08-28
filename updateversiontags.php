<?php

$versions = array_keys(json_decode(file_get_contents("http://api.vintagestory.at/stable-unstable.json"), true));
$dbtags = $con->getCol("select substr(name, 2) as version from tag where assettypeid=2");
$newversions = array_diff($versions, $dbtags);

foreach ($newversions as $newversion) {
	$con->Execute("insert into tag (assettypeid, tagtypeid, name, color, created) values (?,?,?,?,now())", array(2, 1, "v".$newversion, "#C9C9C9"));
}


echo count($newversions) . " new versions added";
