<?php

if ($user['rolecode'] != 'admin') exit("noprivilege");

$view->assign("columns", array(array("cssclassname" => "", "code" => "name", "title" => "Name"), array("cssclassname" => "", "code" => "color", "title" => "Color", "datatype" => "color")));
$view->assign("entrycode", "tag");
$view->assign("entryplural", "Tags");
$view->assign("entrysingular", "Tag");

if (!empty($_GET["deleted"])) {
	addMessage(MSG_CLASS_OK, 'Tag deleted.');
}
if (!empty($_GET["saved"])) {
	addMessage(MSG_CLASS_OK, 'Tag saved.');
}


$view->assign('rows', $con->getAll("SELECT *, LPAD(HEX(color), 8, '0') AS color FROM Tags ORDER BY kind, name"));


$gameVersionStrings = $con->getCol('SELECT version FROM GameVersions ORDER BY version DESC');
$gameVersionStrings = array_map('formatSemanticVersion', $gameVersionStrings);
$view->assign('gameVersionStrings', $gameVersionStrings, null, true);

$view->assign('headerHighlight', HEADER_HIGHLIGHT_ADMIN_TOOLS, null, true);
$view->display("list-tag");
