{include file="header"}

<div class="edit-asset edit-mod">

	<h2>
		<span class="assettype">
			<a href="/list/mod">Mods</a>
		</span> /
		{if $asset['assetid']}
			<span>
				<a href="{formatModPath($mod)}">{$asset["name"]}</a>
			</span> / 
			<span>Edit</span>
		{else}
			<span>Add new Mod</span>
		{/if}
	</h2>

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

		<h3 class="flex-fill">Screenshots<span style="float:right; font-size:70%;">(drag&drop to upload{if false /*:ZipDownloadDisabled*/ && (count($files) > 0)}, <a href="/download?assetid={$asset['assetid']}">download all as zip</a>{/if})</span></h3>
		{include file="edit-asset-files.tpl"}

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
		<div class="editbox" style="align-self: baseline;">
			<label>Side</label>
			<select name="side">
				<option value="client" {if ($asset['side']=='client')}selected="selected" {/if}>Client side only mod</option>
				<option value="server" {if ($asset['side']=='server')}selected="selected" {/if}>Server side only mod</option>
				<option value="both" {if (empty($asset['side']) || $asset['side']=='both')}selected="selected" {/if}>Client and Server side mod</option>
			</select>
		</div>

		<div class="editbox" style="align-self: baseline;">
			<label>ModDB Logo image</label>
			<small>The ModDB logo is selected from the 'Screenshots' and has to be 480x480 or 480x320 px. This image will be used for mod cards on the Mod DB. <span class="text-error">Images selected as logos will not be displayed in the slideshow.</span></small>
			<select name="cardlogofileid">
				<option value="">--- Default ---</option>
				{foreach from=$files item=file}
					{if $file['imagesize'] === '480x320' || $file['imagesize'] === '480x480'}
					<option value="{$file['fileid']}" data-url="{$file['url']}"{if $asset['cardlogofileid']==$file['fileid']} selected="selected" {/if}>
						{$file['filename']} [{$file['imagesize']} px]</option>
					{/if}
				{/foreach}
			</select>
		</div>
		<div class="editbox" style="align-self: baseline;">
			<label>External Logo image</label>
			<small>The external logo is selected from the 'Screenshots', has to be 480x480 or 480x320 px. This image will be used for social media embeds. If no specific logo is selected here, but a ModDB logo is selected, the upper 480x320 px of that ModDB logo will be used. <span class="text-error">Images selected as logos will not be displayed in the slideshow.</span></small>
			<select name="embedlogofileid">
				<option value="">--- Default (crop ModDB image) ---</option>
				{foreach from=$files item=file}
					{if $file['imagesize'] === '480x320' || $file['imagesize'] === '480x480'}
					<option value="{$file['fileid']}" data-url="{$file['url']}"{if $asset['embedlogofileid']==$file['fileid']} selected="selected" {/if}>
						{$file['filename']} [{$file['imagesize']} px]</option>
					{/if}
				{/foreach}
			</select>
		</div>

		<div class="flex-spacer"></div>
		<div id="preview-box-db" class="editbox" style="width: calc(300px + .5em); align-self: baseline;">
			<label>ModDB Card Preview</label>
			{include file="list-mod-entry"}
		</div>
		<div id="preview-box-external" class="editbox" style="width: calc(300px + .5em); align-self: baseline;">
			<label><label><abbr title="Every platform uses this data differently, this is just an example of what it might look like.">External Preview</abbr></label></label>
			<div>
				<h4>{$mod['name']}</h4>
				<div><small>Description...</small></div>
				<img src="{empty($mod['logocdnpath_external']) ? '/web/img/mod-default.png' : formatCdnUrlFromCdnPath($mod['logocdnpath_external'])}" />
			</div>
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
{include file="edit-asset-files-template.tpl"}

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

<p style="clear:both"><br/></p>

<a class="button large submit shine" href="javascript:submitForm(0)">Save</a>

{if $asset['assetid'] && canDeleteAsset($asset, $user)}
	<span style="float:right;">
		<a class="button large btndelete shine" href="javascript:submitDelete()">Delete Mod</a>
	</span>
{/if}


{capture name="footerjs"}
	<script type="text/javascript">
		const $dbLogoSelect = $('select[name="cardlogofileid"]');
		const $externalLogoSelect = $('select[name="embedlogofileid"]');
		const dbPreviewBoxEl = document.getElementById('preview-box-db');
		const externalPreviewBoxEl = document.getElementById('preview-box-external');

		{
			const dbImageEl = dbPreviewBoxEl.getElementsByTagName('img')[0];
			const dbDescriptionEl = dbPreviewBoxEl.querySelector('.moddesc>a');
			const dbTitleEl = dbDescriptionEl.firstElementChild;
			const dbSummaryEl = dbDescriptionEl.lastElementChild;

			const externalTitleEl = externalPreviewBoxEl.children[1].firstElementChild;
			const externalImageEl = externalPreviewBoxEl.getElementsByTagName('img')[0];
			
			$('input[name="name"]').on('input', function(e) {
				let text = e.target.value;
				if(text.length >= 49) text = text.substr(0, 45)+'...';
				dbTitleEl.textContent = text;
				externalTitleEl.textContent = text;
			});
			$('input[name="summary"]').on('input', function(e) {
				dbSummaryEl.textContent = e.target.value;
			});

			const fileFrameEl = document.getElementsByClassName('files')[0];
			
			$dbLogoSelect.on('change', function(e, ex) {
				let src = '/web/img/mod-default.png';
				if(ex.selected) {
					src = $(`option[value="${ex.selected}"]`, $dbLogoSelect).data('url')
				}
				dbImageEl.src = src;
			});

			$externalLogoSelect.on('change', function(e, ex) {
				let src = '/web/img/mod-default.png';
				if(ex.selected) {
					src = $(`option[value="${ex.selected}"]`, $externalLogoSelect).data('url')
				}
				externalImageEl.src = src;
			});
		}

		function onUploadFinished(response) {
			function addOption($select) {
				const opt = document.createElement('option');
				opt.value = response.fileid;
				opt.textContent = `${response.filename} [${response.imagesize} px]`;
				opt.dataset.url = response.filepath;

				$select.append(opt);
				$select.trigger("chosen:updated");
			}

			if(['480x320', '480x480'].includes(response.imagesize)) {
				addOption($dbLogoSelect);
				addOption($externalLogoSelect);
			}
		}

		function onFileDelete($fileEl, fileid) {
			function removeOpt($select, previewBox) {
				const opt = $select[0].querySelector(`option[value="${fileid}"]`);
				if(opt) {
					const previewImage = previewBox.getElementsByTagName('img')[0];
					if(previewImage && previewImage.src.endsWith(opt.dataset.url) /* fix for url vs path comparison */) {
						previewImage.src = '/web/img/mod-default.png';
					}

					opt.remove();
					$select.trigger("chosen:updated");
				}
			}

			removeOpt($dbLogoSelect, dbPreviewBoxEl);
			removeOpt($externalLogoSelect, externalPreviewBoxEl);
		}
	</script>
	<style>
		#preview-box-external>div {
			background-color: var(--color-content-bg);
			padding: .25em;
		}
		#preview-box-external h4,
		#preview-box-external>div>div {
			margin: 1em 0;
		}
		#preview-box-external img {
			width: 100%;
		}
	</style>

	<script type="text/javascript" src="/web/js/edit-asset.js?version=34" async></script>
{/capture}

{include file="footer"}