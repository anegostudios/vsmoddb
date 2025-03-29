{include file="header"}

<div class="edit-asset edit-{$entrycode}">

	<h2>
		<span class="assettype">
			<a href="/list/mod">Mods</a>
		</span> / 
		<span>
			<a href="{formatModPath($mod)}#tab-files">{$mod["name"]}</a>
		</span> / 
		<span>{$asset['assetid'] ? 'Edit Release' : 'Add new Release'}</span>
	</h2>

	<form method="post" name="deleteform">
		<input type="hidden" name="at" value="{$user['actiontoken']}">
		<input type="hidden" name="delete" value="1">
	</form>

	<form method="post" name="form1" enctype="multipart/form-data" autocomplete="off" class="flex-list">
		<input type="hidden" name="at" value="{$user['actiontoken']}">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="assetid" value="{$asset['assetid']}">
		<input type="hidden" name="modid" value="{$mod['modid']}">
		<input type="hidden" name="statusid" value="0">
		<input type="hidden" name="numsaved" value="{$asset['numsaved']}">
		<input type="hidden" name="saveandback" value="0">
		
		{if $modtype == "mod"}
			<div class="editbox">
				<label>Compatible with game versions</label>
				<select name="tagids[]" class="required" multiple>
					{foreach from=$tags item=tag}
						<option value="{$tag['tagid']}" {if !empty($asset['tags'][$tag['tagid']])}selected="selected"{/if}>{$tag['name']}</option>
					{/foreach}
				</select>
			</div>
			
			{if $asset["assetid"]}
				<div class="editbox">
					Created by: {$asset['createdusername']}<br>
					{if $asset['editedusername'] && $asset['createdusername'] != $asset['editedusername']}Last Edited by: {$asset['editedusername']}<br>{/if}
					Last modified: {fancyDate($asset['lastmodified'])}
				</div>
			{/if}
			
			<div class="editbox">
				<label><abbr title="This value is autodetected, please upload a file.">Mod Id</abbr></label>
				<input type="text" name="modidstr" class="required" value="{$asset['modidstr']}" {if empty($allowinfoedit)}disabled=""{/if}>
			</div>
			<div class="editbox">
				<label><abbr title="This value is autodetected, please upload a file.">Mod Version Number</abbr></label>
				<label for="inp-modversion" class="prefixed-input{if empty($allowinfoedit)} disabled{/if}" data-prefix="v"><input id="inp-modversion" type="text" name="modversion" value="{$asset['modversion']}" class="required" style="width: 10ch"{if empty($allowinfoedit)} disabled=""{/if} /></label>
			</div>
		{else}
			<div class="editbox">
				<label>Version Number</label>
				<label for="inp-modversion" class="prefixed-input" data-prefix="v"><input id="inp-modversion" type="text" name="modversion" value="{$asset['modversion']}" class="required" style="width: 10ch" /></label>
			</div>
		{/if}
		
		
		<div class="editbox flex-fill">
			<label>Text</label>
			<textarea name="text" class="editor" data-editorname="text" style="width: 100%; height: auto;">{$asset['text']}</textarea>
		</div>
		
		{if file_exists("templates/edit-asset-$entrycode.tpl")}
			{include file="edit-asset-`$entrycode`.tpl"}
		{/if}

		<h3 class="flex-fill">Files{if false /*:ZipDownloadDisabled*/ && (count($files) > 0)}<span style="float:right; font-size:70%;">(<a href="/download?assetid={$asset['assetid']}">download all as zip</a>)</span>{/if}</h3>

		{include file="edit-asset-files.tpl" formupload="1"}
		
		</form>

		{if $asset['assetid']} 
			<div style="clear:both;"><br></div>

			{include file="comments"}

			<p><br></p>
			<h3>Change log</h3>
			<table class="stdtable activitystream" style="min-width:900px;">
				<thead>
					<tr>
						<th>Changes</th>
						<th style="width:120px;">User</th>
						<th style="width:120px;">Date</th>
					</tr>
				</thead>
				<tbody>
				{if count($changelogs)}
					{foreach from=$changelogs item=entry}
						<tr>
							<td>{str_replace("\r\n", "<br>", $entry['text'])}</td>
							<td>{$entry['username']}</td>
							<td>{fancyDate($entry['lastmodified'])}</td>
						</tr>
					{/foreach}
				{else}
					<td colspan="4"><i>No activity found.</i></td>
				{/if}
				</tbody>
			</table>




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

{capture name="buttons"}
	<a class="button large submit shine" href="javascript:submitForm(0)">Save</a>
	<a class="button large submit shine" href="javascript:submitForm(1)">Save+Back</a>
	
	{if $asset['assetid']}
		<div style="height: 1em"></div>
		<a class="button large btndelete shine" href="javascript:submitDelete()">Delete Release</a>
	{/if}
{/capture}


{capture name="footerjs"}
<script type="text/javascript">	
	modtype='{$modtype}';
	
	if (modtype=='mod') {
		function onUploadFinished(file) {
			if (file.modparse == "error") {
				$("input[name='modidstr']").removeProp("disabled");
				$("input[name='modversion']").removeProp("disabled");
				$(".prefixed-input[for='inp-modversion']").removeClass("disabled");
				$("div.errormessagepopup .text").html("Unable to determine mod id and version from this file, please fill in id and version manually");
				showMessage($(".errormessagepopup"));
			} else {
				$("input[name='modidstr']").val(file.modid);
				$("input[name='modversion']").val(file.modversion);
			}
		}

		function onFileDelete($fileEl, fileid) {
			$("input[name='modidstr']").prop("disabled", 'true').val('');
			$("input[name='modversion']").prop("disabled", 'true').val('');
			$(".prefixed-input[for='inp-modversion']").addClass("disabled");
		}
	}
	
	
	$(document).ready(function() {
		$('form[name=commentformtemplate]').areYouSure();
	});
</script>
<script type="text/javascript" src="/web/js/edit-asset.js?version=34" async></script>

{/capture}


{include file="footer"}
