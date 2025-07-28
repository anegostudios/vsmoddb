{include file="header"}

<div class="edit-asset edit-mod">

	<h2>
		<span>
			<a href="/list/mod">Mods</a>
		</span> /
		{if $asset['assetId']}
			<span>
				<a href="{formatModPath($mod)}">{$asset["name"]}</a>
			</span> / 
			<span>Edit</span>
		{else}
			<span>Add new Mod</span>
		{/if}
	</h2>

	{if canModerate(null, $user)}
	<div style="float: right;">
		<button class="button large shine moderator" style="height:unset;" onclick="lockModDlg(this)">Lock Mod...</button>
	</div>
	{/if}

	<form method="post" name="deleteform">
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<input type="hidden" name="delete" value="1">
	</form>

	<form method="post" name="form1" autocomplete="off" class="flex-list">
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="assetid" value="{$asset['assetId']}">
		<input type="hidden" name="numsaved" value="{$asset['numSaved']}">
		<input type="hidden" name="saveandback" value="0">

		<div class="editbox short">
			<label><abbr title="Only mods with Status 'Published' are publicly visible">Status</abbr></label>
			<select name="statusid"{if $asset['statusId'] == STATUS_LOCKED && !canModerate(null, $user)} disabled="true"{/if}>
				{foreach from=$stati item=status}
					<option value="{$status['statusId']}"{if $asset['statusId']==$status['statusId']} selected="selected"{/if}>{$status['name']}</option>
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
					<option value="{$tag['tagId']}" title="{$tag['text']}"{if isset($asset['tags'][$tag['tagId']])} selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox">
			<label>Name</label>
			<input type="text" name="name" class="required" value="{$asset['name']}" />
		</div>

		<div class="editbox wide">
			<label><abbr title="If set, your mod can be reached with this custom url. Only alphabetical letters are allowed.">URL Alias</abbr></label>
			<label for="inp-urlalias" class="prefixed-input" data-prefix="https://mods.vintagestory.at/"><input id="inp-urlalias" type="text" name="urlalias" value="{$asset['urlAlias']}" style="width: 21ch" /></label>
		</div>

		<div class="editbox flex-fill">
			<label>Summary. Describe your mod in 100 characters or less.</label>
			<input type="text" name="summary" maxlength="100" class="required" value="{$asset['summary']}" />
		</div>

		<div class="editbox flex-fill">
			<label>Text</label>
			<textarea name="text" class="editor" data-editorname="text" style="width: 100%; height: auto;">{$asset['text']}</textarea>
		</div>

		{if file_exists("templates/edit-asset-$entrycode.tpl")}
			{include file="edit-asset-`$entrycode`.tpl"}
		{/if}

		{if canEditAsset($asset, $user, false)}
			<h3 class="flex-fill">Team members</h3>

			<div id="teammembers-box" class="editbox wide pending-markers">
				<label>Team Members</label>
				<select name="teammemberids[]" multiple data-placeholder="Search Users"
					data-url="/api/authors?name=\{name}" data-ownerid="{$asset['createdByUserId']}">
					{if !empty($teamMembers)}
						{foreach from=$teamMembers item=teamMember}
							<option selected class="maybe-accepted{if !$teamMember['pending']} accepted{/if}" value="{$teamMember['userId']}" title="{$teamMember['name']}">{$teamMember['name']}</option>
						{/foreach}
					{/if}
				</select>
			</div>

			<div id="teameditors-box" class="editbox wide pending-markers">
				<label>Team Members with edit permissions</label>
				<select name="teammembereditids[]" multiple data-placeholder="Search Members">
					{foreach from=$teamMembers item=teamMember}
						<option {if $teamMember['canEdit']}selected{/if} class="maybe-accepted{if !$teamMember['pending']} accepted{/if}" value="{$teamMember['userId']}" title="{$teamMember['name']}">{$teamMember['name']}</option>
					{/foreach}
				</select>
			</div>
		{/if}

		<h3 class="flex-fill">Screenshots<span style="float:right; font-size:70%;">(drag&drop to upload{if false /*:ZipDownloadDisabled*/ && (count($files) > 0)}, <a href="/download?assetid={$asset['assetId']}">download all as zip</a>{/if})</span></h3>
		{include file="edit-asset-files.tpl"}

		<h3 class="flex-fill">Links</h3>
		<div class="editbox">
			<label>Homepage or Forum Post Url</label>
			<input type="url" name="homepageurl" value="{$asset['homepageUrl']}" />
		</div>

		<div class="editbox">
			<label>Trailer Video Url</label>
			<input type="url" name="trailervideourl" value="{$asset['trailerVideoUrl']}" />
		</div>

		<div class="editbox">
			<label>Source Code Url</label>
			<input type="url" name="sourcecodeurl" value="{$asset['sourceCodeUrl']}" />
		</div>

		<div class="editbox">
			<label>Issue tracker Url</label>
			<input type="url" name="issuetrackerurl" value="{$asset['issueTrackerUrl']}" />
		</div>

		<div class="editbox">
			<label>Wiki Url</label>
			<input type="url" name="wikiurl" value="{$asset['wikiUrl']}" />
		</div>

		<div class="editbox">
			<label>Donate Url</label>
			<input type="url" name="donateurl" value="{$asset['donateUrl']}" />
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
					{if $file['imageSize'] === '480x320' || $file['imageSize'] === '480x480'}
					<option value="{$file['fileId']}" data-url="{$file['url']}"{if $asset['cardlogofileid']==$file['fileId']} selected="selected" {/if}>
						{$file['name']} [{$file['imageSize']} px]</option>
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
					{if $file['imageSize'] === '480x320' || $file['imageSize'] === '480x480'}
					<option value="{$file['fileId']}" data-url="{$file['url']}"{if $asset['embedlogofileid']==$file['fileId']} selected="selected" {/if}>
						{$file['name']} [{$file['imageSize']} px]</option>
					{/if}
				{/foreach}
			</select>
		</div>

		<div class="flex-spacer"></div>
		<div id="preview-box-card" class="editbox" style="width: calc(300px + .5em); align-self: baseline;" data-fid="{$mod['cardLogoFileId']}">
			<label>ModDB Card Preview</label>
			{include file="list-mod-entry"}
		</div>
		<div id="preview-box-embed" class="editbox" style="width: calc(300px + .5em); align-self: baseline;" data-fid="{$mod['embedLogoFileId']}">
			<label><label><abbr title="Every platform uses this data differently, this is just an example of what it might look like.">External Preview</abbr></label></label>
			<div>
				<h4>{$mod['name']}</h4>
				<div><small>Description...</small></div>
				<img src="{empty($mod['logoCdnPathExternal']) ? '/web/img/mod-default.png' : formatCdnUrlFromCdnPath($mod['logoCdnPathExternal'])}" />
			</div>
		</div>

		{if $asset['assetId'] && canEditAsset($asset, $user, false)}
			<h3 class="flex-fill">Ownership transfer</h3>

			<div class="editbox wide">
				{if isset($ownershipTransferUser) && $ownershipTransferUser}
					<span>An ownership transfer invitation has been sent to: {$ownershipTransferUser}.</span>
					<br>
					<span>You may revoke the pending invitation using the button below:</span>
					<p><a href="/edit/mod/?assetid={$asset['assetId']}&revokenewownership=1" class="button btndelete">REVOKE</a></p>
				{else}
					<div>
						<label>Select new owner</label>
						<small>Ownership can only be transferred by the current owner of this resource.</small>
						<br>
						<small>Ownership can only be transferred to an existing team member.</small>
						<br>
						<small>A notification will be sent to the specified user, inviting them to accept ownership.</small>
						<br>

						<select name="newownerid">
							<option value="" selected="selected">--- Select new owner ---</option>
							{foreach from=$teamMembers item=teamMember}
								{if !$teamMember['pending']}<option value="{$teamMember['userId']}" title="{$teamMember['name']}">{$teamMember['name']}</option>{/if}
							{/foreach}
						</select>
					</div>
				{/if}

			</div>
		{/if}
</div>
{include file="edit-asset-files-template.tpl"}

<p style="clear:both"><br/></p>

<a class="button large submit shine" href="javascript:submitForm(0)">{if $asset['statusId'] != STATUS_LOCKED || canModerate(null, $user)}Save{else}Request Review{/if}</a>

{if $asset['assetId'] && canDeleteAsset($asset, $user)}
	<span style="float:right;">
		<a class="button large btndelete shine" href="javascript:submitDelete()">Delete Mod</a>
	</span>
{/if}

{capture name="footerjs"}
	<script type="text/javascript">
		const targetModId = {$asset['modId'] ?? 0};
		function lockModDlg(btnEl) {
			const message = prompt("Locking a mod will disable automatic downloads for the duration.\nPlease provide a reason for locking this mod.\nThis reason will be displayed to the mod author and logged. The reason message should contain information on how the author can get their mod to be unlocked again.");

			if(!message) return;

			btnEl.disabled = true;

			$.post('/api/v2/mods/'+targetModId+'/lock', { 'reason': message, 'at': actiontoken })
			.fail(jqXHR => {
				btnEl.disabled = false;
				const d = JSON.parse(jqXHR.responseText);
				addMessage(MSG_CLASS_ERROR, 'Failed to lock mod' + (d.reason ? (': '+d.reason) : '.'), true)
			})
			.done(() => {
				addMessage(MSG_CLASS_OK, 'Mod Locked.');
				window.location.reload();
			});
		}
		
		const $cardLogoSelect = $('select[name="cardlogofileid"]');
		const $embedLogoSelect = $('select[name="embedlogofileid"]');
		const cardPreviewBoxEl = document.getElementById('preview-box-card');
		const embedPreviewBoxEl = document.getElementById('preview-box-embed');

		{
			const cardImageEl = cardPreviewBoxEl.getElementsByTagName('img')[0];
			const cardDescriptionEl = cardPreviewBoxEl.querySelector('.moddesc>a');
			const cardTitleEl = cardDescriptionEl.firstElementChild;
			const cardSummaryEl = cardDescriptionEl.lastElementChild;

			const embedTitleEl = embedPreviewBoxEl.children[1].firstElementChild;
			const embedImageEl = embedPreviewBoxEl.getElementsByTagName('img')[0];


			if(!$cardLogoSelect.val() && !cardImageEl.src.endsWith('/web/img/mod-default.png')) {
				alert("Saving this mod without selecting a new logo will remove its current legacy logo.");
			}


			$('input[name="name"]').on('input', function(e) {
				let text = e.target.value;
				if(text.length >= 49) text = text.substr(0, 45)+'...';
				cardTitleEl.textContent = text;
				embedTitleEl.textContent = text;
			});
			$('input[name="summary"]').on('input', function(e) {
				cardSummaryEl.textContent = e.target.value;
			});

			const fileFrameEl = document.getElementsByClassName('files')[0];
			
			$cardLogoSelect.on('change', function(e, ex) {
				cardPreviewBoxEl.dataset.fid = ex.selected;

				let src = '/web/img/mod-default.png';
				if(ex.selected) {
					src = $(`option[value="${ex.selected}"]`, $cardLogoSelect).data('url')
				}
				cardImageEl.src = src;
			});

			$embedLogoSelect.on('change', function(e, ex) {
				embedPreviewBoxEl.dataset.fid = ex.selected;

				let src = '/web/img/mod-default.png';
				if(ex.selected) {
					src = $(`option[value="${ex.selected}"]`, $embedLogoSelect).data('url')
				}
				embedImageEl.src = src;
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
				addOption($cardLogoSelect);
				addOption($embedLogoSelect);
			}
		}

		function onFileDelete($fileEl, fileid) {
			function removeOpt($select, previewBox) {
				const opt = $select[0].querySelector(`option[value="${fileid}"]`);
				if(opt) {
					const previewImage = previewBox.getElementsByTagName('img')[0];
					if(previewImage && previewBox.dataset.fid == opt.value) {
						previewImage.src = '/web/img/mod-default.png';
					}

					opt.remove();
					$select.trigger("chosen:updated");
				}
			}

			removeOpt($cardLogoSelect, cardPreviewBoxEl);
			removeOpt($embedLogoSelect, embedPreviewBoxEl);
		}
	</script>
	<style>
		#preview-box-embed>div {
			background-color: var(--color-content-bg);
			padding: .25em;
		}
		#preview-box-embed h4,
		#preview-box-embed>div>div {
			margin: 1em 0;
		}
		#preview-box-embed img {
			width: 100%;
		}
	</style>

	<script type="text/javascript" src="/web/js/edit-asset.js?version=37" async></script>
{/capture}

{include file="footer"}