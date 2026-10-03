<?php

require_once __DIR__.'/support/api-token-support.php';

/**
 * POST /api/v2/mods/{modid}/releases: every rejection answers with the documented status and leaves nothing behind
 * (no release, no asset, no files row, no audit row, no stored file).
 */
final class ApiReleaseUploadRejectionTest extends ApiTokenTestCase
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

	private function uploadExpectingNothing($fields, $status, $error, $prefixOnly = false) : array
	{
		$fields['fileName'] ??= Fx::unique('rejected').'.zip';
		$response = $this->upload($this->mod['modId'], $this->token['token'], $fields);

		if($prefixOnly)  $this->assertJsonErrorPrefix($response, $status, $error);
		else             $this->assertJsonError($response, $status, $error);
		$this->assertNothingCreated($this->mod['modId'], $this->owner['userId'], $fields['fileName']);
		return $response;
	}

	/** @test */
	public function theFixtureUploadItselfIsAccepted() : void
	{
		// Positive control for all rejections below: the same fixture with the default fields is accepted.
		$response = $this->upload($this->mod['modId'], $this->token['token']);

		$this->assertSame(201, $response['status'], $response['body']);
	}

	//
	// request shape
	//

	/** @test */
	public function urlencodedRequestsAre400() : void
	{
		$response = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/releases", ['bearer' => $this->token['token'], 'form' => ['gameversions' => ['1.18.1']]]);

		$this->assertJsonError($response, 400, "This endpoint requires requests with Content-Type 'multipart/form-data'.");
		$this->assertNothingCreated($this->mod['modId'], $this->owner['userId']);
	}

	/**
	 * @test
	 * @dataProvider wrongContentTypes
	 */
	public function otherContentTypesAre400($contentType) : void
	{
		$body = Http::buildMultipart([['name' => 'file', 'filename' => 'm.zip', 'content' => StoredZip::mod(Fx::identifier(), '1.0.0')], ['name' => 'gameversions[]', 'value' => '1.18.1']], 'b0undary');

		$response = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/releases", ['bearer' => $this->token['token'], 'body' => $body, 'contentType' => $contentType]);

		$this->assertJsonError($response, 400, "This endpoint requires requests with Content-Type 'multipart/form-data'.");
		$this->assertNothingCreated($this->mod['modId'], $this->owner['userId']);
	}

	public function wrongContentTypes() : array
	{
		return [
			'json'                 => ['application/json'],
			'text'                 => ['text/plain'],
			'similar prefix'       => ['multipart/form-dataX; boundary=b0undary'],
			'other multipart type' => ['multipart/mixed; boundary=b0undary'],
		];
	}

	/** @test */
	public function contentTypeIsMatchedCaseInsensitively() : void
	{
		$identifier = Fx::identifier();
		$body = Http::buildMultipart([['name' => 'file', 'filename' => "$identifier.zip", 'content' => StoredZip::mod($identifier, '1.0.0')], ['name' => 'gameversions[]', 'value' => '1.18.1']], 'b0undary');

		$response = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/releases", ['bearer' => $this->token['token'], 'body' => $body, 'contentType' => 'Multipart/Form-Data; boundary=b0undary']);

		$this->assertSame(201, $response['status'], $response['body']);
	}

	/** @test */
	public function missingFileIs400() : void
	{
		$this->uploadExpectingNothing(['fileContent' => null], 400, "Missing file. Upload the mod file in the 'file' field.");
	}

	/** @test */
	public function emptyFileFieldIs400() : void
	{
		// A file part without a file name is what browsers send for an empty file input: UPLOAD_ERR_NO_FILE.
		$response = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/releases", ['bearer' => $this->token['token'], 'multipart' => [
			['name' => 'file', 'filename' => '', 'content' => ''],
			['name' => 'gameversions[]', 'value' => '1.18.1'],
		]]);

		$this->assertJsonError($response, 400, "Missing file. Upload the mod file in the 'file' field.");
		$this->assertNothingCreated($this->mod['modId'], $this->owner['userId']);
	}

	/** @test */
	public function aSecondFileFieldIs400() : void
	{
		$second = Fx::unique('second').'.zip';
		$this->uploadExpectingNothing(['extraParts' => [['name' => 'other', 'filename' => $second, 'content' => StoredZip::mod(Fx::identifier(), '1.0.0')]]],
			400, 'Exactly one file must be uploaded per release.');
		$this->assertFileDoesNotExist(Fx::storedFilePath($this->owner['userId'], $second));
	}

	/** @test */
	public function aFileArrayIs400() : void
	{
		$name = Fx::unique('arr').'.zip';
		$response = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/releases", ['bearer' => $this->token['token'], 'multipart' => [
			['name' => 'file[]', 'filename' => $name, 'content' => StoredZip::mod(Fx::identifier(), '1.0.0')],
			['name' => 'gameversions[]', 'value' => '1.18.1'],
		]]);

		$this->assertJsonError($response, 400, 'Exactly one file must be uploaded per release.');
		$this->assertNothingCreated($this->mod['modId'], $this->owner['userId'], $name);
	}

	//
	// game versions
	//

	/** @test */
	public function missingGameVersionsAre400() : void
	{
		$this->uploadExpectingNothing(['gameversions' => null], 400, 'Missing compatible game versions.');
	}

	/** @test */
	public function malformedGameVersionIs400() : void
	{
		$this->uploadExpectingNothing(['gameversions' => ['1.18.1', 'banana']], 400, "Malformed game version 'banana'.");
	}

	/** @test */
	public function unknownGameVersionsAre400AndListed() : void
	{
		$this->uploadExpectingNothing(['gameversions' => ['1.18.1', '9.9.9', '8.8.8']], 400, 'Unknown game version(s): 9.9.9, 8.8.8.');
	}

	/** @test */
	public function nestedGameVersionArrayIs400() : void
	{
		$this->uploadExpectingNothing(['gameversions' => null, 'extraParts' => [['name' => 'gameversions[][]', 'value' => '1.18.1']]], 400, 'Malformed game version.');
	}

	//
	// changelog
	//

	/** @test */
	public function changelogAboveTheTextColumnLimitIs400() : void
	{
		$this->uploadExpectingNothing(['changelog' => '<p>'.str_repeat('a', 70000).'</p>'], 400, 'Changelog has excessive size (68KB).');
	}

	/** @test */
	public function changelogArrayIs400() : void
	{
		$this->uploadExpectingNothing(['extraParts' => [['name' => 'changelog[]', 'value' => 'x']]], 400, 'Malformed changelog.');
	}

	//
	// the file itself
	//

	/** @test */
	public function disallowedExtensionIs400EvenForAValidModZip() : void
	{
		$this->uploadExpectingNothing(['fileName' => Fx::unique('wrong').'.txt'], 400, 'File type not allowed! Allowed are dll, zip and cs.');
	}

	/** @test */
	public function garbageZipIs400() : void
	{
		$this->uploadExpectingNothing(['fileContent' => random_bytes(512)], 400, 'Failed to parse modinfo: ', true);
	}

	/** @test */
	public function zipWithoutModinfoIs400() : void
	{
		$this->uploadExpectingNothing(['fileContent' => StoredZip::build(['readme.txt' => 'hello'])], 400, 'Failed to parse modinfo: ', true);
	}

	/** @test */
	public function modinfoWithoutVersionIs400() : void
	{
		$zip = StoredZip::build(['modinfo.json' => json_encode(['type' => 'content', 'modid' => Fx::identifier(), 'name' => 'No Version'])]);
		$this->uploadExpectingNothing(['fileContent' => $zip], 400, 'Failed to parse modinfo: ', true);
	}

	/** @test */
	public function fileAboveTheModsUploadLimitIs413ButAcceptedWithoutTheOverwrite() : void
	{
		$content = StoredZip::mod(Fx::identifier(), '1.0.0');
		$this->db()->execute('UPDATE mods SET uploadLimitOverwrite = ? WHERE modId = ?', [strlen($content) - 1, $this->mod['modId']]);

		$this->uploadExpectingNothing(['fileContent' => $content], 413, 'File too large! Limit is ', true);

		$this->db()->execute('UPDATE mods SET uploadLimitOverwrite = ? WHERE modId = ?', [strlen($content), $this->mod['modId']]);
		$accepted = $this->upload($this->mod['modId'], $this->token['token'], ['fileContent' => $content]);
		$this->assertSame(201, $accepted['status'], $accepted['body']);
	}

	/** @test */
	public function fileAbovePhpsUploadLimitIs413() : void
	{
		$limit = parseIniSize(ini_get('upload_max_filesize'));
		$postLimit = parseIniSize(ini_get('post_max_size'));
		if($limit + 4096 >= $postLimit)  $this->markTestSkipped('upload_max_filesize is not below post_max_size in this environment.');

		$this->uploadExpectingNothing(['fileContent' => str_repeat('x', $limit + 1)], 413, 'File too large! Limit is ', true);
	}

	//
	// mod identity
	//

	/** @test */
	public function reservedIdentifierIs400() : void
	{
		foreach(['game', 'creative', 'survival'] as $reserved) {
			$this->uploadExpectingNothing(['fileContent' => StoredZip::mod($reserved, '1.0.0')], 400, "This modid ('$reserved') is reserved.");
		}
	}

	/** @test */
	public function identifierOfAnotherModIs409ButFreeIdentifiersWork() : void
	{
		$stranger = Fx::user();
		$otherMod = Fx::mod($stranger['userId']);
		$taken = Fx::identifier();
		Fx::seedRelease($otherMod['modId'], $stranger['userId'], $taken, '1.0.0');

		$this->uploadExpectingNothing(['fileContent' => StoredZip::mod($taken, '7.0.0')], 409, "This modid ('$taken') is already in use by another mod.");

		$free = $this->upload($this->mod['modId'], $this->token['token'], ['fileContent' => StoredZip::mod(Fx::identifier(), '7.0.0')]);
		$this->assertSame(201, $free['status'], $free['body']);
	}

	/** @test */
	public function sameVersionTwiceIs409AndTheSecondFileIsNotKept() : void
	{
		$identifier = Fx::identifier();
		$first = $this->upload($this->mod['modId'], $this->token['token'], ['fileContent' => StoredZip::mod($identifier, '1.0.0'), 'fileName' => "$identifier-a.zip"]);
		$this->assertSame(201, $first['status'], $first['body']);

		$second = $this->upload($this->mod['modId'], $this->token['token'], ['fileContent' => StoredZip::mod($identifier, '1.0.0'), 'fileName' => "$identifier-b.zip"]);

		$this->assertJsonError($second, 409, 'This version (1.0.0) of the mod has already been released.');
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modReleases WHERE modId = ?', [$this->mod['modId']]));
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM files WHERE userId = ?', [$this->owner['userId']]));
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE kind = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_RELEASE_CREATE, $this->owner['userId']]));
		$this->assertFileExists(Fx::storedFilePath($this->owner['userId'], "$identifier-a.zip"));
		$this->assertFileDoesNotExist(Fx::storedFilePath($this->owner['userId'], "$identifier-b.zip"));
	}

	//
	// mod state
	//

	/** @test */
	public function lockedModIs403UntilUnlocked() : void
	{
		$this->db()->execute('UPDATE assets SET statusId = ? WHERE assetId = ?', [STATUS_LOCKED, $this->mod['assetId']]);

		$this->uploadExpectingNothing([], 403, 'This mod has been locked by a moderator. New releases can not be created until it is unlocked.');

		$this->db()->execute('UPDATE assets SET statusId = ? WHERE assetId = ?', [STATUS_RELEASED, $this->mod['assetId']]);
		$accepted = $this->upload($this->mod['modId'], $this->token['token']);
		$this->assertSame(201, $accepted['status'], $accepted['body']);
	}

	/**
	 * @test
	 * @dataProvider nonGameModCategories
	 */
	public function nonGameModsAre400($category) : void
	{
		$this->db()->execute('UPDATE mods SET category = ? WHERE modId = ?', [$category, $this->mod['modId']]);

		$this->uploadExpectingNothing([], 400, 'Uploading releases through the api is not supported yet for this mod category.');
	}

	public function nonGameModCategories() : array
	{
		return ['external tool' => [CATEGORY_EXTERNAL_TOOL], 'other' => [CATEGORY_OTHER]];
	}

	/** @test */
	public function requestBodyAbovePostMaxSizeIs413() : void
	{
		// The dev container prints a startup warning before any header when post_max_size is exceeded (display_errors=STDOUT),
		// which makes a 413 impossible there. Production runs without display_errors, so this uses a server configured like that.
		$postLimit = parseIniSize(ini_get('post_max_size'));
		$name = Fx::unique('huge').'.zip';

		$response = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/releases", ['bearer' => $this->token['token'], 'iniFlags' => ['display_errors=0'], 'multipart' => [
			['name' => 'file', 'filename' => $name, 'content' => str_repeat('x', $postLimit + 1024)],
			['name' => 'gameversions[]', 'value' => '1.18.1'],
		]]);

		$this->assertJsonError($response, 413, 'Request too large! Limit is '.formatByteSize($postLimit).'.');
		$this->assertNothingCreated($this->mod['modId'], $this->owner['userId'], $name);
	}
}
