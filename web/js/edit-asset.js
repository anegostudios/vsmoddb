var uploading = 0;

$(document).ready(function () {
	createEditor(R.getQ("textarea.editor"), tinymceSettings);

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

	$(document).on("click", ".file .delete", function () {
		const $self = $(this);
		const $fileEl = $self.parent();
		const filename = $fileEl.find(".filename").text();
		const fileid = $self.attr("data-fileid");

		if ($fileEl.hasClass("error")) {
			$fileEl.remove();
			return false;
		}

		if (confirm("Really delete " + filename + "?")) {
			if(typeof(onFileDelete) === 'function') if(onFileDelete($fileEl, fileid) === false) return;

			const xhr = $.post("/edit-deletefile", { fileid: fileid, at: actiontoken });
			R.attachDefaultFailHandler(xhr)
				.done(function() {
					$fileEl.remove();
					R.addMessage(MSG_CLASS_OK, filename + ' deleted.', true);
				});
		}

		return false;
	});

	attachDropHandler({
		target: window,
		destUrl: '/edit-uploadfile',
		additionalData: {
			upload: 1,
			assetid: assetid,
			assettypeid: assettypeid,
			modId: modId || 0,
		},
		onPrefilter : function(item) {
			return true;
		},
		onValidate: function(file) {
			return file.size <= 200 * 1024 * 1024;
		},
		onStart: function (e) {
			uploading++;
			
			const file = e.file;
			const extIdx = file.name.lastIndexOf('.');
			const ext = extIdx < 0 ? '' : file.name.slice(extIdx + 1)

			$elem = $(".file.template").clone();
			$elem.removeClass("template");
			$elem.attr("data-filename", file.name);

			$(".filename", $elem).text(file.name);
			$(".fi", $elem).addClass("fi-" + ext);
			$(".fi-content", $elem).html(ext);
			$(".uploadprogress", $elem).text("0%");

			$(".files").append($elem);

			return $elem;
		},
		onProgress: function (e) {
			const progress = e.lengthComputable ? Math.round((e.loaded * 100) / e.total) : '???';
			$(".uploadprogress", e.userData).text(progress + "%");
		},
		onComplete: function (e) {
			const $elem = e.userData;
			let response = null;
			try { response = JSON.parse(e.target.response); } catch(ex) {
				console.error(ex);
				response = { status: 'error', errormessage: ex.message };
			}

			if (response.status != "ok") {
				$(".filename", $elem).html("Unable to upload file.<br>" + response.errormessage).addClass("text-error");
				$elem.addClass("error");
			}

			if (response.thumbnailfilepath) {
				$(".fi", $elem).remove();
				$("img", $elem).attr("src", response.thumbnailfilepath).show();
			}

			$("input", $elem).attr('name', 'fileIds[]').val(response.fileid);
			$(".uploadprogress", $elem).hide();
			$elem.append("<a href=\"#\" class=\"delete\" data-fileid=\"" + response.fileid + "\"></a>");
			$(".uploaddate", $elem).text(response.uploaddate);
			if(response.imagesize) $(".imagesize", $elem).text(response.imagesize+' px');

			if(typeof(onUploadFinished) === 'function') onUploadFinished(response);
		},
	});

	for(const container of document.getElementsByClassName('reorderable'))
		makeReorderable(container)
});

function makeReorderable(containerEl)
{
	let movingEl;
	const dragStart = (e) => {
		movingEl = e.currentTarget;
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setDragImage(e.currentTarget, e.offsetX, e.offsetY);
	};
	const dragOver = (e) => {
		if(!movingEl || e.currentTarget.parentNode !== movingEl.parentNode) return;
		const p = movingEl.parentNode;
		const i = Array.prototype.indexOf.call(p.children, e.currentTarget);
		const j = Array.prototype.indexOf.call(p.children, movingEl);
		p.insertBefore(movingEl, i < j ? e.currentTarget : e.currentTarget.nextSibling);
	};
	
	for(const item of containerEl.children) {
		if(item.classList.contains('immovable')) return;
		item.addEventListener('dragover', dragOver);
		item.addEventListener('dragstart', dragStart);
		item.draggable = true;
	}
}


function submitForm(returntolist) {
	var good = true;
	$(".required").each(function () {
		if ($(this).parents(".template").length != 0 || $(this)[0].hasAttribute('disabled')) return;

		if (!$(this).val()) {
			$(this).addClass("bg-error");
			good = false;
		}
	});

	$('form[name=form1]').trigger('reinitialize.areYouSure');

	if (!good) {
		alert("Please fill in all required fields");
		return;
	}


	if (returntolist) {
		$('input[name="saveandback"]').val(1);
	}
	const f1 = document['form1'];
	const statusSelect = f1.querySelector('select[name="statusId"]'); // @hack might be locked and disabled. If it is it wont be submitted, so we just briefly unlock it. 
	if(statusSelect) statusSelect.removeAttribute('disabled')
	f1.submit();
}

function submitDelete() {
	var cf = prompt("Really delete this entry? Type DELETE to confirm");
	if (cf == "DELETE") {
		document['deleteform'].submit();
	}
}


