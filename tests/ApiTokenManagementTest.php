<?php

require_once __DIR__.'/support/api-token-support.php';

/** GET / POST /api/v2/mods/{modid}/api-tokens and DELETE /api/v2/mods/{modid}/api-tokens/{tokenid}, session authenticated, over http. */
final class ApiTokenManagementTest extends ApiTokenTestCase
{
	const BAD_AT = 'Invalid action token. Need to log in again?';
	const NOT_FOUND = 'Unknown api token.';
	const NO_CREATE = 'Only the mod owner and team members with edit permissions may create api tokens.';

	/** @var array<string, array> */
	private $users;
	private $mod;
	private $ownerToken;
	private $editorToken;

	protected function setUp() : void
	{
		parent::setUp();
		$this->users = [
			'owner'     => Fx::user(),
			'editor'    => Fx::user(),
			'member'    => Fx::user(),
			'stranger'  => Fx::user(),
			'moderator' => Fx::user(['role' => 'moderator']),
			'admin'     => Fx::user(['role' => 'admin']),
		];
		$this->mod = Fx::mod($this->users['owner']['userId']);
		Fx::teamMember($this->mod['modId'], $this->users['editor']['userId'], true);
		Fx::teamMember($this->mod['modId'], $this->users['member']['userId'], false);
		$this->ownerToken = Fx::token($this->mod, $this->users['owner']['userId'], 'owner token');
		$this->editorToken = Fx::token($this->mod, $this->users['editor']['userId'], 'editor token');
		// Only the fixture creations so far, the tests count audit rows from here on.
		$this->db()->execute('DELETE FROM auditLogs WHERE initiatorUserId IN (?, ?)', [$this->users['owner']['userId'], $this->users['editor']['userId']]);
	}

	/** 'bannedOwner' is the owner with an active ban. */
	private function as($role) : array
	{
		if($role === 'bannedOwner') {
			$this->db()->execute('UPDATE users SET bannedUntil = NOW() + INTERVAL 1 DAY WHERE userId = ?', [$this->users['owner']['userId']]);
			return $this->users['owner'];
		}
		return $this->users[$role];
	}

	private function listAs($who, $at = null, $modId = null) : array
	{
		$modId ??= $this->mod['modId'];
		return Http::request('GET', "/api/v2/mods/$modId/api-tokens?at=".urlencode($at ?? $who['at']), ['session' => $who]);
	}

	private function createAs($who, $fields = [], $modId = null) : array
	{
		$modId ??= $this->mod['modId'];
		return Http::request('POST', "/api/v2/mods/$modId/api-tokens", ['session' => $who, 'form' => $fields + ['name' => 'CI', 'lifetimeDays' => '30', 'at' => $who['at']]]);
	}

	private function revokeAs($who, $tokenId, $modId = null, $form = null) : array
	{
		$modId ??= $this->mod['modId'];
		return Http::request('DELETE', "/api/v2/mods/$modId/api-tokens/$tokenId", ['session' => $who, 'form' => $form ?? ['at' => $who['at']]]);
	}

	private function tokenExists($tokenId) : bool
	{
		return $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$tokenId]) === 1;
	}

	private function tokensOfMod() : int
	{
		return $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE modId = ?', [$this->mod['modId']]);
	}

	private function revokeLogs() : array
	{
		return $this->db()->getAll('SELECT initiatorUserId, referenceId, info, flags FROM auditLogs WHERE kind = ? AND referenceId = ?', [AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $this->mod['modId']]);
	}

	//
	// permission matrix
	//

	/**
	 * @test
	 * @dataProvider listMatrix
	 */
	public function listPermissions($role, $expectedStatus, $expectedTokens) : void
	{
		$response = $this->listAs($this->as($role));

		$this->assertSame($expectedStatus, $response['status'], $response['body']);
		if($expectedStatus === 200) {
			$expectedIds = array_map(fn($t) => $this->{$t}['tokenId'], $expectedTokens);
			$this->assertSame($expectedIds, array_column($response['json'], 'tokenId'));
		}
		else {
			$this->assertSame(['error' => 'You may not view the api tokens of this mod.'], $response['json']);
		}
	}

	public function listMatrix() : array
	{
		return [
			'owner sees all'                 => ['owner', 200, ['editorToken', 'ownerToken']],
			'editor sees own only'           => ['editor', 200, ['editorToken']],
			'member without edit is refused' => ['member', 403, []],
			'stranger is refused'            => ['stranger', 403, []],
			'moderator sees all'             => ['moderator', 200, ['editorToken', 'ownerToken']],
			'admin sees all'                 => ['admin', 200, ['editorToken', 'ownerToken']],
			'banned owner sees own only'     => ['bannedOwner', 200, ['ownerToken']],
		];
	}

	/**
	 * @test
	 * @dataProvider createMatrix
	 */
	public function createPermissions($role, $expectedStatus, $expectedError) : void
	{
		$who = $this->as($role);
		$before = $this->tokensOfMod();

		$response = $this->createAs($who, ['name' => 'matrix']);

		$this->assertSame($expectedStatus, $response['status'], $response['body']);
		if($expectedStatus === 201) {
			$this->assertSame($before + 1, $this->tokensOfMod());
			$this->assertSame($who['userId'], intval($this->db()->getOne('SELECT userId FROM modApiTokens WHERE tokenId = ?', [$response['json']['tokenId']])));
		}
		else {
			$this->assertSame(['error' => $expectedError], $response['json']);
			$this->assertSame($before, $this->tokensOfMod());
			$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE kind = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_MOD_API_TOKEN_CREATE, $who['userId']]));
		}
	}

	public function createMatrix() : array
	{
		return [
			'owner'                          => ['owner', 201, null],
			'editor'                         => ['editor', 201, null],
			'member without edit is refused' => ['member', 403, self::NO_CREATE],
			'stranger is refused'            => ['stranger', 403, self::NO_CREATE],
			'moderator is refused'           => ['moderator', 403, self::NO_CREATE],
			'admin is refused'               => ['admin', 403, self::NO_CREATE],
			'banned owner is refused'        => ['bannedOwner', 403, 'You are currently banned.'],
		];
	}

	/**
	 * @test
	 * @dataProvider everyRole
	 */
	public function everyoneMayRevokeTheirOwnToken($role) : void
	{
		$who = $this->as($role);
		$own = $role === 'owner' || $role === 'bannedOwner' ? $this->ownerToken
			: ($role === 'editor' ? $this->editorToken : Fx::rawToken($this->mod['modId'], $who['userId'], 'NOW() + INTERVAL 1 DAY', 'stale'));

		$response = $this->revokeAs($who, $own['tokenId']);

		$this->assertSame(200, $response['status'], $response['body']);
		$this->assertSame([], $response['json']);
		$this->assertFalse($this->tokenExists($own['tokenId']));
		$this->assertCount(1, $this->revokeLogs());
	}

	public function everyRole() : array
	{
		return ['owner' => ['owner'], 'editor' => ['editor'], 'member' => ['member'], 'stranger' => ['stranger'], 'moderator' => ['moderator'], 'admin' => ['admin'], 'banned owner' => ['bannedOwner']];
	}

	/**
	 * @test
	 * @dataProvider revokeOtherMatrix
	 */
	public function revokingSomeoneElsesTokenPermissions($role, $target, $expectedStatus, $expectedFlags) : void
	{
		$who = $this->as($role);
		$token = $this->{$target};

		$response = $this->revokeAs($who, $token['tokenId']);

		$this->assertSame($expectedStatus, $response['status'], $response['body']);
		if($expectedStatus === 200) {
			$this->assertFalse($this->tokenExists($token['tokenId']));
			$this->assertSame([['initiatorUserId' => $who['userId'], 'referenceId' => $this->mod['modId'], 'info' => $target === 'ownerToken' ? 'owner token' : 'editor token', 'flags' => $expectedFlags]], $this->revokeLogs());
		}
		else {
			$this->assertSame(['error' => self::NOT_FOUND], $response['json']);
			$this->assertTrue($this->tokenExists($token['tokenId']));
			$this->assertSame([], $this->revokeLogs());
		}
	}

	public function revokeOtherMatrix() : array
	{
		return [
			'owner revokes editor token'           => ['owner', 'editorToken', 200, 0],
			'editor cannot revoke owner token'     => ['editor', 'ownerToken', 404, null],
			'member cannot revoke editor token'    => ['member', 'editorToken', 404, null],
			'stranger cannot revoke owner token'   => ['stranger', 'ownerToken', 404, null],
			'moderator revokes owner token'        => ['moderator', 'ownerToken', 200, AUDIT_LOG_FLAG_MODACTION],
			'admin revokes editor token'           => ['admin', 'editorToken', 200, AUDIT_LOG_FLAG_MODACTION],
			'banned owner cannot revoke editor'    => ['bannedOwner', 'editorToken', 404, null],
		];
	}

	//
	// revoke details
	//

	/** @test */
	public function notYoursAndNonexistentGetIdenticalResponses() : void
	{
		$editor = $this->users['editor'];
		$otherMod = Fx::mod($this->users['owner']['userId']);
		$otherModToken = Fx::token($otherMod, $this->users['owner']['userId']);
		$nonexistent = intval($this->db()->getOne('SELECT MAX(tokenId) FROM modApiTokens')) + 1000;

		$notYours = $this->revokeAs($editor, $this->ownerToken['tokenId']);
		$missing = $this->revokeAs($editor, $nonexistent);
		$otherModsToken = $this->revokeAs($this->users['owner'], $otherModToken['tokenId']);

		$this->assertSame(404, $notYours['status']);
		$this->assertSame(['error' => self::NOT_FOUND], $notYours['json']);
		$this->assertSame([$notYours['status'], $notYours['body']], [$missing['status'], $missing['body']]);
		$this->assertSame([$notYours['status'], $notYours['body']], [$otherModsToken['status'], $otherModsToken['body']]);
		$this->assertTrue($this->tokenExists($this->ownerToken['tokenId']));
		$this->assertTrue($this->tokenExists($otherModToken['tokenId']));
	}

	/** @test */
	public function revokeWithoutOrWithAWrongActionTokenIs403AndKeepsTheToken() : void
	{
		$owner = $this->users['owner'];

		$missing = $this->revokeAs($owner, $this->ownerToken['tokenId'], null, ['x' => '1']);
		$wrong = $this->revokeAs($owner, $this->ownerToken['tokenId'], null, ['at' => $this->users['editor']['at']]);

		$this->assertJsonError($missing, 403, self::BAD_AT);
		$this->assertJsonError($wrong, 403, self::BAD_AT);
		$this->assertTrue($this->tokenExists($this->ownerToken['tokenId']));
		$this->assertSame([], $this->revokeLogs());

		$control = $this->revokeAs($owner, $this->ownerToken['tokenId']);
		$this->assertSame(200, $control['status'], $control['body']);
		$this->assertFalse($this->tokenExists($this->ownerToken['tokenId']));
	}

	/** @test */
	public function revokeAcceptsTheActionTokenInTheQueryString() : void
	{
		$owner = $this->users['owner'];

		$response = Http::request('DELETE', "/api/v2/mods/{$this->mod['modId']}/api-tokens/{$this->ownerToken['tokenId']}?at={$owner['at']}", ['session' => $owner]);

		$this->assertSame(200, $response['status'], $response['body']);
		$this->assertFalse($this->tokenExists($this->ownerToken['tokenId']));
	}

	/** @test */
	public function revokeWithMalformedTokenIdIs400() : void
	{
		foreach(['abc', '0', '-1', '1.5'] as $id) {
			$response = $this->revokeAs($this->users['owner'], $id);
			$this->assertJsonError($response, 400, 'Malformed query param tokenid.', "tokenId '$id'");
		}
		$this->assertSame(2, $this->tokensOfMod());
	}

	/** @test */
	public function revokeWithTrailingSegmentsIs400() : void
	{
		$response = Http::request('DELETE', "/api/v2/mods/{$this->mod['modId']}/api-tokens/{$this->ownerToken['tokenId']}/x", ['session' => $this->users['owner'], 'form' => ['at' => $this->users['owner']['at']]]);

		$this->assertSame(400, $response['status'], $response['body']);
		$this->assertTrue($this->tokenExists($this->ownerToken['tokenId']));
	}

	//
	// create details
	//

	/** @test */
	public function createReturnsAWorkingTokenThatIsShownOnlyOnce() : void
	{
		$owner = $this->users['owner'];

		$response = $this->createAs($owner, ['name' => 'GitHub Actions', 'lifetimeDays' => '90']);

		$this->assertSame(201, $response['status'], $response['body']);
		$this->assertSame(['tokenId', 'token', 'expires'], array_keys($response['json']));
		$plaintext = $response['json']['token'];
		$this->assertMatchesRegularExpression('/^vsmoddb_[A-Za-z0-9_-]{43}$/', $plaintext);
		$secret = substr($plaintext, 8);

		$row = $this->db()->getRow('SELECT * FROM modApiTokens WHERE tokenId = ?', [$response['json']['tokenId']]);
		$this->assertSame('GitHub Actions', $row['name']);
		$this->assertSame($response['json']['expires'], $row['expires']);
		$this->assertSame(90, $this->countRows('SELECT DATEDIFF(expires, created) FROM modApiTokens WHERE tokenId = ?', [$response['json']['tokenId']]));
		$this->assertSame(hash('sha256', $plaintext, true), $row['tokenHash']);
		$this->assertStringNotContainsString($secret, implode('|', $row));

		$list = $this->listAs($owner);
		$this->assertSame(200, $list['status']);
		$this->assertContains($response['json']['tokenId'], array_column($list['json'], 'tokenId'));
		$this->assertStringNotContainsString($secret, $list['body']);
		foreach($list['json'] as $entry) {
			$this->assertArrayNotHasKey('token', $entry);
			$this->assertArrayNotHasKey('tokenHash', $entry);
		}

		$audit = $this->db()->getAll('SELECT * FROM auditLogs WHERE kind = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_MOD_API_TOKEN_CREATE, $owner['userId']]);
		$this->assertCount(1, $audit);
		$this->assertSame($this->mod['modId'], intval($audit[0]['referenceId']));
		$this->assertSame('GitHub Actions', $audit[0]['info']);
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE info LIKE ?', ['%'.$secret.'%']));

		// The token works for exactly what it was made for.
		$upload = $this->upload($this->mod['modId'], $plaintext);
		$this->assertSame(201, $upload['status'], $upload['body']);
	}

	/** @test */
	public function createWithoutOrWithAWrongActionTokenIs403() : void
	{
		$owner = $this->users['owner'];

		$missing = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/api-tokens", ['session' => $owner, 'form' => ['name' => 'x', 'lifetimeDays' => '1']]);
		$wrong = $this->createAs($owner, ['at' => $this->users['editor']['at']]);

		$this->assertJsonError($missing, 403, self::BAD_AT);
		$this->assertJsonError($wrong, 403, self::BAD_AT);
		$this->assertSame(2, $this->tokensOfMod());
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE initiatorUserId = ?', [$owner['userId']]));
	}

	/** @test */
	public function createAtTheCapIs409() : void
	{
		$owner = $this->users['owner'];
		for($i = 1; $i < API_TOKEN_MAX_ACTIVE_PER_USER_PER_MOD; $i++) {
			$this->assertSame(201, $this->createAs($owner, ['name' => "t$i"])['status']);
		}

		$response = $this->createAs($owner, ['name' => 'one too many']);

		$this->assertJsonError($response, 409, 'You already have 5 active api tokens for this mod. Revoke one first.');
		$this->assertSame(5, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE modId = ? AND userId = ?', [$this->mod['modId'], $owner['userId']]));
	}

	/** @test */
	public function createWithABadNameOrLifetimeIs400() : void
	{
		$owner = $this->users['owner'];

		$this->assertJsonError($this->createAs($owner, ['name' => '']), 400, 'Token name must be between 1 and 64 characters long.');
		$this->assertJsonError($this->createAs($owner, ['name' => str_repeat('n', 65)]), 400, 'Token name must be between 1 and 64 characters long.');
		$this->assertJsonError($this->createAs($owner, ['name' => "a\x1bb"]), 400, 'Token name must be valid UTF-8 and must not contain control characters.');
		$this->assertJsonError($this->createAs($owner, ['lifetimeDays' => '0']), 400, 'Token lifetime must be between 1 and 365 days.');
		$this->assertJsonError($this->createAs($owner, ['lifetimeDays' => '366']), 400, 'Token lifetime must be between 1 and 365 days.');
		$this->assertJsonError($this->createAs($owner, ['lifetimeDays' => 'forever']), 400, 'Token lifetime must be between 1 and 365 days.');
		$missingLifetime = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/api-tokens", ['session' => $owner, 'form' => ['name' => 'x', 'at' => $owner['at']]]);
		$this->assertJsonError($missingLifetime, 400, 'Token lifetime must be between 1 and 365 days.');
		$this->assertSame(2, $this->tokensOfMod());

		$this->assertSame(201, $this->createAs($owner, ['name' => str_repeat('n', 64), 'lifetimeDays' => '365'])['status']);
	}

	/** @test */
	public function unknownModIs404ForAllOperations() : void
	{
		$owner = $this->users['owner'];
		$missing = intval($this->db()->getOne('SELECT MAX(modId) FROM mods')) + 1000;

		$this->assertJsonError($this->listAs($owner, null, $missing), 404, 'Unknown modid.');
		$this->assertJsonError($this->createAs($owner, [], $missing), 404, 'Unknown modid.');
		$this->assertJsonError($this->revokeAs($owner, $this->ownerToken['tokenId'], $missing), 404, 'Unknown modid.');
		$this->assertTrue($this->tokenExists($this->ownerToken['tokenId']));
	}

	/** @test */
	public function nonGameModsCanListButNotCreate() : void
	{
		$owner = $this->users['owner'];
		$tool = Fx::mod($owner['userId'], ['category' => CATEGORY_EXTERNAL_TOOL]);

		$create = $this->createAs($owner, [], $tool['modId']);
		$this->assertJsonError($create, 400, 'Uploading releases via the api is currently only supported for game mods.');
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE modId = ?', [$tool['modId']]));

		$list = $this->listAs($owner, null, $tool['modId']);
		$this->assertSame(200, $list['status'], $list['body']);
		$this->assertSame([], $list['json']);
	}

	/** @test */
	public function lockedModsCanStillCreateTokens() : void
	{
		$this->db()->execute('UPDATE assets SET statusId = ? WHERE assetId = ?', [STATUS_LOCKED, $this->mod['assetId']]);

		$response = $this->createAs($this->users['owner']);

		$this->assertSame(201, $response['status'], $response['body']);
	}

	//
	// listing details
	//

	/** @test */
	public function listWithoutOrWithAWrongActionTokenIs403() : void
	{
		$owner = $this->users['owner'];

		$missing = Http::request('GET', "/api/v2/mods/{$this->mod['modId']}/api-tokens", ['session' => $owner]);
		$wrong = $this->listAs($owner, $this->users['editor']['at']);

		$this->assertJsonError($missing, 403, self::BAD_AT);
		$this->assertJsonError($wrong, 403, self::BAD_AT);
		$this->assertSame(200, $this->listAs($owner)['status']);
	}

	/** @test */
	public function listShowsMetadataOfEachToken() : void
	{
		$this->db()->execute('UPDATE modApiTokens SET lastUsed = NOW() - INTERVAL 1 HOUR WHERE tokenId = ?', [$this->editorToken['tokenId']]);

		$response = $this->listAs($this->users['owner']);

		$this->assertSame(200, $response['status']);
		$byId = array_column($response['json'], null, 'tokenId');
		$editorEntry = $byId[$this->editorToken['tokenId']];
		$this->assertSame(['tokenId', 'modId', 'userId', 'creatorName', 'name', 'created', 'expires', 'lastUsed', 'isExpired'], array_keys($editorEntry));
		$this->assertSame($this->users['editor']['userId'], $editorEntry['userId']);
		$this->assertSame($this->users['editor']['name'], $editorEntry['creatorName']);
		$this->assertSame('editor token', $editorEntry['name']);
		$this->assertSame($this->editorToken['expires'], $editorEntry['expires']);
		$this->assertNotNull($editorEntry['lastUsed']);
		$this->assertNull($byId[$this->ownerToken['tokenId']]['lastUsed']);
		$this->assertFalse($editorEntry['isExpired']);
	}

	//
	// protocol details
	//

	/** @test */
	public function responsesAreNeverCached() : void
	{
		$owner = $this->users['owner'];
		$responses = [
			'list'          => $this->listAs($owner),
			'create'        => $this->createAs($owner),
			'revoke'        => $this->revokeAs($owner, $this->ownerToken['tokenId']),
			'refused list'  => $this->listAs($this->users['stranger']),
			'bad at create' => $this->createAs($owner, ['at' => 'nope']),
		];

		$this->assertSame([200, 201, 200, 403, 403], array_values(array_map(fn($r) => $r['status'], $responses)));
		foreach($responses as $what => $response) {
			$this->assertSame('no-store', Http::header($response, 'Cache-Control'), $what);
		}
	}

	/** @test */
	public function wrongMethodsAre405WithAllow() : void
	{
		$owner = $this->users['owner'];

		$collection = Http::request('PUT', "/api/v2/mods/{$this->mod['modId']}/api-tokens", ['session' => $owner, 'form' => ['at' => $owner['at']]]);
		$this->assertSame(405, $collection['status'], $collection['body']);
		$this->assertSame('GET, POST', Http::header($collection, 'Allow'));

		$single = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/api-tokens/{$this->ownerToken['tokenId']}", ['session' => $owner, 'form' => ['at' => $owner['at']]]);
		$this->assertSame(405, $single['status'], $single['body']);
		$this->assertSame('DELETE', Http::header($single, 'Allow'));
		$this->assertTrue($this->tokenExists($this->ownerToken['tokenId']));
	}

	/** @test */
	public function withoutASessionEverythingIs401() : void
	{
		$list = Http::request('GET', "/api/v2/mods/{$this->mod['modId']}/api-tokens");
		$create = Http::request('POST', "/api/v2/mods/{$this->mod['modId']}/api-tokens", ['form' => ['name' => 'x', 'lifetimeDays' => '1']]);
		$revoke = Http::request('DELETE', "/api/v2/mods/{$this->mod['modId']}/api-tokens/{$this->ownerToken['tokenId']}");

		$this->assertSame([401, 401, 401], [$list['status'], $create['status'], $revoke['status']]);
		$this->assertSame(2, $this->tokensOfMod());
	}

	/** @test */
	public function revokeWithAnUnparsableBodyIs400() : void
	{
		$owner = $this->users['owner'];

		$response = Http::request('DELETE', "/api/v2/mods/{$this->mod['modId']}/api-tokens/{$this->ownerToken['tokenId']}?at={$owner['at']}", ['session' => $owner, 'body' => '{"at":"x"}', 'contentType' => 'application/json']);

		$this->assertJsonError($response, 400, 'Malformed request body.');
		$this->assertTrue($this->tokenExists($this->ownerToken['tokenId']));
	}
}
