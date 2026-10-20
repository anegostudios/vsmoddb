<?php

require_once __DIR__.'/support/api-token-support.php';

/** Regression tests for the web release flow that was refactored to share code with the api upload. */
final class ReleaseWebPathRegressionTest extends ApiTokenTestCase
{
	private $owner;
	private $mod;
	/** @var string[] */
	private $tmpFiles = [];

	protected function setUp() : void
	{
		parent::setUp();
		$this->owner = Fx::user();
		$this->mod = Fx::mod($this->owner['userId']);
	}

	protected function tearDown() : void
	{
		foreach($this->tmpFiles as $f)  if(is_file($f)) unlink($f);
		parent::tearDown();
	}

	private function tmpUpload($name, $content) : array
	{
		$path = tempnam(sys_get_temp_dir(), 'apitest-upload-');
		file_put_contents($path, $content);
		$this->tmpFiles[] = $path;
		return ['name' => $name, 'type' => 'application/zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
	}

	//
	// validateGameModReleaseIdentity
	//

	/** @test */
	public function identityRejectsReservedIdentifiers() : void
	{
		foreach(RESERVED_MOD_IDENTIFIERS as $reserved) {
			$this->assertSame(
				['ok' => false, 'status' => 400, 'error' => "This modid ('$reserved') is reserved.", 'errorHtml' => "This modid ('$reserved') is reserved."],
				validateGameModReleaseIdentity($this->mod, $reserved, compileSemanticVersion('1.0.0'))
			);
		}
		$this->assertSame(['ok' => true], validateGameModReleaseIdentity($this->mod, 'gamex', compileSemanticVersion('1.0.0')));
	}

	/** @test */
	public function identityRejectsIdentifiersOfOtherModsWithALinkForTheWebForm() : void
	{
		$other = Fx::mod(Fx::user()['userId']);
		$taken = Fx::identifier();
		Fx::seedRelease($other['modId'], $other['createdByUserId'], $taken, '1.0.0');

		$result = validateGameModReleaseIdentity($this->mod, $taken, compileSemanticVersion('2.0.0'));

		$this->assertFalse($result['ok']);
		$this->assertSame(409, $result['status']);
		$this->assertSame("This modid ('$taken') is already in use by another mod.", $result['error']);
		$this->assertStringStartsWith("This modid ('$taken') is already in use by another mod (<a href='", $result['errorHtml']);
		$this->assertStringContainsString((string)$other['assetId'], $result['errorHtml']);
	}

	/** @test */
	public function identityRejectsAnAlreadyReleasedVersionWithALinkToIt() : void
	{
		$identifier = Fx::identifier();
		$existing = Fx::seedRelease($this->mod['modId'], $this->owner['userId'], $identifier, '1.0.0');

		$result = validateGameModReleaseIdentity($this->mod, $identifier, compileSemanticVersion('1.0.0'));

		$this->assertSame(409, $result['status']);
		$this->assertSame('This version (1.0.0) of the mod has already been released.', $result['error']);
		$this->assertSame("This version (1.0.0) of the mod has already been released (<a href='/edit/release/?assetid={$existing['assetId']}'>link</a>).", $result['errorHtml']);
		$this->assertSame(['ok' => true], validateGameModReleaseIdentity($this->mod, $identifier, compileSemanticVersion('1.0.1')));
	}

	/** @test */
	public function identityIgnoresOnlyTheReleaseBeingEdited() : void
	{
		$identifier = Fx::identifier();
		$edited = Fx::seedRelease($this->mod['modId'], $this->owner['userId'], $identifier, '1.0.0');
		$sibling = Fx::seedRelease($this->mod['modId'], $this->owner['userId'], $identifier, '1.1.0');

		// Saving the edited release with its own version is fine...
		$this->assertSame(['ok' => true], validateGameModReleaseIdentity($this->mod, $identifier, compileSemanticVersion('1.0.0'), $edited['releaseId']));
		// ...changing it to the version of another release is not.
		$this->assertSame(409, validateGameModReleaseIdentity($this->mod, $identifier, compileSemanticVersion('1.1.0'), $edited['releaseId'])['status']);
		$this->assertSame(['ok' => true], validateGameModReleaseIdentity($this->mod, $identifier, compileSemanticVersion('1.1.0'), $sibling['releaseId']));
	}

	/** @test */
	public function identityStillRejectsOtherModsIdentifiersWhenEditing() : void
	{
		$other = Fx::mod(Fx::user()['userId']);
		$taken = Fx::identifier();
		Fx::seedRelease($other['modId'], $other['createdByUserId'], $taken, '1.0.0');
		$edited = Fx::seedRelease($this->mod['modId'], $this->owner['userId'], Fx::identifier(), '1.0.0');

		$this->assertSame(409, validateGameModReleaseIdentity($this->mod, $taken, compileSemanticVersion('1.0.0'), $edited['releaseId'])['status']);
	}

	//
	// compileAndValidateGameVersions
	//

	/** @test */
	public function gameVersionsAreCompiledInInputOrderWithDuplicatesCollapsed() : void
	{
		$this->assertSame(
			['ok' => true, 'versions' => [compileSemanticVersion('1.18.1'), compileSemanticVersion('1.17.4')]],
			compileAndValidateGameVersions(['1.18.1', '1.17.4', '1.18.1'])
		);
	}

	/** @test */
	public function missingGameVersionsAreRejected() : void
	{
		foreach([null, '', '1.18.1', [], 0] as $input) {
			$this->assertSame(['ok' => false, 'status' => 400, 'error' => 'Missing compatible game versions.'], compileAndValidateGameVersions($input), json_encode($input));
		}
	}

	/** @test */
	public function malformedGameVersionsAreRejected() : void
	{
		$this->assertSame(['ok' => false, 'status' => 400, 'error' => "Malformed game version 'banana'."], compileAndValidateGameVersions(['1.18.1', 'banana']));
		$this->assertSame(['ok' => false, 'status' => 400, 'error' => "Malformed game version '1.18.1) OR (1=1'."], compileAndValidateGameVersions(['1.18.1) OR (1=1']));
		$this->assertSame(['ok' => false, 'status' => 400, 'error' => 'Malformed game version.'], compileAndValidateGameVersions(['1.18.1', 11801]));
		$this->assertSame(['ok' => false, 'status' => 400, 'error' => 'Malformed game version.'], compileAndValidateGameVersions([['1.18.1']]));
	}

	/** @test */
	public function unknownGameVersionsAreRejected() : void
	{
		$this->assertSame(['ok' => false, 'status' => 400, 'error' => 'Unknown game version(s): 9.9.9.'], compileAndValidateGameVersions(['1.18.1', '9.9.9']));
		$this->assertSame(['ok' => false, 'status' => 400, 'error' => 'Unknown game version(s): 1.18.2, 1.0.0.'], compileAndValidateGameVersions(['1.18.2', '1.17.4', '1.0.0']));
	}

	//
	// processFileUpload (web form upload)
	//

	/** @test */
	public function webUploadStillCreatesAHoveringFileWithModpeekResults() : void
	{
		$identifier = Fx::identifier();
		$name = "$identifier.zip";
		$content = StoredZip::mod($identifier, '1.4.0');

		$result = Fx::actingAs($this->owner['userId'], fn() => processFileUpload($this->tmpUpload($name, $content), ASSETTYPE_RELEASE, 0, $this->mod['modId']));

		$this->assertSame('ok', $result['status'], json_encode($result));
		$this->assertSame(['status', 'fileid', 'filepath', 'thumbnailfilepath', 'filename', 'uploaddate', 'gameversiondep', 'modparse', 'modid', 'modversion'], array_keys($result));
		$this->assertSame("/cdnfile/{$this->owner['userId']}/$name", $result['filepath']);
		$this->assertNull($result['thumbnailfilepath']);
		$this->assertSame($name, $result['filename']);
		$this->assertSame(compileSemanticVersion('1.18.1'), $result['gameversiondep']);
		$this->assertSame('ok', $result['modparse']);
		$this->assertSame($identifier, $result['modid']);
		$this->assertSame(compileSemanticVersion('1.4.0'), $result['modversion']);

		$file = $this->db()->getRow('SELECT * FROM files WHERE fileId = ?', [$result['fileid']]);
		$this->assertNull($file['assetId'], 'Web uploads hover until the release is saved.');
		$this->assertSame($this->owner['userId'], intval($file['userId']));
		$this->assertSame(ASSETTYPE_RELEASE, intval($file['assetTypeId']));
		$this->assertSame(strlen($content), intval($file['size']));
		$this->assertSame($content, file_get_contents(Fx::storedFilePath($this->owner['userId'], $name)));
		$peek = $this->db()->getRow('SELECT modIdentifier, modVersion FROM modPeekResults WHERE fileId = ?', [$result['fileid']]);
		$this->assertSame([$identifier, compileSemanticVersion('1.4.0')], [$peek['modIdentifier'], intval($peek['modVersion'])]);
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE kind = ? AND referenceId = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_FILE_CREATE, $result['fileid'], $this->owner['userId']]));
	}

	/** @test */
	public function webUploadOfAnUnparsableFileHoversWithTheModpeekError() : void
	{
		$result = Fx::actingAs($this->owner['userId'], fn() => processFileUpload($this->tmpUpload(Fx::unique('bad').'.zip', random_bytes(256)), ASSETTYPE_RELEASE, 0, $this->mod['modId']));

		$this->assertSame('ok', $result['status']);
		$this->assertSame('error', $result['modparse']);
		$this->assertNotEmpty($result['parsemsg']);
		$this->assertNotEmpty($this->db()->getOne('SELECT errors FROM modPeekResults WHERE fileId = ?', [$result['fileid']]));
	}

	/** @test */
	public function webUploadAllowsOnlyOneHoveringReleaseFile() : void
	{
		$first = Fx::actingAs($this->owner['userId'], fn() => processFileUpload($this->tmpUpload(Fx::unique('a').'.zip', StoredZip::mod(Fx::identifier(), '1.0.0')), ASSETTYPE_RELEASE, 0, $this->mod['modId']));
		$this->assertSame('ok', $first['status']);

		$second = Fx::actingAs($this->owner['userId'], fn() => processFileUpload($this->tmpUpload(Fx::unique('b').'.zip', StoredZip::mod(Fx::identifier(), '1.0.0')), ASSETTYPE_RELEASE, 0, $this->mod['modId']));

		$this->assertSame(['status' => 'error', 'errormessage' => 'Too many files! The limit is 1 for this asset'], $second);
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM files WHERE userId = ?', [$this->owner['userId']]));
	}

	/** @test */
	public function webUploadKeepsItsErrorMessages() : void
	{
		$stranger = Fx::user();

		$denied = Fx::actingAs($stranger['userId'], fn() => processFileUpload($this->tmpUpload('x.zip', StoredZip::mod(Fx::identifier(), '1.0.0')), ASSETTYPE_RELEASE, 0, $this->mod['modId']));
		$this->assertSame(['status' => 'error', 'errormessage' => 'Missing permissions to upload files to this asset. You may need to login again'], $denied);

		$type = Fx::actingAs($this->owner['userId'], fn() => processFileUpload($this->tmpUpload('x.txt', 'hello'), ASSETTYPE_RELEASE, 0, $this->mod['modId']));
		$this->assertSame(['status' => 'error', 'errormessage' => 'File type not allowed! Allowed are dll, zip and cs.'], $type);

		$this->db()->execute('UPDATE mods SET uploadLimitOverwrite = 10 WHERE modId = ?', [$this->mod['modId']]);
		$size = Fx::actingAs($this->owner['userId'], fn() => processFileUpload($this->tmpUpload('x.zip', StoredZip::mod(Fx::identifier(), '1.0.0')), ASSETTYPE_RELEASE, 0, $this->mod['modId']));
		$this->assertSame(['status' => 'error', 'errormessage' => 'File too large! Limit is '.formatByteSize(10)], $size);

		$iniError = Fx::actingAs($this->owner['userId'], fn() => processFileUpload(['name' => 'x.zip', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], ASSETTYPE_RELEASE, 0, $this->mod['modId']));
		$this->assertSame(['status' => 'error', 'errormessage' => 'File too large! Limit is '.(parseMaxUploadSizeFromIni() / MB).'MB'], $iniError);

		$partial = Fx::actingAs($this->owner['userId'], fn() => processFileUpload(['name' => 'x.zip', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0], ASSETTYPE_RELEASE, 0, $this->mod['modId']));
		$this->assertSame(['status' => 'error', 'errormessage' => 'A unexpected error occurred while uploading. Error number 3'], $partial);

		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM files WHERE userId IN (?, ?)', [$this->owner['userId'], $stranger['userId']]));
	}

	//
	// edit-release page change log
	//

	/** @test */
	public function releaseChangeLogShowsReleaseEntriesButNotModEntriesWithTheSameId() : void
	{
		$token = Fx::token($this->mod, $this->owner['userId']);
		$upload = $this->upload($this->mod['modId'], $token['token']);
		$this->assertSame(201, $upload['status'], $upload['body']);
		$releaseId = $upload['json']['releaseId'];
		$assetId = intval($this->db()->getOne('SELECT assetId FROM modReleases WHERE releaseId = ?', [$releaseId]));
		$marker = Fx::unique('unrelated-mod-entry-');
		// A mod level entry for the mod whose id happens to equal the release id.
		$this->db()->execute('INSERT INTO auditLogs (kind, flags, referenceId, initiatorUserId, info) VALUES (?, 0, ?, ?, ?)', [AUDIT_LOG_KIND_MOD_CREATE, $releaseId, $this->owner['userId'], $marker]);
		$changelogMarker = Fx::unique('changelog-change-');
		$this->db()->execute('INSERT INTO auditLogs (kind, flags, referenceId, initiatorUserId, info) VALUES (?, 0, ?, ?, ?)', [AUDIT_LOG_KIND_RELEASE_RETRACT, $releaseId, $this->owner['userId'], $changelogMarker]);

		$page = Http::request('GET', "/edit/release/?assetid=$assetId", ['session' => $this->owner, 'headers' => ['Accept: text/html']]);

		$this->assertSame(200, $page['status']);
		$this->assertStringContainsString('Created Release (via API token)', $page['body']);
		$this->assertStringContainsString($changelogMarker, $page['body']);
		$this->assertStringNotContainsString($marker, $page['body']);
	}

	/** @test */
	public function webReleasesAreNotMarkedAsApiReleasesInTheChangeLog() : void
	{
		$release = Fx::seedRelease($this->mod['modId'], $this->owner['userId'], Fx::identifier(), '1.0.0');

		$page = Http::request('GET', "/edit/release/?assetid={$release['assetId']}", ['session' => $this->owner, 'headers' => ['Accept: text/html']]);

		$this->assertSame(200, $page['status']);
		$this->assertMatchesRegularExpression('#<td>Created Release</td>#', $page['body']);
		$this->assertStringNotContainsString('(via API token)', $page['body']);
	}
}
