const SCROLL_ROOT_MARGIN_PX = 300;
const MOD_LIST_TEXT_SEARCH_DEBOUNCE_MS = 350;

function attachModListInteractions() : void
{
	const wrapperEl = document.getElementsByClassName('results')[0] as HTMLElement;

	const orderByLabelEl = R.get('list-status')!;
	const sortWrapperEl = document.getElementsByClassName('sort')[0];

	const listWrapperEl = R.get('mods-paging-wrapper')!;
	const cellContainerEl = listWrapperEl.firstElementChild!;
	const fetchTriggerEl = listWrapperEl.lastElementChild!;

	const currentUrl = new URL(wrapperEl.dataset.baseUrl!); // mutated during input change name

	const inputsFormEl = R.get<HTMLFormElement>('filter-inputs')!;
	const searchInputEl = inputsFormEl.querySelector<HTMLInputElement>('input[name="text"]')!;
	const contributorWrapperEl = R.get('contributor')!;
	const contributorSelectEl = contributorWrapperEl.querySelector<HTMLSelectElement>('select[name="a"]')!;
	const $contributorSelectEl = $(contributorSelectEl);

	const resetAllEl = R.get('filters-reset-all')!;
	const badgeEls = wrapperEl.getElementsByClassName('badge');

	// --- 

	let textDebounceTimer = 0;
	searchInputEl.addEventListener('input', () => {
		clearTimeout(textDebounceTimer);
		textDebounceTimer = setTimeout(() => {
			if(searchInputEl.value) currentUrl.searchParams.set('text', searchInputEl.value);
			else currentUrl.searchParams.delete('text');

			updateStateAndRestartFetching();
		}, MOD_LIST_TEXT_SEARCH_DEBOUNCE_MS);
	})

	attachRemoteSearchHandler(contributorWrapperEl);
	$contributorSelectEl.on('change', () => {
		const selectedIds = $contributorSelectEl.val();
		currentUrl.searchParams.delete('a');
		if(selectedIds)
			for(const v of selectedIds)
				currentUrl.searchParams.append('a', v);

		updateStateAndRestartFetching();
	});


	inputsFormEl.addEventListener('click', e => {
		const button = (e.target as Element).closest('button');
		if(!button) return;

		if(button.id === 'filters-reset-all') {
			clearTimeout(textDebounceTimer);

			searchInputEl.value = '';
			for(const el of wrapperEl.querySelectorAll('input[type="checkbox"]') as NodeListOf<HTMLInputElement>)
				el.checked = false;

			contributorSelectEl.value = '';
			$contributorSelectEl.trigger('chosen:updated');

			{ const el = wrapperEl.querySelector<HTMLInputElement>('input[name="st"][value="2"]'); if(el) el.checked = true; } // STATUS_RELEASED
			{ const el = wrapperEl.querySelector<HTMLInputElement>('input[name="st"][value="4"]'); if(el) el.checked = true; } // STATUS_LOCKED

			resetAllEl.hidden = true;
			updateStateAndRestartFetching();
		}
		else if(button.classList.contains('reset')) {
			e.preventDefault();
			const filterPanel = button.closest('.filter');
			if(!filterPanel) return;

			for(const el of filterPanel.querySelectorAll('input[type="checkbox"]') as NodeListOf<HTMLInputElement>)
				el.checked = false;

			hideGlobalResetIfNothingActive();
			updateStateAndRestartFetching();
		}
		else if(button.firstElementChild?.nodeName === 'I') { // has to be the exclude button
			const optionEl = button.closest('li');
			if(!optionEl) return;

			const checkboxEl = optionEl.firstElementChild as HTMLInputElement;

			if(checkboxEl.value.startsWith('-')) checkboxEl.checked = false;
			else {
				checkboxEl.value = '-'+checkboxEl.value;
				checkboxEl.checked = true;
				//TODO manually fire change event?
			}

			//updateStateAndRestartFetching(); // handled in change handler
		}
	});

	inputsFormEl.addEventListener('change', e => {
		const target = e.target as HTMLInputElement;
		if(target.type !== 'checkbox') return;

		const sectionWrapperEl = target.closest('filter')!;
		const badgeInEl = sectionWrapperEl.getElementsByClassName('badge in')[0];
		const badgeExEl = sectionWrapperEl.getElementsByClassName('badge ex')[0];

		const isExclude = target.value.startsWith('-');
		if(target.checked) {
			const inverseValue = isExclude ? target.value.slice(1) : '-'+target.value;
			currentUrl.searchParams.delete(target.name, inverseValue);
			currentUrl.searchParams.append(target.name, target.value);

			const includeValue = isExclude ? inverseValue : target.value;
			let cIn = 0, cEx = 0;
			const valuesWithThisName = currentUrl.searchParams.getAll(target.name);
			for(const v of valuesWithThisName)
				if(v === includeValue)
					cIn++;
			cEx = valuesWithThisName.length - cIn;

			badgeInEl.textContent = cIn ? String(cIn) : '';
			badgeExEl.textContent = cEx ? String(cEx) : '';

			sectionWrapperEl.classList.add('active');
			resetAllEl.hidden = false;
		}
		else {
			currentUrl.searchParams.delete(target.name, target.value);

			const targetBadge = isExclude ? badgeExEl : badgeInEl;
			const newCounter = parseInt(targetBadge.textContent!, 10) - 1;
			
			targetBadge.textContent = newCounter ? String(newCounter) : '';

			if(!(badgeExEl.textContent || badgeInEl.textContent)) {
				sectionWrapperEl.classList.remove('active');
			}

			hideGlobalResetIfNothingActive();
		}


		updateStateAndRestartFetching();
	})

	window.addEventListener('popstate', () => {
		const search = new URLSearchParams(location.search);

		for(const k of currentUrl.searchParams.keys()) // bit ugly, but searchParams is readonly
			currentUrl.searchParams.delete(k);
		for(const [k, v] of search)
			currentUrl.searchParams.append(k, v);

		searchInputEl.value = search.get('text') || '';
		
		for(const el of inputsFormEl.querySelectorAll('input[type="checkbox"]') as NodeListOf<HTMLInputElement>) {
			const values = search.getAll(el.name);
			if(values.includes(el.value)) {
				el.checked = true;
			}
			else {
				// If the value is not directly present, see if the negated version is present.
				// If neither are, set the non-prefixed version as the value and uncheck it.
				if(el.value.startsWith('-')) {
					el.value = el.value.slice(1);
					el.checked = values.includes(el.value);
				}
				else if(values.includes('-'+el.value)) {
					el.value = '-'+el.value;
					el.checked = true;
				}
				else {
					el.checked = false;
				}
			}
		}

		contributorSelectEl.value = search.get('a') || '';
		$contributorSelectEl.trigger('chosen:updated');

		updateStateAndRestartFetching(/* pushHistory: */ false)
	});

	function hideGlobalResetIfNothingActive()
	{
		let anyActive = !!searchInputEl.value;
		anyActive = anyActive || !!contributorSelectEl.value;
		anyActive = anyActive || Array.prototype.some.call(badgeEls, (el : HTMLElement) => el.textContent);
		if(!anyActive) resetAllEl.hidden = true;
	}

	function updateStateAndRestartFetching(pushHistory : boolean = true)
	{
		searchGeneration++;
		cellContainerEl.replaceChildren(); // clear
		hasMoreResults = true;
		fetchCursor = '';
		startFetchingAndAppending();
		if(pushHistory) history.pushState(null, '', currentUrl.toString());
		updateSortByLinks();
	}

	function updateSortByLinks() : void
	{
		const search = new URLSearchParams(currentUrl.searchParams); // don't mutate the global, make a copy

		for(const linkEl of sortWrapperEl.getElementsByClassName('a') as HTMLCollectionOf<HTMLAnchorElement>) {
			search.set('sortby', linkEl.dataset.sortby!);
			search.set('sortdir', linkEl.dataset.sortdir!);
			linkEl.href = '/list/mod?' + search.toString();
		}
	}

	// ---

	let fetchCursor = wrapperEl.dataset.initialCursor!;

	let hasMoreResults = cellContainerEl.childElementCount > 0;
	
	let isFetching = false;
	let searchGeneration = 0;
	/** @returns true if more should be fetched, false if done. */
	async function fetchAndAppendNextPage() : Promise<boolean>
	{
		const _searchGeneration = searchGeneration;

		if(cellContainerEl.childElementCount === 0) {
			orderByLabelEl.textContent = 'Loading...';
		}

		let fetchUrl = new URL(currentUrl);
		for(const [k, v] of new URLSearchParams(fetchCursor))
			fetchUrl.searchParams.append(k, v);

		const response = await fetch(fetchUrl);
		if(_searchGeneration !== searchGeneration) return true; // Stale data (search changed since request started). Don't append but still continue requesting more.

		if(!response.ok) {
			R.addMessage(MSG_CLASS_ERROR, 'Failed to fetch more results.', false);
			hasMoreResults = false;
			return false;
		}

		let html = await response.text();
		if(_searchGeneration !== searchGeneration) return true;

		fetchCursor = response.headers.get('X-Fetch-Cursor') || '';
		hasMoreResults = !!fetchCursor;

		if(html = html.trim()) {
			if(cellContainerEl.childElementCount === 0) {
				orderByLabelEl.textContent = 'List of mods, ordered by '+'something'; // TODO
			}

			cellContainerEl.append(document.createRange().createContextualFragment(html));
		}
		else {
			if(cellContainerEl.childElementCount === 0 && !hasMoreResults) {
				orderByLabelEl.textContent = 'No mods found :(';
			}
		}

		return hasMoreResults;
	}

	function startFetchingAndAppending() : void
	{
		if(isFetching) return;
		isFetching = true;

		(async () => {
			// Repeatedly fetch more until the screen has been filled, regardless of page size:
			for (let i = 0; hasMoreResults && i < 20; i++) {
				await fetchAndAppendNextPage();

				const triggerPosition = fetchTriggerEl.getBoundingClientRect().top;
				if(triggerPosition >= window.innerHeight + SCROLL_ROOT_MARGIN_PX) break;
			}

			isFetching = false;
		})();
	}

	if('IntersectionObserver' in window) {
		const observer = new IntersectionObserver(
			entries => {
				if(isFetching) return;
				if(!entries.some(e => e.intersectionRatio !== 0)) return;

				startFetchingAndAppending();
			},
			{
				root: document.body,
				rootMargin: `0px 0px ${SCROLL_ROOT_MARGIN_PX}px 0px`,
			},
		);
		observer.observe(fetchTriggerEl);
	}
	else {
		const loadMoreButton = R.make<HTMLButtonElement>('button.load-more', 'Load more');
		loadMoreButton.type = 'button';
		loadMoreButton.addEventListener('click', () => {
			loadMoreButton.disabled = true;
			isFetching = true;
			fetchAndAppendNextPage()
				.finally(() => {
					loadMoreButton.disabled = false;
					isFetching = false;
				});
		});

		fetchTriggerEl.replaceChildren(loadMoreButton);
	}
}
