var R = {
get : <T extends HTMLElement = HTMLElement>(id : string) : T|null => document.getElementById(id) as T,
getC : <T extends HTMLElement = HTMLElement>(selector : string) : T|null => document.getElementsByClassName(selector)[0] as T,
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
formatGrammaticallyCorrectEnumeration : function(enumeration : string[], lastJoiner = 'and') : string {
	switch(enumeration.length) {
		case 0: return '';
		case 1: return enumeration[0];
		case 2: return `${enumeration[0]} ${lastJoiner} ${enumeration[1]}`;
		default: return `${enumeration.slice(0, -1).join(', ')} ${lastJoiner} ${enumeration[enumeration.length - 1]}`;
	}
},
compileSemanticVersion : function(versionStr : string) : bigint|false {
	const matches = /^(\d+)\.(\d+)\.(\d+)(?:-(dev|pre|rc)\.(\d+))?$/.exec(versionStr); // @perf
	if(!matches) return false;
	let compliedSuffix = 0xffffn; // non pre-release sorts after pre-release
	if(matches[5]) {
		switch(matches[4]) {
			case 'dev': compliedSuffix =  4n << 12n; break;
			case 'pre': compliedSuffix =  8n << 12n; break;
			case 'rc' : compliedSuffix = 12n << 12n; break;
			default: return false;
		}
		compliedSuffix |= BigInt(matches[5]) & 0x0fffn;
	}
	return ((BigInt(matches[1]) & 0xffffn) << 48n)
			| ((BigInt(matches[2]) & 0xffffn) << 32n)
			| ((BigInt(matches[3]) & 0xffffn) << 16n)
			| compliedSuffix;
},
formatSemanticVersion : function(versionNum : bigint) : string {
	const major   = (versionNum >> 48n /*& 0xffff*/);
	const minor   = (versionNum >> 32n & 0xffffn);
	const release = (versionNum >> 16n & 0xffffn);
	let preRelease : bigint|string = versionNum & 0xffffn;
	switch (Number((preRelease & 0xf000n) >> 12n)) {
		case  4: preRelease = '-dev.'+(preRelease & 0x0fffn); break;
		case  8: preRelease = '-pre.'+(preRelease & 0x0fffn); break;
		case 12: preRelease =  '-rc.'+(preRelease & 0x0fffn); break;
		default: preRelease = '';
	}
	return `${major}.${minor}.${release}${preRelease}`;
},
onDOMLoaded : function(callback : () => void) {
	if(document.readyState !== 'loading') callback();
	else document.addEventListener('DOMContentLoaded', callback);
},
msgContainer : null as unknown as HTMLElement,
addMessage : function(clazz : string, html : string, escapeMessage : boolean = true) {
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
attachDefaultFailHandler : function(jqXHR : jqXHR, errorPrefix : string = 'Request failed', errorHandler? : (e : string) => boolean) : jqXHR {
	return jqXHR.fail(jqXHR => {
		let d;
		try{ d = JSON.parse(jqXHR.responseText); }
		catch {
			R.addMessage(MSG_CLASS_ERROR, 'Failed to parse response.', false);
			d = { error: jqXHR.responseText };
		}
		const message = errorPrefix + (d.error ? (': '+d.error) : '.');
		if(!errorHandler || !errorHandler(message))
			R.addMessage(MSG_CLASS_ERROR, message, true);
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
},
_isSafari : undefined as unknown as boolean,
isSafari : function() : boolean {
	if(R._isSafari === undefined) {
		R._isSafari = Boolean(navigator.vendor) && navigator.vendor.includes('Apple');
	}
	return R._isSafari;
},
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


function temporaryHighlight(el : HTMLElement)
{
	el.classList.add('highlight');
	setTimeout(() => el.classList.remove('highlight'), 2000); // remove so sorting doesn't re-trigger the highlight.
}
