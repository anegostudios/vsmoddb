<?php

include_once $config['basepath'].'lib/relations.php';

const ERROR_SPEC_PARSE_FAILED          = 4001;
const ERROR_MISSING_SPEC_VERSION_NO_GV = 4002;
const ERROR_FORBIDDEN_IN_HOSTED_MODE   = 4031;
const ERROR_CANNOT_IGNORE_RETRACTION   = 4032;
const ERROR_SPEC_NOT_FOUND             = 4041;
const ERROR_RELEASE_RETRACTED          = 4101;
const ERROR_RELEASE_RETRACTED_FORCED   = 4102;

function canIgnoreRetraction($release)
{
	// Allow overwriting retractions only if it wasn't recrated by a moderator, unless that moderator is also the mod owner:
	return ($release['retractedByRoleId'] !== ROLE_ADMIN && $release['retractedByRoleId'] !== ROLE_MODERATOR) || $release['retractedByUserId'] === $release['createdByUserId'];
}

switch($urlparts[0]) {
	case 'install-information':
		validateMethod('GET');

		$gameVersion = 0;
		if(isset($_GET['gv'])) {
			$gameVersion = compileSemanticVersion($_GET['gv']);
			if(!$gameVersion) fail(HTTP_BAD_REQUEST, 'Invalid game version.');
		}

		$ignoreRetraction = boolval($_GET['ignore-retractions'] ?? false); // TODO(Rennorb) @cleanup: avoid round trip for retractions. just send the links if it can be ignored the first time round. 
		$hostedMode = boolval($_GET['hosted-mode'] ?? false);

		if(empty($_GET['ids']))  fail(HTTP_BAD_REQUEST, 'Missing ids.');
		
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
			fail(HTTP_BAD_REQUEST, ['error' => 'All requested ids are malformed.', 'data' => $result]);
		}

		$VERSION_MASK_PRERELEASE = VERSION_MASK_PRERELEASE;

		//NOTE(Rennorb): The trick to selecting all requested mods at once here is to form two almost identical queries, 
		// then join them onto each other under the condition that the second one finds a higher version than the first.
		// This is not possible for the highest version in the first query, so we can then filter for null in the second query
		// to obtain the row that has the highest version in the first row. This is possible with arbitrary amounts of "groups"
		// if crafted carefully. Note that we cannot use 'group by' in most cases, as it is up to the implementation to select any row
		// for non aggregated columns, even different ones for each column, where we would need the first - or first by some other column.

		// Tracks the release the caller explicitly asked for (or that we recommended in the unknown-version
		// path). Used to seed the dep resolver below so the dependency tree reflects the exact release
		// being installed, not whatever pickReleaseForIdentifier would re-pick.
		$pickedRootReleases = [];

		if($knownVersionQueryParams) {
			$placeholders = substr(str_repeat('(?, ?),', count($knownVersionQueryParams) / 2), 0, -1);

			if($gameVersion) {
				// Complicated path with upgrade recommendations:
				$releases = $con->execute(<<<SQL
					SELECT
						r0.releaseId, r0.identifier, r0.version, f0.fileId, f0.name, rr0.reason AS retractionReason,
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

					$pickedRootReleases[$release['identifier']] = [
						'releaseId'  => (int)$release['releaseId'],
						'identifier' => $release['identifier'],
						'version'    => (int)$release['version'],
						'fileName'   => $release['name'],
						'fileUrl'    => $r['fileUrl'],
					];
				}
				unset($r);
			}
			else {
				// Simple path, no recommends:
				$releases = $con->execute(<<<SQL
					SELECT
						r0.releaseId, r0.identifier, r0.version, f0.fileId, f0.name, rr0.reason as retractionReason,
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

					$pickedRootReleases[$release['identifier']] = [
						'releaseId'  => (int)$release['releaseId'],
						'identifier' => $release['identifier'],
						'version'    => (int)$release['version'],
						'fileName'   => $release['name'],
						'fileUrl'    => $r['fileUrl'],
					];
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
					r1.releaseId, r1.identifier, f1.fileId, f1.name,
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

				$pickedRootReleases[$release['identifier']] = [
					'releaseId'  => (int)$release['releaseId'],
					'identifier' => $release['identifier'],
					'version'    => (int)$release['recommendedUpgrade'],
					'fileName'   => $release['name'],
					'fileUrl'    => $r['fileUrl'],
				];
			}
			unset($r);
		}

		foreach($result as &$r) {
			if(!$r) {
				$r['errorCode'] = ERROR_SPEC_NOT_FOUND;
			}
		}
		unset($r);

		$resolvePayload = [];
		if (boolval($_GET['resolve-deps'] ?? false)) {
			// Don't pass identifiers with already-known errors into the resolver.
			// They are unavailable / malformed / retracted and should not surface dependency trees.
			$rootIds = array_keys(array_filter($result, fn($r) => empty($r['errorCode'])));
			// Seed the resolver with the releases picked above so the dep tree starts from the
			// exact (identifier, version) requested in the URL instead of pickReleaseForIdentifier's
			// "latest available" fallback. Transitive deps still use that fallback.
			$rootMap = array_intersect_key($pickedRootReleases, array_flip($rootIds));
			$deps = resolveTransitiveDeps($rootIds, $gameVersion ?: null, $rootMap);
			$resolvePayload = ['resolved' => $deps['resolved'], 'installOrder' => $deps['installOrder'], 'warnings' => $deps['warnings']];
		}
		good(['data' => $result] + $resolvePayload);

	default:
		// /mods/{modId}
		$modId = filter_var($urlparts[0], FILTER_VALIDATE_INT);
		if(!$modId) break; // fallthrough into the authenticated section.

		switch($urlparts[1] ?? null) {
			case 'releases': // /mods/{modId}/releases
				switch($_SERVER['REQUEST_METHOD']) {
					case 'GET':
						$modExists = $con->getOne("
							SELECT 1
							FROM mods m
							JOIN assets a ON a.assetId = m.assetId AND a.statusId = ".STATUS_RELEASED."
							WHERE modId = $modId
						"); // @security $modId is validated to be int, therefore sql inert.

						if(!$modExists)   fail(HTTP_NOT_FOUND, 'Mod not found or not released.');


						switch(count($urlparts)) {
							case 2:
								// list endpoint /mods/{modId}/releases

								$queryJoins = '';
								$queryWhere = 'r.modId = '.$modId; // @security: $modId is validated to be int, therefore sql inert.

								if(boolval($_GET['ignore-retractions'] ?? false)) {
									// Allow overwriting retractions only if it wasn't recrated by a moderator, unless that moderator is also the mod owner:
									$queryJoins .= ' JOIN assets a ON a.assetId = r.assetId LEFT JOIN users ru ON ru.userId = rr.lastModifiedBy';
									$queryWhere .= ' AND (rr.reason IS NULL OR (ru.roleId != '.ROLE_ADMIN.' AND ru.roleId != '.ROLE_MODERATOR.') OR rr.lastModifiedBy = a.createdByUserId)';
								}
								else {
									$queryWhere .= ' AND rr.reason IS NULL';
								}

								$releases = $con->getAssoc(<<<SQL
									SELECT r.releaseId, r.identifier, r.version,
										rr.reason AS retractionReason
									FROM modReleases r
									LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
									$queryJoins
									WHERE $queryWhere
									ORDER BY r.version DESC
								SQL);

								foreach($releases as &$release) {
									$release['version'] = formatSemanticVersion($release['version']);
									if(!$release['retractionReason']) unset($release['retractionReason']);
								}
								unset($release);

								good($releases, JSON_FORCE_OBJECT);
							
							case 3: // GET specific release
								$queryWhere = 'r.modId = '.$modId; // @security: $modId filtered to be int, therefore sql inert.
								$queryParams = [];

								if($urlparts[2] === 'latest') { // /mods/{modId}/releases/latest
									//NOTE(Rennorb): The latest release is already selected by the order by clause if we don't specify a releaseId in the where clause.

									if($identifier = $_GET['identifier'] ?? '') {
										$queryWhere .= ' AND r.identifier = ?';
										$queryParams[] = $identifier;
									}

									if(!boolval($_GET['ignore-retractions'] ?? false)) {
										$queryWhere .= ' AND (rr.reason IS NULL OR (ru.roleId != '.ROLE_ADMIN.' AND ru.roleId != '.ROLE_MODERATOR.') OR rr.lastModifiedBy = a.createdByUserId)'; // a copy of canIgnoreRetraction in sql
									}
								}
								else if($releaseId = filter_var($urlparts[2], FILTER_VALIDATE_INT)) { // /mods/{modId}/releases/{releaseId}
									$queryWhere .= ' AND r.releaseId = '.$releaseId; // @security: $releaseId filtered to be int, therefore sql inert.
								}
								else {
									fail(HTTP_BAD_REQUEST, 'Malformed releaseId.');
								}

								$release = $con->getRow(<<<SQL
									SELECT r.releaseId, r.identifier, r.version, UNIX_TIMESTAMP(r.created) AS created,
										f.fileId, f.name,
										rr.reason AS retractionReason, ru.roleId AS retractedByRoleId, rr.lastModifiedBy AS retractedByUserId, a.createdByUserId,
										GROUP_CONCAT(cgv.gameVersion ORDER BY cgv.gameVersion DESC SEPARATOR ';') AS compatibleGameVersions
									FROM modReleases r
									JOIN assets a ON a.assetId = r.assetId
									LEFT JOIN files f ON f.assetId = r.assetId
									LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
									LEFT JOIN users ru ON ru.userId = rr.lastModifiedBy
									LEFT JOIN modReleaseCompatibleGameVersions cgv ON cgv.releaseId = r.releaseId
									WHERE $queryWhere
									GROUP BY r.releaseId
									ORDER BY r.version DESC
									LIMIT 1
								SQL, $queryParams);

								if(!$release) fail(HTTP_NOT_FOUND, 'Release not found.');

								$response = [
									'releaseId'  => intval($release['releaseId']),
									'identifier' => $release['identifier'],
									'version'    => formatSemanticVersion(intval($release['version'])),
									'compatibleGameVersions' => $release['compatibleGameVersions']
										? array_map(fn($v) => formatSemanticVersion(intval($v)), explode(';', $release['compatibleGameVersions']))
										: [],
									'created'    => intval($release['created']),
								];

								if($release['retractionReason']) {
									$response['retractionReason'] = $release['retractionReason'];
								}

								if(!$release['retractionReason'] || canIgnoreRetraction($release)) {
									$response['fileName'] = $release['name'];
									$response['fileUrl']  = $release['fileId'] ? formatDownloadTrackingUrl($release) : null;
								}

								good($response);

							default:
								break; // fall though to authenticated eps. maybe TODO(Rennorb) @cleanup: merge auch / public? 
						}
				}
		}
}
