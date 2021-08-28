if(typeof lngMain == 'undefined') {
	lngMain = {
		edit_page: 'Edit page',
		doneedit_page: 'Finished editing',
		configure: 'Configure',
		configuration: 'Configuration',
		error: 'Error',
		reallydeleteelement: 'Really delete this element?',
		pleaseconfirm: 'Bitte confirm'
	};
	
	// Todo: refactor all this into above property list
	lng_uploading = "Uploading...";
	lng_completed = "completed!";
	lng_failed = 'failed.';

	lng_addfile = "Add file";
	lng_maxfilesize = "Maximum Filesize:";
	lng_renamefile = 'Rename file';
	lng_addfolder = "Add folder";
	lng_enterfolder = "Please enter a folder name";
	lng_invalidfolder = "Folder Names may only contain letters, numbers, dash (-) and underscore (_)";
	lng_folderconfirm = "Delete Folder and ALL contents in it?";
	lng_fileconfirm = "Delete this file?";
	lng_foldername = 'Folder name';

	lng_yes = 'Yes';
	lng_no = 'No';
	lng_ok = 'OK';
	lng_cancel = 'Cancel';
	lng_close = 'Close';

	lng_entername = "Please enter at least a name";
	lng_pagename = 'Page name';
	lng_pageinfo = 'Page info';
	lng_link2file = 'Link to another file / website';
	lng_filename = 'Filename';
	lng_showinmenu = 'Show item in menu';
	lng_notvisible = 'Not visible to visitors';
	lng_ident = 'Is Subpoint (ident by 10 pixel)';
	lng_notpage = 'Is not a page (only text for menu structure)';
	lng_addpage = 'Add page';

	lng_delete = 'Delete page';
	lng_delpage = 'Really delete page?<br>Notice that all Child pages<br>will also be deleted!';

	lng_editpage = 'Edit page';
	lng_rename = 'Page name';
	lng_pageurl = 'URL Alias';

	lng_titledesc = "Title/Description";

	lng_typeurl = 'Type URL:';
	lng_or = 'or';

	lng_topage = 'go to page';
	lng_savechanges = "Save changes";
	lng_save = 'Save';
	lng_cancelchanges = "Cancel";
	lng_close = 'Close';
}

Core = new CoreFunctions();

function CoreFunctions() {
	this.openDialogs = Array();
	this.dialogId = 1;
}


/***************** Dialog code **********************/
var BTN_OKCANCEL = 1;
var BTN_YESNO = 2;
var BTN_CLOSE = 3;
var BTN_SAVECANCEL = 4;
var BTN_NONE = 5;


function AlertDialog(content) {
	OpenDialog({
		title: lngMain.error,
		content: content,
		buttons: BTN_CLOSE,
		blocking: 1
	});
}

function ConfirmDialog(content, yescallback) {
	OpenDialog({
		title: lngMain.pleaseconfirm,
		content: content,
		buttons: BTN_YESNO,
		blocking: 1,
		ok_callback: yescallback
	});
}

/* Opens a dialog. Possible settings:
	title:			The dialog title
	content:		The actual html content of the dialog, this may be a jquery object 
	left:			x coordinate of the window
	top:			y coordinate of the window, if x&y not defined it will be centered and the position will be remembered in a cookie
	width:			dialog width in pixel (default: autosize)
	height:			dialog height in pixel (default: autosize)
	buttons:		Number: BTN_YESNO, BTN_OKCANCEL, BTN_SAVECANCEL, BTN_CLOSE or BTN_NONE (default: BTN_OKCANCEL)
					Object: Custom defined buttons with their callbacks
	blocking:		false if you want the dialog to be non blocking (= user can still interact with the page) (default: true)
	ok_callback:	function to be called when the user pressed Ok/Yes
	close_callback:	function to be called when the user pressed Cancel,No or Close
	autocollapse:	If true, minimizes the dialog when out of focus (default: false)
*/
/* Todo: Refactor this into a jquery plugin. */
function OpenDialog(settings) {
	var w='', h='';
	
	/*** Dialog behavior ***/
	if (settings.collapse == undefined) settings.collapse = false;
	if (settings.blocking == undefined) settings.blocking = true;
	
	if (settings.width != undefined) {
		w = 'width: ' + settings.width + 'px; ';
	}
	if (settings.height != undefined) {
		h = 'height: ' + settings.height + 'px; ';
	}
	
	if ($("#inactive").length == 0) {
		$('body').append('<div id="inactive" style="display:none"></div>');
	}
	
	$("#inactive").css('display','');

	/*** Dialog HTML ***/
	var str = 
		'<div class="dlgBox" class="adminstyles" style="' + w + h + '">' +
			'<div class="dlgTitle titleBar">' + 
				settings.title + 
				'<div class="dlgXBtn dlgBtn">X</div>' + 
				'<div class="dlgMBtn dlgBtn">_</div>' + 
			'</div>' + 
			'<hr class="dlgSep">' +
			'<div class="dlgContent">' + 
				/* Content goes here */
				'<div class="dlgBtnContainer adminstyles">' +
					'<img src="web/img/cleardot.gif" class="loadingIcon"> ' +
					//buttons +
				'</div>' +
			'</div>' +
		'</div>';

	var $dlgBox = $(str);

	/*** Button set up ***/
	var btn1 = lng_ok, btn2 = lng_cancel;
	
	switch (settings.buttons) {
		case BTN_YESNO: 
			btn1=lng_yes;
			btn2=lng_no;
			break;
		
		case BTN_SAVECANCEL: 
			btn1=lng_save;
			break;

		case BTN_CLOSE:
			btn2=lng_close;
			break;	
	}
	
	/* Construct the buttons */
	var $buttons = $('<span></span>');
	
	if (typeof settings.buttons == "object") {
		$.each(settings.buttons, function(name, callback) {
			var $btn = $('<input type="button" class="dlgButton" value="' + name + '"> ');
			// Weird encapsulation thingy to have the correct 'this' in the callback
			if(callback) {
				$dlgBox['button_cb'+name] = callback; 
				$btn.click({ name: name }, function(e) { $dlgBox['button_cb' + e.data.name]() });
			} 
			i++;
			$buttons.append($btn);
		});
	} else {
		if (settings.buttons != BTN_NONE) {
			if (settings.buttons != BTN_CLOSE)
				$buttons.append('<input type="button" class="dlgOK" value="' + btn1 + '"> ');
			$buttons.append('<input type="button" class="dlgCancel" value="' + btn2 + '">');
		}
	}

	$('.dlgContent', $dlgBox).prepend(settings.content);
	$('.dlgBtnContainer', $dlgBox).append($buttons);

	// Make dialog visible
	$("#inactive").append($dlgBox);
	
	// Focus the first field
	$('input', $dlgBox).first().focus();
	
	/* settings.blocking defines wether the user is still 
	 * allowed to interact with the site or not (blocking or non blocking dialog) 
	 */
	$('#inactive').toggleClass('blocking', settings.blocking);
	
	
	/* Get previously saved position if it is set and no custom position supplied, but limit to viewable area  */
	if (settings.top == undefined && settings.left == undefined) {
		if (localStorage.getItem("anego_dlg_" + settings.title + "_left") != null) {
			settings.left = BoundBy(localStorage.getItem("anego_dlg_" + settings.title + "_left"), 0, f_clientWidth() - $('#dlgBox').width());
			settings.top  = BoundBy(localStorage.getItem("anego_dlg_" + settings.title + "_top"), 0, f_clientHeight() - $('#dlgBox').height());
		}
	}
	
	/* Position element if any coordinate is set */
	if (settings.top != undefined || settings.left != undefined) {
		if (settings.top == undefined) settings.top = 0;
		if (settings.left == undefined) settings.left = 0;
		
		$dlgBox.css('top', settings.top);
		$dlgBox.css('left', settings.left);
	} else {
		$dlgBox.css('top', (window.innerHeight/3) + 'px');
		$dlgBox.css('left', (window.innerWidth/2 - $dlgBox.width()) + 'px');
	}
	
	
	/* Helper methods */ 
	
	$dlgBox.closeDialog = function() {
		var unblock = true;
		
		if(Core.openDialogs.length == 0)
			document.onkeydown = null;
		
		for(var i=0; i < Core.openDialogs.length; i++) {
			if (Core.openDialogs[i].dialogSettings.blocking && Core.openDialogs[i].dialogId != this.dialogId) {
				unblock = false;
			}
			
			if (Core.openDialogs[i].dialogId == this.dialogId) {
				Core.openDialogs.splice(i,1);
			}
		}
		
		if(unblock) {
			$('#inactive').removeClass('blocking');
		}
		
		this.remove();
	};
	
	// Disables dialog buttons and shows a ajax loading icon
	$dlgBox.waitResponse = function() {
		$('input[type=button]', $dlgBox).attr('disabled','disabled');
		$('.dlgBtnContainer .loadingIcon', $dlgBox).show();
	};
	
	// Resets changes from waitResponse()
	$dlgBox.endWait = function() {
		$('input[type=button]', $dlgBox).removeAttr('disabled');
		$('.dlgBtnContainer .loadingIcon', $dlgBox).hide();
	};
	
	// Store some metadata about the dialog in the jquery object
	$dlgBox.dialogSettings = settings;
	$dlgBox.dialogId = Core.dialogId++;
	$dlgBox.ok_callback = settings.ok_callback;
	$dlgBox.close_callback = settings.close_callback;
	
	Core.openDialogs.push($dlgBox);

	SetupEvents();
	
	// We're done here
	return $dlgBox;
	
	
	/* All events related to the dialog */
	function SetupEvents() {
		var dx=0, dy=0;
		var mouseDown = 0;
		
		if (Core.openDialogs.length == 1) {
			$(window).resize(function() {
				for (var i=0; i < Core.openDialogs.length; i++) {
					$dlg = Core.openDialogs[i];
					$dlg.css('top', BoundBy(parseFloat($dlg.css('top')), 3, $(window).height() - $dlg.height() - 3) + 'px');
					$dlg.css('left', BoundBy(parseFloat($dlg.css('left')),3, $(window).width() - $dlg.width() - 3) + 'px'); 
				}
			});
		}
		
		$(window).trigger('resize');
		
		/* Dialog collapse expand */
		var expand = function() {
			if(settings.height == undefined) {
				$dlgBox.css('height', 'auto');
			} else {
				$dlgBox.css('height', settings.height + 'px');
			}
			
			$dlgBox.css('backgroundColor', boxColor);
			$('.dlgTitle', $dlgBox).css('backgroundColor', headerColor);
			$('.dlgContent', $dlgBox).show();
			$('.dlgSep', $dlgBox).show();
			
			$dlgBox.css('top', BoundBy(parseFloat($dlgBox.data("prevtop")), 3, $(window).height() - $dlgBox.height() - 3) + 'px');
			$dlgBox.css('left', BoundBy(parseFloat($dlgBox.data("prefleft")),3, $(window).width() - $dlgBox.width() - 3) + 'px'); 

			$dlgBox.removeClass("minimized");
			
			//jQuery.cookie('dialogCollapseState-' + settings.title, false);
		};
		
		var collapse = function() {
			$dlgBox.css('height', '21px');
			$dlgBox.css('backgroundColor', headerColor);
			$('.dlgTitle', $dlgBox).css('backgroundColor','transparent');
			$('.dlgContent', $dlgBox).hide();
			$('.dlgSep', $dlgBox).hide();
			
			$dlgBox.data("prevtop", $dlgBox.css('top'));
			$dlgBox.data("prefleft", $dlgBox.css('left'));
			
			$dlgBox.css('top', ($(window).height() - $dlgBox.height() - 3) + 'px');
			$dlgBox.css('left', '3px'); 
			
			$dlgBox.addClass("minimized");
			
			//jQuery.cookie('dialogCollapseState-' + settings.title, true);
		};

		/* Button callbacks */
		if($dlgBox.ok_callback) {
			$('.dlgOK', $dlgBox).click(function() {
				$dlgBox.ok_callback();
			});
		}

		$('.dlgCancel', $dlgBox).click(function() {
			$dlgBox.closeDialog();
			if($dlgBox.close_callback != undefined)
				$dlgBox.close_callback();
		});

		$('.dlgXBtn', $dlgBox).click(function() {
			$dlgBox.closeDialog();
			if($dlgBox.close_callback != undefined)
				$dlgBox.close_callback();
		});
		
		$('.dlgMBtn', $dlgBox).click(function() {
			settings.collapse = !settings.collapse;
			if(settings.collapse) {
				$('.dlgMBtn', $dlgBox).html('□');
				collapse();
			} else {
				$('.dlgMBtn', $dlgBox).html('_');
				expand();
			}
		});
		
		/* Drag and Drop functionality */
		
		$('.dlgTitle', $dlgBox).mousedown(function(event) {
			mouseDown = 1;
			$dlgBox.css('margin', '0');
			dx = event.pageX - $dlgBox.css('left').substr(0, $dlgBox.css('left').length - 2);
			dy = event.pageY - $dlgBox.css('top').substr(0, $dlgBox.css('top').length - 2);
			return false;
		}); 
	
		$(document).mouseup(function(event) {
			if(mouseDown && !settings.collapse) {
				localStorage.setItem("anego_dlg_" + settings.title + "_left", $dlgBox.css('left').substr(0, $dlgBox.css('left').length - 2));
				localStorage.setItem("anego_dlg_" + settings.title + "_top", $dlgBox.css('top').substr(0, $dlgBox.css('top').length - 2));
			}
			mouseDown = 0;
		});
		
		$(document).mousemove(function(event) {
			if(mouseDown) {
				$dlgBox.css('top', BoundBy(event.pageY - dy,3, $(window).height() - $dlgBox.height() - 3) + 'px');
				$dlgBox.css('left', BoundBy(event.pageX - dx,3, $(window).width() - $dlgBox.width() - 3) + 'px'); 
			}
		});
		
		var boxColor = $dlgBox.css('backgroundColor');
		var headerColor = $('.dlgTitle', $dlgBox).css('backgroundColor');
		
		/*if (jQuery.cookie('dialogCollapseState-' + settings.title)) {
			$('.dlgMBtn', $dlgBox).trigger('click');
		}*/

		/* Autocollapse feature */
		if(settings.autocollapse) {
			$dlgBox.mouseover(collapse);
			$dlgBox.mouseout(expand);
		}
		
		if(settings.nohotkeys) return;
		
		// Keyboard interaction support (Esc and Enter)
		if( !document.onkeydown) {
			document.onkeydown = function(event) {
				// escape: 27
				// enter: 13
				if (!event) {
					event = window.event;
				}
				
				// Dispatch these to the currently focused dialog or to the last opened one
				var $dlg = null;
				for (var i=0; i < Core.openDialogs.length; i++) {
					$dlg = Core.openDialogs[i];
					if ($dlg.is(':focus')) break;
				}
				if (!$dlg) return;

				if (event.keyCode == 27 || ($dlg.dialogSettings.buttons == BTN_CLOSE && event.keyCode == 13)) {
					if($dlg.close_callback != undefined) {
						$dlg.close_callback();
					}
					$dlg.closeDialog();
				} else {
					if (event.keyCode==13 && $dlg.ok_callback != undefined) {
						$dlg.ok_callback();
					}
				}
			};
		}
	}
}

function BoundBy(x, minx, maxx) {
	return Math.min(maxx,Math.max(x,minx));
}


// Todo: Can these 4 functions be factored away through use of their respective jQuery alternatives?
function f_scrollTop() {
	return f_filterResults (
		window.pageYOffset ? window.pageYOffset : 0,
		document.documentElement ? document.documentElement.scrollTop : 0,
		document.body ? document.body.scrollTop : 0
	);
}

function f_scrollLeft() {
	return f_filterResults (
		window.pageXOffset ? window.pageXOffset : 0,
		document.documentElement ? document.documentElement.scrollLeft : 0,
		document.body ? document.body.scrollLeft : 0
	);
}

function f_clientWidth() {
	return f_filterResults (
		window.innerWidth ? window.innerWidth : 0,
		document.documentElement ? document.documentElement.clientWidth : 0,
		document.body ? document.body.clientWidth : 0
	);
}
function f_clientHeight() {
	return f_filterResults (
		window.innerHeight ? window.innerHeight : 0,
		document.documentElement ? document.documentElement.clientHeight : 0,
		document.body ? document.body.clientHeight : 0
	);
}

function f_filterResults(n_win, n_docel, n_body) {
	var n_result = n_win ? n_win : 0;
	if (n_docel && (!n_result || (n_result > n_docel)))
		n_result = n_docel;
	return n_body && (!n_result || (n_result > n_body)) ? n_body : n_result;
}
