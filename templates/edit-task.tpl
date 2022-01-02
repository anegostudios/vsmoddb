{include file="header"}

<div class="edit-asset {$entrycode}">

	{if $asset['assetid']}
		<h2>
			<span class="assettype">
				<a href="/list/{$entrycode}">{$entryplural}</a>
			</span> / 
			<span class="title">
				{$asset["name"]}
			</span>
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
		<input type="hidden" name="delete" value="1">
	</form>

	<form method="post" name="form1">
		<input type="hidden" name="at" value="{$user['actiontoken']}">
		<input type="hidden" name="save" value="1">
		<input type="hidden" name="assetid" value="{$asset['assetid']}">
		<input type="hidden" name="saveandback" value="0">
		
		<div class="editbox" style="min-height:50px;">
			<label>Status</label>
			<select name="statusid" style="width:120px;">
				{foreach from=$stati item=status}
					<option value="{$status['statusid']}" {if $asset['statusid']==$status['statusid']}selected="selected"{/if}>{$status['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox" style="min-height:50px;">
			<label>Assigned to user</label>
			<select name="userid" style="width:120px;">
				{foreach from=$users item=oneuser}
					<option value="{$oneuser['userid']}" {if $asset['userid']==$oneuser['userid'] || (empty($asset["assetid"]) && $oneuser["userid"]==$user["userid"]) }selected="selected"{/if}>{$oneuser['name']}</option>
				{/foreach}
			</select>
		</div>

		<div class="editbox">
			<label>Tags</label>
			<select name="tagids[]" style="width:300px;" multiple>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagid']}" {if !empty($asset['tags'][$tag['tagid']])}selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</div>
		
		{if $asset["assetid"]}
			<div class="editbox" style="min-height: 44px;">
				Created by: {$asset['createdusername']}<br>
				{if $asset['editedusername'] && $asset['createdusername'] != $asset['editedusername']}Last Edited by: {$asset['editedusername']}<br>{/if}
				Last modified: {fancyDate($asset['lastmodified'])}
			</div>
		{/if}
		
		<div class="editbox linebreak">
			<label>Name</label>
			<input type="text" name="name" style="width: 996px;" class="required" value="{$asset['name']}"/>
		</div>
		

		<div class="editbox linebreak" style="width: 1000px; max-width:1000px">
			<label>Text</label>
			<textarea name="text" class="editor" data-editorname="text" style="width: 994px; height: auto;">{$asset['text']}</textarea>
		</div>
		
		{if file_exists("templates/edit-asset-$entrycode.tpl")}
			{include file="edit-asset-`$entrycode`.tpl"}
		{/if}

		<div style="clear:both;"></div>
		<h3>Files<span style="float:right; font-size:70%;">(drag&drop to upload)</span></h3>

		<div class="files">
			{foreach from=$files item=file}
				<div class="file">
					{if $file['thumbnailfilename']}
						<a data-fancybox="gallery" href="/files/asset/{$asset['assetid']}/{$file['filename']}" class="editbox">
							<img src="/files/asset/{$asset['assetid']}/{$file['thumbnailfilename']}"/>
							<div class="filename">{$file["filename"]}</div><br>
							<div class="uploaddate">{$file["created"]}</div>
						</a>
					{else}
						<a href="/files/asset/{$asset['assetid']}/{$file['filename']}" class="editbox">
							<div class="fi fi-{$file['ending']}">
								<div class="fi-content">{$file['ending']}</div>
							</div>
							<div class="filename">{$file["filename"]}</div><br>
							<div class="uploaddate">{$file["created"]}</div>
						</a>
					{/if}
					<a href="" class="delete" data-fileid="{$file['fileid']}"></a>
					<a href="/download?fileid={$file['fileid']}" class="download">&#11123;</a>
				</div>
			{/foreach}
		</div>

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

			<h3>Responses {if empty($ownresponse)}<a href="#addresponse" class="add" title="Add your response"></a>{/if}</h3>

			<form name="responseform" method="post">
				<input type="hidden" name="saveresponse" value="1">
				<div class="responses">
					{if empty($ownresponse)}				
						<div class="ownresponse template editbox" style="clear:both; width: 1000px; max-width: 1000px;">
							<p style="margin-top:0px; margin-bottom:5px;">
								<select class="required" name="responsetype" style="width:250px; margin-bottom:3px;">
									<option value="">-</option>
									{foreach from=$responsetypes item=responsetype}
										<option>{$responsetype}</option>
									{/foreach}
								</select>
							</p>
							<textarea name="responsetext" class="editor" data-editorname="response"  style="display:block; width: 990px; height: 100px;"></textarea>
							<p style="margin-top:5px; margin-bottom:0px; float:right; clear:both;"><button type="submit" name="save">Save Response</button>
						</div>
					{/if}
				
					{foreach from=$responses item=response}
						{if $ownresponse && $response['responseid'] == $ownresponse['responseid']}
							<div class="ownresponse editbox" style="clear:both; width: 1000px; max-width: 1000px;">
								<input type="hidden" name="responseid" value="{$response['responseid']}">
								<p style="margin-top:0px; margin-bottom:5px;">
									<select class="required" name="responsetype" style="width:250px;">
										<option value="">-</option>
										{foreach from=$responsetypes item=responsetype}
											{if $responsetype == $response["type"]}
												<option selected="selected">{$responsetype}</option>
											{else}
												<option>{$responsetype}</option>
											{/if}
										{/foreach}
									</select>
								</p>
								<textarea name="responsetext" class="editor" data-editorname="response"  style="display:block; width: 990px; height: 100px;">{$response['text']}</textarea>
								<p style="margin-top:5px; margin-bottom:0px; float:right; clear:both;"><button type="submit" name="save">Save Response</button>
							</div>
						{else}
							<div class="response editbox">
								<div class="title {$response['type']}">{$response['type']}</div>
								<div class="body">{autoFormat($response['text'])}</div>
							</div>
						{/if}
					{/foreach}
				</div>
			</form>
			
			<div style="clear:both;"></div>

			<h3>Comments <a href="#addcomment" class="add" title="Add a comment"></a></h3>

			<div class="comments">
				<div class="comment template editbox" style="clear:both; width: 1006px; max-width: 1006px;">
					<div class="title">{$user['name']}, 0 seconds ago</div>
					<div class="body">
						<form name="commentformtemplate">
							<textarea name="commenttext" class="editor" data-editorname="comment" style="width: 994px; height: 135px;"></textarea>
						</form>
					</div>
					<p style="margin-top:5px; margin-bottom:4px; float:right; clear:both; margin-right: 4px;"><button type="submit" name="save">Add Comment</button>
				</div>
			
				{foreach from=$comments item=comment}
					<div class="editbox comment" style="clear:both; width: 1007px; max-width: 1007px;">
						<div class="title">
							{$comment['username']}, {fancyDate($comment['created'])} {if $comment['modifieddate']}(modified at {$comment['modifieddate']}){/if}
							{if $comment["userid"] == $user["userid"]}<a href="#editcomment" class="edit" data-commentid="{$comment['commentid']}" style="float:right;"></a>{/if}
						</div>
						<div class="body">{autoFormat($comment['text'])}</div>
					</div>
				{/foreach}
			</div>
		{/if}
</div>

<div class="file template">
	<input type="hidden" name="fileids[]" value="" />
	<a href="#" class="editbox">
		<div class="fi">
			<div class="fi-content"></div>
		</div>
		<img src="" style="display:none;"/>
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


{capture name="buttons"}
	
	{include
		file="button"
		href="javascript:submitForm(0)"
		buttontext="Save"
	}

	{include
		file="button"
		href="javascript:submitForm(1)"
		buttontext="Save+Back"
	}
	
	<p style="clear:both;"><br></p>
	{include
		file="button"
		href="/reclassify?assetid=`$asset['assetid']`"
		buttontext="Reclassify"
	}
	
	{if $asset['assetid']}
		<p style="clear:both;"><br></p>
		{include
			file="button"
			href="javascript:submitDelete()"
			buttontext="Delete `$entrysingular`"
		}
	{/if}
	
{/capture}

{capture name="footerjs"}
	<script type="text/javascript">
		$(document).ready(function() {
			$('form[name=commentformtemplate]').areYouSure();
		});
		
	</script>	
	<script type="text/javascript" src="/web/js/edit-asset.js?version=21" async></script>
{/capture}

{include file="footer"}
