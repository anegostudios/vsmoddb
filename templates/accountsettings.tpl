{include file="header"}
<h3>Account Settings</h3>

<form method="post">
	<div style="float:left;">

		<div class="editbox linebreak">
			<label>Name</label>
			<input type="text" name="name" value="{$user['name']}" style="width:290px;" disabled> (<a href="https://account.vintagestory.at/profile">edit</a>)
		</div>

		<div class="editbox linebreak">
			<label>E-Mail</label>
			<input type="text" name="email" value="{$user['email']}" style="width:290px;" disabled> (<a href="https://account.vintagestory.at/profile">edit</a>)
		</div>

		<div class="editbox linebreak">
			<label>Time Zone</label>
			<select name="timezone" style="width:294px;">
				{foreach from=$timezones item=timezone key=index}
					<option value="{$index}" {if $user['timezone'] == $timezone}selected="selected"{/if}>{$timezone}</option>
				{/foreach}
			</select>
		</div>

		<p><br></p>

	</div>
	
	
	<div style="clear:both">
		<input type="submit" name="save" value="Save changes">
	</div>
</form>

{if isset($errormessage)}
	<div class="text-error" style="clear:both; margin-top:20px;">
		{$errormessage}
	</div>
{/if}


{include file="footer"}
