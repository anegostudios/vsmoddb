
{include file="header"}

<div style="float: right;">
	{if canEditProfile($shownuser, $user)}
		{include
			file="button"
			href="/edit/profile/$usertoken"
			buttontext="Edit"
		}
	{/if}
</div>

<h2><span class="title">{$shownuser['name']}</span></h2>

{if !empty($shownuserraw['bio'])}
	{$shownuserraw['bio']}
{/if}

<div style="clear:both;"></div>

{if !empty($mods)}

	{if $shownuser['userid'] == $user['userid']}
	<h3>Your mods</h3>
	{else}
	<h3>Their mods</h3>
	{/if}

	<div class="mods">
		{foreach from=$mods item=mod}
			{include file="list-mod-entry"}
		{/foreach}
	</div>

{/if}

{include file="footer"}