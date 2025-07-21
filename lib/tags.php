<?php

$tagsortby = array(
	1 => "name"
);

function sortTags($_, $tags) {
	usort($tags, "cmpTagName");
	return $tags;
}


function cmpTagName($tag1, $tag2) {
	return strcmp($tag1["name"], $tag2["name"]);
}


/**
 * @param string $cachedTags
 * @return array{name:string, color:string, tagId:int}
 */
function unwrapCachedTags($tagsCached)
{
	$tagsCached = trim($tagsCached);
	$tags = [];
	if($tagsCached) {
		foreach(explode("\r\n", $tagsCached) as $tagStr) {
			$parts = explode(',', $tagStr);
			$tags[] = ['name' => $parts[0], 'color' => $parts[1], 'tagId' => $parts[2]];
		}
	}
	return $tags;
}

/**
 * @param string $cachedTags
 * @param string[] $allTagTests indexed by tag id
 * @return array{name:string, color:string, tagId:int}
 */
function unwrapCachedTagsWithText($tagsCached, $allTagTests)
{
	$tagsCached = trim($tagsCached);
	$tags = [];
	if($tagsCached) {
		foreach(explode("\r\n", $tagsCached) as $tagStr) {
			$parts = explode(',', $tagStr);
			$tags[] = ['name' => $parts[0], 'color' => $parts[1], 'tagId' => $parts[2], 'text' => $allTagTests[$parts[2]]];
		}
	}
	return $tags;
}
