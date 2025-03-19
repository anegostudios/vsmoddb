{include file="header"}
<h2>Reclassify {$nowassettype} '{$asset['name']}'</h2>
Reclassifying an asset will migrate data fields common to all assets, such as the title, text, file attachments, comments and so on. <br>
Custom data (e.g. the structure type) will be lost!
<p><br></p>
<p>
Current asset classification: {$nowassettype}
</p>
<p>
<form method="post" autocomplete="off">
	<input type="hidden" name="save" value="1">
	Desired asset classification:
	<select name="assettypeid" style="width: 300px">
		{foreach from=$assettypes item=assettype}
			<option value="{$assettype['assettypeid']}">{$assettype['name']}</option>
		{/foreach}
	</select>
	</p>
	<input type="submit" value="Reclassify now">
</form>



{include file="footer"}

