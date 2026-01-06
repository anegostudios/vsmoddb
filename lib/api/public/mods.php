<?php

const ERROR_SPEC_PARSE_FAILED          = 4001;
const ERROR_MISSING_SPEC_VERSION_NO_GV = 4002;
const ERROR_FORBIDDEN_IN_HOSTED_MODE   = 4031;
const ERROR_CANNOT_IGNORE_RETRACTION   = 4032;
const ERROR_SPEC_NOT_FOUND             = 4041;
const ERROR_RELEASE_RETRACTED          = 4101;
const ERROR_RELEASE_RETRACTED_FORCED   = 4102;

switch($urlparts[0]) {
	case 'install-information':
		validateMethod('GET');

		$gameVersion = 0;
		if(isset($_GET['gv'])) {
			$gameVersion = compileSemanticVersion($_GET['gv']);
			if(!$gameVersion) fail(HTTP_BAD_REQUEST, ['reason' => 'Invalid game version.']);
		}

		$ignoreRetraction = boolval($_GET['ignore-retractions'] ?? false);
		$hostedMode = boolval($_GET['hosted-mode'] ?? false);

		if(empty($_GET['ids']))  fail(HTTP_BAD_REQUEST, ['reason' => 'Missing ids.']);
		
		$result = [];

		$knownVersionQueryParams = [];
		$unknownVersionQueryParams = [];

		foreach(explode(',', $_GET['ids']) as $spec) {
			$r = [];
			
			splitOnce($spec, '@', $identifier, $versionStr);
			if($versionStr) {
				$ver = compileSemanticVersion($versionStr);
				if($ver === false) {
					$r['errorCode'] = ERROR_SPEC_PARSE_FAILED;
				}
				else  array_push($knownVersionQueryParams, $identifier, $ver);
			}
			else if($gameVersion) {
				array_push($unknownVersionQueryParams, $identifier);
			}
			else {
				$r['errorCode'] = ERROR_MISSING_SPEC_VERSION_NO_GV;
			}

			$result[$identifier] = $r;
		}

		if($hostedMode) {
			foreach($result as &$r) {
				if(!$r) {
					$r['errorCode'] = ERROR_FORBIDDEN_IN_HOSTED_MODE;
				}
			}
			unset($r);
	
			good(['data' => $result]);
		}

		if(!$knownVersionQueryParams && !$unknownVersionQueryParams) {
			fail(HTTP_BAD_REQUEST, ['reason' => 'All requested ids are malformed.', 'data' => $result]);
		}

		$VERSION_MASK_PRERELEASE = VERSION_MASK_PRERELEASE;

		function canIgnoreRetraction($release)
		{
			// Allow overwriting retractions only if it wasn't recrated by a moderator, unless that moderator is also the mod owner:
			return ($release['retractedByRoleId'] !== ROLE_ADMIN && $release['retractedByRoleId'] !== ROLE_MODERATOR) || $release['retractedByUserId'] === $release['createdByUserId'];
		}


		//NOTE(Rennorb): The trick to selecting all requested mods at once here is to form two almost identical queries, 
		// then join them onto each other under the condition that the second one finds a higher version than the first.
		// This is not possible for the highest version in the first query, so we can then filter for null in the second query
		// to obtain the row that has the highest version in the first row. This is possible with arbitrary amounts of "groups"
		// if crafted carefully. Note that we cannot use 'group by' in most cases, as it is up to the implementation to select any row
		// for non aggregated columns, even different ones for each column, where we would need the first - or first by some other column.

		if($knownVersionQueryParams) {
			$placeholders = substr(str_repeat('(?, ?),', count($knownVersionQueryParams) / 2), 0, -1);

			if($gameVersion) {
				// Complicated path with upgrade recommendations:
				$releases = $con->execute(<<<SQL
					SELECT 
						r0.identifier, f0.fileId, f0.name, rr0.reason AS retractionReason,
						ru0.roleId AS retractedByRoleId, rr0.lastModifiedBy AS retractedByUserId, a0.createdByUserId,
						r1.version AS recommendedUpgrade

					FROM modReleases r0
					JOIN assets a0 ON a0.assetId = r0.assetId
					LEFT JOIN files f0 ON f0.assetId = r0.assetId
					LEFT JOIN modReleaseRetractions rr0 ON rr0.releaseId = r0.releaseId
					LEFT JOIN users ru0 ON ru0.userId = rr0.lastModifiedBy

					LEFT JOIN (
						SELECT r1.modId, r1.version
						FROM modReleases r1
						JOIN modReleaseCompatibleGameVersions cgv1 ON cgv1.releaseId = r1.releaseId AND cgv1.gameVersion = $gameVersion
						LEFT JOIN modReleaseRetractions rr1 ON rr1.releaseId = r1.releaseId
						WHERE rr1.reason IS NULL AND (r1.version & $VERSION_MASK_PRERELEASE) = 0xffff
					) r1 ON r1.modId = r0.modId AND r1.version > r0.version

					LEFT JOIN (
						SELECT r2.modId, r2.version
						FROM modReleases r2
						JOIN modReleaseCompatibleGameVersions cgv2 ON cgv2.releaseId = r2.releaseId AND cgv2.gameVersion = $gameVersion
						LEFT JOIN modReleaseRetractions rr2 ON rr2.releaseId = r2.releaseId
						WHERE rr2.reason IS NULL AND (r2.version & $VERSION_MASK_PRERELEASE) = 0xffff
					) r2 ON r2.modId = r1.modId AND r2.version > r1.version

					WHERE r2.version IS NULL AND

					(r0.identifier, r0.version) IN ($placeholders)
				SQL, $knownVersionQueryParams);

				foreach($releases as $release) {
					$r = &$result[$release['identifier']];

					if($release['recommendedUpgrade']) {
						$r['recommendedUpgrade'] = formatSemanticVersion(intval($release['recommendedUpgrade']));
					}

					if($release['retractionReason']) {
						$r['retractionReason'] = $release['retractionReason'];

						if($ignoreRetraction) {
							if(!canIgnoreRetraction($release)) {
								$r['errorCode'] = ERROR_CANNOT_IGNORE_RETRACTION;
								continue;
							}
						}
						else {
							$r['errorCode'] = canIgnoreRetraction($release) ? ERROR_RELEASE_RETRACTED : ERROR_RELEASE_RETRACTED_FORCED;
							continue;
						}
					}

					$r['fileName'] = $release['name'];
					$r['fileUrl'] = formatDownloadTrackingUrl($release);
				}
				unset($r);
			}
			else {
				// Simple path, no recommends:
				$releases = $con->execute(<<<SQL
					SELECT 
						r0.identifier, f0.fileId, f0.name, rr0.reason as retractionReason,
						ru0.roleId AS retractedByRoleId, rr0.lastModifiedBy AS retractedByUserId, a0.createdByUserId

					FROM modReleases r0
					JOIN assets a0 ON a0.assetId = r0.assetId
					LEFT JOIN files f0 ON f0.assetId = r0.assetId
					LEFT JOIN modReleaseRetractions rr0 ON rr0.releaseId = r0.releaseId
					LEFT JOIN users ru0 ON ru0.userId = rr0.lastModifiedBy

					WHERE (r0.identifier, r0.version) IN ($placeholders)
				SQL, $knownVersionQueryParams);
				foreach($releases as $release) {
					$r = &$result[$release['identifier']];

					if($release['retractionReason']) {
						$r['retractionReason'] = $release['retractionReason'];

						if($ignoreRetraction) {
							if(!canIgnoreRetraction($release)) {
								$r['errorCode'] = ERROR_CANNOT_IGNORE_RETRACTION;
								continue;
							}
						}
						else {
							$r['errorCode'] = canIgnoreRetraction($release) ? ERROR_RELEASE_RETRACTED : ERROR_RELEASE_RETRACTED_FORCED;
							continue;
						}
					}

					$r['fileName'] = $release['name'];
					$r['fileUrl'] = formatDownloadTrackingUrl($release);
				}
				unset($r);
			}
		}
		
		if($unknownVersionQueryParams && $gameVersion) {
			// Complicated path. Can only exist if we have a gameversion to recommend any releases for.
			// Never recommend retracted releases.
			$placeholders = substr(str_repeat('?,', count($unknownVersionQueryParams)), 0, -1);

			$releases = $con->execute(<<<SQL
				SELECT 
					r1.identifier, f1.fileId, f1.name,
					r1.version as recommendedUpgrade

				FROM (
					SELECT r1.releaseId, r1.identifier, r1.modId, r1.assetId, r1.version, rr1.reason
					FROM modReleases r1
					JOIN modReleaseCompatibleGameVersions cgv1 ON cgv1.releaseId = r1.releaseId AND cgv1.gameVersion = $gameVersion
					LEFT JOIN modReleaseRetractions rr1 ON rr1.releaseId = r1.releaseId
					WHERE rr1.reason IS NULL AND (r1.version & $VERSION_MASK_PRERELEASE) = 0xffff AND
					r1.identifier IN ($placeholders)
				) r1

				LEFT JOIN (
					SELECT r2.modId, r2.version
					FROM modReleases r2
					JOIN modReleaseCompatibleGameVersions cgv2 ON cgv2.releaseId = r2.releaseId AND cgv2.gameVersion = $gameVersion
					LEFT JOIN modReleaseRetractions rr2 ON rr2.releaseId = r2.releaseId
					WHERE rr2.reason IS NULL AND (r2.version & $VERSION_MASK_PRERELEASE) = 0xffff
				) r2 ON r2.modId = r1.modId AND r2.version > r1.version

				LEFT JOIN files f1 ON f1.assetId = r1.assetId
				LEFT JOIN modReleaseRetractions rr1 ON rr1.releaseId = r1.releaseId

				WHERE r2.version IS NULL
			SQL, $unknownVersionQueryParams);

			foreach($releases as $release) {
				$r = &$result[$release['identifier']];
				$r['fileName'] = $release['name'];
				$r['fileUrl'] = formatDownloadTrackingUrl($release);
				if($release['recommendedUpgrade']) $r['recommendedUpgrade'] = formatSemanticVersion(intval($release['recommendedUpgrade']));
			}
			unset($r);
		}

		foreach($result as &$r) {
			if(!$r) {
				$r['errorCode'] = ERROR_SPEC_NOT_FOUND;
			}
		}
		unset($r);

		good(['data' => $result]);
}
