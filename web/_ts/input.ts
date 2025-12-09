function attachUserSearchHandler(scopeEl : HTMLElement) : void
{
	let waitTimeout : number|null = null, lastWaitTimeout : number|null = null;

	const input = scopeEl.getElementsByClassName('chosen-search-input')[0] as HTMLInputElement;
	const select = scopeEl.getElementsByTagName('select')[0];

	const urlTemplate = select.dataset.url;
	if(!urlTemplate) {
		console.warn("attachUserSearchHandler called on an element who's select does not have a url in its dataset.");
		return
	}

	input.addEventListener('keydown', e => {
		if(waitTimeout !== null)  clearTimeout(waitTimeout);

		lastWaitTimeout = waitTimeout;
		waitTimeout = setTimeout(() => {
			const search = input.value;
			if(!search) {
				waitTimeout = null;
				return;
			}

			const timeoutRef = lastWaitTimeout;
			const url = urlTemplate.replace('{name}', search);
			$.get(url, (authors : Object) => {
				if(lastWaitTimeout !== timeoutRef)  return;

				if(!authors) {
					waitTimeout = null;
					return;
				}

				const currentUserIds = $(select).val();
				select.replaceChildren(...Array.from(select.querySelectorAll('option:checked')));

				for(const [id, name] of Object.entries(authors as Object)) {
					if(currentUserIds != null && currentUserIds.includes(id))  continue;

					const opt = document.createElement('option');
					opt.value = id; opt.textContent = name;
					select.append(opt);
				}

				const oldWidth = getComputedStyle(input).width;
				$(select).trigger('chosen:updated');
				input.value = search;
				// Choses resets this values in the update call. We manually modify the search, so we need to set the width as well.
				input.style.width = oldWidth;
				waitTimeout = null;
			});

		}, 500);
	})
}

function attachVersionSelectorHandlers(versionSelectorEl : HTMLDetailsElement) : void
{
	const countLabelEl = versionSelectorEl.getElementsByClassName('count-label')[0];
	const updateSelectedCounter = () => {
		const count = versionSelectorEl.querySelectorAll('input:checked').length;
		countLabelEl.textContent = `${count} Version${count !== 1 ? 's' : ''} Selected`;
	}
	versionSelectorEl.addEventListener('click', e => {
		let t = e.target as Element | null
		if(!t || !t.nodeName) return;

		if(t.nodeName === 'INPUT') {
			updateSelectedCounter();
			return;
		}

		// <div><span>subversion</></>
		// <div>container for all the inputs of that subversion</>
		if(t.nodeName !== 'DIV' || t.firstChild?.nodeName !== 'SPAN') {
			if(t.nodeName !== 'SPAN' || t.parentElement?.nodeName !== 'DIV') return;

			t = t.parentElement;
		}

		e.stopPropagation()

		const ns = t.nextElementSibling!;
		const inputs = ns.nodeName === 'INPUT' ? [ns as HTMLInputElement] : ns.getElementsByTagName('input');

		let toggleOn = false;
		for(const el of inputs) {
			if(!el.checked) {
				// As long as there is one unchecked input in the subset check them all first.
				toggleOn = true;
				break;
			}
		}
		for(const el of inputs) {
			el.checked = toggleOn;
		}
		
		updateSelectedCounter();
	})
}
