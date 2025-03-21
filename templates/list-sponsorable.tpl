{include file="header" pagetitle="Sponsorable - "}

<h2>List of all Users with sponsor links</h2>

<div id="sponsorable-list">
	{foreach from=$dataByUser item=sponsorableUserData}
		<details>
			<summary><a href="/show/user/{$sponsorableUserData['userhash']}">{$sponsorableUserData['username']}</a>: {count($sponsorableUserData['mods'])} Mod(s) with sponsor option</summary>
			<ul>
				{foreach from=$sponsorableUserData['mods'] item=mod}
				<li>
					<a href="{$mod['path']}"><h4><img src="{$mod['logourl'] ?? '/web/img/mod-default.png'}"> <span>{$mod['name']}</span></h4></a>
					<div class="matches">{$mod['matchhtml']}</div>
				</li>
				{/foreach}
			</ul>
		</details>
	{/foreach}

	{if !count($dataByUser)}<h3>Result is empty</h3>{/if}
</div>

<style>
	#sponsorable-list details {
		display: block;
	}
	#sponsorable-list summary {
		height: 3em;
		padding: 1em 0;
		cursor: pointer;
	}
	#sponsorable-list summary:hover {
		background-color: var(--color-input);
	}

	#sponsorable-list ul {
		list-style: none;
	}
	#sponsorable-list li {
		border: 1px solid var(--color-border)
	}

	#sponsorable-list h4 {
		border-bottom: 1px solid var(--color-border);
	}
	#sponsorable-list h4>* {
		vertical-align: middle;
	}

	#sponsorable-list img {
		width: 2em;
		height: 2em;
		object-fit: contain;
	}

	#sponsorable-list .matches>* {
		display: block;
	}
</style>

{include file="footer"}
