<?php

if(count($urlparts) < 2) showErrorPage(HTTP_BAD_REQUEST);

$rootRelease = $con->getRow(<<<SQL
	SELECT ma.name AS modName, f.name AS `fileName`, r.identifier, r.version, mpr.errors IS NOT NULL AS hasErrors, mpr.rawDependencies
	FROM modReleases r
	JOIN mods m ON m.modId = r.modId
	JOIN assets ma ON ma.assetId = m.assetId
	JOIN files f ON f.assetId = r.assetId
	JOIN modPeekResults mpr ON mpr.fileId = f.fileId
	WHERE r.releaseId = ?
SQL, [$urlparts[1]]);
if(!$rootRelease) showErrorPage(HTTP_NOT_FOUND);

/*
TreeNode: {
	identifier: string,
	minVersion: int
	fileName: string,
	minGameVersion: int,
	children: TreeNode[],
}

Dependency: {
	identifier: string,
	minVersion: int
	resolution: 
}

https://mods.vintagestory.stage/dependencies/20324
*/

$tree = [];

pushDependencyLayer($tree, $rootRelease);

function pushDependencyLayer(&$branch, $release) {
	global $con;

	$node = [
		'name' => 
	]
	$name = $release['identifier'].'@'.formatSemanticVersion(intval($release['version'])).' -> '.$release['fileName'];
	
	$deps = [];
	if($release['rawDependencies']) {
		foreach(explode(', ', $release['rawDependencies']) as $dep) {
			splitOnce($dep, '@', $target, $version);

			$childRelease = $con->getRow(<<<SQL
				SELECT f.name AS `fileName`, r.identifier, r.version, mpr.errors IS NOT NULL AS hasErrors, mpr.rawDependencies
				FROM modReleases r
				JOIN files f ON f.assetId = r.assetId
				JOIN modPeekResults mpr ON mpr.fileId = f.fileId
				WHERE r.identifier = ? AND r.version >= ?
				ORDER BY r.version DESC
				LIMIT 1
			SQL, [$target, compileSemanticVersion(intval($version))]);
			if($childRelease) {
				pushDependencyLayer($deps, $childRelease);
			}
			else if($target === "game") {
				if($version === '') $version = 'latest';
				$name .= ' for game@'.$version;
			}
		}
	}
	$branch[$name] = $deps;
}

$rootRelease['version'] = formatSemanticVersion(intval($rootRelease['version']));

$view->assign('rootRelease', $rootRelease);

$view->assign('treeLayer', $tree);
$view->display('dependencies');
