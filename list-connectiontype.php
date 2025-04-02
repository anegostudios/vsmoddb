<?php

$view->assign("columns", array(array("cssclassname" => "", "code" => "code", "title" => "Code"), array("cssclassname" => "", "code" => "name", "title" => "Name")));
$view->assign("entrycode", "connectiontype");
$view->assign("entryplural", "Connection types");
$view->assign("entrysingular", "Connection type");

if (!empty($_GET["deleted"])) {
	addMessage(MSG_CLASS_OK, 'Connection type deleted.');
}
if (!empty($_GET["saved"])) {
	addMessage(MSG_CLASS_OK, 'Connection type saved.');
}


$view->assign("rows", $con->getAll("select * from connectiontype"));
$view->display("list");
