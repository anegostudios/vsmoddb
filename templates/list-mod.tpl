{include file="header"}

	{if $selectedParams['category'] === 's'}
		<p>These mods are generally not meant for personal use, instead being designed for specific servers.</p>
	{/if}

	{if $selectedParams['category'] === 'l'}
		<p>These mods do not modify the game by themselves. They are sometimes required by other mods.</p>
	{/if}

	<div class="mod-list">
		<input id="drawer-trigger" class="trigger" type="checkbox" autocomplete="off" />

		<aside class="filters" id="filters" >
			<form id="filter-inputs" method="get" action="/list/mod" autocomplete="off" onsubmit="return false;">
				<input type="hidden" name="sortby" value="{$selectedParams['order'][0]}">
				<input type="hidden" name="sortdir" value="{$selectedParams['order'][1][0]}">
				<input type="hidden" name="c" value="{$selectedParams['category']}">

				<div class="top">
					<h2>Filters</h2>
					<button type="button" id="filters-reset-all" class="reset" {if !$strippedQuery}hidden{/if}>Reset</button>
				</div>

				<label class="search big" title="Search mods...">
					<span class="icon" aria-hidden="true"></span>
					<input type="search" name="text" value="{$selectedParams['text']}" placeholder="Search mods...">
				</label>

				{include file="list-mod-filter" id="gameversion" title="Game version" _name="gv" filter=$selectedParams['gameversions'] options=$gameVersionOptions search="1"}

				{include file="list-mod-filter" id="tag" title="Tags" _name="tg" filter=$selectedParams['tags'] options=$tagOptions search="1" exclude="1" expandable="1"}

				<details id="contributor-panel" class="filter{if $selectedParams['contributor']} active{/if}" open>
					<summary class="head">
						<span class="title">Contributor</span>
						<span class="badges">
							<span class="badge in"><?= $selectedParams['contributor'] ? '1' : '' ?></span>
							<span class="badge ex"></span>
						</span>
						<span class="trailing">
							<button type="button" class="reset" aria-label="Reset Contributor filter">Reset</button>
							<span class="chevron"></span>
						</span>
					</summary>
					<div class="body">
						<select name="a" data-url="/api/v2/users/by-name/\{name}?contributors-only=1" data-placeholder="Search Users" class="no-chosen">
							<option value="">-</option>
							{if $selectedParams['contributor']}<option value="{$selectedParams['contributor']['hash']}" selected>{$selectedParams['contributor']['name']}</option>{/if}
						</select>
					</div>
				</details>

				{include file="list-mod-filter" id="side" title="Mod side" _name="sd" filter=$selectedParams['sides'] options=$sideOptions exclude="1"}

				{if $selectedParams['category'] === 'm' || $selectedParams['category'] === 'l'}
					{include file="list-mod-filter" id="type" title="Mod type" _name="t" filter=$selectedParams['types'] options=$typeOptions exclude="1"}
				{/if}

				{if canModerate(null, $user)}
					{include file="list-mod-filter" id="status" title="Mod status" _name="st" filter=$selectedParams['stati'] options=$statusOptions exclude="1"}
				{/if}
			</form>
		</aside>

		<div class="results">
			<p id="warn-missing-data"{if !$selectedParams['types']['in'] && !$selectedParams['types']['ex']} hidden{/if}>
				<small>"Mod type" : Mod releases from before July 1st 2025 do not have this information available and will not show up when this filter is selected. This data will become available in the future.</small>
			</p>
			<div class="sort" style="margin-bottom: 1em;">
				<small>
				Sort by:
				{foreach from=$sortOptions item=opt key=key}
					<span style="margin-right: 5px;">
						{if $key == $selectedParams['order'][0] && $selectedParams['order'][1] === 'desc'}
							<a href="?{$strippedQuery}&sortby={$key}&sortdir=a" data-sortby="{$key}" data-sortdir="a">{$opt[2]}</a>
						{else}
							<a href="?{$strippedQuery}&sortby={$key}&sortdir=d" data-sortby="{$key}" data-sortdir="d">{$opt[1]}</a>
						{/if}
					</span>
				{/foreach}
				</small>
			</div>


			<p id="order-by-lbl">List of mods, ordered by {$sortOptions[$selectedParams['order'][0]][$selectedParams['order'][1] === 'asc' ? 2 : 1]}</p>
			<div id="mods-paging-wrapper" >
				<div class="mods">
					{foreach from=$mods item=mod}{include file="list-mod-entry"}{/foreach}
				</div>

				<div class="fetch-trigger"{if !$fetchCursorJS} hidden{/if}>{if count($mods)}Loading more…{else}No mods found :({/if}</div>
			</div>
		</div>

		<label id="open-filters" for="drawer-trigger" aria-controls="filters" aria-expanded="false">Filters</label>
		<label id="backdrop" for="drawer-trigger"></label>
	</div>

	<script nonce="{$cspNonce}" type="text/javascript">
		$(function() \{ attachModListInteractions(\{
			baseUrl: '?{$strippedQuery}',
			cursor: '{$fetchCursorJS}',
			statusDefaults: [<?= STATUS_RELEASED ?>, <?= STATUS_LOCKED ?>],
		}); });
	</script>

{include file="footer"}
