		<div class="files">
			{foreach from=$files item=file}
				{assign var="path" value="/files/asset/".$asset['assetid']."/".$file['filename']}
				{if !$file['assetid']}
					{assign var="path" value="/tmp/".$user['userid']."/".$file['filename']}
				{/if}
				<div class="file">
					{if $file['thumbnailfilename']}
						<a data-fancybox="gallery" href="{$path}" class="editbox">
							<img src="{if !$file['assetid']}/tmp/{$user['userid']}/{$file['thumbnailfilename']}{else}/files/asset/{$asset['assetid']}/{$file['thumbnailfilename']}{/if}"/>
							<div class="filename">{$file["filename"]}</div><br>
							<div class="uploaddate">{$file["created"]}</div>
						</a>
					{else}
						<a href="{$path}" class="editbox">
							<div class="fi fi-{$file['ending']}">
								<div class="fi-content">{$file['ending']}</div>
							</div>
							<div class="filename">{$file["filename"]}</div><br>
							<div class="uploaddate">{$file["created"]}</div>
						</a>
					{/if}
					<a href="" class="delete" data-fileid="{$file['fileid']}"></a>
					<a href="/download?fileid={$file['fileid']}&at={$user['actiontoken']}" class="download">&#11123;</a>
				</div>
			{/foreach}
		</div>