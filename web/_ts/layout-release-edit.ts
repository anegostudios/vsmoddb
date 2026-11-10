function release_attachFileInteractions(uploadSizeLimit : number, allowedExtensions : string[], validateFileUploads : boolean, canEditRelease : boolean) : void
{
	let didShowMessage = false;

	const containerEl = R.getC('files')!;
	const submitButtonEls = document.getElementsByClassName('btn-submit');
	const modIdInputEl = R.getQ<HTMLInputElement>('input[name="modidstr"]')!;
	const modVersionInputEl = R.getQ<HTMLInputElement>('input[name="modversion"]')!;

	function _getOtherAttachedTriggerElement() : HTMLInputElement|null
	{
		return containerEl.querySelector<HTMLInputElement>('[name="attachedFileIds[]"]:checked');
	}

	function _changeAttachment(response : UploadResponse, nowAttached : boolean)
	{
		if(nowAttached) {
			if(validateFileUploads) {
				modIdInputEl.value = response.modId || '';
				modVersionInputEl.value = response.modVersion ? R.formatSemanticVersion(response.modVersion) : '';
				if(response.gameVersionDep) {
					const versionInputs = document.querySelectorAll<HTMLInputElement>('.version-selector input[name="cgvs[]"]');
					const labelEl = document.getElementsByClassName('count-label')[0];
					let c = 0;
					for(const boxEl of versionInputs) {
						boxEl.checked = R.compileSemanticVersion(boxEl.value) as bigint >= response.gameVersionDep;
						if(boxEl.checked) c++;
					}
					labelEl.textContent = `${c} Version${c !== 1 ? 's' : ''} Selected`;
					R.addMessage(MSG_CLASS_OK, `Automatically selected ${c} compatible game version${c !== 1 ? 's' : ''}.`, false);
				}

				const oldWarningMessage = R.getC('msg-require-review');
				if(response.requiresReview) {
					if(!oldWarningMessage) {
						R.addMessage(MSG_CLASS_WARN+'.msg-require-review', 'This mod uses potentially dangerous features of the dotnet runtime, and will require manual review.<br>A request for review will be submitted for you automatically after completing the release.', false);
					}
					submitButtonEls[0].textContent = 'Submit for Review';
					submitButtonEls[1].textContent = 'Submit for Review + Back';
				}
				else {
					if(oldWarningMessage) oldWarningMessage.remove();

					submitButtonEls[0].textContent = 'Save';
					submitButtonEls[1].textContent = 'Save + Back';
				}
			}
		}
		else {
			if(validateFileUploads) {
				modIdInputEl.value = '';
				modVersionInputEl.value = '';
				if(response.gameVersionDep) {
					const versionInputs = document.querySelectorAll<HTMLInputElement>('.version-selector input[name="cgvs[]"]');
					for(const boxEl of versionInputs)  boxEl.checked = false;
					R.getC('count-label')!.textContent = `0 Versions Selected`;
				}

				const oldWarningMessage = R.getC('msg-require-review');
				if(oldWarningMessage) oldWarningMessage.remove();

				submitButtonEls[0].textContent = 'Save';
				submitButtonEls[1].textContent = 'Save + Back';
			}
		}
	}

	_attachFileInteractions({
		containerEl: containerEl,
		onChangeAttachment: (fileEl, fileId, goingToAttach) => {
			if(goingToAttach) {
				const otherCheckedEl = _getOtherAttachedTriggerElement();
				if(otherCheckedEl) { // If another file is attached, replace it instead of just checking this one.
					otherCheckedEl.checked = false;
					(otherCheckedEl.closest('.file')!.getElementsByTagName('LABEL')[0] as HTMLElement).title = 'Attach';

					if(!didShowMessage) {
						R.addMessage(MSG_CLASS_WARN, 'Only one file can be attached to each release, the other one was detached for you.<br/><small>This message will not show again until you reload.</small>', false);
						didShowMessage = true;
					}
				}
			}

			_changeAttachment({
				id: fileId, name: '', url: '',
				modId         : fileEl.dataset.modId,
				modVersion    : fileEl.dataset.modVersion ? BigInt(fileEl.dataset.modVersion) : undefined,
				gameVersionDep: fileEl.dataset.gameVersionDep ? BigInt(fileEl.dataset.gameVersionDep) : undefined,
				requiresReview: !!fileEl.dataset.requiresReview,
			}, goingToAttach);
		}
	});

	attachDefaultDropHandler(uploadSizeLimit, allowedExtensions, canEditRelease, (response, $elem) => {
		if(validateFileUploads) {
			const fileEl = $elem[0]!;

			fileEl.dataset.modId = response.modId || '';
			fileEl.dataset.modVersion = String(response.modVersion || '');
			fileEl.dataset.gameVersionDep = String(response.gameVersionDep || '');
			fileEl.dataset.requiresReview = String(response.requiresReview || '');

			if(response.modParse == "error") {
				R.addMessage(MSG_CLASS_ERROR, 'Failed to parse mod information from this file: '+response.parseMsg, true);
				return;
			}

			if(!_getOtherAttachedTriggerElement()) {
				(fileEl.getElementsByClassName('trigger')[0] as HTMLInputElement).checked = true;
				_changeAttachment(response, true);
			}
		}
	});
}
