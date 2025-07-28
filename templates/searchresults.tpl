{include file="header"}
<br>
	<form method="get" autocomplete="off" class="flex-list">
		<input type="hidden" name="search" value="1">
		<div data-label="Text">
			<input type="text" name="text" value="{$searchvalues['text']}">
		</div>
		
		<div data-label="Status">
			<select name="statusId">
				<option value="">-</option>
				{foreach from=$stati item=status}
					<option value="{$status['statusId']}" {if $searchvalues['statusId']==$status['statusId']}selected="selected"{/if}>{$status['name']}</option>
				{/foreach}
			</select>
		</div>

		<div data-label="Tags">
			<select style="width:300px;" name="tagids[]" multiple>
				<option value="">-</option>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagId']}" {if !empty($searchvalues['tagids'][$tag['tagId']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>

		<div data-label="">
			<button type="submit" name="">Search</button>
		</div>
		
	</form>
	

<div style="clear:both;"><br></div>
<h3>Found Assets</h3>
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
	{if count($foundassets)}
		{foreach from=$foundassets item=entry}
			<tr>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['assettypename']}</a></td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['name']}</td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{$entry['createdbyusername']}</td>
				<td><a href="/edit/{$entry['assettypecode']}?assetid={$entry['assetid']}">{fancyDate($entry['lastmodified'])}</a></td>
			</tr>
		{/foreach}
	{else}
		<td colspan="4"><i>No entries without response. Congratulations!</i></td>
	{/if}
	</tbody>
</table>





{include file="footer"}
