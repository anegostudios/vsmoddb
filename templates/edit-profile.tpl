{capture name="head"}
<meta content="{strip_tags($bio)}" property="og:description" />
{/capture}

{include file="header"}

<h3>
	<span class="title">
 		<a href="/show/user/{$usertoken}">Profile</a>
	</span> / 
	<span class="title">Edit</span>
</h3>

<form method="post">
	<div class="editbox flex-fill" style="width: 99%; max-width:1000px">
		<label>Text</label>
		<textarea name="bio" class="editor" data-editorname="bio" style="width: 99%; height: auto;">{$bio}</textarea>
	</div>

	<p><br></p>
	
	<div style="clear:both">
		<input type="submit" name="save" value="Save changes">
	</div>
</form>

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
