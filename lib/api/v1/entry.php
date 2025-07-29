<?php

//NOTE(Rennorb): This part specifically is split out to enable testing the code and setting up different fail / good handlers for those tests.

header('Content-Type: application/json');

/** @param string $statuscode */
function fail($statuscode)
{
	exit(json_encode(array("statuscode" => $statuscode)));
}

/** @param array $data */
function good($data, $statuscode = "200")
{
	$data["statuscode"] = $statuscode;
	exit(json_encode($data));
}


include "functions.php";
include "logic.php";