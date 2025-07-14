{include file="header"}

	<h2>
		<span class="assettype">
			<a href="/list/user">{intval(count($rows))} Users</a>
		</span>
	</h2>	
	
	<form method="get" autocomplete="off" class="flex-list">
		<div data-label="Name">
			<input type="text" name="name" value="{$searchvalues['name']}">
		</div>
		
		<div data-label="">
			<button type="submit" name="">Search</button>
		</div>
		
	</form>	
	<p style="clear:both;">&nbsp;</p>
	
	<table class="stdtable" id="Users">
		<thead>
			<tr>
				{foreach from=$columns item=column}
					<th>{$column['title']}</th>
				{/foreach}
			</tr>
		</thead>
		<tbody>
		{if !empty($rows)}
			{foreach from=$rows item=row}
				<tr>
					{foreach from=$columns item=column}
						<td>
							<a href="/show/user/{$row['hash']}">
								{if isset($column["format"]) && $column["format"] == "date"} 
									{fancyDate($row[$column['code']])}
								{else}
									{$row[$column['code']]}
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
	<a class="button large shine" href="/edit/user">New User</a>
{/capture}



{include file="footer"}
