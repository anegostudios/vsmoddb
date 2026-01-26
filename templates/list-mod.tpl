{include file="header"}

	{if $selectedParams['category'] === 's'}
	<p>These mods are generally not meant for personal use, instead being designed for specific servers.</p>
	{/if}
	
	<form method="get" autocomplete="off" class="flex-list" style="margin-bottom: 1em;">
		<input type="hidden" name="sortby" value="{$selectedParams['order'][0]}">
		<input type="hidden" name="sortdir" value="{$selectedParams['order'][1][0]}">

		<span data-label="Text" title="Searches mod names, summaries and descriptions.">
			<input type="text" name="text" value="{$selectedParams['text']}" style="width:12em;">
		</span>

		<span data-label="Side">
			<select name="side" style="width:10em;">
				<option value="">Any</option>
				<option value="both"{if $selectedParams['side'] == 'both'} selected="selected"{/if}>Both</option>
				<option value="client"{if $selectedParams['side'] == 'client'} selected="selected"{/if}>Client side mod</option>
				<option value="server"{if $selectedParams['side'] == 'server'} selected="selected"{/if}>Server side mod</option>
			</select>
		</span>

		<span data-label="Tags">
			<select style="width:20em;" name="tagids[]" multiple>
				{foreach from=$tags item=tag}
					<option value="{$tag['tagId']}" title="{$tag['text']}"{if isset($selectedParams['tags'][$tag['tagId']])} selected="selected"{/if}>{$tag['name']}</option>
				{/foreach}
			</select>
		</span>
		
		<span id="contributor-box" data-label="Contributor">
			<select style="width:10em;" name="a" data-url="/api/v2/users/by-name/\{name}?contributors-only=1" data-placeholder="Search Users">
				<option value="">-</option>
				{if !empty($selectedParams['contributor'])}<option value="{$selectedParams['contributor'][0]}" selected="true">{$selectedParams['contributor'][1]}</option>{/if}
			</select>
		</span>
		
		<span data-label="Game Version">
			<select style="width:10em;" name="mv" noSearch="noSearch">
				<option value="">Any</option>
				{foreach from=$majorGameVersions item=version}
					<option value="{$version['name']}"{if $selectedParams['majorversion'] === $version['version']} selected="selected"{/if}>{$version['name']}.x</option>
				{/foreach}
			</select>
		</span>
		
		<span data-label="Game Version Exact">
			<select style="width:12em;" name="gv[]" multiple>
				{foreach from=$gameVersions item=version}
					<option value="{$version['name']}"{if isset($selectedParams['gameversions'][$version['version']])} selected="selected"{/if}>{$version['name']}</option>
				{/foreach}
			</select>
		</span>

		{if $selectedParams['category'] !== 's'}
		<span data-label="Category">
			<select name="c" style="width:14em;">
				<option value="">Any</option>
				<option value="m"{if $selectedParams['category'] === 'm'} selected="selected"{/if}>Game Mod</option>
				<option value="e"{if $selectedParams['category'] === 'e'} selected="selected"{/if}>External Tool</option>
				<option value="o"{if $selectedParams['category'] === 'o'} selected="selected"{/if}>Other</option>
			</select>
		</span>
		{else}
		<input type="hidden" name="c" value="s">
		{/if}

		<span data-label="Mod Type">
			<select name="t" style="width:10em;">
				<option value="">Any</option>
				<option value="v"{if $selectedParams['type'] === 'v'} selected="selected"{/if}>* Theme Pack (purely visual)</option>
				<option value="d"{if $selectedParams['type'] === 'd'} selected="selected"{/if}>* Content Mod</option>
				<option value="c"{if $selectedParams['type'] === 'c'} selected="selected"{/if}>* Code Mod</option>
			</select>
		</span>

		{if canModerate(null, $user)}
		<span data-label="[Moderator] Mod Status">
			<select name="stati[]" multiple="true" noSearch="noSearch" style="width:18em;">
				<option value="1"{if isset($selectedParams['stati'][1])} selected="selected"{/if}>Draft</option>
				<option value="2"{if isset($selectedParams['stati'][2])} selected="selected"{/if}>Released</option>
				<option value="3"{if isset($selectedParams['stati'][3])} selected="selected"{/if}>Status3</option>
				<option value="4"{if isset($selectedParams['stati'][4])} selected="selected"{/if}>Locked</option>
			</select>
		</span>
		{/if}

		<span data-label="">
			<button class="button shine" type="submit" name="">Search</button>
		</span>
	</form>
	
	<p id="warn-missing-data"{if !$selectedParams['type']} style="display: none;"{/if}>
		<small><sup>*</sup> Mod releases from before July 1st 2025 do not have this information available and will not show up when this filter is selected. This data will become available in the future.</small>
	</p>
	<div class="sort" style="margin-bottom: 1em;">
		<small>
		Sort by: 
		{foreach from=$sortOptions item=opt key=key}
			<span style="margin-right: 5px;">
				{if $key == $selectedParams['order'][0] && $selectedParams['order'][1] === 'desc'}
					<a href="?{$strippedQuery}&sortby={$key}&sortdir=a">{$opt[2]}</a>
				{else}
					<a href="?{$strippedQuery}&sortby={$key}&sortdir=d">{$opt[1]}</a>
				{/if}
			</span>
		{/foreach}
		</small>
	</div>

	{if !empty($mods)}
		<p>List of mods, ordered by {$sortOptions[$selectedParams['order'][0]][$selectedParams['order'][1] === 'asc' ? 2 : 1]}</p>
		<div class="mods">
			{foreach from=$mods item=mod}{include file="list-mod-entry"}{/foreach}
			{if count($mods) < 5 /* @hack some spacing so the mods dont blow out */}<span></span><span></span><span></span><span></span><span></span>{/if}
		</div>
		{if $fetchCursorJS}<div id="scroll-trigger" style="text-align: center; margin-top: 1em;">Loading More...</div>{/if}
	{else}
		<p>&nbsp;</p>
		No mods found :(
	{/if}

	<script nonce="{$cspNonce}" type="text/javascript">
		$("select").each(function() {
			if ($(this).parents(".template").length == 0) {
				var ds = $(this).attr("noSearch") == 'noSearch';
				$(this).chosen({ placeholder_text_multiple: " ", disable_search:ds, });
			}
		});

		const missingDataLabelEl = document.getElementById('warn-missing-data');
		const categorySelectEl = document.querySelector('select[name="c"]');
		const typeSelectEl = document.querySelector('select[name="t"]');
		$(typeSelectEl).on('change', function(e) {
			if(e.target.value) {
				missingDataLabelEl.style.display = '';
				categorySelectEl.value = 'm';
				$(categorySelectEl).trigger('chosen:updated');
			}
			else {
				missingDataLabelEl.style.display = 'none';
			}
		});

		$(categorySelectEl).on('change', function(e) {
			if(e.target.value !== 'm') {
				missingDataLabelEl.style.display = 'none';
				typeSelectEl.value = '';
				$(typeSelectEl).trigger('chosen:updated');
			}
		});

		$(() => attachUserSearchHandler(document.getElementById('contributor-box')));

		let fetchCursor = '{$fetchCursorJS}';
		const scrollTrigger = document.getElementById('scroll-trigger');
		if(scrollTrigger) {
			let isFetching = false;

			if('IntersectionObserver' in window) {
				const elementHeight = 300;
				const observer = new IntersectionObserver(entries => {
					if(isFetching)   return;

					let fetchEntry = null;
					for(const e of entries) {
						if(e.intersectionRatio === 0) continue;
						fetchEntry = e;
						isFetching = true;
					}
					if(fetchEntry === null)  return;

					(async () => {
						for(let i = 0; fetchEntry.target.getBoundingClientRect().top < (window.innerHeight + elementHeight) && i < 20; i++) {
							await fetchMore()
								.catch((d) => {
									if(d === 'DONE') {
										observer.disconnect();
										i = 99;
									}
									isFetching = false;
								});
						}
						isFetching = false;
					})();
				}, {
					root: document.body, // needs to the the scroll ancestor
					rootMargin: `0px 0px ${elementHeight}px 0px`,
				});
				observer.observe(scrollTrigger);
			}
			else {
				const btn = document.createElement('button');
				btn.textContent = 'Load More';
				btn.addEventListener('click', onFetchMoreClick);
				scrollTrigger.replaceChildren(btn);
				
				function onFetchMoreClick() {
					if(isFetching) return;

					isFetching = true;
					btn.setAttribute('disabled', 'true')
					fetchMore()
						.finally(() => {
							btn.removeAttribute('disabled');
							isFetching = false;
						});
				}
			}

			const modsContainer = document.getElementsByClassName('mods')[0];
			let pageFetchUrl = window.location.search;
			pageFetchUrl += pageFetchUrl ? '&paging=1' : '?paging=1';
			function fetchMore() {
				if(!fetchCursor) {
					//TODO @cleanup: Remove trigger/button immediately after response without cursor.
					// Will remove the trigger / button one request after the response where we already know that there is nothing more to get.
					// This is only really noticeable with the fallback mode, since you click the button and it just adds nothing before it finally vanishes.
					scrollTrigger.remove();
					return Promise.reject('DONE');
				}

				return fetch(pageFetchUrl + fetchCursor)
					.then(r => new Promise((resolve, reject) => r.text().then(t => resolve([t, r])).catch(e => reject(e))))
					.then(c => {
						const fragment = document.createRange().createContextualFragment(c[0]);
						modsContainer.append(fragment);
						
						fetchCursor = c[1].headers.get('X-Fetch-Cursor');
					})
					.catch((t) => {
						R.addMessage(MSG_CLASS_ERROR, 'Failed to fetch more mods: ' + t, true)
					});
			}
		}
	</script>

{include file="footer"}
