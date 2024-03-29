{include file="header"}

<div class="edit">

	{if $row[$entrycode.'id']}
		<h2>
			<span class="assettype">
				<a href="/list/{$entrycode}">{$entryplural}</a>
			</span> / 
			<span class="title">
				{$row["name"]}
			</span>
		</h2>	
	{else}
		<h2>
			<span class="assettype">
				<a href="/list/{$entrycode}">{$entryplural}</a>
			</span> / 
			<span class="title">Add new {$entrysingular}</span>
		</h2>
	{/if}

	<form method="post" name="deleteform">
		<input type="hidden" name="delete" value="1">
	</form>

	<form method="post" name="form1">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="{$entrycode}id" value="{$row[$entrycode.'id']}">
		<input type="hidden" name="saveandback" value="0">
		
		<div class="editbox linebreak">
			<label>Name</label>
			<input type="text" name="name" value="{$row['name']}"/>
		</div>
		
		<div class="editbox linebreak">
			<label>Code</label>
			<input type="text" name="code" value="{$row['code']}"/>
		</div>
	</form>
</div>

{capture name="buttons"}
	
	{include
		file="button"
		href="javascript:submitForm(0)"
		buttontext="Save"
	}

	{include
		file="button"
		href="javascript:submitForm(1)"
		buttontext="Save+Back"
	}
	
	{if $row[$entrycode.'id']}
		<p style="clear:both;"><br></p>
		{include
			file="button"
			href="javascript:submitDelete()"
			buttontext="Delete `$entrysingular`"
		}
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
