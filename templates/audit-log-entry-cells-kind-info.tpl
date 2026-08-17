<? /** @var array $logEntry */ switch($logEntry['kind']) {
case AUDIT_LOG_KIND_LEGACY: ?>
<td>[LEGACY]</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CREATE: ?>
<td>Created Mod</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_DELETE: ?>
<td>Deleted Mod</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_NAME: ?>
<td>Changed Mod Name</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_SUMMARY: ?>
<td>Change Mod Summary</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_DESCRIPTION: ?>
<td>Changed Mod Description</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_URL_ALIAS: ?>
<td>Changed Mod URL Alias</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_TAGS: ?>
<td>Changed Mod Tags</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_IMAGES: ?>
<td>Changed Mod Images</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_THUMBNAIL: ?>
<td>Changed Mod Thumbnail</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_THUMBNAIL_WEB: ?>
<td>Changed Mod External Thumbnail</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_OWNER_INITIATED: ?>
<td>Initiated Ownership Transfer</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_OWNER_RESOLVED: ?>
<td><? switch($logEntry['flags'] & AUDIT_LOG_FLAGS_MASK_RESOLUTION) {
	case AUDIT_LOG_FLAG_ACCEPTED: echo 'Accepted Ownership'; break;
	case AUDIT_LOG_FLAG_REJECTED: echo 'Rejected Ownership'; break;
	case AUDIT_LOG_FLAG_ABORTED:  echo 'Aborted Ownership Transfer'; break;
} ?></td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_STATUS: ?>
<td>Changed Mod Status</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_UPLOAD_LIMIT: ?>
<td>Changed Mod Upload Limit</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_LINK: ?>
<td><? switch($logEntry['flags'] & AUDIT_LOG_FLAGS_MASK_LINK_CHANGES) {
	case AUDIT_LOG_FLAG_LINK_CHANGE_HOMEPAGE: echo 'Changed Mod Homepage Link'; break;
	case AUDIT_LOG_FLAG_LINK_CHANGE_SOURCE:   echo 'Changed Mod Source Link'; break;
	case AUDIT_LOG_FLAG_LINK_CHANGE_TRAILER:  echo 'Changed Mod Trailer Link'; break;
	case AUDIT_LOG_FLAG_LINK_CHANGE_ISSUES:   echo 'Changed Mod Issue Tracker Link'; break;
	case AUDIT_LOG_FLAG_LINK_CHANGE_WIKI:     echo 'Changed Mod Wiki Link'; break;
	case AUDIT_LOG_FLAG_LINK_CHANGE_DONATE:   echo 'Changed Mod Donation Link'; break;
} ?></td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_CHANGE_CATEGORY: ?>
<td>Changed Mod Category</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_MEMBER_INVITE_INITIATED: ?>
<td>Invited Team Member</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_MEMBER_INVITE_CHANGED: ?>
<td>Changed Member Invite</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_MEMBER_INVITE_RESOLVED: ?>
<td><? switch($logEntry['flags'] & AUDIT_LOG_FLAGS_MASK_RESOLUTION) {
	case AUDIT_LOG_FLAG_ACCEPTED: echo 'Accepted Team Invite'; break;
	case AUDIT_LOG_FLAG_REJECTED: echo 'Rejected Team Invite'; break;
	case AUDIT_LOG_FLAG_ABORTED:  echo 'Aborted Team Member Invite'; break;
} ?></td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_MEMBER_PERMISSION_CHANGED: ?>
<td>Changed Team Member Permission</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_MOD_MEMBER_REMOVED: ?>
<td>Removed Team Member</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_RELEASE_CREATE: ?>
<td>Created Release</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_RELEASE_RETRACT: ?>
<td>Retracted Release</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_RELEASE_CHANGE_IDENTIFIER: ?>
<td>Changed Release ModIdentifier</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_RELEASE_CHANGE_VERSION: ?>
<td>Changed Release Version</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_RELEASE_CHANGE_COMPAT: ?>
<td>Changed Release Game Compatibility</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_RELEASE_CHANGE_FILE: ?>
<td>Changed Release File</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_RELEASE_CHANGE_CHANGELOG: ?>
<td>Changed Release Changelog</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_RELEASE_CHANGE_RETRACTION: ?>
<td>Changed Retraction Reason</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_COMMENT_CREATE: ?>
<td>Created Comment</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_COMMENT_DELETE: ?>
<td>Deleted Comment</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_COMMENT_EDIT: ?>
<td>Edited Comment</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_USER_CHANGE_BIO: ?>
<td>Changed User Bio</td>
<td class="info"><?= formatAuditLogDiff($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_USER_WARN: ?>
<td>Sent User Warning</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_USER_BAN: ?>
<td>Banned User</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_USER_REDEEM: ?>
<td>Redeemed User Ban</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_FILE_CREATE: ?>
<td>Created File</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_FILE_DELETE: ?>
<td>Deleted File</td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;


case AUDIT_LOG_KIND_REPORT_CREATE: ?>
<td>Created Report</td>
<td class="info"><?= $logEntry['info'] /* @security: sanitized on ingest */ ?></td>
	<? break;


case AUDIT_LOG_KIND_REPORT_RESOLVE: ?>
<td><? switch($logEntry['flags'] & AUDIT_LOG_FLAGS_MASK_RESOLUTION) {
	case AUDIT_LOG_FLAG_SOLVED:   echo 'Solved Report'; break;
	case AUDIT_LOG_FLAG_WONT_FIX: echo 'Closed Report'; break;
	case AUDIT_LOG_FLAG_SPAM:     echo 'Closed Report as Spam'; break;
} ?></td>
<td class="info"><?= $logEntry['info'] /* @security: sanitized on ingest */ ?></td>
	<? break;


default: ?>
<td><?= $logEntry['kind'] ?></td>
<td class="info"><?= escapeHtml($logEntry['info']) ?></td>
	<? break;
} ?>