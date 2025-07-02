{include file="header"}

<div class="edit">

	{if $row['tagId']}
		<h2>
			<span class="assettype">
				<a href="/list/tag">Tags</a>
			</span> / 
			<span>{$row["name"]}</span>
		</h2>	
	{else}
		<h2>
			<span class="assettype">
				<a href="/list/tag">Tags</a>
			</span> / 
			<span>Add new Tag</span>
		</h2>
	{/if}

	<form method="post" name="deleteform">
		<input type="hidden" name="at" value="{$user['actiontoken']}">
		<input type="hidden" name="delete" value="1">
	</form>

	<form method="post" name="form1" autocomplete="off" class="flex-list">
		<input type="hidden" name="at" value="{$user['actiontoken']}">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="saveandback" value="0">
		
		<div class="editbox">
			<label>Name</label>
			<input type="text" name="name" class="required" value="{$row['name']}"/>
		</div>

		<div class="editbox">
			<label>Color</label>
			<input type="color" class="color" name="color" class="required" value="#{substr($row['color'], 0, 6)}"/>
		</div>

		<div class="editbox flex-fill">
			<label>Description</label>
			<textarea name="text" style="width: 100%; height: auto;">{$row['text']}</textarea>
		</div>
	</form>
</div>

{capture name="buttons"}
	<a class="button large submit shine" href="javascript:submitForm(0)">Save</a>
	<a class="button large submit shine" href="javascript:submitForm(1)">Save+Back</a>
	
	{if $row['tagId']}
		<div style="height: 1em"></div>
		<a class="button large btndelete shine" href="javascript:submitDelete()">Delete Tag</a>
	{/if}
{/capture}

{capture name="footerjs"}
<script type="text/javascript" src="/web/js/jQueryColorPicker.min.js"></script>
<script type="text/javascript">

	function submitForm(returntolist) {
		$('form[name=form1]').trigger('reinitialize.areYouSure');
		
		if (returntolist) {
			$('input[name="saveandback"]').val(1);
		}
		document['form1'].submit();
	}
	
	function submitDelete() {
		if (confirm("Really delete this entry?")) {
			document['deleteform'].submit();
		}
	}
	
</script>
{/capture}

{include file="footer"}
