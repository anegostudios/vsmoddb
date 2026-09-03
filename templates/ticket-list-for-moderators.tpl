<?php
/** @var array $reportedMods */
/** @var array $reportedComments */
/** @var array $user */
?>{include file="header"}

<h2 style="margin-bottom: .5em;">
	<span>Open Moderation Requests</span>
</h2>

<div id="reports-wrapper">
	<div>
		<h3 style="margin-bottom: .5em;">Reported Mods:</h3>
		<div id="reported-mods">
		<? foreach($reportedMods as $mod): ?>
			<div>
				<a href="<?= formatModPath($mod) ?>" target="_blank">
					<img src="<?= empty($mod['logoCdnPath']) ? '/web/img/mod-default.png' : escapeHtml(formatCdnUrlFromCdnPath($mod['logoCdnPath'])) ?>" alt="Mod Thumbnail" loading="lazy" />
					<div>
						<h3><?= escapeHtml($mod['name']) ?></h3>
						<p><?= escapeHtml($mod['summary']) ?></p>
					</div>
				</a>
				<div class="ticket-list">
				<? foreach($mod['reports'] as $ticket): ?>
					<a href="/t/<?= $ticket['requestId'] ?>" target="_blank">
						<h4><?= stringifyModerationRequestCategory($ticket) ?></h4>
						<div><?= escapeHtml($ticket['requestSearchable']) ?></div>
					</a>
				<? endforeach; ?>
				</div>
			</div>
		<? endforeach; if(empty($reportedMods)): ?>
			<div>
				<div>
					<div>
						<h3>Nothing here.</h3>
						<p>No reports here.</p>
					</div>
				</div>
			</div>
		<? endif; ?>
		</div>
	</div>

	<div>
		<h3 style="margin-bottom: .5em;">Reported Comments:</h3>
		<div id="reported-comments">
		<? foreach($reportedComments as $comment): ?>
			<div>
				<div>
					<a href="<?= formatModPath($comment) ?>#cmt-<?= $comment['commentId'] ?>" target="_blank"><h4><?= escapeHtml($comment['textShort']) ?></h4></a>
					<p class="text-weak">On <?= escapeHtml($comment['modName']) ?>, By <a href="/show/user/<?= $comment['userHash'] ?>" target="_blank"><?= escapeHtml($comment['userName']) ?></a></p>
				</div>
				<div class="ticket-list">
				<? foreach($comment['reports'] as $ticket): ?>
					<a href="/t/<?= $ticket['requestId'] ?>" target="_blank">
						<h4><?= stringifyModerationRequestCategory($ticket) ?></h4>
						<div><?= escapeHtml($ticket['requestSearchable']) ?></div>
					</a>
				<? endforeach; ?>
				</div>
			</div>
		<? endforeach; if(empty($reportedComments)): ?>
			<div>
				<div>
					<div>
						<h3>Nothing here.</h3>
						<p>No reports here.</p>
					</div>
				</div>
			</div>
		<? endif; ?>
		</div>
	</div>
</div>

{include file="footer"}