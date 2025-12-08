var R = {
get : <T extends HTMLElement = HTMLElement>(id : string) : T|null => document.getElementById(id) as T,
getQ : <T extends HTMLElement = HTMLElement>(selector : string) : T|null => document.querySelector<T>(selector),
make : function<T extends HTMLElement = HTMLElement>(spec : string, ...children : (string|Node)[]) : T {
	const [nodeName, ...classes] = spec.split('.');
	const el = document.createElement(nodeName);
	if(classes) el.classList.add(...classes);
	if(children) el.append(...children);
	return el as T;
},
formatByteSize : function(size : number) : string {
	if(size > 1073741824) return (size / 1073741824).toFixed(2) + ' GB';
	if(size > 1048576) return (size / 1048576).toFixed(2) + ' MB';
	if(size > 1024) return (size / 1024).toFixed(2) + ' KB';
	return size + ' B';
},
onDOMLoaded : function(callback : () => void) {
	if(document.readyState !== 'loading') callback();
	else document.addEventListener('DOMContentLoaded', callback);
},
msgContainer : null as unknown as HTMLElement,
addMessage : function(clazz : string, html : string, escapeMessage? : boolean) {
	escapeMessage = escapeMessage || true;
	const msgEl = R.make('div.'+clazz);
	if(escapeMessage) {
		msgEl.textContent = html;
		msgEl.append(R.make('span.dismiss'))
	}
	else {
		msgEl.innerHTML = html+'<span class="dismiss"></span>';
	}
	R.msgContainer.append(msgEl);
},
markAsErrorElement : function(el : HTMLElement) {
	el.classList.add('invalid');
	setTimeout(() => el.classList.remove('invalid'), 500);
},
attachDefaultFailHandler : function(jqXHR : jqXHR, errorPrefix : string = 'Request failed') : jqXHR {
	return jqXHR.fail(jqXHR => {
		let d;
		try{ d = JSON.parse(jqXHR.responseText); }
		catch {
			R.addMessage(MSG_CLASS_ERROR, 'Failed to parse response.', false);
			d = { reason: jqXHR.responseText };
		}
		R.addMessage(MSG_CLASS_ERROR, errorPrefix + (d.reason ? (': '+d.reason) : '.'), true)
	});
},
trimLeadingEmptyLines : function(element : Node) : void {
	let firstChild = element.firstChild;
	while(firstChild) {
		if(firstChild.nodeName === 'BR') {
			firstChild.remove();
		}
		else if(["P", "DIV"].includes(firstChild.nodeName) && !firstChild.textContent) {
			firstChild.remove();
		}
		else {
			element = firstChild;
		}
		firstChild = element.firstChild;
	}
},
trimTrailingEmptyLines : function(element : Node) : void {
	let lastChild = element.lastChild;
	while(lastChild) {
		if(lastChild.nodeName === 'BR') {
			lastChild.remove();
		}
		else if(["P", "DIV"].includes(lastChild.nodeName) && !lastChild.textContent) {
			lastChild.remove();
		}
		else {
			element = lastChild;
		}
		lastChild = element.lastChild;
	}
}
};

//NOTE(Rennorb) This script is included after the body, so this always already exists to be grabbed.
R.msgContainer = R.get('message-container')!;
R.msgContainer.addEventListener('click', e => {
	let t = e.target as HTMLElement;
	if(!t || !t.classList.contains('dismiss')) return;
	t = t.parentElement!;
	$(t).slideUp(400, () => t.remove());
})

const MSG_CLASS_OK = 'bg-success.text-success';
const MSG_CLASS_WARN = 'bg-warning';
const MSG_CLASS_ERROR = 'bg-error.text-error';


