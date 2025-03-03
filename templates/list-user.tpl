{include file="header"}

	<h2>
		<span class="assettype">
			<a href="/list/{$entrycode}">{intval(count($rows))} {$entryplural}</a>
		</span>
	</h2>	
	
	<form method="get">
		<div class="searchbox">
			<label>Name</label>
			<input type="text" name="name" value="{$searchvalues['name']}">
		</div>
		
		<div class="searchbox">
			<p style="height:6px;"></p>
			<button type="submit" name="">Search</button>
		</div>
		
	</form>	
	<p style="clear:both;">&nbsp;</p>
	
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
							<a href="/show/user/{getUserHash($row['userid'], $row['created'])}">
							{if $column['code'] == "iconfilepath"}
								{if $row["iconfilepath"]}<img width="50" src="/files/icons/{$row['iconfilepath']}">{/if}
							{else}
								{if isset($column["format"]) && $column["format"] == "date"} 
									{fancyDate($row[$column['code']])}
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
			<td colspan="{count($columns)}">{if empty($searchvalues['name'])}Search for a name to get results{else}<i>No {$entryplural} found</i>{/if}</td>
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
