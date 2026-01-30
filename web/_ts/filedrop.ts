interface DropHandler {
	target         : HTMLElement,
	destUrl        : string,
	additionalData : Map<string, any>,
	// You cannot access file properties here, only type and kind.
	onPrefilter    : (item : DataTransferItem) => boolean,
	onValidate     : (file : File) => boolean,
	onStart        : (e : any) => any,
	onProgress     : (e : any | { userData : any }) => void,
	onComplete     : (e : any | { userData : any }) => void,
}

function attachDropHandler(args : DropHandler)
{
	// const isAllowedByExt = (file : File) => {
	// 	const ext = file.name.slice(file.name.lastIndexOf('.') + 1);
	// 	return args.allowedExtensions.includes(ext);
	// };

	args.target.addEventListener('dragover', e => {
		let hasFiles = false;

		if(R.isSafari()) {
			//NOTE(Rennorb): the dataTransfer is empty for Safari, so no pre-filter for us.
			if(e.dataTransfer!.types.includes('Files')) { // https://html.spec.whatwg.org/multipage/dnd.html#dom-datatransfer-types-dev   section 2.2
				e.preventDefault()
				e.dataTransfer!.dropEffect = 'copy';
			}
			else {
				e.dataTransfer!.dropEffect = 'none';
			}
			return
		}
		
		for(const item of e.dataTransfer!.items) {
			if(item.kind === 'file') {
				e.preventDefault();
				hasFiles = true;

				if(args.onPrefilter(item)) {
					e.dataTransfer!.dropEffect = 'copy';
					return
				}
			}
		}
		
		if(hasFiles) e.dataTransfer!.dropEffect = 'none';
	});

	args.target.addEventListener('drop', e => {
		debugger;
		const files = Array.from(e.dataTransfer!.files).filter(args.onValidate);
		if(!files.length) return;

		e.preventDefault();

		for(const file of files) {
			const data = new FormData();
			for(const [k, v] of Object.entries(args.additionalData))
				data.append(k, v)
			data.append('file', file);

			let userData = null;

			const xhr = new XMLHttpRequest();
			xhr.upload.addEventListener('loadstart', e => {
				(e as any).file = file;
				userData = args.onStart(e)
			});
			xhr.upload.addEventListener('progress', e => {
				(e as any).file = file;
				(e as any).userData = userData;
				args.onProgress(e);
			});
			xhr.addEventListener('loadend', e => {
				(e as any).file = file;
				(e as any).userData = userData;
				args.onComplete(e);
			});
			xhr.open('POST', args.destUrl, true);
			xhr.send(data);
		}
	});
}