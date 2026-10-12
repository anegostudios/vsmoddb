<?php
/**
 * @var array  $user
 * @var object $con
 */

if(empty($urlparts)) {
	fail(HTTP_BAD_REQUEST);
}

$requestId = filter_var($urlparts[0], FILTER_VALIDATE_INT);
if($requestId === false) fail(HTTP_BAD_REQUEST, 'Malformed query param.');

validateMethod('POST');
validateActionToken();
validateUserNotBanned();

$isModerator = canModerate(null, $user);

$sendNotification = filter_input(INPUT_POST, 'sendNotification', FILTER_VALIDATE_BOOL);
if(!$isModerator && $sendNotification !== null)  fail(HTTP_BAD_REQUEST, 'Only moderators may specify to send notifications.');

$oldData = $con->getRow("SELECT kind, stateFlags, initiatorUserId, referenceId FROM moderationRequests WHERE requestId = $requestId"); // @security: $requestId is validate to be int, therefore sql inert.

if(!$oldData)  fail(HTTP_NOT_FOUND, 'Request not found.');
if($oldData['initiatorUserId'] !== $user['userId'] && !$isModerator)   fail(HTTP_FORBIDDEN, 'You do not have access to this request.');
if($oldData['stateFlags'] & MOD_REQUEST_FLAG_CLOSED)   fail(HTTP_BAD_REQUEST, 'Request was already closed.');

$newState = filter_input(INPUT_POST, 'newState', FILTER_UNSAFE_RAW);
switch($newState) {
	case 'solved':
		if(!$isModerator)  fail(HTTP_BAD_REQUEST, 'Only moderators may transition states explicitly.');

		$newState = MOD_REQUEST_FLAG_CLOSED;
		$auditLog = AUDIT_LOG_KIND_REPORT_RESOLVE;
		$auditLogFlags = AUDIT_LOG_FLAG_SOLVED;
		$newReleaseState = RELEASE_STATUS_RELEASED;

		$sqlEventValues = "($requestId, {$user['userId']}, ".REPORT_EVENT_CHANGE_STAUS.", $newState)";
		break;

	case 'requireAction':
		if(!$isModerator)  fail(HTTP_BAD_REQUEST, 'Only moderators may transition states explicitly.');

		$newState = MOD_REQUEST_FLAG_USER_ACTION_REQUIRED;
		$auditLog = 0;
		$auditLogFlags = 0;
		$newReleaseState = RELEASE_STATUS_AWAITING_CHANGES;

		$sqlEventValues[] = "($requestId, {$user['userId']}, ".REPORT_EVENT_CHANGE_STAUS.", $newState)";
		break;

	case 'wontFix':
		// The user that opened the request may dismiss it aswell.
		//if(!$isModerator)  fail(HTTP_BAD_REQUEST, 'Only moderators may transition states explicitly.');

		$newState = MOD_REQUEST_FLAG_CLOSED | MOD_REQUEST_FLAG_WONTFIX;
		$auditLog = AUDIT_LOG_KIND_REPORT_RESOLVE;
		$auditLogFlags = AUDIT_LOG_FLAG_WONT_FIX;
		$newReleaseState = RELEASE_STATUS_NOT_ACCEPTABLE; // unreachable
		$reason = trimHtml(sanitizeHtml($_POST['reason']));

		$sqlEventValues[] = "($requestId, {$user['userId']}, ".REPORT_EVENT_CHANGE_STAUS.", $newState)";
		break;
		
	case 'spam':
		if(!$isModerator)  fail(HTTP_BAD_REQUEST, 'Only moderators may transition states explicitly.');

		$newState = MOD_REQUEST_FLAG_CLOSED | MOD_REQUEST_FLAG_SPAM;
		$auditLog = AUDIT_LOG_KIND_REPORT_RESOLVE;
		$auditLogFlags = AUDIT_LOG_FLAG_SPAM;
		$newReleaseState = RELEASE_STATUS_NOT_ACCEPTABLE;
		$reason = trimHtml(sanitizeHtml($_POST['reason']));

		$sqlEventValues[] = "($requestId, {$user['userId']}, ".REPORT_EVENT_CHANGE_STAUS.", $newState)";
		break;

	case 'open':
		// The user may reopen their request or signal that they have addressed a requests, so no mod check here.
		//if(!$isModerator)  fail(HTTP_BAD_REQUEST, 'Only moderators may transition states explicitly.');

		if($oldData['stateFlags'] & MOD_REQUEST_FLAG_USER_ACTION_REQUIRED) {
			if($oldData['stateFlags'] & MOD_REQUEST_FLAG_CLOSED)  fail(HTTP_BAD_REQUEST, 'Cannot reopen closed review request.');

			$newState = 0;
			$auditLog = AUDIT_LOG_KIND_REPORT_REOPEN;
			$auditLogFlags = 0;
			$newReleaseState = RELEASE_STATUS_AWAITING_REVIEW;

			$sqlEventValues[] = "($requestId, {$user['userId']}, ".REPORT_EVENT_CHANGE_STAUS.", $newState)";
			break;
		}
		else if($oldData['stateFlags'] & MOD_REQUEST_FLAG_CLOSED) {
			$newState = 0;
			$auditLog = AUDIT_LOG_KIND_REPORT_REOPEN;
			$auditLogFlags = 0;
			$newReleaseState = false;

			$sqlEventValues[] = "($requestId, {$user['userId']}, ".REPORT_EVENT_CHANGE_STAUS.", $newState)";
			break;
		}
		// else fallthough. If we are not closed just treat this as a message without state transition.

	case null: // Also matches false, but that is ok because there is no real filter on the value either way.
		$newState = false;
		$auditLog = 0;
		$auditLogFlags = 0;
		$newReleaseState = false;
		$sqlEventValues  = []; // ok, just the message
		break;

	default:
		fail(HTTP_BAD_REQUEST, "Unknown new state '$newState'");
}

$message = filter_input(INPUT_POST, 'message', FILTER_UNSAFE_RAW);
$message = $message ? trimHtml(sanitizeHtml($message)) : null;

if($message) array_unshift($sqlEventValues, "($requestId, {$user['userId']}, ".REPORT_EVENT_MESSAGE.", 0)");
else if(!($newState & MOD_REQUEST_FLAG_SPAM))   fail(HTTP_BAD_REQUEST, 'Missing message.');


//
// do the thing
//

$con->startTrans();

$con->execute("INSERT INTO moderationRequestEvents (requestId, userId, kind, data) VALUES ".implode(',', $sqlEventValues));
$resolutionEvt = $con->insert_ID(); // first event should be message if that exists.

if($message) $con->execute("INSERT INTO moderationRequestMessages (eventId, text, textSearchable) VALUES (?, ?, ?)", [$resolutionEvt, $message, textContent($message)]);

if($auditLog === AUDIT_LOG_KIND_REPORT_RESOLVE) {
	$con->execute("UPDATE moderationRequests SET stateFlags = $newState, resolved = NOW(), resolverUserId = {$user['userId']}, resolutionEvt = $resolutionEvt WHERE requestId = $requestId");
}
else if($newState !== false) {
	$con->execute("UPDATE moderationRequests SET stateFlags = $newState WHERE requestId = $requestId");
}

if($sendNotification || $newState & MOD_REQUEST_FLAG_USER_ACTION_REQUIRED) {
	$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES ('.NOTIFICATION_REQUEST_RESOLVED.', ?, ?) ', [$oldData['initiatorUserId'], $requestId]);
}

if($oldData['kind'] === MOD_REQUEST_KIND_MANUAL_REVIEW && $newReleaseState) {
	$con->execute("UPDATE modReleases SET `status` = $newReleaseState WHERE releaseId = {$oldData['referenceId']}");
	logAuditEvent(AUDIT_LOG_KIND_RELEASE_CHANGE_STATUS, $oldData['referenceId'], null, ($newReleaseState << 1) | ($isModerator ? AUDIT_LOG_FLAG_MODACTION : 0));
}

if($auditLogFlags) {
	logAuditEvent(AUDIT_LOG_KIND_REPORT_RESOLVE, $requestId, null, $auditLogFlags | ($isModerator ? AUDIT_LOG_FLAG_MODACTION : 0));
}
else if($auditLogFlags === 0) {
	logAuditEvent(AUDIT_LOG_KIND_REPORT_REOPEN, $requestId, null, $isModerator ? AUDIT_LOG_FLAG_MODACTION : 0);
}

$ok = $con->completeTrans();
if(!$ok) fail(MSG_CLASS_ERROR, 'Internal database error');
good();
