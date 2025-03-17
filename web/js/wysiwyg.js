	
	var mentions = {
		source: function(query, process, delimiter) {
			$.getJSON('/get-usernames?name=' + query, function(data) {
				process(data);
			});
		},
		insert: function(item) {
			return '<span class="mention username">' + item.name + '</span>';
		}
	};
	
	var tinymceSettings = {
		plugins: 'paste print preview searchreplace autolink autoresize directionality visualblocks visualchars fullscreen image link media code codesample table charmap hr pagebreak nonbreaking anchor toc insertdatetime emoticons advlist lists wordcount imagetools textpattern help spoiler mention',
		toolbar: 'formatselect | bold italic strikethrough forecolor backcolor permanentpen formatpainter | link image media pageembed emoticons | alignleft aligncenter alignright alignjustify  | numlist bullist outdent indent | removeformat code | spoiler-add spoiler-remove',
		image_advtab: true,
		importcss_append: true,
		height: 400,
		image_caption: true,
		convert_urls:true,
		relative_urls:false,
		remove_script_host:false,
		tinycomments_mode: 'embedded',
		content_css: "/web/css/editor_content.css?ver=2",
		setup: function (editor) {
			editor.on('change', function(e) { 
				tinyMCE.triggerSave(); 
				var $form = $("#" + e.target.id).parents("form");
				$form.trigger('checkform.areYouSure'); 
			}); 
				editor.on('keyup', function(e) { 
				tinyMCE.triggerSave(); 
				var $form = $("#" + e.target.id).parents("form");
				$form.trigger('checkform.areYouSure');
			 });
		},
		mentions: mentions
	};
	
	var tinymceSettingsCmt = {
		menubar: false,
		plugins: 'paste searchreplace autolink autoresize directionality image link codesample charmap hr pagebreak nonbreaking anchor emoticons advlist lists wordcount imagetools textpattern help mention',
		toolbar: 'bold italic strikethrough | link image emoticons | alignleft aligncenter alignright alignjustify  | numlist bullist outdent indent | removeformat',
		image_advtab: true,
		importcss_append: true,
		min_height: 400 /* @hack: required for mobile */,
		height: 400,
		image_caption: true,
		convert_urls:true,
		relative_urls:false,
		remove_script_host:false,
		tinycomments_mode: 'embedded',
		content_css: "/web/css/editor_content.css?ver=2",
		mentions: mentions
	};
	tinymceSettingsCmt.setup = tinymceSettings.setup;
	
	
	function createEditor($elem, settings) {
		if (!settings) settings = tinymceSettings;
		
		$elem.each(function() {
			if (!this.id) {
				this.id = "editor" + Math.floor(Math.random() * 10000);
			}
			settings.selector = "#" + this.id;
			settings.auto_focus = this.id;
			
			tinyMCE.init(settings);
		});
	}
	
	function destroyEditor($elem) {
		$elem.each(function() {
			tinyMCE.remove("#" + this.id);
		});
	}
	
	function getEditorContents($elem) {
		tinyMCE.triggerSave();
		return $elem.val();
	}
	
	function setEditorContents($elem, html) {
		tinyMCE.get($elem[0].id).setContent(html);
	}
	

	$(function(){
		$('.spoiler-text').hide();
		$('.spoiler-toggle').click(function(){
			$(this).toggleClass("expanded");
			$(this).next().toggle();
		}); // end spoiler-toggle
	}); // end document ready
