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
include($config["basepath"] . "lib/version.php");


$rd = opendir($config["basepath"] . "lib/assetimpl");
while (($file = readdir($rd))) {
	if (endsWith($file, ".php")) {
		include($config["basepath"] . "lib/assetimpl/" . $file);
	}
}

//mysqli_report(MYSQLI_REPORT_ERROR);
$con = createADOConnection($config);
$view = new View();

// may later on be modified by asset specific overrides
global $maxFileUploadSizeMB;
$maxFileUploadSizeMB = round(file_upload_max_size() / (1024 * 1024), 1);
$view->assign("fileuploadmaxsize", $maxFileUploadSizeMB);

$view->assign("assetserver", $config['assetserver']);

$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;


//NOTE(Rennorb): Technically we should only count the public mods, but in reality this probably doesn't matter for production and just counting all mods makes the query simpler.
$view->assign('totalModCount', $con->getOne('SELECT COUNT(*) from `mod`'), null, true);


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



function dump($var)
{
	echo "<pre style='background: #fff; color: #000; padding: .5em; border: solid 1px currentcolor;'>";
	var_dump($var);
	echo "</pre>";
}

function dump_die($var)
{
	dump($var);
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

function contains($string, $part)
{
	return mb_strstr($string, $part) !== false;
}

/** Formats the elements of the array into a string in the shape of '1, 2, 3 and 4'.
 * @param array $array 
 * @return string
 */
function formatGrammaticallyCorrectEnumeration($array)
{
	switch(count($array)) {
		case 0:
			return '';

		case 1:
			return $array[0];

		default:
			$str = $array[0];
			for($i = 1; $i < count($array) - 1; $i++) {
				$str .= ', ';
				$str .= $array[$i];
			}
			$str .= ' and ';
			$str .= $array[$i];
			return $str;
	}
}


function isNumber($val)
{
	return intval($val) . "" == $val;
}

function isUrl($url)
{
	return strlen(filter_var($url, FILTER_VALIDATE_URL));
}

function last($array)
{
	return $array[count($array) - 1];
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

	//TODO(Rennorb) @correctness: This is supposed to allow usage of POINT(x, y) in inserts, but those still fail with
	// "Cannot get geometry object from data you send to the GEOMETRY field"
	// :BrokenSqlPointType
	//$con->setCustomMetaType('P', MYSQLI_TYPE_GEOMETRY, 'POINT');

	return $con;
}

/** This function forces a location header to the the current uri, as well as status 303.
 * This causes the browser to GET the (same) page again, and prevents "resend with data" on page reloads.
 * This function does not terminate execution. You likely want to exit() after calling this.
 * 
 */
function forceRedirectAfterPOST()
{
	header('Location: '.$_SERVER['REQUEST_URI'], true, 303);
}

/** This function forces a location header to the provided location, as well as status 303.
 * This function does not terminate execution. You likely want to exit() after calling this.
 * 
 * @param string|array $url
 */
function forceRedirect($url)
{
	if(gettype($url) === 'array') $url = buildLocalUri($url);
	header('Location: '.$url, true, 303);
}

/** Constructs a uri from a parse_url result shaped array.
 * 
 * @param array{scheme : string, host : string, port : int, query : string, path : string, fragment : string} $parts
 * @return string
 */
function buildUri($parts)
{
	$uri = "{$parts['scheme']}://{$parts['hostname']}";
	if(!empty($parts['port'])) $uri .= ':'.$parts['port'];
	$uri .= $parts['path'];
	if(!empty($parts['query'])) $uri .= '?'.$parts['query'];
	if(!empty($parts['fragment'])) $uri .= '#'.$parts['fragment'];
	return $uri;
}

/** Constructs a uri from a parse_url result shaped array, does not contain schema, host, port, and auth. User for redirects on the same page
 * 
 * @param array{scheme : string, host : string, port : int, query : string, path : string, fragment : string} $parts
 * @return string
 */
function buildLocalUri($parts)
{
	$uri = $parts['path'];
	if(!empty($parts['query'])) $uri .= '?'.$parts['query'];
	if(!empty($parts['fragment'])) $uri .= '#'.$parts['fragment'];
	return $uri;
}

/** Strips one specific query parameter from the provided query string if it exists.
 * @param string $query
 * @param string $paramname
 * @return string
 */
function stripQueryParam($query, $paramname)
{
	$params = explode('&', $query);
	$s = $paramname.'=';
	$params = array_filter($params, fn($p) => !startsWith($p, $s));
	return implode('&', $params);
}

/** Strips specific query parameters from the provided query string if they exists.
 * @param string $query
 * @param string[] $paramNames
 * @return string
 */
function stripQueryParams($query, $paramNames)
{
	$params = explode('&', $query);
	$params = array_filter($params, function($p) use ($paramNames) {
		$pname = strchr($p, '=', true);
		return !in_array($pname, $paramNames);
	});
	return implode('&', $params);
}

/** Formats the path to a mod page using the urlalias of the mod if possible.
 * @param array{urlalias : string, assetid : int} $mod
 * @return string Path to the mod page starting with the root slash.
 */
function formatModPath($mod)
{
	return $mod['urlalias'] ? ('/'.$mod['urlalias']) : ("/show/mod/" . $mod['assetid']);
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

// NOTE(Rennorb): Mod logos older than this date are considdered "legacy" and have to be formatted differently.
// :LegacyModLogos
const SQL_MOD_CARD_TRANSITION_DATE = "2025-03-10 15:50:00";

function logAssetChanges($changes, $assetid)
{
	global $con, $user;

	if (!empty($changes)) {
		$change = $con->getRow('
			SELECT *
			FROM changelog
			WHERE userid = ? AND assetid = ? AND lastmodified >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
			ORDER BY created DESC
			LIMIT 1
		', [$user['userid'], $assetid]);
		if ($change) {
			$changesdb = explode("\r\n", $change["text"]);
			$changelogid = $change["changelogid"];

			$changes = array_merge($changes, $changesdb);
		}

		if (!$change) {
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

/**
 * @param string $date
 * @return string
 */
function formatDateRelative($sqldate)
{
	$diff = date_diff(parseSqlDateTime($sqldate), date_create_immutable());

	     if($diff->y > 0) $fmt = $diff->y === 1 ? '1 year'   : $diff->y.' years';
	else if($diff->m > 0) $fmt = $diff->m === 1 ? '1 month'  : $diff->m.' months';
	else if($diff->d > 0) $fmt = $diff->d === 1 ? '1 day'    : $diff->d.' days';
	else if($diff->h > 0) $fmt = $diff->h === 1 ? '1 hour'   : $diff->h.' hours';
	else if($diff->i > 0) $fmt = $diff->i === 1 ? '1 minute' : $diff->i.' minutes';
	else                  $fmt = $diff->s === 1 ? '1 second' : $diff->s.' seconds';

	return $diff->invert ? 'in '.$fmt : $fmt.' ago';
}

/** @obsolete */
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

/**
 * @param string $str
 * @return string
 */
function escapeStringForLikeQuery($str)
{
	return str_replace(['_', '%'], ['\_', '\%'], $str);
}


/** Inflates links and creates spoiler elements.
 * @param string $html
 */
function postprocessCommentHtml($html)
{
	// http:///..... => Create a link from it
	$html = inflateLinks($html);

	$html = preg_replace(
		[
			'#\[spoiler\]\s*(.*)\s*\[/spoiler\]#Us', // [spoiler] ... [/spoiler]  to proper html tags
			'#^\s*(?:<p>\s*</p>)+#', // strip empty leading paragraphs @brittle
			'#(?:<p>\s*</p>)+\s*$#', // strip empty trailing paragraphs @brittle
		],
		[
			'<div class="spoiler"><div class="spoiler-toggle">Spoiler!</div><div class="spoiler-text" style="display: none;">\1</div></div>',
			'',
			'',
		],
		$html
	);

	return $html;
}

/** Inflates links in text and anchor tags into iframes, images and/or anchor tags depending on the url.
 * @param string html
 */
function inflateLinks($html)
{
	$doc = new DOMDocument();
	$doc->recover = true;
	$doc->strictErrorChecking = false;
	// @hack: The parser realy doesnt like having multiple root elements, so we first synthesize one and then strip it from the result...
	//  :WrapUnwrapForDomParser
	//TODO(Rennorb): Update to php 8 so we can use the proper htmldom parser with html5, which should get rid of this reencoding requirement here.
	$doc->loadHTML('<body>'.mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8').'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	_inflateWalker($doc);
	$result = $doc->saveHTML();
	$result = $result ? substr($result, 6, strlen($result) - 1 - 6 - 7) : ''; // :WrapUnwrapForDomParser
	return $result;
}

/** @param \DOMNode &$node */
function _inflateWalker($node)
{
	$toReplace = [];

	foreach($node->childNodes as $child) {
		if($child->nodeType === XML_TEXT_NODE) {
			$newHtml = preg_replace_callback(
				'#https?://[\w.@:/\[\]!$&\'"()*+,;%=\#?]+#',
				fn($match) => _inflateLink($match[0], true),
				$child->textContent, -1, $count
			);
			if($count) {
				$d = new DOMDocument();
				$d->loadHTML($newHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
				
				$frag = $node->ownerDocument->createDocumentFragment();
				foreach($d->childNodes as $c) {
					$frag->appendChild($frag->ownerDocument->importNode($c, true));
				}
				//NOTE(Rennorb): Since we might turn the text node into multiple other nodes we cannot directly insert these new nodes while itterating.
				// That would likely invalidate the iterator and/or skip children. We store the replacements instead and itterate over them after we inspected all children.
				// We also cannot loop over the nodes in reverse, because that apparently doesnt work for DOMNodeList's.
				$toReplace[] = [$child, $frag];
			}
			continue;
		}

		if($child->nodeType === XML_ELEMENT_NODE) {
			if($child->nodeName === 'a') {
				if(count($child->childNodes) < 2) {
					$link = $child->attributes->getNamedItem('href');
					if($link) $link = $link->textContent;

					if(!$link) {
						if(preg_match('#https?://[\w.@:/\[\]!$&\'"()*+,;%=\#?]+#', $child->textContent, $matches))
							$link = $matches[0];
					}

					if($link) {
						$newHtml = _inflateLink($link, false);
						if($newHtml) {
							$d = new DOMDocument();
							$d->loadHTML($newHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
							// This is always one-to-one, so we can directly replace it.
							$node->replaceChild($node->ownerDocument->importNode($d->firstChild, true), $child);
						}
					}
				}
				continue;
			}
		}

		_inflateWalker($child); // recurse
	}

	foreach($toReplace as [$n, $r]) {
		$n->parentNode->replaceChild($r, $n);
	}
}

//TODO(Rennorb): Transform this into a preprocessing + migration function for the old data. Having to run this on every render is quite bad.
/** Inflates a link into html. Always wrapped in a singular node.
 * @param string $link
 * @param bool   $wrapUnmatchedLink Wrap links that do not have special treatment with anchor tags.
 * @return string|false
 */
function _inflateLink($link, $wrapUnmatchedLink)
{
	// https://youtu.be/XNV8SaaDi0o?si=ecgbd8PE_vfFKSDi
	// https://www.youtube.com/watch?v=vkSP1pNpfEQ&list=PLMWVmegrv0fqF7kQGkQU-d_SBffJUjWIhyou
	if(preg_match('#youtu(?:be.\w+/.+?v=|\.be/)([\w-]+)#', $link, $matches)) {
		$ytid = $matches[1]; // @security: The id is alphanumeric and therefore inert
		return "<iframe width='100%' height='315' src='https://www.youtube.com/embed/{$ytid}?rel=0&amp;showinfo=0&amp;color=orange&amp;iv_load_policy=3' frameborder='0' allowfullscreen></iframe>";
	}

	$urlParts = parse_url($link);
	$path = $urlParts['path'] ?? '';
	$relAttr = empty($urlParts['host']) || endsWith($urlParts['host'], 'vintagestory.at') ? '' : ' rel="nofollow external"';

	$lastDot = strrpos($path, '.');
	if($lastDot !== false && $lastDot + 1 < strlen($path)) {
		if(in_array(substr($path, $lastDot + 1), ['png', 'jpg', 'jpeg', 'gif', 'bmp'])) {
			$safeLink = str_replace("'", "%27", $link); // @security: We escape the single quote to prevent the link form being able to escape the href in the anchor tag.
			return "<a target='_blank'{$relAttr} href='$safeLink'><img src='$safeLink' alt='' /></a>";
		}
	}

	if(!$wrapUnmatchedLink) return false;

	$safeLink = str_replace("'", "%27", $link); // @security: We escape the single quote to prevent the link form being able to escape the href in the anchor tag, but we cannot use htmlspecialchars since we need an actual link.
	$content = htmlspecialchars($link);
	return "<a target='_blank'{$relAttr} href='$safeLink'>$content</a>";
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

	return $result;
}


/**
 * @param string $filepath
 * @return array{modparse:'error', parsemsg:string}|array{modparse:'ok', modid:string, modversion:int}
 */
function getModInfo($filepath)
{
	$modpeek = substr(PHP_OS, 0, 3) === 'WIN' ? 'util\\modpeek.exe' : 'mono util/modpeek.exe';
	//NOTE(Rennorb): Unfortunately we cannot use exec, because that trims its output and therefore allows versions with whitespace at the end.
	// That happens for both, the last line returned by exec, and the output param.
	$idver = trim(shell_exec($modpeek.' -i -f '.escapeshellarg($filepath)), "\r\n");

	if (empty($idver)) {
		return ["modparse" => "error", "parsemsg" => "Unable to find mod id and version, which must be present in any mod (.cs, .dll, or .zip). If you are certain you added it, please contact Rennorb"];
	}

	$parts = explode(":", $idver);
	if (count($parts) != 2) {
		return ["modparse" => "error", "parsemsg" => "Unable to determine mod id and version, which must be present in any mod (.cs, .dll, or .zip). If you are certain you added it, please contact Rennorb"];
	}

	//TODO(Rennorb) @cleanup: Move this check out of here once modpeek upgrades are implemented.
	// Since errors cause the data to not be saved, this can cause misleading error messages when roundtripping.
	$version = compileSemanticVersion($parts[1]);
	if($version === false) {
		return ["modparse" => "error", "parsemsg" => "Mod version was malformed and could not be parsed as a semantic version (n.n.n[-{dev|pre|rc}.n])."];
	}

	return ["modparse" => "ok", "modid" => $parts[0], "modversion" => $version];
}

function updateGameVersionsCached($modId)
{
	global $con;

	$modId = intval($modId);

	$con->startTrans();

	$con->execute('DELETE FROM ModCompatibleGameVersionsCached WHERE modId = ?', [$modId]);
	$con->execute('DELETE FROM ModCompatibleMajorGameVersionsCached WHERE modId = ?', [$modId]);

	// @security: modId is numeric and therefore SQL inert.
	$con->execute("INSERT INTO ModCompatibleGameVersionsCached (modId, gameVersion)
		SELECT DISTINCT {$modId}, cgv.gameVersion
		FROM `release` r
		JOIN ModReleaseCompatibleGameVersions cgv
		where r.modid = {$modId}
	");

	$con->execute("INSERT INTO ModCompatibleMajorGameVersionsCached (modId, majorGameVersion)
		SELECT DISTINCT {$modId}, cgv.gameVersion & 0xffffffff00000000
		FROM `release` r
		JOIN ModReleaseCompatibleGameVersions cgv
		where r.modid = {$modId}
	");

	$con->completeTrans();
}

function getUserHash($userid, $joindate)
{
	return substr(hash("sha512", $userid . $joindate), 0, 20);
}

function getUserByHash($hashcode, $con)
{
	return $con->getRow("
		select *, ifnull(user.banneduntil >= NOW(), 0) as `isbanned`
		from user
		where substring(sha2(concat(user.userid, user.created), 512), 1, 20) = ?
	", array($hashcode));
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

const HTTP_CREATED             = 201;
const HTTP_BAD_REQUEST         = 400;
const HTTP_UNAUTHORIZED        = 401;
const HTTP_FORBIDDEN           = 403;
const HTTP_NOT_FOUND           = 404;
const HTTP_WRONG_METHOD        = 405;
const HTTP_INTERNAL_ERROR      = 500;
const HTTP_NOT_IMPLEMENTED     = 501;
const HTTP_SERVICE_UNAVAILABLE = 503;

/** Shows an error page based on the http error code and reason message. 
 * This function terminates execution.
 * @param int $errorCode
 * @param string $reason
 * @param bool|null $goBugRennorb Use `null` to pick a default based on the `$errorCode`, `bool` to overwrite the default.
 * @param bool $rawReason If set to `true` prevents htmlescaping the reason. Only use if neccesary and never with user input.
 */
function showErrorPage($errorCode, $reason = '', $goBugRennorb = null, $rawReason = false)
{
	global $view;

	switch($errorCode) {
		case HTTP_BAD_REQUEST:
			$statusMessage = '400 - The request was malformed.';
			if($goBugRennorb === null) $goBugRennorb = true;
			break;
		case HTTP_UNAUTHORIZED:
			$statusMessage = '401 - You need to log in.';
			break;
		case HTTP_FORBIDDEN:
			$statusMessage = '403 - You do not have sufficient permissions to perform this action.';
			break;
		case HTTP_NOT_FOUND:
			$statusMessage = '404 - Requested page was not found.';
			break;
		case HTTP_INTERNAL_ERROR:
			$statusMessage = '500 - Internal server error.';
			if($goBugRennorb === null) $goBugRennorb = true;
			break;
		case HTTP_NOT_IMPLEMENTED:
			$statusMessage = '501 - This is not (yet) implemented.';
			break;
		case HTTP_SERVICE_UNAVAILABLE:
			$statusMessage = '503 - Service currently unavailable.';
			break;

		default:
			$statusMessage = $errorCode.' - Unknown error.';
			if($goBugRennorb === null) $goBugRennorb = true;
	}

	http_response_code($errorCode);

	$view->assign('headerHighlight', null, null, true);
	$view->assign('goBugRennorb', $goBugRennorb);
	$view->assign('statusMessage', $statusMessage);
	$view->assign('reason', $reason, null, $rawReason);
	$view->display('error');
	exit();
}


const FOLLOW_FLAG_CREATE_NOTIFICATIONS = 1 << 0;
//const FOLLOW_FLAG_SEND_MAIL          = 1 << 1; // @unused, for later mail sending feature



const HEADER_HIGHLIGHT_HOME          = 1;
const HEADER_HIGHLIGHT_MODS          = 2;
const HEADER_HIGHLIGHT_SUBMIT_MOD    = 3;
const HEADER_HIGHLIGHT_NOTIFICATIONS = 4;
const HEADER_HIGHLIGHT_ADMIN_TOOLS   = 5;
const HEADER_HIGHLIGHT_CURRENT_USER  = 6;
