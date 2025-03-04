
{include file="header"}

<div style="float: right;">
	{if canModerate($shownuser, $user)}
		{include
			file="button"
			href="/moderate/user/$usertoken"
			buttontext="Moderate User"
			class="flair-moderator"
		}
	{/if}
	{if canEditProfile($shownuser, $user)}
		{include
			file="button"
			href="/edit/profile/$usertoken"
			buttontext="Edit"
		}
	{/if}
</div>

<h2><span class="title">About {$shownuser['name']}</span>{if $shownuser['isbanned']}&nbsp;<span style="color: red;">[currently restricted]</span>{/if}</h2>

{if !empty($shownuserraw['bio'])}
	{$shownuserraw['bio']}
{else}
	<pre><i style="font-size:80%">User has not added a bio about themselves yet.</i></pre>
{/if}

<div style="clear:both;"></div>

{if !empty($mods)}
	<h3>Mods {$shownuser['name']} contributed to</h3>

	<div class="mods">
		{foreach from=$mods item=mod}
			{include file="list-mod-entry"}
		{/foreach}
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