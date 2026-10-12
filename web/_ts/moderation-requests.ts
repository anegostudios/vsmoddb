function attachMRMessageSendHandlers(form : HTMLFormElement)
{
	createEditor(form.getElementsByTagName('textarea')[0], tinymceSettingsReport);

	const buttons = form.getElementsByTagName('button');

	form.addEventListener('submit', e => {
		e.preventDefault();

		const data = new FormData(e.target as HTMLFormElement, e.submitter)
		const resolution = (e.submitter as HTMLButtonElement|null)?.value;
		if(resolution !== 'spam') {
			if(!data.get('message')) {
				R.markAsErrorElement(form.getElementsByClassName('tox-tinymce')[0] as HTMLElement);
				return;
			}
		}

		for(const el of buttons) el.disabled = true;

		const xhr = $.ajax(form.action, { method: form.method, processData: false, contentType: false, data: data }) as jqXHR;
		R.attachDefaultFailHandler(xhr, 'Failed to send message', (err) => {
			for(const el of buttons) el.disabled = false;
			return false;
		})
		xhr.done(() => window.location.reload());
	});
}