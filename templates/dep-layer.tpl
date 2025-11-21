<ul>
	{foreach from=$children item=child}
		<li>
			{if !($res = $child->resolution)->printed && $res->children}
				{assign var=$child->resolution->printed value=true}
				<details open id="dep-{$child->identifier}-{$res->version}">
					<summary>{$child->identifier}@<span class="text-weak">{formatSemanticVersion($child->minVersion)}<sup>+</sup></span> &rArr; {formatSemanticVersion($res->version)}</summary>
					{include file="dep-layer" children=$res->children}
				</details>
			{elseif $res->error}
				<span>{$child->identifier}@{formatSemanticVersion($child->minVersion)}<sup>+</sup> <i style="color: var(--color-input-r)">{$res->error}</i></span>
			{else}
				<span>{$child->identifier}@<span class="text-weak">{formatSemanticVersion($child->minVersion)}<sup>+</sup></span> &rArr; {formatSemanticVersion($res->version)}</span>{if $res->printed} <a href="#dep-{$child->identifier}-{$res->version}" class="text-weak">[reused]</a>{/if}
			{/if}
		</li>
	{/foreach}
</ul>