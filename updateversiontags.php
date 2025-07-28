<?php

$availableVersionsStrs = array_keys(json_decode(file_get_contents('http://api.vintagestory.at/stable-unstable.json'), true));

$availableVersions = [];
$malformedAvailableVersions = [];
foreach ($availableVersionsStrs as $versionStr) {
	$compiled = compileSemanticVersion($versionStr);
	if($compiled === false)   $malformedAvailableVersions[] = $versionStr;
	else                      $availableVersions[] = $compiled;
}

if($malformedAvailableVersions) {
	$foldedErrors = implode(', ', array_map(fn($v) => "'$v'", $malformedAvailableVersions));
	showErrorPage(HTTP_INTERNAL_ERROR, 'The following provided version strings do not parse as semantic versions: '.$foldedErrors.'. A semantic version must match /^\d+\.\d+\.\d+(-(dev|pre|rc)\.\d+)?$/.');
}


// Merge existing values with the new/current ones and recalculate the sort index.
//NOTE(Rennorb): The sort index is an ascending n+1 index that is used to find consecutive sequences of versions.
// We just use the index in the array after sorting the values for this. :VersionSortIndex
$storedVersions = array_map('intval', $con->getCol('SELECT `version` FROM gameVersions'));
$allVersions = array_unique(array_merge($storedVersions, $availableVersions));
sort($allVersions); // sort ascending so the keys are in the correct order

$foldedValues = implode(', ', array_map(fn($k, $v) => "($v, $k)", array_keys($allVersions), $allVersions));

// @security: All keys and values are numeric and therefore SQL inert.
$con->Execute(<<<SQL
	INSERT INTO gameVersions (version, sortIndex)
		VALUES $foldedValues
	ON DUPLICATE KEY UPDATE
		sortIndex = VALUES(sortIndex)
SQL);

echo count(array_diff($allVersions, $storedVersions)) . ' new versions added.';
