function attachUserSearchHandler(scopeEl)
{
	let waitTimeout = null, lastWaitTimeout = null;

	const input = scopeEl.getElementsByClassName('chosen-search-input')[0];
	const select = scopeEl.getElementsByTagName('SELECT')[0];
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

			const url = select.dataset.url.replace('{name}', search);
			$.get(url, (authors) => {
				if(lastWaitTimeout !== timeoutRef)  return;

				if(!authors) {
					waitTimeout = null;
					return;
				}

				const currentUserIds = $(select).val();
				select.replaceChildren(...Array.from(select.querySelectorAll('option:checked')));

				for(const [id, name] of Object.entries(authors)) {
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
