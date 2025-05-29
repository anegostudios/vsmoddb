var tinymceSettings = {
	//NOTE(Rennorb): TinyMCE mobile has a whitelist for plugins, so if we want specific ones we need to use the external_plugins directive.
	// Better practice either way, became it makes updating TinyMCE a lot easer.
	plugins: 'paste print preview searchreplace autolink autoresize directionality visualblocks visualchars fullscreen image link media code codesample table charmap hr pagebreak nonbreaking anchor toc insertdatetime emoticons advlist lists wordcount imagetools textpattern help spoiler mention noneditable',
	external_plugins: {
		'mention': '/web/js/tinymce-custom/plugins/mention/plugin.min.js',
	},
	toolbar: 'formatselect | bold italic strikethrough forecolor backcolor permanentpen formatpainter | link image media pageembed emoticons | alignleft aligncenter alignright alignjustify  | numlist bullist outdent indent | removeformat code | spoiler-add spoiler-remove',
	toolbar_sticky: true,
	image_advtab: true,
	importcss_append: true,
	height: 400,
	image_caption: true,
	convert_urls:true,
	relative_urls:false,
	remove_script_host:false,
	tinycomments_mode: 'embedded',
	content_css: "/web/css/editor_content.css?ver=4",
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
	mentions: {
		source: function(query, process, delimiter) {
			if(!query) return;
	
			$.getJSON('/api/v2/users/by-name/' + encodeURIComponent(query), function(data) {
				process(Object.entries(data));
			});
		},
		queryBy: 1,
		insert: function(item) {
			return `<a class="mention username mceNonEditable" data-user-hash="${item[0]}" href="/show/user/${item[0]}">${item[1]}</a>`;
		}
	},
};

var tinymceSettingsCmt = {
	menubar: false,
	plugins: 'paste searchreplace autolink autoresize directionality image link codesample charmap hr pagebreak nonbreaking anchor emoticons advlist lists wordcount imagetools textpattern help spoiler mention noneditable',
	external_plugins: tinymceSettings.external_plugins,
	toolbar: 'bold italic strikethrough | link image emoticons | alignleft aligncenter alignright alignjustify  | numlist bullist outdent indent | removeformat | spoiler-add spoiler-remove',
	toolbar_sticky: true,
	image_advtab: true,
	importcss_append: true,
	min_height: 400 /* @hack: required for mobile */,
	height: 400,
	image_caption: true,
	convert_urls:true,
	relative_urls:false,
	remove_script_host:false,
	tinycomments_mode: 'embedded',
	content_css: "/web/css/editor_content.css?ver=4",
	setup: tinymceSettings.setup,
	mentions: tinymceSettings.mentions,
};


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

function attachSpoilerToggle($sel) {
	$sel.each(function(_, e) {
		if(!e.classList.contains('expanded')) $(e).next().hide();
	})
	.click(function(){
		$(this).toggleClass("expanded");
		$(this).next().toggle();
	});
}

$(function() { attachSpoilerToggle($('.spoiler-toggle')); });
