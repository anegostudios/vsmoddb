
{include file="header"}

<h2><span class="title">Moderate {$shownuser['name']}</span></h2>

<div style="clear:both;"></div>

<form method="POST" autocomplete="off">
	<label for="modreason">Reason for this ban:</label><br/>
	<textarea id="modreason" name="modreason" style="display: block; width: 100%; margin-bottom: 1em;">{$banreasonautocomplete}</textarea>
	<label for="until">Ban until: <input id="until" type="datetime-local" name="until" /></label> <label for="forever" style="user-select: none;"><input id="forever" type="checkbox" name="forever" /> Forever</label>
	<div style="float: right;">
		<button type="submit" name="submit" value="ban" style="background-color: darkred;color: white;">Ban User!</button>
		<button type="submit" name="submit" value="redeem" style="background-color: darkgreen;color: white;">Redeem User!</button>
	</div>
<form>

{if !empty($moderationrecord)}
	<h3>ModAction history targeting {$shownuser['name']}</h3>
	<table class="stdtable">
	<thead>
		<tr><th>Applied at</th><th>Kind</th><th>Until</th><th>(Reason) Message</th><th>Acting Moderator</th></tr>
	</thead>
	<tbody>
		{foreach from=$moderationrecord item=rec}
			<tr><td>{$rec['created']} ({fancydate($rec['created'])})</td><td>{stringifyModactionKind($rec['kind'])}</td><td>{formatDateWhichMightBeForever($rec['until'])}</td><td>{$rec['reason']}</td><td>{$rec['moderatorname']}</td></tr>
		{/foreach}
	</tbody>
	</table>
{/if}

{include file="footer"}