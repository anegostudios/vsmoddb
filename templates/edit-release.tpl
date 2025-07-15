{include file="header"}

<div class="edit-asset edit-release">

	<h2>
		<span class="assettype">
			<a href="/list/mod">Mods</a>
		</span> / 
		<span>
			<a href="{formatModPath($mod)}#tab-files">{$mod["name"]}</a>
		</span> / 
		<span>{$release['assetId'] ? 'Edit Release' : 'Add new Release'}</span>
	</h2>

	<form method="post" name="deleteform">
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<input type="hidden" name="delete" value="1">
	</form>

	<form method="post" name="form1" enctype="multipart/form-data" autocomplete="off" class="flex-list">
		<input type="hidden" name="at" value="{$user['actionToken']}">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="assetid" value="{$release['assetId']}">
		<input type="hidden" name="modid" value="{$mod['modid']}">
		<input type="hidden" name="numsaved" value="{$release['numsaved']}">
		<input type="hidden" name="saveandback" value="0">
		
		{if $mod['type'] === 'mod'}
			<div class="editbox">
				<label>Compatible with game versions</label>
				<select name="cgvs[]" class="required" multiple>
					{foreach from=$allGameVersions item=version}
						<option value="{$version['name']}" {if isset($release['compatibleGameVersions'][$version['version']])}selected="selected"{/if}>{$version['name']}</option>
					{/foreach}
				</select>
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
							<td>{str_replace("\r\n", "<br>", $entry['text'])}</td>
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

{include file="edit-asset-files-template.tpl"}

{capture name="buttons"}
	<a class="button large submit shine" href="javascript:submitForm(0)">Save</a>
	<a class="button large submit shine" href="javascript:submitForm(1)">Save+Back</a>
	
	{if $release['assetId']}
		<div style="height: 1em"></div>
		<a class="button large btndelete shine" href="javascript:submitDelete()">Delete Release</a>
	{/if}
{/capture}


{capture name="footerjs"}
<script type="text/javascript">	
	modtype='{$mod["type"]}';
	assetid = {$release['assetId']};
	assettypeid = 2;
	
	if (modtype === 'mod') {
		function onUploadFinished(file) {
			if (file.modparse == "error") {
				addMessage(MSG_CLASS_ERROR, 'Failed to parse mod information from this file: '+file.parsemsg, true);
			} else {
				$("input[name='modidstr']").val(file.modid);
				$("input[name='modversion']").val(file.modversion);
			}
		}
	}
	
	
	$(document).ready(function() {
		$('form[name=commentformtemplate]').areYouSure();
	});
</script>
<script type="text/javascript" src="/web/js/edit-asset.js?version=37" async></script>

{/capture}


{include file="footer"}
