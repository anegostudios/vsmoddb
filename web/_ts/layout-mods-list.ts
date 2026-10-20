const SCROLL_ROOT_MARGIN_PX = 300;
const MOD_LIST_TEXT_SEARCH_DEBOUNCE_MS = 350;

type ModListArgs = {
	baseUrl : string,
	cursor : string,
	statusDefaults: number[],
}

function attachModListInteractions(args : ModListArgs) : void
{
	const orderByLabelEl = R.get('order-by-lbl')!;
	const sortWrapperEl = document.getElementsByClassName('sort')[0];

	const listWrapperEl = R.get('mods-paging-wrapper')!;
	const cellContainerEl = listWrapperEl.firstElementChild!;
	const fetchTriggerEl = listWrapperEl.lastElementChild!;

	const fetchStatusLabel = fetchTriggerEl;

	let fetchInvalidationCtrl = new AbortController();
	const currentUrl = new URL(args.baseUrl, window.location.toString());
	let fetchCursor = args.cursor;
	let hasMoreResults = cellContainerEl.childElementCount > 0;

	const inputsFormEl = R.get<HTMLFormElement>('filter-inputs')!;
	const searchInputEl = inputsFormEl.querySelector<HTMLInputElement>('input[name="text"]')!;
	const contributorWrapperEl = R.get('contributor-panel')!;
	const contributorSelectEl = contributorWrapperEl.querySelector<HTMLSelectElement>('select[name="a"]')!;
	const $contributorSelectEl = $(contributorSelectEl);

	const resetAllEl = R.get('filters-reset-all')!;
	const badgeEls = inputsFormEl.getElementsByClassName('badge');

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

	$contributorSelectEl.chosen();
	attachRemoteSearchHandler(contributorWrapperEl);
	$contributorSelectEl.on('change', () => {
		currentUrl.searchParams.delete('a');
		const val = $contributorSelectEl.val();
		if(val) currentUrl.searchParams.set('a', val);

		updateStateAndRestartFetching();
	});


	inputsFormEl.addEventListener('click', e => {
		const button = (e.target as Element).closest('button');
		if(!button) return;

		if(button.id === 'filters-reset-all') {
			clearTimeout(textDebounceTimer);
			searchInputEl.value = '';

			for(const el of inputsFormEl.querySelectorAll('input[type="checkbox"]') as NodeListOf<HTMLInputElement>) {
				el.checked = false;
				if(el.value.startsWith('-')) el.value = el.value.slice(1);

				if(el.name === 'st') {
					for(const status of args.statusDefaults) {
						if(el.value === String(status)) el.checked = true;
					}
				}
			}

			contributorSelectEl.value = '';
			$contributorSelectEl.trigger('chosen:updated');

			for(const el of inputsFormEl.getElementsByClassName('filter'))
				el.classList.remove('active');

			for(const el of badgeEls)
				el.textContent = '';
			const statusBadgeEl = inputsFormEl.querySelector('#status-panel .badge.in');
			if(statusBadgeEl) statusBadgeEl.textContent = String(args.statusDefaults.length);

			for(const k of Array.from(currentUrl.searchParams.keys())) // bit ugly, but searchParams is readonly
				currentUrl.searchParams.delete(k);

			resetAllEl.hidden = true;
			updateStateAndRestartFetching();
		}
		else if(button.classList.contains('reset')) {
			e.preventDefault();
			const sectionWrapperEl = button.closest('.filter');
			if(!sectionWrapperEl) return;

			const badgeInEl = sectionWrapperEl.getElementsByClassName('badge in')[0];
			badgeInEl.textContent = '';
			sectionWrapperEl.getElementsByClassName('badge ex')[0].textContent = '';

			if(sectionWrapperEl.id === 'contributor-panel') {
				currentUrl.searchParams.delete('a');

				contributorSelectEl.value = '';
				$contributorSelectEl.trigger('chosen:updated');
			}
			else {
				for(const el of sectionWrapperEl.querySelectorAll('input[type="checkbox"]') as NodeListOf<HTMLInputElement>) {
					currentUrl.searchParams.delete(el.name);

					el.checked = false;
					if(el.value.startsWith('-')) el.value = el.value.slice(1);
				}

				if(sectionWrapperEl.id === 'status-panel') {
					for(const el of inputsFormEl.querySelectorAll('input[type="checkbox"]') as NodeListOf<HTMLInputElement>) {
						if(args.statusDefaults.includes(parseInt(el.value, 10))) {
							currentUrl.searchParams.append(el.name, el.value);
							el.checked = true;
						}
						badgeInEl.textContent = String(args.statusDefaults.length);
					}
				}
			}

			sectionWrapperEl.classList.remove('active');

			hideGlobalResetIfNothingActive();
			updateStateAndRestartFetching();
		}
		else if(button.firstElementChild?.nodeName === 'I') { // has to be the exclude button
			const optionEl = button.closest('li');
			if(!optionEl) return;

			const checkboxEl = optionEl.firstElementChild as HTMLInputElement;

			if(checkboxEl.value.startsWith('-')) {
				checkboxEl.value = checkboxEl.value.slice(1);
				checkboxEl.checked = false;
			}
			else {
				checkboxEl.value = '-'+checkboxEl.value;
				checkboxEl.checked = true;
			}
			checkboxEl.dispatchEvent(new Event('change', { bubbles: true }));

			//updateStateAndRestartFetching(); // handled in change handler
		}
	});

	inputsFormEl.addEventListener('change', e => {
		const target = e.target as HTMLInputElement;
		if(target.type !== 'checkbox') return;

		const sectionWrapperEl = target.closest('.filter')!;
		const badgeInEl = sectionWrapperEl.getElementsByClassName('badge in')[0];
		const badgeExEl = sectionWrapperEl.getElementsByClassName('badge ex')[0];

		const isExclude = target.value.startsWith('-');
		const inverseValue = isExclude ? target.value.slice(1) : '-'+target.value;

		if(target.checked) {
			currentUrl.searchParams.delete(target.name, inverseValue);
			currentUrl.searchParams.append(target.name, target.value);

			updateBadges(badgeInEl, badgeExEl, target.name);

			sectionWrapperEl.classList.add('active');
			resetAllEl.hidden = false;
		}
		else {
			currentUrl.searchParams.delete(target.name, inverseValue);
			currentUrl.searchParams.delete(target.name, target.value);

			//NOTE(Rennorb): unchecking always sets to the include value
			updateBadges(badgeInEl, badgeExEl, target.name);

			if(!(badgeExEl.textContent || badgeInEl.textContent)) {
				sectionWrapperEl.classList.remove('active');
			}

			hideGlobalResetIfNothingActive();
		}


		updateStateAndRestartFetching();
	})

	window.addEventListener('popstate', () => {
		const search = new URLSearchParams(location.search);

		for(const k of Array.from(currentUrl.searchParams.keys())) // bit ugly, but searchParams is readonly
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

	function updateBadges(badgeInEl : Element, badgeExEl : Element, paramName : string) : void
	{
		let cIn = 0, cEx = 0;
		const valuesWithThisName = currentUrl.searchParams.getAll(paramName);
		for(const v of valuesWithThisName)
			if(v.startsWith('-'))
				cEx++;
		cIn = valuesWithThisName.length - cEx;

		badgeInEl.textContent = cIn ? String(cIn) : '';
		badgeExEl.textContent = cEx ? String(cEx) : '';
	}

	function hideGlobalResetIfNothingActive() : void
	{
		let anyActive = !!searchInputEl.value;
		anyActive = anyActive || !!contributorSelectEl.value;
		anyActive = anyActive || Array.prototype.some.call(badgeEls, (el : HTMLElement) => el.textContent);
		if(!anyActive) resetAllEl.hidden = true;
	}

	function updateStateAndRestartFetching(pushHistory : boolean = true) : void
	{
		fetchInvalidationCtrl.abort();
		fetchInvalidationCtrl = new AbortController();

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
	
	let isFetching = false;
	/** @returns true if more should be fetched, false if done. */
	async function fetchAndAppendNextPage(signal : AbortSignal) : Promise<boolean>
	{
		if(cellContainerEl.childElementCount === 0) {
			fetchStatusLabel.textContent = 'Loading more…';
		}

		let fetchUrl = new URL(currentUrl);
		fetchUrl.searchParams.append('paging', '1');
		for(const [k, v] of new URLSearchParams(fetchCursor))
			fetchUrl.searchParams.append(k, v);

		const response = await fetch(fetchUrl, { signal });

		if(!response.ok) {
			if(signal.aborted) return true;

			R.addMessage(MSG_CLASS_ERROR, 'Failed to fetch more results.', false);
			hasMoreResults = false;
			return false;
		}

		let html = await response.text();

		fetchCursor = response.headers.get('X-Fetch-Cursor') || '';
		hasMoreResults = !!fetchCursor;

		if(html = html.trim()) {
			cellContainerEl.append(document.createRange().createContextualFragment(html));
		}
		else {
			if(cellContainerEl.childElementCount === 0 && !hasMoreResults) {
				fetchStatusLabel.textContent = 'No mods found :(';
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
				await fetchAndAppendNextPage(fetchInvalidationCtrl.signal);

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
			fetchAndAppendNextPage(fetchInvalidationCtrl.signal)
				.finally(() => {
					loadMoreButton.disabled = false;
					isFetching = false;
				});
		});

		fetchTriggerEl.replaceChildren(loadMoreButton);
	}
}
