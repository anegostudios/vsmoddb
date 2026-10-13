const SCROLL_ROOT_MARGIN_PX = 300;

class ModListPagination {
	private readonly grid: HTMLDivElement;
	private readonly trigger: HTMLDivElement;
	private controller = new AbortController();
	private isFetching = false;
	private cursor = '';
	private readonly observer = 'IntersectionObserver' in window
		? new IntersectionObserver(
			entries => this.onIntersect(entries),
			{ rootMargin: `0px 0px ${SCROLL_ROOT_MARGIN_PX}px 0px` }
		)
		: null;

	constructor(results: HTMLElement) {
		this.grid = results.querySelector<HTMLDivElement>('.mods')!;
		this.trigger = results.querySelector<HTMLDivElement>('.scroll-trigger')!;

		this.attachTrigger(results.dataset.fetchCursor || "");
	}

	/** Cancels the last fetch and hide the trigger */
	reset(): AbortSignal {
		this.controller.abort(); 
		this.controller = new AbortController();

		this.isFetching = false;
		this.attachTrigger('');

		return this.controller.signal;
	}

	/** If there is a next page, observe the trigger */
	attachTrigger(cursor: string) {
		this.cursor = cursor;

		const active = Boolean(cursor) && this.grid.children.length > 0;
		this.trigger.hidden = !active;

		this.observer?.disconnect();
		if (active) this.observer?.observe(this.trigger);
	}

	private onIntersect(entries: IntersectionObserverEntry[]) {
		if (!this.isFetching && entries.some(entry => entry.isIntersecting)) {
			this.fetchMore();
		}
	}

	/** Fetches pages until the trigger is pushed out of the viewport. */
	private async fetchMore(maxPages = 20) {
		const signal = this.controller.signal;
		this.isFetching = true;

		try {
			for (let i = 0; i < maxPages && this.cursor; i++) {
				const query = location.search.slice(1);
				const response = await fetch(
					`/list/mod?${query ? `${query}&` : ''}paging=1${this.cursor}`,
					{ signal }
				);

				if (!response.ok) throw new Error(String(response.status));

				this.grid.append(document.createRange().createContextualFragment(await response.text()));
				this.attachTrigger(response.headers.get('X-Fetch-Cursor') || '');

				// Stop once the viewport is filled
				if (this.trigger.getBoundingClientRect().top >= innerHeight + SCROLL_ROOT_MARGIN_PX) break;
			}
		}
		catch {
			if (!signal.aborted) {
				R.addMessage(MSG_CLASS_ERROR, 'Failed to fetch more mods.', true);
			}
		}
		finally {
			if (!signal.aborted) this.isFetching = false;
		}
	}
}
