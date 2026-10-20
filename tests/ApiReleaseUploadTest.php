<?php

require_once __DIR__.'/support/api-token-support.php';

/** POST /api/v2/mods/{modid}/releases: successful uploads and what they leave in the database. */
final class ApiReleaseUploadTest extends ApiTokenTestCase
{
	private $owner;
	private $mod;
	private $token;

	protected function setUp() : void
	{
		parent::setUp();
		$this->owner = Fx::user();
		$this->mod = Fx::mod($this->owner['userId']);
		$this->token = Fx::token($this->mod, $this->owner['userId']);
	}

	/** @test */
	public function uploadAnswers201WithLocationAndTheSameBodyAsThePublicReleaseEndpoint() : void
	{
		$identifier = Fx::identifier();
		$fileName = "$identifier-1.2.3.zip";

		$response = $this->upload($this->mod['modId'], $this->token['token'], [
			'fileContent' => StoredZip::mod($identifier, '1.2.3'), 'fileName' => $fileName, 'gameversions' => ['1.17.4', '1.18.1'],
		]);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertSame('application/json', Http::header($response, 'Content-Type'));
		$releaseId = $this->countRows('SELECT releaseId FROM modReleases WHERE modId = ?', [$this->mod['modId']]);
		$this->assertGreaterThan(0, $releaseId);
		$this->assertSame("/api/v2/mods/{$this->mod['modId']}/releases/$releaseId", Http::header($response, 'Location'));

		$fileId = $this->countRows('SELECT f.fileId FROM files f JOIN modReleases r ON r.assetId = f.assetId WHERE r.releaseId = ?', [$releaseId]);
		$json = $response['json'];
		$this->assertSame(['releaseId', 'identifier', 'version', 'compatibleGameVersions', 'created', 'fileName', 'fileUrl'], array_keys($json));
		$this->assertSame($releaseId, $json['releaseId']);
		$this->assertSame($identifier, $json['identifier']);
		$this->assertSame('1.2.3', $json['version']);
		$this->assertSame(['1.18.1', '1.17.4'], $json['compatibleGameVersions']);
		$this->assertEqualsWithDelta(time(), $json['created'], 30);
		$this->assertSame($fileName, $json['fileName']);
		$this->assertSame("/download/$fileId/$fileName", $json['fileUrl']);

		$public = Http::request('GET', Http::header($response, 'Location'));
		$this->assertSame(200, $public['status'], $public['body']);
		$this->assertSame($public['body'], $response['body']);
	}

	/** @test */
	public function uploadCreatesReleaseAssetFileAndModpeekRowsAttributedToTheCreator() : void
	{
		$identifier = Fx::identifier();
		$fileName = "$identifier.zip";
		$content = StoredZip::mod($identifier, '2.0.0-rc.1');

		$response = $this->upload($this->mod['modId'], $this->token['token'], ['fileContent' => $content, 'fileName' => $fileName, 'gameversions' => ['1.18.1']]);
		$this->assertSame(201, $response['status'], $response['body']);
		$releaseId = $response['json']['releaseId'];

		$release = $this->db()->getRow('SELECT * FROM modReleases WHERE releaseId = ?', [$releaseId]);
		$this->assertSame($this->mod['modId'], intval($release['modId']));
		$this->assertSame($identifier, $release['identifier']);
		$this->assertSame(compileSemanticVersion('2.0.0-rc.1'), intval($release['version']));

		$asset = $this->db()->getRow('SELECT * FROM assets WHERE assetId = ?', [$release['assetId']]);
		$this->assertSame(ASSETTYPE_RELEASE, intval($asset['assetTypeId']));
		$this->assertSame(STATUS_RELEASED, intval($asset['statusId']));
		$this->assertSame($this->owner['userId'], intval($asset['createdByUserId']));
		$this->assertSame($this->owner['userId'], intval($asset['editedByUserId']));

		$files = $this->db()->getAll('SELECT * FROM files WHERE userId = ?', [$this->owner['userId']]);
		$this->assertCount(1, $files);
		$file = $files[0];
		$this->assertSame(intval($release['assetId']), intval($file['assetId']), 'The file is attached to the release, it never hovers.');
		$this->assertSame(ASSETTYPE_RELEASE, intval($file['assetTypeId']));
		$this->assertSame($fileName, $file['name']);
		$this->assertSame(strlen($content), intval($file['size']));
		$this->assertSame(0, intval($file['order']));
		$this->assertSame("{$this->owner['userId']}/$fileName", $file['cdnPath']);
		$this->assertSame($content, file_get_contents(Fx::storedFilePath($this->owner['userId'], $fileName)));

		$peek = $this->db()->getRow('SELECT * FROM modPeekResults WHERE fileId = ?', [$file['fileId']]);
		$this->assertNotEmpty($peek);
		$this->assertSame($identifier, $peek['modIdentifier']);
		$this->assertSame(compileSemanticVersion('2.0.0-rc.1'), intval($peek['modVersion']));
		$this->assertSame('Content', $peek['type']);
	}

	/** @test */
	public function uploadStoresCompatibleGameVersionsAndUpdatesTheModCaches() : void
	{
		$this->assertNull($this->db()->getOne('SELECT lastReleased FROM mods WHERE modId = ?', [$this->mod['modId']]));

		$response = $this->upload($this->mod['modId'], $this->token['token'], ['gameversions' => ['1.18.1', '1.17.4', '1.18.1']]);
		$this->assertSame(201, $response['status'], $response['body']);

		$cgvs = array_map('intval', $this->db()->getCol('SELECT gameVersion FROM modReleaseCompatibleGameVersions WHERE releaseId = ? ORDER BY gameVersion', [$response['json']['releaseId']]));
		$this->assertSame([compileSemanticVersion('1.17.4'), compileSemanticVersion('1.18.1')], $cgvs);

		$cached = array_map('intval', $this->db()->getCol('SELECT gameVersion FROM modCompatibleGameVersionsCached WHERE modId = ? ORDER BY gameVersion', [$this->mod['modId']]));
		$this->assertSame($cgvs, $cached);
		$cachedMajor = array_map('intval', $this->db()->getCol('SELECT majorGameVersion FROM modCompatibleMajorGameVersionsCached WHERE modId = ? ORDER BY majorGameVersion', [$this->mod['modId']]));
		$this->assertSame([compileSemanticVersion('1.17.0') & VERSION_MASK_PRIMARY, compileSemanticVersion('1.18.0') & VERSION_MASK_PRIMARY], $cachedMajor);

		$age = $this->db()->getOne('SELECT TIMESTAMPDIFF(SECOND, lastReleased, NOW()) FROM mods WHERE modId = ?', [$this->mod['modId']]);
		$this->assertNotNull($age, 'mods.lastReleased must be set');
		$this->assertLessThanOrEqual(30, abs(intval($age)));
	}

	/** @test */
	public function uploadIsAuditedAsMadeViaApiToken() : void
	{
		$fileName = Fx::unique('audit').'.zip';
		$response = $this->upload($this->mod['modId'], $this->token['token'], ['fileName' => $fileName]);
		$this->assertSame(201, $response['status'], $response['body']);

		$releaseLog = $this->db()->getAll('SELECT * FROM auditLogs WHERE kind = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_RELEASE_CREATE, $this->owner['userId']]);
		$this->assertCount(1, $releaseLog);
		$this->assertSame($response['json']['releaseId'], intval($releaseLog[0]['referenceId']));
		$this->assertSame(AUDIT_LOG_FLAG_VIA_API_TOKEN, intval($releaseLog[0]['flags']));
		$this->assertStringStartsWith('v1.0.0 for ', $releaseLog[0]['info']);

		$fileLog = $this->db()->getAll('SELECT * FROM auditLogs WHERE kind = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_FILE_CREATE, $this->owner['userId']]);
		$this->assertCount(1, $fileLog);
		$this->assertSame(0, intval($fileLog[0]['flags']));
		$this->assertSame($fileName, $fileLog[0]['info']);
		$this->assertSame(intval($this->db()->getOne('SELECT fileId FROM files WHERE userId = ?', [$this->owner['userId']])), intval($fileLog[0]['referenceId']));
	}

	/** @test */
	public function followersWithNotificationsEnabledAreNotified() : void
	{
		$follower = Fx::user();
		$silentFollower = Fx::user();
		$this->db()->execute('INSERT INTO userFollowedMods (modId, userId, flags) VALUES (?, ?, ?), (?, ?, ?)', [
			$this->mod['modId'], $follower['userId'], FOLLOW_FLAG_CREATE_NOTIFICATIONS,
			$this->mod['modId'], $silentFollower['userId'], 0,
		]);

		$response = $this->upload($this->mod['modId'], $this->token['token']);
		$this->assertSame(201, $response['status'], $response['body']);

		$notifications = $this->db()->getAll('SELECT kind, recordId, `read` FROM notifications WHERE userId = ?', [$follower['userId']]);
		$this->assertSame([['kind' => NOTIFICATION_NEW_RELEASE, 'recordId' => $this->mod['modId'], 'read' => 0]], array_map(fn($n) => array_map('intval', $n), $notifications));
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM notifications WHERE userId = ?', [$silentFollower['userId']]));
	}

	/** @test */
	public function changelogIsSanitized() : void
	{
		$response = $this->upload($this->mod['modId'], $this->token['token'], [
			'changelog' => '<p>Fixed <b>things</b></p><script>alert(1)</script><img src="x" onerror="alert(2)">',
		]);
		$this->assertSame(201, $response['status'], $response['body']);

		$text = $this->db()->getOne('SELECT a.text FROM modReleases r JOIN assets a ON a.assetId = r.assetId WHERE r.releaseId = ?', [$response['json']['releaseId']]);
		$this->assertStringContainsString('<p>Fixed <b>things</b></p>', $text);
		$this->assertStringNotContainsStringIgnoringCase('<script', $text);
		$this->assertStringNotContainsStringIgnoringCase('onerror', $text);
	}

	/** @test */
	public function changelogIsOptional() : void
	{
		$response = $this->upload($this->mod['modId'], $this->token['token']);
		$this->assertSame(201, $response['status'], $response['body']);

		$this->assertSame('', $this->db()->getOne('SELECT a.text FROM modReleases r JOIN assets a ON a.assetId = r.assetId WHERE r.releaseId = ?', [$response['json']['releaseId']]));
	}

	/** @test */
	public function changelogJustBelowTheLimitIsAccepted() : void
	{
		$response = $this->upload($this->mod['modId'], $this->token['token'], ['changelog' => '<p>'.str_repeat('a', 60000).'</p>']);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertGreaterThanOrEqual(60000, strlen($this->db()->getOne('SELECT a.text FROM modReleases r JOIN assets a ON a.assetId = r.assetId WHERE r.releaseId = ?', [$response['json']['releaseId']])));
	}

	/** @test */
	public function aSingleScalarGameversionsFieldIsAccepted() : void
	{
		$response = $this->upload($this->mod['modId'], $this->token['token'], ['gameversions' => '1.17.4']);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertSame(['1.17.4'], $response['json']['compatibleGameVersions']);
	}

	/** @test */
	public function csSourceFileModsCanBeUploaded() : void
	{
		$identifier = Fx::identifier();
		$source = "using Vintagestory.API.Common;\n[assembly: ModInfo(\"Api Test Cs\", \"$identifier\", Version = \"3.1.4\")]\n";

		$response = $this->upload($this->mod['modId'], $this->token['token'], ['fileContent' => $source, 'fileName' => "$identifier.cs"]);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertSame($identifier, $response['json']['identifier']);
		$this->assertSame('3.1.4', $response['json']['version']);
	}

	/** @test */
	public function editorTokensCreateReleasesAttributedToTheEditor() : void
	{
		$editor = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		$token = Fx::token($this->mod, $editor['userId']);

		$response = $this->upload($this->mod['modId'], $token['token']);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertSame($editor['userId'], intval($this->db()->getOne('SELECT a.createdByUserId FROM modReleases r JOIN assets a ON a.assetId = r.assetId WHERE r.releaseId = ?', [$response['json']['releaseId']])));
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE kind = ? AND initiatorUserId = ? AND flags = ?', [AUDIT_LOG_KIND_RELEASE_CREATE, $editor['userId'], AUDIT_LOG_FLAG_VIA_API_TOKEN]));
	}

	/** @test */
	public function draftModsAcceptUploadsButThePublicLocationIsHiddenUntilRelease() : void
	{
		$draft = Fx::mod($this->owner['userId'], ['statusId' => STATUS_DRAFT]);
		$token = Fx::token($draft, $this->owner['userId']);

		$response = $this->upload($draft['modId'], $token['token']);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modReleases WHERE modId = ?', [$draft['modId']]));
		// Documented limitation (HANDOFF-CD.md): the public endpoint does not show releases of unreleased mods.
		$this->assertSame(404, Http::request('GET', Http::header($response, 'Location'))['status']);
	}

	/** @test */
	public function serverTweakModsCountAsGameMods() : void
	{
		$tweak = Fx::mod($this->owner['userId'], ['category' => CATEGORY_SERVER_TWEAK]);
		$token = Fx::token($tweak, $this->owner['userId']);

		$response = $this->upload($tweak['modId'], $token['token']);

		$this->assertSame(201, $response['status'], $response['body']);
	}

	/** @test */
	public function aHoveringWebUploadNeitherBlocksTheUploadNorGetsAttached() : void
	{
		$this->db()->execute('INSERT INTO files (assetId, assetTypeId, userId, name, cdnPath, size, `order`) VALUES (NULL, ?, ?, ?, ?, 10, 0)',
			[ASSETTYPE_RELEASE, $this->owner['userId'], 'hovering.zip', "{$this->owner['userId']}/hovering.zip"]);
		$hoveringId = intval($this->db()->insert_ID());

		$response = $this->upload($this->mod['modId'], $this->token['token']);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertNull($this->db()->getOne('SELECT assetId FROM files WHERE fileId = ?', [$hoveringId]));
		$attached = array_map('intval', $this->db()->getCol('SELECT f.fileId FROM files f JOIN modReleases r ON r.assetId = f.assetId WHERE r.releaseId = ?', [$response['json']['releaseId']]));
		$this->assertCount(1, $attached);
		$this->assertNotSame($hoveringId, $attached[0]);
	}

	/** @test */
	public function anotherReleaseOfTheSameIdentifierWithANewVersionIsAccepted() : void
	{
		$identifier = Fx::identifier();
		$first = $this->upload($this->mod['modId'], $this->token['token'], ['fileContent' => StoredZip::mod($identifier, '1.0.0')]);
		$second = $this->upload($this->mod['modId'], $this->token['token'], ['fileContent' => StoredZip::mod($identifier, '1.0.1')]);

		$this->assertSame(201, $first['status'], $first['body']);
		$this->assertSame(201, $second['status'], $second['body']);
		$this->assertSame(['1.0.0', '1.0.1'], [$first['json']['version'], $second['json']['version']]);
		$this->assertSame(2, $this->countRows('SELECT COUNT(*) FROM modReleases WHERE modId = ?', [$this->mod['modId']]));
	}
}
