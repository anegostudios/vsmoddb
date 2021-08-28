<?php

$data = array("assets" => array());

if (!empty($_GET['assettypeid'])) {
	$data["assets"] = $con->getAll("select name, assetid from asset where assettypeid=?", array($_GET["assettypeid"]));
}

exit(json_encode($data));