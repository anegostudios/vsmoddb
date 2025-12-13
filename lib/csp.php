<?php

$cspNonce = md5($config['noncesalt'].time());
global $_csp, $_cspInlineHashes;
$_csp = [
	// Scripts, styles, images and fetching that does not carry this nonce is not allowed by default.
	'default-src' => "'nonce-$cspNonce'",
	// No fetchign at all. Must be enabled manually via cspReplaceAllowedFetchSources.
	// This also disallowes inline event handlers (onload=".."), which must be manually whitelisted via the 'unsafe-hashes' directive as they cannot have nonces.
	'connect-src' => "'none'",
	// Allow inline styles, as well as ones generated from js.
	'style-src-attr' => "'unsafe-inline' 'unsafe-eval'",
	// Allow all images served from us or any http(s) domain, as well as inlined data images. Effectively all images.
	'img-src' => "'self' http: data: blob:",
	'manifest-src' => "'self'",
	// Allow data i-frames for tinymce preview and youtube (very specific, www.youtube.com is the only one allowed by the filters)
	'frame-src' => "data: www.youtube.com/embed/ www.youtube-nocookie.com/embed/",
	// Explicitly allow boxicons font(s) from unpkg
	'font-src' => "unpkg.com/boxicons@2.1.4/fonts/",
];
$_cspInlineHashes = [
	'sha256-2rvfFrggTCtyF5WOiTri1gDS8Boibj4Njn0e+VCBmDI=', // return false;
];

$view->assign('cspNonce', $cspNonce, null, true); // needs to be applied to all script elements

/** @param string $newPattern */
function cspReplaceAllowedFetchSources($newPattern)
{
	if(DEBUG && headers_sent()) {
		ErrorHandler::printException(new Error("Cannot change fetch sources after output has started."));
		return;
	}
	global $_csp; $_csp['connect-src'] = $newPattern;
}


/** @param string $hash Must be in the shape of 'algo-base64value', without the single quotes. */
function cspPushAllowedInlineHandlerHash($hash)
{
	if(DEBUG && headers_sent()) {
		ErrorHandler::printException(new Error("Cannot change fetch sources after output has started."));
		return;
	}
	global $_cspInlineHashes; $_cspInlineHashes[] = $hash;
}

function cspAllowTinyMceComment()
{
	global $_csp;

	$tinymce = $_SERVER['HTTP_HOST'].'/web/js/tinymce';
	$plugins = $tinymce.'/plugins';

	$_csp['script-src-elem'] = $_csp['default-src']." $tinymce/themes/silver/theme.min.js $tinymce/themes/mobile/theme.min.js $plugins/paste/plugin.min.js $plugins/searchreplace/plugin.min.js $plugins/autolink/plugin.min.js $plugins/autoresize/plugin.min.js $plugins/directionality/plugin.min.js $plugins/image/plugin.min.js $plugins/link/plugin.min.js $plugins/codesample/plugin.min.js $plugins/charmap/plugin.min.js $plugins/hr/plugin.min.js $plugins/pagebreak/plugin.min.js $plugins/nonbreaking/plugin.min.js $plugins/anchor/plugin.min.js $plugins/emoticons/plugin.min.js $plugins/emoticons/js/emojis.min.js $plugins/advlist/plugin.min.js $plugins/lists/plugin.min.js $plugins/wordcount/plugin.min.js $plugins/imagetools/plugin.min.js $plugins/textpattern/plugin.min.js $plugins/help/plugin.min.js $plugins/spoiler/plugin.min.js $plugins/noneditable/plugin.min.js {$_SERVER['HTTP_HOST']}/web/js/tinymce-custom/plugins/mention/plugin.min.js";

	// Safari apparently doesn't recognize style-src-elem
	$_csp['style-src'] = $_csp['default-src']." $tinymce/skins/ui/oxide/skin.min.css $tinymce/skins/ui/oxide/content.min.css $tinymce/plugins/spoiler/css/spoiler.css {$_SERVER['HTTP_HOST']}/web/css/editor_content.css";

	// Icon font
	$_csp['font-src'] .= " $tinymce/skins/ui/oxide/fonts/tinymce-mobile.woff?8x92w3";
}

function cspAllowTinyMceFull()
{
	global $_csp;

	$tinymce = $_SERVER['HTTP_HOST'].'/web/js/tinymce';
	$plugins = $tinymce.'/plugins';

	$_csp['script-src-elem'] = $_csp['default-src']." $tinymce/themes/silver/theme.min.js $tinymce/themes/mobile/theme.min.js $plugins/paste/plugin.min.js $plugins/searchreplace/plugin.min.js $plugins/autolink/plugin.min.js $plugins/autoresize/plugin.min.js $plugins/directionality/plugin.min.js $plugins/image/plugin.min.js $plugins/link/plugin.min.js $plugins/codesample/plugin.min.js $plugins/charmap/plugin.min.js $plugins/hr/plugin.min.js $plugins/pagebreak/plugin.min.js $plugins/nonbreaking/plugin.min.js $plugins/anchor/plugin.min.js $plugins/emoticons/plugin.min.js $plugins/emoticons/js/emojis.min.js $plugins/advlist/plugin.min.js $plugins/lists/plugin.min.js $plugins/wordcount/plugin.min.js $plugins/imagetools/plugin.min.js $plugins/textpattern/plugin.min.js $plugins/help/plugin.min.js $plugins/spoiler/plugin.min.js $plugins/noneditable/plugin.min.js $plugins/preview/plugin.min.js $plugins/visualblocks/plugin.min.js $plugins/visualchars/plugin.min.js $plugins/fullscreen/plugin.min.js $plugins/media/plugin.min.js $plugins/code/plugin.min.js $plugins/table/plugin.min.js $plugins/toc/plugin.min.js $plugins/insertdatetime/plugin.min.js $plugins/print/plugin.min.js {$_SERVER['HTTP_HOST']}/web/js/tinymce-custom/plugins/mention/plugin.min.js";

	// Safari apparently doesn't recognize style-src-elem
	$_csp['style-src'] = $_csp['default-src']." $tinymce/skins/ui/oxide/skin.min.css $tinymce/skins/ui/oxide/content.min.css $tinymce/plugins/spoiler/css/spoiler.css {$_SERVER['HTTP_HOST']}/web/css/editor_content.css";

	// Icon font
	$_csp['font-src'] .= " $tinymce/skins/ui/oxide/fonts/tinymce-mobile.woff?8x92w3";
}

/** Call this one last, it overwrites all allowed style sources. */
function cspAllowFotorama()
{
	global $_csp;
	// Fotorama uses insertRule, which is blocked by unsafe-inline rules
	//TODO(Rennorb): Replace fotorama. its literally a few lines of css.
	$_csp['style-src-elem'] = "'self' https: 'unsafe-inline' 'unsafe-eval'";
}

function _cspEmitHeader()
{
	global $_csp, $_cspInlineHashes;
	if($_cspInlineHashes) {
		//NOTE(Rennorb): In theory script-src-attr exists, but i could not get that to work. We mangle everything into script-src.
		$uh = " 'unsafe-hashes' ".implode(' ', array_map(fn($h) => "'$h'", $_cspInlineHashes));
		$_csp['script-src'] = $_csp['default-src'].$uh;
		if(!empty($_csp['script-src-elem'])) $_csp['script-src-elem'] .= $uh;
	}
	header('Content-Security-Policy: '.implode('; ', array_map(fn($k, $v) => "$k $v", array_keys($_csp), $_csp)));
}
