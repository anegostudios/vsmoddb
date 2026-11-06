<?php

require_once __DIR__.'/support/api-token-support.php';

/** At most API_RELEASE_LIMIT_PER_MOD_PER_HOUR token-made releases per mod per rolling hour. */
final class ApiReleaseRateLimitTest extends ApiTokenTestCase
{
	const TOO_MANY = 'Too many releases. At most 10 releases per hour can be created through the api.';

	private $owner;
	private $mod;
	private $token;

	protected function setUp() : void
	{
		parent::setUp();
		$this->assertSame(10, API_RELEASE_LIMIT_PER_MOD_PER_HOUR, 'These tests assume the default limit.');
		$this->owner = Fx::user();
		$this->mod = Fx::mod($this->owner['userId']);
		$this->token = Fx::token($this->mod, $this->owner['userId']);
	}

	private function seed($count, $flags, $minutesAgo, $modId = null) : void
	{
		$identifier = Fx::identifier();
		for($i = 1; $i <= $count; $i++) {
			Fx::seedRelease($modId ?? $this->mod['modId'], $this->owner['userId'], $identifier, "0.0.$i", $flags, $minutesAgo);
		}
	}

	private function releases() : int
	{
		return $this->countRows('SELECT COUNT(*) FROM modReleases WHERE modId = ?', [$this->mod['modId']]);
	}

	/** @test */
	public function tenthReleaseWithinTheHourIsAcceptedAndTheEleventhIs429WithRetryAfter() : void
	{
		$this->seed(9, AUDIT_LOG_FLAG_VIA_API_TOKEN, 30);

		$tenth = $this->upload($this->mod['modId'], $this->token['token']);
		$this->assertSame(201, $tenth['status'], $tenth['body']);

		$fileName = Fx::unique('eleventh').'.zip';
		$eleventh = $this->upload($this->mod['modId'], $this->token['token'], ['fileName' => $fileName]);

		$this->assertJsonError($eleventh, 429, self::TOO_MANY);
		// The 10th newest counted release (seeded 30 minutes ago) has to leave the window: ~1800s.
		$retryAfter = Http::header($eleventh, 'Retry-After');
		$this->assertMatchesRegularExpression('/^\d+$/', (string)$retryAfter);
		$this->assertGreaterThanOrEqual(1780, intval($retryAfter));
		$this->assertLessThanOrEqual(1800, intval($retryAfter));
		$this->assertSame(10, $this->releases());
		$this->assertFileDoesNotExist(Fx::storedFilePath($this->owner['userId'], $fileName));
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM files WHERE userId = ?', [$this->owner['userId']]));
	}

	/** @test */
	public function tenRealUploadsExhaustTheLimit() : void
	{
		for($i = 0; $i < 10; $i++) {
			$response = $this->upload($this->mod['modId'], $this->token['token']);
			$this->assertSame(201, $response['status'], "Upload #$i: ".$response['body']);
		}

		$refused = $this->upload($this->mod['modId'], $this->token['token']);

		$this->assertJsonError($refused, 429, self::TOO_MANY);
		$this->assertGreaterThanOrEqual(3590, intval(Http::header($refused, 'Retry-After')));
		$this->assertLessThanOrEqual(3600, intval(Http::header($refused, 'Retry-After')));
		$this->assertSame(10, $this->releases());
	}

	/** @test */
	public function theLimitIsPerModAndNotPerToken() : void
	{
		$editor = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		$editorToken = Fx::token($this->mod, $editor['userId']);
		$this->seed(10, AUDIT_LOG_FLAG_VIA_API_TOKEN, 5);

		$refused = $this->upload($this->mod['modId'], $editorToken['token']);

		$this->assertJsonError($refused, 429, self::TOO_MANY);
		$this->assertSame(10, $this->releases());
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM files WHERE userId = ?', [$editor['userId']]));
		$this->assertDirectoryDoesNotExist(SRC_ROOT.'/files/'.$editor['userId']);
	}

	/** @test */
	public function releasesMadeThroughTheWebFormDoNotCount() : void
	{
		$this->seed(10, 0, 5);
		$this->seed(5, AUDIT_LOG_FLAG_MODACTION, 5);

		$response = $this->upload($this->mod['modId'], $this->token['token']);

		$this->assertSame(201, $response['status'], $response['body']);
	}

	/** @test */
	public function releasesOfOtherModsDoNotCount() : void
	{
		$otherMod = Fx::mod($this->owner['userId']);
		$this->seed(10, AUDIT_LOG_FLAG_VIA_API_TOKEN, 5, $otherMod['modId']);

		$response = $this->upload($this->mod['modId'], $this->token['token']);
		$this->assertSame(201, $response['status'], $response['body']);

		// ...while the other mod itself is limited.
		$otherToken = Fx::token($otherMod, $this->owner['userId']);
		$this->assertSame(429, $this->upload($otherMod['modId'], $otherToken['token'])['status']);
	}

	/** @test */
	public function releasesOlderThanAnHourDoNotCount() : void
	{
		$this->seed(10, AUDIT_LOG_FLAG_VIA_API_TOKEN, 61);

		$response = $this->upload($this->mod['modId'], $this->token['token']);

		$this->assertSame(201, $response['status'], $response['body']);
	}

	/** @test */
	public function releasesWithAdditionalFlagsStillCount() : void
	{
		$this->seed(10, AUDIT_LOG_FLAG_VIA_API_TOKEN | AUDIT_LOG_FLAG_MODACTION, 5);

		$this->assertSame(429, $this->upload($this->mod['modId'], $this->token['token'])['status']);
	}
}
