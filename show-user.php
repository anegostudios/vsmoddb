<?php

$userHash = $urlparts[2] ?? null;
$shownUser = null;

if (strlen($userHash) > 20) {
	showErrorPage(HTTP_BAD_REQUEST);
	exit();
}

if (empty($userHash) || empty($shownUser = getUserByHash($userHash))) {
	showErrorPage(HTTP_NOT_FOUND);
	exit();
}

$sqlWhereExt = (isset($user) && $shownUser['userId'] == $user['userId']) || canModerate($shownUser, $user) ? '' : ' and a.statusId = 2'; // show drafts if owner or mod
$userMods = $con->getAll("
	SELECT
		a.assetId, a.name, a.createdByUserId, a.statusId,
		m.modId, m.urlAlias, m.summary, m.downloads, m.comments,
		logo.cdnPath AS logoCdnPath,
		logo.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
		s.code AS statusCode
	FROM
		assets a
		JOIN mods m ON m.assetId = a.assetId
		LEFT JOIN status s ON s.statusId = a.statusId
		LEFT JOIN files AS logo ON logo.fileId = m.cardLogoFileId
		LEFT JOIN modTeamMembers t ON t.modId = m.modId
	WHERE
		(a.createdByUserId = {$shownUser['userId']} OR t.userId = {$shownUser['userId']}) $sqlWhereExt
	GROUP BY a.assetId
	ORDER BY a.created DESC
");

foreach ($userMods as &$mod) {
	$mod['tags'] = [];
	$mod['from'] = $shownUser['name'];
	$mod['dbPath'] = formatModPath($mod);
}
unset($mod);

if (canModerate($shownUser, $user)) {
	$logs = $con->getAll("
		SELECT l.created, l.kind, l.flags, l.referenceId,
		COALESCE(l.info, c.textShort) AS info,
		HEX(u.hash) AS `hash`,
		COALESCE(ma.name, u.name) AS referencedName,
		COALESCE(c.assetId, m.assetId, r.assetId, l.referenceId) AS assetId
		FROM auditLogs l
		LEFT JOIN users u ON l.kind IN (".AUDIT_LOG_KIND_USER_CHANGE_BIO.','.AUDIT_LOG_KIND_USER_WARN.','.AUDIT_LOG_KIND_USER_BAN.','.AUDIT_LOG_KIND_USER_REDEEM.") AND u.userId = l.referenceId
		LEFT JOIN mods m on l.kind IN (".AUDIT_LOG_KIND_MOD_CREATE.','.AUDIT_LOG_KIND_MOD_DELETE.','.AUDIT_LOG_KIND_MOD_CHANGE_NAME.','.AUDIT_LOG_KIND_MOD_CHANGE_SUMMARY.','.AUDIT_LOG_KIND_MOD_CHANGE_DESCRIPTION.','.AUDIT_LOG_KIND_MOD_CHANGE_URL_ALIAS.','.AUDIT_LOG_KIND_MOD_CHANGE_TAGS.','.AUDIT_LOG_KIND_MOD_CHANGE_IMAGES.','.AUDIT_LOG_KIND_MOD_CHANGE_THUMBNAIL.','.AUDIT_LOG_KIND_MOD_CHANGE_THUMBNAIL_WEB.','.AUDIT_LOG_KIND_MOD_CHANGE_OWNER_INITIATED.','.AUDIT_LOG_KIND_MOD_CHANGE_OWNER_RESOLVED.','.AUDIT_LOG_KIND_MOD_CHANGE_STATUS.','.AUDIT_LOG_KIND_MOD_CHANGE_UPLOAD_LIMIT.','.AUDIT_LOG_KIND_MOD_CHANGE_LINK.','.AUDIT_LOG_KIND_MOD_CHANGE_CATEGORY.','.
		
		AUDIT_LOG_KIND_MOD_MEMBER_INVITE_INITIATED.','.AUDIT_LOG_KIND_MOD_MEMBER_INVITE_CHANGED.','.AUDIT_LOG_KIND_MOD_MEMBER_INVITE_RESOLVED.','.AUDIT_LOG_KIND_MOD_MEMBER_PERMISSION_CHANGED.','.AUDIT_LOG_KIND_MOD_MEMBER_REMOVED.") AND m.modId = l.referenceId
		LEFT JOIN modReleases r on l.kind IN (".AUDIT_LOG_KIND_RELEASE_CREATE.','.AUDIT_LOG_KIND_RELEASE_RETRACT.','.AUDIT_LOG_KIND_RELEASE_CHANGE_IDENTIFIER.','.AUDIT_LOG_KIND_RELEASE_CHANGE_VERSION.','.AUDIT_LOG_KIND_RELEASE_CHANGE_COMPAT.','.AUDIT_LOG_KIND_RELEASE_CHANGE_FILE.','.AUDIT_LOG_KIND_RELEASE_CHANGE_CHANGELOG.','.AUDIT_LOG_KIND_RELEASE_CHANGE_RETRACTION.") AND r.releaseId = l.referenceId
		LEFT JOIN comments c ON l.kind IN (".AUDIT_LOG_KIND_COMMENT_CREATE.','.AUDIT_LOG_KIND_COMMENT_DELETE.','.AUDIT_LOG_KIND_COMMENT_EDIT.") AND c.commentId = l.referenceId
		LEFT JOIN mods rm ON rm.modId = r.modId
		LEFT JOIN assets ma ON ma.assetId = COALESCE(m.assetId, c.assetId, rm.assetId)
		WHERE l.initiatorUserId = {$shownUser['userId']}
		ORDER BY l.created DESC
		LIMIT 100
	");
	$view->assign('auditLogs', $logs, null, true);
}

if($shownUser['userId'] == $user['userId']) $view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);

$view->assign('mods', $userMods);
$view->assign('user', $user);
$view->assign('shownUser', $shownUser, null, true);
$view->display('show-user');
