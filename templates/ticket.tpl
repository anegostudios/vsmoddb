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

	<ol id="event-stack" class="no-mark">
	<? foreach($ticket['events'] as $eid => $event) \{
		?><li <? if($eid === $ticket['requestEvt']): ?>class="request"<? elseif($eid === $ticket['resolutionEvt']): ?>class="resolution"<? endif; ?>>
			<div class="by-user">
				<?= fancyDate($event['created']) ?> | 
				<? if($event['userId'] === $user['userId']) \{
					?><span>You<span><?
				} else if($event['userId'] === $ticket['initiatorUserId']) \{
					?><a href="/show/user/<?= $event['userHash'] ?>"><?= escapeHtml($event['userName']) ?></a><?
				} else if($isModerator) \{
					?><i class="bx bx-hide" title="Acting moderator only shown to other moderators."></i>&nbsp;<a href="<?= $event['userHash'] ?>"><?= escapeHtml($event['userName']) ?></a><?
				} else \{
					?><span>a Moderator</span><?
				}
				?>
			</div>
			<?
			switch($event['kind']) \{
				case REPORT_EVENT_CHANGE_STAUS:
					?><div class="text-weak">Changed status to '<?= stringifyModerationRequestState($ticket['kind'], $event['data']) ?>'.</div><?
					break;

				case REPORT_EVENT_MESSAGE:
					?><div><?= $event['text'] /* @security: sanitized on ingest */ ?></div><?
					break;

				default:
					?><div>Unknown Event <code>kid: <?= $event['kind'] ?></code>.</div><?
					break;
			} ?>
		</li><?
	} ?>
	</ol>
</div>

<? if($isModerator || (~$ticket['stateFlags'] & MOD_REQUEST_FLAG_CLOSED)): ?>
<form class="buttons" method="post" action="/api/v2/tickets/{$ticket['requestId']}" autocomplete="off">
	<h3>Response:</h3>
	<div>
		<textarea name="message" style="width: 100%;"></textarea>
	</div>

	<? if(!$isModerator && $ticket['stateFlags'] & MOD_REQUEST_FLAG_USER_ACTION_REQUIRED): ?>
		<div class="bg-warning" style="align-items:center;justify-content:center;">A moderator has requested you address something about <a href="<?= $targetLink ?>" target="_blank"><?= $targetLabel ?></a>.</div>
	<? endif; ?>

	<input type="hidden" name="at" value="<?= $user['actionToken'] ?>">
	<div>
		<? if($isModerator): ?>
			<button class="button large shine" >Send</button>
			<? if($ticket['kind'] == MOD_REQUEST_KIND_MANUAL_REVIEW): ?>
				<button class="button large shine" name="newState" value="solved">Send and Grant</button>
				<button class="button large shine" name="newState" value="requireAction">Send and Request Changes</button>
			<? else: ?>
				<button class="button large shine" name="newState" value="solved">Send and Close as 'Solved'</button>
				<button class="button large shine" name="newState" value="wontFix">Send and Close as 'Wont Fix'</button>
				<label for="cb-send-notification" style="line-height: 3em; user-select: none;"><label class="toggle" for="cb-send-notification"><input id="cb-send-notification" type="checkbox" name="sendNotification" value="1"></label> Send notification to user</label>
			<? endif; ?>
			<button class="button large btndelete shine" name="newState" value="spam" style="margin-left:auto;">Dismiss as Spam</button>
		<? else: ?>
			<button class="button large shine">Send</button>
			<? if($ticket['stateFlags'] & MOD_REQUEST_FLAG_USER_ACTION_REQUIRED): ?>
			<button class="button large shine" name="newState" value="open">Send and Mark as Addressed</button>
			<? endif; ?>
			<button class="button large shine" name="newState" value="wontFix" style="margin-left:auto;">Send and Retract Request</button>
		<? endif; ?>
	</div>
</form>

<script nonce="<?= $cspNonce ?>">
$(function() \{ attachMRMessageSendHandlers(document.getElementsByClassName('buttons')[0]); });
</script>
<? endif; ?>

{include file="footer"}