{include file="header" pagetitle="Notifications - "}

<h2>Your Notifications{if count($notifications)}<a href="/notification/clearall" style="float: right"><small>Clear All</small></a>{/if}</h2>

<ul id="notifications-list">
	{if count($notifications)}
	{foreach from=$notifications item=notification}
		<li data-label="{formatDateRelative($notification['created'])}">
			{$notification['text']}
			<div class="flex-spacer"></div>
			<a href="/notification/{$notification['notificationid']}">Go There</a>
			<? /*<a href="/notification/{$notification['notificationid']}/clear">Clear</a> */ ?>
		</li>
	{/foreach}
	{else}
		<span>All caught up!</span>
	{/if}
</ul>

{include file="footer"}
