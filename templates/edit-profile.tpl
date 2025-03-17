{capture name="head"}
<meta content="{strip_tags($bio)}" property="og:description" />
{/capture}

{include file="header"}

<div class="edit-asset">

	<h2>
		<span>
			<a href="/show/user/{$usertoken}">Profile</a>
		</span> / 
		<span>Edit</span>
	</h2>

<form method="post" class="flex-list">
	<div class="editbox flex-fill">
		<label>Bio</label>
		<textarea name="bio" class="editor" data-editorname="bio">{$bio}</textarea>
	</div>

	<div>
		<input type="submit" name="save" value="Save changes">
	</div>
</form>

</div>

{if isset($errormessage)}
	<div class="text-error" style="clear:both; margin-top:20px;">
		{$errormessage}
	</div>
{/if}

{capture name="footerjs"}
	<script type="text/javascript">
		$(document).ready(function());
	</script>
	<script type="text/javascript" src="/web/js/edit-profile.js" async></script>
{/capture}

{include file="footer"}
