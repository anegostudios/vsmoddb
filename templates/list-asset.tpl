{include file="header"}

	<h2>
		<span class="assettype">
			<a href="/list/{$entrycode}">{$entryplural}</a>
		</span>
	</h2>	
	
	<form method="get" autocomplete="off" class="flex-list">
		<div data-label="Text">
			<input type="text" name="text" value="{$searchvalues['text']}">
		</div>
		
		<div data-label="Status">
			<select name="statusid">
				<option value="">-</option>
				{foreach from=$stati item=status}
					<option value="{$status['statusid']}" {if $searchvalues['statusid']==$status['statusid']}selected="selected"{/if}>{$status['name']}</option>
				{/foreach}
			</select>
		</div>

		<div data-label="Tags">
			<select style="width:300px;" name="tagids[]" multiple>
				<option value="">-</option>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagid']}" {if !empty($searchvalues['tagids'][$tag['tagid']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>

		<div data-label="">
			<button type="submit" name="">Search</button>
		</div>
		
	</form>
	{if !empty($rows)}
	<p style="clear:both;">&sum; = {count($rows)}</p>
	{else}
		<p style="clear:both;">&nbsp;</p>
	{/if}
	
	<table class="stdtable" id="{$entryplural}">
		<thead>
			<tr>
				{foreach from=$columns item=column}
					<th class="{$column['cssclassname']}">{$column['title']}</th>
				{/foreach}
			</tr>
		</thead>
		<tbody>
		{if !empty($rows)}
			{foreach from=$rows item=row}
				<tr>
					{foreach from=$columns item=column}
						<td>
							<a href="/edit/{$entrycode}?assetid={$row['assetid']}">
							{if $column['code'] == "iconfilepath"}
								{if $row["iconfilepath"]}<img width="50" src="/files/icons/{$row['iconfilepath']}">{/if}
							{else}
								{if $column['datatype'] == 'date'}
									{fancyDate($row[$column['code']])}
								{elseif $column['datatype'] == 'tags'}
									{foreach from=$row['tags'] item=tag}
										<span class="tag" style="background-color:{$tag['color']}">{$tag['name']}</span>
									{/foreach}
								{else}
									{$row[$column['code']]}
								{/if}
								
							{/if}
							</a>
						</td>
					{/foreach}
					
				</tr>
			{/foreach}
		{else}
			<td colspan="{count($columns)}"><i>No {$entryplural} found</i></td>
		{/if}
		</tbody>
	</table>


{capture name="buttons"}
	{include
		file="button"
		href="/edit/`$entrycode`"
		buttontext="New `$entrysingular`"
	}
{/capture}



{include file="footer"}
