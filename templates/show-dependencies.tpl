{include file="header"}

<div class="edit-asset">
	<h2>
		<span><a href="/list/mod">Mods</a></span> / <span><a href="{htmlspecialchars(formatModPath($rootRelease))}">{htmlspecialchars($rootRelease['modName'])}</a></span> / <span>{$context->treeRoot->identifier}@{formatSemanticVersion($context->treeRoot->minVersion)}</span> / <span>Dependencies</span>
	</h2>

	{if !$context->treeRoot->resolution->children}
		<p style="text-align: center;">No dependencies</p>
	{else}
		<ul class="tabs no-mark">
			<li><label for="tab-direct" onclick="location.hash='tab-direct'">Direct Dependencies</label></li>
			<li><label for="tab-tree" onclick="location.hash='tab-tree'">Detailed Tree</label></li>
			<li><label for="tab-list" onclick="location.hash='tab-list'">Detailed List</label></li>
		</ul>

		<div class="tab-container">
			<input class="tab-trigger" type="radio" name="tab" autocomplete="off" id="tab-direct">
			<div class="tab-content mods">
				{foreach from=$context->treeRoot->resolution->children item=child}{if $child->resolution->mod}{include file="list-mod-entry" mod=$child->resolution->mod}{/if}{/foreach}
				{if count($context->treeRoot->resolution->children) < 5 /* @hack some spacing so the mods dont blow out */}<span></span><span></span><span></span><span></span><span></span>{/if}
			</div>

			<input class="tab-trigger" type="radio" name="tab" autocomplete="off" id="tab-tree">
			<div class="tab-content">
				<h4 style="margin: .5em 0;">{$context->treeRoot->identifier}@{formatSemanticVersion($context->treeRoot->minVersion)}</h4>
				<div class="tree" style="margin-left: .25em;">
				{include file="dep-layer" children=$context->treeRoot->resolution->children}
				</div>
				<aside style="margin-top: 1em;" class="text-weak">The algorithm used for resolving dependencies is rather naive (greedy). The resulting tree might therefore not be optimal.</aside>
			</div>

			<input class="tab-trigger" type="radio" name="tab" autocomplete="off" id="tab-list">
			<div class="tab-content">
				<div style="overflow-x: auto">
					<table class="stdtable deps-table">
						<thead><tr><th>Mod</th><th>Release</th><th>Download</th>{if $shouldShowOneClickInstall}<th><abbr title="Requires game version v1.18.0-rc.1 or later, currently not supported on MacOS.">1-Click Install*</abbr></th>{/if}</tr></thead>
						<tbody>
							{foreach from=$context->resolutions item=resolution key=identifier}
								<tr>
									<td>{if $resolution->mod}{htmlspecialchars($resolution->mod['name'])}{else}[{$identifier}]{/if}</td>
									<td>{formatSemanticVersion($resolution->version)}</td>
									<td>{if $resolution->link}<div><a class="button square ico-button mod-dl" href="{$resolution->link}">{htmlspecialchars($resolution->fileName)}</a><a class="button square ico-button deps" target="_blank" href="/show/dependencies/{$resolution->releaseId}"></a></div>{else}<i style="color: var(--color-input-r)">{$resolution->error}</i>{/if}</td>
									{if $shouldShowOneClickInstall}<td>{if $resolution->oneclick}{include file="button-one-click-install" release=$resolution->oneclick}{/if}</td>{/if}
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
				<aside style="margin-top: 1em;" class="text-weak">The algorithm used for resolving dependencies is rather naive (greedy). The resulting tree might therefore not be optimal.</aside>
			</div>
		</div>
	{/if}
</div>

<script nonce="{$cspNonce}" type="text/javascript"> {
	const tree = document.getElementsByClassName('tree')[0];
	tree.addEventListener('click', e => {
		if(e.target.nodeName !== 'A') return;
		const href = e.target.getAttribute('href');
		if(!href.startsWith('#dep')) return;

		const target = document.getElementById(href.substring(1));
		if(!target) return;

		target.classList.add('highlight');
		setTimeout(() => target.classList.remove('highlight'), 2000);
	});

	let t = 'tab-direct';
	switch(location.hash) {
		case '#tab-tree': t = 'tab-tree'; break;
		case '#tab-list': t = 'tab-list'; break;
	}
	document.getElementById(t).checked = true;
}</script>

{include file="footer"}