{include file="header"}

<div class="edit-asset edit-{$entrycode}">

	{if $asset['assetid']}
		<h2>
			<span class="assettype">
				<a href="/list/{$entrycode}">{$entryplural}</a>
			</span> /
			<span class="title">
				<a href="{if $asset['urlalias']}/{$asset['urlalias']}{else}/show/mod/{$asset['assetid']}{/if}">{$asset["name"]}</a>
			</span> /
			<span class="title">Edit</span>
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
		<input type="hidden" name="at" value="{$user['actiontoken']}">
		<input type="hidden" name="delete" value="1">
	</form>

	<form method="post" name="form1" autocomplete="off" class="flex-list">
		<input type="hidden" name="at" value="{$user['actiontoken']}">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="assetid" value="{$asset['assetid']}">
		<input type="hidden" name="numsaved" value="{$asset['numsaved']}">
		<input type="hidden" name="saveandback" value="0">

		<div class="editbox short">
			<label><abbr title="Only mods with Status 'Published' are publicly visible">Status</abbr></label>
			<select name="statusid">
				{foreach from=$stati item=status}
					<option value="{$status['statusid']}"{if $asset['statusid']==$status['statusid']} selected="selected"{/if}>{$status['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox short">
			<label><abbr title="Only mods with type 'Game Mod' are available in the in-game mod browser">Type</abbr></label>
			<select name="type">
				{foreach from=$modtypes item=modtype}
					<option value="{$modtype['code']}"{if $asset['type']==$modtype['code']} selected="selected"{/if}>{$modtype['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox">
			<label>Tags</label>
			<select name="tagids[]" multiple>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagid']}" title="{$tag['text']}"{if !empty($asset['tags'][$tag['tagid']])} selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox">
			<label>Name</label>
			<input type="text" name="name" class="required" value="{$asset['name']}" />
		</div>

		<div class="editbox wide">
			<label><abbr title="If set, your mod can be reached with this custom url. Only alphabetical letters are allowed.">URL Alias</abbr></label>
			<label for="inp-urlalias" class="prefixed-input" data-prefix="https://mods.vintagestory.at/"><input id="inp-urlalias" type="text" name="urlalias" value="{$asset['urlalias']}" style="width: 21ch" /></label>
		</div>

		<div class="editbox flex-fill">
			<label>Summary. Describe your mod in 100 characters or less.</label>
			<input type="text" name="summary" maxlength="100" class="required" value="{$asset['summary']}" />
		</div>

		<div class="editbox flex-fill">
			<label>Text</label>
			<textarea name="text" class="editor" data-editorname="text"
				style="width: 100%; height: auto;">{$asset['text']}</textarea>
		</div>

		{if file_exists("templates/edit-asset-$entrycode.tpl")}
			{include file="edit-asset-`$entrycode`.tpl"}
		{/if}

		{if canEditAsset($asset, $user, false)}
			<h3 class="flex-fill">Team members</h3>

			<div id="teammembers-box" class="editbox wide pending-markers">
				<label>Team Members</label>
				<select name="teammemberids[]" multiple class="ajax-autocomplete" data-placeholder="Search Users"
					data-url="/api/authors?name=\{name}" data-ownerid="{$asset['createdbyuserid']}">
					{if !empty($teammembers)}
						{foreach from=$teammembers item=teammember}
							<option selected class="maybe-accepted{if !$teammember['pending']} accepted{/if}" value="{$teammember['userid']}" title="{$teammember['name']}">{$teammember['name']}</option>
						{/foreach}
					{/if}
				</select>
			</div>

			<div id="teameditors-box" class="editbox wide pending-markers">
				<label>Team Members with edit permissions</label>
				<select name="teammembereditids[]" multiple data-placeholder="Search Members">
					{foreach from=$teammembers item=teammember}
						<option {if $teammember['canedit']}selected{/if} class="maybe-accepted{if !$teammember['pending']} accepted{/if}" value="{$teammember['userid']}" title="{$teammember['name']}">{$teammember['name']}</option>
					{/foreach}
				</select>
			</div>
		{/if}

		<div class="flex-fill">
			<h3>Screenshots<span style="float:right; font-size:70%;">(drag&drop to upload{if false /*:ZipDownloadDisabled*/ && (count($files) > 0)}, <a href="/download?assetid={$asset['assetid']}">download all as zip</a>{/if})</span></h3>

			{include file="edit-asset-files.tpl"}
		</div>

		<h3 class="flex-fill">Links</h3>
		<div class="editbox">
			<label>Homepage or Forum Post Url</label>
			<input type="url" name="homepageurl" value="{$asset['homepageurl']}" />
		</div>

		<div class="editbox">
			<label>Trailer Video Url</label>
			<input type="url" name="trailervideourl" value="{$asset['trailervideourl']}" />
		</div>

		<div class="editbox">
			<label>Source Code Url</label>
			<input type="url" name="sourcecodeurl" value="{$asset['sourcecodeurl']}" />
		</div>

		<div class="editbox">
			<label>Issue tracker Url</label>
			<input type="url" name="issuetrackerurl" value="{$asset['issuetrackerurl']}" />
		</div>

		<div class="editbox">
			<label>Wiki Url</label>
			<input type="url" name="wikiurl" value="{$asset['wikiurl']}" />
		</div>

		<div class="editbox">
			<label>Donate Url</label>
			<input type="url" name="donateurl" value="{$asset['donateurl']}" />
		</div>

		<h3 class="flex-fill">Additional information</h3>
		<div class="editbox">
			<label>Side</label>
			<select name="side">
				<option value="client" {if ($asset['side']=='client')}selected="selected" {/if}>Client side only mod</option>
				<option value="server" {if ($asset['side']=='server')}selected="selected" {/if}>Server side only mod</option>
				<option value="both" {if (empty($asset['side']) || $asset['side']=='both')}selected="selected" {/if}>Client and Server side mod</option>
			</select>
		</div>

		<div class="editbox">
			<label>Logo/Thumbnail image</label>
			<small>Logo has to be 480x480 or 480x320 px.</small>
			<select name="logofileid">
				{foreach from=$files item=file}
					<option value="{$file['fileid']}" {if $asset['logofileid']==$file['fileid']} selected="selected" {/if}>
						{$file['filename']}</option>
				{/foreach}
			</select>
		</div>

		{if $asset['assetid'] && canEditAsset($asset, $user, false)}
			<h3 class="flex-fill">Ownership transfer</h3>

			<div class="editbox wide">
				{if isset($ownershipTransferUser) && $ownershipTransferUser}
					<span>An ownership transfer invitation has been sent to: {$ownershipTransferUser}.</span>
					<br>
					<span>You may revoke the pending invitation using the button below:</span>
					<p><a href="/edit/mod/?assetid={$asset['assetid']}&revokenewownership=1" class="button btndelete">REVOKE</a></p>
				{else}
					<div>
						<label>Select new owner</label>
						<small>Ownership can only be transferred by the current owner of this resource.</small>
						<br>
						<small>Ownership can only be transferred to an existing teammember.</small>
						<br>
						<small>A notification will be sent to the specified user, inviting them to accept ownership.</small>
						<br>

						<select name="newownerid">
							<option value="" selected="selected">--- Select new owner ---</option>
							{foreach from=$teammembers item=teammember}
								{if !$teammember['pending']}<option value="{$teammember['userid']}" title="{$teammember['name']}">{$teammember['name']}</option>{/if}
							{/foreach}
						</select>
					</div>
				{/if}

			</div>
		{/if}
</div>

<div class="file template">
	<input type="hidden" name="fileids[]" value="" />
	<a href="#">
		<div class="fi">
			<div class="fi-content"></div>
		</div>
		<img src="" style="display:none;" />
		<div class="filename"></div><br>
		<div class="uploaddate"></div><br>
		<div class="uploadprogress"></div>
	</a>
</div>

<div class="connection template editbox" style="clear:both;">
	<input type="hidden" name="connectionid[]" value="">
	<select name="connectiontypeid[]" class="required" style="width:150px;">
		{foreach from=$connectiontypes item=connectiontype}
			<option value="{$connectiontype['connectiontypeid']}">{$connectiontype['name']}</option>
		{/foreach}
	</select>

	<select name="assettypeid[]" class="required" style="width: 150px">
		<option value="">-</option>
		{foreach from=$assettypes item=assettype}
			<option value="{$assettype['assettypeid']}">{$assettype['name']}</option>
		{/foreach}
	</select>

	<select name="toassetid[]" class="required" style="width: 300px">
	</select>

	<a href="#" class="delete"></a>
</div>

<p style="clear:both;"><br></p>

{include
	file="submitbutton"
	href="javascript:submitForm(0)"
	buttontext="Save"
}

{if $asset['assetid'] && canDeleteAsset($asset, $user)}
	<span style="float:right;">
		{include
			file="button"
			class="btndelete"
			href="javascript:submitDelete()"
			buttontext="Delete `$entrysingular`"
		}
	</span>
{/if}

<p style="clear:both;"><br></p>


{capture name="footerjs"}
	<script type="text/javascript">
		$(document).ready(function() {
			$('form[name=commentformtemplate]').areYouSure();
		});
	</script>	

	<script type="text/javascript" src="/web/js/edit-asset.js?version=30" async></script>
{/capture}

{include file="footer"}