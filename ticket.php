<?php
/** @var array<string> $urlparts */
/** @var object $con */
/** @var object $view */
/** @var array $messages */
/** @var array $config */

if(empty($user['userId']))   showErrorPage(HTTP_UNAUTHORIZED);

require($config['basepath'].'lib/moderation.php');

$requestId = intval($urlparts[1]);
if(!$requestId)   showErrorPage(HTTP_BAD_REQUEST, 'Missing or malformed request id.');


$queryFilterOwnTickets = canModerate(null, $user) ? '' : ' AND r.initiatorUserId = '.$user['userId']; // @security: $user['userId'] comes form the database and is int, therefore sql inert.
$ticket = $con->getRow("
	SELECT r.requestId, r.kind, r.category, r.request, r.resolution, r.stateFlags, r.initiatorUserId, r.referenceId, COALESCE(`mod`.modId, modc.modId) AS modId, am.name AS modName,
		i.name AS `initiatorName`, HEX(i.hash) AS `initiatorHash`, 
		m.name AS `resolverName`, HEX(m.hash) AS `resolverHash`
	FROM moderationRequests r
	LEFT JOIN users i ON i.userId = r.initiatorUserId
	LEFT JOIN users m ON m.userId = r.resolverUserId
	LEFT JOIN mods `mod` ON (r.kind = ".MOD_REQUEST_KIND_REPORT_MOD." AND `mod`.modId = r.referenceId)
	LEFT JOIN comments c ON (r.kind = ".MOD_REQUEST_KIND_REPORT_COMMENT." AND c.commentId = r.referenceId)
	LEFT JOIN assets am ON am.assetId = COALESCE(`mod`.assetId, c.assetId)
	LEFT JOIN mods modc ON (r.kind = ".MOD_REQUEST_KIND_REPORT_COMMENT." AND modc.assetId = am.assetId)
	WHERE r.requestId = $requestId $queryFilterOwnTickets
"); // @security: $requestId is validated to be int, therefore sql inert.
if(!$ticket)   showErrorPage(HTTP_NOT_FOUND, 'Moderation Request not found.');

$ticket['stateFlags'] = intval($ticket['stateFlags']); // ... ffs mysqli

if(!empty($_POST['resolution'])) {
	validateActionToken();
	if(!canModerate(null, $user))   showErrorPage(HTTP_FORBIDDEN);

	$oldMsgCount = count($messages);

	if($ticket['stateFlags'] & MOD_REQUEST_FLAG_CLOSED) {
		addMessage(MSG_CLASS_ERROR, 'Ticket was already closed, sorry.'); // @hack, but good enough to prevent accidents.
	}

	switch($_POST['resolution']) {
		case 'solved':
			$newState = MOD_REQUEST_FLAG_CLOSED;
			$logFlags = AUDIT_LOG_FLAG_SOLVED;
			$reason = trimHtml(sanitizeHtml($_POST['reason']));
			if(!$reason)  addMessage(MSG_CLASS_ERROR, 'Please provide a reason.');
			break;

		case 'wontfix':
			$newState = MOD_REQUEST_FLAG_CLOSED | MOD_REQUEST_FLAG_WONTFIX;
			$logFlags = AUDIT_LOG_FLAG_WONT_FIX;
			$reason = trimHtml(sanitizeHtml($_POST['reason']));
			if(!$reason)  addMessage(MSG_CLASS_ERROR, 'Please provide a reason.');
			break;

		case 'spam':
			$newState = MOD_REQUEST_FLAG_CLOSED | MOD_REQUEST_FLAG_SPAM;
			$logFlags = AUDIT_LOG_FLAG_SPAM;
			$reason = trimHtml(sanitizeHtml($_POST['reason']));
			// The reason is allowed to be empty here.
			break;

		default: showErrorPage(HTTP_BAD_REQUEST, 'Invalid resolution.');
	}

	if(count($messages) === $oldMsgCount) {
		$con->startTrans();

		$con->execute('UPDATE moderationRequests SET stateFlags = stateFlags | ?, resolved = NOW(), resolverUserId = ?, resolution = ? WHERE requestId = ?', [$newState, $user['userId'], $reason, $requestId]);

		if(!empty($_POST['sendNotification'])) {
			$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES ('.NOTIFICATION_REQUEST_RESOLVED.', ?, ?) ', [$ticket['initiatorUserId'], $requestId]);
		}

		logAuditEvent(AUDIT_LOG_KIND_REPORT_RESOLVE, $requestId, null, $logFlags | AUDIT_LOG_FLAG_MODACTION);

		$ok = $con->completeTrans();
		if(!$ok) addMessage(MSG_CLASS_ERROR, 'Internal database error');
		else {
			forceRedirectAfterPOST();
			exit();
		}
	}
}

cspAllowTinyMceComment();

$view->assign('pagetitle', "Moderation request #{$ticket['requestId']} - ");
$view->assign('ticket', $ticket, null, true);
$view->display('ticket');
