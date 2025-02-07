<?php

//NOTE(Rennorb): The functions here have generic names as to make it possible to swap out the cdn implementation for a different one.


/* Config options: 

$config["assetserver"] = "https://abcd.b-cdn.net";
// storage.bunnycdn.com = de
// otherwise {uk, ny, la, sg, se, br, jh}.storage.bunnycdn.com
$config["bunnyendpoint"] = "storage.bunnycdn.com";
$config["bunnyzone"] = "abcd";
$config["bunnykey"] = "aaaaaaaa-bbbb-cccc-dddddddddddd-eeee-ffff";

*/



//NOTE(Rennorb): Make sure to add an Edge Rule on bunny with the following content:
/*
	Actions:
	- Force download
	- Set Response Header -> attachment; filename="%{Query.dl}"

	IF:
	- Query String matches ?dl=*
*/
// otherwise download links will not work properly.


/**
 * Generates a unique, reproducible and immutable filename for storage on the cdn.
 * This is a somewhat expensive operation.
 * 
 * @param int    $userid The id of the user that owns the file.
 * @param string $localpath
 * @param string $originalfilebasename
 * @param string $originalfileextension
 * @return string
 */
function generateCdnFileBasename($userid, $localpath, $originalfilebasename)
{
	$h = hash_init('md5', HASH_HMAC, $userid);
	hash_update_file($h, $localpath);
	return substr($originalfilebasename, 0, 10).'_'.hash_final($h, false);
}


/**
 * @param string $localpath
 * @param string $cdnpath
 * @return array{error : false|string}
 */
function uploadToCdn($localpath, $cdnpath) {
	global $config;

	$url = "https://{$config['bunnyendpoint']}/{$config['bunnyzone']}/{$cdnpath}";

	$curl = curl_init();
	curl_setopt_array($curl, [
		CURLOPT_URL => $url,
		CURLOPT_PUT => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_INFILE => fopen($localpath, 'rb'),
		CURLOPT_INFILESIZE => filesize($localpath),
		CURLOPT_HTTPHEADER => [
			"AccessKey: {$config['bunnykey']}",
			'Content-Type: application/octet-stream',
		],
	]);

	$response = curl_exec($curl);

	if(!$response) {
		$result = ['error' => curl_error($curl)];
	}
	else {
		$result = ['error' => false];
	}

	curl_close($curl);
	return $result;
}


/**
 * @param string $cdnpath
 * @return null|array{error:string}
 */
function deleteFromCdn($cdnpath) {
	global $config;

	$url = "https://{$config['bunnyendpoint']}/{$config['bunnyzone']}/{$cdnpath}";

	$curl = curl_init();
	curl_setopt_array($curl, [
		CURLOPT_URL => $url,
		CURLOPT_CUSTOMREQUEST => 'DELETE',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => [
			"AccessKey: {$config['bunnykey']}",
		],
	]);

	$response = curl_exec($curl);

	if(!$response) {
		$result = ['error' => curl_error($curl)];
	}
	else {
		$result = ['error' => false];
	}

	curl_close($curl);
	return $result;
}


/**
 * Formats a "normal" url to the file.
 * This url is meant to be used for in-browser resources, e.g. a image to be placed onto a page, as compared to a download link for that image.
 * 
 * @param array{cdnpath: string, ext: string} $file
 * @param string $filenamepostfix a postfix applied to the file basename. Can be used to format thumbnail urls.
 * @return string
 */
function formatUrl($file, $filenamepostfix = '') {
	global $config;

	return "{$config['assetserver']}/{$file['cdnpath']}{$filenamepostfix}.{$file["ext"]}";
}

/**
 * Formats a download link to the file.
 * This url is meant to enforce that the enduser gets prompted to download the file, as compared to a "normal" link which might just display the file in browser.
 * 
 * @param array{cdnpath: string, ext: string, filename:string} $file
 * @param string $actiontoken TODO, probably deprecated
 * @return string
 */
function formatDownloadUrl($file, $actiontoken) {
	global $config;

	return "{$config['assetserver']}/{$file['cdnpath']}.{$file["ext"]}?dl={$file['filename']}&at=$actiontoken";
}
