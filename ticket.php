<?php
/**
 * @var array<string> $urlparts
 * @var object        $con
 * @var object        $view
 * @var array         $messages
 * @var array         $user
 */

if(empty($user['userId']))   showErrorPage(HTTP_UNAUTHORIZED);

$requestId = intval($urlparts[1]);
if(!$requestId)   showErrorPage(HTTP_BAD_REQUEST, 'Missing or malformed request id.');

require(SRC_ROOT.'/lib/modinfo.php');

$queryFilterOwnTickets = canModerate(null, $user) ? '' : ' AND r.initiatorUserId = '.$user['userId']; // @security: $user['userId'] comes form the database and is int, therefore sql inert.
//TODO(Rennorb) @cleanup: probably split this into two calls, where the second one is different based in the kind. This is turning into a mess.
$ticket = $con->getRow("
	SELECT r.requestId, r.kind, r.category, r.requestEvt, r.resolutionEvt, r.stateFlags, r.initiatorUserId, r.referenceId, COALESCE(`mod`.modId, modc.modId) AS modId, am.assetId, am.name AS modName,
		i.name AS `initiatorName`, HEX(i.hash) AS `initiatorHash`, 
		m.name AS `resolverName`, HEX(m.hash) AS `resolverHash`,
		rel.assetId AS releaseAssetId, rel.version, mpr.capabilities, r.created
	FROM moderationRequests r
	LEFT JOIN users i ON i.userId = r.initiatorUserId
	LEFT JOIN users m ON m.userId = r.resolverUserId
	LEFT JOIN modReleases rel ON (r.kind = ".MOD_REQUEST_KIND_MANUAL_REVIEW." AND rel.releaseId = r.referenceId)
	LEFT JOIN mods `mod` ON (r.kind = ".MOD_REQUEST_KIND_REPORT_MOD." AND `mod`.modId = r.referenceId) OR (r.kind = ".MOD_REQUEST_KIND_MANUAL_REVIEW." AND `mod`.modId = rel.modId)
	LEFT JOIN comments c ON (r.kind = ".MOD_REQUEST_KIND_REPORT_COMMENT." AND c.commentId = r.referenceId)
	LEFT JOIN assets am ON am.assetId = COALESCE(`mod`.assetId, c.assetId)
	LEFT JOIN mods modc ON (r.kind = ".MOD_REQUEST_KIND_REPORT_COMMENT." AND modc.assetId = am.assetId)
	LEFT JOIN files rfile ON (r.kind = ".MOD_REQUEST_KIND_MANUAL_REVIEW." AND rfile.assetId = rel.assetId)
	LEFT JOIN modPeekResults mpr ON (r.kind = ".MOD_REQUEST_KIND_MANUAL_REVIEW." AND mpr.fileId = rfile.fileId)
	WHERE r.requestId = $requestId $queryFilterOwnTickets
"); // @security: $requestId is validated to be int, therefore sql inert.
if(!$ticket)   showErrorPage(HTTP_NOT_FOUND, 'Moderation Request not found.');

$events = $con->getAssoc("
	SELECT e.eventId, e.kind, e.created, e.data, e.userId, HEX(u.hash) AS userHash, u.name AS userName, m.text
	FROM moderationRequestEvents e
	LEFT JOIN moderationRequestMessages m ON e.kind = ".REPORT_EVENT_MESSAGE." AND m.eventId = e.eventId
	LEFT JOIN users u ON u.userId = e.userId
	WHERE e.requestId = $requestId
	ORDER BY e.created ASC, e.eventId ASC
");

$ticket['events'] = $events;

cspReplaceAllowedFetchSources("{$_SERVER['HTTP_HOST']}/api/v2/tickets/{$requestId}");
cspAllowTinyMceComment();

$view->assign('pagetitle', "Moderation request #{$ticket['requestId']} - ");
$view->assign('ticket', $ticket, null, true);
$view->display('ticket');
