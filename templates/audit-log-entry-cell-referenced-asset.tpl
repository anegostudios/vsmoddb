<? /** @var array $logEntry */ switch($logEntry['kind']) {
case AUDIT_LOG_KIND_LEGACY: ?>
<td class="target">Target #<?= $logEntry['referenceId'] ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CREATE:
case AUDIT_LOG_KIND_MOD_DELETE:
case AUDIT_LOG_KIND_MOD_CHANGE_NAME:
case AUDIT_LOG_KIND_MOD_CHANGE_SUMMARY:
case AUDIT_LOG_KIND_MOD_CHANGE_DESCRIPTION:
case AUDIT_LOG_KIND_MOD_CHANGE_URL_ALIAS:
case AUDIT_LOG_KIND_MOD_CHANGE_TAGS:
case AUDIT_LOG_KIND_MOD_CHANGE_IMAGES:
case AUDIT_LOG_KIND_MOD_CHANGE_THUMBNAIL:
case AUDIT_LOG_KIND_MOD_CHANGE_THUMBNAIL_WEB:
case AUDIT_LOG_KIND_MOD_CHANGE_OWNER_INITIATED:
case AUDIT_LOG_KIND_MOD_CHANGE_OWNER_RESOLVED:
case AUDIT_LOG_KIND_MOD_CHANGE_STATUS:
case AUDIT_LOG_KIND_MOD_CHANGE_UPLOAD_LIMIT:
case AUDIT_LOG_KIND_MOD_CHANGE_LINK:
case AUDIT_LOG_KIND_MOD_CHANGE_CATEGORY:
case AUDIT_LOG_KIND_MOD_MEMBER_INVITE_INITIATED:
case AUDIT_LOG_KIND_MOD_MEMBER_INVITE_CHANGED:
case AUDIT_LOG_KIND_MOD_MEMBER_INVITE_RESOLVED:
case AUDIT_LOG_KIND_MOD_MEMBER_PERMISSION_CHANGED:
case AUDIT_LOG_KIND_MOD_MEMBER_REMOVED: ?>
<td class="target"><a href="/show/mod/<?= $logEntry['assetId'] ?>"><?= escapeHtml($logEntry['referencedName']) ?></a></td>
	<? break;

case AUDIT_LOG_KIND_RELEASE_CREATE:
case AUDIT_LOG_KIND_RELEASE_RETRACT:
case AUDIT_LOG_KIND_RELEASE_CHANGE_IDENTIFIER:
case AUDIT_LOG_KIND_RELEASE_CHANGE_VERSION:
case AUDIT_LOG_KIND_RELEASE_CHANGE_COMPAT:
case AUDIT_LOG_KIND_RELEASE_CHANGE_FILE:
case AUDIT_LOG_KIND_RELEASE_CHANGE_CHANGELOG:
case AUDIT_LOG_KIND_RELEASE_CHANGE_RETRACTION: ?>
<td class="target"><a href="/edit/release?assetid=<?= $logEntry['assetId'] ?>">Release of <?= escapeHtml($logEntry['referencedName']) ?></a></td>
	<? break;

case AUDIT_LOG_KIND_COMMENT_CREATE: ?>
<td class="target"><a href="/show/mod/<?= $logEntry['assetId'] ?>#cmt-<?= $logEntry['referenceId'] ?>"><?= escapeHtml($logEntry['referencedName']) ?></a></td>
	<? break;
case AUDIT_LOG_KIND_COMMENT_DELETE:
case AUDIT_LOG_KIND_COMMENT_EDIT: ?>
<td class="target"><a href="/show/mod/<?= $logEntry['assetId'] ?>#cmt-<?= $logEntry['referenceId'] ?>">Comment on <?= escapeHtml($logEntry['referencedName']) ?></a></td>
	<? break;


case AUDIT_LOG_KIND_USER_CHANGE_BIO:
case AUDIT_LOG_KIND_USER_WARN:
case AUDIT_LOG_KIND_USER_BAN:
case AUDIT_LOG_KIND_USER_REDEEM: ?>
<td class="target"><a href="/show/user/<?= $logEntry['hash'] ?>"><?= escapeHtml($logEntry['referencedName']) ?></a></td>
	<? break;


case AUDIT_LOG_KIND_FILE_CREATE:
case AUDIT_LOG_KIND_FILE_DELETE: ?>
<td class="target">-</td>
	<? break;

case AUDIT_LOG_KIND_REPORT_CREATE:
case AUDIT_LOG_KIND_REPORT_RESOLVE: ?>
<td class="target"><a href="/t/<?= $logEntry['referenceId'] ?>">Report #<?= $logEntry['referenceId'] ?></a></td>
	<? break;

default: ?>
<td class="target"></td>
	<? break;
} ?>