<?php

require_once __DIR__.'/support/api-token-support.php';

/** lib/api-tokens.php, in-process against the database. */
final class ApiTokenLibraryTest extends ApiTokenTestCase
{
	private $owner;
	private $mod;

	protected function setUp() : void
	{
		parent::setUp();
		$this->owner = Fx::user();
		$this->mod = Fx::mod($this->owner['userId']);
	}

	private function create($name, $days, $userId = null, $mod = null) : array
	{
		$userId ??= $this->owner['userId'];
		$mod ??= $this->mod;
		return Fx::actingAs($userId, fn() => createModApiToken($mod, Fx::userRow($userId), $name, $days));
	}

	private function tokenCount($modId = null, $userId = null) : int
	{
		return $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE modId = ? AND userId = ?', [$modId ?? $this->mod['modId'], $userId ?? $this->owner['userId']]);
	}

	//
	// token format and storage
	//

	/** @test */
	public function createdTokenHasPrefixAnd32BytesOfBase64UrlEntropy() : void
	{
		$result = $this->create('ci', 30);

		$this->assertTrue($result['ok']);
		$this->assertMatchesRegularExpression('/^vsmoddb_[A-Za-z0-9_-]{43}$/', $result['token']);
		$raw = base64_decode(strtr(substr($result['token'], 8), '-_', '+/'), true);
		$this->assertSame(32, strlen($raw));
	}

	/** @test */
	public function everyCreatedTokenIsDifferent() : void
	{
		$tokens = [];
		for($i = 0; $i < API_TOKEN_MAX_ACTIVE_PER_USER_PER_MOD; $i++)  $tokens[] = $this->create("t$i", 1)['token'];

		$this->assertCount(API_TOKEN_MAX_ACTIVE_PER_USER_PER_MOD, array_unique($tokens));
	}

	/** @test */
	public function onlyTheSha256HashOfTheTokenIsStored() : void
	{
		$result = $this->create('ci', 30);

		$row = $this->db()->getRow('SELECT * FROM modApiTokens WHERE tokenId = ?', [$result['tokenId']]);
		$this->assertNotEmpty($row);
		$this->assertSame(hash('sha256', $result['token'], true), $row['tokenHash']);
		$secretPart = substr($result['token'], 8);
		foreach($row as $column => $value) {
			$this->assertStringNotContainsString($secretPart, (string)$value, "Column $column must not contain the plaintext token.");
		}
		$this->assertSame(intval($this->mod['modId']), intval($row['modId']));
		$this->assertSame($this->owner['userId'], intval($row['userId']));
		$this->assertSame('ci', $row['name']);
		$this->assertNull($row['lastUsed']);
	}

	/** @test */
	public function plaintextTokenDoesNotEndUpInTheAuditLog() : void
	{
		$result = $this->create('audit-me', 30);

		$rows = $this->db()->getAll('SELECT * FROM auditLogs WHERE initiatorUserId = ?', [$this->owner['userId']]);
		$this->assertCount(1, $rows);
		$this->assertSame(AUDIT_LOG_KIND_MOD_API_TOKEN_CREATE, intval($rows[0]['kind']));
		$this->assertSame($this->mod['modId'], intval($rows[0]['referenceId']));
		$this->assertSame('audit-me', $rows[0]['info']);
		$this->assertSame(0, intval($rows[0]['flags']));
		$this->assertStringNotContainsString(substr($result['token'], 8), implode('|', $rows[0]));
	}

	/** @test */
	public function expiresIsReturnedAndStoredLifetimeDaysFromNow() : void
	{
		$result = $this->create('ci', 7);

		$row = $this->db()->getRow('SELECT expires, TIMESTAMPDIFF(SECOND, NOW(), expires) AS secondsLeft FROM modApiTokens WHERE tokenId = ?', [$result['tokenId']]);
		$this->assertSame($row['expires'], $result['expires']);
		$this->assertEqualsWithDelta(7 * 86400, intval($row['secondsLeft']), 5);
	}

	//
	// lookup
	//

	/** @test */
	public function findReturnsTheTokenRowForAValidToken() : void
	{
		$result = $this->create('find-me', 30);

		$this->assertSame(
			['tokenId' => $result['tokenId'], 'modId' => $this->mod['modId'], 'userId' => $this->owner['userId'], 'name' => 'find-me'],
			findValidModApiToken($result['token'])
		);
	}

	/** @test */
	public function findRejectsATokenWithOneCharacterChanged() : void
	{
		$token = $this->create('ci', 30)['token'];
		$last = substr($token, -1);
		$tampered = substr($token, 0, -1).($last === 'A' ? 'B' : 'A');

		$this->assertNotNull(findValidModApiToken($token));
		$this->assertNull(findValidModApiToken($tampered));
	}

	/** @test */
	public function findRejectsAWrongPrefixEmptyAndNonStringInput() : void
	{
		$token = $this->create('ci', 30)['token'];

		$this->assertNotNull(findValidModApiToken($token));
		$this->assertNull(findValidModApiToken('vsmoddx_'.substr($token, 8)));
		$this->assertNull(findValidModApiToken(substr($token, 8)));
		$this->assertNull(findValidModApiToken(''));
		$this->assertNull(findValidModApiToken(null));
		$this->assertNull(findValidModApiToken($token.'A'));
	}

	/** @test */
	public function findRejectsARevokedToken() : void
	{
		$result = $this->create('ci', 30);
		$this->assertNotNull(findValidModApiToken($result['token']));

		Fx::actingAs($this->owner['userId'], fn() => revokeModApiToken($result['tokenId'], $this->mod['modId']));

		$this->assertNull(findValidModApiToken($result['token']));
	}

	/** @test */
	public function findAcceptsATokenThatExpiresInAMinute() : void
	{
		$raw = Fx::rawToken($this->mod['modId'], $this->owner['userId'], 'NOW() + INTERVAL 1 MINUTE');

		$this->assertSame($raw['tokenId'], findValidModApiToken($raw['token'])['tokenId']);
	}

	/** @test */
	public function findRejectsATokenThatExpiresRightNow() : void
	{
		// expires is compared with `>`, a token is dead in the second it expires.
		$raw = Fx::rawToken($this->mod['modId'], $this->owner['userId'], 'NOW()');

		$this->assertNull(findValidModApiToken($raw['token']));
	}

	/** @test */
	public function findRejectsAnExpiredToken() : void
	{
		$raw = Fx::rawToken($this->mod['modId'], $this->owner['userId'], 'NOW() - INTERVAL 1 DAY');

		$this->assertNull(findValidModApiToken($raw['token']));
	}

	/** @test */
	public function touchSetsLastUsedButFindDoesNot() : void
	{
		$result = $this->create('ci', 30);

		findValidModApiToken($result['token']);
		$this->assertNull($this->db()->getOne('SELECT lastUsed FROM modApiTokens WHERE tokenId = ?', [$result['tokenId']]));

		touchModApiToken($result['tokenId']);
		$age = $this->db()->getOne('SELECT TIMESTAMPDIFF(SECOND, lastUsed, NOW()) FROM modApiTokens WHERE tokenId = ?', [$result['tokenId']]);
		$this->assertNotNull($age, 'lastUsed must be set');
		$this->assertLessThanOrEqual(2, abs(intval($age)));
	}

	//
	// bearer header parsing
	//

	/** @test */
	public function bearerTokenIsReadFromTheAuthorizationHeader() : void
	{
		$backup = $_SERVER;
		try {
			unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
			$this->assertNull(getBearerTokenFromRequest());

			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer  vsmoddb_abc ';
			$this->assertSame('vsmoddb_abc', getBearerTokenFromRequest());

			$_SERVER['HTTP_AUTHORIZATION'] = 'bearer x';
			$this->assertSame('x', getBearerTokenFromRequest());

			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer';
			$this->assertSame('', getBearerTokenFromRequest());

			$_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
			$this->assertNull(getBearerTokenFromRequest());

			unset($_SERVER['HTTP_AUTHORIZATION']);
			$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer fromredirect';
			$this->assertSame('fromredirect', getBearerTokenFromRequest());
		}
		finally {
			$_SERVER = $backup;
		}
	}

	//
	// name validation
	//

	/**
	 * @test
	 * @dataProvider invalidNames
	 */
	public function createRejectsInvalidNamesWithoutCreatingAnything($name, $expectedError) : void
	{
		$result = $this->create($name, 30);

		$this->assertSame(['ok' => false, 'status' => 400, 'error' => $expectedError], $result);
		$this->assertSame(0, $this->tokenCount());
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE initiatorUserId = ?', [$this->owner['userId']]));
	}

	public function invalidNames() : array
	{
		$length = 'Token name must be between 1 and 64 characters long.';
		$encoding = 'Token name must be valid UTF-8 and must not contain control characters.';
		return [
			'empty'               => ['', $length],
			'only whitespace'     => ["  \t ", $length],
			'65 ascii'            => [str_repeat('a', 65), $length],
			'65 multibyte'        => [str_repeat('ä', 65), $length],
			'invalid utf-8'       => ["ci\xff\xfe", $encoding],
			'bell character'      => ["ci\x07token", $encoding],
			'newline in the name' => ["ci\ntoken", $encoding],
			'nul byte'            => ["ci\0token", $encoding],
		];
	}

	/**
	 * @test
	 * @dataProvider validNames
	 */
	public function createAcceptsValidNamesAndStoresThemTrimmed($name, $stored) : void
	{
		$result = $this->create($name, 30);

		$this->assertTrue($result['ok'], json_encode($result));
		$this->assertSame($stored, $this->db()->getOne('SELECT name FROM modApiTokens WHERE tokenId = ?', [$result['tokenId']]));
	}

	public function validNames() : array
	{
		return [
			'single char'        => ['x', 'x'],
			'64 ascii'           => [str_repeat('a', 64), str_repeat('a', 64)],
			'64 multibyte'       => [str_repeat('ä', 64), str_repeat('ä', 64)],
			'64 emoji (4 bytes)' => [str_repeat('🚀', 64), str_repeat('🚀', 64)],
			'surrounding spaces' => ['  GitHub Actions  ', 'GitHub Actions'],
		];
	}

	//
	// lifetime validation
	//

	/**
	 * @test
	 * @dataProvider invalidLifetimes
	 */
	public function createRejectsInvalidLifetimes($days) : void
	{
		$result = $this->create('ci', $days);

		$this->assertSame(['ok' => false, 'status' => 400, 'error' => 'Token lifetime must be between 1 and 365 days.'], $result);
		$this->assertSame(0, $this->tokenCount());
	}

	public function invalidLifetimes() : array
	{
		return ['zero' => [0], 'negative' => [-1], '366' => [366], 'fraction' => ['1.5'], 'float' => [1.5], 'text' => ['abc'], 'empty' => [''], 'sql' => ['1 DAY + INTERVAL 10 YEAR'], 'null' => [null]];
	}

	/**
	 * @test
	 * @dataProvider validLifetimes
	 */
	public function createAcceptsLifetimesFromOneTo365Days($days) : void
	{
		$result = $this->create('ci', $days);

		$this->assertTrue($result['ok'], json_encode($result));
		$diffDays = $this->countRows('SELECT DATEDIFF(expires, created) FROM modApiTokens WHERE tokenId = ?', [$result['tokenId']]);
		$this->assertSame(intval($days), $diffDays);
	}

	public function validLifetimes() : array
	{
		return ['one' => [1], 'max' => [365], 'numeric string' => ['90']];
	}

	//
	// cap
	//

	/** @test */
	public function sixthActiveTokenOfAUserForAModIsRefused() : void
	{
		for($i = 0; $i < 5; $i++)  $this->assertTrue($this->create("t$i", 30)['ok']);

		$result = $this->create('one too many', 30);

		$this->assertSame(['ok' => false, 'status' => 409, 'error' => 'You already have 5 active api tokens for this mod. Revoke one first.'], $result);
		$this->assertSame(5, $this->tokenCount());
	}

	/** @test */
	public function expiredTokensDoNotCountTowardsTheCap() : void
	{
		for($i = 0; $i < 4; $i++)  $this->assertTrue($this->create("t$i", 30)['ok']);
		for($i = 0; $i < 3; $i++)  Fx::rawToken($this->mod['modId'], $this->owner['userId'], 'NOW() - INTERVAL 1 HOUR');

		$this->assertTrue($this->create('fifth active', 30)['ok']);
		$this->assertSame(409, $this->create('sixth active', 30)['status']);
	}

	/** @test */
	public function capIsPerUser() : void
	{
		$editor = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		for($i = 0; $i < 5; $i++)  $this->create("t$i", 30);
		$this->assertSame(409, $this->create('owner sixth', 30)['status']);

		$this->assertTrue($this->create('editor first', 30, $editor['userId'])['ok']);
	}

	/** @test */
	public function capIsPerMod() : void
	{
		$otherMod = Fx::mod($this->owner['userId']);
		for($i = 0; $i < 5; $i++)  $this->create("t$i", 30);
		$this->assertSame(409, $this->create('sixth', 30)['status']);

		$this->assertTrue($this->create('other mod', 30, null, $otherMod)['ok']);
	}

	//
	// permissions
	//

	/** @test */
	public function isModOwnerOrEditorFollowsOwnershipAndTheEditFlagOnly() : void
	{
		$editor = Fx::user();
		$member = Fx::user();
		$moderator = Fx::user(['role' => 'moderator']);
		$admin = Fx::user(['role' => 'admin']);
		$stranger = Fx::user();
		$editorElsewhere = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		Fx::teamMember($this->mod['modId'], $member['userId'], false);
		Fx::teamMember(Fx::mod($this->owner['userId'])['modId'], $editorElsewhere['userId'], true);

		$this->assertTrue(isModOwnerOrEditor($this->mod, Fx::userRow($this->owner['userId'])));
		$this->assertTrue(isModOwnerOrEditor($this->mod, Fx::userRow($editor['userId'])));
		$this->assertFalse(isModOwnerOrEditor($this->mod, Fx::userRow($member['userId'])));
		$this->assertFalse(isModOwnerOrEditor($this->mod, Fx::userRow($moderator['userId'])));
		$this->assertFalse(isModOwnerOrEditor($this->mod, Fx::userRow($admin['userId'])));
		$this->assertFalse(isModOwnerOrEditor($this->mod, Fx::userRow($stranger['userId'])));
		$this->assertFalse(isModOwnerOrEditor($this->mod, Fx::userRow($editorElsewhere['userId'])));
		$this->assertFalse(isModOwnerOrEditor($this->mod, null));
	}

	/** @test */
	public function createRefusesUsersThatAreNotOwnerOrEditorIncludingModerators() : void
	{
		$member = Fx::user();
		$moderator = Fx::user(['role' => 'moderator']);
		Fx::teamMember($this->mod['modId'], $member['userId'], false);

		foreach([$member, $moderator] as $who) {
			$result = $this->create('nope', 30, $who['userId']);
			$this->assertSame(['ok' => false, 'status' => 403, 'error' => 'Only the mod owner and team members with edit permissions may create api tokens.'], $result);
			$this->assertSame(0, $this->tokenCount(null, $who['userId']));
		}

		$editor = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		$this->assertTrue($this->create('yes', 30, $editor['userId'])['ok']);
	}

	//
	// listing
	//

	/** @test */
	public function listContainsMetadataOnlyAndNeverTheHashOrToken() : void
	{
		$result = $this->create('listed', 30);
		touchModApiToken($result['tokenId']);

		$list = listModApiTokens($this->mod['modId']);

		$this->assertCount(1, $list);
		$this->assertSame(['tokenId', 'modId', 'userId', 'creatorName', 'name', 'created', 'expires', 'lastUsed', 'isExpired'], array_keys($list[0]));
		$this->assertSame($result['tokenId'], $list[0]['tokenId']);
		$this->assertSame($this->mod['modId'], $list[0]['modId']);
		$this->assertSame($this->owner['userId'], $list[0]['userId']);
		$this->assertSame($this->owner['name'], $list[0]['creatorName']);
		$this->assertSame('listed', $list[0]['name']);
		$this->assertSame($result['expires'], $list[0]['expires']);
		$this->assertNotNull($list[0]['lastUsed']);
		$this->assertFalse($list[0]['isExpired']);
		$serialized = json_encode($list);
		$this->assertStringNotContainsString(substr($result['token'], 8), $serialized);
		$this->assertStringNotContainsString(bin2hex(hash('sha256', $result['token'], true)), $serialized);
	}

	/** @test */
	public function listIncludesExpiredTokensFlaggedAsExpiredNewestFirst() : void
	{
		$expired = Fx::rawToken($this->mod['modId'], $this->owner['userId'], 'NOW() - INTERVAL 1 HOUR', 'old');
		$fresh = $this->create('fresh', 30);

		$list = listModApiTokens($this->mod['modId']);

		$this->assertSame([$fresh['tokenId'], $expired['tokenId']], array_column($list, 'tokenId'));
		$this->assertSame([false, true], array_column($list, 'isExpired'));
	}

	/** @test */
	public function listOnlyUserIdFiltersByCreatorAndListIsPerMod() : void
	{
		$editor = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		$ownerToken = $this->create('owner', 30);
		$editorToken = $this->create('editor', 30, $editor['userId']);
		$otherModToken = $this->create('other mod', 30, null, Fx::mod($this->owner['userId']));

		$all = array_column(listModApiTokens($this->mod['modId']), 'tokenId');
		sort($all);
		$expected = [$ownerToken['tokenId'], $editorToken['tokenId']];
		sort($expected);
		$this->assertSame($expected, $all);
		$this->assertNotContains($otherModToken['tokenId'], $all);

		$this->assertSame([$editorToken['tokenId']], array_column(listModApiTokens($this->mod['modId'], $editor['userId']), 'tokenId'));
		$this->assertSame([$ownerToken['tokenId']], array_column(listModApiTokens($this->mod['modId'], $this->owner['userId']), 'tokenId'));
	}

	//
	// revoking
	//

	/** @test */
	public function revokeDeletesTheTokenAndLogsIt() : void
	{
		$result = $this->create('to revoke', 30);
		$keep = $this->create('to keep', 30);

		$ok = Fx::actingAs($this->owner['userId'], fn() => revokeModApiToken($result['tokenId'], $this->mod['modId']));

		$this->assertTrue($ok);
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$result['tokenId']]));
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$keep['tokenId']]));
		$log = $this->db()->getRow('SELECT * FROM auditLogs WHERE kind = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $this->owner['userId']]);
		$this->assertSame($this->mod['modId'], intval($log['referenceId']));
		$this->assertSame('to revoke', $log['info']);
		$this->assertSame(0, intval($log['flags']));
	}

	/** @test */
	public function revokeWithTheWrongModOrAnUnknownIdDoesNothing() : void
	{
		$result = $this->create('ci', 30);
		$otherMod = Fx::mod($this->owner['userId']);

		Fx::actingAs($this->owner['userId'], function() use($result, $otherMod) {
			$this->assertFalse(revokeModApiToken($result['tokenId'], $otherMod['modId']));
			$this->assertFalse(revokeModApiToken($result['tokenId'] + 100000, $this->mod['modId']));
		});

		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$result['tokenId']]));
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE kind = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $this->owner['userId']]));
	}

	/** @test */
	public function revokeByAModeratorOutsideTheTeamIsFlaggedAsModAction() : void
	{
		$moderator = Fx::user(['role' => 'moderator']);
		$result = $this->create('ci', 30);

		$this->assertTrue(Fx::actingAs($moderator['userId'], fn() => revokeModApiToken($result['tokenId'], $this->mod['modId'])));

		$flags = $this->db()->getOne('SELECT flags FROM auditLogs WHERE kind = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $moderator['userId']]);
		$this->assertSame(AUDIT_LOG_FLAG_MODACTION, intval($flags));
	}

	/** @test */
	public function revokeByAModeratorWhoIsAnEditorOfTheModIsNotFlaggedAsModAction() : void
	{
		$moderator = Fx::user(['role' => 'moderator']);
		Fx::teamMember($this->mod['modId'], $moderator['userId'], true);
		$result = $this->create('ci', 30);

		$this->assertTrue(Fx::actingAs($moderator['userId'], fn() => revokeModApiToken($result['tokenId'], $this->mod['modId'])));

		$flags = $this->db()->getOne('SELECT flags FROM auditLogs WHERE kind = ? AND initiatorUserId = ?', [AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $moderator['userId']]);
		$this->assertSame('0', (string)$flags);
	}

	/** @test */
	public function revokeAllTokensOfAUserOnlyTouchesThatUserOnThatMod() : void
	{
		$editor = Fx::user();
		$otherMod = Fx::mod($this->owner['userId']);
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		Fx::teamMember($otherMod['modId'], $editor['userId'], true);
		$this->create('e1', 30, $editor['userId']);
		$this->create('e2', 30, $editor['userId']);
		$expired = Fx::rawToken($this->mod['modId'], $editor['userId'], 'NOW() - INTERVAL 1 DAY', 'e-expired');
		$editorOtherMod = $this->create('e other mod', 30, $editor['userId'], $otherMod);
		$ownerToken = $this->create('owner', 30);

		$revoked = Fx::actingAs($this->owner['userId'], fn() => revokeModApiTokensOfUser($this->mod['modId'], $editor['userId'], AUDIT_LOG_FLAG_MODACTION));

		$this->assertSame(3, $revoked);
		$this->assertSame(0, $this->tokenCount(null, $editor['userId']));
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$expired['tokenId']]));
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$editorOtherMod['tokenId']]));
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$ownerToken['tokenId']]));

		$logs = $this->db()->getAll('SELECT referenceId, info, flags FROM auditLogs WHERE kind = ? AND initiatorUserId = ? ORDER BY info', [AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $this->owner['userId']]);
		$this->assertSame([
			['referenceId' => $this->mod['modId'], 'info' => 'e-expired', 'flags' => AUDIT_LOG_FLAG_MODACTION],
			['referenceId' => $this->mod['modId'], 'info' => 'e1', 'flags' => AUDIT_LOG_FLAG_MODACTION],
			['referenceId' => $this->mod['modId'], 'info' => 'e2', 'flags' => AUDIT_LOG_FLAG_MODACTION],
		], $logs);
	}

	/** @test */
	public function revokeAllTokensOfAUserWithoutTokensReturnsZeroAndLogsNothing() : void
	{
		$this->create('owner', 30);
		$stranger = Fx::user();

		$this->assertSame(0, Fx::actingAs($this->owner['userId'], fn() => revokeModApiTokensOfUser($this->mod['modId'], $stranger['userId'])));
		$this->assertSame(1, $this->tokenCount());
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE kind = ?  AND initiatorUserId = ?', [AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $this->owner['userId']]));
	}

	//
	// rate limit bookkeeping
	//

	/** @test */
	public function countRecentApiReleasesOnlyCountsFlaggedReleasesOfTheModWithinTheLastHour() : void
	{
		$otherMod = Fx::mod($this->owner['userId']);
		$uid = $this->owner['userId'];
		$id = Fx::identifier();
		Fx::seedRelease($this->mod['modId'], $uid, $id, '1.0.1', AUDIT_LOG_FLAG_VIA_API_TOKEN, 1);
		Fx::seedRelease($this->mod['modId'], $uid, $id, '1.0.2', AUDIT_LOG_FLAG_VIA_API_TOKEN | AUDIT_LOG_FLAG_MODACTION, 59);
		Fx::seedRelease($this->mod['modId'], $uid, $id, '1.0.3', 0, 2);                                // web release
		Fx::seedRelease($this->mod['modId'], $uid, $id, '1.0.4', AUDIT_LOG_FLAG_MODACTION, 2);        // other flag only
		Fx::seedRelease($this->mod['modId'], $uid, $id, '1.0.5', AUDIT_LOG_FLAG_VIA_API_TOKEN, 61);   // too old
		Fx::seedRelease($otherMod['modId'], $uid, Fx::identifier(), '1.0.0', AUDIT_LOG_FLAG_VIA_API_TOKEN, 1); // other mod
		// A non-release audit row that happens to reference one of the release ids and carries the same bit must not count either.
		$releaseId = Fx::seedRelease($this->mod['modId'], $uid, $id, '1.0.6', 0, 1)['releaseId'];
		$this->db()->execute('INSERT INTO auditLogs (kind, flags, referenceId, initiatorUserId) VALUES (?, ?, ?, ?)', [AUDIT_LOG_KIND_MOD_CHANGE_NAME, AUDIT_LOG_FLAG_VIA_API_TOKEN, $releaseId, $uid]);

		$this->assertSame(2, countRecentApiReleases($this->mod['modId']));
		$this->assertSame(1, countRecentApiReleases($otherMod['modId']));
	}

	/** @test */
	public function secondsUntilApiReleaseAllowedWaitsForTheLimitThNewestReleaseToLeaveTheWindow() : void
	{
		// 11 flagged releases, 5, 10, ..., 55 minutes ago. With a limit of 10 the 10th newest (50 minutes ago) has to leave the window: ~600s.
		$id = Fx::identifier();
		for($i = 1; $i <= 11; $i++) {
			Fx::seedRelease($this->mod['modId'], $this->owner['userId'], $id, "1.0.$i", AUDIT_LOG_FLAG_VIA_API_TOKEN, 5 * $i);
		}
		// Unflagged and newer releases must not shift the window.
		Fx::seedRelease($this->mod['modId'], $this->owner['userId'], $id, '2.0.0', 0, 0);

		$seconds = secondsUntilApiReleaseAllowed($this->mod['modId']);

		$this->assertGreaterThanOrEqual(590, $seconds);
		$this->assertLessThanOrEqual(600, $seconds);
	}

	/** @test */
	public function secondsUntilApiReleaseAllowedIsAtLeastOne() : void
	{
		$this->assertSame(1, secondsUntilApiReleaseAllowed($this->mod['modId']));
	}

	//
	// cascades
	//

	/** @test */
	public function deletingTheModDeletesItsTokens() : void
	{
		$result = $this->create('ci', 30);
		$otherMod = Fx::mod($this->owner['userId']);
		$other = $this->create('ci', 30, null, $otherMod);

		$mod = $this->db()->getRow('SELECT m.modId, m.assetId, a.name FROM mods m JOIN assets a ON a.assetId = m.assetId WHERE m.modId = ?', [$this->mod['modId']]);
		$this->assertTrue(Fx::actingAs($this->owner['userId'], fn() => deleteMod($mod)));

		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$result['tokenId']]));
		$this->assertNull(findValidModApiToken($result['token']));
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$other['tokenId']]));
	}

	/** @test */
	public function deletingTheCreatorDeletesTheirTokens() : void
	{
		$editor = Fx::user();
		Fx::teamMember($this->mod['modId'], $editor['userId'], true);
		$editorToken = $this->create('editor', 30, $editor['userId']);
		$ownerToken = $this->create('owner', 30);
		$this->db()->execute('DELETE FROM auditLogs WHERE initiatorUserId = ?', [$editor['userId']]);

		$this->db()->execute('DELETE FROM users WHERE userId = ?', [$editor['userId']]);

		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$editorToken['tokenId']]));
		$this->assertSame(1, $this->countRows('SELECT COUNT(*) FROM modApiTokens WHERE tokenId = ?', [$ownerToken['tokenId']]));
	}
}
