		<div class="files flex-list">
			{foreach from=$files item=file}
				<div class="file">
					{if $file['hasthumbnail']}
						<a data-fancybox="gallery" href="{$file['url']}">
							<img src="{formatCdnUrl($file, '_55_60')}"/>
							<div>
								<h5 class="filename">{$file["filename"]}</h5>
								<small class="uploaddate">{$file["created"]}</small>
								<small class="imagesize">{$file["imagesize"]} px</small>
							</div>
						</a>
					{else}
						<a href="{$file['url']}">
							<div class="fi fi-{$file['ext']}">
								<div class="fi-content">{$file['ext']}</div>
							</div>
							<div>
								<h5 class="filename">{$file["filename"]}</h5>
								<small class="uploaddate">{$file["created"]}</small>
							</div>
						</a>
					{/if}
					<a href="" class="delete" data-fileid="{$file['fileid']}"></a>
					<a href="{formatDownloadTrackingUrl($file)}" class="download">&#11123;</a>
				</div>
			{/foreach}
			
			{if !empty($formupload)}
				<div class="editbox wide">
					<label>Upload new file (or drag and drop, max file size: {$fileuploadmaxsize} MB)</label>
					<input type="file" name="newfile" style="height: unset; padding: .25em;">
				</div>
			{/if}

		</div>