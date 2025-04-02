<?php

if ($user['rolecode'] != 'admin') exit("noprivilege");

$view->assign("columns", array(array("cssclassname" => "", "code" => "assettypename", "title" => "For asset Type"), array("cssclassname" => "", "code" => "name", "title" => "Name"), array("cssclassname" => "", "code" => "color", "title" => "Color", "datatype" => "color")));
$view->assign("entrycode", "tag");
$view->assign("entryplural", "Tags");
$view->assign("entrysingular", "Tag");

if (!empty($_GET["deleted"])) {
	addMessage(MSG_CLASS_OK, 'Tag deleted.');
}
if (!empty($_GET["saved"])) {
	addMessage(MSG_CLASS_OK, 'Tag saved.');
}


$view->assign("rows", $con->getAll("select tag.*, assettype.name as assettypename from tag left join assettype on (tag.assettypeid = assettype.assettypeid) order by tag.assettypeid, tag.tagtypeid, tag.name"));
$view->display("list");
