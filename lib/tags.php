<?php

$tagsortby = array(
	1 => "name"
);

function sortTags($_, $tags) {
	usort($tags, "cmpTagName");
	return $tags;
}


function cmpVersionTag($tag1, $tag2) {
	return cmpVersion($tag1["name"], $tag2["name"]);
}

function rcmpVersionTag($tag1, $tag2) {
	return cmpVersion($tag2["name"], $tag1["name"]);
}


function cmpTagName($tag1, $tag2) {
	return strcmp($tag1["name"], $tag2["name"]);
}


function cmpVersion($a, $b) {
	$isversion = splitVersion($a);
	$reqversion = splitVersion($b);
	
	$cnt = max($isversion, $reqversion);
	
	for ($i = 0; $i < $cnt; $i++) {
		if ($i >= count($isversion)) return 1;
		
		if (intval($isversion[$i]) > intval($reqversion[$i])) return -1;
		if (intval($isversion[$i]) < intval($reqversion[$i])) return 1;
	}
	
	return 0;
}

function splitVersion($version) {
	$parts = preg_split("/(\-|\.)/", $version);

	if (count($parts) <= 1) {
		return $parts;
	}
	
	// Full release
	if (count($parts) <= 3) {
		$parts[3] = 2;
		return $parts;
	}
	
	// Release candidate
	if ($parts[3] == "rc") {
		$parts[3] = 1;
		return $parts;
	}
	
	// Pre-Release
	$parts[3] = 0;
	return $parts;
}
