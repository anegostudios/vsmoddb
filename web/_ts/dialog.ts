function attachDialogSendHandler(
	dialog : HTMLDialogElement,
	validationCallback : (form : HTMLFormElement, data : FormData) => boolean,
	sendCallback : (jqXHR : jqXHR) => void,
) : void
{
	const _forms = dialog.getElementsByTagName('form');
	if(_forms.length < 1) {
		console.warn("Dialog is missing a form", dialog);
		return;
	}
	const form = _forms[0];

	let button : HTMLButtonElement | undefined;
	for(const btn of dialog.getElementsByTagName('button')) {
		if(btn.formMethod !== 'dialog') {
			button = btn;
			break;
		}
	}
	if(!button) {
		console.warn("Dialog is missing a non closing (formmethod!=dialog) button", dialog);
		return;
	}

	button.addEventListener('click', () => {
		const data = new FormData(form, button);
		if(!validationCallback(form, data)) { return; }

		button.disabled = true; // disable to prevent impatiens from double sending

		const xhr = $.ajax(form.action, { method: form.dataset.method, processData: false, contentType: false, data: data }) as jqXHR;
		xhr.fail(() => button.disabled = false); // make sure to re-enable if there was a problem
		sendCallback(xhr);
	})
}
