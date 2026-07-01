<?php
/** @var object $con */
/** @var object $view */
/** @var array $config */
/** @var array<string> $urlparts */

if(empty($user['userId']))   showErrorPage(HTTP_UNAUTHORIZED);

if(empty($urlparts[2]))   showErrorPage(HTTP_NOT_FOUND);
$targetUserHash = $urlparts[2];
if(($targetUserHash !== 'self' && !ctype_xdigit($targetUserHash)))   showErrorPage(HTTP_NOT_FOUND);

require($config['basepath'].'lib/moderation.php');

if($targetUserHash === 'self' || $targetUserHash === $user['hash']) {
	$shownUser = $user;
}
else if(canModerate(null, $user)) {
	$shownUser = getUserByHash($targetUserHash);
	if(!$shownUser)   showErrorPage(HTTP_NOT_FOUND);
}
else {
	// Non-moderators can only view their own tickets.
	header('Location: /t/u/self', true, HTTP_FOUND);
	exit();
}


$tickets = $con->getAll(<<<SQL
	SELECT requestId, kind, category, stateFlags, IF(LENGTH(requestSearchable) > 256, CONCAT(SUBSTR(requestSearchable, 1, 256), '...'), requestSearchable) AS requestSearchable
	FROM moderationRequests
	WHERE initiatorUserId = {$shownUser['userId']}
	ORDER BY created DESC
SQL); // @security: $shownUser['userId'] comes from the database and is int, therefore sql inert.


$view->assign('pagetitle', $shownUser['userId'] == $user['userId'] ? "My Moderation Requests - " : "Moderation Requests initiated by {$shownUser['name']} - ");
$view->assign('shownUser', $shownUser, null, true);
$view->assign('tickets', $tickets, null, true);
$view->display('ticket-list');
