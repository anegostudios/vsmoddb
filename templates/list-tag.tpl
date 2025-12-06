{include file="header"}

	<h2>
		<span>
			<a href="/list/tag">{intval(count($rows))} Tags</a>
		</span>
	</h2>	
	
	<table class="stdtable" id="Tags" style="display: inline-table;">
		<thead>
			<tr>
				{foreach from=$columns item=column}
					<th class="{$column['cssclassname']}">{$column['title']}</th>
				{/foreach}
			</tr>
		</thead>
		<tbody>
		{if !empty($rows)}
			{foreach from=$rows item=row}
				<tr>
					<td><a href="/edit/tag?tagid={$row['tagId']}">{$row['name']}</a></td>
					<td><a href="/edit/tag?tagid={$row['tagId']}"><span style="display: inline-block; vertical-align: bottom; height: 1.2em; aspect-ratio: 1; background-color: #{$row['color']}"></span> {$row['color']}</a></td>
				</tr>
			{/foreach}
		{else}
			<td colspan="{count($columns)}"><i>No Tags found</i></td>
		{/if}
		</tbody>
	</table>

	<table class="stdtable" id="Versions" style="display: inline-table;" onclick="clickDelete(event)">
		<thead>
			<tr><th>Game Version</th><th></th></tr>
		</thead>
		<tbody>
		{if !empty($gameVersionStrings)}
			{foreach from=$gameVersionStrings item=gvStr}
				<tr>
					<td>{$gvStr}</td>
					<td><button class="button btndelete strikethrough-when-readonly">X</button></td>
				</tr>
			{/foreach}
		{else}
			<td colspan="2"><i>No Versions found</i></td>
		{/if}
		</tbody>
	</table>


{capture name="buttons"}
	<a class="button large shine strikethrough-when-readonly" href="/edit/tag">New Tag</a>
	<button class="button large shine strikethrough-when-readonly" onclick="addVersionPrompt()" nonce="{$cspNonce}">Manually Add Version</button>
{/capture}

<script nonce="{$cspNonce}" type="text/javascript">
	function addVersionPrompt() {
		let newVerStr = prompt('Specify the game version to add. The new version string must match our semver derivate\n (/^\\d+.\\d+.\\d+.(-(dev|pre|rc).\\d+)?$/).');
		if(!newVerStr || !(newVerStr = newVerStr.trim())) return;
		if(newVerStr[0] === 'v') newVerStr = newVerStr.slice(1); // Just being nice here. If the input starts with a v, just slice it off.

		const xhr = $.post('/api/v2/game-versions', { new: newVerStr, at: actiontoken })
			.done(function() {
				R.addMessage(MSG_CLASS_OK, `Successfully added '${newVerStr}'.`, true);
				const newRow = $(`<tr><td>${newVerStr}</td><td><button class="button btndelete">X</button></td></tr>`).get(0);
				document.getElementById('Versions').getElementsByTagName('tbody')[0].prepend(newRow);
			});
		R.attachDefaultFailHandler(xhr, 'Failed to add Version');
	}

	function clickDelete(e) {
		if(!e.target || !e.target.classList.contains('btndelete')) return;

		const targetVersion = e.target.parentElement.previousElementSibling.textContent;

		const xhr = $.ajax({ url: `/api/v2/game-versions/${targetVersion}?at=${actiontoken}`, method: 'DELETE' })
			.done(function() {
				R.addMessage(MSG_CLASS_OK, `Successfully deleted '${targetVersion}'.`, true);
				e.target.parentElement.parentElement.remove();
			});
		R.attachDefaultFailHandler(xhr, 'Failed to delete Version');
	}

</script>

{include file="footer"}
