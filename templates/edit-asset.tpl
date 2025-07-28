{include file="header"}

<div class="edit-asset edit-{$entrycode}">

	<h2>
		<span>
			<a href="/list/{$entrycode}">{$entryplural}</a>
		</span> / 
		<span>{$asset['assetId'] ? $asset['name'] : "Add new ".$entrysingular}</span>
	</h2>	

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
		
		<div class="editbox">
			<label>Status</label>
			<select name="statusid">
				{foreach from=$stati item=status}
					<option value="{$status['statusId']}" {if $asset['statusId']==$status['statusId']}selected="selected"{/if}>{$status['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox">
			<label>Tags</label>
			<select name="tagids[]" multiple>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagId']}" {if !empty($asset['tags'][$tag['tagId']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>
		
		{if $asset["assetId"]}
			<div class="editbox">
				Created by: {$asset['createdUsername']}<br>
				{if $asset['editedUsername'] && $asset['createdUsername'] != $asset['editedUsername']}Last Edited by: {$asset['editedUsername']}<br>{/if}
				Last modified: {fancyDate($asset['lastModified'])}
			</div>
		{/if}
		
		<div class="editbox flex-fill">
			<label>Name</label>
			<input type="text" name="name" class="required" value="{$asset['name']}"/>
		</div>
		

		<div class="editbox flex-fill">
			<label>Text</label>
			<textarea name="text" class="editor" data-editorname="text">{$asset['text']}</textarea>
		</div>
		
		{if file_exists("templates/edit-asset-$entrycode.tpl")}
			{include file="edit-asset-`$entrycode`.tpl"}
		{/if}

		<div style="clear:both;"></div>
		<h3>Files<span style="float:right; font-size:70%;">(drag&drop to upload{if false /*:ZipDownloadDisabled*/ && (count($files) > 0)}, <a href="/download?assetid={$asset['assetId']}">download all as zip</a>{/if})</span></h3>
		{include file="edit-asset-files.tpl"}
		</form>

		{if $asset['assetId']} 
			<div style="clear:both;"><br></div>

			
			<div style="clear:both;"></div>
			{include file="comments"}



			<p><br></p>
			<h3>Change log</h3>
			<table class="stdtable" style="min-width:900px;">
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
							<td>{fancyDate($entry['lastModified'])}</td>
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

{capture name="buttons"}
	<a class="button large submit shine" href="javascript:submitForm(0)">Save</a>
	<a class="button large submit shine" href="javascript:submitForm(1)">Save+Back</a>
	
	{if $asset['assetId']}
		<a class="button large btndelete shine" href="javascript:submitDelete()">Delete {$entrysingular}</a>
	{/if}
{/capture}

{capture name="footerjs"}
	<script type="text/javascript">
		$(document).ready(function() {
			$('form[name=commentformtemplate]').areYouSure();
		});
		
	</script>	
	<script type="text/javascript" src="/web/js/edit-asset.js?version=37" async></script>
{/capture}

{include file="footer"}
