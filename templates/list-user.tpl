{include file="header"}
	<h2>
		User List
	</h2>
	<p>{count($rows)} Results{if count($rows) === 500} <small><i>(display limit)</i></small>{/if}</p>
	
	<form method="get" autocomplete="off" class="flex-list">
		<div data-label="Name">
			<input type="text" name="name" value="{$searchParameters['name']}">
		</div>
		
		<div data-label="">
			<button type="submit" name="">Search</button>
		</div>
		
	</form>	
	<p style="clear:both;">&nbsp;</p>
	
	<table class="stdtable" id="Users">
		<thead>
			<tr>
				<th>Name</th>
				{if $user['roleCode'] === 'admin'}<th>E-Mail</th>{/if}
				<th>First Login</th>
				<th>Banned Until</th>
			</tr>
		</thead>
		<tbody>
		{if !empty($rows)}
			{foreach from=$rows item=row}
				<tr>
					<td><a href="/show/user/{$row['hash']}">{$row['name']}</a></td>
					{if $user['roleCode'] === 'admin'}<td>{$row['email']}</td>{/if}
					<td>{fancyDate($row['created'])}</td>
					<td>{fancyDate($row['bannedUntil'])}</td>
				</tr>
			{/foreach}
		{else}
			<td colspan="5"><i>No Users found</i></td>
		{/if}
		</tbody>
	</table>



{include file="footer"}
