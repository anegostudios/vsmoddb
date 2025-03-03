
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

{include file="footer"}