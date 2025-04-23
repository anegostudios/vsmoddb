
initialized = false;

$(document).ready(function () {

	$("a[href='#ordernewestfirst']").click(function () {
		var result = $('.comments > div').sort(function (a, b) {

			var contentA = parseInt($(a).attr('data-timestamp'));
			var contentB = parseInt($(b).attr('data-timestamp'));
			return (contentA < contentB) ? 1 : (contentA > contentB) ? -1 : 0;
		});

		$('.comments').html(result);
		$.cookie("commentsort", "newestfirst", { expires: 365 });

		return false;
	});

	$("a[href='#orderoldestfirst']").click(function () {
		var result = $('.comments > div').sort(function (a, b) {
			var contentA = parseInt($(a).attr('data-timestamp'));
			var contentB = parseInt($(b).attr('data-timestamp'));
			return (contentA < contentB) ? -1 : (contentA > contentB) ? 1 : 0;
		});

		$('.comments').html(result);
		$.cookie("commentsort", "oldestfirst", { expires: 365 });
		return false;
	});

	if ($.cookie("commentsort") == "oldestfirst") $("a[href='#orderoldestfirst']").trigger("click");

	$(".comments .comment.comment-editor").show();
	$(".comments .comment.comment-editor textarea").focus(function () {
		if (initialized) return;
		$(this).removeClass("whitetext");
		initialized = true;
		$('form[name=commentformtemplate]').trigger('reinitialize.areYouSure');
		createEditor($('.comment.comment-editor textarea[name=commenttext]'), tinymceSettingsCmt);
	});


	$(document).on("click", "a[href='#deletecomment']", function () {
		if (confirm("Really delete comment?")) {
			const $comment = $(this).parents(".comment");
			$comment.hide();

			const commentid = $(this).attr("data-commentid");
			$.ajax({ url: `/api/v2/comments/${commentid}?at=`+actiontoken, method: 'DELETE'})
				.fail(function(jqXHR) {
					$comment.show();

					const d = JSON.parse(jqXHR.responseText);
					addMessage(MSG_CLASS_ERROR, 'Failed to delete comment' + (d.reason ? (': '+d.reason) : '.'))
				});;
		}
	});

	$(document).on("click", "a[href='#editcomment']", function () {
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

		const commentid = $(this).attr("data-commentid");
		const $form = $(`
			<form name="commentformedit" onsubmit="javascript:return false;">
				<textarea name="commenttext" class="editor editcommenteditor" data-editorname="editcomment" style="width: 100%; height: 135px;">${$body.html()}</textarea>
				<p style="margin:4px; margin-top:5px;"><button class="shine" type="submit" name="save">Update Comment</button></p>
			</form>
		`);
		$form.appendTo($comment);
		$form.areYouSure();

		const $editor = $("textarea", $form);
		createEditor($editor, tinymceSettingsCmt);

		$("button[name='save']", $form).click(function() {
			var content = getEditorContents($editor);
			//TODO(Rennorb): optimistic update

			$.ajax({ url: `/api/v2/comments/${commentid}?at=`+actiontoken, method: 'POST', data: content, contentType: 'text/html', dataType: 'json' })
				.done(function(response) {
					destroyEditor($editor);
					$form.remove();

					$body.html(response.html);
					attachSpoilerToggle($('.spoiler-toggle', $body));
					$comment.data("editing", 0);
					$body.show();
				})
				.fail(function(jqXHR) {
					const d = JSON.parse(jqXHR.responseText);
					addMessage(MSG_CLASS_ERROR, 'Failed to edit comment' + (d.reason ? (': '+d.reason) : '.'))
				});
		});

		return false;
	});


	$(".comments .comment.comment-editor button[name='save']").click(function () {
		const $comment = $(this).parents(".comment");

		const content = getEditorContents($("textarea", $comment));
		if(!content) return;

		const $editor = $(".comments .comment.comment-editor");

		const $cmt = $(`
			<div class="editbox comment">
			<div class="title"><a style="text-decoration:none;" href="#">&#128172;</a> You, just now</div>
			<div class="body">${content}</div>
			</div>
		`);
		$cmt.insertAfter($editor);
		attachSpoilerToggle($('.spoiler-toggle', $cmt));
		$editor.hide();

		$.ajax({ url: `/api/v2/mods/${modid}/comments/new?at=`+actiontoken, method: 'POST', data: content, contentType: 'text/html', dataType: 'json' })
			.done(function (response) {
				$('.title a', $cmt)[0].href = '#cmt-'+response.id;
				$('.title', $cmt)[0].innerHTML += getCmtLinks(response.id);
				$('.body', $cmt)[0].innerHTML = response.html;
				attachSpoilerToggle($('.spoiler-toggle', $cmt));
			})
			.fail(function(jqXHR) {
				$cmt.remove();
				$editor.show();

				const d = JSON.parse(jqXHR.responseText);
				addMessage(MSG_CLASS_ERROR, 'Failed to submit comment' + (d.reason ? (': '+d.reason) : '.'))
			});
	})
});


function getCmtLinks(commentid) {
	$cmtlinks = $(".buttonlinks.template").clone().removeClass("template");
	$("a", $cmtlinks).each(function () {
		$(this).attr('data-commentid', commentid);
	});

	return $cmtlinks[0].outerHTML;
}
