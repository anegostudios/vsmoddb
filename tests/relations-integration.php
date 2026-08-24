<?php

include_once "prelude.php";
include_once $config['basepath'].'lib/version.php';
include_once $config['basepath'].'lib/relations.php';

use PHPUnit\Framework\TestCase;

/** @return array{userId:int,modA:int,modB:int,modC:int,releaseA:int,releaseB:int,releaseC:int} */
function relationsTestFixture(): array
{
	global $con;

	$userId = (int)$con->getOne("SELECT userId FROM users LIMIT 1");

	$result = ['userId' => $userId];
	foreach (['A','B','C'] as $suffix) {
		$alias = 'rel-test-'.$suffix;

		$existingMod = $con->getOne("SELECT modId FROM mods WHERE urlAlias = ?", [$alias]);
		if ($existingMod) {
			$modId = (int)$existingMod;
			$relAssetIds = $con->getCol("SELECT assetId FROM modReleases WHERE modId = ?", [$modId]);
			foreach ($relAssetIds as $relAssetId) {
				if (!$con->getOne("SELECT fileId FROM files WHERE assetId = ?", [$relAssetId])) {
					$con->execute("INSERT INTO files (assetId, assetTypeId, userId, name, cdnPath, size, `order`) VALUES (?,?,?,?,?,?,?)",
						[$relAssetId, ASSETTYPE_MOD, $userId, $alias.'.zip', '/cdn/'.$alias.'.zip', 1024, 0]);
				}
			}
		} else {
			$con->execute("INSERT INTO assets (createdByUserId, statusId, assetTypeId, name, text) VALUES (?,?,?,?,?)",
				[$userId, STATUS_RELEASED, ASSETTYPE_MOD, 'Rel Test '.$alias, '']);
			$assetId = (int)$con->Insert_ID();
			$con->execute("INSERT INTO mods (assetId, urlAlias, summary, descriptionSearchable, side, category, lastReleased) VALUES (?,?,?,?,?,?,NOW())",
				[$assetId, $alias, $alias, $alias, 'both', CATEGORY_GAME_MOD]);
			$modId = (int)$con->Insert_ID();

			$con->execute("INSERT INTO assets (createdByUserId, statusId, assetTypeId, name) VALUES (?,?,?,?)",
				[$userId, STATUS_RELEASED, ASSETTYPE_MOD, $alias.'-r1']);
			$relAssetId = (int)$con->Insert_ID();
			$con->execute("INSERT INTO modReleases (assetId, modId, identifier, version) VALUES (?,?,?,?)",
				[$relAssetId, $modId, $alias, compileSemanticVersion('1.0.0')]);
			$con->execute("INSERT INTO files (assetId, assetTypeId, userId, name, cdnPath, size, `order`) VALUES (?,?,?,?,?,?,?)",
				[$relAssetId, ASSETTYPE_MOD, $userId, $alias.'.zip', '/cdn/'.$alias.'.zip', 1024, 0]);
		}

		$result['mod'.$suffix]     = $modId;
		$result['release'.$suffix] = (int)$con->getOne("SELECT releaseId FROM modReleases WHERE modId = ? ORDER BY releaseId ASC LIMIT 1", [$modId]);
	}

	return $result;
}

final class RelationsIntegrationTest extends TestCase
{
	private static $fx;

	public static function setUpBeforeClass(): void
	{
		self::$fx = relationsTestFixture();
	}

	protected function setUp(): void
	{
		global $con;
		$releaseIds = [self::$fx['releaseA'], self::$fx['releaseB'], self::$fx['releaseC']];
		$in = implode(',', array_map('intval', $releaseIds));
		$con->execute("DELETE FROM modRelations WHERE releaseId IN ($in)");
	}

	public function testTableExistsWithExpectedColumns(): void
	{
		global $con;
		$cols = $con->getCol("SHOW COLUMNS FROM modRelations");
		$expected = [
			'relationId','releaseId','targetIdentifier','targetModId',
			'relationType','minVersion','maxVersion','origin','createdByUserId','created','lastModified',
		];
		sort($cols); sort($expected);
		$this->assertEquals($expected, $cols);
	}

	public function testUniqueIndexPreventsDuplicates(): void
	{
		global $con;
		$userId    = self::$fx['userId'];
		$releaseId = self::$fx['releaseA'];

		$con->execute("DELETE FROM modRelations WHERE targetIdentifier = 'pluq-test-target'");
		$threw = false;
		try {
			$con->execute(
				"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, origin, createdByUserId) VALUES (?,?,?,?,?)",
				[$releaseId, 'pluq-test-target', REL_REQUIRED, REL_ORIGIN_MANUAL, $userId]
			);
			try {
				$con->execute(
					"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, origin, createdByUserId) VALUES (?,?,?,?,?)",
					[$releaseId, 'pluq-test-target', REL_REQUIRED, REL_ORIGIN_MANUAL, $userId]
				);
			} catch (\Exception $e) {
				$threw = true;
			}
		} finally {
			$con->execute("DELETE FROM modRelations WHERE targetIdentifier = 'pluq-test-target'");
		}
		$this->assertTrue($threw, 'Expected unique index violation on second insert');
	}

	public function testUpsertManualInsertsRow(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		$id = upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, compileSemanticVersion('1.0.0'), null);
		$row = $con->getRow("SELECT * FROM modRelations WHERE relationId = ?", [$id]);
		$this->assertEquals('rel-test-B', $row['targetIdentifier']);
		$this->assertEquals(REL_ORIGIN_MANUAL, $row['origin']);
		$this->assertEquals(self::$fx['releaseA'], (int)$row['releaseId']);
		$this->assertEquals((int)self::$fx['modB'], (int)$row['targetModId']); // auto-resolved
	}

	public function testUpsertManualConvertsAutoToManual(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		$con->execute(
			"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, minVersion, origin, createdByUserId) VALUES (?,?,?,?,?,?)",
			[self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, compileSemanticVersion('1.0.0'), REL_ORIGIN_AUTO, self::$fx['userId']]
		);
		upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, compileSemanticVersion('2.0.0'), null);
		$row = $con->getRow(
			"SELECT * FROM modRelations WHERE releaseId = ? AND targetIdentifier = ? AND relationType = ?",
			[self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED]
		);
		$this->assertEquals(REL_ORIGIN_MANUAL, $row['origin']);
		$this->assertEquals((string)compileSemanticVersion('2.0.0'), (string)$row['minVersion']);
	}

	public function testDeleteManualRemovesRow(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		$id = upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_OPTIONAL, null, null);
		deleteManualRelation($id);
		$this->assertEmpty($con->getRow("SELECT * FROM modRelations WHERE relationId = ?", [$id]));
	}

	public function testDeleteManualRefusesToTouchAutoRows(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		$con->execute(
			"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, origin, createdByUserId) VALUES (?,?,?,?,?)",
			[self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, REL_ORIGIN_AUTO, self::$fx['userId']]
		);
		$id = (int)$con->Insert_ID();
		deleteManualRelation($id);
		$this->assertNotEmpty($con->getRow("SELECT * FROM modRelations WHERE relationId = ?", [$id]));
	}

	public function testGetRelationsForReleaseResolvesTargetMod(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_OPTIONAL, null, null);
		$rels = getRelationsForRelease(self::$fx['releaseA']);
		$this->assertCount(1, $rels);
		$this->assertEquals('Rel Test rel-test-B', $rels[0]['resolvedMod']['name']);
	}

	public function testGetRelationsForReleaseMergesAutoAndManualWithManualWinning(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		$con->execute(
			"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, minVersion, origin, createdByUserId) VALUES (?,?,?,?,?,?)",
			[self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, compileSemanticVersion('1.0.0'), REL_ORIGIN_AUTO, self::$fx['userId']]
		);
		upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, compileSemanticVersion('3.0.0'), null);

		$rels = getRelationsForRelease(self::$fx['releaseA']);
		$required = array_filter($rels, fn($r) => $r['targetIdentifier'] === 'rel-test-B' && $r['relationType'] === REL_REQUIRED);
		$this->assertCount(1, $required);
		$first = array_values($required)[0];
		$this->assertEquals(REL_ORIGIN_MANUAL, $first['origin']);
		$this->assertEquals((string)compileSemanticVersion('3.0.0'), (string)$first['minVersion']);
	}

	public function testGetRelationsForLatestReleaseOfModUsesLatestRelease(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];

		// Drop any leftover v2.0.0 from a previous crashed run before recreating it.
		$staleReleaseId = $con->getOne("SELECT releaseId FROM modReleases WHERE modId = ? AND identifier = ? AND version = ?",
			[self::$fx['modA'], 'rel-test-A', compileSemanticVersion('2.0.0')]);
		if ($staleReleaseId) {
			$staleAssetId = $con->getOne("SELECT assetId FROM modReleases WHERE releaseId = ?", [$staleReleaseId]);
			$con->execute("DELETE FROM modReleases WHERE releaseId = ?", [$staleReleaseId]);
			$con->execute("DELETE FROM files WHERE assetId = ?", [$staleAssetId]);
			$con->execute("DELETE FROM assets WHERE assetId = ?", [$staleAssetId]);
		}

		$con->execute("INSERT INTO assets (createdByUserId, statusId, assetTypeId, name) VALUES (?,?,?,?)",
			[self::$fx['userId'], STATUS_RELEASED, ASSETTYPE_MOD, 'rel-test-A-r2']);
		$assetId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO modReleases (assetId, modId, identifier, version) VALUES (?,?,?,?)",
			[$assetId, self::$fx['modA'], 'rel-test-A', compileSemanticVersion('2.0.0')]);
		$newReleaseId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO files (assetId, assetTypeId, userId, name, cdnPath, size, `order`) VALUES (?,?,?,?,?,?,?)",
			[$assetId, ASSETTYPE_MOD, self::$fx['userId'], 'rel-test-A-2.0.0.zip', '/cdn/rel-test-A-2.zip', 1024, 0]);

		// Auto row only on the older release.
		$con->execute(
			"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, origin, createdByUserId) VALUES (?,?,?,?,?)",
			[self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, REL_ORIGIN_AUTO, self::$fx['userId']]
		);
		// Manual row on the newer release.
		upsertManualRelation($newReleaseId, 'rel-test-C', REL_OPTIONAL, null, null);

		$rels = getRelationsForLatestReleaseOfMod(self::$fx['modA']);
		$targets = array_column($rels, 'targetIdentifier');
		$this->assertContains('rel-test-C', $targets);
		$this->assertNotContains('rel-test-B', $targets, 'relations from older releases should not surface on latest-release view');

		$con->execute("DELETE FROM modReleases WHERE releaseId = ?", [$newReleaseId]);
		$con->execute("DELETE FROM files WHERE assetId = ?", [$assetId]);
		$con->execute("DELETE FROM assets WHERE assetId = ?", [$assetId]);
	}

	public function testEditViewSplitsAutoAndManualForSameRelease(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		$con->execute(
			"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, origin, createdByUserId) VALUES (?,?,?,?,?)",
			[self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, REL_ORIGIN_AUTO, self::$fx['userId']]
		);
		upsertManualRelation(self::$fx['releaseA'], 'rel-test-C', REL_OPTIONAL, null, null);

		$view = getRelationsForReleaseEditView(self::$fx['releaseA']);
		$this->assertCount(1, $view['auto']);
		$this->assertCount(1, $view['manual']);
		$this->assertEquals('rel-test-B', $view['auto'][0]['targetIdentifier']);
		$this->assertEquals('rel-test-C', $view['manual'][0]['targetIdentifier']);
	}

	public function testEditViewKeepsDistinctRelationTypesForSameTarget(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		$con->execute(
			"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, origin, createdByUserId) VALUES (?,?,?,?,?)",
			[self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, REL_ORIGIN_AUTO, self::$fx['userId']]
		);
		$con->execute(
			"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, origin, createdByUserId) VALUES (?,?,?,?,?)",
			[self::$fx['releaseA'], 'rel-test-B', REL_TESTED_WITH, REL_ORIGIN_AUTO, self::$fx['userId']]
		);
		$view = getRelationsForReleaseEditView(self::$fx['releaseA']);
		$autoTypes = array_column(array_filter($view['auto'], fn($r) => $r['targetIdentifier'] === 'rel-test-B'), 'relationType');
		sort($autoTypes);
		$this->assertEquals([REL_REQUIRED, REL_TESTED_WITH], $autoTypes,
			'EditView must keep distinct relation types for the same target');
	}

	public function testCascadeOnReleaseDelete(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];

		$con->execute("INSERT INTO assets (createdByUserId, statusId, assetTypeId, name, text) VALUES (?,?,?,?,?)",
			[self::$fx['userId'], STATUS_RELEASED, ASSETTYPE_MOD, 'rel-test-temp', '']);
		$assetId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO mods (assetId, urlAlias, summary, descriptionSearchable, side, category, lastReleased) VALUES (?,?,?,?,?,?,NOW())",
			[$assetId, 'rel-test-temp', 'tmp', 'tmp', 'both', CATEGORY_GAME_MOD]);
		$tempModId = (int)$con->Insert_ID();

		$con->execute("INSERT INTO assets (createdByUserId, statusId, assetTypeId, name) VALUES (?,?,?,?)",
			[self::$fx['userId'], STATUS_RELEASED, ASSETTYPE_MOD, 'rel-test-temp-r1']);
		$relAssetId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO modReleases (assetId, modId, identifier, version) VALUES (?,?,?,?)",
			[$relAssetId, $tempModId, 'rel-test-temp', compileSemanticVersion('1.0.0')]);
		$tempReleaseId = (int)$con->Insert_ID();

		upsertManualRelation($tempReleaseId, 'rel-test-A', REL_REQUIRED, null, null);
		$con->execute("DELETE FROM modReleases WHERE releaseId = ?", [$tempReleaseId]);

		$count = (int)$con->getOne("SELECT COUNT(*) FROM modRelations WHERE releaseId = ?", [$tempReleaseId]);
		$this->assertEquals(0, $count, 'modRelations must cascade-delete with their release');

		$con->execute("DELETE FROM mods WHERE modId = ?", [$tempModId]);
	}

	public function testSyncAutoInsertsNewRelations(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		syncAutoRelationsForRelease(self::$fx['releaseA'], 'rel-test-B@1.0.0, rel-test-C@2.0.0');
		$rows = $con->getAll("SELECT * FROM modRelations WHERE releaseId = ? AND origin = ?",
			[self::$fx['releaseA'], REL_ORIGIN_AUTO]);
		$this->assertCount(2, $rows);
	}

	public function testSyncAutoIsIdempotent(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		syncAutoRelationsForRelease(self::$fx['releaseA'], 'rel-test-B@1.0.0');
		syncAutoRelationsForRelease(self::$fx['releaseA'], 'rel-test-B@1.0.0');
		$count = (int)$con->getOne("SELECT COUNT(*) FROM modRelations WHERE releaseId = ?", [self::$fx['releaseA']]);
		$this->assertEquals(1, $count);
	}

	public function testSyncAutoDiffsCorrectly(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		syncAutoRelationsForRelease(self::$fx['releaseA'], 'rel-test-B@1.0.0, rel-test-C@2.0.0');
		syncAutoRelationsForRelease(self::$fx['releaseA'], 'rel-test-B@2.0.0');
		$rows = $con->getAll(
			"SELECT targetIdentifier, minVersion FROM modRelations WHERE releaseId = ? ORDER BY targetIdentifier",
			[self::$fx['releaseA']]
		);
		$this->assertCount(1, $rows);
		$this->assertEquals('rel-test-B', $rows[0]['targetIdentifier']);
		$this->assertEquals((string)compileSemanticVersion('2.0.0'), (string)$rows[0]['minVersion']);
	}

	public function testSyncAutoDoesNotTouchManualOnSameRelease(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_OPTIONAL, compileSemanticVersion('5.0.0'), null);
		syncAutoRelationsForRelease(self::$fx['releaseA'], 'rel-test-B@1.0.0');
		$manual = $con->getRow(
			"SELECT minVersion, relationType FROM modRelations WHERE releaseId = ? AND targetIdentifier = ? AND origin = ?",
			[self::$fx['releaseA'], 'rel-test-B', REL_ORIGIN_MANUAL]
		);
		$this->assertNotNull($manual);
		$this->assertEquals(REL_OPTIONAL, $manual['relationType']);
		$this->assertEquals((string)compileSemanticVersion('5.0.0'), (string)$manual['minVersion']);
	}

	public function testCloneManualRelationsFromPreviousReleaseCopiesManualOnly(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];

		upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_TESTED_WITH, null, null);
		$con->execute(
			"INSERT INTO modRelations (releaseId, targetIdentifier, relationType, origin, createdByUserId) VALUES (?,?,?,?,?)",
			[self::$fx['releaseA'], 'rel-test-C', REL_REQUIRED, REL_ORIGIN_AUTO, self::$fx['userId']]
		);

		// Drop any leftover v2.0.0 from a previous crashed run before recreating it.
		$staleReleaseId = $con->getOne("SELECT releaseId FROM modReleases WHERE modId = ? AND identifier = ? AND version = ?",
			[self::$fx['modA'], 'rel-test-A', compileSemanticVersion('2.0.0')]);
		if ($staleReleaseId) {
			$staleAssetId = $con->getOne("SELECT assetId FROM modReleases WHERE releaseId = ?", [$staleReleaseId]);
			$con->execute("DELETE FROM modReleases WHERE releaseId = ?", [$staleReleaseId]);
			$con->execute("DELETE FROM files WHERE assetId = ?", [$staleAssetId]);
			$con->execute("DELETE FROM assets WHERE assetId = ?", [$staleAssetId]);
		}

		$con->execute("INSERT INTO assets (createdByUserId, statusId, assetTypeId, name) VALUES (?,?,?,?)",
			[self::$fx['userId'], STATUS_RELEASED, ASSETTYPE_MOD, 'rel-test-A-r2']);
		$newAssetId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO modReleases (assetId, modId, identifier, version) VALUES (?,?,?,?)",
			[$newAssetId, self::$fx['modA'], 'rel-test-A', compileSemanticVersion('2.0.0')]);
		$newReleaseId = (int)$con->Insert_ID();

		$copied = cloneManualRelationsFromPreviousRelease($newReleaseId);
		$this->assertEquals(1, $copied);

		$rows = $con->getAll(
			"SELECT targetIdentifier, relationType, origin FROM modRelations WHERE releaseId = ?",
			[$newReleaseId]
		);
		$this->assertCount(1, $rows, 'only the manual row should be cloned, never auto');
		$this->assertEquals('rel-test-B', $rows[0]['targetIdentifier']);
		$this->assertEquals(REL_TESTED_WITH, $rows[0]['relationType']);
		$this->assertEquals(REL_ORIGIN_MANUAL, $rows[0]['origin']);

		$con->execute("DELETE FROM modReleases WHERE releaseId = ?", [$newReleaseId]);
		$con->execute("DELETE FROM assets WHERE assetId = ?", [$newAssetId]);
	}

	public function testResolveDanglingTargetsLinksLaterPublishedMod(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		upsertManualRelation(self::$fx['releaseA'], 'rel-test-newcomer', REL_REQUIRED, null, null);
		$row = $con->getRow("SELECT * FROM modRelations WHERE releaseId = ? AND targetIdentifier = ?",
			[self::$fx['releaseA'], 'rel-test-newcomer']);
		$this->assertNull($row['targetModId']);

		$con->execute("INSERT INTO assets (createdByUserId, statusId, assetTypeId, name, text) VALUES (?,?,?,?,?)",
			[self::$fx['userId'], STATUS_RELEASED, ASSETTYPE_MOD, 'Newcomer', '']);
		$assetId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO mods (assetId, urlAlias, summary, descriptionSearchable, side, category, lastReleased) VALUES (?,?,?,?,?,?,NOW())",
			[$assetId, 'rel-test-newcomer', 'n', 'n', 'both', CATEGORY_GAME_MOD]);
		$newModId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO assets (createdByUserId, statusId, assetTypeId, name) VALUES (?,?,?,?)",
			[self::$fx['userId'], STATUS_RELEASED, ASSETTYPE_MOD, 'newcomer-r1']);
		$relAssetId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO modReleases (assetId, modId, identifier, version) VALUES (?,?,?,?)",
			[$relAssetId, $newModId, 'rel-test-newcomer', compileSemanticVersion('1.0.0')]);

		$updated = resolveDanglingTargets('rel-test-newcomer', $newModId);
		$this->assertGreaterThanOrEqual(1, $updated);

		$row = $con->getRow("SELECT * FROM modRelations WHERE releaseId = ? AND targetIdentifier = ?",
			[self::$fx['releaseA'], 'rel-test-newcomer']);
		$this->assertEquals($newModId, (int)$row['targetModId']);

		$con->execute("DELETE FROM modRelations WHERE targetIdentifier = ?", ['rel-test-newcomer']);
		$con->execute("DELETE FROM mods WHERE modId = ?", [$newModId]);
	}

	public function testPickReleaseForKnownIdentifierReturnsRelease(): void
	{
		$out = pickReleaseForIdentifier('rel-test-A', null);
		$this->assertNotNull($out);
		$this->assertArrayHasKey('releaseId', $out);
		$this->assertArrayHasKey('fileName', $out);
		$this->assertArrayHasKey('fileUrl', $out);
	}

	public function testPickReleaseForUnknownIdentifierReturnsNull(): void
	{
		$this->assertNull(pickReleaseForIdentifier('this-identifier-does-not-exist-anywhere', null));
	}

	public function testResolveTransitiveDepsResolvesChain(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, null, null);
		upsertManualRelation(self::$fx['releaseB'], 'rel-test-C', REL_REQUIRED, null, null);

		$out = resolveTransitiveDeps(['rel-test-A'], null);
		$this->assertArrayHasKey('rel-test-B', $out['resolved']);
		$this->assertArrayHasKey('rel-test-C', $out['resolved']);
		$this->assertEquals([], $out['warnings']);
	}

	public function testResolveTransitiveDepsRespectsExplicitRootRelease(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];

		// Drop any leftover v2.0.0 from a previous crashed run before recreating it.
		$staleReleaseId = $con->getOne("SELECT releaseId FROM modReleases WHERE modId = ? AND identifier = ? AND version = ?",
			[self::$fx['modA'], 'rel-test-A', compileSemanticVersion('2.0.0')]);
		if ($staleReleaseId) {
			$staleAssetId = $con->getOne("SELECT assetId FROM modReleases WHERE releaseId = ?", [$staleReleaseId]);
			$con->execute("DELETE FROM modReleases WHERE releaseId = ?", [$staleReleaseId]);
			$con->execute("DELETE FROM files WHERE assetId = ?", [$staleAssetId]);
			$con->execute("DELETE FROM assets WHERE assetId = ?", [$staleAssetId]);
		}

		$con->execute("INSERT INTO assets (createdByUserId, statusId, assetTypeId, name) VALUES (?,?,?,?)",
			[self::$fx['userId'], STATUS_RELEASED, ASSETTYPE_MOD, 'rel-test-A-r2-explicit']);
		$newAssetId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO modReleases (assetId, modId, identifier, version) VALUES (?,?,?,?)",
			[$newAssetId, self::$fx['modA'], 'rel-test-A', compileSemanticVersion('2.0.0')]);
		$newReleaseId = (int)$con->Insert_ID();
		$con->execute("INSERT INTO files (assetId, assetTypeId, userId, name, cdnPath, size, `order`) VALUES (?,?,?,?,?,?,?)",
			[$newAssetId, ASSETTYPE_MOD, self::$fx['userId'], 'rel-test-A-2.0.0.zip', '/cdn/rel-test-A-2.zip', 1024, 0]);

		// v1 (releaseA) requires B; v2 (newReleaseId) requires C. Without the rootReleaseMap, the resolver
		// picks the latest (v2) and would see C only. With the explicit map pointing at v1, it must see B.
		upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, null, null);
		upsertManualRelation($newReleaseId,         'rel-test-C', REL_REQUIRED, null, null);

		$rootMap = [
			'rel-test-A' => [
				'releaseId'  => self::$fx['releaseA'],
				'identifier' => 'rel-test-A',
				'version'    => compileSemanticVersion('1.0.0'),
				'fileName'   => 'rel-test-A.zip',
				'fileUrl'    => '/cdn/rel-test-A.zip',
			],
		];
		$out = resolveTransitiveDeps(['rel-test-A'], null, $rootMap);
		$this->assertArrayHasKey('rel-test-B', $out['resolved'], 'rootReleaseMap must pin the resolver to v1, which requires B');
		$this->assertArrayNotHasKey('rel-test-C', $out['resolved'], 'v2-only dep C must not surface when explicit root is v1');

		$con->execute("DELETE FROM modReleases WHERE releaseId = ?", [$newReleaseId]);
		$con->execute("DELETE FROM files WHERE assetId = ?", [$newAssetId]);
		$con->execute("DELETE FROM assets WHERE assetId = ?", [$newAssetId]);
	}

	public function testResolveTransitiveDepsReportsCycle(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_REQUIRED, null, null);
		upsertManualRelation(self::$fx['releaseB'], 'rel-test-A', REL_REQUIRED, null, null);

		$out = resolveTransitiveDeps(['rel-test-A'], null);
		$kinds = array_column($out['warnings'], 'kind');
		$this->assertContains('cycle', $kinds);
	}

	public function testPickReleaseForIdentifierWithGameVersion(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		$gv = compileSemanticVersion('1.20.0');
		$con->execute(
			"INSERT IGNORE INTO modReleaseCompatibleGameVersions (releaseId, gameVersion) VALUES (?,?)",
			[self::$fx['releaseA'], $gv]
		);
		$out = pickReleaseForIdentifier('rel-test-A', $gv);
		$this->assertNotNull($out, 'gv-matching release should be picked');

		$gvOther = compileSemanticVersion('99.0.0');
		$outNone = pickReleaseForIdentifier('rel-test-A', $gvOther);
		$this->assertNull($outNone, 'no release matches a non-existent gv');

		$con->execute("DELETE FROM modReleaseCompatibleGameVersions WHERE releaseId = ? AND gameVersion = ?", [self::$fx['releaseA'], $gv]);
	}

	public function testPersistManualRelationsFromFormHandlesInsertUpdateRemove(): void
	{
		global $con, $user;
		$user = ['userId' => self::$fx['userId']];
		$existingId = upsertManualRelation(self::$fx['releaseA'], 'rel-test-B', REL_OPTIONAL, null, null);

		persistManualRelationsFromForm(self::$fx['releaseA'], [
			$existingId => [
				'type' => REL_INCOMPATIBLE,
				'target' => 'rel-test-B',
				'minVersion' => '',
				'maxVersion' => '',
			],
			'new-1' => [
				'type' => REL_TESTED_WITH,
				'target' => 'rel-test-C',
				'minVersion' => '1.0.0',
				'maxVersion' => '',
			],
		]);

		$rows = $con->getAll(
			"SELECT * FROM modRelations WHERE releaseId = ? ORDER BY targetIdentifier",
			[self::$fx['releaseA']]
		);
		$this->assertCount(2, $rows);
		$byTarget = [];
		foreach ($rows as $r) $byTarget[$r['targetIdentifier']] = $r;
		$this->assertEquals(REL_INCOMPATIBLE, $byTarget['rel-test-B']['relationType']);
		$this->assertEquals(REL_TESTED_WITH,  $byTarget['rel-test-C']['relationType']);
		$this->assertEquals((string)compileSemanticVersion('1.0.0'), (string)$byTarget['rel-test-C']['minVersion']);
	}
}
