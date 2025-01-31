
{include file="header"}

<div style="float: right;">
	{if canEditProfile($shownuser, $user)}
		{include
			file="button"
			href="/edit/profile/$usertoken"
			buttontext="Edit"
		}
	{/if}
	{if canModerate($shownuser, $user)}
		{include
			file="button"
			href="/ban/$usertoken"
			buttontext="Edit"
		}
	{/if}
</div>

<h2><span class="title">About {$shownuser['name']}</span></h2>

{if !empty($shownuserraw['bio'])}
	{$shownuserraw['bio']}
{else}
	<pre><i style="font-size:80%">User has not added a bio about himself yet</i></pre>
{/if}

<div style="clear:both;"></div>

{if !empty($mods)}

	<h3>Mod created by {$shownuser['name']}</h3>

	<div class="mods">
		{foreach from=$mods item=mod}
			{include file="list-mod-entry"}
		{/foreach}
	</div>

{/if}

{include file="footer"}