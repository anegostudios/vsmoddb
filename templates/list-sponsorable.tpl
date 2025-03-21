{include file="header" pagetitle="Sponsorable - "}

<h2>List of all Users with sponsor links</h2>

<div id="sponsorable-list">
	{if count($dataByUser)}
		<table class="stdtable">
			<thead><tr><th>User</th><th><abbr title="There might be more in the description!">Confirmed Urls</abbr></th></tr></thead>
			<tbody>
				{foreach from=$dataByUser item=sponsorableUserData}
					<tr>
						<td><a href="/show/user/{$sponsorableUserData['userhash']}">{$sponsorableUserData['username']}</a></td>
						<td>{implode(' ', array_keys($sponsorableUserData['confirmedurls']))}</td>
					</tr>
					<tr>
						<td colspan="2">
							<details>
								<summary>{count($sponsorableUserData['mods'])} Mod(s) with sponsor option.</summary>
								<ul>
									{foreach from=$sponsorableUserData['mods'] item=mod}
									<li>
										<a href="{$mod['path']}"><h4><img src="{$mod['logourl'] ?? '/web/img/mod-default.png'}"> <span>{$mod['name']}</span></h4></a>
										<div class="matches">{$mod['matchhtml']}</div>
									</li>
									{/foreach}
								</ul>
							</details>
						</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	{else}
		<h3>Result is empty</h3>
	{/if}
</div>

<style>
	#sponsorable-list details {
		display: block;
	}
	#sponsorable-list summary {
		cursor: pointer;
	}
	#sponsorable-list summary:hover {
		background-color: var(--color-input);
	}

	#sponsorable-list ul {
		list-style: none;
	}
	#sponsorable-list li {
		margin-left: 0;
		border: 1px solid var(--color-border)
	}

	#sponsorable-list details h4 {
		border-bottom: 1px solid var(--color-border);
	}
	#sponsorable-list details h4>* {
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
