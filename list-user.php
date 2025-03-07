<?php

if ($user['rolecode'] !== 'admin' && $user['rolecode'] !== 'moderator')  {
	$view->display("403");
	exit();
}

$view->assign("columns", array(
	array("cssclassname" => "", "code" => "name", "title" => "Name"), 
	array("cssclassname" => "", "code" => "email", "title" => "E-Mail"), 
	array("cssclassname" => "", "code" => "lastonline", "title" => "Last online", "format" => "date"),
	array("cssclassname" => "", "code" => "banneduntil", "title" => "Banned until", "format" => "date"),
));

$view->assign("entrycode", "user");
$view->assign("entryplural", "Users");
$view->assign("entrysingular", "User");

$searchvalues = array(
	"name" => $_GET["name"] ?? '',
);
$view->assign("searchvalues", $searchvalues);

if (isset($searchvalues["name"])) {
	$view->assign("rows", $con->getAll("select * from user where name like ? limit 500", array("%".substr($searchvalues['name'], 0, 20)."%")));
} else {
	$view->assign("rows", array());
}

$view->display("list-user");
