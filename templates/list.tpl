{include file="header"}

	<h2>
		<span class="assettype">
			<a href="/list/{$entrycode}">{intval(count($rows))} {$entryplural}</a>
		</span>
	</h2>	
	
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
							<a href="/edit/{$entrycode}?{$entrycode}id={$row[$entrycode.'id']}">
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
			<td colspan="{count($columns)}"><i>No {$entryplural} found</i></td>
		{/if}
		</tbody>
	</table>


{capture name="buttons"}
	<a class="button large shine" href="/edit/{$entrycode}">New {$entrysingular}</a>
{/capture}



{include file="footer"}
