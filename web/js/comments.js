
initialized=false;

$(document).ready(function() {

	$("a[href='#ordernewestfirst']").click(function() {
	      var result = $('.comments > div').sort(function (a, b) {

      	  var contentA = parseInt( $(a).attr('data-timestamp'));
	      var contentB = parseInt( $(b).attr('data-timestamp'));
	      return (contentA < contentB) ? 1 : (contentA > contentB) ? -1 : 0;
	     });

             $('.comments').html(result);
	     $.cookie("commentsort", "newestfirst", { expires: 365 });

	   return false;
	});
	
	$("a[href='#orderoldestfirst']").click(function() {
	    var result = $('.comments > div').sort(function (a, b) {
      	  var contentA = parseInt( $(a).attr('data-timestamp'));
	      var contentB = parseInt( $(b).attr('data-timestamp'));
	      return (contentA < contentB) ? -1 : (contentA > contentB) ? 1 : 0;
	    });

        $('.comments').html(result);
	    $.cookie("commentsort", "oldestfirst", { expires: 365 });
   	    return false;
	});

	if ($.cookie("commentsort") == "oldestfirst") $("a[href='#orderoldestfirst']").trigger("click");

	$(".comments .comment.template").toggle();
	$(".comments .comment.template textarea").focus(function() {
		if (initialized) return;
		$(this).removeClass("whitetext");
		initialized=true;
		$('form[name=commentformtemplate]').trigger('reinitialize.areYouSure');
		createEditor($('.comment.template textarea[name=commenttext]'), tinymceSettingsCmt);
	});
	
	
	$(document).on("click", "a[href='#deletecomment']", function() {
		$self = $(this);
		if (confirm("Really delete comment?")) {
			var commentid = $self.attr("data-commentid");
			$.post('/delete-comment', { commentid: commentid, at: actiontoken, delete: 1  }, function(response) {
				var $elem = $self.parents(".comment");
				$elem.remove();
			});
		}
	});
	
	$(document).on("click", "a[href='#editcomment']", function() {
		var $elem = $(this).parents(".comment");
		
		if ($elem.data("editing") == 1) {
			if ($elem.find("form").hasClass("dirty")) {
				var ok = confirm("Discard changed comment data?");
				if (!ok) return false;
			}

			destroyEditor($("textarea", $elem));
				
			$(".updateCmt", $elem).remove();
			
			$("textarea",  $elem).replaceWith($elem.data("body"));
			
			$elem.data("editing", 0);
			$('form[name=commentformedit]').trigger('reinitialize.areYouSure');
			return false;
		}
		
		$elem.data("editing", 1);
		
		var commentid = $(this).attr("data-commentid");
		
		$elem.data("body", $(".body", $elem).html());
		var $form = $('<form name="commentformedit"><textarea name="commenttext" class="editor editcommenteditor" data-editorname="editcomment" style="width: 100%; height: 135px;">'+$(".body", $elem).html()+'</textarea></form>');
		$(".body", $elem).html($form);
		
		$form.areYouSure();
		
		createEditor($('.editcommenteditor'), tinymceSettingsCmt);
			
		$elem.append($('<p class="updateCmt" style="margin:4px; margin-top:5px; clear:both;"><button type="submit" name="save">Update Comment</button>'));
		
		$("button[name='save']", $elem).click(function() {
			var html = getEditorContents($('.editcommenteditor'));

			$.post('/edit-comment', { commentid: commentid, text: html, at: actiontoken, save: 1  }, function(response) {
				var data = $.parseJSON(response).comment;
				
				destroyEditor($('.editcommenteditor'));
				
				var $cmt = $(
					'<div class="editbox comment" style="clear:both;">'+
						'<div class="title">'+data.username +', '+data.created+getCmtLinks(commentid)+'</div>'+
						'<div class="body">'+data.text+'</div>'+
					'</div>'
				);
				
				$elem.replaceWith($cmt);
				$elem.data("editing", 0);
			});
			
		});
			
		return false;
	});
	
	
	$(".comments .comment.template button[name='save']").click(function() {
		var $elem =  $(this).parents(".comment");
		
		$.post('/edit-comment', { assetid:assetid, text: getEditorContents($("textarea", $elem)), at: actiontoken, save: 1 }, function(response) {
			var data = $.parseJSON(response).comment;
			
			var $cmt = $(
				'<div class="editbox comment" style="clear:both;">'+
					'<div class="title">'+data.username+', '+data.created+getCmtLinks(data.commentid)+'</div>'+
					'<div class="body">'+data.text+'</div>'+
				'</div>'
			);
			
			setEditorContents($("textarea", $elem), "");
			$cmt.insertAfter($(".comments .comment.template"));
			$(".comments .comment.template").toggle();
		});
	});
	
});


function getCmtLinks(commentid) {
	$cmtlinks = $(".buttonlinks.template").clone().removeClass("template");
	$("a", $cmtlinks).each(function() {
		$(this).attr('data-commentid', commentid);
	});
	
	return $cmtlinks[0].outerHTML;
}
