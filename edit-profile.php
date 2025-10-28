<?php
if(DB_READONLY) showReadonlyPage();

$userHash = $urlparts[2] ?? null;
if(empty($userHash)) showErrorPage(HTTP_BAD_REQUEST, 'Missing user hash.');

$shownUser = getUserByHash($userHash, $con);
if (empty($shownUser)) showErrorPage(HTTP_NOT_FOUND, 'User not found.');

if (!canEditProfile($shownUser, $user)) showErrorPage(HTTP_FORBIDDEN);

if (!empty($_POST['save'])) {	
	$ok = $con->execute('UPDATE users SET bio = ? WHERE userId = ?', [sanitizeHtml($_POST['bio']), $shownUser['userId']]);
	if ($ok) {
		// addMessage(MSG_CLASS_OK, 'New profile information saved.');

		if($shownUser['userId'] == $user['userId']) $log = 'Changed their profile bio.';
		else $log = "Changed #{$shownUser['userId']} ({$shownUser['name']})s profile bio.";
		logAssetChanges([$log], null);
		
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