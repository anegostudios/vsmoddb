<?php

if ($user['roleCode'] !== 'admin' && $user['roleCode'] !== 'moderator') showErrorPage(HTTP_FORBIDDEN);

$view->assign("columns", array(
	["code" => "name", "title" => "Name"],
	["code" => "email", "title" => "E-Mail"],
	["code" => "created", "title" => "First Login", "format" => "date"],
	["code" => "lastOnline", "title" => "Last online", "format" => "date"],
	["code" => "bannedUntil", "title" => "Banned until", "format" => "date"],
));

$searchvalues = array(
	"name" => $_GET["name"] ?? '',
);
$view->assign("searchvalues", $searchvalues);

if (isset($searchvalues["name"])) {
	$view->assign("rows", $con->getAll(<<<SQL
		SELECT *, HEX(`hash`) AS `hash`
		FROM users
		WHERE name LIKE ?
		LIMIT 500
	SQL, ["%".escapeStringForLikeQuery($searchvalues['name'])."%"]));
} else {
	$view->assign("rows", []);
}

$view->assign('headerHighlight', HEADER_HIGHLIGHT_ADMIN_TOOLS, null, true);
$view->display('list-user');
