{include file="header"}

	<br>
	<form method="get">
		<input type="hidden" name="search" value="1">
		<div class="searchbox">
			<label>Text</label>
			<input type="text" name="text" value="{$searchvalues['text']}">
		</div>
		
		<div class="searchbox">
			<label>Status</label>
			<select name="statusid">
				<option value="">-</option>
				{foreach from=$stati item=status}
					<option value="{$status['statusid']}" {if $searchvalues['statusid']==$status['statusid']}selected="selected"{/if}>{$status['name']}</option>
				{/foreach}
			</select>
		</div>

		<!--<div class="searchbox">
			<label>Tags</label>
			<select style="width:300px;" name="tagids[]" multiple>
				<option value="">-</option>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagid']}" {if !empty($searchvalues['tagids'][$tag['tagid']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>-->

		<div class="searchbox">
			<p style="height:6px;"></p>
			<button type="submit" name="">Search</button>
		</div>
		
	</form>

<div style="clear:both;"></div>
	
	
{if !empty($featurereleases)}
	<h2>Next Milestone</h2>
	{foreach from=$featurereleases item=row}
		<div class="editbox linebreak" style="min-width: 900px; position:relative;">
			<strong>{$row['name']}</strong>
			<span style="float:right; padding-right:100px;">{$row['releasedate']}</span>
			<p>{$row['text']}</p>
			<hr>
			<p>{$row['detailtext']}</p>
			<a href="/edit/featurerelease?assetid={$row['assetid']}" style="position:absolute; right: 5px; top: 5px;">go to entry</a>
		</div>
	{/foreach}
{/if}

<div style="clear:both;"><br></div>

<!--<h3>The following assets are awaiting your response</h3>
<table class="stdtable unresponded" style="width:1000px;">
	<thead>
		<tr>
			<th style="width:150px;">Type</th>
			<th>Name</th>
			<th style="width:120px;">Created by</th>
			<th style="width:120px;">Last modified</th>
		</tr>
	</thead>
	<tbody>
	{if count($unrespondedentries)}
		{foreach from=$unrespondedentries item=entry}
			<tr>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['assettypename']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['name']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['createdbyusername']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{fancyDate($entry['lastmodified'])}</a></td>
			</tr>
		{/foreach}
	{else}
		<td colspan="4"><i>No entries without response. Congratulations!</i></td>
	{/if}
	</tbody>
</table>-->


<p><br></p>
<h3>Latest 20 Comments</h3>
<table class="stdtable latestcomments" style="min-width:900px;">
	<thead>
		<tr>
			<th style="width:100px;">By</th>
			<th style="width:100px;">Asset</th>
			<th style="width:200px;">Asset Name</th>
			<th>Text</th>
			<th style="width:120px;">Last modified</th>
		</tr>
	</thead>
	<tbody>
	{if count($latestcomments)}
		{foreach from=$latestcomments item=entry}
			<tr>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['username']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['assettypename']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['assetname']}</a></td>
				<td><div style="max-height: 100px; overflow:auto; cursor:pointer;" onclick="location.href='/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}'">{$entry['text']}</div></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{fancyDate($entry['lastmodified'])}</a></td>
			</tr>
		{/foreach}
	{else}
		<td colspan="4"><i>No comments found.</i></td>
	{/if}
	</tbody>
</table>


<p><br></p>
<h3>Latest 20 Assets</h3>
<table class="stdtable latestasests" style="min-width:900px;">
	<thead>
		<tr>
			<th style="width:150px">Type</th>
			<th>Name</th>
			<th style="width:120px;">Created by</th>
			<th style="width:120px;">Last modified</th>
		</tr>
	</thead>
	<tbody>
	{if count($latestentries)}
		{foreach from=$latestentries item=entry}
			<tr>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['assettypename']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['name']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['createdbyusername']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{fancyDate($entry['lastmodified'])}</a></td>
			</tr>
		{/foreach}
	{else}
		<td colspan="4"><i>No entries without response. Congratulations!</i></td>
	{/if}
	</tbody>
</table>


<p><br></p>
<h3>Activity Stream</h3>
<table class="stdtable activitystream" style="min-width:900px;">
	<thead>
		<tr>
			<th style="width:300px">Asset</th>
			<th>Changes</th>
			<th style="width:120px;">User</th>
			<th style="width:120px;">Date</th>
		</tr>
	</thead>
	<tbody>
	{if count($changelogs)}
		{foreach from=$changelogs item=entry}
			<tr>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['assetname']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{str_replace("\r\n", "<br>", $entry['text'])}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['username']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{fancyDate($entry['lastmodified'])}</a></td>
			</tr>
		{/foreach}
	{else}
		<td colspan="4"><i>No activity found.</i></td>
	{/if}
	</tbody>
</table>




{include file="footer"}
