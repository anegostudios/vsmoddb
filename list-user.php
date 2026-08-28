<?php
/**
 * @var object $view
 * @var object $con
 * @var array? $user
 */

if($user['roleCode'] !== 'admin' && $user['roleCode'] !== 'moderator') showErrorPage(HTTP_FORBIDDEN);

$searchParameters = [
	"name" => $_GET["name"] ?? '',
];

$sqlColumns = '`name`, email, HEX(`hash`) AS `hash`, created, bannedUntil';

if (!empty($searchParameters["name"])) {
	$resultRows = $con->getAll(<<<SQL
			SELECT $sqlColumns
			FROM users
			WHERE `name` = ?
		UNION 
			SELECT $sqlColumns
			FROM users
			WHERE `name` LIKE ?
			LIMIT 500
	SQL, [$searchParameters['name'], "%".escapeStringForLikeQuery($searchParameters['name'])."%"]);
} else {
	$resultRows = $con->getAll(<<<SQL
		SELECT $sqlColumns
		FROM users
		ORDER BY created DESC
		LIMIT 500
	SQL);
}

$view->assign('headerHighlight', HEADER_HIGHLIGHT_ADMIN_TOOLS, null, true);
$view->assign('searchParameters', $searchParameters);
$view->assign('rows', $resultRows);
$view->display('list-user');
