<?php

/** Compiles a semantic version string (our derivate) to a 64 bit number that is sortable.
 * |16 Bit |16 Bit |16 Bit |16 Bit|
 * |Major  |Minor  |Release|Suffix|
 * 
 * |16 Bit Suffix                   |
 * |4 Bit Kind|12 Bit Suffix Version|
 * 
 * Kind, suffix name.    Space between numbers is future proofing in case we want more at some point.
 *    0 with version 0x0000 is 'reserved' - it denotes a 'major version' (see compilePrimaryVersion).
 *    4, dev
 *    8, pre
 *   12, rc
 *   15 with version 0x0fff is reserved - non pre-release versions use the max value here because they should sort after pre-releases.
 * 
 * @param string $versionStr
 * @return int
 */
function compileSemanticVersion($versionStr)
{
	if(!preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-(dev|pre|rc)\.(\d+))?$/', $versionStr, $matches)) return false; // @perf
	$compliedSuffix = 0xffff; // non pre-release sorts after pre-release
	if(count($matches) > 4) {
		switch($matches[4]) {
			case 'dev': $compliedSuffix =  4 << 12; break;
			case 'pre': $compliedSuffix =  8 << 12; break;
			case 'rc' : $compliedSuffix = 12 << 12; break;
			default: return false;
		}
		$compliedSuffix |= intval($matches[5]) & 0x0fff;
	}
	return ((intval($matches[1]) & 0xffff) << 48)
	     | ((intval($matches[2]) & 0xffff) << 32)
	     | ((intval($matches[3]) & 0xffff) << 16)
	     | $compliedSuffix;
}

/** Does the same asc compileSemanticVersion except it matches only two components, the major and minor.
 *  For end users this is called the 'Major Version', but that means something different in the context of semvar.
 *  The result also uses pre-release kind 0 to allow for masked comparisons against this 'major version',
 *  e.g. compilePrimaryVersion('1.2') == (compileSemanticVersion('1.2.3-pre.1') & (VERSION_MASK_MAJOR | VERSION_MASK_MINOR)).
 * @param string $versionStr
 * @return int
 */
function compilePrimaryVersion($versionStr)
{
	if(!preg_match('/^(\d+)\.(\d+)$/', $versionStr, $matches)) return false; // @perf
	// suffix is zero to include pre-releases
	return ((intval($matches[1]) & 0xffff) << 48)
	     | ((intval($matches[2]) & 0xffff) << 32);
}

/**
 * @param int $versionNum
 * @return string
 */
function formatSemanticVersion($versionNum)
{
	$major   = ($versionNum >> 48 /*& 0xffff*/);
	$minor   = ($versionNum >> 32 & 0xffff);
	$release = ($versionNum >> 16 & 0xffff);
	$preRelease  = $versionNum & 0xffff;
	switch (($preRelease & 0xf000) >> 12) {
		case  4: $preRelease = '-dev.'.($preRelease & 0x0fff); break;
		case  8: $preRelease = '-pre.'.($preRelease & 0x0fff); break;
		case 12: $preRelease =  '-rc.'.($preRelease & 0x0fff); break;
		default: $preRelease = '';
	}
	return "{$major}.{$minor}.{$release}{$preRelease}";
}

/**
 * @param int $versionNum
 * @return bool
 */
function isPreReleaseVersion($versionNum)
{
	return ($versionNum & VERSION_MASK_PRERELEASE) != 0xffff;
}

const VERSION_MASK_MAJOR             = 0xffff_0000_0000_0000;
const VERSION_MASK_MINOR             = 0x0000_ffff_0000_0000;
// For end users this is called the 'Major Version', but that means something different in the context of semvar.
const VERSION_MASK_PRIMARY           = VERSION_MASK_MAJOR | VERSION_MASK_MINOR;
const VERSION_MASK_PATCH             = 0x0000_0000_ffff_0000;
const VERSION_MASK_PRERELEASE_KIND   = 0x0000_0000_0000_f000;
const VERSION_MASK_PRERELEASE_NUMBER = 0x0000_0000_0000_0fff;
const VERSION_MASK_PRERELEASE        = VERSION_MASK_PRERELEASE_KIND | VERSION_MASK_PRERELEASE_NUMBER;
