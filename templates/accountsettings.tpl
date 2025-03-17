{include file="header"}
<h3>Account Settings</h3>

<form method="post" class="flex-list">

	<div class="editbox">
		<label>Name (<a class="external" href="https://account.vintagestory.at/profile">edit</a>)</label>
		<input type="text" name="name" value="{$user['name']}" disabled>
	</div>

	<div class="editbox">
		<label>E-Mail (<a class="external" href="https://account.vintagestory.at/profile">edit</a>)</label>
		<input type="text" name="email" value="{$user['email']}" disabled>
	</div>

	<div class="editbox">
		<label>Time Zone</label>
		<select name="timezone">
			{foreach from=$timezones item=timezone key=index}
				<option value="{$index}" {if $user['timezone'] == $timezone}selected="selected"{/if}>{$timezone}</option>
			{/foreach}
		</select>
	</div>

	<div class="flex-fill">
		<input type="submit" name="save" value="Save changes">
	</div>

</form>


{if isset($errormessage)}
	<div class="text-error" style="clear:both; margin-top:20px;">
		{$errormessage}
	</div>
{/if}


{include file="footer"}
