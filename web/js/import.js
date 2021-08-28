var uploaded = 0;
var uploading = 0;
var dropready = false;
var pendingassets = [];



function getSchematicsHTML(result) {
	var $tpl = $(
		'<div class="pendingasset">' +
			'<div class="imgframe"><span class="helper"></span><img src="" /></div>' + 
			'<input type="hidden" name="filenames[]" value="'+result.filename+'">' + 
			'Schematic (sized ' + result.size + ') found' +	
			'<span style="padding-left:40px;">Name: <input type="text" name="schematicnames[]" value="'+result.filename.replace('.json', '')+'"></span>' +
		'</div>'
	);
	
	return $tpl;
}

function getAssetHTML(result, type) {
	var $tpl = $(
		'<div class="pendingasset">' +
			'<div class="imgframe"><span class="helper"></span><img src="" /></div>' + 
			'<input type="hidden" name="filenames[]" value="'+result.filename+'">' + 
			result.quantity + ' '+type+' found' +	
			'<span class="filename">'+result.filename+'</span>' +
		'</div>'
	);
	
	return $tpl;
}

function getIconHTML(result, type) {
	var $tpl = $(
		'<div class="pendingasset">' +
			'<div class="imgframe"><span class="helper"></span><img style="max-width:50px; float:left;" src="" /></div>' + 
			'<input type="hidden" name="filenames[]">' + 
			'<select name="'+type+'ids[]">' +
				'<option value="">??</option>'+
			'</select>' +
		'</div>'
	);
	
	$("img", $tpl).attr("src", "/tmp/" + result.filename);
	$('input[name="filenames[]"]', $tpl).val(result.filename);
	
	if (!result[type+'id']) {
		$("select", $tpl).addClass("bg-error");
	} else {
		$("select", $tpl).append('<option value="'+result[type+'id']+'">'+result.name+'</option>');
		$("select", $tpl).val(result[type+'id']);
	}
	
	$tpl.append('<span class="filename">'+result.filename+'</span>');
	
	return $tpl;
}

function refreshAssetList() {
	$list = $(".pendingassets");
	$list.html("");
	
	
	for (var i=0; i < pendingassets.length; i++) {
		if (importtype == "items" || importtype == "blocks") {
			$list.append(getAssetHTML(pendingassets[i], importtype));
		}
		if (importtype == "schematics") {
			$list.append(getSchematicsHTML(pendingassets[i]));
		}
		if (importtype == "blockicons") {
			$list.append(getIconHTML(pendingassets[i], "block"));
		}
		if (importtype == "itemicons") {
			$list.append(getIconHTML(pendingassets[i], "item"));
		}
		
		$list.append("<br>");
	}
	
	$(".pendingassetsform").toggle(pendingassets.length > 0);
}


$(document).filedrop({
	url: 'import',
	paramname: 'asset',
	data: {
		upload: 1,
		type: importtype
	},			
	error: function(err, file) {
		switch(err) {
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
				alert('file too larte')
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
	allowedfileextensions: ['.jpg','.jpeg','.png','.gif', '.json'], // file extensions allowed. Empty array means no restrictions
	maxfiles: 1000,
	maxfilesize: 1,    // max file size in MBs
	
	dragOver: function() {
		// user dragging files over #dropzone
	},
	dragLeave: function() {
		// user dragging files out of #dropzone
	},
	docOver: function() {
		// user dragging files anywhere inside the browser document window
	},
	docLeave: function() {
		// user dragging files out of the browser document window
	},
	drop: function() {
		// user drops file
		//console.log("dropped");
		
		return true;
	},
	uploadStarted: function(i, file, len){
		// a file began uploading
		// i = index => 0, 1, 2, 3, 4 etc
		// file is the actual file of the index
		// len = total files user dropped
		//console.log("started");
		uploading++;
		refreshLabels();
	},
	uploadFinished: function(i, file, response, time) {
		pendingassets.push(response);
		refreshAssetList();
		// response is the data you got back from server in JSON format.
		uploading--;
		uploaded++;
		refreshLabels();
	},
	progressUpdated: function(i, file, progress) {
		// this function is used for large files and updates intermittently
		// progress is the integer value of file being uploaded percentage to completion
	},
	globalProgressUpdated: function(progress) {
		// progress for all the files uploaded on the current instance (percentage)
		// ex: $('#progress div').width(progress+"%");
	},
	speedUpdated: function(i, file, speed) {
		// speed in kb/s
	},
	rename: function(name) {
		// name in string format
		// must return alternate name as string
	},
	beforeEach: function(file) {
		// file is a file object
		// return false to cancel upload
	},
	beforeSend: function(file, i, done) {
		// file is a file object
		// i is the file index
		// call done() to start the upload
		done();
	},
	afterAll: function() {
		// runs after all files have been uploaded or otherwise dealt with
	}
});


function refreshLabels() {
	if (uploading > 0) {
		$("p.status").html("Uploading " + uploading + " assets currently, " + uploaded + " uploaded so far");
	} else {
		$("p.status").html("All uploads done, " + uploaded + " assets uploaded so far");
	}
}