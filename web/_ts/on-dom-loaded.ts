R.onDOMLoaded(function() {
	// Turn all selects that are not within a template into jquery-chosen selects:
	$("select").each(function() {
		if ($(this).parents(".template").length == 0) {
			var ds = $(this).attr("noSearch") == 'noSearch';
			$(this).chosen({ placeholder_text_multiple: " ", disable_search:ds, });
		}
	});
	
	// Attach "are you sure you want to leave this page" on location change prompts to the primary form on the page:
	$('form[name=form1]').areYouSure();

	for(const btn of document.querySelectorAll('[data-opens-dialog]')) {
		btn.addEventListener('click', e => {
			const id = (e.currentTarget as HTMLElement).dataset.opensDialog;
			let target;
			if(!id || !(target = R.get(id))) {
				console.warn("Failed to find dialog to open", id);
				return;
			}

			(target as HTMLDialogElement).showModal();
		})
	}

	attachSpoilerToggle($('.spoiler-toggle'));
});
