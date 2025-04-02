{include file="header"}

<div class="edit-asset edit-{$entrycode}">

	<h2>
		<span class="assettype">
			<a href="/list/{$entrycode}">{$entryplural}</a>
		</span> / 
		<span>{$asset['assetid'] ? $asset['name'] : "Add new ".$entrysingular}</span>
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
		
		<div class="editbox">
			<label>Status</label>
			<select name="statusid">
				{foreach from=$stati item=status}
					<option value="{$status['statusid']}" {if $asset['statusid']==$status['statusid']}selected="selected"{/if}>{$status['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox">
			<label>Tags</label>
			<select name="tagids[]" multiple>
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
		<h3>Files<span style="float:right; font-size:70%;">(drag&drop to upload{if false /*:ZipDownloadDisabled*/ && (count($files) > 0)}, <a href="/download?assetid={$asset['assetid']}">download all as zip</a>{/if})</span></h3>
		{include file="edit-asset-files.tpl"}

		<div style="clear:both;"></div>
		<h3>Connections <a href="#addconnection" class="add" title="Add a connection"></a></h3>

		<div class="connections">
			{foreach from=$connections item=connection}
				<div class="connection editbox" style="clear:both;">
					<input type="hidden" name="connectionid[]" value="{$connection['connectionid']}">
					<select name="connectiontypeid[]" class="required" style="width:150px;">
						{foreach from=$connectiontypes item=connectiontype}
							<option value="{$connectiontype['connectiontypeid']}" {if $connection['connectiontypeid'] == $connectiontype['connectiontypeid']}selected="selected"{/if}>{$connectiontype['name']}</option>
						{/foreach}
					</select>
					
					<select name="assettypeid[]" class="required" style="width: 150px">
						<option value="">-</option>
						{foreach from=$assettypes item=assettype}
							<option value="{$assettype['assettypeid']}" {if $connection['asset']['assettypeid'] == $assettype['assettypeid']}selected="selected"{/if}>{$assettype['name']}</option>
						{/foreach}
					</select>

					<select name="toassetid[]" class="required" style="width: 300px">
						<option value="{$connection['asset']['assetid']}">{$connection['asset']['name']}</option>
					</select>
					
					<a href="#" class="delete"></a>
				</div>
			{/foreach}
		</div>
		</form>

		{if $asset['assetid']} 
			<div style="clear:both;"><br></div>

			
			<div style="clear:both;"></div>
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
	
	<div style="height: 1em"></div>
	<a class="button large shine" href="/reclassify?assetid={$asset['assetid']}">Reclassify</a>
	{if $asset['assetid']}
		<a class="button large btndelete shine" href="javascript:submitDelete()">Delete {$entrysingular}</a>
	{/if}
{/capture}

{capture name="footerjs"}
	<script type="text/javascript">
		$(document).ready(function() {
			$('form[name=commentformtemplate]').areYouSure();
		});
		
	</script>	
	<script type="text/javascript" src="/web/js/edit-asset.js?version=35" async></script>
{/capture}

{include file="footer"}
