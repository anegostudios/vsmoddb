<div class="mod {$mod['statuscode']}">
	{if $mod['statuscode']=='draft'}<span class="draftnotice">Draft</span>{/if}
	<a href="{if $mod['urlalias']}/{$mod['urlalias']}{else}/show/mod/{$mod['assetid']}{/if}">
		{if (empty($mod['logocdnpath']))}
			<img src="/web/img/mod-default.png">
		{else}
			<img src={formatCdnUrlFromCdnPath($mod['logocdnpath'])}">
		{/if}
	</a>
	
	<div class="moddesc" style="line-height:125%">
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
		<br>
		Latest version: <a href="/show/mod/{$mod['assetid']}#tab-files">v{$mod['releaseversion']} from
		<br>{fancyDate($mod['releasedate'])}</a>
		{if time()-strtotime($mod['created']) < 10*24*3600}<div class="ribbon-top">New!</div>{/if}
	</div>
</div>
