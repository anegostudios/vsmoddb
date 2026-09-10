{capture name="head"}
<link nonce="{$cspNonce}" href="/web/css/prism.min.css?version=0" rel="stylesheet" type="text/css">
{/capture}
{include file="header" hclass="innercontent with-buttons-bottom"}

<div class="edit-asset edit-release" style="padding: 1em 1em 0 1em">

	<h2>
		<span>
			<a href="/list/mod">Mods</a>
		</span> / 
		<span>
			<a href="{formatModPath($mod)}#tab-files">{$mod["name"]}</a>
		</span> / 
		<span>{$release['assetId'] ? 'Edit Release' : 'Add new Release'}</span>
	</h2>

	<form method="post" name="form1" enctype="multipart/form-data" autocomplete="off" class="flex-list are-you-sure">
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="assetid" value="{$release['assetId']}">
		<input type="hidden" name="modid" value="{$mod['modId']}">
		<input type="hidden" name="numsaved" value="{$release['numSaved']}">
		<input type="hidden" name="saveandback" value="0">
		
		{if ($mod['category'] & CATEGORY__MASK) === CATEGORY_GAME_MOD}
			<div class="editbox">
				<label>Compatible with game versions</label>
				<details class="version-selector">
					<summary class="button square">Select</summary>
					<div>
						<p class="count-label" style="text-align:center;padding:.25em;">{$c = count($release['compatibleGameVersions'])} Version{$c !== 1 ? 's' : ''} Selected</p>
						<h4>
							<label>Major</label>
							<label>Minor</label>
							<label>Patch</label>
							<label>Pre</label>
							<label></label>
						</h4>
						<div>
							{foreach from=$allGameVersionsTree item=major key=val}
							<div class="h" style="border-left-width: 0;">
								<div><span>{$val}</span></div>
								<div>
									{foreach from=$major item=minor key=val}
									<div class="h">
										<div><span>.{$val}</span></div>
										<div>
											{foreach from=$minor item=patch key=val}
											<div class="h">
												<div><span>.{$val}</span></div>
												<div>
													{foreach from=$patch item=version key=val}
														<div class="h">
															{if ($val & VERSION_MASK_PRERELEASE_KIND) === 0x4000}
															<div><span>.dev-{$val & VERSION_MASK_PRERELEASE_NUMBER}</span></div>
															{elseif ($val & VERSION_MASK_PRERELEASE_KIND) === 0x8000}
															<div><span>.pre-{$val & VERSION_MASK_PRERELEASE_NUMBER}</span></div>
															{elseif ($val & VERSION_MASK_PRERELEASE_KIND) === 0xc000}
															<div><span>.rc-{$val & VERSION_MASK_PRERELEASE_NUMBER}</span></div>
															{elseif ($val & VERSION_MASK_PRERELEASE_KIND) === 0xf000}
																{if $val !== 0xffff}
																<div><span>.{$val & VERSION_MASK_PRERELEASE_NUMBER}</span></div>
																{else}
																<div><span>&nbsp;</span></div>
																{/if}
															{/if}
															<input type="checkbox" name="cgvs[]" value="{$version['name']}" {if isset($release['compatibleGameVersions'][$version['version']])}checked=""{/if}>
														</div>
													{/foreach}
												</div>
											</div>
											{/foreach}
										</div>
									</div>
									{/foreach}
								</div>
							</div>
							{/foreach}
						</div>
					</div>
				</details>
			</div>

			{if $release["assetId"]}
				<div class="editbox">
					Created by: {$release['createdByUsername']}<br>
					{if $release['lastEditedByUsername'] && $release['createdByUsername'] != $release['lastEditedByUsername']}Last Edited by: {$release['lastEditedByUsername']}<br>{/if}
					Last modified: {fancyDate($release['lastModified'])}
				</div>
			{/if}
			
			<div class="editbox">
				<label><abbr title="This value is autodetected, please upload a file.">Mod Id</abbr></label>
				<input type="text" name="modidstr" class="required" value="{$release['identifier']}" disabled="">
			</div>
			<div class="editbox">
				<label><abbr title="This value is autodetected, please upload a file.">Mod Version Number</abbr></label>
				<label for="inp-modversion" class="prefixed-input disabled" data-prefix="v"><input id="inp-modversion" type="text" name="modversion" value="{$release['version']}" class="required" style="width: 10ch" disabled="" /></label>
			</div>
		{else}
			<div class="editbox">
				<label>Version Number</label>
				<label for="inp-modversion" class="prefixed-input" data-prefix="v"><input id="inp-modversion" type="text" name="modversion" value="{$release['version']}" class="required" style="width: 10ch" /></label>
			</div>
		{/if}
		
		
		<div class="editbox flex-fill">
			<label>Changelog</label>
			<textarea name="text" class="editor" data-editorname="text" style="width: 100%; height: auto;">{$release['text']}</textarea>
		</div>

		<h3 class="flex-fill">File <small>(cannot be altered after release is submitted)</small>{if !$release['assetId']}<small style="float:right;">(drag&drop to upload)</small>{/if}</h3>

		<ol class="no-mark files flex-list">
			{foreach from=$files item=file}
				<li class="file">
					<input type="hidden" name="fileIds[]" value="{$file['fileId']}" />
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
						{if !$release['assetId']}<a href="#" class="delete" data-fileid="{$file['fileId']}"></a>{/if}
					<a href="{formatDownloadTrackingUrl($file)}" class="download">&#11123;</a>
				</li>
			{/foreach}
			
			{if !$release['assetId']}
				<li class="editbox file-upload immovable">
					<label>Upload new file (or drag and drop, max file size: {formatByteSize($uploadSizeLimit)})</label>
					<input type="file" name="newfile" style="height: unset; padding: .25em;">
				</li>
			{/if}
		</ol>

		{if $release['assetId']}
		<h3 class="flex-fill">Mod relations</h3>
		<div class="editbox flex-fill">
			{include file="edit-release-relations" autoRelations=$autoRelations manualRelations=$manualRelations}
		</div>
		{/if}
	</form>

	{if $release['assetId']} 
		<p><br></p>
		<h3 style="margin-bottom:.5em;">Change log</h3>
		{if $auditLogs}
		<div class="audit-log-wrap">
			<table class="stdtable" style="width:100%;">
				<thead><tr><th>Date</th><th>Initiator</th><th>Kind</th><th>Info</th></tr></thead>
				<tbody>
					<? foreach($auditLogs as $logEntry): ?>
					<tr>
						<? require($this->templatedir.'audit-log-entry-cell-date.tpl'); ?>
						<? require($this->templatedir.'audit-log-entry-cell-initiator.tpl'); ?>
						<? require($this->templatedir.'audit-log-entry-cells-kind-info.tpl'); ?>
					</tr>
					<? endforeach; ?>
				</tbody>
			</table>
		</div>
		{else}
			<p><i>No activity found.</i></p>
		{/if}
	{/if}
</div>

<div class="buttons">
	{if !$release['retractionReason']}
		<a class="button large submit shine" href="javascript:submitForm(0)">Save</a>
		<a class="button large submit shine" href="javascript:submitForm(1)">Save+Back</a>
	{/if}

	{if $release['assetId']}
		{if !$release['retractionReason'] || !$release['retractedByModerator'] || canModerate(null, $user)}
			<button class="button large btndelete shine" style="margin-left:auto;padding:.25em;" data-opens-dialog="retract-mdl" onclick="return false;">{$release['retractionReason'] ? 'Update Retraction Message' : 'Retract Release'}</button>
		{else}
			<div class="bg-warning" style="width:100%;text-align: center;">Release retracted by moderator, cannot be edited.</div>
		{/if}
	{else}
		<div class="flex-spacer not-mobile"></div>
	{/if}
</div>

{if $release['assetId']}
<dialog id="retract-mdl" closedby="any" autofocus="">
	<form class="with-buttons-bottom" method="dialog" data-method="put" action="/api/v2/mods/{$mod['modId']}/releases/{$release['releaseId']}/retraction" autocomplete="off">
		{if !$release['retractionReason']}
			<h1>Retract Release</h1>
			<p>Are you sure want to retract this release?</p>
			<p>
				Retracting a release <b>prevents players from downloading it</b> and puts its release page into <b>readonly mode</b>.<br/>
				This is intended for the removal of harmful releases, the existence of a newer version with bugfixes <b>does not constitute a reason</b> for retraction.
			</p>
			<p>Should you still wish to retract this release then provide the reason for doing so here:</p>
		{else}
			<h1>Update Retraction Message</h1>
			<p>This release is already retracted. <b>players are prevented from downloading it</b> and the release page is in <b>readonly mode</b>.</p>
			<p>Provide an updated retraction message here:</p>
		{/if}
		<textarea id="retract-reason-ta" name="reason">{$release['retractionReason']}</textarea>
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<div class="buttons">
			<button class="button large btndelete shine" id="retract-subm" onclick="return false;">Confirm Retraction</button>
			<button class="button large shine" style="margin-left:auto;" formmethod="dialog">Cancel</button>
		</div>
	</form>
</dialog>
{/if}

{capture name="footerjs"}
{include file="edit-asset-files-template.tpl"}
<script nonce="{$cspNonce}" type="text/javascript">
	var modId = {$mod['modId']};

	{if $release['assetId']}\{
		const retractMdl = R.get('retract-mdl');
		R.onDOMLoaded(() => createEditor(retractMdl.getElementsByTagName('textarea')[0], tinymceSettingsCmt));
		attachDialogSendHandler(retractMdl, (form, data) => \{
			if(!data.get('reason')) \{
				R.markAsErrorElement(form.getElementsByClassName('tox-tinymce')[0]);
				return false;
			}
			return true;
		}, (jqXHR) => \{
			R.attachDefaultFailHandler(jqXHR, "Failed to retract release")
				.done(() => \{
					retractMdl.close();
					if({$release['retractionReason'] ? '0':'1'}) \{
						R.addMessage(MSG_CLASS_OK, 'Release retracted.', false);
						window.location.replace("{formatModPath($mod)}#tab-files");
					}
					else \{
						R.addMessage(MSG_CLASS_OK, 'Retraction message updated.', false);
					}
				});
		});
	}
	{/if}

	{if $doFileValidation} \{
		function onUploadFinished(file) \{
			if (file.modparse == "error") \{
				R.addMessage(MSG_CLASS_ERROR, 'Failed to parse mod information from this file: '+file.parsemsg, true);
			} else \{
				$("input[name='modidstr']").val(file.modid);
				$("input[name='modversion']").val(file.modversion);
				if(file.gameversiondep) \{
					const versionInputs = document.querySelectorAll('.version-selector input[name="cgvs[]"]');
					const labelEl = document.getElementsByClassName('count-label')[0];
					let c = 0;
					for(const box of versionInputs) {
						box.checked = R.compileSemanticVersion(box.value) >= file.gameversiondep;
						if(box.checked) c++;
					}
					labelEl.textContent = `${c} Version${c !== 1 ? 's' : ''} Selected`;
					R.addMessage(MSG_CLASS_OK, `Automatically selected ${c} compatible game version${c !== 1 ? 's' : ''}.`, false);
				}
			}
		}

		attachVersionSelectorHandlers(document.getElementsByClassName('version-selector')[0]);
	} {/if}
	
	$(document).ready(function() {
		$('form[name=commentformtemplate]').areYouSure();
	});

	window.allowdrop = {$release['assetId'] ? 'false' : 'true'};
</script>

<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/prism.min.js?v=0" data-manual=""></script>
<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/edit-asset.js?version=46" async></script>
<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/jquery.fancybox.min.js" async></script>
{/capture}


{include file="footer"}
