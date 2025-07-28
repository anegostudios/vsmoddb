<div class="mod {$mod['statusCode']}{if $mod['hasLegacyLogo']} legacy{/if}">
	<a href="{$mod['dbPath']}">
		<img src="{empty($mod['logoCdnPath']) ? '/web/img/mod-default.png' : formatCdnUrlFromCdnPath($mod['logoCdnPath'])}" loading="lazy">
	</a>
	
	<div class="moddesc">
		<p class="stats">
			<a href="{$mod['dbPath']}#tab-files"><img src="/web/img/download.png"> {intval($mod['downloads'])}</a><br>
			<a href="{$mod['dbPath']}#comments"><img src="/web/img/comments.png"> {intval($mod['comments'])}</a>
		</p>
		<a href="{$mod['dbPath']}">
			{if strlen($mod['name']) < 49}
				<strong>{$mod['name']}</strong>
			{else}
				<strong>{substr($mod['name'], 0, 45)}...</strong>
			{/if}
			<br>by {$mod['from']}
		</a>
		<br>
		Latest version: <a href="{$mod['dbPath']}#tab-files">{formatSemanticVersion($mod['releaseVersion'])} from
		<br>{fancyDate($mod['releaseDate'])}</a>
	</div>

	{if time()-strtotime($mod['created']) < 10*24*3600}<span class="ribbon-tr" style="background: #ffe300">New!</span>{/if}
	{if $mod['statusCode'] === 'draft'}<span class="ribbon-tr d2" style="background: #ccc">Draft</span>{/if}
</div>
