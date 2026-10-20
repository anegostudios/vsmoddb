<?php

require_once __DIR__.'/support/api-token-support.php';

/**
 * Bearer authentication in lib/api/authenticated/_routing.php and the security invariant:
 * a token can ONLY be used for `POST /api/v2/mods/{modid}/releases` of its own mod.
 * Every test runs against the real entry point over http.
 */
final class ApiTokenBearerAuthTest extends ApiTokenTestCase
{
	const ONLY_UPLOADS = 'Api tokens can only be used to upload releases (POST /api/v2/mods/{modid}/releases).';
	const INVALID = 'Invalid or expired api token.';

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

	private function releaseCount($modId = null) : int
	{
		return $this->countRows('SELECT COUNT(*) FROM modReleases WHERE modId = ?', [$modId ?? $this->mod['modId']]);
	}

	private function lastUsed($tokenId = null)
	{
		return $this->db()->getOne('SELECT lastUsed FROM modApiTokens WHERE tokenId = ?', [$tokenId ?? $this->token['tokenId']]);
	}

	//
	// authentication failures
	//

	/** @test */
	public function validTokenCreatesARelease() : void
	{
		$response = $this->upload($this->mod['modId'], $this->token['token']);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertSame(1, $this->releaseCount());
	}

	/** @test */
	public function uploadWithoutAnyAuthenticationIs401WithBearerChallenge() : void
	{
		$response = $this->upload($this->mod['modId'], null);

		$this->assertJsonError($response, 401, 'This endpoint requires an api token (Authorization: Bearer <token>).');
		$this->assertSame('Bearer', Http::header($response, 'WWW-Authenticate'));
		$this->assertSame(0, $this->releaseCount());
	}

	/** @test */
	public function uploadWithTheOwnersSessionCookieInsteadOfATokenIs401() : void
	{
		$response = $this->upload($this->mod['modId'], null, ['session' => $this->owner]);

		$this->assertJsonError($response, 401, 'This endpoint requires an api token (Authorization: Bearer <token>).');
		$this->assertSame(0, $this->releaseCount());
	}

	/**
	 * @test
	 * @dataProvider invalidTokens
	 */
	public function invalidTokensAre401WithInvalidTokenChallenge($makeToken) : void
	{
		$token = $makeToken($this);

		$response = $this->upload($this->mod['modId'], $token);

		$this->assertJsonError($response, 401, self::INVALID);
		$this->assertSame('Bearer error="invalid_token"', Http::header($response, 'WWW-Authenticate'));
		$this->assertSame(0, $this->releaseCount());
		$this->assertNull($this->lastUsed());
	}

	public function invalidTokens() : array
	{
		return [
			'empty bearer'        => [fn($t) => ''],
			'garbage'             => [fn($t) => 'abc'],
			'unknown well formed' => [fn($t) => API_TOKEN_PREFIX.str_repeat('A', 43)],
			'one char changed'    => [fn($t) => substr($t->token['token'], 0, -1).(substr($t->token['token'], -1) === 'x' ? 'y' : 'x')],
			'missing prefix'      => [fn($t) => substr($t->token['token'], 8)],
			'expired'             => [fn($t) => Fx::rawToken($t->mod['modId'], $t->owner['userId'], 'NOW() - INTERVAL 1 MINUTE')['token']],
		];
	}

	/** @test */
	public function nonBearerAuthorizationSchemeIsTreatedAsNoToken() : void
	{
		$response = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/releases", [
			'headers' => ['Authorization: Basic '.base64_encode('a:'.$this->token['token'])],
			'multipart' => [['name' => 'gameversions[]', 'value' => '1.18.1']],
		]);

		$this->assertJsonError($response, 401, 'This endpoint requires an api token (Authorization: Bearer <token>).');
	}

	/** @test */
	public function invalidTokenNeverFallsBackToTheSessionCookie() : void
	{
		$before = $this->db()->getOne('SELECT genAiTolerance FROM users WHERE userId = ?', [$this->owner['userId']]);

		$response = Http::request('POST', '/api/v2/settings/gen-ai', ['bearer' => 'garbage', 'session' => $this->owner, 'form' => ['tolerance' => 42]]);

		$this->assertJsonError($response, 401, self::INVALID);
		$this->assertSame($before, $this->db()->getOne('SELECT genAiTolerance FROM users WHERE userId = ?', [$this->owner['userId']]));

		// Positive control: the same request with only the cookie works.
		$control = Http::request('POST', '/api/v2/settings/gen-ai', ['session' => $this->owner, 'form' => ['tolerance' => 42]]);
		$this->assertSame(200, $control['status'], $control['body']);
		$this->assertSame(42, intval($this->db()->getOne('SELECT genAiTolerance FROM users WHERE userId = ?', [$this->owner['userId']])));
	}

	//
	// SECURITY INVARIANT: no route but the upload
	//

	/** @test */
	public function tokenCannotChangeUserSettingsButTheSessionCan() : void
	{
		$before = $this->db()->getOne('SELECT genAiTolerance FROM users WHERE userId = ?', [$this->owner['userId']]);

		$response = Http::request('POST', '/api/v2/settings/gen-ai', ['bearer' => $this->token['token'], 'form' => ['tolerance' => 42]]);

		$this->assertJsonError($response, 403, self::ONLY_UPLOADS);
		$this->assertSame($before, $this->db()->getOne('SELECT genAiTolerance FROM users WHERE userId = ?', [$this->owner['userId']]));

		$control = Http::request('POST', '/api/v2/settings/gen-ai', ['session' => $this->owner, 'form' => ['tolerance' => 42]]);
		$this->assertSame(200, $control['status'], $control['body']);
		$this->assertSame(42, intval($this->db()->getOne('SELECT genAiTolerance FROM users WHERE userId = ?', [$this->owner['userId']])));
	}

	/** @test */
	public function tokenCannotReadOrClearNotificationsButTheSessionCan() : void
	{
		$this->db()->execute('INSERT INTO notifications (userId, kind, recordId) VALUES (?, ?, ?)', [$this->owner['userId'], NOTIFICATION_NEW_RELEASE, $this->mod['modId']]);
		$notificationId = intval($this->db()->insert_ID());

		$read = Http::request('GET', '/api/v2/notifications', ['bearer' => $this->token['token']]);
		$this->assertJsonError($read, 403, self::ONLY_UPLOADS);

		$clear = Http::request('POST', '/api/v2/notifications/clear', ['bearer' => $this->token['token'], 'form' => ['ids' => "$notificationId"]]);
		$this->assertJsonError($clear, 403, self::ONLY_UPLOADS);
		$this->assertSame(0, intval($this->db()->getOne('SELECT `read` FROM notifications WHERE notificationId = ?', [$notificationId])));

		$controlRead = Http::request('GET', '/api/v2/notifications', ['session' => $this->owner]);
		$this->assertSame([$notificationId], $controlRead['json']);
		$controlClear = Http::request('POST', '/api/v2/notifications/clear', ['session' => $this->owner, 'form' => ['ids' => "$notificationId"]]);
		$this->assertSame(200, $controlClear['status'], $controlClear['body']);
		$this->assertSame(1, intval($this->db()->getOne('SELECT `read` FROM notifications WHERE notificationId = ?', [$notificationId])));
	}

	/** @test */
	public function tokenCannotManageApiTokensEvenWithTheActionToken() : void
	{
		$list = Http::request('GET', "/api/v2/mods/{$this->mod['modId']}/api-tokens?at={$this->owner['at']}", ['bearer' => $this->token['token']]);
		$this->assertJsonError($list, 403, self::ONLY_UPLOADS);

		$create = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/api-tokens", ['bearer' => $this->token['token'], 'form' => ['name' => 'evil', 'lifetimeDays' => 365, 'at' => $this->owner['at']]]);
		$this->assertJsonError($create, 403, self::ONLY_UPLOADS);
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE modId = ?', [$this->mod['modId']]));

		$revoke = Http::request('DELETE', "/api/v2/mods/{$this->mod['modId']}/api-tokens/{$this->token['tokenId']}", ['bearer' => $this->token['token'], 'form' => ['at' => $this->owner['at']]]);
		$this->assertJsonError($revoke, 403, self::ONLY_UPLOADS);
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$this->token['tokenId']]));

		// Positive control with the session.
		$control = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/api-tokens", ['session' => $this->owner, 'form' => ['name' => 'fine', 'lifetimeDays' => 1, 'at' => $this->owner['at']]]);
		$this->assertSame(201, $control['status'], $control['body']);
		$this->assertSame(2, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE modId = ?', [$this->mod['modId']]));
	}

	/** @test */
	public function tokenCannotRetractAReleaseButTheSessionCan() : void
	{
		$release = Fx::seedRelease($this->mod['modId'], $this->owner['userId'], Fx::identifier(), '1.0.0');
		$path = "/api/v2/mods/{$this->mod['modId']}/releases/{$release['releaseId']}/retraction";

		$response = Http::request('PUT', $path, ['bearer' => $this->token['token'], 'form' => ['reason' => '<p>broken</p>', 'at' => $this->owner['at']]]);

		$this->assertJsonError($response, 403, self::ONLY_UPLOADS);
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM modReleaseRetractions WHERE releaseId = ?', [$release['releaseId']]));

		$control = Http::request('PUT', $path, ['session' => $this->owner, 'form' => ['reason' => '<p>broken</p>', 'at' => $this->owner['at']]]);
		$this->assertSame(200, $control['status'], $control['body']);
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modReleaseRetractions WHERE releaseId = ?', [$release['releaseId']]));
	}

	/** @test */
	public function tokenOfAModeratorOwnerCannotUseModeratorRoutes() : void
	{
		$moderator = Fx::user(['role' => 'moderator']);
		$mod = Fx::mod($moderator['userId']);
		$token = Fx::token($mod, $moderator['userId']);
		$path = "/api/v2/mods/{$mod['modId']}/releases/upload-limit";

		$response = Http::request('PUT', $path, ['bearer' => $token['token'], 'form' => ['limit' => 1234, 'at' => $moderator['at']]]);
		$this->assertJsonError($response, 403, self::ONLY_UPLOADS);
		$this->assertNull($this->db()->getOne('SELECT uploadLimitOverwrite FROM mods WHERE modId = ?', [$mod['modId']]));

		$lock = Http::request('POST', "/api/v2/mods/{$mod['modId']}/lock", ['bearer' => $token['token'], 'form' => ['reason' => 'x', 'at' => $moderator['at']]]);
		$this->assertJsonError($lock, 403, self::ONLY_UPLOADS);
		$this->assertSame(STATUS_RELEASED, intval($this->db()->getOne('SELECT statusId FROM assets WHERE assetId = ?', [$mod['assetId']])));

		$control = Http::request('PUT', $path, ['session' => $moderator, 'form' => ['limit' => 1234, 'at' => $moderator['at']]]);
		$this->assertSame(200, $control['status'], $control['body']);
		$this->assertSame(1234, intval($this->db()->getOne('SELECT uploadLimitOverwrite FROM mods WHERE modId = ?', [$mod['modId']])));
	}

	/** @test */
	public function tokenCannotTransferOwnership() : void
	{
		$editor = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);

		$response = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/transfer", ['bearer' => $this->token['token'], 'form' => ['newOwnerId' => $editor['userId'], 'at' => $this->owner['at']]]);

		$this->assertJsonError($response, 403, self::ONLY_UPLOADS);
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM notifications WHERE userId = ?', [$editor['userId']]));

		$control = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/transfer", ['session' => $this->owner, 'form' => ['newOwnerId' => $editor['userId'], 'at' => $this->owner['at']]]);
		$this->assertSame(200, $control['status'], $control['body']);
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM notifications WHERE userId = ? AND kind = ?', [$editor['userId'], NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST]));
	}

	/**
	 * @test
	 * @dataProvider pathTricks
	 */
	public function uploadOnlyWorksOnTheExactPath($method, $pathTemplate, $expectedStatus) : void
	{
		$path = str_replace('{id}', $this->mod['modId'], $pathTemplate);

		$response = Http::request($method, $path, ['bearer' => $this->token['token'], 'multipart' => [
			['name' => 'file', 'filename' => Fx::unique('m').'.zip', 'content' => StoredZip::mod(Fx::identifier(), '1.0.0')],
			['name' => 'gameversions[]', 'value' => '1.18.1'],
		]]);

		$this->assertSame($expectedStatus, $response['status'], $response['body']);
		if($expectedStatus === 403)  $this->assertSame(['error' => self::ONLY_UPLOADS], $response['json']);
		$this->assertSame(0, $this->releaseCount());
		$this->assertNull($this->lastUsed());
	}

	public function pathTricks() : array
	{
		return [
			'tags prefix'        => ['POST', '/api/v2/tags/mods/{id}/releases', 403],
			'users prefix'       => ['POST', '/api/v2/users/mods/{id}/releases', 403],
			'dot segment'        => ['POST', '/api/v2/mods/{id}/./releases', 403],
			'dot dot segment'    => ['POST', '/api/v2/mods/{id}/../{id}/releases', 403],
			'hidden segment'     => ['POST', '/api/v2/mods/{id}/.x/releases', 403],
			'trailing segment'   => ['POST', '/api/v2/mods/{id}/releases/upload-limit', 403],
			'double slash'       => ['POST', '/api/v2/mods//{id}/releases', 403],
			'PUT method'         => ['PUT', '/api/v2/mods/{id}/releases', 403],
			'PATCH method'       => ['PATCH', '/api/v2/mods/{id}/releases', 403],
			'DELETE method'      => ['DELETE', '/api/v2/mods/{id}/releases', 403],
		];
	}

	/** @test */
	public function uploadAcceptsATrailingSlashAndAQueryString() : void
	{
		$response = $this->upload($this->mod['modId'], $this->token['token'], ['path' => "/api/v2/mods/{$this->mod['modId']}/releases/?x=1"]);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertSame(1, $this->releaseCount());
	}

	//
	// token / mod binding and the creator's standing
	//

	/** @test */
	public function tokenOfModAIsRefusedOnModBButWorksOnModA() : void
	{
		$modB = Fx::mod($this->owner['userId']);

		$refused = $this->upload($modB['modId'], $this->token['token']);
		$this->assertJsonError($refused, 403, 'This api token belongs to a different mod.');
		$this->assertSame(0, $this->releaseCount($modB['modId']));
		$this->assertNull($this->lastUsed());

		$accepted = $this->upload($this->mod['modId'], $this->token['token']);
		$this->assertSame(201, $accepted['status'], $accepted['body']);
		$this->assertSame(1, $this->releaseCount());
	}

	/** @test */
	public function tokenOnANonexistentModIs403Not404() : void
	{
		$missing = intval($this->db()->getOne('SELECT MAX(modId) FROM mods')) + 1000;

		$response = $this->upload($missing, $this->token['token']);

		$this->assertJsonError($response, 403, 'This api token belongs to a different mod.');
	}

	/** @test */
	public function tokenActsAsItsCreatorNeverAsTheCookieUser() : void
	{
		$other = Fx::user(['role' => 'admin']);

		$response = $this->upload($this->mod['modId'], $this->token['token'], ['session' => $other]);

		$this->assertSame(201, $response['status'], $response['body']);
		$row = $this->db()->getRow('SELECT a.createdByUserId, a.editedByUserId FROM modReleases r JOIN assets a ON a.assetId = r.assetId WHERE r.releaseId = ?', [$response['json']['releaseId']]);
		$this->assertSame($this->owner['userId'], intval($row['createdByUserId']));
		$this->assertSame($this->owner['userId'], intval($row['editedByUserId']));
		$this->assertSame($this->owner['userId'], intval($this->db()->getOne('SELECT userId FROM files WHERE assetId = (SELECT assetId FROM modReleases WHERE releaseId = ?)', [$response['json']['releaseId']])));
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE initiatorUserId = ?', [$other['userId']]));
	}

	/** @test */
	public function tokenOfABannedCreatorIsRefusedUntilTheBanEnds() : void
	{
		$this->db()->execute('UPDATE users SET bannedUntil = NOW() + INTERVAL 1 DAY WHERE userId = ?', [$this->owner['userId']]);

		$refused = $this->upload($this->mod['modId'], $this->token['token']);
		$this->assertJsonError($refused, 403, 'The creator of this api token is currently banned.');
		$this->assertSame(0, $this->releaseCount());
		$this->assertNull($this->lastUsed());

		$this->db()->execute('UPDATE users SET bannedUntil = NOW() - INTERVAL 1 MINUTE WHERE userId = ?', [$this->owner['userId']]);

		$accepted = $this->upload($this->mod['modId'], $this->token['token']);
		$this->assertSame(201, $accepted['status'], $accepted['body']);
		$this->assertSame(1, $this->releaseCount());
	}

	/** @test */
	public function tokenOfAnEditorIsRefusedOnceTheEditFlagIsGoneEvenIfTheRowWasNotRevoked() : void
	{
		$editor = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		$token = Fx::token($this->mod, $editor['userId']);

		$accepted = $this->upload($this->mod['modId'], $token['token']);
		$this->assertSame(201, $accepted['status'], $accepted['body']);

		// Bypasses updateModTeamMembers on purpose, the router must not rely on the revocation alone.
		$this->db()->execute('UPDATE modTeamMembers SET canEdit = 0 WHERE modId = ? AND userId = ?', [$this->mod['modId'], $editor['userId']]);

		$refused = $this->upload($this->mod['modId'], $token['token']);
		$this->assertJsonError($refused, 403, 'The creator of this api token is no longer the owner or an editor of this mod.');
		$this->assertSame(1, $this->releaseCount());
	}

	/** @test */
	public function tokenOfARemovedTeamMemberIsRefused() : void
	{
		$editor = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		$token = Fx::token($this->mod, $editor['userId']);
		$this->db()->execute('DELETE FROM modTeamMembers WHERE modId = ? AND userId = ?', [$this->mod['modId'], $editor['userId']]);

		$refused = $this->upload($this->mod['modId'], $token['token']);

		$this->assertJsonError($refused, 403, 'The creator of this api token is no longer the owner or an editor of this mod.');
		$this->assertSame(0, $this->releaseCount());
	}

	/** @test */
	public function tokenOfAPreviousOwnerIsRefusedAfterTheOwnershipChanged() : void
	{
		$newOwner = Fx::user();
		$this->db()->execute('UPDATE assets SET createdByUserId = ? WHERE assetId = ?', [$newOwner['userId'], $this->mod['assetId']]);

		$refused = $this->upload($this->mod['modId'], $this->token['token']);

		$this->assertJsonError($refused, 403, 'The creator of this api token is no longer the owner or an editor of this mod.');
		$this->assertSame(0, $this->releaseCount());
	}

	/** @test */
	public function aModeratorWhoIsNotOnTheTeamCannotUseATokenCreatedForThem() : void
	{
		// A token row for a moderator can only exist through manual db edits or a bug, canEditMod() would let them through, the router must not.
		$moderator = Fx::user(['role' => 'moderator']);
		$raw = Fx::rawToken($this->mod['modId'], $moderator['userId'], 'NOW() + INTERVAL 1 DAY');

		$refused = $this->upload($this->mod['modId'], $raw['token']);

		$this->assertJsonError($refused, 403, 'The creator of this api token is no longer the owner or an editor of this mod.');
		$this->assertSame(0, $this->releaseCount());
	}

	//
	// lastUsed
	//

	/** @test */
	public function lastUsedIsOnlyUpdatedOnceTheTokenIsAccepted() : void
	{
		Http::request('POST', '/api/v2/settings/gen-ai', ['bearer' => $this->token['token'], 'form' => ['tolerance' => 1]]);
		$this->upload(Fx::mod($this->owner['userId'])['modId'], $this->token['token']);
		$this->assertNull($this->lastUsed(), 'Refused requests must not update lastUsed');

		// Authenticated, but rejected by the handler: still counts as a use of the token.
		$response = $this->upload($this->mod['modId'], $this->token['token'], ['fileContent' => null]);
		$this->assertSame(400, $response['status'], $response['body']);

		$age = $this->db()->getOne('SELECT TIMESTAMPDIFF(SECOND, lastUsed, NOW()) FROM modApiTokens WHERE tokenId = ?', [$this->token['tokenId']]);
		$this->assertNotNull($age);
		$this->assertLessThanOrEqual(5, abs(intval($age)));
	}

	//
	// public endpoints and v1 never see the token
	//

	/** @test */
	public function bearerHeaderHasNoEffectOnPublicV2Endpoints() : void
	{
		Fx::seedRelease($this->mod['modId'], $this->owner['userId'], Fx::identifier(), '1.0.0');
		$path = "/api/v2/mods/{$this->mod['modId']}/releases";

		$plain = Http::request('GET', $path);
		$withToken = Http::request('GET', $path, ['bearer' => $this->token['token']]);
		$withGarbage = Http::request('GET', $path, ['bearer' => 'garbage']);

		$this->assertSame(200, $plain['status'], $plain['body']);
		$this->assertCount(1, $plain['json']);
		$this->assertSame($plain['body'], $withToken['body']);
		$this->assertSame(200, $withGarbage['status']);
		$this->assertSame($plain['body'], $withGarbage['body']);
		$this->assertNull($this->lastUsed());
	}

	/** @test */
	public function bearerHeaderHasNoEffectOnTheV1Api() : void
	{
		$plain = Http::request('GET', '/api/gameversions');
		$withGarbage = Http::request('GET', '/api/gameversions', ['bearer' => 'garbage']);

		$this->assertSame(200, $plain['status'], $plain['body']);
		$this->assertNotEmpty($plain['json']['gameversions']);
		$this->assertSame(200, $withGarbage['status']);
		$this->assertSame($plain['body'], $withGarbage['body']);
	}
}
