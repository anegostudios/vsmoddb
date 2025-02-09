<?php

//NOTE(Rennorb): The functions here have generic names as to make it possible to swap out the cdn implementation for a different one.

//NOTE(Rennorb): This is meant for local development so we don't bother with any kind of security. :NoneCDN_NoSecurity


/**
 * Generates a unique, reproducible and immutable filename for storage on the cdn.
 * 
 * @param int    $userid The id of the user that owns the file.
 * @param string $localpath
 * @param string $originalfilebasename
 * @return string
 */
function generateCdnFileBasenameWithPath($userid, $localpath, $originalfilebasename)
{
	//NOTE(Rennorb): For local storage we just use the filename, no need to do magic and it makes it easier to track and test things.
	return "$userid/$originalfilebasename";
}


/**
 * @param string $localpath
 * @param string $cdnpath
 * @return array{error : false|string}
 */
function uploadToCdn($localpath, $cdnpath) {
	$destination = "files/$cdnpath";
	$path = pathinfo($destination, PATHINFO_DIRNAME);
	if(!is_dir($path)) {
		mkdir($path, 0777, true);
	}
	$ok = copy($localpath, $destination); // :NoneCDN_NoSecurity
	return ['error' => $ok ? false : 'Unknown error during file "upload".'];
}


/**
 * @param string $cdnpath
 * @return null|array{error:string}
 */
function deleteFromCdn($cdnpath) {
	global $config;

	$ok = unlink($config['basepath']."files/$cdnpath");
	return ['error' => $ok ? false : 'Unknown error during file removal.'];
}


/**
 * Formats a "normal" url to the file.
 * This url is meant to be used for in-browser resources, e.g. a image to be placed onto a page, as compared to a download link for that image.
 * 
 * @param string|array{cdnpath: string} $file Either a file database row or the cdnpath directly;
 * @param string $filenamepostfix a postfix applied to the file basename. Can be used to format thumbnail urls.
 * @return string
 */
function formatUrl($file, $filenamepostfix = '') {
	return formatUrlFromCdnPath($file['cdnpath'], $filenamepostfix);
}

/**
 * Formats a "normal" url to the file.
 * This url is meant to be used for in-browser resources, e.g. a image to be placed onto a page, as compared to a download link for that image.
 * 
 * @param string|array{cdnpath: string} $file Either a file database row or the cdnpath directly;
 * @param string $filenamepostfix a postfix applied to the file basename. Can be used to format thumbnail urls.
 * @return string
 */
function formatUrlFromCdnPath($cdnpath, $filenamepostfix = '') {
	$basepath = '/cdnfile';

	if($filenamepostfix) {
		splitOffExtension($cdnpath, $pathnoext, $ext);
		if($ext === '') {
			return "{$basepath}/{$cdnpath}{$filenamepostfix}"; // should never happen in reality, but just in case
		}

		return "{$basepath}/{$pathnoext}{$filenamepostfix}.{$ext}";
	}
	else {
		return "{$basepath}/{$cdnpath}";
	}
}

/**
 * Formats a download link to the file.
 * This url is meant to enforce that the enduser gets prompted to download the file, as compared to a "normal" link which might just display the file in browser.
 * 
 * @param array{cdnpath: string, filename:string} $file
 * @return string
 */
function formatDownloadUrl($file) {
	return "/download/{$file['cdnpath']}";
}




//
// "download" handler 
//

{
	$path_parts = explode('/', substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1), 2);
	if($path_parts[0] === 'download') {
		$filepath = $config["basepath"] . "files/" . $path_parts[1]; // easy path traversal, we don't care. :NoneCDN_NoSecurity
		
		// copy paste from the old dl code
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment');
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . filesize($filepath)); //Absolute URL

		readfile($filepath); //Absolute URL
		exit();
	}
	else if($path_parts[0] === 'cdnfile') {
		$filepath = $config["basepath"] . "files/" . $path_parts[1]; // easy path traversal, we don't care. :NoneCDN_NoSecurity

		$type = mime_content_type($filepath);
		header('Content-Type: '.$type);

		readfile($filepath);
		exit();
	}
}
