{include file="header"}

<div class="edit">

	<h2>
		<span class="assettype">
			<a href="/list/{$entrycode}">{$entryplural}</a>
		</span> / 
		<span>{$row[$entrycode.'id'] ? $row["name"] : "Add new ".$entrysingular}</span>
	</h2>

	<form method="post" name="deleteform">
		<input type="hidden" name="delete" value="1">
	</form>

	<form method="post" name="form1" autocomplete="off" class="flex-list">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="{$entrycode}id" value="{$row[$entrycode.'id']}">
		<input type="hidden" name="saveandback" value="0">
		
		<div class="editbox">
			<label>Name</label>
			<input type="text" name="name" value="{$row['name']}"/>
		</div>
		
		<div class="editbox">
			<label>Code</label>
			<input type="text" name="code" value="{$row['code']}"/>
		</div>
	</form>
</div>

{capture name="buttons"}
	<a class="button large submit shine" href="javascript:submitForm(0)">Save</a>
	<a class="button large submit shine" href="javascript:submitForm(1)">Save+Back</a>
	
	{if $row[$entrycode.'id']}
		<div style="height: 1em"></div>
		<a class="button large btndelete shine" href="javascript:submitDelete()">Delete {$entrysingular}</a>
	{/if}
{/capture}

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

{include file="footer"}
