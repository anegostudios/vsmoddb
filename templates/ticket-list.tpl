<?php
/** @var array $tickets */
/** @var array $shownUser */
/** @var array $user */

$isModerator = canModerate(null, $user);

?>
{include file="header"}

<h2 style="margin-bottom: .5em;">
<? if($shownUser['userId'] === $user['userId']): ?>
	<span>My Moderation Requests</span>
<? else: ?>
	<a href="/t/">Moderation Requests</a> / <a href="/show/user/<?= $shownUser['hash'] ?>" target="_blank"><?= escapeHtml($shownUser['name']) ?><? if($shownUser['isBanned']): ?>&nbsp;<span style="color:red;">[currently restricted]</span><? endif; ?></a>
<? endif; ?>
</h2>

<div class="ticket-list">
<? foreach($tickets as $ticket): ?>
	<a href="/t/<?= $ticket['requestId'] ?>"<? if($ticket['stateFlags'] & MOD_REQUEST_FLAG_CLOSED): ?> class="closed"<? endif; ?>>
		<h3><?= stringifyModerationRequestKind($ticket) ?> <span class="tag"><?= stringifyModerationRequestStateForUser($ticket['stateFlags']) ?></span></h3>
		<div><?= escapeHtml($ticket['requestSearchable']) ?></div>
	</a>
<? endforeach; if(empty($tickets)): ?>
	<div>
		<h3>Nothing Here</h3>
		<div>No requests here right now.</div>
	</div>
<? endif; ?>
</div>

{include file="footer"}