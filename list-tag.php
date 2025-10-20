<?php

if ($user['roleCode'] != 'admin') showErrorPage(HTTP_FORBIDDEN);

cspReplaceAllowedFetchSources("{$_SERVER['HTTP_HOST']}/api/v2/game-versions {$_SERVER['HTTP_HOST']}/api/v2/game-versions/"); //NOTE(Rennorb): Yes. The CSP api genuinely requires both to be specified here.
cspPushAllowedInlineHandlerHash('sha256-ACiiR9Pq4vOFtqhzTGFgHTXHnUnOEGCMCKPo/Hys5tE='); // addVersionPrompt()
cspPushAllowedInlineHandlerHash('sha256-OV/BCQXzN5T2ARKxgGtnfxHIee+D1qxtNG9wGGJf0Iw='); // clickDelete(event)

$view->assign("columns", array(array("cssclassname" => "", "code" => "name", "title" => "Name"), array("cssclassname" => "", "code" => "color", "title" => "Color", "datatype" => "colo
r")));
$view->assign("entrycode", "tag");
$view->assign("entryplural", "Tags");
$view->assign("entrysingular", "Tag");

if (!empty($_GET["deleted"])) {
	addMessage(MSG_CLASS_OK, 'Tag deleted.');
}
if (!empty($_GET["saved"])) {
	addMessage(MSG_CLASS_OK, 'Tag saved.');
}


$view->assign('rows', $con->getAll("SELECT *, LPAD(HEX(color), 8, '0') AS color FROM tags ORDER BY kind, name"));


$gameVersionStrings = $con->getCol('SELECT version FROM gameVersions ORDER BY version DESC');
$gameVersionStrings = array_map('formatSemanticVersion', $gameVersionStrings);
$view->assign('gameVersionStrings', $gameVersionStrings, null, true);

$view->assign('headerHighlight', HEADER_HIGHLIGHT_ADMIN_TOOLS, null, true);
$view->display("list-tag");
