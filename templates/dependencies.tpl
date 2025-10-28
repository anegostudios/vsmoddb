{include file="header"}

<h4 style="margin-bottom: .5em;">{$rootRelease['fileName']} (v{$rootRelease['version']} of {$rootRelease['modName']})</h4>
<div class="tree" style="margin-left: .25em;">
{include file="dep-layer"}
</div>

<style nonce="{$cspNonce}">
	.tree {
		--spacing: 1.25em;
		--knob-size: .75em;
		line-height: var(--spacing);
		padding-left: var(--spacing);
	}
	.tree ul {
		list-style: none;
		margin-left: calc(var(--spacing) * -1);
	}

	.tree li {
		padding-left: calc(2 * var(--spacing));
		border-left: 2px solid #ddd;
		position: relative;
		margin: 0;
	}
	.tree li:last-child {
		border-color: transparent;
	}

	.tree li::before {
		content: '';
		display: block;
		position: absolute;
		top: calc(var(--spacing) / -2);
		width: calc(var(--spacing) + 2px);
		height: calc(var(--spacing) + 1px);
		border: solid #ddd;
		border-width: 0 0 2px 2px;
		left: -2px;
	}

	.tree summary {
		display: block;
		cursor: pointer;
	}
	.tree summary::marker,
	.tree summary::-webkit-details-marker {
		display: none;
	}
	.tree summary:focus {
		outline: none;
	}
	.tree summary:focus-visible {
		outline: solid 1px #000;
	}

	.tree summary::before {
		content: '+';
		line-height: var(--knob-size);
		text-align: center;
		display: block;
		position: absolute;
		border-radius: 50%;
		width: var(--knob-size);
		height: var(--knob-size);
		left: calc(var(--spacing) - var(--knob-size) / 2 + 1px);
		top: calc(var(--spacing) / 2 - var(--knob-size) / 2 + 2px);
		background-color: #ddd;
		z-index: 1;
	}

	.tree details[open]>summary::before {
		content: '-';
		line-height: calc(var(--knob-size) / 1.25);
	}


</style>

{include file="footer"}