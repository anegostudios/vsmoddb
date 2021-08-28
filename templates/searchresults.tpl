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

		<div class="searchbox">
			<label>Tags</label>
			<select style="width:300px;" name="tagids[]" multiple>
				<option value="">-</option>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagid']}" {if !empty($searchvalues['tagids'][$tag['tagid']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="searchbox">
			<p style="height:6px;"></p>
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
