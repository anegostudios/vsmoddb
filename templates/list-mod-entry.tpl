<div class="mod {$mod['statuscode']}">
	{if $mod['statuscode']=='draft'}<span class="draftnotice">Draft</span>{/if}
	<a href="{if $mod['urlalias']}/{$mod['urlalias']}{else}/show/mod/{$mod['assetid']}{/if}">
		{if (empty($mod['logofilename']))}
			<img src="/web/img/mod-default.png" loading="lazy">
		{else}
			<img src="/files/asset/{$mod['assetid']}/{$mod['logofilename']}" loading="lazy">
		{/if}
		{if !empty($mod['following'])}<i title="You are following this mod" class="followed fas fa-star"></i>{/if}
	</a>
	
	<div class="moddesc">
		<p class="stats">
			<a href="{if $mod['urlalias']}/{$mod['urlalias']}{else}/show/mod/{$mod['assetid']}{/if}#tab-files"><img src="/web/img/download.png"> {intval($mod['downloads'])}</a><br>
			<a href="{if $mod['urlalias']}/{$mod['urlalias']}{else}/show/mod/{$mod['assetid']}{/if}#comments"><img src="/web/img/comments.png"> {intval($mod['comments'])}</a>
		</p>
		<a href="/show/mod/{$mod['assetid']}">
			{if strlen($mod['name']) < 49}
				<strong>{$mod['name']}</strong>
			{else}
				<strong>{substr($mod['name'], 0, 45)}...</strong>
			{/if}
			<br>by {$mod['from']}
		</a>
		
		<div class="tags">
			{foreach from=$mod['tags'] item=tag}
				<a href="/list/mod/?tagids[]={$tag['tagid']}" class="tag" style="background-color:{$tag['color']}">#{$tag['name']}</a>
			{/foreach}
		</div>
	</div>
</div>
