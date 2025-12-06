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
