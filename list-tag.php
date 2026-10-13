<?php

if ($user['roleCode'] !== 'admin' && $user['roleCode'] !== 'moderator') showErrorPage(HTTP_FORBIDDEN);

cspReplaceAllowedFetchSources("{$_SERVER['HTTP_HOST']}/api/v2/game-versions {$_SERVER['HTTP_HOST']}/api/v2/game-versions/"); //NOTE(Rennorb): Yes. The CSP api genuinely requires both to be specified here.
cspPushAllowedInlineHandlerHash('sha256-ACiiR9Pq4vOFtqhzTGFgHTXHnUnOEGCMCKPo/Hys5tE='); // addVersionPrompt()
cspPushAllowedInlineHandlerHash('sha256-OV/BCQXzN5T2ARKxgGtnfxHIee+D1qxtNG9wGGJf0Iw='); // clickDelete(event)

$view->assign("columns", array(
	array("cssclassname" => "", "code" => "name", "title" => "Name"),
	array("cssclassname" => "", "code" => "kind", "title" => "Kind"),
	array("cssclassname" => "", "code" => "text", "title" => "Description"),
	array("cssclassname" => "", "code" => "modCount", "title" => "Mods"),
	array("cssclassname" => "", "code" => "color", "title" => "Color", "datatype" => "color")));
$view->assign("entrycode", "tag");
$view->assign("entryplural", "Tags");
$view->assign("entrysingular", "Tag");

if (!empty($_GET["deleted"])) {
	addMessage(MSG_CLASS_OK, 'Tag deleted.');
}
if (!empty($_GET["saved"])) {
	addMessage(MSG_CLASS_OK, 'Tag saved.');
}

// Get all tags, with mods count
$tags = $con->getAll("SELECT
		tag.tagId,
		tag.parentTagId IS NOT NULL AS depth,
		tag.name,
		tag.text,
		tag.kind,
		LPAD(HEX(tag.color), 8, '0') AS color,
		COUNT(DISTINCT CASE WHEN modTag.votes >= 0 THEN modTag.modId END) AS modCount
	FROM tags tag
	LEFT JOIN tags p ON p.tagId = tag.parentTagId
	LEFT JOIN modTags modTag ON modTag.tagId = tag.tagId
	GROUP BY tag.tagId
	ORDER BY COALESCE(p.name, tag.name), tag.parentTagId IS NOT NULL, tag.name
");
$view->assign('rows', $tags);


$gameVersionStrings = $con->getCol('SELECT version FROM gameVersions ORDER BY version DESC');
$gameVersionStrings = array_map('formatSemanticVersion', $gameVersionStrings);
$view->assign('gameVersionStrings', $gameVersionStrings, null, true);

$view->assign('headerHighlight', HEADER_HIGHLIGHT_ADMIN_TOOLS, null, true);
$view->display("list-tag");
