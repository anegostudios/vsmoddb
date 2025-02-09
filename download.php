<?php

die("deprecated"); // TODO remove


error_reporting(E_ALL);
ini_set("display_errors", 1);


/*if (isset($_GET['assetid'])) {
	$assetid = intval($_GET['assetid']);
	downloadAsZip("asset-".$assetid, "files/asset/{$assetid}/"); // :ZipDownloadDisabled
	exit();
} else {*/

	if (empty($_GET['fileid'])) exit("missing fileid");
	$fileid = $_GET['fileid'];

	$file = $con->getRow("select * from file where fileid=?", array($fileid));
	if (!$file) exit("file not found");
	
	$date = $con->getOne("select date from downloadip where fileid=? and ipaddress=?", array($fileid, $_SERVER['REMOTE_ADDR']));
	$docount = false;
	if (!$date) {
		$docount = true;
		$con->Execute("insert into downloadip values (?, ?, now())", array($_SERVER['REMOTE_ADDR'], $fileid));
	} else if (strtotime($date) + 24*3600 < time()) {
		$docount = true;
		$con->Execute("update downloadip set date=now() where fileid=? and ipaddress=?", array($fileid, $_SERVER['REMOTE_ADDR']));
	}
	
	if ($docount) {
		$con->Execute("update file set downloads=downloads+1 where fileid=?", array($fileid));
		$con->Execute("update `mod` set downloads=downloads+1 where modid=(select `release`.modid from `release` where `release`.assetid=?)", array($file['assetid']));
	}
	
	downloadFile($file);
	exit();
//}

function downloadFile($file) {
	$dir = "files/asset/{$file['assetid']}/";
	$filepath = $dir . $file["filename"];
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="'.$file['filename'].'"');
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');
	header('Content-Length: ' . filesize($filepath)); //Absolute URL
	ob_clean();
	flush();
	readfile($filepath);//Absolute URL
}

function downloadAsZip($filename, $path) {
	$zipfilename = "{$filename}.zip";

	$d = dir($path);
	while (false !== ( $entry = $d->read())) {
		if (substr($entry, 0, 1) != "." && !is_dir($entry) && !strstr($entry, "_thumb."))  {
			$files[] = $entry;
		}
	}

	header( "Content-Type: application/x-zip" );
	header( "Content-Disposition: attachment; filename=\"$zipfilename\"" );

	$filespec = "";

	foreach ($files as $entry) {
		$filespec .= "\"$entry\" ";
	}

	chdir($path);

	$stream = popen( "/usr/bin/zip -q - $filespec", "r");

	if ($stream) {
		fpassthru($stream);
		fclose($stream);
	}
}

