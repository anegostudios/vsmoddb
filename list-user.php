<?php

if ($user['rolecode'] != 'admin')  {
	$view->display("403");
	exit();
}

$view->assign("columns", array(
	array("cssclassname" => "", "code" => "name", "title" => "Name"), 
	array("cssclassname" => "", "code" => "email", "title" => "E-Mail"), 
	array("cssclassname" => "", "code" => "lastonline", "title" => "Last online", "format" => "date")
));

$view->assign("entrycode", "user");
$view->assign("entryplural", "Users");
$view->assign("entrysingular", "User");

$view->assign("rows", $con->getAll("select * from user"));
$view->display("list");