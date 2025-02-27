<?php
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');

global $config, $con, $view;

include($config["basepath"] . "lib/ErrorHandler.php");
ErrorHandler::setupErrorHandling();


include($config["basepath"] . "lib/timezones.php");
include($config["basepath"] . "lib/View.php");
include($config["basepath"] . "lib/img.php");
include($config["basepath"] . "lib/tags.php");
include($config["basepath"] . "lib/3rdparty/adodb5/adodb-exceptions.inc.php");
include($config["basepath"] . "lib/3rdparty/adodb5/adodb.inc.php");

include($config["basepath"] . "lib/asset.php");
include($config["basepath"] . "lib/assetcontroller.php");
include($config["basepath"] . "lib/assetlist.php");
include($config["basepath"] . "lib/asseteditor.php");
include($config["basepath"] . "lib/fileupload.php");


$rd = opendir($config["basepath"] . "lib/assetimpl");
while (($file = readdir($rd))) {
	if (endsWith($file, ".php")) {
		include($config["basepath"] . "lib/assetimpl/" . $file);
	}
}

//mysqli_report(MYSQLI_REPORT_ERROR);
$con = createADOConnection($config);
$view = new View();

$view->assign("fileuploadmaxsize", round(file_upload_max_size() / 1024 / 1024, 1));
$view->assign("assetserver", $config['assetserver']);

$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;



// insert db record
function insert($tablename, $recordid = null, $con = null)
{
	if (!$con) $con = $GLOBALS['con'];

	$con->Execute("insert into `{$tablename}` (created) values (now())");

	return $con->Insert_ID();
}

// update db record
function update($tablename, $recordid, $data, $con = null)
{
	if (!$con) $con = $GLOBALS['con'];

	$columnnames = array();
	$values = array();
	foreach ($data as $columnname => $value) {
		array_push($columnnames, "`{$columnname}`= ?");
		array_push($values, $data[$columnname]);
	}

	$updatessql = "
			update `{$tablename}` set " . join(", ", $columnnames) . " where {$tablename}id = ?";

	return $con->Execute($updatessql, array_merge(
		$values,
		array($recordid)
	));
}

// delete db record
function delete($tablename, $recordid)
{
	global $con;

	$con->Execute("delete from `{$tablename}` where `{$tablename}id` = ?", array($recordid));
}



function print_p($var)
{
	echo "<pre>";
	print_r($var);
	echo "</pre>";
}

function dump_die($var)
{
	echo "<pre>";
	var_dump($var);
	echo "</pre>";

	die();
}


function endsWith($string, $part) //TODO(Rennorb)  @perf: use str_ends_with() instead, if we can get a newer version of php
{
	return mb_strlen($string) >= mb_strlen($part) && mb_substr($string, mb_strlen($string) - mb_strlen($part)) == $part;
}

function startsWith($string, $part) //TODO(Rennorb)  @perf: use str_starts_with() instead, if we can get a newer version of php
{
	return mb_substr($string, 0, mb_strlen($part)) == $part;
}

function isNumber($val)
{
	return intval($val) . "" == $val;
}

function isUrl($url)
{
	return strlen(filter_var($url, FILTER_VALIDATE_URL));
}

function sanitizeHtml($text)
{
	global $config;
	include_once($config["basepath"] . "lib/3rdparty/htmLawed.php");
	
	$key = urlencode(genToken());

	$text = preg_replace("#<iframe( src=\"//www.youtube.com/embed/[a-zA-Z0-9]{1,20}\" width=\"[0-9]+\" height=\"[0-9]+\" allowfullscreen=\"allowfullscreen\")></iframe>#i", "<span class=\"__embed{$key}\">\\1</span>", $text);
	
	$text = htmLawed($text, array('tidy' => 0, 'safe' => 1, 'elements' => '* -script -object -applet -canvas -iframe -video -audio -embed'));

	$text = preg_replace("#<span class=\"__embed{$key}\">(.*)</span>#i", "<iframe \\1></iframe>", $text);

	return $text;
}




function createADOConnection($config, $persistent = true)
{
	$con = ADONewConnection("mysqli");

	$result = $con->NConnect($config["databasehost"], $config["databaseuser"], $config["databasepassword"], $config["database"]);

	if (!$result) {
		throw new Exception("Error connecting to database. " . $con->_errorMsg);
		die();
	}

	return $con;
}

function getURLPath()
{
	global $con, $language;

	$scripturl = $_SERVER['REQUEST_URI'];
	if (strstr($scripturl, "?")) {
		$scripturl = substr($scripturl, 0, strpos($scripturl, "?"));
	}
	$urlcode = substr($scripturl, 1);


	return $urlcode;
}

function forceRedirectAfterPOST()
{
	header('Location: '.$_SERVER['REQUEST_URI'], true, 303);
}


function genToken()
{
	return base64_encode(openssl_random_pseudo_bytes(32));
}

function createPasswordHash($password)
{
	// http://php.net/manual/en/function.password-hash.php
	return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPasswordHash($password, $hash)
{
	return password_verify($password, $hash);
}


function logAssetChanges($changes, $assetid)
{
	global $con, $user;

	if (!empty($changes)) {
		$changelogdb = $con->getRow("select * from changelog order by created desc limit 1");
		$changelogid = 0;
		if ($changelogdb && $changelogdb["assetid"] == $assetid && $changelogdb["userid"] == $user["userid"]) {
			$changesdb = explode("\r\n", $changelogdb["text"]);
			$changelogid = $changelogdb["changelogid"];

			$changes = array_unique(array_merge($changes, $changesdb));
		}

		if (!$changelogid) {
			$changelogid = insert("changelog");
		}

		update("changelog", $changelogid, array("assetid" => $assetid, "userid" => $user["userid"], "text" => implode("\r\n", $changes)));
	}
}


const MODACTION_KIND_BAN    = 1;
const MODACTION_KIND_DELETE = 2;
const MODACTION_KIND_EDIT   = 3;
const MODACTION_KIND_REDEEM = 4;


/**
 * @param int kind
 * @return string
 */
function stringifyModactionKind($kind)
{
	switch($kind) {
		case MODACTION_KIND_BAN   : return "Ban";
		case MODACTION_KIND_DELETE: return "Delete";
		case MODACTION_KIND_EDIT  : return "Edit";
		case MODACTION_KIND_REDEEM: return "Redeem";
		default: return strval($kind);
	}
}

const SQL_DATE_FOREVER = "9999-12-31";
const SQL_DATE_FORMAT = "Y-m-d H:i:s";


/**
 * @param int            $targetuserid
 * @param int            $moderatoruserid
 * @param MODACTION_KIND $kind
 * @param string         $until
 * @param string|null    $reason
 * @return int generated modaction id
 */
function logModeratorAction($targetuserid, $moderatoruserid, $kind, $until, $reason)
{
	global $con;
	$con->Execute("insert into moderationrecord (targetuserid, moderatorid, kind, until, reason) values (?, ?, ?, ?, ?)", array($targetuserid, $moderatoruserid, $kind, $until, $reason));
	return intval($con->getOne("select LAST_INSERT_ID()"));
}


function logError($str)
{
	logLine($str, "logs/error.txt");
}


function logLine($str, $debugfile)
{
	if (!is_writable($debugfile) || !file_exists($debugfile)) {
		return;
	}

	$fp = fopen($debugfile, 'a');
	fwrite($fp, date('d.m.Y H:i:s: ') . $str . "\n");
	fclose($fp);
}


function fullDate($sqldate)
{
	return date("M jS Y, H:i:s", strtotime($sqldate));
}

function timelessDate($sqldate)
{
	return date("M jS Y", strtotime($sqldate));
}

/**
 * @param string $str
 * @return DateTimeImmutable|false
 * 
 * Expects the string to be in current timezone, SQL_DATE_FORMAT.
 */
function parseSqlDateTime($str)
{
	return DateTimeImmutable::createFromFormat(SQL_DATE_FORMAT, $str);
}

/**
 * @param DateTimeInterface $date
 * @param string $format
 * @param string $forevertext
 * @return string
 * 
 * Used for formatting moderation related dates. a user might be banned "forever" which is represented as the year 9999.
 */
function formatDateWhichMightBeForever($date, $format = "M jS Y, H:i:s", $forevertext = "forever")
{
	$year = $date->format("Y"); // unfortunately no way to get the year directly.
	return startsWith($year, "9999") ? $forevertext : $date->format($format);
}

function fancyDate($sqldate) //TODO(Rennorb): support for future dates
{
	if (empty($sqldate)) return "-";
	$timestamp = strtotime($sqldate);
	$localtimestamp = getLocalTimeStamp($timestamp);

	$seconds = time() - $timestamp;
	$strdate = date("M jS Y, H:i:s", $localtimestamp);

	if ($seconds >= 0 && $seconds < 7 * 24 * 3600) {
		$minutes = intval($seconds / 60);
		$hours = intval($seconds / 3600);
		$days = intval($seconds / 3600 / 24);

		if ($days < 1) {
			if ($seconds <= 60) {
				return '<span title="' . $strdate . '">' . $seconds . " seconds ago</span>";
			}
			if ($hours < 1) {
				if ($minutes == 1) return '<span title="' . $strdate . '">1 minute ago</span>';
				return '<span title="' . $strdate . '">' . $minutes . ' minutes ago</span>';
			}

			if ($hours == 1) return '<span title="' . $strdate . '">1 hour ago</span>';
			return '<span title="' . $strdate . '">' . $hours . " hours ago</span>";
		} else {
			if ($days == 1) return '<span title="' . $strdate . '">1 day ago</span>';
			return '<span title="' . $strdate . '">' . $days . " days ago</span>";
		}
	}

	if (date("Y", $localtimestamp) != date("Y")) {
		return '<span title="' . $strdate . '">' . date("M jS Y \\a\\t g:i A", $localtimestamp) . '</span>';
	} else {
		return '<span title="' . $strdate . '">' . date("M jS \\a\\t g:i A", $localtimestamp) . '</span>';
	}
}


function getLocalTimeStamp($timestamp)
{
	global $user, $timezones;

	$local_tz = new DateTimeZone(date_default_timezone_get());
	$localtime = new DateTime('now', $local_tz);

	//NY is 3 hours ahead, so it is 2am, 02:00
	$userzone = date_default_timezone_get();

	if ($user && !empty($timezones[$user["timezone"]])) {
		$userzone = $timezones[$user["timezone"]];
	}

	$user_tz = new DateTimeZone($userzone);
	$usertime = new DateTime('now', $user_tz);

	$local_offset = $localtime->getOffset() / 3600;
	$user_offset = $usertime->getOffset() / 3600;

	$hourdiff = $user_offset - $local_offset;

	return $timestamp + 3600 * $hourdiff;
}


function autoFormat($html)
{
	// http:///..... => Create a link from it
	$html = linkify($html, 1);
	// [spoiler] 
	$html = preg_replace("/\[spoiler\]\s*(.*)\s*\[\/spoiler\]/Us", "<p><a href=\"#\" class=\"spoiler\">Spoiler</a></p><div class=\"spoiler\">\\1</div>", $html);

	// Fix mention css issue caused by the editor
	$html = preg_replace("/<span class=\"mention username\">([\w\d_\-]+)([^\w\d_\-])(.*)<\/span>/U", "<span class=\"mention username\">\\1</span>\\2\\3", $html);

	return $html;
}


function linkify($value, $showimg = 1, $protocols = array('http', 'mail', 'https'), array $attributes = array('target' => '_blank'))
{
	// Link attributes
	$attr = '';
	foreach ($attributes as $key => $val) {
		$attr = ' ' . $key . '="' . htmlentities($val) . '"';
	}

	$links = array();

	// Extract existing links and tags
	$value = preg_replace_callback('~(<a .*?>.*?</a>|<.*?>)~i', function ($match) use (&$links) {
		return '<' . array_push($links, $match[1]) . '>';
	}, $value);

	// Extract text links for each protocol
	foreach ((array)$protocols as $protocol) {
		switch ($protocol) {
			case 'http':
			case 'https':
				$value = preg_replace_callback(
					'~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i',
					function ($match) use ($protocol, &$links, $attr, $showimg) {
						if ($match[1]) {
							$protocol = $match[1];
							$link = $match[2] ?: $match[3];
							// Youtube
							if ($showimg == 1) {
								if (strpos($link, 'youtube.com') > 0 || strpos($link, 'youtu.be') > 0) {
									$parts = explode('=', $link);
									$link = '<iframe width="100%" height="315" src="https://www.youtube.com/embed/'.end($parts).'?rel=0&showinfo=0&color=orange&iv_load_policy=3" frameborder="0" allowfullscreen></iframe>';
									return '<' . array_push($links, $link) . '></a>';
								}
								if (strpos($link, '.png') > 0 || strpos($link, '.jpg') > 0 || strpos($link, '.jpeg') > 0 || strpos($link, '.gif') > 0 || strpos($link, '.bmp') > 0) {
									return '<' . array_push($links, "<a $attr href=\"$protocol://$link\" class=\"htmllink\"><img src=\"$protocol://$link\" class=\"htmlimg\">") . '></a>';
								}
							}
							return '<' . array_push($links, "<a $attr href=\"$protocol://$link\" class=\"htmllink\">$link</a>") . '>';
						}
					},
					$value
				);
				break;
			case 'mail':
				$value = preg_replace_callback('~([^\s<]+?@[^\s<]+?\.[^\s<]+)(?<![\.,:])~', function ($match) use (&$links, $attr) {
					return '<' . array_push($links, "<a $attr href=\"mailto:{$match[1]}\" class=\"htmllink\">{$match[1]}</a>") . '>';
				}, $value);
				break;
			case 'twitter':
				$value = preg_replace_callback('~(?<!\w)[@#](\w++)~', function ($match) use (&$links, $attr) {
					return '<' . array_push($links, "<a $attr href=\"https://twitter.com/" . ($match[0][0] == '@' ? '' : 'search/%23') . $match[1]  . "\" class=\"htmllink\">{$match[0]}</a>") . '>';
				}, $value);
				break;
			default:
				$value = preg_replace_callback('~' . preg_quote($protocol, '~') . '://([^\s<]+?)(?<![\.,:])~i', function ($match) use ($protocol, &$links, $attr) {
					return '<' . array_push($links, "<a $attr href=\"$protocol://{$match[1]}\" class=\"htmllink\">{$match[1]}</a>") . '>';
				}, $value);
				break;
		}
	}

	// Insert all link
	return preg_replace_callback('/<(\d+)>/', function ($match) use (&$links) {
		return $links[$match[1] - 1];
	}, $value);
}



function sendPostData($path, $data, $remoteurl = null)
{
	global $config;

	if ($remoteurl == null) {
		$remoteurl = "https://" . $config["authserver"] . "/" . $path;
	} else {
		$remoteurl = $remoteurl . "/" . $path;
	}

	$httpopts = array(
		"http" => array(
			"method"  => "POST",
			"header"  => "Content-type: application/x-www-form-urlencoded" . "\r\n",
			"content" => http_build_query($data)
		)
	);

	$context = stream_context_create($httpopts);
	$result = file_get_contents($remoteurl, false, $context);

	if (!empty($GLOBALS["authDebug"])) {
		echo "request sent. Result is";
		print_p($result);
	}

	return $result;
}


/**
 * @param string $filepath
 * @return array{modparse:'error', parsemsg:string}|array{modparse:'ok', modid:string, modversion:string}
 */
function getModInfo($filepath)
{
	$returncode = null;
	if (substr(PHP_OS, 0, 3) === 'WIN') {
		$idver = exec("util\\modpeek.exe -i -f " . escapeshellarg($filepath), $unused, $returncode);
	} else {
		$idver = exec("mono util/modpeek.exe -i -f " . escapeshellarg($filepath), $unused, $returncode);
	}

	if ($returncode != 0) {
		$error = array("modparse" => "error", "parsemsg" => "Unable to find mod id and version, which must be present in any mod (.cs, .dll, or .zip). If you are certain you added it, please contact Tyron");
	}

	$parts = explode(":", $idver);
	if (count($parts) != 2) {
		$error = array("modparse" => "error", "parsemsg" => "Unable to determine mod id and version, which must be present in any mod (.cs, .dll, or .zip). If you are certain you added it, please contact Tyron");
	}

	// allow uploading files when DEBUG is set AND mono/windows is unavailable
	if (isset($error)) {
		if (DEBUG === 1) {
			return array("modparse" => "ok", "modid" => $_POST['modidstr'] ?? "ExampleMod", "modversion" => $_POST['modversion'] ?? "1.0.0");
		}
		return $error;
	}

	return array("modparse" => "ok", "modid" => $parts[0], "modversion" => $parts[1]);
}

function updateGameVersionsCached($modid)
{
	global $con;
	$modid = intval($modid);

	$tags = $con->getAll("select distinct tag.tagid, tag.name from `release` join assettag on (`release`.assetid = assettag.assetid) join `tag` on (assettag.tagid = tag.tagid) where modid=?", array($modid));
	$inserts = array();
	$majorversions = array();
	foreach ($tags as $tag) {
		$inserts[] = "({$tag['tagid']}, {$modid})";

		$parts = explode(".", substr($tag['name'], 1));
		$key = $parts[0] . "." . $parts[1] . ".x";
		$majorversions[$key] = 1;
	}


	foreach ($majorversions as $majorversion => $val) {
		$mvid = $con->getOne("select majorversionid from majorversion where name=?", array($majorversion));
		$con->Execute("INSERT IGNORE INTO majormodversioncached (majorversionid, modid) values (?,?)", array($mvid, $modid));
	}

	$con->Execute("delete from modversioncached where modid=?", array($modid));

	if (count($tags) > 0) $con->Execute("insert into modversioncached values " . implode(",", $inserts));
}

function getUserHash($userid, $joindate)
{
	global $config;
	return substr(hash("sha512", $userid . $joindate), 0, 20);
}

function getUserByHash($hashcode, $con)
{
	global $config;
	return $con->getRow("select * from user where sha2(concat(user.userid, user.created), 512) like ?", array($hashcode . "%"));
}

/**
 * Splits of the last extension from a path, gives back the whole path without extension and the extension.
 * More light-weight than pathinfo.
 * 
 * @param string &$out_noext
 * @param string &$out_ext
 * @param string $path
 */
function splitOffExtension($path, &$out_noext, &$out_ext)
{
	$lastdot = strrpos($path, '.');
	if($lastdot === false) {
		$out_noext = $path;
		$out_ext = '';
	}

	$out_noext = substr($path, 0, $lastdot);
	$out_ext = substr($path, $lastdot + 1);
}


// Loads after other function deffinitions so we can use them during global userstate init.
include($config["basepath"] . "lib/user.php");


if(CDN == 'bunny') {
	include($config["basepath"] . "lib/cdn/bunny.php");
}
else {
	include($config["basepath"] . "lib/cdn/none.php");
}


/**
 * Formats a download tracking link to the file.
 * This url is meant to enforce that the enduser gets prompted to download the file, as compared to a "normal" link which might just display the file in browser as well as tracking that download (-attempt).
 * 
 * @param array{filename:string, fileid:int} $file
 * @return string
 */
function formatDownloadTrackingUrl($file)
{
	return "/download/{$file['fileid']}/{$file['filename']}";
}

/**
 * Formats a download tracking link to the file if the extension is not one of the image types we support.
 * In that case this url is meant to enforce that the enduser gets prompted to download the file, as compared to a "normal" link which might just display the file in browser as well as tracking that download (-attempt).
 * Otherwise this just returns the cdn download url without tracking.
 * This is meant specifically for the purpose of the asset file attachement; open images in browser, download everything else and track the download.
 * 
 * @param array{filename:string, fileid:int, ext:string, cdnpath:string} $file
 * @return string
 */
function maybeFormatDownloadTrackingUrlDependingOnFileExt($file)
{
	switch($file['ext']) {
		case 'png': case 'jpg': case 'gif':
			return formatCdnDownloadUrl($file);

		default:
			return formatDownloadTrackingUrl($file);
	}
}
