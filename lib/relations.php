<?php

const REL_REQUIRED      = 'required';
const REL_OPTIONAL      = 'optional';
const REL_INCOMPATIBLE  = 'incompatible';
const REL_TESTED_WITH   = 'tested_with';

const REL_ORIGIN_AUTO   = 'auto';
const REL_ORIGIN_MANUAL = 'manual';

// Identifiers from modinfo.json that we never store as mod relations
// (game version compat lives in modReleaseCompatibleGameVersions; survival/creative are bundled).
const IGNORED_AUTO_IDENTIFIERS = ['game', 'survival', 'creative'];

// Hard cap on BFS depth during transitive resolution to guard against pathological graphs.
const MAX_DEPS_DEPTH = 32;

/**
 * Parse a modinfo dependency string ("modA@1.2.3, modB, modC@2.0.0") into an associative array
 * [identifier => compiledMinVersion]. Returns 0 for entries without a version OR with a version
 * string that fails to parse. Filters IGNORED_AUTO_IDENTIFIERS and the wildcard '*'. If an
 * identifier appears more than once, keeps the highest compiled version.
 *
 * @return array<string, int>
 */
function parseRawDeps(?string $rawDependencies): array
{
	if (!$rawDependencies) return [];
	$result = [];
	foreach (explode(', ', $rawDependencies) as $dep) {
		splitOnce($dep, '@', $id, $versionStr);
		if ($id === '' || $id === '*' || in_array($id, IGNORED_AUTO_IDENTIFIERS, true)) continue;
		$compiled = $versionStr ? (compileSemanticVersion($versionStr) ?: 0) : 0;
		if (!isset($result[$id]) || $compiled > $result[$id]) {
			$result[$id] = $compiled;
		}
	}
	return $result;
}

/**
 * Intersect a set of version range constraints. Each constraint is ['from'=>string, 'min'=>?int, 'max'=>?int]
 * where NULL means open. The result is the tightest enclosing range, plus an `unsatisfiable` flag if the
 * effective min would exceed the effective max.
 *
 * @param array<array{from:string,min:?int,max:?int}> $constraints
 * @return array{effectiveMin:?int, effectiveMax:?int, unsatisfiable:bool}
 */
function mergeRanges(array $constraints): array
{
	$effMin = null;
	$effMax = null;
	foreach ($constraints as $c) {
		if ($c['min'] !== null && ($effMin === null || $c['min'] > $effMin)) $effMin = $c['min'];
		if ($c['max'] !== null && ($effMax === null || $c['max'] < $effMax)) $effMax = $c['max'];
	}
	$unsat = ($effMin !== null && $effMax !== null && $effMin > $effMax);
	return ['effectiveMin' => $effMin, 'effectiveMax' => $effMax, 'unsatisfiable' => $unsat];
}

/**
 * Pure BFS resolver. Same shape as before; relations are now release-scoped so the loader callback
 * resolves identifier -> picked release -> relations of that specific release.
 *
 * @param string[]                 $rootIdentifiers
 * @param callable(string): array  $relationsLoader  identifier -> array of relation rows
 * @param callable(string): ?array $releasePicker    identifier -> ['fileName'=>..., 'fileUrl'=>..., ...] or null
 * @return array{resolved: array<string, array<string,mixed>>, warnings: array<array<string,mixed>>}
 */
function bfsResolve(array $rootIdentifiers, callable $relationsLoader, callable $releasePicker): array
{
	$queue            = [];
	$resolved         = [];
	$versionConstrs   = [];
	$incompatDeclared = [];
	$informational    = [];
	$warnings         = [];

	foreach ($rootIdentifiers as $id) {
		$queue[] = [$id, []];
	}

	while ($queue) {
		[$id, $chain] = array_shift($queue);

		if (in_array($id, $chain, true)) {
			$warnings[] = ['kind' => 'cycle', 'path' => array_merge($chain, [$id])];
			continue;
		}
		if (count($chain) > MAX_DEPS_DEPTH) {
			$warnings[] = ['kind' => 'depth_limit', 'stoppedAt' => $id, 'limit' => MAX_DEPS_DEPTH];
			continue;
		}
		if (isset($resolved[$id])) {
			$parent = end($chain) !== false ? end($chain) : '<root>';
			if (!in_array($parent, $resolved[$id]['requiredBy'], true)) {
				$resolved[$id]['requiredBy'][] = $parent;
			}
			continue;
		}

		$release = $releasePicker($id);
		if ($release === null) {
			$warnings[] = ['kind' => 'missing_dep', 'identifier' => $id, 'requiredBy' => $chain];
			continue;
		}
		$parent = end($chain);
		$resolved[$id] = $release + [
			'requiredBy' => [$parent !== false ? $parent : '<root>'],
			'depth'      => count($chain),
		];

		foreach ($relationsLoader($id) as $rel) {
			switch ($rel['relationType']) {
				case REL_REQUIRED:
					$versionConstrs[$rel['targetIdentifier']][] = [
						'from' => $id,
						'min'  => $rel['minVersion'] ?? null,
						'max'  => $rel['maxVersion'] ?? null,
					];
					$queue[] = [$rel['targetIdentifier'], array_merge($chain, [$id])];
					break;
				case REL_INCOMPATIBLE:
					$incompatDeclared[$id][] = $rel['targetIdentifier'];
					break;
				case REL_OPTIONAL:
				case REL_TESTED_WITH:
					$informational[] = [
						'kind'       => $rel['relationType'].'_unmet',
						'from'       => $id,
						'identifier' => $rel['targetIdentifier'],
					];
					break;
			}
		}
	}

	foreach ($incompatDeclared as $declarer => $targets) {
		foreach ($targets as $target) {
			if (isset($resolved[$target]) || in_array($target, $rootIdentifiers, true)) {
				$warnings[] = ['kind' => 'incompatible', 'between' => [$declarer, $target], 'declaredBy' => $declarer];
			}
		}
	}

	foreach ($versionConstrs as $target => $constraints) {
		$merged = mergeRanges($constraints);
		if ($merged['unsatisfiable']) {
			$warnings[] = ['kind' => 'version_conflict', 'identifier' => $target, 'ranges' => $constraints];
		}
	}

	foreach ($informational as $info) {
		if (!isset($resolved[$info['identifier']]) && !in_array($info['identifier'], $rootIdentifiers, true)) {
			$warnings[] = $info;
		}
	}

	return ['resolved' => $resolved, 'warnings' => $warnings];
}

/**
 * Pure cycle-guard helper. Returns true iff adding (sourceIdentifier -required-> targetIdentifier)
 * would close a cycle in the required-relation graph supplied as $graph.
 *
 * @param array<string, array<array{target:string,type:string}>> $graph  identifier -> outbound edges
 */
function wouldCreateCycleInGraph(string $sourceIdentifier, string $targetIdentifier, string $relationType, array $graph): bool
{
	if ($relationType !== REL_REQUIRED) return false;
	if ($sourceIdentifier === $targetIdentifier) return true;

	$stack   = [$targetIdentifier];
	$visited = [];
	while ($stack) {
		$cur = array_pop($stack);
		if (isset($visited[$cur])) continue;
		$visited[$cur] = true;
		if ($cur === $sourceIdentifier) return true;
		foreach ($graph[$cur] ?? [] as $edge) {
			if ($edge['type'] === REL_REQUIRED) $stack[] = $edge['target'];
		}
	}
	return false;
}

/**
 * DB-backed wrapper: true if declaring (releaseId -required-> targetIdentifier) would create a cycle
 * in the latest-release-per-identifier required-relation graph. Used by the UI to warn at relation
 * declaration time. Returns false if the source release has no identifier (release without modinfo).
 */
function wouldCreateCycle(int $sourceReleaseId, string $targetIdentifier): bool
{
	global $con;

	$sourceIdentifier = $con->getOne(
		"SELECT identifier FROM modReleases WHERE releaseId = ? AND identifier IS NOT NULL",
		[$sourceReleaseId]
	);
	if (!$sourceIdentifier) return false;

	// Build the required-only adjacency map keyed on identifier, using each identifier's most recent release.
	$rows = $con->getAll(
		"SELECT srcr.identifier AS src, r.targetIdentifier AS tgt
		 FROM modRelations r
		 JOIN (
		     SELECT mr.releaseId, mr.identifier
		     FROM modReleases mr
		     JOIN (
		         SELECT identifier, MAX(releaseId) AS maxRel
		         FROM modReleases
		         WHERE identifier IS NOT NULL
		         GROUP BY identifier
		     ) latest ON latest.identifier = mr.identifier AND latest.maxRel = mr.releaseId
		 ) srcr ON srcr.releaseId = r.releaseId
		 WHERE r.relationType = ?",
		[REL_REQUIRED]
	);
	$graph = [];
	foreach ($rows as $row) {
		$graph[$row['src']][] = ['target' => $row['tgt'], 'type' => REL_REQUIRED];
	}

	return wouldCreateCycleInGraph($sourceIdentifier, $targetIdentifier, REL_REQUIRED, $graph);
}

/** Internal: resolve targetIdentifier to a targetModId via modReleases.identifier. Returns null if not found. */
function _resolveTargetModId(string $targetIdentifier): ?int
{
	global $con;
	$id = $con->getOne(
		"SELECT m.modId FROM mods m JOIN modReleases r ON r.modId = m.modId WHERE r.identifier = ? LIMIT 1",
		[$targetIdentifier]
	);
	return $id !== null ? (int)$id : null;
}

/**
 * Manually upsert a relation declared via the UI. Always origin='manual'.
 * If a row with the same (releaseId, targetIdentifier, relationType) already exists,
 * its versions and origin are updated to manual.
 */
function upsertManualRelation(int $releaseId, string $targetIdentifier, string $relationType, ?int $minVersion, ?int $maxVersion): int
{
	global $con, $user;

	$existingId = $con->getOne(
		"SELECT relationId FROM modRelations
		 WHERE releaseId = ? AND targetIdentifier = ? AND relationType = ?",
		[$releaseId, $targetIdentifier, $relationType]
	);

	$targetModId = _resolveTargetModId($targetIdentifier);

	if ($existingId) {
		$con->execute(
			"UPDATE modRelations SET minVersion = ?, maxVersion = ?, origin = ?, targetModId = ? WHERE relationId = ?",
			[$minVersion, $maxVersion, REL_ORIGIN_MANUAL, $targetModId, $existingId]
		);
		return (int)$existingId;
	}

	$con->execute(
		"INSERT INTO modRelations (releaseId, targetIdentifier, targetModId, relationType, minVersion, maxVersion, origin, createdByUserId)
		 VALUES (?,?,?,?,?,?,?,?)",
		[$releaseId, $targetIdentifier, $targetModId, $relationType, $minVersion, $maxVersion, REL_ORIGIN_MANUAL, $user['userId']]
	);
	return (int)$con->Insert_ID();
}

/** Removes a manual relation. Auto rows are not affected. */
function deleteManualRelation(int $relationId): void
{
	global $con;
	$con->execute("DELETE FROM modRelations WHERE relationId = ? AND origin = ?", [$relationId, REL_ORIGIN_MANUAL]);
}

/** Internal: hydrate a list of relation rows with their resolvedMod link (null if target doesn't exist). */
function _hydrateResolvedMod(array $rows): array
{
	global $con;
	if (!$rows) return $rows;
	$modIds = array_filter(array_unique(array_column($rows, 'targetModId')));
	$modsByModId = [];
	if ($modIds) {
		$in = implode(',', array_map('intval', $modIds));
		// @security: $in built from integer column values, sql inert.
		$modsRows = $con->getAll("
			SELECT m.modId, m.assetId, a.name, m.urlAlias, m.summary
			FROM mods m JOIN assets a ON a.assetId = m.assetId
			WHERE m.modId IN ($in)");
		foreach ($modsRows as $m) $modsByModId[(int)$m['modId']] = $m;
	}
	foreach ($rows as &$r) {
		$r['resolvedMod'] = isset($modsByModId[(int)$r['targetModId']]) ? $modsByModId[(int)$r['targetModId']] : null;
	}
	return $rows;
}

/**
 * Internal: load the deduped (manual-wins) relation rows for a release, WITHOUT hydrating
 * resolvedMod data. Used by display helpers (which add hydration on top) and by the BFS
 * resolver (which only needs targetIdentifier / relationType / version bounds for graph walking).
 */
function _loadDedupedRelationsForRelease(int $releaseId, ?string $relationType = null): array
{
	global $con;

	$where  = "releaseId = ?";
	$params = [$releaseId];
	if ($relationType !== null) {
		$where .= " AND relationType = ?";
		$params[] = $relationType;
	}

	$rows = $con->getAll("SELECT * FROM modRelations WHERE $where", $params);

	// Re-resolve targetModId on every call (cheap, catches mods uploaded after relation declaration).
	foreach ($rows as &$r) {
		if (!$r['targetModId']) $r['targetModId'] = _resolveTargetModId($r['targetIdentifier']);
	}
	unset($r);

	// Dedupe with manual winning.
	$byKey = [];
	foreach ($rows as $row) {
		$key = $row['targetIdentifier'].'|'.$row['relationType'];
		if (!isset($byKey[$key]) || $row['origin'] === REL_ORIGIN_MANUAL) $byKey[$key] = $row;
	}
	return array_values($byKey);
}

/**
 * Merged display view for a specific release: manual rows ∪ auto rows of the same release.
 * Manual wins on (targetIdentifier, relationType) collisions. Caller specifies the release.
 */
function getRelationsForRelease(int $releaseId, ?string $relationType = null): array
{
	return _hydrateResolvedMod(_loadDedupedRelationsForRelease($releaseId, $relationType));
}

/**
 * Edit-view: returns auto rows and manual rows of a release separately so the UI can render
 * two distinct sections.
 *
 * @return array{auto:array, manual:array}
 */
function getRelationsForReleaseEditView(int $releaseId): array
{
	global $con;

	$autoRows = $con->getAll(
		"SELECT * FROM modRelations WHERE releaseId = ? AND origin = ?",
		[$releaseId, REL_ORIGIN_AUTO]
	);
	$byKey = [];
	foreach ($autoRows as $row) $byKey[$row['targetIdentifier'].'|'.$row['relationType']] = $row;
	$auto = array_values($byKey);

	$manual = $con->getAll(
		"SELECT * FROM modRelations WHERE releaseId = ? AND origin = ?",
		[$releaseId, REL_ORIGIN_MANUAL]
	);

	return ['auto' => _hydrateResolvedMod($auto), 'manual' => _hydrateResolvedMod($manual)];
}

/**
 * Public infobox helper: returns the merged relations for the latest non-retracted release of a mod.
 * If the mod hosts multiple identifiers, the most recently released release is used (matching the
 * "latest release" surfaced elsewhere on the mod page).
 */
function getRelationsForLatestReleaseOfMod(int $modId, ?string $relationType = null): array
{
	global $con;

	$latestReleaseId = $con->getOne(
		"SELECT r.releaseId
		 FROM modReleases r
		 LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
		 WHERE r.modId = ? AND rr.reason IS NULL
		 ORDER BY r.releaseId DESC LIMIT 1",
		[$modId]
	);
	if (!$latestReleaseId) return [];
	return getRelationsForRelease((int)$latestReleaseId, $relationType);
}

/**
 * Re-syncs the auto-derived 'required' relations for a release based on its parsed modinfo dependencies.
 * Idempotent. Manual rows are never touched. Auto rows for this release that no longer appear in
 * $rawDependencies are deleted; new ones are inserted; existing ones are updated when minVersion changes.
 */
function syncAutoRelationsForRelease(int $releaseId, ?string $rawDependencies): void
{
	global $con, $user;

	$desired = parseRawDeps($rawDependencies);

	$existing = $con->getAll(
		"SELECT relationId, targetIdentifier, minVersion FROM modRelations
		 WHERE releaseId = ? AND origin = ? AND relationType = ?",
		[$releaseId, REL_ORIGIN_AUTO, REL_REQUIRED]
	);
	$existingByTarget = [];
	foreach ($existing as $row) $existingByTarget[$row['targetIdentifier']] = $row;

	$con->startTrans();

	foreach ($desired as $target => $minVersion) {
		if (isset($existingByTarget[$target])) {
			if ((int)$existingByTarget[$target]['minVersion'] !== (int)$minVersion) {
				$con->execute(
					"UPDATE modRelations SET minVersion = ?, targetModId = ? WHERE relationId = ?",
					[$minVersion ?: null, _resolveTargetModId($target), $existingByTarget[$target]['relationId']]
				);
			}
			unset($existingByTarget[$target]);
		} else {
			$con->execute(
				"INSERT INTO modRelations (releaseId, targetIdentifier, targetModId, relationType, minVersion, origin, createdByUserId)
				 VALUES (?,?,?,?,?,?,?)",
				[$releaseId, $target, _resolveTargetModId($target), REL_REQUIRED, $minVersion ?: null, REL_ORIGIN_AUTO, $user['userId'] ?? 0]
			);
		}
	}

	foreach ($existingByTarget as $stale) {
		$con->execute("DELETE FROM modRelations WHERE relationId = ?", [$stale['relationId']]);
	}

	$con->completeTrans();
}

/**
 * Copy manual relations from the previous release of the same identifier into a freshly-created release.
 * Acts as a per-release template so authors don't have to re-declare optional / incompatible / tested-with
 * relations on every upload (auto-detected 'required' rows are populated separately by syncAutoRelationsForRelease
 * from the new release's own rawDependencies).
 *
 * No-op when the new release has no identifier, no prior release exists, or the new release already has
 * manual relations (prevents accidental overwrite if called twice).
 */
function cloneManualRelationsFromPreviousRelease(int $newReleaseId): int
{
	global $con, $user;

	$newRelease = $con->getRow(
		"SELECT releaseId, modId, identifier FROM modReleases WHERE releaseId = ?",
		[$newReleaseId]
	);
	if (!$newRelease || !$newRelease['identifier']) return 0;

	$alreadyHasManual = $con->getOne(
		"SELECT 1 FROM modRelations WHERE releaseId = ? AND origin = ? LIMIT 1",
		[$newReleaseId, REL_ORIGIN_MANUAL]
	);
	if ($alreadyHasManual) return 0;

	$previousReleaseId = $con->getOne(
		"SELECT releaseId FROM modReleases
		 WHERE modId = ? AND identifier = ? AND releaseId != ?
		 ORDER BY releaseId DESC LIMIT 1",
		[$newRelease['modId'], $newRelease['identifier'], $newReleaseId]
	);
	if (!$previousReleaseId) return 0;

	$previousManual = $con->getAll(
		"SELECT targetIdentifier, targetModId, relationType, minVersion, maxVersion
		 FROM modRelations WHERE releaseId = ? AND origin = ?",
		[$previousReleaseId, REL_ORIGIN_MANUAL]
	);
	if (!$previousManual) return 0;

	$copied = 0;
	$con->startTrans();
	foreach ($previousManual as $row) {
		$con->execute(
			"INSERT INTO modRelations (releaseId, targetIdentifier, targetModId, relationType, minVersion, maxVersion, origin, createdByUserId)
			 VALUES (?,?,?,?,?,?,?,?)",
			[$newReleaseId, $row['targetIdentifier'], $row['targetModId'], $row['relationType'], $row['minVersion'], $row['maxVersion'], REL_ORIGIN_MANUAL, $user['userId'] ?? 0]
		);
		$copied++;
	}
	$con->completeTrans();
	return $copied;
}

/**
 * Updates all rows where targetIdentifier == $newlyPublishedIdentifier and targetModId IS NULL,
 * setting their targetModId to $newModId. Returns the number of rows updated. Called when a release
 * publishes an identifier so previously-orphan relations get retro-linked.
 */
function resolveDanglingTargets(string $newlyPublishedIdentifier, int $newModId): int
{
	global $con;
	$con->execute(
		"UPDATE modRelations SET targetModId = ? WHERE targetIdentifier = ? AND targetModId IS NULL",
		[$newModId, $newlyPublishedIdentifier]
	);
	return (int)$con->Affected_Rows();
}

/**
 * Returns the release that should be installed for this identifier, given an optional target game version.
 * Light wrapper used during transitive resolution. Returns null if no compatible release exists.
 *
 * @return ?array{releaseId:int, identifier:string, version:int, fileName:string, fileUrl:string}
 */
function pickReleaseForIdentifier(string $identifier, ?int $gameVersion): ?array
{
	global $con;

	if ($gameVersion) {
		$row = $con->getRow(
			"SELECT r.releaseId, r.identifier, r.version, f.fileId, f.name, f.cdnPath
			 FROM modReleases r
			 JOIN modReleaseCompatibleGameVersions cgv ON cgv.releaseId = r.releaseId AND cgv.gameVersion = ?
			 LEFT JOIN files f ON f.assetId = r.assetId
			 LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
			 WHERE r.identifier = ? AND rr.reason IS NULL
			 ORDER BY r.version DESC, f.`order` ASC, f.fileId ASC LIMIT 1",
			[$gameVersion, $identifier]
		);
	} else {
		$row = $con->getRow(
			"SELECT r.releaseId, r.identifier, r.version, f.fileId, f.name, f.cdnPath
			 FROM modReleases r
			 LEFT JOIN files f ON f.assetId = r.assetId
			 LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
			 WHERE r.identifier = ? AND rr.reason IS NULL
			 ORDER BY r.version DESC, f.`order` ASC, f.fileId ASC LIMIT 1",
			[$identifier]
		);
	}

	if (!$row || !$row['fileId']) return null;
	return [
		'releaseId'  => (int)$row['releaseId'],
		'identifier' => $row['identifier'],
		'version'    => (int)$row['version'],
		'fileName'   => $row['name'],
		'fileUrl'    => formatDownloadTrackingUrl($row),
	];
}

/**
 * Public DB-backed transitive resolver. Wires bfsResolve to real DB-backed loaders. The relations
 * loader is release-aware: it picks the release for each identifier first, then loads relations
 * of that specific release.
 *
 * $rootReleaseMap optionally pre-seeds the resolver with caller-chosen releases for the root
 * identifiers (e.g. the install-information API uses this to honor the @version specified in the
 * URL instead of falling back to the latest version of that identifier). Each entry must have the
 * same shape as pickReleaseForIdentifier returns: {releaseId, identifier, version, fileName, fileUrl}.
 * Transitive deps still fall through to pickReleaseForIdentifier (constrained by $gameVersion if given).
 *
 * @param string[] $rootIdentifiers
 * @param array<string, ?array{releaseId:int,identifier:string,version:int,fileName:string,fileUrl:string}> $rootReleaseMap
 */
function resolveTransitiveDeps(array $rootIdentifiers, ?int $gameVersion, array $rootReleaseMap = []): array
{
	$pickedReleases = $rootReleaseMap;

	$releasePicker = function (string $identifier) use ($gameVersion, &$pickedReleases) {
		if (array_key_exists($identifier, $pickedReleases)) return $pickedReleases[$identifier];
		$rel = pickReleaseForIdentifier($identifier, $gameVersion);
		$pickedReleases[$identifier] = $rel;
		return $rel;
	};

	$relationsLoader = function (string $identifier) use (&$pickedReleases) {
		$picked = $pickedReleases[$identifier] ?? null;
		if (!$picked || !isset($picked['releaseId'])) return [];
		// Resolver doesn't need resolvedMod hydration - skip it for cheaper BFS nodes.
		return _loadDedupedRelationsForRelease((int)$picked['releaseId']);
	};

	return bfsResolve($rootIdentifiers, $relationsLoader, $releasePicker);
}

/**
 * Persist relation form data submitted from edit-release.tpl. Each row's key is either an existing
 * relationId (integer) for updates, or a "new-N" pseudo-id for inserts. All operations are scoped
 * to the given release and origin=manual; auto rows are not touched here.
 *
 * Form row shape: ['type'=>string, 'target'=>string, 'minVersion'=>string, 'maxVersion'=>string]
 *
 * @param array<string|int, array{type:string,target:string,minVersion:string,maxVersion:string}> $formRows
 */
function persistManualRelationsFromForm(int $releaseId, array $formRows): void
{
	global $con;

	$allowedTypes = [REL_REQUIRED, REL_OPTIONAL, REL_INCOMPATIBLE, REL_TESTED_WITH];

	$con->startTrans();
	foreach ($formRows as $key => $row) {
		$type   = $row['type'] ?? '';
		$target = trim($row['target'] ?? '');
		if ($target === '' || !in_array($type, $allowedTypes, true)) continue;

		$min = ($row['minVersion'] ?? '') !== '' ? (compileSemanticVersion($row['minVersion']) ?: null) : null;
		$max = ($row['maxVersion'] ?? '') !== '' ? (compileSemanticVersion($row['maxVersion']) ?: null) : null;

		if (is_numeric($key)) {
			// Update existing row (must belong to this release and be manual).
			$existing = $con->getRow(
				"SELECT * FROM modRelations WHERE relationId = ? AND releaseId = ? AND origin = ?",
				[intval($key), $releaseId, REL_ORIGIN_MANUAL]
			);
			if (!$existing) continue;
			// If type or target changed, fall back to delete+insert to respect the unique index.
			if ($existing['relationType'] !== $type || $existing['targetIdentifier'] !== $target) {
				deleteManualRelation((int)$existing['relationId']);
				upsertManualRelation($releaseId, $target, $type, $min, $max);
			} else {
				$con->execute(
					"UPDATE modRelations SET minVersion = ?, maxVersion = ?, targetModId = ? WHERE relationId = ?",
					[$min, $max, _resolveTargetModId($target), $existing['relationId']]
				);
			}
		} else {
			upsertManualRelation($releaseId, $target, $type, $min, $max);
		}
	}
	$con->completeTrans();
}
