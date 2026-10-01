<?php 
/**
 * @var array  $user
 * @var string $cspNonce
 * @var array  $ticket
 */

$isModerator = canModerate(null, $user);

switch($ticket['kind']) {
	case MOD_REQUEST_KIND_REPORT_MOD:
		$targetLink = "/show/mod/\{$ticket['assetId']}";
		$targetLabel = escapeHtml($ticket['modName']);
		break;

	case MOD_REQUEST_KIND_REPORT_COMMENT:
		$targetLink = "/show/mod/\{$ticket['assetId']}#cmt-\{$ticket['referenceId']}";
		$targetLabel = 'a comment on '.escapeHtml($ticket['modName']);
		break;

	case MOD_REQUEST_KIND_MANUAL_REVIEW:
		$targetLink = "/edit/release?assetid=\{$ticket['releaseAssetId']}";
		$targetLabel = 'Release v'.formatSemanticVersion($ticket['version']).' of '.escapeHtml($ticket['modName']);

		$ticket['request'] = 'Automated request for manual review because the mod has the capability for '.formatModCapabilities($ticket['capabilities']).'.';
		break;

	default:
		$targetLink = "#";
		$targetLabel = '???';
}

?>
{include file="header" hclass="innercontent with-buttons-bottom moderation-request"}

<div style="padding: 1em 1em 0 1em">
	<h2>
		<span>
		<? if($isModerator): ?>
			<a href="/t/">Moderation Requests</a>
		<? else: ?>
			<a href="/t/u/self">My Moderation Requests</a>
		<? endif; ?>
		</span>
		/
		<span><?= stringifyModerationRequestKind($ticket) ?></span>
		<span class="text-weak">[#<?= $ticket['requestId'] ?>]</span>
		<p class="by-user">About <a href="<?= $targetLink ?>"><?= $targetLabel ?></a><? if($isModerator): ?>, issued by <a href="/show/user/<?= $ticket['initiatorHash'] ?>"><?= escapeHtml($ticket['initiatorName']) ?></a><? endif; ?></p>
	</h2>

	<h3>Request:</h3>
	<div><?= $ticket['request'] /* @security: sanitized on ingest */ ?></div>

	<h3>
		Resolution: <span class="tag"><?= $isModerator ? stringifyModerationRequestState($ticket['stateFlags']) : stringifyModerationRequestStateForUser($ticket['stateFlags']) ?></span>
		<? if($isModerator && ($ticket['stateFlags'] & MOD_REQUEST_FLAG_CLOSED)): ?>
		<p class="by-user">By <a href="/show/user/<?= $ticket['resolverHash'] ?>"><?= escapeHtml($ticket['resolverName']) ?></a> (acting moderator only shown to other moderators)</p>
		<? endif; ?>
	</h3>
	<div><?= ($ticket['stateFlags'] & MOD_REQUEST_FLAG_CLOSED) ? $ticket['resolution'] /* @security: sanitized on ingest */ : 'No resolution yet.' ?></div>
</div>

<? if($isModerator && (~$ticket['stateFlags'] & MOD_REQUEST_FLAG_CLOSED)): ?>
<form class="buttons" method="post">
	<h4>You may resolve this request:</h4>
	<div>
		<textarea id="resolve-reason" name="reason" style="width: 100%;"></textarea>
	</div>
	<input type="hidden" name="at" value="<?= $user['actionToken'] ?>">
	<div>
		<? if($ticket['kind'] == MOD_REQUEST_KIND_MANUAL_REVIEW): ?>
			<button class="button large shine" name="resolution" value="solved">Close as 'Solved'</button>
			<button class="button large shine" name="resolution" value="requireaction">Close as 'Wont Fix'</button>
		<? else: ?>
			<button class="button large shine" name="resolution" value="solved">Grant</button>
			<button class="button large shine" name="resolution" value="wontfix">Request Changes</button>
			<label for="cb-send-notification" style="line-height: 3em; user-select: none;"><label class="toggle" for="cb-send-notification"><input id="cb-send-notification" type="checkbox" name="sendNotification" value="1"></label> Send notification to user</label>
		<? endif; ?>
		<button class="button large btndelete shine" name="resolution" value="spam" style="margin-left:auto;">Dismiss as Spam</button>
	</div>
</form>

<script nonce="<?= $cspNonce ?>">
$(function() { createEditor(R.get('resolve-reason'), tinymceSettingsReport); });
</script>
<? endif; ?>

{include file="footer"}