{include file="header"}
	
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
		
		<span id="author-box" data-label="Author">
			<select style="width:10em;" name="userid" data-url="/api/authors?name=\{name}" data-placeholder="Search Users">
				<option value="">-</option>
				{if !empty($selectedParams['creator'])}<option value="{$selectedParams['creator'][0]}" selected="true">{$selectedParams['creator'][1]}</option>{/if}
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

		<span data-label="Category">
			<select name="c" style="width:10em;">
				<option value="">Any</option>
				<option value="m"{if $selectedParams['category'] == 'm'} selected="selected"{/if}>Game mod</option>
				<option value="e"{if $selectedParams['category'] == 'e'} selected="selected"{/if}>External Tool</option>
				<option value="o"{if $selectedParams['category'] == 'o'} selected="selected"{/if}>Other</option>
			</select>
		</span>

		<span data-label="Mod Type">
			<select name="t" style="width:10em;">
				<option value="">Any</option>
				<option value="v"{if $selectedParams['type'] == 'v'} selected="selected"{/if}>* Theme Pack (purely visual)</option>
				<option value="d"{if $selectedParams['type'] == 'd'} selected="selected"{/if}>* Content mod</option>
				<option value="c"{if $selectedParams['type'] == 'c'} selected="selected"{/if}>* Code mod</option>
			</select>
		</span>

		<span data-label="">
			<button class="button shine" type="submit" name="">Search</button>
		</span>
	</form>
	
	<p id="warn-missing-data"{if !$selectedParams['type']}style="display: none;"{/if}>
		<small><sup>*</sup> Mod releases from before July 1st 2025 do not have this information available and will not show up when this filter is selected.</small>
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

	<script type="text/javascript">
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

		const $authorBox = $('#author-box');
		let waitTimeout = null, lastWaitTimeout = null;
		$($authorBox, 'input.chosen-search-input').on('keydown', function() {
			if(waitTimeout !== null) {
				clearTimeout(waitTimeout);
			}

			lastWaitTimeout = waitTimeout;
			waitTimeout = setTimeout(() => getAuthors($authorBox, lastWaitTimeout), 500);
		});

		function getAuthors($box, timeoutRef) {
			const $searchInput = $box.find(".chosen-search-input");
			const searchname = $searchInput.val();
			
			if (!searchname) {
				waitTimeout = null;
				return;
			}
			
			const $select = $box.find("select");
			const url = $select.data('url').replace("{name}", searchname);
			
			$.get(url, function (data) {
				if(lastWaitTimeout !== timeoutRef) {
					return
				}

				const authors = data.authors;
				if (!authors) {
					waitTimeout = null;
					return;
				}
				
				const currentUserIds = $select.val();
				const $currentSelected = $select.children(":selected");
				$select.empty();
				$select.append($currentSelected);

				authors.forEach(function (author) {
					if (currentUserIds != null && currentUserIds.includes(author.userid+'')) return;
					
					$select.append(`<option value="${author.userid}">${author.name}</option>`);
				});

				$select.trigger("chosen:updated");
				$searchInput.val(searchname);
				// Choses resets this values in the update call. We manually modify the search, so we need to set the width as well.
				$searchInput.css("width", "145px");
				waitTimeout = null;
			});
		}

		
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
						addMessage(MSG_CLASS_ERROR, 'Failed to fetch more mods: ' + t, true)
					});
			}
		}
	</script>

{include file="footer"}
