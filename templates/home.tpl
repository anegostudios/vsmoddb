{include file="header"}

{if empty($user)}
<p>Welcome to the Official Hub for Vintage Story Mods!</p>
<p>
It's goal is to simplify access and management of community made modifications to the base game. This site connects to your game account, so no extra sign up required.<br>
If you don't have a game account yet you can buy it on the <a href="https://www.vintagestory.at/store/">official store</a>.<br><br>

Whenever you're ready head over to the <a href="/list/mod">list of mods</a>! To install mods, check out <a href="https://wiki.vintagestory.at/index.php?title=Adding_mods">our guide on the wiki</a>
<br><br>
Cheers,<br>
&nbsp;&nbsp;Tyron

</p>
{/if}

<div class="mods">
{if !empty($user)}
	{if !empty($mods)}
		<h3>Your mod contributions</h3>
		{foreach from=$mods item=mod}{include file="list-mod-entry"}{/foreach}
	{/if}

	{if !empty($followedmods)}
		<h3>Followed Mods</h3>
		{foreach from=$followedmods item=mod}{include file="list-mod-entry-followed"}{/foreach}
	{/if}
{/if}

{if !empty($latestMods)}
	<h3>Latest 10 Mods</h3>
	{foreach from=$latestMods item=mod}{include file="list-mod-entry"}{/foreach}
{/if}
</div>

<br/>

<h3>Latest 20 Comments</h3>
<table class="stdtable latestcomments" style="width:100%;">
	<thead>
		<tr>
			<th style="width:200px;">On</th>
			<th class="textCol">Text</th>
			<th style="width:140px;">By</th>
			<th style="width:120px;">Date</th>
		</tr>
	</thead>
	<tbody>
	{if count($lastestComments)}
		{foreach from=$lastestComments item=comment}
			<tr>
				<td><a href="/show/mod/{$comment['assetId']}">{$comment['assetName']}</a></td>
				<td class="textCol"><div onclick="location.href='/show/mod/{$comment['assetId']}#cmt-{$comment['commentId']}'">{$comment['text']}</div></td>
				<td><a href="/show/mod/{$comment['assetId']}#comments">{$comment['username']}</a>{if $comment['isBanned']} <span style="color:red;white-space:nowrap;">[currently restricted]</span>{/if}</td>
				<td><a href="/show/mod/{$comment['assetId']}#comments">{fancyDate($comment['created'])}</a></td>
			</tr>
		{/foreach}
	{else}
		<td colspan="4"><i>No comments found.</i></td>
	{/if}
	</tbody>
</table>

{include file="footer"}
