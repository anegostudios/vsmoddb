<?php

//NOTE(Rennorb): The functions here have generic names as to make it possible to swap out the cdn implementation for a different one.

//NOTE(Rennorb): This is meant for local development so we don't bother with any kind of security. :NoneCDN_NoSecurity


/**
 * Generates a unique, reproducible and immutable filename for storage on the cdn.
 * This returns an _almost_ complete storage path, which is still missing the extension. This is useful because we often generate multiple variants of a file, and we would need to split the returned path otherwise. We therefore simply return a path without extension, and the caller assembles the final path(s).
 * 
 * @param int    $userId The id of the user that owns the file.
 * @param string $localPath
 * @param string $originalFileBasename
 * @return string
 */
function generateCdnFileBasenameWithPath($userId, $localPath, $originalFileBasename)
{
	//NOTE(Rennorb): For local storage we just use the filename, no need to do magic and it makes it easier to track and test things.
	// In theory this can cause collisions, but we don't really care for testing and simplicity is a priority.
	return "$userId/$originalFileBasename";
}


/**
 * @param string $localPath
 * @param string $cdnPath
 * @return array{error : false|string}
 */
function uploadToCdn($localPath, $cdnPath) {
	$destination = "files/$cdnPath";
	$path = pathinfo($destination, PATHINFO_DIRNAME);
	if(!is_dir($path)) {
		mkdir($path, 0777, true);
	}
	$ok = copy($localPath, $destination); // :NoneCDN_NoSecurity
	return ['error' => $ok ? false : 'Unknown error during file "upload".'];
}


/**
 * @param string $cdnPath
 * @return null|array{error:string}
 */
function deleteFromCdn($cdnPath) {
	global $config;

	$ok = @unlink($config['basepath']."files/$cdnPath");
	
	return ['error' => $ok ? false : 'Unknown error during file removal.'];
}


/**
 * Formats a "normal" url to the file.
 * This url is meant to be used for in-browser resources, e.g. a image to be placed onto a page, as compared to a download link for that image.
 * 
 * @param array{cdnPath: string} $file A file database row
 * @param string $filenamepostfix a postfix applied to the file basename. Can be used to format thumbnail urls.
 * @return string
 */
function formatCdnUrl($file, $filenamePostfix = '') {
	$url = formatCdnUrlFromCdnPath($file['cdnPath'], $filenamePostfix);

	// Evil hackery to test for the case where we use this internally to feed get_file_contents, where we cannot just pass a url fragment.
	$trace = debug_backtrace(0, 2);
	$caller = $trace[1]['function'];
	if($caller === 'cropLogoImageAndUploadToCDN' || $caller === 'migrateImageSizes') {
		global $config;
		return $config['basepath'].'files/'.substr($url, 8 /* /cdnfile */);
	}

	return $url;
}

/**
 * Formats a "normal" url to the file.
 * This url is meant to be used for in-browser resources, e.g. a image to be placed onto a page, as compared to a download link for that image.
 * 
 * @param string|array{cdnPath: string} $file Either a file database row or the cdnpath directly;
 * @param string $filenamePostfix a postfix applied to the file basename. Can be used to format thumbnail urls.
 * @return string
 */
function formatCdnUrlFromCdnPath($cdnPath, $filenamePostfix = '') {
	$basePath = '/cdnfile';

	if($filenamePostfix) {
		splitOffExtension($cdnPath, $pathnoext, $ext);
		if($ext === '') {
			return "{$basePath}/{$cdnPath}{$filenamePostfix}"; // should never happen in reality, but just in case
		}

		return "{$basePath}/{$pathnoext}{$filenamePostfix}.{$ext}";
	}
	else {
		return "{$basePath}/{$cdnPath}";
	}
}

/**
 * Formats a download link to the file.
 * This url is meant to enforce that the enduser gets prompted to download the file, as compared to a "normal" link which might just display the file in browser.
 * 
 * @param array{cdnPath: string, name:string} $file
 * @return string
 */
function formatCdnDownloadUrl($file) {
	@list($path, $name) = explode('/', $file['cdnPath'], 2); // suppress errors when loading data from other cdns locally for testing
	$name = urlencode($name);
	return "/cdndl/{$path}/{$name}";
}




//
// "download" handler 
//

{
	$path_parts = explode('/', substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1), 2);
	if($path_parts[0] === 'cdndl') {
		$filepath = $config["basepath"] . "files/" . urldecode($path_parts[1]); // easy path traversal, we don't care. :NoneCDN_NoSecurity
		
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
		$filepath = $config["basepath"] . "files/" . urldecode($path_parts[1]); // easy path traversal, we don't care. :NoneCDN_NoSecurity

		$type = mime_content_type($filepath);
		header('Content-Type: '.$type);

		readfile($filepath);
		exit();
	}
}
