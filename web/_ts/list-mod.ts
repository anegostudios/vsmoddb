const MOD_LIST_TEXT_SEARCH_DEBOUNCE = 350; //ms

enum STATUS { EMPTY = 'empty', ERROR = 'error', LOADING = 'loading' }

/** the count badges and reset buttons stay rendered when "hidden", they just get smaller, but they also need to leave the tab order. */
function setShown(el: HTMLElement | null, shown: boolean) {
	if (!el) return;

	el.hidden = !shown;
	el.setAttribute('aria-hidden', String(!shown));

	if (el instanceof HTMLButtonElement) el.tabIndex = shown ? 0 : -1;
}

// MARK: ModListFilters
class ModListFilters {
	private debounce?: ReturnType<typeof setTimeout>;
	private readonly text: HTMLInputElement;
	private readonly contributor: HTMLSelectElement;

	constructor(
		private readonly form: HTMLFormElement,
		private readonly onChange: (query: string) => void,
	) {
		this.text = form.querySelector<HTMLInputElement>('input[name="text"]')!;
		this.contributor = form.querySelector<HTMLSelectElement>('select[name="a"]')!;
	}


	/** Update the UI, push the filters to the URL and reloads the search results */
	private applyFilters() {
		clearTimeout(this.debounce);
		this.syncUI();

		const formData = new FormData(this.form);
		const searchParams = new URLSearchParams();
		for (const filterName of new Set(formData.keys())) {
			const values = formData.getAll(filterName).filter(value => value !== '');
			if (values.length) searchParams.set(filterName, values.join(','));
		}

		const query = searchParams.toString().replace(/%2C/g, ',');

		history.pushState(null, '', `/list/mod${query ? `?${query}` : ''}`);
		this.onChange(query);
	}

	private applyStatiDefaults() {
		const filterPanel = this.form.querySelector<HTMLElement>('#status-panel');
		const defaults = filterPanel?.dataset.defaults?.split(',') || [];
		filterPanel?.querySelectorAll<HTMLInputElement>('input[type="checkbox"]').forEach(input => {
			input.checked = input.dataset.role === 'in' && defaults.includes(input.value);
		});
	}

	bind() {
		this.syncUI();

		const contributorPanel = this.form.querySelector<HTMLElement>('#contributor-panel')!;
		attachRemoteSearchHandler(contributorPanel); // from input.ts
		$(this.contributor).on('change', () => this.applyFilters());

		this.text.addEventListener('input', () => {
			clearTimeout(this.debounce);
			this.debounce = setTimeout(() => this.applyFilters(), MOD_LIST_TEXT_SEARCH_DEBOUNCE);
		});

		this.form.addEventListener('click', e => {
			const button = (e.target as Element).closest('button');
			if (!button) return;

			if (button.id === 'filters-reset-all') {
				this.text.value = '';
				this.form.querySelectorAll('.filter').forEach(filterPanel => this.clearFilter(filterPanel));
				this.applyFilters();
			}
			else if (button.classList.contains('reset')) {
				e.preventDefault();
				const filterPanel = button.closest('.filter');
				if (!filterPanel) return;
				this.clearFilter(filterPanel);
				this.applyFilters();
			}
			//Include/Exclude filter
			else if (button.dataset.role) {
				const optionEl = button.closest('li');
				if (!optionEl) return;
				optionEl.querySelectorAll<HTMLInputElement>('input').forEach(input => {
					input.checked = input.dataset.role === button.dataset.role && !input.checked;
				});
				this.applyFilters();
			}
		});

		this.form.addEventListener('submit', e => {
			e.preventDefault();
			this.applyFilters();
		});

		// In-panel search, client side only
		this.form.querySelectorAll<HTMLInputElement>('input[data-filter-for]').forEach(input => {
			const options = this.form.querySelectorAll<HTMLElement>(`#${input.dataset.filterFor} > li`);

			input.addEventListener('input', () => {
				const search = input.value.trim().toLowerCase();
				options.forEach(li =>
					li.hidden = !li.dataset.text!.toLowerCase().includes(search)
				);
			});

			input.addEventListener('keydown', e => {
				if (e.key === 'Enter') e.preventDefault();
			});
		});

		const tagList = this.form.querySelector<HTMLUListElement>('#tag-list')!;
		const expand = this.form.querySelector<HTMLButtonElement>('#tag-panel .expand')!;

		expand.addEventListener('click', () => {
			const expanded = tagList.classList.toggle('expanded');
			expand.setAttribute('aria-expanded', String(expanded));
			expand.setAttribute('aria-label', expanded ? 'Collapse tags list' : 'Expand tags list');
			$.cookie('modlist_tags_expanded', expanded ? '1' : '0', { expires: 365, path: '/' });
		});
	}

	private clearFilter(filterPanel: Element) {
		filterPanel.querySelectorAll<HTMLInputElement>('input[type="checkbox"]').forEach(input => input.checked = false);
		if (filterPanel.id === 'status-panel') this.applyStatiDefaults();
		if (filterPanel.contains(this.contributor)) this.setContributor('');
	}

	private setContributor(value: string) {
		this.contributor.value = value;
		$(this.contributor).trigger('chosen:updated');
	}

	/** Read the checkboxes values (or other input) to sync the UI style to their value */
	private syncUI() {
		this.form.querySelectorAll<HTMLLIElement>('.list > li').forEach(option => {
			option.classList.toggle('in', !!option.querySelector(`input[data-role="in"]:checked`));
			option.classList.toggle('ex', !!option.querySelector(`input[data-role="ex"]:checked`));
		});

		this.form.querySelectorAll<HTMLDetailsElement>('.filter').forEach(filterPanel => {
			const counts = {
				in: filterPanel.querySelectorAll('input[data-role="in"]:checked').length,
				ex: filterPanel.querySelectorAll('input[data-role="ex"]:checked').length,
			};

			if (filterPanel.id === 'contributor-panel') counts.in = this.contributor.value ? 1 : 0;

			for (const role of ['in', 'ex']) {
				const badge = filterPanel.querySelector<HTMLElement>(`.badge[data-${role}]`);
				if (badge) badge.textContent = String(counts[role]);
				setShown(badge, counts[role] > 0);
			}

			const active = (counts.in + counts.ex) > 0;
			filterPanel.classList.toggle('active', active);

			setShown(filterPanel.querySelector('.badges'), active);
			setShown(filterPanel.querySelector('.reset'), active);
		});

		setShown(this.form.querySelector('#filters-reset-all'), !!this.text.value.trim() || !!this.form.querySelector('.filter.active'));
	}

	/** on Back/forward navigation, restore the inputs from the URL */
	syncFromUrl(params: URLSearchParams) {
		this.text.value = params.get('text') || '';

		this.form.querySelectorAll<HTMLInputElement>('input[type="checkbox"]').forEach(input => {
			const param = params.get(input.name) || '';
			input.checked = param.split(',').includes(input.value);
		});

		this.setContributor(params.get('a') || '');
		if (!params.has('st')) this.applyStatiDefaults();

		this.syncUI();
	}
}

// MARK: ModListPage (main)
class ModListPage {
	private readonly results: HTMLElement;
	private readonly pagination: ModListPagination;
	private readonly filters: ModListFilters;

	private constructor(private readonly layout: HTMLElement) {
		this.results = layout.querySelector<HTMLElement>('.results')!;
		this.pagination = new ModListPagination(this.results);
		this.filters = new ModListFilters(layout.querySelector('form')!, query => this.load(query));
	}

	static init(layout: HTMLElement | null) {
		if (!layout || layout.dataset.init) return;
		layout.dataset.init = '1';

		const page = new ModListPage(layout);
		page.filters.bind();
		page.bindDrawer();

		window.addEventListener('popstate', () => {
			page.filters.syncFromUrl(new URLSearchParams(location.search));
			page.load(location.search.slice(1));
		});
	}

	/** Reloads the results from page 1 for the given query */
	private async load(query: string) {
		const params = new URLSearchParams(query);

		const warnMissingDataEl = document.getElementById('warn-missing-data');
		if (warnMissingDataEl) warnMissingDataEl.hidden = !params.has('t');

		params.delete('sortby');
		params.delete('sortdir');

		this.layout.querySelectorAll<HTMLAnchorElement>('.sort a[data-sortby]').forEach(sortLink => {
			const linkParams = new URLSearchParams(params);
			linkParams.set('sortby', sortLink.dataset.sortby!);
			linkParams.set('sortdir', sortLink.dataset.sortdir!);
			sortLink.href = '/list/mod?' + linkParams.toString().replace(/%2C/g, ',');
		});

		const grid = this.results.querySelector<HTMLDivElement>('.mods')!;

		// Abort any ongoing fetch
		const signal = this.pagination.reset();
		this.showStatus(STATUS.LOADING);
		grid.replaceChildren();

		try {
			const response = await fetch(`/list/mod?${query ? `${query}&` : ''}paging=1`, { signal });
			if (!response.ok) throw new Error(String(response.status));

			const html = await response.text();

			this.showStatus(html.trim() ? undefined : STATUS.EMPTY);

			grid.append(document.createRange().createContextualFragment(html));
			this.pagination.attachTrigger(response.headers.get('X-Fetch-Cursor') ?? '');
		}
		catch {
			if (!signal.aborted) this.showStatus(STATUS.ERROR);
		}
	}

	/** No status = hide all status messages */
	private showStatus(status?: STATUS) {
		this.results.querySelectorAll<HTMLElement>('[data-list-status]').forEach(el => el.hidden = el.dataset.listStatus !== status);
	}

	/** Mobile version of the filters */
	private bindDrawer() {
		const button = this.layout.querySelector<HTMLButtonElement>('#open-filters')!;
		const backdrop = this.layout.querySelector('#backdrop')!;

		const setOpen = (open: boolean) => {
			this.layout.classList.toggle('open', open);
			button.setAttribute('aria-expanded', String(open));
		};

		button.addEventListener('click', () => setOpen(true));
		backdrop.addEventListener('click', () => setOpen(false));

		matchMedia('(max-width: 768px)').addEventListener('change', e => {
			// Close the drawer if the window fits the desktop layout
			if (!e.matches) setOpen(false);
		});
	}
}
