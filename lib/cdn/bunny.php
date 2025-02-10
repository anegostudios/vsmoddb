<?php

//NOTE(Rennorb): The functions here have generic names as to make it possible to swap out the cdn implementation for a different one.


/* Config options: 

$config["assetserver"] = "https://abcd.b-cdn.net";
// storage.bunnycdn.com = de
// otherwise {uk, ny, la, sg, se, br, jh}.storage.bunnycdn.com
$config["bunnyendpoint"] = "storage.bunnycdn.com";
$config["bunnyzone"] = "abcd";
$config["bunnyzoneid"] = "12345"; // for log processing
$config["bunnykey"] = "aaaaaaaa-bbbb-cccc-dddddddddddd-eeee-ffff";
$config["bunnyapikey"] = "aaaaaaaa-bbbb-cccc-ddddddddddddxxxxxxxxxxxxxxxxxx-eeee-ffff"; // for log processing

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
// Additionally set the expiration date to Never. Files are immutable.


/**
 * Generates a unique, reproducible and immutable filename for storage on the cdn.
 * This is a somewhat expensive operation.
 * 
 * This returns an _almost_ complete storage path, which is still missing the extension. This is useful because we often generate multiple variants of a file, and we would need to split the returned path otherwise. We therefore simply return a path without extension, and the caller assembles the final path(s).
 * 
 * @param int    $userid The id of the user that owns the file.
 * @param string $localpath
 * @param string $originalfilebasename
 * @return string
 */
function generateCdnFileBasenameWithPath($userid, $localpath, $originalfilebasename)
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
 * @param string|array{cdnpath: string} $file Either a file database row or the cdnpath directly;
 * @param string $filenamepostfix a postfix applied to the file basename. Can be used to format thumbnail urls.
 * @return string
 */
function formatCdnUrl($file, $filenamepostfix = '') {
	return formatCdnUrlFromCdnPath($file['cdnpath'], $filenamepostfix);
}

/**
 * Formats a "normal" url to the file.
 * This url is meant to be used for in-browser resources, e.g. a image to be placed onto a page, as compared to a download link for that image.
 * 
 * @param string|array{cdnpath: string} $file Either a file database row or the cdnpath directly;
 * @param string $filenamepostfix a postfix applied to the file basename. Can be used to format thumbnail urls.
 * @return string
 */
function formatCdnUrlFromCdnPath($cdnpath, $filenamepostfix = '') {
	global $config;

	if($filenamepostfix) {
		splitOffExtension($cdnpath, $pathnoext, $ext);
		if($ext === '') {
			return "{$config['assetserver']}/{$cdnpath}{$filenamepostfix}"; // should never happen in reality, but just in case
		}

		return "{$config['assetserver']}/{$pathnoext}{$filenamepostfix}.{$ext}";
	}
	else {
		return "{$config['assetserver']}/{$cdnpath}";
	}
}

/**
 * Formats a download link to the file.
 * This url is meant to enforce that the enduser gets prompted to download the file, as compared to a "normal" link which might just display the file in browser.
 * 
 * @param array{cdnpath: string, filename:string, fileid:int} $file
 * @return string
 */
function formatCdnDownloadUrl($file) {
	global $config;

	// dl -> used for download name in edge rules
	return "{$config['assetserver']}/{$file['cdnpath']}?dl={$file['filename']}";
}


/**
 * @param DateTimeImmutable $date
 */
function bunny_pullLogsAndUpdateDownloadNumbers($date)
{
	global $con, $config;

	$curl = curl_init("https://logging.bunnycdn.com/".$date->format('m-d-y')."/{$config['bunnyzoneid']}.log");
	curl_setopt_array($curl, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => [ 'AccessKey: '.$config['bunnyapikey'] ],
	]);

	$response = curl_exec($curl);
	
	if(!$response) {
		curl_close($curl);
		return;
	}
	curl_close($curl);


	//HIT|200|1739040399423|303521|3304896|87.139.167.0|https://rennorb-test.b-cdn.net/|https://rennorb-test.b-cdn.net/chad_.png|DE|Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:134.0) Gecko/20100101 Firefox/134.0|db940c5b26d2ec2beb26fe007b00d917|DE
	// https://support.bunny.net/hc/en-us/articles/115001917451-bunny-net-CDN-raw-log-format-explained
	foreach(explode('\n', $response) as $line) {
		$parts = explode('|', $line);
		
		$statuscode = $parts[1];
		if($statuscode != 200) continue;

		$url = parse_url($parts[5]);
		if(!startsWith($url['query'], '?dl=')) continue;
		
		$cdnpath = substr($url['path'], 1);
		$time = intval($parts[2]) / 1000;
		$date = date(SQL_DATE_FORMAT, $time);
		$remoteip = $parts[3];


		$file = $con->getRow("select * from file where cdnpath=?", array($cdnpath));
		if (!$file) exit("file not found");
		$fileid = $file['fileid'];

		$olddate = $con->getOne("select date from downloadip where fileid=? and ipaddress=?", array($fileid, $remoteip));
		$docount = false;
		if (!$olddate) {
			$docount = true;
			$con->Execute("insert into downloadip values (?, ?, ?)", array($remoteip, $fileid, $date));
		} else if (strtotime($olddate) + 24*3600 < $time) {
			$docount = true;
			$con->Execute("update downloadip set date=? where fileid=? and ipaddress=?", array($date, $fileid, $remoteip));
		}

		if ($docount) {
			$con->Execute("update file set downloads=downloads+1 where fileid=?", array($fileid));
			$con->Execute("update `mod` set downloads=downloads+1 where modid=(select `release`.modid from `release` where `release`.assetid=?)", array($file['assetid']));
		}
	}
}
