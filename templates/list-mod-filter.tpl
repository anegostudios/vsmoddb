<details id="{$id}-panel" class="filter{if $filter['count'] > 0} active{/if}" open{if !empty($defaults)} data-defaults="{$defaults}"{/if}>
	<summary class="head">
		<span class="title">{$title}</span>
		<span class="badges"{if $filter['count'] == 0} hidden{/if}>
			<span class="badge" data-in{if $filter['inCount'] == 0} hidden{/if}>{$filter['inCount']}</span>
			{if !empty($exclude)}
				<span class="badge" data-ex{if $filter['exCount'] == 0} hidden{/if}>
					{$filter['exCount']}
				</span>
			{/if}
		</span>
		<span class="trailing">
			<button type="button" class="reset" aria-label="Reset {$title} filter"{if $filter['count'] == 0} hidden tabindex="-1"{/if}>
				Reset
			</button>
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
						<button type="button" data-role="in" aria-label="Include {$option['label']}">
							<span class="label">{$option['label']}</span>
							<i></i>
						</button>

						<input type="checkbox" name="{$name}" value="{$option['value'] ?? $key}" data-role="in"{if isset($filter['in'][$key])} checked{/if} />

						{if !empty($exclude)}
							<button type="button" data-role="ex" aria-label="Exclude {$option['label']}">
								<i></i>
							</button>
							<input type="checkbox" name="{$name}" value="-{$option['value'] ?? $key}" data-role="ex"{if isset($filter['ex'][$key])} checked{/if} />
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
