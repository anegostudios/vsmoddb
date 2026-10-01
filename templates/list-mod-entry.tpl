<div class="mod {stringifyModStatusInternal($mod['status'])}{if $mod['hasLegacyLogo']} legacy{/if}">
	<a href="{$mod['dbPath']}">
		<img src="{empty($mod['logoCdnPath']) ? '/web/img/mod-default.png' : formatCdnUrlFromCdnPath($mod['logoCdnPath'])}" alt="Mod Thumbnail" loading="lazy">
	</a>

	{if !empty($mod['following'])}<i title="You are following this mod" class="followed-star ico star"></i>{/if}

	<div class="moddesc">
		<p class="stats">
			<a href="{$mod['dbPath']}#tab-files"><img src="/web/img/download.png"> {intval($mod['downloads'])}</a><br>
			<a href="{$mod['dbPath']}#comments"><img src="/web/img/comments.png"> {intval($mod['comments'])}</a>
		</p>
		<a href="{$mod['dbPath']}">
			{if strlen($mod['name']) < 49}
				<h4>{$mod['name']}</h4>
			{else}
				<h4>{substr($mod['name'], 0, 45)}...</h4>
			{/if}
			<p>{$mod['summary'] ?? ''}</p>
		</a>
	</div>

	{if time()-strtotime($mod['created']) < 10*24*3600}<span class="ribbon-tr" style="background: #ffe300">New!</span>{/if}
	{if $mod['status'] == MOD_STATUS_DRAFT}<span class="ribbon-tr d2" style="background: #ccc">Draft</span>{/if}
</div>
