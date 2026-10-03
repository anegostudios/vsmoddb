{capture name="head"}
<link nonce="{$cspNonce}" href="/web/css/prism.min.css?version=0" rel="stylesheet" type="text/css">
{/capture}
{include file="header" hclass="innercontent with-buttons-bottom"}

<div class="edit-asset" style="padding: 1em 1em 0 1em">

	<h2>
	{if $mod['assetId']}
		<span>
		{if $mod['category'] == CATEGORY_SERVER_TWEAK}
			<a href="/list/mod?c=s">Server Tweaks</a>
		{else}
			<a href="/list/mod">Mods</a>
		{/if}
		</span> /
		<span>
			<a href="{formatModPath($mod)}">{$mod["name"]}</a>
		</span> / 
		<span>Edit</span>
	{else}
		<span><a href="/list/mod">Mods</a></span> /
		<span>Add new Mod</span>
	{/if}
	</h2>

	<form method="post" name="deleteform">
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<input type="hidden" name="delete" value="1">
	</form>

	<form method="post" name="form1" autocomplete="off" class="flex-list are-you-sure">
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="assetid" value="{$mod['assetId']}">
		<input type="hidden" name="saveandback" value="0">

		<div class="editbox short">
			<label><abbr title="Only mods with Status 'Published' are publicly visible">Status</abbr></label>
			<select name="statusId"{if $mod['statusId'] == STATUS_LOCKED && !canModerate(null, $user)} disabled="true"{/if} noSearch="noSearch">
				{foreach from=$stati item=name key=status}
					<option value="{$status}"{if $mod['statusId'] == $status} selected="selected"{/if}>{$name}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox short">
			<label><abbr title="Only mods from the category 'Game Mod' are available in the in-game mod browser">Category</abbr></label>
			<select name="category" class="required" noSearch="noSearch" data-placeholder="Select">
				{if $mod['category'] === CATEGORY_NOT_SPECIFIED}<option value=""></option>{/if}
				{foreach from=$modCategories item=name key=category}
					<option value="{$category}"{if $mod['category'] === $category} selected="selected"{/if}>{$name}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox">
			<label><abbr title="Tags with negative total votes are hidden by default and are ignored when searching.&#x0a;Tags with total votes below <?= TAG_HIDE_THRESHOLD ?> are additionally fully hidden.">Tags</abbr></label>
			<select name="tagids[]" multiple>
				{foreach from=$tags item=tag key=tagId}
					<option value="{$tagId}" title="{$tag['text']}"{if isset($mod['tags'][$tagId])} selected="selected"{/if}>{$tag['name']}{if isset($mod['tags'][$tagId])} ({$mod['tags'][$tagId]['votes']}){/if}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox">
			<label>Name</label>
			<input type="text" name="name" class="required" value="{$mod['name']}" />
		</div>

		<div class="editbox wide">
			<label><abbr title="If set, your mod can be reached with this custom url. Only alphabetical letters are allowed.">URL Alias</abbr></label>
			<label for="inp-urlalias" class="prefixed-input" data-prefix="https://mods.vintagestory.at/"><input id="inp-urlalias" type="text" name="urlAlias" value="{$mod['urlAlias']}" style="width: 21ch" /></label>
		</div>

		<div class="editbox flex-fill">
			<label>Summary. Describe your mod in 100 characters or less.</label>
			<input type="text" name="summary" maxlength="100" class="required" value="{$mod['summary']}" />
		</div>

		<div class="editbox flex-fill">
			<label>Text</label>
			<textarea name="text" class="editor" data-editorname="text" style="width: 100%; height: auto;">{$mod['text']}</textarea>
		</div>

		{if $canEditTeamMembers = canEditMod($mod, $user, false)}
			<h3 class="flex-fill">Team members</h3>

			<div id="teammembers-box" class="editbox wide pending-markers">
				<label>Team Members</label>
				<select name="teammemberids[]" multiple data-placeholder="Search Users"
					data-url="/api/v2/users/by-name/\{name}" data-ignore-id="{$mod['createdByUserHash']}">
					{if !empty($teamMembers)}
						{foreach from=$teamMembers item=teamMember}
							<option selected class="maybe-accepted{if !$teamMember['pending']} accepted{/if}" value="{$teamMember['hash']}" title="{$teamMember['name']}">{$teamMember['name']}</option>
						{/foreach}
					{/if}
				</select>
			</div>

			<div id="teameditors-box" class="editbox wide pending-markers">
				<label>Team Members with edit permissions</label>
				<select name="teammembereditids[]" multiple data-placeholder="Search Members">
					{foreach from=$teamMembers item=teamMember}
						<option {if $teamMember['canEdit']}selected{/if} class="maybe-accepted{if !$teamMember['pending']} accepted{/if}" value="{$teamMember['hash']}" title="{$teamMember['name']}">{$teamMember['name']}</option>
					{/foreach}
				</select>
			</div>
		{/if}

		<h3 class="flex-fill">Screenshots {$screenshotsDisclaimer}<small style="float:right;">(drag&drop to upload)</small></h3>
		<ol class="no-mark files flex-list reorderable">
			{foreach from=$files item=file}
				<li class="file">
					<input type="hidden" name="fileIds[]" value="{$file['fileId']}" />
					{if $file['hasThumbnail']}
						<a data-fancybox="gallery" href="{$file['url']}">
							<img src="{formatCdnUrl($file, '_55_60')}"/>
							<div class="details">
								<h5 class="filename">{$file["name"]}</h5>
								<small class="uploaddate">{$file["created"]}</small>
								<small>
									<span class="imagesize">{$file["imageSize"]} px</span>
									{if $file["size"]} - <span class="size">{formatByteSize($file["size"])}</span>{/if}
								</small>
							</div>
						</a>
					{else}
						<a href="{$file['url']}">
							<div class="fi fi-{$file['ext']}">
								<div class="fi-content">{$file['ext']}</div>
							</div>
							<div class="details">
								<h5 class="filename">{$file["name"]}</h5>
								<small class="uploaddate">{$file["created"]}</small>
								{if $file["size"]}<small class="size">{formatByteSize($file["size"])}</small>{/if}
							</div>
						</a>
					{/if}
					<a href="#" class="delete" data-fileid="{$file['fileId']}"></a>
					<a href="{formatDownloadTrackingUrl($file)}" class="download">&#11123;</a>
				</li>
			{/foreach}
		</ol>

		<h3 class="flex-fill">Links</h3>
		<div class="editbox">
			<label>Homepage or Forum Post Url</label>
			<input type="url" name="homepageUrl" value="{$mod['homepageUrl']}" />
		</div>

		<div class="editbox">
			<label>Trailer Video Url</label>
			<input type="url" name="trailerVideoUrl" value="{$mod['trailerVideoUrl']}" />
		</div>

		<div class="editbox">
			<label>Source Code Url</label>
			<input type="url" name="sourceCodeUrl" value="{$mod['sourceCodeUrl']}" />
		</div>

		<div class="editbox">
			<label>Issue tracker Url</label>
			<input type="url" name="issueTrackerUrl" value="{$mod['issueTrackerUrl']}" />
		</div>

		<div class="editbox">
			<label>Wiki Url</label>
			<input type="url" name="wikiUrl" value="{$mod['wikiUrl']}" />
		</div>

		<div class="editbox">
			<label>Donate Url</label>
			<input type="url" name="donateUrl" value="{$mod['donateUrl']}" />
		</div>

		<h3 class="flex-fill">Additional information</h3>
		<div class="editbox" style="align-self: baseline;">
			<label>Side</label>
			<select name="side" noSearch="noSearch">
				{foreach from=$modSidedness item=name key=side}
					<option value="{$side}"{if $mod['side'] === $side} selected="selected"{/if}>{$name}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox" style="align-self: baseline;">
			<label>ModDB Logo image</label>
			<small>The ModDB logo is selected from the 'Screenshots' and has to be 480x480 or 480x320 px. This image will be used for mod cards on the Mod DB. <span class="text-error">Images selected as logos will not be displayed in the slideshow.</span></small>
			<select name="cardLogoFileId">
				<option value="">--- Default ---</option>
				{foreach from=$files item=file}
					{if $file['imageSize'] === '480x320' || $file['imageSize'] === '480x480'}
					<option value="{$file['fileId']}" data-url="{$file['url']}"{if $mod['cardLogoFileId']==$file['fileId']} selected="selected" {/if}>
						{$file['name']} [{$file['imageSize']} px]</option>
					{/if}
				{/foreach}
			</select>
		</div>
		<div class="editbox" style="align-self: baseline;">
			<label>External Logo image</label>
			<small>The external logo is selected from the 'Screenshots', has to be 480x480 or 480x320 px. This image will be used for social media embeds. If no specific logo is selected here, but a ModDB logo is selected, the upper 480x320 px of that ModDB logo will be used. <span class="text-error">Images selected as logos will not be displayed in the slideshow.</span></small>
			<select name="embedLogoFileId">
				<option value="">--- Default (crop ModDB image) ---</option>
				{foreach from=$files item=file}
					{if $file['imageSize'] === '480x320' || $file['imageSize'] === '480x480'}
					<option value="{$file['fileId']}" data-url="{$file['url']}"{if $mod['embedLogoFileId']==$file['fileId']} selected="selected" {/if}>
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
			<label><abbr title="Every platform uses this data differently, this is just an example of what it might look like.">External Preview</abbr></label>
			<div>
				<h4>{$mod['name']}</h4>
				<div><small>Description...</small></div>
				<img src="{empty($mod['logoCdnPathExternal']) ? '/web/img/mod-default.png' : formatCdnUrlFromCdnPath($mod['logoCdnPathExternal'])}" alt="Mod Thumbnail" />
			</div>
		</div>

		{if $mod['assetId'] && canEditMod($mod, $user, false)}
			<h3 id='ownership-transfer' class="flex-fill">Ownership transfer</h3>

			<div class="editbox wide">
				{if !empty($ownershipTransferUser)}
					<span>An ownership transfer invitation has been sent to: {$ownershipTransferUser}.</span>
					<br>
					<span>You may revoke the pending invitation using the button below:</span>
					<p><button type="submit" name="revokenewownership" value="1" class="button btndelete">REVOKE</button></p>
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
	</form>

	{if $apiTokens !== null}
	<form id="api-tokens" class="flex-list" autocomplete="off" method="post" data-user-name="{$user['name']}" action="/api/v2/mods/{$mod['modId']}/api-tokens">
		<h3 class="flex-fill">API tokens</h3>
		<p class="flex-fill">
			<small>
				API tokens let automated tools (e.g. a CI pipeline) upload new releases of this mod. A token can only upload releases for this mod and acts as the team member who created it.
				Tokens expire after at most <?= API_TOKEN_MAX_LIFETIME_DAYS ?> days and are deleted if their creator loses edit permissions for this mod.
			</small>
			{if !$isApiTokenUploadSupported}
				<br><small class="text-error">Uploading releases with API tokens is currently only supported for mods in the 'Game Mod' category.</small>
			{/if}
		</p>

		<table id="api-tokens-table" class="stdtable flex-fill"{if empty($apiTokens)} hidden{/if}>
			<thead><tr><th>Name</th><th>Created by</th><th>Created</th><th>Expires</th><th>Last used</th><th></th></tr></thead>
			<tbody>
				{foreach from=$apiTokens item=apiToken}
				<tr data-token-id="{$apiToken['tokenId']}">
					<td class="api-token-name">{$apiToken['name']}</td>
					<td>{$apiToken['creatorName']}</td>
					<td>{$apiToken['created']}</td>
					<td>{$apiToken['expires']}{if $apiToken['isExpired']} <span class="text-error">(expired)</span>{/if}</td>
					<td>{$apiToken['lastUsed']}</td>
					<td><button type="button" class="button btndelete api-token-revoke">Revoke</button></td>
				</tr>
				{/foreach}
			</tbody>
		</table>
		<p id="api-tokens-empty" class="flex-fill"{if !empty($apiTokens)} hidden{/if}><i>No API tokens.</i></p>

		{if $canCreateApiTokens}
			<div class="editbox">
				<label for="api-token-name">New token name</label>
				<input id="api-token-name" type="text" name="name" maxlength="64" required placeholder="e.g. GitHub Actions">
			</div>
			<div class="editbox short">
				<label for="api-token-lifetime">Expires after</label>
				<select id="api-token-lifetime" name="lifetimeDays" noSearch="noSearch">
					{foreach from=$apiTokenLifetimes item=days}
						<option value="{$days}"{if $days === API_TOKEN_MAX_LIFETIME_DAYS} selected="selected"{/if}>{$days} days</option>
					{/foreach}
				</select>
			</div>
			<div class="editbox" style="align-self: end;">
				<button type="submit" class="button submit">Create token</button>
			</div>

			<div id="api-token-created" class="flex-fill bg-success" style="padding: .5em;" hidden>
				<label for="api-token-value">New token <b class="api-token-created-name"></b> created. <b>Copy it now, you will not be able to see it again.</b></label>
				<div style="display: flex; gap: .5em; margin-top: .25em;">
					<input id="api-token-value" type="text" readonly style="flex-grow: 1; font-family: monospace;">
					<button id="api-token-copy" type="button" class="button">Copy</button>
				</div>
			</div>

			<p class="flex-fill"><small>Usage: <code>curl -H "Authorization: Bearer &lt;token&gt;" -F "file=@mymod.zip" -F "gameversions[]=1.21.0" {$apiTokenUploadUrl}</code></small></p>
		{/if}
	</form>
	{/if}
</div>

{if $mod['modId'] && canModerate(null, $user)}
<dialog id="lock-mdl" closedby="any" autofocus="">
	<form class="with-buttons-bottom" method="dialog" data-method="post" autocomplete="off" action="/api/v2/mods/{$mod['modId']}/lock">
		<h1>Lock Mod</h1>
		<p>Are you sure want to lock this mod?</p>
		<p>Locking a mod will <b>disable automatic downloads</b> for the duration and <b>cannot be lifted by a normal contributor</b>.</p>
		<p>
			Please provide a reason for locking this mod.<br/>
			This reason will be displayed to the mod author and logged.<br/>
			The reason message should contain information on how the author can get their mod to be unlocked again.
		</p>
		<textarea name="reason"></textarea>
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<div class="buttons">
			<button class="button large btndelete shine moderator" id="lock-subm" onclick="return false;">Lock</button>
			<button class="button large shine" style="margin-left:auto;" formmethod="dialog">Cancel</button>
		</div>
	</form>
</dialog>
{/if}

<div class="buttons">
	<a class="button large submit shine" href="javascript:submitForm(0)">{if $mod['statusId'] != STATUS_LOCKED || canModerate(null, $user)}Save{else}Request Review{/if}</a>

	{if canModerate(null, $user) && $mod['modId']}
		<button class="button large shine moderator" style="height:unset;" data-opens-dialog="lock-mdl" onclick="return false;">Lock Mod...</button>
		<button class="button large shine moderator" style="height:unset;padding-left:.5em;padding-right:.5em;" onclick="releaseSizeLimitDlg(this); return false;">Set Custom Release Size Limit...</button>
	{/if}

	{if $mod['assetId'] && canDeleteAsset($mod, $user)}
		<a class="button large btndelete shine" style="margin-left:auto;" href="javascript:submitDelete()">Delete Mod</a>
	{/if}
</div>

{capture name="footerjs"}
	{include file="edit-asset-files-template.tpl"}
	<script nonce="{$cspNonce}" type="text/javascript">
		const modId = {$mod['modId'] ?? 0};
		
		{if $canEditTeamMembers}R.onDOMLoaded(() => attachRemoteSearchHandler(R.get('teammembers-box')));{/if}

		{if $mod['modId'] && canModerate(null, $user)}
		R.onDOMLoaded(() => { createEditor(R.getQ('#lock-mdl textarea'), tinymceSettingsCmt) });
		attachDialogSendHandler(R.get('lock-mdl'), (form, data) => \{
			if(!data.get('reason')) \{
				R.markAsErrorElement(form.getElementsByClassName('tox-tinymce')[0]);
				return false;
			}
			return true;
		}, (jqXHR) => \{
			R.attachDefaultFailHandler(jqXHR, "Failed to lock mod")
				.done(() => \{
					R.addMessage(MSG_CLASS_OK, 'Mod Locked.');
					window.location.reload();
				});
		});
		

		let currentReleaseUploadOverwrite = {$currentReleaseUploadOverwrite ?? 'null'};
		function releaseSizeLimitDlg(btnEl) \{
			const defaultReleaseUploadLimit = {$defaultReleaseUploadLimit};
			const maxUploadLimit = {$maxUploadLimit};

			function parseByteSize(string) {
				const match = /(\d+(?:\.\d*)?)\s*([kKmMgG]?[bB])?/i.exec(string);
				if(!match) return null;

				let scale = 1;
				switch((match[2] || '').toUpperCase()) {
					case 'GB': scale *= 1024;
					case 'MB': scale *= 1024;
					case 'KB': scale *= 1024;
					default:
						return Math.floor(parseFloat(match[1]) * scale)
				}
			}

			let newLimitStr = prompt(`Set a custom size limit for release files for this mod.\nOnly do this to reasonable limits and when given a good reason.\nMost mods do not need this to be adjusted.\n\n- Deafult Upload Limit: $\{R.formatByteSize(defaultReleaseUploadLimit)}\n- Current Limit: $\{currentReleaseUploadOverwrite === null ? '[Default]' : R.formatByteSize(currentReleaseUploadOverwrite)}\n- Max Limit: $\{R.formatByteSize(maxUploadLimit)}\n- Keep in mind that 1000 is not 1KB.\n- Set to 'default' to reset to default.\n\nNew Limit (raw number or suffixed, e.g. '5.7MB'):`);

			if(!newLimitStr || !(newLimitStr = newLimitStr.trim())) { btnEl.disabled = false; return; }

			let newLimit;
			
			if(newLimitStr.toLowerCase() === 'default') {
				newLimit = null;
			}
			else {
				newLimit = parseByteSize(newLimitStr);
				if(newLimit === null) {
					alert(`Failed to parse new limit from '$\{newLimitStr}'.\nThe input should look like '64MB', '67108864' or 'default'`);
					btnEl.disabled = false; return;
				}
				if(newLimit > maxUploadLimit) {
					alert(`$\{R.formatByteSize(newLimit)} is to high for current server settings. The current maximum possible is $\{R.formatByteSize(maxUploadLimit)}.`);
					btnEl.disabled = false; return;
				}
			}

			const xhr = $.ajax({ method: 'PUT', url: '/api/v2/mods/'+modId+'/releases/upload-limit', data: { 'limit': newLimit, 'at': actiontoken }});
			R.attachDefaultFailHandler(xhr, 'Failed to set release upload limit for this mod');
			xhr.fail(jqXHR => btnEl.disabled = false)
			.done(() => {
				currentReleaseUploadOverwrite = newLimit;
				btnEl.disabled = false;
				R.addMessage(MSG_CLASS_OK, `Release upload limit for this mod changed to $\{R.formatByteSize(newLimit === null ? defaultReleaseUploadLimit : newLimit)}.`);
			});
		}
		{/if}
		
		const $cardLogoSelect = $('select[name="cardLogoFileId"]');
		const $embedLogoSelect = $('select[name="embedLogoFileId"]');
		const cardPreviewBoxEl = R.get('preview-box-card');
		const embedPreviewBoxEl = R.get('preview-box-embed');

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

				if(!$embedLogoSelect.val()) {
					maybeSelectInternalImageAsExternalImage();
				}
			});

			$embedLogoSelect.on('change', function(e, ex) {
				embedPreviewBoxEl.dataset.fid = ex.selected;

				if(ex.selected) {
					embedImageEl.src = $(`option[value="${ex.selected}"]`, $embedLogoSelect).data('url')
					return
				}

				maybeSelectInternalImageAsExternalImage();
			});

			function maybeSelectInternalImageAsExternalImage() {
				const $selected = $('option:selected', $cardLogoSelect);
				if($selected && $selected.text().endsWith('[480x320 px]')) {
					embedImageEl.src = $(`option[value="${$selected.val()}"]`, $embedLogoSelect).data('url')
				}
				else {
					embedImageEl.src = '/web/img/mod-default.png';
				}
			}
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

		window.allowdrop = true;
	</script>
	<style nonce="{$cspNonce}">
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

	{if $apiTokens !== null}
	<script nonce="{$cspNonce}" type="text/javascript">
		\{
			const formEl = R.get('api-tokens');
			const tableEl = R.get('api-tokens-table');
			const emptyEl = R.get('api-tokens-empty');
			const tokensUrl = '/api/v2/mods/'+modId+'/api-tokens';

			function updateApiTokensEmptyState() \{
				const isEmpty = tableEl.tBodies[0].rows.length === 0;
				tableEl.hidden = isEmpty;
				emptyEl.hidden = !isEmpty;
			}

			// Same format as fullDate() in lib/core.php. Dates from the api are in the same (server) timezone, so no conversion is needed.
			function formatFullDate(sqlDate) \{
				const m = /^(\d\{4})-(\d\{2})-(\d\{2}) (\d\{2}:\d\{2}:\d\{2})$/.exec(sqlDate);
				if(!m) return sqlDate;
				const day = parseInt(m[3]);
				const suffix = (day >= 11 && day <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][day % 10] ?? 'th');
				const month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][parseInt(m[2]) - 1];
				return `$\{month} $\{day}$\{suffix} $\{m[1]}, $\{m[4]}`;
			}

			tableEl.addEventListener('click', e => \{
				const btnEl = e.target.closest('.api-token-revoke');
				if(!btnEl) return;
				const rowEl = btnEl.closest('tr');
				const name = rowEl.querySelector('.api-token-name').textContent;
				if(!confirm(`Revoke the API token '$\{name}'?\nAnything still using it will no longer be able to upload releases. This cannot be undone.`)) return;

				btnEl.disabled = true;
				const xhr = $.ajax(\{ method: 'DELETE', url: tokensUrl+'/'+rowEl.dataset.tokenId, data: \{ 'at': actiontoken } });
				R.attachDefaultFailHandler(xhr, 'Failed to revoke API token');
				xhr.fail(() => btnEl.disabled = false)
				.done(() => \{
					rowEl.remove();
					updateApiTokensEmptyState();
					R.addMessage(MSG_CLASS_OK, `API token '$\{name}' revoked.`);
				});
			});

			const createdEl = R.get('api-token-created');
			if(createdEl) \{ // only present for users that may create tokens
				const nameInputEl = R.get('api-token-name');
				const lifetimeSelectEl = R.get('api-token-lifetime');
				const tokenValueEl = R.get('api-token-value');
				const submitBtnEl = formEl.querySelector('button[type="submit"]');

				formEl.addEventListener('submit', e => \{
					e.preventDefault(); // never submit this form normally, and it is separate from the mod form so it can't trigger a mod save either

					const name = nameInputEl.value.trim();
					submitBtnEl.disabled = true;
					const xhr = $.post(tokensUrl, \{ 'name': name, 'lifetimeDays': lifetimeSelectEl.value, 'at': actiontoken });
					R.attachDefaultFailHandler(xhr, 'Failed to create API token');
					xhr.always(() => submitBtnEl.disabled = false)
					.done(data => \{
						// The token is only ever shown here, it can't be retrieved again.
						createdEl.querySelector('.api-token-created-name').textContent = name;
						tokenValueEl.value = data.token;
						createdEl.hidden = false;
						tokenValueEl.focus();
						tokenValueEl.select();
						nameInputEl.value = '';

						const rowEl = tableEl.tBodies[0].insertRow(0);
						rowEl.dataset.tokenId = data.tokenId;
						for(const text of [name, formEl.dataset.userName, 'just now', formatFullDate(data.expires), 'never']) \{
							rowEl.insertCell().textContent = text;
						}
						rowEl.cells[0].className = 'api-token-name';
						const revokeBtnEl = document.createElement('button');
						revokeBtnEl.type = 'button';
						revokeBtnEl.className = 'button btndelete api-token-revoke';
						revokeBtnEl.textContent = 'Revoke';
						rowEl.insertCell().append(revokeBtnEl);
						updateApiTokensEmptyState();
					});
				});

				R.get('api-token-copy').addEventListener('click', () => \{
					tokenValueEl.select();
					if(!navigator.clipboard) \{
						R.addMessage(MSG_CLASS_ERROR, 'Copying is not available here, please copy the selected token manually.');
						return;
					}
					navigator.clipboard.writeText(tokenValueEl.value)
						.then(() => R.addMessage(MSG_CLASS_OK, 'API token copied to the clipboard.'))
						.catch(() => R.addMessage(MSG_CLASS_ERROR, 'Failed to copy, please copy the selected token manually.'));
				});
			}
		}
	</script>
	{/if}

	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/prism.min.js?v=0" data-manual=""></script>
	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/edit-asset.js?version=46" async></script>
	<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/jquery.fancybox.min.js" async></script>
{/capture}

{include file="footer"}