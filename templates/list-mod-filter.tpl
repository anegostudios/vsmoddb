<details id="{$id}-panel" class="filter{if $filter['in'] || $filter['ex']} active{/if}" open>
	<summary class="head">
		<span class="title">{$title}</span>
		<span class="badges">
			<span class="badge in">{count($filter['in']) ?: ''}</span>
			<span class="badge ex">{count($filter['ex']) ?: ''}</span>
		</span>
		<span class="trailing">
			<button type="button" class="reset" aria-label="Reset {$title} filter">Reset</button>
			<span class="chevron"></span>
		</span>
	</summary>
	<div class="body">
		{if !empty($body)}
			{$bodyraw}
		{else}
			{if !empty($search)}
				<label class="search">
					<span class="icon" aria-hidden="true"></span>
					<input type="search" data-filter-for="{$id}-list" placeholder="Search..." autocomplete="off" />
				</label>
			{/if}

			<ul class="list{if !empty($expandable) && $tagsListExpanded} expanded{/if}" id="{$id}-list">
				{foreach from=$options item=option key=key}
					<li class="{if !empty($option['sub'])}sub{/if}{if isset($filter['in'][$key])} in{/if}{if isset($filter['ex'][$key])} ex{/if}" data-text="{$option['label']}">
						<input type="checkbox" id="{$_name}-{$key}" class="trigger" name="{$_name}[]" value="{$option['value'] ?? $key}" {if isset($filter['in'][$key])} checked{/if} />
						<label for="{$_name}-{$key}" aria-label="Include {$option['label']}">
							<span>{$option['label']}</span>
							<i></i>
						</label>

						{if !empty($exclude)}
						<button type="button" aria-label="Exclude {$option['label']}">
							<i></i>
						</button>
						{/if}
					</li>
				{/foreach}
			</ul>

			{if !empty($expandable)}
				<button type="button" class="expand" aria-controls="{$id}-list" aria-expanded="{$tagsListExpanded ? 'true' : 'false'}" aria-label="{$tagsListExpanded ? 'Collapse' : 'Expand'} tags list">
					<span class="chevron"></span>
				</button>
			{/if}
		{/if}
	</div>
</details>
