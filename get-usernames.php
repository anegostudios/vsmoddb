<?php
header('Content-Type: application/json');

if (empty($user) || !isset($_GET['name'])) {
	echo "[]";
	exit();
}

$names = $con->getall("select name from user where name like ?", array($_GET['name'] . '%'));

echo json_encode($names);