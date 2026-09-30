<?php
if(DB_READONLY) showReadonlyPage();

$userHash = $urlparts[2] ?? null;
if(empty($userHash)) showErrorPage(HTTP_BAD_REQUEST, 'Missing user hash.');

$shownUser = getUserByHash($userHash);
if (empty($shownUser)) showErrorPage(HTTP_NOT_FOUND, 'User not found.');

if (!canEditProfile($shownUser, $user)) showErrorPage(HTTP_FORBIDDEN);
if ($user['isBanned'])  showErrorPage(HTTP_FORBIDDEN, 'You are currently banned.');

if (!empty($_POST['save'])) {
	validateActionToken();

	$old = $shownUser['bio'];
	$new = trimHtml(sanitizeHtml($_POST['bio']));

	$diff = createAuditLogDiff($old, $new);
	if(!$diff) {
		forceRedirectAfterPOST();
		exit();
	}

	$con->startTrans();

	$con->execute('UPDATE users SET bio = ? WHERE userId = ?', [$new, $shownUser['userId']]);

	$logFlags = $shownUser['userId'] == $user['userId'] ? 0 : AUDIT_LOG_FLAG_MODACTION;
	logAuditEvent(AUDIT_LOG_KIND_USER_CHANGE_BIO, $shownUser['userId'], $diff, $logFlags);

	$ok = $con->completeTrans();
	if ($ok) {
		forceRedirectAfterPOST();
		exit();
	}

	addMessage(MSG_CLASS_ERROR, 'Internal Server Error.');
}

cspAllowTinyMceComment();

if($shownUser['userId'] == $user['userId']) $view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);
$view->assign('userHash', $userHash);
$view->assign('bio', $shownUser['bio']);
$view->display('edit-profile.tpl');