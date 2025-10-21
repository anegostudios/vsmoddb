var uploading = 0;

$(document).ready(function () {
	createEditor($("textarea.editor"), tinymceSettings);

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

			$.post("/edit-deletefile", { fileid: fileid, at: actiontoken }).done(function() {
				$fileEl.remove();
				addMessage(MSG_CLASS_OK, filename + ' deleted.', true);
			});
		}

		return false;
	});

	$(document).filedrop({
		url: '/edit-uploadfile',
		paramname: 'file',
		data: {
			upload: 1,
			assetid: assetid,
			assettypeid: assettypeid
		},
		error: function (err, file) {
			switch (err) {
				case 'BrowserNotSupported':
					//alert('browser does not support HTML5 drag and drop')
					break;
				case 'TooManyFiles':
					// user uploaded more than 'maxfiles'
					alert('too many files')
					break;
				case 'FileTooLarge':
					// program encountered a file whose size is greater than 'maxfilesize'
					// FileTooLarge also has access to the file which was too large
					// use file.name to reference the filename of the culprit file
					alert('file too large')
					break;
				case 'FileTypeNotAllowed':
					// The file type is not in the specified list 'allowedimporttypes'
					alert('not allowed file type')
					break;
				case 'FileExtensionNotAllowed':
					// The file extension is not in the specified list 'allowedfileextensions'
					alert('not allowed file extension')
					break;
				default:
					break;
			}
		},
		allowedimporttypes: [],   // importtypes allowed by Content-Type.  Empty array means no restrictions
		allowedfileextensions: [], // file extensions allowed. Empty array means no restrictions
		maxfiles: 100,
		maxfilesize: 200,    // max file size in MBs

		dragOver: function () {
			// user dragging files over #dropzone
		},
		dragLeave: function () {
			// user dragging files out of #dropzone
		},
		docOver: function () {
			// user dragging files anywhere inside the browser document window
		},
		docLeave: function () {
			// user dragging files out of the browser document window
		},
		drop: function () {
			// user drops file
			//console.log("dropped");

			return true;
		},
		uploadStarted: function (i, file, len) {
			// a file began uploading
			// i = index => 0, 1, 2, 3, 4 etc
			// file is the actual file of the index
			// len = total files user dropped
			//console.log("started");
			uploading++;

			var parts = file.name.split('.');
			var ending = parts[parts.length - 1];

			$elem = $(".file.template").clone();
			$elem.removeClass("template");
			$elem.attr("data-filename", file.name);

			$(".filename", $elem).text(file.name);
			$(".fi", $elem).addClass("fi-" + ending);
			$(".fi-content", $elem).html(ending);
			$(".uploadprogress", $elem).text("0%");

			$(".files").append($elem);
		},
		uploadFinished: function (i, file, response, time) {
			$elem = $(".file[data-filename='" + file.name + "']");

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
		progressUpdated: function (i, file, progress) {
			// this function is used for large files and updates intermittently
			// progress is the integer value of file being uploaded percentage to completion

			$elem = $(".file[data-filename='" + file.name + "']");
			$(".uploadprogress", $elem).text(progress + "%");
		},
		globalProgressUpdated: function (progress) {
			// progress for all the files uploaded on the current instance (percentage)
			// ex: $('#progress div').width(progress+"%");
		},
		speedUpdated: function (i, file, speed) {
			// speed in kb/s
		},
		rename: function (name) {
			// name in string format
			// must return alternate name as string
		},
		beforeEach: function (file) {
			// file is a file object
			// return false to cancel upload
		},
		beforeSend: function (file, i, done) {
			// file is a file object
			// i is the file index
			// call done() to start the upload
			done();
		},
		afterAll: function () {
			// runs after all files have been uploaded or otherwise dealt with
		}
	});

	for(const container of document.getElementsByClassName('reorderable'))
		makeReorderable(container)
});

function makeReorderable(containerEl)
{
	let movingEl;
	const dragStart = (e) => {
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', null);
		movingEl = e.currentTarget;
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


