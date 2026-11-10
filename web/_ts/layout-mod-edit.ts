function attachTeamMemberSelections() : void
{
	const $editPermsSelect = $('#teameditors-box select');
	$("#teammembers-box select").on('change', function(e, ex) {
		if(ex.selected) {
			const $option = $(e.target).find(`[value="${ex.selected}"]`).clone();
			$option.removeProp('selected');
			$editPermsSelect.append($option);
		}
		else if(ex.deselected) {
			$editPermsSelect.find(`[value="${ex.deselected}"]`).remove()
		}
		$editPermsSelect.trigger("chosen:updated");
	});
}

//TODO(Rennorb) @cleanup:
declare var $cardLogoSelect;
declare var cardPreviewBoxEl;
declare var $embedLogoSelect;
declare var embedPreviewBoxEl;

function mod_attachFileInteractions(uploadLimits : UploadLimits) : void
{
	const containerEl = R.getC('files')!;

	function _canAttachMore() : boolean
	{
		return containerEl.querySelectorAll('[name="attachedFileIds[]"]:checked').length < uploadLimits.attachmentCount;
	}

	function _changeAttachment(response : UploadResponse, imgSize : string, nowAttached : boolean) : void
	{
		if(!['480x320', '480x480'].includes(imgSize)) return;

		if(nowAttached) {
			function _addThumbOption($select : JQElement, imgSize : string) {
				const opt = document.createElement('option');
				opt.value = String(response.id);
				opt.textContent = `${response.name} [${imgSize} px]`;
				opt.dataset.url = response.url;

				$select.append(opt);
				$select.trigger("chosen:updated");
			}

			_addThumbOption($cardLogoSelect, imgSize);
			_addThumbOption($embedLogoSelect, imgSize);
		}
		else {
			_removeThumbOption(null, response.id)
		}
	}

	function _removeThumbOption(_ : any, fileId : number) : void
	{
		function _removeOpt($select : JQElement, previewBox : HTMLElement) {
			const opt = $select[0]!.querySelector<HTMLOptionElement>(`option[value="${fileId}"]`);
			if(opt) {
				const previewImage = previewBox.getElementsByTagName('img')[0];
				if(previewImage && previewBox.dataset.fid == opt.value) {
					previewImage.src = '/web/img/mod-default.png';
				}

				opt.remove();
				$select.trigger("chosen:updated");
			}
		}

		_removeOpt($cardLogoSelect, cardPreviewBoxEl);
		_removeOpt($embedLogoSelect, embedPreviewBoxEl);
	}

	_attachFileInteractions({
		containerEl: containerEl,
		onChangeAttachment: (fileEl, fileId, goingToAttach) => {
			if(goingToAttach) {
				if(!_canAttachMore()) {
					R.addMessage(MSG_CLASS_ERROR, `Max. amount of images (${uploadLimits.attachmentCount}) is already attached.`);
					return false;
				}
			}

			_changeAttachment({
				id       : fileId,
				name     : fileEl.getElementsByClassName('filename')[0].textContent!,
				url      : (fileEl.getElementsByTagName('A')[0] as HTMLElement).getAttribute('href')!,
			}, fileEl.dataset.imageSize!, goingToAttach);
		},
		onDelete: _removeThumbOption,
	});

	attachDefaultDropHandler(uploadLimits.individualSize, uploadLimits.allowedExtensions, (response, $elem) => {
		if(!response || response.error) return;

		const fileEl = $elem[0]!;
		fileEl.dataset.imageSize = `${response.imgWidth}x${response.imgHeight}`;

		if(_canAttachMore()) {
			$('.trigger', $elem)[0].checked = true;
			_changeAttachment(response, fileEl.dataset.imageSize, true);
		}
	});
}
