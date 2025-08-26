
{include file="header"}

<h2><span>About {htmlspecialchars($shownUser['name'])}</span>{if $shownUser['isBanned']}&nbsp;<span style="color: red;">[currently restricted]</span>{/if}</h2>

<div style="float: right;">
	{if canModerate($shownUser, $user)}
		<a class="button large shine moderator" href="/moderate/user/{$shownUser['hash']}">Moderate User</a>&nbsp;
	{/if}
	{if canEditProfile($shownUser, $user)}
		<a class="button large shine" href="/edit/profile/{$shownUser['hash']}">Edit</a>
	{/if}
</div>

{if !empty($shownUser['bio'])}
	{$shownUser['bio']}
{else}
	<pre><i style="font-size:80%">User has not added a bio about themselves yet.</i></pre>
{/if}

{if !empty($mods)}
	<h3>Mods {$shownUser['name']} contributed to</h3>

	<div class="mods">
		{foreach from=$mods item=mod}{include file="list-mod-entry"}{/foreach}
		{if count($mods) < 5 /* @hack some spacing so the mods dont blow out */}<span></span><span></span><span></span><span></span><span></span>{/if}
	</div>
{/if}

{if canModerate($shownUser, $user)}
	<p><br><strong>User activity history (newest 100)</strong></p>
	<table class="stdtable">
		<thead><th>Text</th><th>Assetid</th><th>Date</th></thead>
		<tbody>
		{foreach from=$changelog item=centry}
			<tr><td>{str_replace("\n\r", "<br/>", $centry['text'])}</td><td>{$centry["assetId"]}</td><td>{fancyDate($centry["created"])}</td></tr>
		{/foreach}
		</tbody>
	</table>
{/if}

{include file="footer"}