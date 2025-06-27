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
	content_css: "/web/css/editor_content.css?ver=5",
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
	paste_postprocess: function(editor, args) {
		maybePromptForRelativeLinkRemoval(args.node);
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
	content_css: tinymceSettings.content_css,
	setup: function(editor) {
		tinymceSettings.setup(editor);
		editor.on('SetContent', function(e) {
			if(e.initial) return;

			if(e.paste && wrapNextPaste) {
				wrapNextPaste = 0;
				//TODO(Rennorb) @correctness: This won't always select the correct one, but its good enough for now.
				const spoilers  = editor.dom.select('.spoiler');
				editor.selection.setNode(spoilers[spoilers.length - 1])
				editor.selection.collapse(false);
				editor.focus();
			}
		})
	},
	paste_preprocess: function(editor, args) {
		const text = args.content;
		if(!text) return;

		if(couldBeCrashReport(text)) {
			if(confirm('Whoa there, looks like you pasted a crash report.\nShould we wrap that for you, so its easier to read for the Modder?\n\nPressing "cancel" (or the equivalent in your language) will paste the text as-is.')) {
				wrapNextPaste = 2;
			}
		}
		else if(text.length >= 1000) {
			if(confirm('Whoa there, looks like you pasted a lot of text at once.\nShould we wrap that for you, so its easier to read for the Modder?\n\nPressing "cancel" (or the equivalent in your language) will paste the text as-is.')) {
				wrapNextPaste = 1;
			}
		}
	},
	paste_postprocess: function(editor, args) {
		if(wrapNextPaste) {
			const spoiler = wrapAsSpoilerForTMCE(args.node.childNodes, wrapNextPaste === 2);
			args.node.replaceChildren(spoiler);
		}
		
		maybePromptForRelativeLinkRemoval(args.node);
	},
	mentions: tinymceSettings.mentions,
};

function maybePromptForRelativeLinkRemoval(node) {
	const relativeUrlAnchors = node.querySelectorAll('a[href^="./"], a[href^="../"], a[href^="/"][href*="/issues/"]');
	if(relativeUrlAnchors) {
		let firstHref = relativeUrlAnchors[0].getAttribute('href'); // cant use .href, that resolves the url
		let firstActualDestination;
		try { firstActualDestination = (new URL(firstHref, document.baseURI)).href; }
		catch { firstActualDestination = '<link was malformed>' }

		if(confirm(`Looks like there are relative links in that html you've just pasted in here - we found ${relativeUrlAnchors.length} such link${relativeUrlAnchors.length === 1 ? '' : 's'}.
Unless you also copied it from here, those probably wont link to the page you want.

Here is the first one and where it would link to:
	${relativeUrlAnchors[0].textContent}
	links to '${firstHref}',
	which actually resolves to '${firstActualDestination}'

Since we cannot determine where this was copied from, we cannot automatically fix the links for you - we can however at least remove them from the text.
Should we do that?

Pressing "cancel" (or the equivalent in your language) will paste the text as-is.`)) {
			for(const el of relativeUrlAnchors) {
				const span = document.createElement('span');
				span.append(...el.childNodes);
				el.parentElement.replaceChild(span, el);
			}
		}
	}
}

// 0 = dont wrap
// 1 = wrap without markup
// 2 = wrap as crash report
let wrapNextPaste = 0;

function couldBeCrashReport(text) {
	return text.includes('System.Exception') || text.includes('at Vintagestory.') || text.includes('Event Log') || text.includes('Critical error');
}

function wrapAsSpoilerForTMCE(nodes, isCrashReport) {
	const toggleEl = document.createElement('div');
	toggleEl.classList.add('spoiler-toggle');
	toggleEl.setAttribute('contenteditable', 'true')
	toggleEl.innerText = isCrashReport ? 'Crash Report' : 'Spoiler';

	const textEl = document.createElement(isCrashReport ? 'code' : 'div');
	textEl.classList.add('spoiler-text');
	textEl.setAttribute('contenteditable', 'true')
	textEl.append(...nodes)

	const wrapEl = document.createElement('div');
	wrapEl.classList.add('spoiler');
	if(isCrashReport) wrapEl.classList.add('crash-report');
	wrapEl.setAttribute('contenteditable', 'false')
	wrapEl.append(toggleEl, textEl);
	return wrapEl;
}


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
	$sel.click(function(){
		$(this).toggleClass("expanded");
	});
}

$(function() { attachSpoilerToggle($('.spoiler-toggle')); });
