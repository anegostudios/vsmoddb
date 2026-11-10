interface DropHandler {
	target         : HTMLElement,
	additionalData : { [k : symbol|string|number] : any },
	// You cannot access file properties here, only type and kind.
	onPrefilter    : (item : DataTransferItem) => boolean,
	onValidate     : (file : File) => boolean,
	onStart        : (e : ExtendedProgressEvent1) => any,
	onProgress     : (e : ExtendedProgressEvent2) => void,
	onComplete     : (e : ExtendedProgressEvent2) => void,
}

type ExtendedProgressEvent1 = ProgressEvent<XMLHttpRequest> & { file : File }
type ExtendedProgressEvent2 = ExtendedProgressEvent1 & { userData : any }

function attachDropHandler(args : DropHandler)
{
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
		const files = Array.from(e.dataTransfer!.files).filter(args.onValidate);

		e.preventDefault();
		if(!files.length) return;

		for(const file of files) {
			const data = new FormData();
			for(const [k, v] of Object.entries(args.additionalData))
				data.append(k, v)
			data.append('file', file);

			let userData = null;

			const xhr = new XMLHttpRequest();
			xhr.upload.addEventListener('loadstart', e => {
				(e as ExtendedProgressEvent1).file = file;
				userData = args.onStart(e as ExtendedProgressEvent1)
			});
			xhr.upload.addEventListener('progress', e => {
				(e as ExtendedProgressEvent2).file = file;
				(e as ExtendedProgressEvent2).userData = userData;
				args.onProgress(e as ExtendedProgressEvent2);
			});
			xhr.addEventListener('loadend', e => {
				(e as ExtendedProgressEvent2).file = file;
				(e as ExtendedProgressEvent2).userData = userData;
				args.onComplete(e as ExtendedProgressEvent2);
			});
			xhr.open('POST', '/api/v2/files', true);
			xhr.send(data);
		}
	});
}

type UploadResponse = {
	id               : number,
	name             : string,
	url              : string,
	thumbUrl?        : string,
	imgWidth?        : number,
	imgHeight?       : number,
	gameVersionDep?  : bigint,
	requiresReview?  : boolean,
	modParse?        : 'ok'|'error',
	modId?           : string,
	modVersion?      : bigint,
	parseMsg?        : string,

	error?           : string,
}



function attachDefaultDropHandler(maxSize : number, allowedExtensions : string[], canAttach : boolean, onUploadFinished : (response : UploadResponse, $elem : JQElement) => void)
{
	attachDropHandler({
		target: (window as any as HTMLElement),
		additionalData: {
			at: window.user.at,
		},
		onPrefilter : function(item) {
			return true;
		},
		onValidate: function(file) {
			const containerEl = R.getC('files')!;
			let attachedCount = 0; 
			for(const childEl of containerEl.children) {
				if((childEl.getElementsByClassName('trigger')[0] as HTMLInputElement).checked)
					attachedCount++;
			}

			if(file.size > maxSize) {
				R.addMessage(MSG_CLASS_ERROR, `${file.name}: File too large. Must be below ${R.formatByteSize(maxSize)}.`, true);
				return false;
			}

			const extIdx = file.name.lastIndexOf('.');
			const ext = extIdx < 0 ? '' : file.name.slice(extIdx + 1)
			if(!allowedExtensions.includes(ext)) {
				R.addMessage(MSG_CLASS_ERROR, `${file.name}: Wrong extension. Must be one of ${R.formatGrammaticallyCorrectEnumeration(allowedExtensions)}.`, true);
				return false;
			}

			return true;
		},
		onStart: function (e) {
			const file = e.file;
			const extIdx = file.name.lastIndexOf('.');
			const ext = extIdx < 0 ? '' : file.name.slice(extIdx + 1)

			const $elem = $(".file.template").clone();
			$elem.removeClass("template");

			$(".filename", $elem).text(file.name);
			$(".fi", $elem).addClass("fi-" + ext);
			$(".fi-content", $elem).html(ext);

			$(".files").append($elem);

			return $elem;
		},
		onProgress: function (e) {
			const progress = e.lengthComputable ? Math.round((e.loaded * 100) / e.total) : '???';
			$(".uploadprogress", e.userData).text(progress + "%");
		},
		onComplete: function (e) {
			const $elem = e.userData;
			let response : UploadResponse = null!;
			try { response = JSON.parse(e.target!.response) as UploadResponse; }
			catch(ex) {
				console.error(ex);

				$(".filename", $elem).html("Unable to upload file.<br>" + ex.message).addClass("text-error");
				$elem.addClass("error");

				response = { error: ex.message } as UploadResponse;
			}

			$(".uploadprogress", $elem).hide();

			if(response) {
				if(response.thumbUrl) {
					$(".fi", $elem).remove();
					$("img", $elem).attr("src", response.thumbUrl).show();
					$("a", $elem).attr("data-fancybox", 'gallery');
				}
				$("a", $elem).attr("href", response.url);

				const triggerId = 'file-tgr-'+response.id;

				$('[name="fileIds[]"]', $elem).val(response.id);
				$('.trigger', $elem).val(response.id).attr('id', triggerId);
				const $attachToggle = $('label', $elem).attr('for', triggerId);
				
				$(".uploaddate", $elem).text('Just now');
				if(response.imgWidth) {
					$(".imagesize", $elem).text(`${response.imgWidth}x${response.imgHeight}px`).after(' - ');
				}
				$(".size", $elem).text(R.formatByteSize(e.file.size));

				if(response.error || response.modParse === "error" || !canAttach) {
					$attachToggle.replaceWith('<span></span>');
				}
			}

			onUploadFinished(response, $elem);

			if(response) {
				$(".buttons", $elem).show();
			}
		},
	});
}
