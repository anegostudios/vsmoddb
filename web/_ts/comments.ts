
function attachCommentHandlers() {
	const container = document.getElementsByClassName('comments')[0];

	function updateCommentOrder()
	{
		const flatStructure = $.cookie('commentstructure') === 'flat';
		const oldestFirst = $.cookie("commentsort") === 'oldestfirst';
		const comments = Array.from(container.getElementsByClassName('comment') as HTMLCollectionOf<HTMLElement>);

		let newChildren : HTMLElement[];
		if(flatStructure) {
			newChildren = oldestFirst
				? comments.sort(function (a, b) {
						var dt = parseInt(a.dataset.stamp!) - parseInt(b.dataset.stamp!);
						return dt < 0 ? -1 : dt > 0 ? 1 : 0;
					})
				: comments.sort(function (a, b) {
						var dt = parseInt(b.dataset.stamp!) - parseInt(a.dataset.stamp!);
						return dt < 0 ? -1 : dt > 0 ? 1 : 0;
					})
			;
			container.replaceChildren(...newChildren);
		}
		else { // threaded 
			container.replaceChildren(); // clear

			// :MirroredLayouting
			const sorted = comments.sort(function (a, b) {
				var dt = parseFloat(a.dataset.order!) - parseFloat(b.dataset.order!);
				return dt < 0 ? -1 : dt > 0 ? 1 : 0;
			});

			const depthStack = [] as number[];
			const newRoot = R.make('div');
			let pushTargetEl = newRoot;

			for(const comment of sorted) {
				const responseDepth = parseInt(comment.dataset.d!);
				for(; depthStack.length && depthStack[depthStack.length - 1] >= responseDepth; depthStack.pop()) {
					pushTargetEl = pushTargetEl.parentElement!;
				}

				if(parseInt(comment.dataset.cldn!)) {
					const wrapper = R.make('div.convo');

					pushTargetEl.appendChild(wrapper);
					pushTargetEl = wrapper;
					
					depthStack.push(responseDepth);
				}

				pushTargetEl.appendChild(comment);
			}

			newChildren = Array.from(newRoot.children as HTMLCollectionOf<HTMLElement>);
			newChildren = oldestFirst 
				? newChildren.sort(function (a, b) {
					var dt = parseInt(a.dataset.stamp || (a.firstElementChild as HTMLElement)!.dataset.stamp!) - parseInt(b.dataset.stamp || (b.firstElementChild as HTMLElement)!.dataset.stamp!);
					return dt < 0 ? -1 : dt > 0 ? 1 : 0;
				})
				: newChildren.sort(function (a, b) {
					var dt = parseInt(b.dataset.stamp || (b.firstElementChild as HTMLElement)!.dataset.stamp!) - parseInt(a.dataset.stamp || (a.firstElementChild as HTMLElement)!.dataset.stamp!);
					return dt < 0 ? -1 : dt > 0 ? 1 : 0;
				})
			;

			container.replaceChildren(...newChildren)
		}
	}

	R.get('cmt-ord-desc')!.addEventListener('click', e => {
		e.preventDefault();
		$.cookie("commentsort", "newestfirst", { expires: 365, path: '/' });
		updateCommentOrder();
	});
	R.get('cmt-ord-asc')!.addEventListener('click', e => {
		e.preventDefault();
		$.cookie("commentsort", "oldestfirst", { expires: 365, path: '/' });
		updateCommentOrder();
	});

	R.get('cmt-threaded')!.addEventListener('click', e => {
		e.preventDefault();
		container.classList.add('threaded');
		$.cookie("commentstructure", "threaded", { expires: 365, path: '/' });
		updateCommentOrder();
	});
	R.get('cmt-flat')!.addEventListener('click', e => {
		e.preventDefault();
		container.classList.remove('threaded');
		$.cookie("commentstructure", "flat", { expires: 365, path: '/' });
		updateCommentOrder();
	});

	// @hack: This will cause a resort after the page finishes loading if the user selected flat view.
	// I to avoid that we need to store the threaded order on the objects, and do a bunch of time parsing.
	// TODO(Rennorb) @cleanup: Should probably just clean up the time formatting so this isn't such a penalty.
	if($.cookie("commentstructure") === "flat") updateCommentOrder();

	const newCommentWrapperEl = container.getElementsByClassName("comment-editor")[0];
	let cEditorInitialized = false;
	$("textarea", newCommentWrapperEl).focus(function (e : FocusEvent) {
		if (cEditorInitialized) return;
		cEditorInitialized = true;

		$(this).removeClass("whitetext");

		$('form[name=commentformtemplate]').trigger('reinitialize.areYouSure');
		
		const ta = e.currentTarget as HTMLTextAreaElement
		createEditor(ta, tinymceSettingsCmt, true);
	});

	$("button[name='save']", newCommentWrapperEl).click(function (e : MouseEvent) {
		const editorWrapper = $(this).parents(".comment.comment-editor")[0] as HTMLElement;
		
		const editorTAEl = editorWrapper.getElementsByTagName("textarea")[0];
		const content = getEditorContent($(editorTAEl));
		if(!content) return;

		const button = e.target as HTMLButtonElement;
		button.disabled = true; // prevent double submission by impatient user
		const prevButtonText = button.textContent!;

		startSubmissionSpinner(button);

		const xhr = $.ajax({ url: `/api/v2/mods/${modId}/comments?at=`+actiontoken, method: 'PUT', data: content, contentType: 'text/html', dataType: 'text' })
			.done(function (response : string, _, jqXHR : jqXHR) {
				const cmtFrag = jqXHR.getResponseHeader('Location')!;  // the response contains the newly generated comment id in the location header as a fragment link (e.g. `#cmt-213`)
				const commentId = cmtFrag.slice(5) // slice off the #cmt- from the link

				const cmt = createComment(commentId, 0, response, 0);
				editorWrapper.after(cmt)

				tinyMCE.get(editorTAEl.id).setContent(''); // Clear the editor

				temporaryHighlight(cmt);

				stopSubmissionSpinner(button, prevButtonText);
				button.disabled = false;
			})
			.fail(function() {
				stopSubmissionSpinner(button, prevButtonText);
				button.disabled = false;
			});
		R.attachDefaultFailHandler(xhr, 'Failed to submit comment');
	})

	$(".comment.comment-editor", container).show(); // Hidden initially until we are fully loaded. TODO @cleanup: dot cause reflow, just show a loading spinner.

	//
	// Set up comment head functions (edit, delete, moderate)
	//

	function clickRespond(e : MouseEvent)
	{
		e.preventDefault();
		let targetCommentEl = $(this).parents(".comment")[0] as HTMLElement;

		const targetCommentId = targetCommentEl.id.split('-')[1];
		const wrapperId = 'repl-'+targetCommentId;

		const prevWrapper = document.getElementById(wrapperId);
		if(prevWrapper) {
			const prevForm = prevWrapper.getElementsByTagName("form")[0];
			if (prevForm.classList.contains("dirty")) {
				var ok = confirm("Discard changed comment data?");
				if (!ok) return false;
			}

			destroyEditor($("textarea", prevForm));
			prevWrapper.remove();
			return false;
		}

		let responseDepth = parseInt(targetCommentEl.dataset.d!) + 1;
		responseDepth = Math.min(responseDepth, 10) // :MaxResponseDepth

		const editorWrapperEl = $(`
<div id="${wrapperId}" class="comment comment-editor editbox rsp" data-d="${responseDepth}">
	<div class="title">Response to comment:</div>
	<a class="reference" href="#${targetCommentEl.id}"><span></span></a>
	<div class="body"></div>
</div>
`)[0] as HTMLElement;
		const shortResponseText = targetCommentEl.getElementsByClassName('body')[0].textContent!.substring(0, 255);
		editorWrapperEl.getElementsByTagName('span')[0].textContent = shortResponseText;

		for(const anchor of targetCommentEl.getElementsByClassName('title')[0].getElementsByTagName('a')) {
			if(anchor.href.includes('user')) {
				editorWrapperEl.getElementsByClassName('title')[0].textContent = `Respond to ${anchor.textContent}'s comment:`;
				break;
			}
		}

		let targetOrder = 0;

		if($.cookie("commentstructure") !== "flat") {
			let wrapper = targetCommentEl.parentElement!;
			if(!wrapper.classList.contains('convo') || !parseInt(targetCommentEl.dataset.cldn!)) {
				wrapper = R.make('div.convo');
				const commentEl = targetCommentEl;
				targetCommentEl.replaceWith(wrapper);
				wrapper.appendChild(commentEl);
				targetCommentEl = commentEl;
			}

			let closestComment = wrapper;
			while(!closestComment.classList.contains('comment'))
				closestComment = closestComment.lastElementChild! as HTMLElement;
			targetOrder = parseFloat(closestComment.dataset.order!);

			wrapper.appendChild(editorWrapperEl);
		}
		else {
			if($.cookie("commentsort") === 'oldestfirst') {
				container.append(editorWrapperEl);
			}
			else {
				container.insertBefore(editorWrapperEl, container.firstElementChild!.nextElementSibling); // first child is the comment box
			}
		}

		editorWrapperEl.scrollIntoView({ behavior: "smooth", block: "nearest" })

		createInlineEditor(editorWrapperEl.getElementsByClassName('body')[0], 'Add Response', '', (button, content, form, editor) => {
			button.disabled = true; // prevent impatience
			const prevButtonText = button.textContent!;
			startSubmissionSpinner(button);

			const xhr = $.ajax({ url: `/api/v2/mods/${modId}/comments?response-to=${targetCommentId}&at=`+actiontoken, method: 'PUT', data: content, contentType: 'text/html', dataType: 'text' })
				.done(function(response, _, jqXHR : jqXHR) {
					stopSubmissionSpinner(button, "Ok")

					const cmtFrag = jqXHR.getResponseHeader('Location')!;  // the response contains the newly generated comment id in the location header as a fragment link (e.g. `#cmt-213`)
					const commentId = cmtFrag.slice(5) // slice off the #cmt- from the link
					
					// @hack: Adding .01 will fail after adding 100 messages without reloading the page, meaning sorting will be wired until a reload. Literally irrelevant.
					const cmt = createComment(commentId, responseDepth, response, targetOrder + .01);

					const usernameRef = targetCommentEl.getElementsByTagName('a')[1].textContent; //TODO(Rennorb) @hardcoded
					const ref = R.make<HTMLAnchorElement>('a.reference', '@'+usernameRef+": ", R.make('span', shortResponseText))
					ref.href = '#'+targetCommentEl.id;
					cmt.getElementsByClassName('title')[0].after(ref);

					targetCommentEl.dataset.cldn = String(parseInt(targetCommentEl.dataset.cldn!) + 1);

					tinyMCE.remove("#" + editor.id);
					editorWrapperEl.replaceWith(cmt);

					temporaryHighlight(cmt);
				})
				.fail(function() {
					stopSubmissionSpinner(button, prevButtonText)
					button.disabled = false;
				});
			R.attachDefaultFailHandler(xhr, 'Failed to submit comment');
		});

		return false;
	}

	function clickEdit(e : MouseEvent)
	{
		e.preventDefault();
		const $comment = $(this).parents(".comment");
		const $body = $('.body', $comment);

		if ($comment.data("editing") == 1) {
			const $form = $comment.find("form");
			if ($form.hasClass("dirty")) {
				var ok = confirm("Discard changed comment data?");
				if (!ok) return false;
			}

			destroyEditor($("textarea", $comment));
			$form.remove();
			$body.show();

			$comment.data("editing", 0);
			$('form[name=commentformedit]').trigger('reinitialize.areYouSure');
			return false;
		}

		$body.hide();
		$comment.data("editing", 1);

		const commentId = $comment[0].id.split('-')[1];
		createInlineEditor($comment[0], 'Update Comment', $body.html(), (button, content, form, editor) => {
			const xhr = $.ajax({ url: `/api/v2/comments/${commentId}?at=`+actiontoken, method: 'POST', data: content, contentType: 'text/html', dataType: 'json' })
				.done(function(response) {
					tinyMCE.remove("#" + editor.id);
					form.remove();

					$body.html(response.html);
					attachSpoilerToggle($('.spoiler-toggle', $body));
					$comment.data("editing", 0);
					$body.show();
				});
			R.attachDefaultFailHandler(xhr, 'Failed to edit comment');
		});

		return false;
	}

	function clickHide(e : MouseEvent)
	{
		e.preventDefault();

		const comment = $(this).parents(".comment")[0];
		const isModerator = !!comment.querySelector('a[href^="/moderate/"]');
		if(!comment.classList.contains('deleted')) {
			if(confirm("Are you sure you want to hide this comment?")) {
				if(isModerator) comment.classList.add('deleted');
				else comment.style.display = 'none';
	
				const commentId = comment.id.split('-')[1];
				const xhr = $.ajax({ url: `/api/v2/comments/${commentId}`, method: 'DELETE', data: { at: actiontoken }})
				R.attachDefaultFailHandler(xhr, 'Failed to hide comment')
					.fail(() => {
						// Make it visible again if we failed to delete it, so the user may retry.
						if(isModerator) comment.classList.remove('deleted');
						else comment.style.display = '';
					});
			}
		}
		else if(isModerator) {
			if(confirm("Are you sure you want to unhide this comment?")) {
				comment.classList.remove('deleted');

				comment.getElementsByClassName('ribbon-tr')[0].remove();
	
				const commentId = comment.id.split('-')[1];
				const xhr = $.ajax({ url: `/api/v2/comments/${commentId}/unhide`, method: 'POST', data: { at: actiontoken }})
				R.attachDefaultFailHandler(xhr, 'Failed to hide comment')
					.fail(() => {
						// Make it visible again if we failed to delete it, so the user may retry.
						comment.classList.add('deleted');
					});
			}
		}
	}

	const reportDialogEl = R.get<HTMLDialogElement>('report-cmt-mdl');
	if(reportDialogEl) {
		const reportErrContainerEl = reportDialogEl.getElementsByClassName('err-container')[0];

		attachDialogSendHandler(reportDialogEl, (form, data) => {
			reportErrContainerEl.innerHTML = '&nbsp;';

			if(!data.get('category')) {
				R.markAsErrorElement(form.querySelector('[name="category"]')!);
				return false;
			}

			const reason = data.get('reason') as string | null;
			if(reason) {
				reason.trim();
				data.set('reason', reason);
			}

			if(!reason || reason.length < 50) {
				R.markAsErrorElement(form.getElementsByClassName('tox-tinymce')[0] as HTMLElement);
				reportErrContainerEl.textContent = 'Please provide substantial reasoning for your report.';
				return false;
			}

			return true;
		}, (jqXHR) => {
			const submitButton = reportDialogEl.getElementsByClassName('btn-submit')[0] as HTMLButtonElement;
			startSubmissionSpinner(submitButton);

			R.attachDefaultFailHandler(jqXHR, "Failed to report comment", (err) => {
				stopSubmissionSpinner(submitButton, "Report");
				if(jqXHR.status == 429) // Too many requests. Special case where we don't escape.
					reportErrContainerEl.innerHTML = err;
				else
					reportErrContainerEl.textContent = err;
				return true;
			})
			.done(() => {
				const link = jqXHR.getResponseHeader('Location')!;
				R.addMessage(MSG_CLASS_OK, `Your report has been submitted (<a href="${link}" target="_blank">link</a>).`, false)
				reportDialogEl.close();
				stopSubmissionSpinner(submitButton, "Report");
				submitButton.disabled = false;
			});
		});

		function clickReport(e : MouseEvent)
		{
			e.preventDefault();

			const commentId = $(this).parents(".comment")[0].id.split('-')[1];
			reportDialogEl!.getElementsByTagName('form')[0].action = `/api/v2/comments/${commentId}/report`;

			reportDialogEl!.getElementsByTagName('select')[0].value = '';

			const ta = reportDialogEl!.getElementsByTagName('textarea')[0];
			if(ta.style.display !== 'none') createEditor(ta, tinymceSettingsReport);
			else clearEditorContent(ta);

			reportErrContainerEl.innerHTML = '&nbsp;';

			reportDialogEl!.showModal();
		}

		$(container).on("click", 'a[href="#p"]', clickReport);
	}


	$(container).on("click", 'a[href="#r"]', clickRespond);
	$(container).on("click", 'a[href="#e"]', clickEdit);
	$(container).on("click", 'a[href="#h"]', clickHide);
	$(container).on("click", 'a[href^="#cmt-"]', highlightClickedEl);

	//
	// Highlight for direct comment links
	//

	if(document.location.hash.split('-')[0] === '#cmt') {
		const el = document.getElementById(document.location.hash.substring(1));
		if(el) temporaryHighlight(el);
	}

	function highlightClickedEl(_ : MouseEvent)
	{
		const href = (this as HTMLAnchorElement).getAttribute('href');
		if(!href) return;

		const id = href.slice(1)
		var target = document.getElementById(id)!;
		setTimeout(() => temporaryHighlight(target), 100); // wait until scrolled into view
	}

	//
	// Util stuff
	//

	function createInlineEditor(wrapperEl : Element, buttonText : string, contents : string, saveCallback : (button : HTMLButtonElement, editorContents : string, form : HTMLFormElement, editor : HTMLTextAreaElement) => void) : void
	{
		const formEl = $(`
			<form name="commentformedit" onsubmit="return false;">
				<textarea name="commenttext" class="editor editcommenteditor" data-editorname="editcomment" style="width: 100%; height: 135px;"></textarea>
				<p style="margin:4px; margin-top:5px;"><button class="shine" type="submit" name="save">${buttonText}</button></p>
			</form>
		`)[0] as HTMLFormElement;

		formEl.getElementsByTagName('textarea')[0].textContent = contents;

		wrapperEl.append(formEl);
		$(formEl).areYouSure();

		const editorTAEl = formEl.getElementsByTagName("textarea")[0];
		createEditor(editorTAEl, tinymceSettingsCmt, true);

		const button = formEl.querySelector("button[name='save']")! as HTMLButtonElement;
		button.addEventListener('click', (e) => {
			e.preventDefault();

			var content = getEditorContent($(editorTAEl));
			saveCallback(button, content, formEl, editorTAEl);
		});
	}

	function createComment(commentId : number|string, responseDepth : number, safeBody : string, responseTargetOrder : number) : HTMLElement
	{
		// We don't have direct information about the current user in js land, so we extract it form the menu:
		const accMenu = R.get('account-menu');
		const userName = accMenu!.firstElementChild!.textContent;
		const userUrl = (accMenu!.lastElementChild!.firstElementChild as HTMLAnchorElement).getAttribute('href');

		const cmt = $(`
<div id="cmt-${commentId}" class="editbox comment${responseDepth ? ' rsp' : ''}" data-order="${responseTargetOrder}" data-stamp="${Date.now()}"${responseDepth ? ' data-d="'+responseDepth+'"' : ''} data-cldn="0">
	<div class="title">
		<span><a style="text-decoration:none;" class="cmt-pinner" href="#cmt-${commentId}"><i class="bx bx-link-alt"></i></a> <a href="${userUrl}">${userName}</a>, just now</span>
		<span class="buttons">(<a href="#r" onclick="return false;">respond</a>&nbsp;<a href="#e" onclick="return false;">edit</a>&nbsp;<a href="#h" onclick="return false;">hide</a>&nbsp;<a href="#p" onclick="return false;">report</a>)</span>
	</div>
	<div class="body">${safeBody}</div>
</div>
`)[0] as HTMLElement;

		attachSpoilerToggle($('.spoiler-toggle', $(cmt)));

		return cmt;
	}
}
