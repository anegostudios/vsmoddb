<ul>
	{foreach from=$treeLayer item=childLayer key=key}
		<li>
			{if $childLayer}
				<details open>
					<summary>{$key}</summary>
					{include file="dep-layer" treeLayer=$childLayer}
				</details>
			{else}
				<span>{$key}</span>
			{/if}
		</li>
	{/foreach}
</ul>