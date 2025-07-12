
{include file="header"}

<h2><span>Moderate {$shownUser['name']}</span></h2>

<form method="POST" autocomplete="off">
	<label for="modreason">Reason for this ban:</label><br/>
	<textarea id="modreason" name="modreason" style="display: block; width: 100%; min-height:150px; margin-bottom: 1em;">{$banReasonSuggestion}</textarea>
	<p><label for="until">Ban until: <input id="until" type="datetime-local" name="until" /></label> <label for="forever" style="user-select: none;"><input id="forever" type="checkbox" name="forever" /> Forever</label></p>
		
	<div>
		<button class="button submit" type="submit" name="submit" value="ban" style="background-color: darkred;color: white;">Ban User</button>
		<button class="button submit" type="submit" name="submit" value="redeem" style="background-color: darkgreen;color: white;">Redeem User</button>
	</div>
</form>

{if !empty($records)}
	<h3>ModAction history targeting {$shownUser['name']}</h3>
	<table class="stdtable">
	<thead>
		<tr><th>Applied at</th><th>Kind</th><th>Until</th><th>(Reason) Message</th><th>Acting Moderator</th></tr>
	</thead>
	<tbody>
		{foreach from=$records item=rec}
			<tr>
				<td>{$rec['created']} ({fancydate($rec['created'])})</td>
				{if $rec['commentId']}
					<td><a target="_blank" href="/show/mod/{$rec['assetId']}#cmt-{$rec['commentId']}">{stringifyModactionKind($rec['kind'])}</a></td>
				{else}
					<td>{stringifyModactionKind($rec['kind'])}</td>
				{/if}
				<td>{formatDateWhichMightBeForever($rec['until'])}</td>
				<td>{$rec['reason']}</td><td>{$rec['moderatorName']}</td>
			</tr>
		{/foreach}
	</tbody>
	</table>
{/if}

{include file="footer"}