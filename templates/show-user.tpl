
{include file="header"}

<h2><span>About {$shownuser['name']}</span>{if $shownuser['isbanned']}&nbsp;<span style="color: red;">[currently restricted]</span>{/if}</h2>

<div style="float: right;">
	{if canModerate($shownuser, $user)}
		<a class="button large shine moderator" href="/moderate/user/{$usertoken}">Moderate User</a>&nbsp;
	{/if}
	{if canEditProfile($shownuser, $user)}
		<a class="button large shine" href="/edit/profile/{$usertoken}">Edit</a>
	{/if}
</div>

{if !empty($shownuserraw['bio'])}
	{$shownuserraw['bio']}
{else}
	<pre><i style="font-size:80%">User has not added a bio about themselves yet.</i></pre>
{/if}

{if !empty($mods)}
	<h3>Mods {$shownuser['name']} contributed to</h3>

	<div class="mods">
		{foreach from=$mods item=mod}{include file="list-mod-entry"}{/foreach}
		{if count($mods) < 5 /* @hack some spacing so the mods dont blow out */}<span></span><span></span><span></span><span></span><span></span>{/if}
	</div>
{/if}

{if canModerate($shownuser, $user)}
	<p><br><strong>User activity history (newest 100)</strong></p>
	<table class="stdtable">
		<thead><th>Text</th><th>Assetid</th><th>Date</th></thead>
		<tbody>
		{foreach from=$changelog item=centry}
			<tr><td>{$centry["text"]}</td><td>{$centry["assetid"]}</td><td>{fancyDate($centry["created"])}</td></tr>
		{/foreach}
		</tbody>
	</table>
{/if}

{include file="footer"}