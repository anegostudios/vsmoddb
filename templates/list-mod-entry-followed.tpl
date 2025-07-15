<div class="mod {$mod['statusCode']}{if $mod['legacylogo']} legacy{/if}">
	<a href="{$mod['modpath']}">
		<img src="{empty($mod['logocdnpath']) ? '/web/img/mod-default.png' : formatCdnUrlFromCdnPath($mod['logocdnpath'])}" loading="lazy">
	</a>
	
	<div class="moddesc">
		<p class="stats">
			<a href="{$mod['modpath']}#tab-files"><img src="/web/img/download.png"> {intval($mod['downloads'])}</a><br>
			<a href="{$mod['modpath']}#comments"><img src="/web/img/comments.png"> {intval($mod['comments'])}</a>
		</p>
		<a href="{$mod['modpath']}">
			{if strlen($mod['name']) < 49}
				<strong>{$mod['name']}</strong>
			{else}
				<strong>{substr($mod['name'], 0, 45)}...</strong>
			{/if}
			<br>by {$mod['from']}
		</a>
		<br>
		Latest version: <a href="{$mod['modpath']}#tab-files">{formatSemanticVersion($mod['releaseversion'])} from
		<br>{fancyDate($mod['releasedate'])}</a>
	</div>

	{if time()-strtotime($mod['created']) < 10*24*3600}<span class="ribbon-tr" style="background: #ffe300">New!</span>{/if}
	{if $mod['statusCode'] === 'draft'}<span class="ribbon-tr d2" style="background: #ccc">Draft</span>{/if}
</div>
