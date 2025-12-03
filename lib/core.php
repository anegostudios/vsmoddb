<?php
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');

global $config, $con, $view;

include($config["basepath"] . "lib/ErrorHandler.php");
if(!defined("TESTING")) ErrorHandler::setupErrorHandling(); // TODO(Rennorb) @cleanup: Change this into "detaching" when testing instead of always checking for testing mode.


include($config["basepath"] . "lib/timezones.php");
include($config["basepath"] . "lib/View.php");
include($config["basepath"] . "lib/img.php");
include($config["basepath"] . "lib/tags.php");
include($config["basepath"] . "lib/3rdparty/adodb5/adodb-exceptions.inc.php");
include($config["basepath"] . "lib/3rdparty/adodb5/adodb.inc.php");

include($config["basepath"] . "lib/fileupload.php");
include($config["basepath"] . "lib/version.php");

//mysqli_report(MYSQLI_REPORT_ERROR);
$con = createADOConnection($config);
$view = new View();

$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;


const KB = 1024;
const MB = 1024 * KB;
const GB = 1024 * MB;

/** Format 1024 as 1KB, with two significant digits after the dot.
 * @param int $size
 * @return string
 */
function formatByteSize($size)
{
	if($size > 1024 * 1024 * 1024) return round((float)$size / (1024 * 1024 * 1024), 2).' GB';
	if($size > 1024 * 1024) return round((float)$size / (1024 * 1024), 2).' MB';
	if($size > 1024) return round((float)$size / 1024, 2).' KB';
	return $size.' B';
}


$view->assign("assetserver", $config['assetserver']);


//NOTE(Rennorb): Technically we should only count the public mods, but in reality this probably doesn't matter for production and just counting all mods makes the query simpler.
$view->assign('totalModCount', $con->getOne('SELECT COUNT(*) from mods'), null, true);




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

/** Splits a string at a separator, but at most once.
 * If the separator is not found the left string contains the whole input, and the right string is empty.
 * @param string $string
 * @param string $separator
 * @param string &$out_left
 * @param string &$out_right
 */
function splitOnce($string, $separator, &$out_left, &$out_right)
{
	$seppos = strpos($string, $separator);
	if($seppos === false) {
		$out_left  = $string;
		$out_right = '';
	}
	else {
		$out_left  = substr($string, 0, $seppos);
		$out_right = substr($string, $seppos + strlen($separator));
	}
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

/** Strips html tags, trailing and leading whitespace and consecutive whitespace, as well as converting html entries to their utf8 counterparts.
 * This is intended to create a searchable, plain-text version of a html string.
 * @remark This is a rather expensive function!
 * @param string $string
 * @return string
 */
function textContent($string)
{
	return preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags($string), ENT_SUBSTITUTE | ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}


function isNumber($val)
{
	return intval($val) . "" == $val;
}

function isUrl($url)
{
	return strlen(filter_var($url, FILTER_VALIDATE_URL));
}

/**
 *  When filter_input doesn't quite do what you need it to.
 * 
 * @param int $type One of <b>INPUT_GET</b>, <b>INPUT_POST</b> or <b>INPUT_REQUEST</b>
 * @param string $varName
 * @return array<int>|false|null  false if element cannot be converted, null if key is missing in given input. Cannot be false if $filterInsteadOfFail is set.
 */
function getInputArrayOfInts($type, $varName, $filterInsteadOfFail = false)
{
	switch($type) {
		case INPUT_GET:     $input = $_GET;     break;
		case INPUT_POST:    $input = $_POST;    break;
		case INPUT_REQUEST: $input = $_REQUEST; break;
	}
	if(!array_key_exists($varName, $input)) return null;
	return forceArrayOfInts($input[$varName], $filterInsteadOfFail);
}

/**
 *  When filter_var doesn't quite do what you need it to.
 * 
 * @param int|string|array<int|string> $var
 * @return array<int>|false
 */
function forceArrayOfInts($var, $filterInsteadOfFail = false)
{
	if(is_int($var))   return [$var];
	else if(is_string($var)) {
		$var = trim($var);
		if(is_numeric($var))  return [intval($var)];
	}
	else if(is_array($var)) {
		$mapped = [];
		foreach($var as $el) {
			if(is_int($el)) {
				$mapped[] = $el;
				continue;
			}
			else if(is_string($el)) {
				$el = trim($el);
				if(is_numeric($el)) {
					$mapped[] = intval($el);
					continue;
				}
			}
			// neither int not numeric string
			if(!$filterInsteadOfFail) return false;
		}
		return $mapped;
	}
	return $filterInsteadOfFail ? [] : false;
}

function last($array)
{
	return $array[count($array) - 1];
}

function sanitizeHtml($text)
{
	global $config;
	include_once($config["basepath"] . "lib/3rdparty/htmLawed.php");

	// Extremely rudimentary check to not ingest expanded spoilers after editing a comment, but good enough for that case.
	$text = str_replace('class="spoiler-toggle expanded"', 'class="spoiler-toggle"', $text);

	$text = htmLawed($text, array('tidy' => 0, 'safe' => 1, 'elements' => '* -script -object -applet -canvas +iframe -video -audio -embed -form', 'schemes' => 'src: http, https, data', 'hook_tag' => "_htmLawed_sanitize_node"));

	return $text;
}

// TinyMCE media embed:
// <iframe src="//www.youtube.com/embed/AmQV7QwjCac" width="560" height="315" allowfullscreen="allowfullscreen"></iframe>"

// YouTube "share as embed":
// <iframe width="560" height="315" src="https://www.youtube.com/embed/AmQV7QwjCac?si=iJaNM5nzTf4s7FHX" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>

/**
 * @param string $element_name
 * @param array<string, string>|0 $attributes  zero in case of closing tag
 * @return string post-filter html according to the tag. Must produce opening / closing tags according to the attributes param.
 */
function _htmLawed_sanitize_node($elementName, $attributes = 0) {
	// Taken from htmlLawed.
	static $emptyElements = array('area'=>1, 'br'=>1, 'col'=>1, 'command'=>1, 'embed'=>1, 'hr'=>1, 'img'=>1, 'input'=>1, 'isindex'=>1, 'keygen'=>1, 'link'=>1, 'meta'=>1, 'param'=>1, 'source'=>1, 'track'=>1, 'wbr'=>1); // Empty ele

	static $removeNextClosing = false;
	if($attributes === 0) {
		if($removeNextClosing) {
			$removeNextClosing = false;
			return '';
		}
		else {
			return "</$elementName>";
		}
	}

	switch($elementName) {
		case 'iframe': {
			if(empty($attributes['src']) || !preg_match('#//(?:www\.)?youtube(?:-nocookie)?\.com/embed#i', $attributes['src'])) {
				$removeNextClosing = true;
				return '';
			}

			static $allowedKeys = ['src'=>1, 'width'=>1, 'height'=>1, 'allowfullscreen'=>1, 'allow'=>1];
			$attributes = array_intersect_key($attributes, $allowedKeys);
			// Strip unnecessary params from url and turn it into a no-cookie link:
			$attributes['src'] = '//www.youtube-nocookie.com'.parse_url($attributes['src'], PHP_URL_PATH);
			// Strip autoplay and other telemetry gunk:
			$attributes['allow'] = 'encrypted-media; picture-in-picture; web-share; clipboard-write';
		}
	}

	// Reconstruct the tag. Taken form htmlLawed.
	$foldedAttrs = '';
	foreach($attributes as $k => $v) { $foldedAttrs .= " {$k}=\"{$v}\""; }
	return "<{$elementName}{$foldedAttrs}". (isset($emptyElements[$elementName]) ? ' /' : ''). '>';
}




function createADOConnection($config, $persistent = true)
{
	$con = ADONewConnection("mysqli");

	$result = $con->NConnect($config["databasehost"], $config["databaseuser"], $config["databasepassword"], $config["database"]);

	if (!$result) {
		throw new Exception("Error connecting to database. " . $con->_errorMsg);
		die();
	}

	$con->execute("SET CHARACTER SET utf8mb4");
	$con->execute("SET NAMES utf8mb4");

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

/** Formats the path to a mod page using the urlAlias of the mod if possible.
 * @param array{urlAlias : string, assetId : int} $mod
 * @return string Path to the mod page starting with the root slash.
 */
function formatModPath($mod)
{
	return $mod['urlAlias'] ? ('/'.$mod['urlAlias']) : ("/show/mod/" . $mod['assetId']);
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

// NOTE(Rennorb): Mod logos older than this date are considered "legacy" and have to be formatted differently.
// :LegacyModLogos
const SQL_MOD_CARD_TRANSITION_DATE = "2025-03-10 15:50:00";

/**
 * @param string[] $changes
 * @param int $assetId
 */
function logAssetChanges($changes, $assetId)
{
	if (empty($changes)) return;
	global $con, $user;

	$changes = implode("\r\n", $changes);

	$activeChangeId = $con->getOne(<<<SQL
		SELECT changelogId
		FROM changelogs
		WHERE userId = ? AND assetId = ? AND lastModified >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
		ORDER BY created DESC
		LIMIT 1
	SQL, [$user['userId'], $assetId]);

	if ($activeChangeId) {
		$con->execute("UPDATE changelogs SET text = CONCAT(?, '\n\r', text) WHERE changelogId = ?",
			[$changes, $activeChangeId]
		);
	}
	else {
		$con->execute('INSERT INTO changelogs (assetId, userId, text) VALUES (?, ?, ?)',
			[$assetId, $user["userId"], $changes]
		);
	}
}


const MODACTION_KIND_BAN    = 1;
const MODACTION_KIND_DELETE = 2;
const MODACTION_KIND_EDIT   = 3;
const MODACTION_KIND_REDEEM = 4;
const MODACTION_KIND_LOCK   = 5;


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
		case MODACTION_KIND_LOCK  : return "Lock Mod";
		default: return strval($kind);
	}
}

const SQL_DATE_FOREVER = "9999-12-31";
const SQL_DATE_FORMAT = "Y-m-d H:i:s";


/**
 * @param int            $targetUserId
 * @param int            $moderatorUserId
 * @param MODACTION_KIND $kind
 * @param int            $recordId The id of the related record in the kind-specific table.
 * @param string         $until
 * @param string|null    $reason
 * @return int generated modaction id
 */
function logModeratorAction($targetUserId, $moderatorUserId, $kind, $recordId, $until, $reason)
{
	global $con;
	$con->Execute('INSERT INTO moderationRecords (targetUserId, moderatorId, kind, recordId, until, reason) VALUES (?,?,?,?,?,?)',
		[$targetUserId, $moderatorUserId, $kind, $recordId, $until, $reason]
	);
	return $con->Insert_ID();
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
			'<div class="spoiler"><div class="spoiler-toggle">Spoiler!</div><div style="display: none;">\1</div></div>',
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
			else if(($userHash = $child->attributes->getNamedItem('data-user-hash')) && preg_match('/[a-z0-9]{20}/', $userHash->value)) {
				//NOTE(Rennorb): unfortunately, we cant just rename the node, so we have to copy the data onto another one.
				$replacement = $child->ownerDocument->createElement('a');
				$replacement->setAttribute('class', $child->attributes->getNamedItem('class')->value);
				$replacement->setAttribute('data-user-hash', $userHash->value);
				$replacement->setAttribute('href', '/show/user/'.$userHash->value);
				$replacement->textContent = $child->textContent;
				$toReplace[] = [$child, $replacement];
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
 * @param array{name:string, fileid:int} $file
 * @return string
 */
function formatDownloadTrackingUrl($file)
{
	$escapedName = urlencode($file['name']);
	return "/download/{$file['fileId']}/{$escapedName}";
}

/**
 * Formats a download tracking link to the file if the extension is not one of the image types we support.
 * In that case this url is meant to enforce that the enduser gets prompted to download the file, as compared to a "normal" link which might just display the file in browser as well as tracking that download (-attempt).
 * Otherwise this just returns the cdn download url without tracking.
 * This is meant specifically for the purpose of the asset file attachement; open images in browser, download everything else and track the download.
 * 
 * @param array{name:string, fileId:int, ext:string, cdnPath:string} $file
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
const HTTP_FOUND               = 302;
const HTTP_BAD_REQUEST         = 400;
const HTTP_UNAUTHORIZED        = 401;
const HTTP_FORBIDDEN           = 403;
const HTTP_NOT_FOUND           = 404;
const HTTP_WRONG_METHOD        = 405;
const HTTP_CONFLICT            = 409;
const HTTP_GONE                = 410;
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
		case HTTP_GONE:
			$statusMessage = '410 - Gone.';
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

/** Terminates execution. */
function showReadonlyPage()
{
	global $view;

	header('Retry-After: 1800', true, HTTP_SERVICE_UNAVAILABLE); // 30 min

	$view->assign('headerHighlight', null, null, true);
	$view->assign('goBugRennorb', false, null, true);
	$view->assign('statusMessage', "Service Unavailable", null, true);
	$view->assign('reason', "We are currently in readonly mode.", null, true);
	$view->display('error');
	exit();
}


const FOLLOW_FLAG_CREATE_NOTIFICATIONS = 1 << 0;
//const FOLLOW_FLAG_SEND_MAIL          = 1 << 1; // @unused, for later mail sending feature



const HEADER_HIGHLIGHT_HOME          = 1;
const HEADER_HIGHLIGHT_MODS          = 2;
const HEADER_HIGHLIGHT_TWEAKS        = 3;
const HEADER_HIGHLIGHT_SUBMIT_MOD    = 4;
const HEADER_HIGHLIGHT_NOTIFICATIONS = 5;
const HEADER_HIGHLIGHT_ADMIN_TOOLS   = 6;
const HEADER_HIGHLIGHT_CURRENT_USER  = 7;


const TAG_KIND_PREDEFINED = 2;

// A game mod and server tweak share a lot of logic.
// CATEGORY_GAME_MOD & CATEGORY__MASK === CATEGORY_SERVER_TWEAK & CATEGORY__MASK
const CATEGORY__MASK = 0b01111111;

const CATEGORY_GAME_MOD      = 0;
const CATEGORY_EXTERNAL_TOOL = 1;
const CATEGORY_OTHER         = 2;
const CATEGORY_SERVER_TWEAK  = CATEGORY_GAME_MOD | (1 << 7);

const ASSETTYPE_MOD = 1;
const ASSETTYPE_RELEASE = 2;

const STATUS_DRAFT = 1;
const STATUS_RELEASED = 2;
const STATUS_3 = 3;
const STATUS_LOCKED = 4;


if(!defined('INPUT_REQUEST')) define('INPUT_REQUEST', 99);

include($config["basepath"] . "lib/upload-limits.php");

/** @return bool */
function isTouchPlatform()
{
	return preg_match('/i(?:phone|pod|pad)|android|blackberry|mobile/i', $_SERVER['HTTP_USER_AGENT']);
}

/** @return bool */
function isTVPlatform()
{
	return preg_match('/webos|apple(?:tv| tv)|aft|roku|smart(?:tv|-tv| tv)/i', $_SERVER['HTTP_USER_AGENT']);
}
