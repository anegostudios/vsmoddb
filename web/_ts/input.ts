function attachRemoteSearchHandler(scopeEl : HTMLElement) : void
{
	let waitTimeout : number|null = null, lastWaitTimeout : number|null = null;

	const input = scopeEl.getElementsByClassName('chosen-search-input')[0] as HTMLInputElement;
	const select = scopeEl.getElementsByTagName('select')[0];

	const urlTemplate = select.dataset.url;
	if(!urlTemplate) {
		console.warn("attachRemoteSearchHandler called on an element who's select does not have a url in its dataset.");
		return
	}
	const ignoreId = select.dataset.ignoreId;

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

				const selectedIds = $(select).val();
				select.replaceChildren(...Array.from(select.querySelectorAll('option:checked')));

				for(const [id, name] of Object.entries(authors as Object)) {
					if(selectedIds != null && selectedIds.includes(id))  continue;
					if(id === ignoreId) continue;

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

function attachTagVoteButtons(tagsContainerEl : HTMLElement, addTagModalEl : HTMLDialogElement) : void
{
	tagsContainerEl.addEventListener('click', e => {
		const buttonEl = e.target as Element;
		if(!buttonEl || !buttonEl.classList) return;

		const tagEl = buttonEl.parentElement!;
		const tagId = tagEl.dataset.tagid;
		if(!tagId) return;

		const oldValue = tagEl.dataset.vote;
		let newValue : number;
		if(buttonEl.classList.contains('add')) {
			newValue = tagEl.dataset.vote === '1' ? 0 : 1;
		}
		else if(buttonEl.classList.contains('rem')) {
			newValue = tagEl.dataset.vote === '-1' ? 0 : -1;
		}
		else {
			return;
		}

		tagEl.dataset.vote = String(newValue);
		const xhr = $.ajax(`/api/v2/mods/${modId}/tags/${tagId}/vote`, { method: 'PUT', data: { at: actiontoken, vote: newValue } }) as jqXHR;
		R.attachDefaultFailHandler(xhr, "Failed to vote")
			.fail(() => tagEl.dataset.vote = oldValue); // Reset value in case of error.
	})

	const inputWrapEl = document.getElementById('tag-input-wrap')!;
	const input = inputWrapEl.getElementsByTagName('input')[0];
	const optionsWrapEl = inputWrapEl.lastElementChild!;

	let waitTimeout : number|null = null, lastWaitTimeout : number|null = null;

	input.addEventListener('keydown', e => {
		if(waitTimeout !== null)  clearTimeout(waitTimeout);

		lastWaitTimeout = waitTimeout;
		waitTimeout = setTimeout(() => {
			const inputText = input.value;
			const search = inputText.slice(inputText.lastIndexOf(',') + 1).trim();
			if(!search) {
				waitTimeout = null;
				return;
			}

			const timeoutRef = lastWaitTimeout;
			$.get('/api/v2/tags/by-name/'+search, (authors : Object) => {
				if(lastWaitTimeout !== timeoutRef)  return;

				if(!authors) {
					waitTimeout = null;
					return;
				}

				optionsWrapEl.replaceChildren();
				for(const [_, name] of Object.entries(authors as Object)) {
					const opt = R.make('div.tag-option', name); // Don't even bother with the id, we need to handle the name case and deduplication anyways.
					optionsWrapEl.append(opt);
				}

				waitTimeout = null;
			});

		}, 500);
	});

	//TODO(Rennorb) @completeness: keyboard nav

	const addTagToInput = (tagName : string) => {
		const inputText = input.value;
		let lastCommaIndex = inputText.lastIndexOf(',');
		if(lastCommaIndex !== -1 && inputText.length > lastCommaIndex && inputText[lastCommaIndex + 1] === ' ') lastCommaIndex++; // go to after the space in the ', ' separator.
		const previousTags = inputText.slice(0, lastCommaIndex + 1); // removes everything after the last coma (the currently being typed portion)
		input.value = previousTags + tagName + ', ';
	}

	optionsWrapEl.addEventListener('click', e => {
		const targetEl = e.target as Element;
		if(!targetEl || !targetEl.classList || !targetEl.classList.contains('tag-option')) return;

		addTagToInput(targetEl.textContent!)
		optionsWrapEl.replaceChildren();
		input.focus(); // put the focus back after an option has been clicked
	});

	attachDialogSendHandler(addTagModalEl, (form, data) => {
		const newTags = data.get('newTags') as string|null;
		if(!newTags) {
			R.markAsErrorElement(input);
			return false;
		}

		data.delete('newTags');
		for(let tag of newTags.split(',')) {
			tag = tag.trim();
			if(tag) data.append('tags[]', tag);
		}

		return true;
	}, (jqXHR) => {
		R.attachDefaultFailHandler(jqXHR, "Failed to add tag")
			//NOTE(Rennorb): No optimistic prediction here for now, rewinding that would get very ugly very quickly.
			.done((response) => {
				addTagModalEl.getElementsByTagName('button')[0].disabled = false;
				input.value = '';

				let insertPoint = tagsContainerEl.lastElementChild!;
				if(insertPoint.previousElementSibling?.nodeName === "LABEL") insertPoint = insertPoint.previousElementSibling!; // skip the 'more tags' label if it exists
				while(insertPoint.previousElementSibling && insertPoint.previousElementSibling.classList.contains('downvoted'))
					insertPoint = insertPoint.previousElementSibling; // move to before the downvoted tags
				
				for(const [tagId, tag] of Object.entries<any>(response)) {
					// If this tag already exists only update our vote for it:
					const existingTagEl = tagsContainerEl.querySelector<HTMLElement>(`.tag[data-tagid="${tagId}"]`);
					if(existingTagEl) {
						existingTagEl.dataset.vote = "1";
						continue;
					}

					// Ok, this is a tag that is new (to us). Add it clientside:
					const linkEl = R.make<HTMLAnchorElement>('a', tag.name);
					linkEl.href = '/list/mod?tg='+tagId;

					const tagEl = R.make('span.tag', linkEl, R.make('span.add'), R.make('span.rem'));
					tagEl.style.backgroundColor = tag.color;
					tagEl.dataset.tagid = tagId;
					tagEl.dataset.vote = "1"; // always our vote

					tagsContainerEl.insertBefore(tagEl, insertPoint);
				}
			});
		addTagModalEl.close();
	});
}

function startSubmissionSpinner(button : HTMLButtonElement)
{
	button.textContent = 'Submitting..';
	let dots = 0;
	const buttonInterval = setInterval(() => {
		button.textContent = 'Submitting....'.slice(0, 12 + dots);
		dots = (dots + 1) % 3;
	}, 300);
	button.dataset.interval = String(buttonInterval);
}

function stopSubmissionSpinner(button : HTMLButtonElement, ...content : (Node|string)[])
{
	clearInterval(parseInt(button.dataset.interval!))
	button.replaceChildren(...content)
}
