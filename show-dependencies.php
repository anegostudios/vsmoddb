<?php

$releaseId = $urlparts[2] ?? 0;
if(!$releaseId) showErrorPage(HTTP_BAD_REQUEST);

$rootRelease = $con->getRow(<<<SQL
	SELECT r.identifier, r.version, mpr.rawDependencies, ma.assetId, ma.name AS modName, m.urlAlias
	FROM modReleases r
	JOIN files f ON f.assetId = r.assetId
	JOIN modPeekResults mpr ON mpr.fileId = f.fileId
	JOIN mods m ON m.modId = r.modId
	JOIN assets ma ON ma.assetId = m.assetId
	WHERE r.releaseId = ?
	LIMIT 1
SQL, [$releaseId]);
if(!$rootRelease) showErrorPage(HTTP_NOT_FOUND);

$maxKnownStableGameVersion = intval($con->getOne('SELECT MAX(version) FROM gameVersions WHERE (version & '.VERSION_MASK_PRERELEASE.') = '.VERSION_MASK_PRERELEASE));

/*
tests:
https://mods.vintagestory.stage/show/dependencies/20324
https://mods.vintagestory.stage/show/dependencies/23548
*/

class Dependency {
	function __construct(
		public string    $identifier,
		public int       $minVersion,

		public Resolution|null $resolution = null,
	) { }
}

/** @property array<int, Dependency> $children */
class Resolution {
	function __construct(
		public int $minVersion,

		public int|null $version = null,
		public array|null $children = null,
		public string|null $link = null,
		public array|null $mod = null,
		public string|null $fileName = null,
		public int|null $releaseId = null,

		public string|null $error = null,

		public $oneclick = null,

		public $printed = false, // Used to prevent recursive printing
	) { }
}

/**
 * @property array<Resolution> $resolutions
 * @property array<Resolution> $pendingResolutions
 */
class Context {
	function __construct(
		public Dependency $treeRoot,
		public array $resolutions,
		public array $pendingResolutions = [],
	) { }
}

$rr = new Resolution(intval($rootRelease['version']), intval($rootRelease['version']));
$rd = new Dependency($rootRelease['identifier'], intval($rootRelease['version']), $rr);
$context = new Context(
	$rd,
	[
		// Push the initial target release as resolved, since we are specifically searching for it it should be 'pinned'.
		$rootRelease['identifier'] => $rr,
	],
);

if($rootRelease['rawDependencies']) {
	pushDependenciesAsChildren($context, $context->treeRoot->resolution, $rootRelease['rawDependencies']);

	$MAX_STEPS = 100; // Arbitrary loop prevention, just in case.
	for($i = 0; $i < $MAX_STEPS && $context->pendingResolutions; $i++) {
		processPendingResolutions($context);
	}
}

// Postfix the builtin "mods", namely 'game', 'survival', 'creative'.
foreach(['game', 'survival', 'creative'] as $identifier) {
	$res = $context->resolutions[$identifier] ?? false;
	if(!$res) continue;

	$res->error = null;
	$res->version = $maxKnownStableGameVersion;
}


function pushDependenciesAsChildren(Context $context, Resolution $current, string $rawDependencies) {
	foreach(explode(', ', $rawDependencies) as $rawDependency) {
		splitOnce($rawDependency, '@', $dependencyIdentifier, $dependencyMinVersion);
		$dependencyMinVersion = $dependencyMinVersion === '' ? 0 : compileSemanticVersion($dependencyMinVersion);
		// Strip out "any game version" dependencies, as that is implied by the fact that its a mod and will only clutter the output.
		// You wold expect every mod to have this, but in reality only few actually do. Nevertheless, remove them.
		if($dependencyMinVersion === 0 && $dependencyIdentifier === 'game') continue;

		$resolution = $context->resolutions[$dependencyIdentifier] ?? false;
		if(!$resolution) {
			$resolution = new Resolution($dependencyMinVersion);
			$context->resolutions[$dependencyIdentifier] = $resolution;
			$context->pendingResolutions[$dependencyIdentifier] = $resolution;
		}
		$current->children[] = new Dependency($dependencyIdentifier, $dependencyMinVersion, $resolution);
	}
}

function processPendingResolutions(Context $context) {
	global $con;

	$pendingResolutions = $context->pendingResolutions;
	//NOTE(Rennorb): We already added the partial resolution into the context->resolutions table when pushing dependencies, so 
	// we wont be putting in the same keys we already are processing in this round, even if there is a loop.
	// It is therefore safe to just clear this and just collect the next round of things to process.
	$context->pendingResolutions = [];
	
	//TODO(Rennorb) @hammer @perf: construct a query that is able to fetch all the missing dependencies in one call.
	// This is the only reason I separated this from the other function in the first place, but right now I am not able to come up with a good query for that.
	foreach($pendingResolutions as $identifier => $resolution) {
		// @security: minVersion is numeric and therefore sql inert.
		$versionSearchSql = $resolution->minVersion === 0 ? '' : 'AND r.version >= '.$resolution->minVersion.' ORDER BY r.version DESC';
		$resolvingRelease = $con->getRow(<<<SQL
			SELECT m.modId, ma.name as `modName`, r.releaseId, f.fileId, f.name, r.version, mpr.errors IS NOT NULL AS hasErrors, mpr.rawDependencies
			FROM modReleases r
			JOIN mods m ON m.modId = r.modId
			JOIN assets ma ON ma.assetId = m.assetId
			JOIN files f ON f.assetId = r.assetId
			JOIN modPeekResults mpr ON mpr.fileId = f.fileId
			WHERE r.identifier = ? $versionSearchSql
			LIMIT 1
		SQL, [$identifier]);

		if(!$resolvingRelease) {
			$resolution->error = "Failed to find release that resolves this dependency.";
			continue;
		}

		$resolution->version = $resolvingRelease['version'];
		$resolution->mod = ['id' => $resolvingRelease['modId'], 'name' => $resolvingRelease['modName']];
		$resolution->fileName = $resolvingRelease['name'];
		$resolution->link = formatDownloadTrackingUrl($resolvingRelease);
		$resolution->releaseId = $resolvingRelease['releaseId'];

		if($resolvingRelease['rawDependencies']) {
			pushDependenciesAsChildren($context, $resolution, $resolvingRelease['rawDependencies']);
		}
	}
}


// Remove builtin "mods", namely 'game', 'survival', 'creative' just before displaying so they don't show up n the table.
// The tree is not affected by this.
foreach(['game', 'survival', 'creative'] as $identifier) {
	unset($context->resolutions[$identifier]);
}
// Also remove the root file from the list:
unset($context->resolutions[$rootRelease['identifier']]);

// Prepare data for 1-click-dl buttons:
foreach($context->resolutions as $identifier => $resolution) { //TODO(Rennorb) @perf: Get rid of this entirely, its unnecessary
	if($resolution->version) $resolution->oneclick = ['identifier' => $identifier, 'version' => $resolution->version];
}

ksort($context->resolutions, SORT_STRING);

// Prepare data for mod cards
{
	$directChildResolutions = [];
	foreach($context->treeRoot->resolution->children as $directChild) {
		if(($res = $directChild->resolution) && $res->mod) {
			$directChildResolutions[$res->mod['id']] = $res;
		}
	}
	if(count($directChildResolutions)) {
		$foldedIds = implode(',', array_keys($directChildResolutions));
		$currentUserId = intval($user['userId']);
		// @security: $foldedIds and $currentUserId are numeric and therefore sql inert.
		$mods = $con->execute("
			SELECT
				a.created,
				a.tagsCached,
				m.modId, m.assetId, m.urlAlias, m.downloads, m.comments, m.summary,
				l.cdnPath AS logoCdnPath,
				l.created < '".SQL_MOD_CARD_TRANSITION_DATE."' AS hasLegacyLogo,
				s.code AS statusCode,
				f.userId AS following
			FROM mods m
			JOIN assets a ON a.assetId = m.assetId
			LEFT JOIN status s ON s.statusId = a.statusId
			LEFT JOIN userFollowedMods f ON f.modId = m.modId and f.userId = $currentUserId
			LEFT JOIN files l ON l.fileId = m.cardLogoFileId
			WHERE m.modId IN ($foldedIds)
		");

		foreach($mods as $mod) {
			$res = $directChildResolutions[$mod['modId']];

			$mod['name'] = $res->mod['name'];
			$mod['dbPath'] = formatModPath($mod);
			$mod['tags'] = unwrapCachedTags($mod['tagsCached']);
			unset($mod['tagsCached']); // @perf for the template engine

			$res->mod = $mod;
		}
	}
}


cspPushAllowedInlineHandlerHash('sha256-Hzjw+jaMvglkCU/moLRBe/kljnMPTdxVYaD5oRgBvdY='); // location.hash='tab-tree'
cspPushAllowedInlineHandlerHash('sha256-3VMmL6Xy+VuUuduxZEdfXcpxMTaQp1R4ltGbj+W0AQw='); // location.hash='tab-list'
cspPushAllowedInlineHandlerHash('sha256-Cd19gmOOkQ90cXDxMkUPq46x0bELegro6PpxG9/bQ3U='); // location.hash='tab-direct'

$view->assign('shouldShowOneClickInstall', isOneClickInstallPlatform(), null, true);
$view->assign('rootRelease', $rootRelease, null, true);
$view->assign('context', $context, null, true);
$view->display('show-dependencies');
