<?php

/*
	Determine the game versions of interest:
		stable and
		unstable (newer than current stable if any)

	Match mod releases:
		latest stable release that is for the stable version of the game -> recommend
		latest unstable version that is either
			for the unstable version of the game, or
			for the stable version of the game, if the release is a newer unstable version than the stable release (only if there is no release for the unstable game version)
		-> recommend for testers
		If there are not releases for for either of these, select the latest release -> latest release for an outdated version of the game

	Examples assuming current game version = 5, and a newer unstable version = 6p also exists,
	RV = mod release version, GV = game version required by the corresponding mod release version:
		GV  RV
		5   2.5
		5   3
		5   4.1  -> Recommended
		6p  4.2  -> For testers

		5   2.5
		5   3    -> Recommended
		5   4.p1
		5   4.p2 -> For testers

		5   2.5
		5   3    -> Recommended
		5   4.p1
		6p  4.p2 -> For testers

		2   2.5
		2   3
		3   4.p1
		3   4.p2 -> Latest outdated

		Assuming we came here by searching for mods for GV 2:
		2   2.5
		2   3    -> Recommended*
		3   4.p1
		3   4.p2
*/

/**
 * @param int[]      $allGameVersions  must be ordered descending
 * @param int|null   $acceptablePrimaryVersion
 * @param int[]|null $acceptableSpecificVersions
 * @param int        &$out_highestAcceptableInputVersion
 * @param int|null   &$out_maxAcceptableStableVersion
 * @param int|null   &$out_maxAcceptableUnstableVersion
 * @return bool  true if the selection was influenced by the acceptable versions
 */
function selectDesiredVersions($allGameVersions, $acceptablePrimaryVersion, $acceptableSpecificVersions, &$out_highestAcceptableInputVersion, &$out_maxAcceptableStableVersion = null, &$out_maxAcceptableUnstableVersion = null)
{
	$out_highestAcceptableInputVersion = $allGameVersions[0];
	$recommendationIsInfluencedBySearch = false;

	if ($acceptablePrimaryVersion || $acceptableSpecificVersions) {
		foreach($allGameVersions as $gameVersion) {
			if(
				($acceptablePrimaryVersion && (($gameVersion & VERSION_MASK_PRIMARY) === $acceptablePrimaryVersion))
			|| ($acceptableSpecificVersions && in_array($gameVersion, $acceptableSpecificVersions, true))
			) {
				$out_highestAcceptableInputVersion = $gameVersion;
				$recommendationIsInfluencedBySearch = true;
				break;
			}
		}
	}

	$highestAcceptableVersionReached = false;
	foreach($allGameVersions as $gameVersion) {
		if(!$highestAcceptableVersionReached && $gameVersion !== $out_highestAcceptableInputVersion) continue;
		else $highestAcceptableVersionReached = true;

		if(isPreReleaseVersion($gameVersion)) {
			if(!$out_maxAcceptableUnstableVersion) {
				$out_maxAcceptableUnstableVersion = $gameVersion;
			}
		}
		else {
			if(!$out_maxAcceptableStableVersion) {
				$out_maxAcceptableStableVersion = $gameVersion;
				//NOTE(Rennorb): If there was no explicit search we might still have a newer, unstable game version.
				// We cap the "highest" target version to the highest Stable version in that case, so we dont get 'outdated' warnings 
				// when in reality we are only a pre-release ahead with the game versions.
				if(!$recommendationIsInfluencedBySearch) $out_highestAcceptableInputVersion = $gameVersion;
			}
			break;
		}
	}

	return $recommendationIsInfluencedBySearch;
}


/**
 * @param array<T> $releases
 * @param int|null $maxDesiredGameVersionStable
 * @param int|null $maxDesiredGameVersionUnstable
 * @param T|null   &$out_recommendedRelease
 * @param T|null   &$out_testersRelease
 * @param T|null   &$out_fallbackRelease
 */
function recommendReleases($releases, $maxDesiredGameVersionStable, $maxDesiredGameVersionUnstable, &$out_recommendedRelease = null, &$out_testersRelease = null, &$out_fallbackRelease = null)
{
	// Sort releases by max supported game version to find the recommendation.
	//NOTE(Rennorb): This is not the default sorting from the db, because we want the releases in mod version order (if we can) for the files tab.
	// Could consider swapping this around for @perf.
	//NOTE(Rennorb): usort is not a "stable sort", meaning if two elements compare equal their final ordering is not defined.
	// We do however need to keep the ordering of the releases if maxversions are the same.
	$releasesByMaxGameVersion = $releases;
	usort($releasesByMaxGameVersion, fn($a, $b) => (
		(($b['maxCompatibleGameVersion'] - $a['maxCompatibleGameVersion']) << 1) // Make room for the second property comparison. This difference should never be so large as to get cut of by shifting it by one. 
		| ($b['version'] > $a['version'] ? 1 : 0) // If two releases have the same maxversion, use their version to determine the order
	));

	$hasStableFallback = null;
	foreach($releasesByMaxGameVersion as $release) {
		if(in_array($maxDesiredGameVersionStable, $release['compatibleGameVersions'], true)) {
			if(!isPreReleaseVersion($release['version'])) {
				$out_recommendedRelease = $release;
				break; // If there is a newer unstable version we already found it.
			}
			else {
				// unstable version of the mod that is compatible wit the correct stable version of the game
				if(!$out_testersRelease)   $out_testersRelease = $release;
				continue;
			}
		}
		else {
			if(in_array($maxDesiredGameVersionUnstable, $release['compatibleGameVersions'], true)) {
				if(!$out_testersRelease)   $out_testersRelease = $release;
				continue;
			}
		}

		if($hasStableFallback) continue;

		if($out_testersRelease) {
			// If we have a more recent testers version we look for an older stable one as fallback,
			// otherwise just pick the latest one as fallback.
			foreach($release['compatibleGameVersions'] as $version) {
				if(!isPreReleaseVersion($version)) {
					$out_fallbackRelease = $release;
					$hasStableFallback = true;
					continue 2;
				}
			}
		}
		if(!$out_fallbackRelease) $out_fallbackRelease = $release;
	}
}