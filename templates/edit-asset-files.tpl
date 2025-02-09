		<div class="files">
			{foreach from=$files item=file}
				<div class="file">
					{if $file['hasthumbnail']}
						<a data-fancybox="gallery" href="{$file['url']}" class="editbox">
							<img src="{formatUrl($file, '_55_60')}"/>
							<div class="filename">{$file["filename"]}</div><br>
							<div class="uploaddate">{$file["created"]}</div>
						</a>
					{else}
						<a href="{$file['url']}" class="editbox">
							<div class="fi fi-{$file['ext']}">
								<div class="fi-content">{$file['ext']}</div>
							</div>
							<div class="filename">{$file["filename"]}</div><br>
							<div class="uploaddate">{$file["created"]}</div>
						</a>
					{/if}
					<a href="" class="delete" data-fileid="{$file['fileid']}"></a>
					<a href="{formatDownloadUrl($file)}" class="download">&#11123;</a>
				</div>
			{/foreach}
			
			{if !empty($formupload)}
			<div class="file">
				<div class="editbox">
					<p>Upload new file (or drag and drop, max file size: {$fileuploadmaxsize} MB)</p>
					<input type="file" name="newfile">
				</div>
			</div>
			{/if}
			
		</div>