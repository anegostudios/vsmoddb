{capture name="head"}
<meta property="og:title" content="{escapeHtml($shownUser['name'])}" />
{if !empty($shownUser['bio'])}
<meta property="og:description" content="{escapeHtml(formatEllipsis(strip_tags($shownUser['bio']), 255))}" />
{/if}
<meta property="og:site_name " content="VS ModDB" />
<meta name="theme-color" content="#91A357" />
{/capture}

{include file="header"}

<h2><span>About {escapeHtml($shownUser['name'])}</span>{if $shownUser['isBanned']}&nbsp;<span style="color: red;">[currently restricted]</span>{/if}</h2>

<div style="float: right;">
	{if canModerate($shownUser, $user)}
		<a class="button large shine moderator strikethrough-when-readonly" data-opens-dialog="history-mdl" onclick="return false;">Audit User</a>&nbsp;
		<a class="button large shine moderator strikethrough-when-readonly" href="/moderate/user/{$shownUser['hash']}">Moderate User</a>&nbsp;
	{/if}
	{if canEditProfile($shownUser, $user)}
		<a class="button large shine strikethrough-when-readonly" href="/edit/profile/{$shownUser['hash']}">Edit</a>
	{/if}
</div>

{if !empty($shownUser['bio'])}
	{$shownUser['bio']}
{else}
	<pre><i style="font-size:80%">User has not added a bio about themselves yet.</i></pre>
{/if}

{if !empty($mods)}
	<h3>Mods {escapeHtml($shownUser['name'])} contributed to</h3>

	<div class="mods">
		{foreach from=$mods item=mod}{include file="list-mod-entry"}{/foreach}
	</div>
{/if}

{if canModerate($shownUser, $user)}
<dialog id="history-mdl" class="full-screen" closedby="any">
	<form  class="with-buttons-bottom">
		<h1>User activity history (latest 100)</h1>
		<div class="audit-log-wrap" style="max-height: calc(100vh - 15em)">
			<table class="stdtable">
				<thead><tr><th>Date</th><th>Target</th><th>Kind</th><th>Info</th></tr></thead>
				<tbody>
					<? foreach($auditLogs as $logEntry): ?>
					<tr>
						<? require($this->templatedir.'audit-log-entry-cell-date.tpl'); ?>
						<? require($this->templatedir.'audit-log-entry-cell-referenced-asset.tpl'); ?>
						<? require($this->templatedir.'audit-log-entry-cells-kind-info.tpl'); ?>
					</tr>
					<? endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="buttons">
			<button class="button large shine" formmethod="dialog" autofocus="" style="margin-left: auto">Close</button>
		</div>
	</form>
</dialog>
{/if}

{include file="footer"}