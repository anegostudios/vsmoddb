
var cEditorInitialized = false;
function attachCommentHandlers() {
	const container = document.getElementsByClassName('comments')[0];

	$("a[href='#ordernewestfirst']").click(function () {
		const sorted = Array.from(container.children as HTMLCollectionOf<HTMLElement>).sort(function (a, b) {
			var dt = parseInt(b.dataset.timestamp!) - parseInt(a.dataset.timestamp!);
			return dt < 0 ? -1 : dt > 0 ? 1 : 0;
		})

		container.replaceChildren(...sorted);

		$.cookie("commentsort", "newestfirst", { expires: 365 });
		return false;
	});

	$("a[href='#orderoldestfirst']").click(function () {
		const sorted = Array.from(container.children as HTMLCollectionOf<HTMLElement>).sort(function (a, b) {
			var dt = parseInt(a.dataset.timestamp!) - parseInt(b.dataset.timestamp!);
			return dt < 0 ? -1 : dt > 0 ? 1 : 0;
		})

		container.replaceChildren(...sorted);

		$.cookie("commentsort", "oldestfirst", { expires: 365 });
		return false;
	});

	if ($.cookie("commentsort") == "oldestfirst") $("a[href='#orderoldestfirst']").trigger("click");

	$(".comment.comment-editor", container).show();
	$(".comment.comment-editor textarea", container).focus(function (e : FocusEvent) {
		if (cEditorInitialized) return;
		$(this).removeClass("whitetext");
		cEditorInitialized = true;
		$('form[name=commentformtemplate]').trigger('reinitialize.areYouSure');
		const ta = e.currentTarget as HTMLTextAreaElement
		createEditor(ta, tinymceSettingsCmt);
		//TODO(Rennorb): @ux: would like to focus the editor after we created it, but that just crashes for some reason.
		// Seems to be a know bug in tiny aswell...
	});

	$(".comment.comment-editor button[name='save']", container).click(function () {
		const $comment = $(this).parents(".comment");

		const content = getEditorContents($("textarea", $comment));
		if(!content) return;

		const $editor = $(".comments .comment.comment-editor");

		const $cmt = $(`
<div class="editbox comment">
	<div class="title">
		<span><a style="text-decoration:none;" class="cmt-pinner" href="#"><i class="bx bx-link-alt"></i></a> You, just now</span>
	</div>
	<div class="body">${content}</div>
</div>
		`);
		// Guess that it worked and already attach the comment, we revert it in case it didn't. Makes things snappier.
		$cmt.insertAfter($editor);
		attachSpoilerToggle($('.spoiler-toggle', $cmt));
		$editor.hide();

		const xhr = $.ajax({ url: `/api/v2/mods/${modId}/comments?at=`+actiontoken, method: 'PUT', data: content, contentType: 'text/html', dataType: 'text' })
			.done(function (response : string, _, jqXHR : jqXHR) {
				const cmtFrag = jqXHR.getResponseHeader('Location')!;
				$cmt.id = cmtFrag.slice(1); // slice of the # from #cmt-213
				$('.cmt-pinner', $cmt)[0].href = cmtFrag;
				$('.title', $cmt)[0].innerHTML += `<div class="buttons"><button class="button square" data-a="e" title="Edit"><i class="bx bx-pencil"></i></button>&nbsp;<button class="button square" data-a="d" title="Delete"><i class="bx bx-trash"></i></button></div>`;
				$('.body', $cmt)[0].innerHTML = response; // update the response to the actual serverside validated version
				attachSpoilerToggle($('.spoiler-toggle', $cmt));
			})
			.fail(function() {
				$cmt.remove();
				$editor.show();
			});
		R.attachDefaultFailHandler(xhr, 'Failed to submit comment');
	})


	$(container).on("click", 'button[data-a="d"]', function () {
		if (confirm("Are you sure you want to delete this comment?")) {
			const $comment = $(this).parents(".comment");
			$comment.hide();

			const commentId = $comment[0].id.split('-')[1];
			const xhr = $.ajax({ url: `/api/v2/comments/${commentId}?at=`+actiontoken, method: 'DELETE'})
			R.attachDefaultFailHandler(xhr, 'Failed to delete comment')
				.fail(() => $comment.show()); // Make it visible again if we failed to delete it, so the user may retry.
		}
	});

	$(container).on("click", 'button[data-a="e"]', function () {
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
		const $form = $(`
			<form name="commentformedit" onsubmit="javascript:return false;">
				<textarea name="commenttext" class="editor editcommenteditor" data-editorname="editcomment" style="width: 100%; height: 135px;">${$body.html()}</textarea>
				<p style="margin:4px; margin-top:5px;"><button class="shine" type="submit" name="save">Update Comment</button></p>
			</form>
		`);
		$form.appendTo($comment);
		$form.areYouSure();

		const $editor = $("textarea", $form);
		createEditor($editor[0], tinymceSettingsCmt);

		$("button[name='save']", $form).click(function(e) {
			e.preventDefault();
			var content = getEditorContents($editor);
			//TODO(Rennorb): optimistic update

			const xhr = $.ajax({ url: `/api/v2/comments/${commentId}?at=`+actiontoken, method: 'POST', data: content, contentType: 'text/html', dataType: 'json' })
				.done(function(response) {
					destroyEditor($editor);
					$form.remove();

					$body.html(response.html);
					attachSpoilerToggle($('.spoiler-toggle', $body));
					$comment.data("editing", 0);
					$body.show();
				});
			R.attachDefaultFailHandler(xhr, 'Failed to edit comment');
		});

		return false;
	});

	if(document.location.hash.split('-')[0] === '#cmt') {
		const el = document.getElementById(document.location.hash.substring(1));
		if(el) {
			el.classList.add('highlight');
			setTimeout(() => el.classList.remove('highlight'), 2000); // remove so sorting doesn't re-trigger the highlight.
		}
	}
}
