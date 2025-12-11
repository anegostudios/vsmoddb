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

	<form method="post" name="form1" enctype="multipart/form-data" autocomplete="off" class="flex-list">
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

		<h3 class="flex-fill">Files {if $release['assetId']}<small>(changes apply immediately!)</small>{/if}{if false /*:ZipDownloadDisabled*/ && (count($files) > 0)}<span style="float:right; font-size:70%;">(<a href="/download?assetid={$release['assetId']}">download all as zip</a>)</span>{/if}</h3>

		{include file="edit-asset-files.tpl" formupload="1"}	
	</form>

	{if $release['assetId']} 
		<p><br></p>
		<h3 style="margin-bottom:.5em;">Change log</h3>
		{if $assetChangelog}
			<table class="stdtable" style="width:100%;">
			<thead>
				<tr>
					<th>Changes</th>
					<th style="width:15ch;">User</th>
					<th style="width:15ch;">Date</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$assetChangelog item=entry}
					<tr>
						<td>{str_replace("\n\r", "<br/>", $entry['text'])}</td>
						<td>{$entry['username']}</td>
						<td>{fancyDate($entry['lastModified'])}</td>
					</tr>
				{/foreach}
				</tbody>
			{else}
				<p><i>No activity found.</i></p>
			{/if}
		</table>
	{/if}
</div>

<div class="buttons">
	{if !$release['retractionReason']}
		<a class="button large submit shine" href="javascript:submitForm(0)">Save</a>
		<a class="button large submit shine" href="javascript:submitForm(1)">Save+Back</a>
	{/if}

	{if $release['assetId']}
		{if !$release['retractionReason'] || canModerate(null, $user)}
			<button class="button large btndelete shine" style="margin-left:auto;" data-opens-dialog="retract-mdl" onclick="return false;">Retract Release</button>
		{else}
			<div class="bg-warning" style="width:100%;text-align: center;">Release retracted, cannot be edited.</div>
		{/if}
	{else}
		<div class="flex-spacer not-mobile"></div>
	{/if}
</div>

{if $release['assetId']}
<dialog id="retract-mdl" autofocus="">
	<form class="with-buttons-bottom" method="dialog" data-method="put" action="/api/v2/mods/{$mod['modId']}/releases/{$release['releaseId']}/retraction" autocomplete="off">
		<h1>Retract Release</h1>
		<p>Are you sure want to retract this release?</p>
		<p>
			Retracting a release <b>prevents users from downloading</b> it and puts its release page into <b>readonly mode</b>.<br/>
			This is intended for the removal of harmful releases, the existence of a newer version with bugfixes <b>does not constitute a reason</b> for retraction.
		</p>
		<p>Should you still wish to retract this release then provide the reason for doing so here:</p>
		<textarea id="retract-reason-ta" name="reason"></textarea>
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
					R.addMessage(MSG_CLASS_OK, 'Release retracted.', false);
					window.location.replace("{formatModPath($mod)}#tab-files");
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
					labelEl.textContent = `${c} Versions${c !== 1 ? 's' : ''} Selected`;
					R.addMessage(MSG_CLASS_OK, `Automatically selected ${c} compatible game version${c !== 1 ? 's' : ''}.`, false);
				}
			}
		}

		attachVersionSelectorHandlers(document.getElementsByClassName('version-selector')[0]);
	} {/if}
	
	$(document).ready(function() {
		$('form[name=commentformtemplate]').areYouSure();
	});
</script>
<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/edit-asset.js?version=42" async></script>
<script nonce="{$cspNonce}" type="text/javascript" src="/web/js/jquery.fancybox.min.js" async></script>
{/capture}


{include file="footer"}
