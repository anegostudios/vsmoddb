{include file="header" hclass=""}

<form method="post" class="edit-asset with-buttons-bottom innercontent" autocomplete="off">
	<h2 style="padding: 1rem 1rem 0 1rem">
		<span>
			<a href="/show/user/{$userHash}">Profile</a>
		</span> / 
		<span>Edit</span>
	</h2>

	<div style="width: 100%; padding: 0 1em">
		<div class="editbox" style="width: 100%; min-height: 400px;">
			<label>Bio</label>
			<textarea name="bio" class="editor" data-editorname="bio">{$bio}</textarea>
		</div>
	</div>

	<div class="buttons">
		<button type="submit" name="save" class="button large submit shine" value="Save changes">Save changes</button>
	</div>
</form>

{capture name="footerjs"}
	<script nonce="{$cspNonce}" type="text/javascript">
		R.onDOMLoaded(() => createEditor(R.getQ("textarea.editor"), tinymceSettingsCmt));
	</script>
{/capture}

{include file="footer"}
