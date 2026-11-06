<?php

// Shared helpers for the mod api token / release upload tests.
// phpunit loads every .php file under tests/, so this file must only declare things and be loaded with require_once.

require_once dirname(__DIR__).'/prelude.php';
require_once SRC_ROOT.'/lib/edit-release.php';
require_once SRC_ROOT.'/lib/mod.php';

use PHPUnit\Framework\TestCase;

/** Marker for everything the tests create, so leftovers of crashed runs can be found and removed. */
const APITEST_EMAIL_DOMAIN = '@apitest.invalid';

/**
 * A real `php -S` server running index.php inside the same container, shared by all test classes of the process.
 * Gives real multipart parsing, real headers and real exit() semantics of the v2 api.
 */
final class ApiTestServer
{
	/** @var array<string, array{proc:resource, port:int, log:string}> keyed by the extra php -d flags */
	private static $servers = [];

	/** @param string[] $iniFlags extra `-d` settings, each set gets its own server */
	public static function baseUrl($iniFlags = []) : string
	{
		return 'http://127.0.0.1:'.self::ensureStarted($iniFlags)['port'];
	}

	public static function log($iniFlags = []) : string
	{
		$log = self::$servers[implode(' ', $iniFlags)]['log'] ?? '';
		return $log && is_file($log) ? file_get_contents($log) : '';
	}

	private static function ensureStarted($iniFlags) : array
	{
		$key = implode(' ', $iniFlags);
		if(isset(self::$servers[$key])) {
			$status = proc_get_status(self::$servers[$key]['proc']);
			if($status['running']) return self::$servers[$key];
			throw new RuntimeException("The test http server died. Log:\n".self::log($iniFlags));
		}

		// Let the kernel pick a free port, then hand it to the server. Only binds the container's loopback, never a host port.
		$probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		if(!$probe) throw new RuntimeException("Could not find a free port: $errstr");
		$name = stream_socket_get_name($probe, false);
		fclose($probe);
		$port = intval(substr($name, strrpos($name, ':') + 1));

		$log = tempnam(sys_get_temp_dir(), 'apitest-server-');
		$cmd = [PHP_BINARY];
		foreach($iniFlags as $flag)  array_push($cmd, '-d', $flag);
		array_push($cmd, '-S', '127.0.0.1:'.$port, '-t', SRC_ROOT, SRC_ROOT.'/index.php');
		$proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, SRC_ROOT);
		if(!is_resource($proc)) throw new RuntimeException('Could not start the test http server.');

		if(!self::$servers)  register_shutdown_function([self::class, 'stopAll']);
		self::$servers[$key] = ['proc' => $proc, 'port' => $port, 'log' => $log];

		$deadline = microtime(true) + 15;
		while(microtime(true) < $deadline) {
			$status = proc_get_status($proc);
			if(!$status['running']) throw new RuntimeException("The test http server exited during startup. Log:\n".self::log($iniFlags));
			$sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
			if($sock) { fclose($sock); return self::$servers[$key]; }
			usleep(50000);
		}
		throw new RuntimeException("The test http server did not start listening within 15s. Log:\n".self::log($iniFlags));
	}

	public static function stopAll() : void
	{
		foreach(self::$servers as $server) {
			proc_terminate($server['proc']);
			proc_close($server['proc']);
			@unlink($server['log']);
		}
		self::$servers = [];
	}
}

/** Minimal http client on top of the curl extension. */
final class Http
{
	/**
	 * @param string $method
	 * @param string $path including the query string. Sent as is (no dot segment normalization).
	 * @param array{headers?:string[], bearer?:string, session?:array, form?:array, multipart?:array, body?:string, contentType?:string, iniFlags?:string[]} $opt
	 * @return array{status:int, headers:array<string, string[]>, body:string, json:mixed}
	 */
	public static function request($method, $path, $opt = []) : array
	{
		$headers = $opt['headers'] ?? [];
		if(isset($opt['bearer']))  $headers[] = 'Authorization: Bearer '.$opt['bearer'];
		if(isset($opt['session'])) $headers[] = 'Cookie: vs_websessionkey='.rawurlencode($opt['session']['cookie']);
		$headers[] = 'Accept: application/json';
		$headers[] = 'Expect:'; // no 100-continue round trips

		$body = null;
		if(isset($opt['multipart'])) {
			$boundary = '----apitest'.bin2hex(random_bytes(12));
			$body = self::buildMultipart($opt['multipart'], $boundary);
			$headers[] = 'Content-Type: multipart/form-data; boundary='.$boundary;
		}
		else if(isset($opt['form'])) {
			$body = http_build_query($opt['form']);
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
		}
		else if(isset($opt['body'])) {
			$body = $opt['body'];
			if(isset($opt['contentType']))  $headers[] = 'Content-Type: '.$opt['contentType'];
		}

		$ch = curl_init(ApiTestServer::baseUrl($opt['iniFlags'] ?? []).$path);
		$responseHeaders = [];
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_PATH_AS_IS     => true,
			CURLOPT_PROXY          => '',
			CURLOPT_NOPROXY        => '*',
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_HEADERFUNCTION => function($ch, $line) use(&$responseHeaders) {
				$parts = explode(':', $line, 2);
				if(count($parts) === 2)  $responseHeaders[strtolower(trim($parts[0]))][] = trim($parts[1]);
				return strlen($line);
			},
		]);
		if($body !== null)  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

		$responseBody = curl_exec($ch);
		if($responseBody === false)  throw new RuntimeException('Request failed: '.curl_error($ch)."\nServer log:\n".ApiTestServer::log($opt['iniFlags'] ?? []));
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

		return ['status' => $status, 'headers' => $responseHeaders, 'body' => $responseBody, 'json' => json_decode($responseBody, true)];
	}

	/** @param array<array{name:string, value?:string, filename?:string, content?:string, type?:string}> $parts */
	public static function buildMultipart($parts, $boundary) : string
	{
		$out = '';
		foreach($parts as $part) {
			$out .= "--$boundary\r\n";
			if(isset($part['filename'])) {
				$out .= "Content-Disposition: form-data; name=\"{$part['name']}\"; filename=\"{$part['filename']}\"\r\n";
				$out .= 'Content-Type: '.($part['type'] ?? 'application/zip')."\r\n\r\n";
				$out .= $part['content']."\r\n";
			}
			else {
				$out .= "Content-Disposition: form-data; name=\"{$part['name']}\"\r\n\r\n";
				$out .= $part['value']."\r\n";
			}
		}
		return $out."--$boundary--\r\n";
	}

	public static function header($response, $name) : ?string
	{
		return $response['headers'][strtolower($name)][0] ?? null;
	}
}

/** Writes uncompressed (stored) zip archives, the container has no ZipArchive. */
final class StoredZip
{
	/** @param array<string, string> $files name => content */
	public static function build($files) : string
	{
		$local = '';
		$central = '';
		foreach($files as $name => $content) {
			$crc = crc32($content);
			$len = strlen($content);
			$offset = strlen($local);
			$local .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0x21, $crc, $len, $len, strlen($name), 0).$name.$content;
			$central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0x21, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $offset).$name;
		}
		$end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($local), 0);
		return $local.$central.$end;
	}

	/** A minimal content mod that modpeek can read. */
	public static function mod($identifier, $version, $name = 'Api Test Mod') : string
	{
		return self::build(['modinfo.json' => json_encode([
			'type' => 'content', 'modid' => $identifier, 'name' => $name, 'version' => $version, 'dependencies' => ['game' => '1.18.1'],
		])]);
	}
}

/**
 * Fixture builder. Everything it creates is tracked and removed again by cleanup().
 * Users get an email under APITEST_EMAIL_DOMAIN, so leftovers of an aborted run are removed by purgeLeftovers().
 */
final class Fx
{
	public static $runId = '';
	private static $counter = 0;
	/** @var int[] */ public static $userIds = [];
	/** @var int[] */ public static $modIds = [];
	private static $filesDirExisted = null;

	public static function unique($prefix = 'apit') : string
	{
		if(!self::$runId)  self::$runId = bin2hex(random_bytes(4));
		return $prefix.self::$runId.(++self::$counter);
	}

	/** A mod identifier modpeek accepts (lowercase letters and digits, starting with a letter). */
	public static function identifier() : string
	{
		return self::unique('apit');
	}

	public static function con()
	{
		global $con;
		return $con;
	}

	/**
	 * @param array{role?:string, bannedUntilSql?:string} $opt role: 'player' | 'moderator' | 'admin'
	 * @return array{userId:int, hash:string, cookie:string, at:string, name:string}
	 */
	public static function user($opt = []) : array
	{
		if(self::$filesDirExisted === null)  self::$filesDirExisted = is_dir(SRC_ROOT.'/files');

		$con = self::con();
		$name = self::unique('apitest-user-');
		$sessionToken = random_bytes(32);
		$actionToken = random_bytes(8);
		$roleId = intval($con->getOne('SELECT roleId FROM roles WHERE code = ?', [$opt['role'] ?? 'player']));
		if(!$roleId)  throw new RuntimeException('Unknown role.');

		$bannedUntil = $opt['bannedUntilSql'] ?? 'NULL';
		$con->execute("INSERT INTO users (hash, roleId, uid, name, email, actionToken, sessionToken, sessionValidUntil, timezone, lastOnline, bannedUntil)
			VALUES (?, ?, ?, ?, ?, ?, ?, NOW() + INTERVAL 1 DAY, '(GMT) London', NOW(), $bannedUntil)",
			[random_bytes(10), $roleId, random_bytes(18), $name, $name.APITEST_EMAIL_DOMAIN, $actionToken, $sessionToken]);
		$userId = intval($con->insert_ID());
		self::$userIds[] = $userId;

		return [
			'userId' => $userId,
			'hash'   => $con->getOne('SELECT HEX(hash) FROM users WHERE userId = ?', [$userId]),
			'cookie' => base64_encode($sessionToken),
			'at'     => strtoupper(bin2hex($actionToken)),
			'name'   => $name,
		];
	}

	/** The full user row as the application loads it (USER_QUERY_SQL_BASE). */
	public static function userRow($userId) : array
	{
		return self::con()->getRow(USER_QUERY_SQL_BASE.'WHERE u.userId = ?', [$userId]);
	}

	/**
	 * @param int $ownerId
	 * @param array{category?:int, statusId?:int, uploadLimitOverwrite?:int} $opt
	 * @return array{modId:int, assetId:int, createdByUserId:int, category:int}
	 */
	public static function mod($ownerId, $opt = []) : array
	{
		$con = self::con();
		$con->execute('INSERT INTO assets (createdByUserId, statusId, assetTypeId, name, text) VALUES (?, ?, ?, ?, ?)',
			[$ownerId, $opt['statusId'] ?? STATUS_RELEASED, ASSETTYPE_MOD, self::unique('Api Test Mod '), '']);
		$assetId = intval($con->insert_ID());
		$category = $opt['category'] ?? CATEGORY_GAME_MOD;
		$con->execute('INSERT INTO mods (assetId, summary, category, uploadLimitOverwrite, lastReleased) VALUES (?, ?, ?, ?, NULL)',
			[$assetId, 'Api token test mod.', $category, $opt['uploadLimitOverwrite'] ?? null]);
		$modId = intval($con->insert_ID());
		self::$modIds[] = $modId;

		return ['modId' => $modId, 'assetId' => $assetId, 'createdByUserId' => intval($ownerId), 'category' => $category];
	}

	public static function teamMember($modId, $userId, $canEdit) : void
	{
		self::con()->execute('INSERT INTO modTeamMembers (modId, userId, canEdit) VALUES (?, ?, ?)', [$modId, $userId, $canEdit ? 1 : 0]);
	}

	/** Runs $fn with the global $user set to the given user (as the application would have it). */
	public static function actingAs($userId, callable $fn)
	{
		$prev = $GLOBALS['user'] ?? null;
		$GLOBALS['user'] = self::userRow($userId);
		try { return $fn(); }
		finally { $GLOBALS['user'] = $prev; }
	}

	/** Creates a token through the real library function, acting as the creator. @return array{tokenId:int, token:string, expires:string} */
	public static function token($mod, $creatorId, $name = null, $days = 30) : array
	{
		$result = self::actingAs($creatorId, fn() => createModApiToken($mod, self::userRow($creatorId), $name ?? self::unique('token '), $days));
		if(!$result['ok'])  throw new RuntimeException('Fixture token creation failed: '.$result['error']);
		return $result;
	}

	/**
	 * Inserts a token row directly, for states the library can't produce (expired tokens, stale tokens of demoted users).
	 * @return array{tokenId:int, token:string}
	 */
	public static function rawToken($modId, $userId, $expiresSql, $name = 'raw') : array
	{
		$token = API_TOKEN_PREFIX.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
		self::con()->execute("INSERT INTO modApiTokens (modId, userId, name, tokenHash, created, expires) VALUES (?, ?, ?, ?, NOW() - INTERVAL 2 DAY, $expiresSql)",
			[$modId, $userId, $name, hash('sha256', $token, true)]);
		return ['tokenId' => intval(self::con()->insert_ID()), 'token' => $token];
	}

	/**
	 * Inserts a release (asset, modReleases row, compatible versions, RELEASE_CREATE audit row) without a file, as if it had been created $minutesAgo.
	 * @return array{releaseId:int, assetId:int}
	 */
	public static function seedRelease($modId, $creatorId, $identifier, $versionString, $auditFlags = 0, $minutesAgo = 0, $gameVersions = ['1.18.1']) : array
	{
		$con = self::con();
		$con->execute("INSERT INTO assets (createdByUserId, editedByUserId, statusId, assetTypeId, text, created) VALUES (?, ?, ?, ?, '', NOW() - INTERVAL $minutesAgo MINUTE)",
			[$creatorId, $creatorId, STATUS_RELEASED, ASSETTYPE_RELEASE]);
		$assetId = intval($con->insert_ID());
		$con->execute("INSERT INTO modReleases (modId, assetId, identifier, version, created) VALUES (?, ?, ?, ?, NOW() - INTERVAL $minutesAgo MINUTE)",
			[$modId, $assetId, $identifier, compileSemanticVersion($versionString)]);
		$releaseId = intval($con->insert_ID());
		foreach($gameVersions as $gv) {
			$con->execute('INSERT INTO modReleaseCompatibleGameVersions (releaseId, gameVersion) VALUES (?, ?)', [$releaseId, compileSemanticVersion($gv)]);
		}
		$con->execute("INSERT INTO auditLogs (kind, flags, referenceId, initiatorUserId, info, created) VALUES (?, ?, ?, ?, 'seeded', NOW() - INTERVAL $minutesAgo MINUTE)",
			[AUDIT_LOG_KIND_RELEASE_CREATE, $auditFlags, $releaseId, $creatorId]);
		return ['releaseId' => $releaseId, 'assetId' => $assetId];
	}

	public static function storedFilePath($userId, $fileName) : string
	{
		return SRC_ROOT."/files/$userId/$fileName";
	}

	/** Removes everything created through this class. */
	public static function cleanup() : void
	{
		self::deleteEverythingOf(self::$userIds, self::$modIds);
		self::$userIds = [];
		self::$modIds = [];
	}

	/** Removes leftovers of earlier, aborted runs. */
	public static function purgeLeftovers() : void
	{
		$con = self::con();
		// Only users older than an hour, so a concurrently running suite does not lose its fixtures.
		$userIds = array_map('intval', $con->getCol("SELECT userId FROM users WHERE email LIKE ? AND created < NOW() - INTERVAL 1 HOUR", ['%'.APITEST_EMAIL_DOMAIN]));
		if(!$userIds) return;
		$folded = implode(',', $userIds);
		$modIds = array_map('intval', $con->getCol("SELECT m.modId FROM mods m JOIN assets a ON a.assetId = m.assetId WHERE a.createdByUserId IN ($folded)"));
		self::deleteEverythingOf($userIds, $modIds);
	}

	/** @param int[] $userIds @param int[] $modIds */
	private static function deleteEverythingOf($userIds, $modIds) : void
	{
		$con = self::con();
		$users = $userIds ? implode(',', array_map('intval', $userIds)) : '0';
		$mods  = $modIds  ? implode(',', array_map('intval', $modIds))  : '0';

		$modAssetIds = $con->getCol("SELECT assetId FROM mods WHERE modId IN ($mods)");
		$releaseIds  = $con->getCol("SELECT releaseId FROM modReleases WHERE modId IN ($mods)");
		$releaseAssetIds = $con->getCol("SELECT assetId FROM modReleases WHERE modId IN ($mods)");
		$assets = implode(',', array_merge([0], array_map('intval', $modAssetIds), array_map('intval', $releaseAssetIds)));
		$releases = implode(',', array_merge([0], array_map('intval', $releaseIds)));

		$con->execute("DELETE FROM files WHERE userId IN ($users) OR assetId IN ($assets)"); // modPeekResults cascade
		$con->execute("DELETE FROM modReleaseCompatibleGameVersions WHERE releaseId IN ($releases)");
		$con->execute("DELETE FROM modReleaseRetractions WHERE releaseId IN ($releases)");
		$con->execute("DELETE FROM modReleases WHERE modId IN ($mods)");
		$con->execute("DELETE FROM modCompatibleGameVersionsCached WHERE modId IN ($mods)");
		$con->execute("DELETE FROM modCompatibleMajorGameVersionsCached WHERE modId IN ($mods)");
		$con->execute("DELETE FROM mods WHERE modId IN ($mods)"); // tokens, team members, follows cascade
		$con->execute("DELETE FROM assets WHERE assetId IN ($assets) OR createdByUserId IN ($users)");
		$con->execute("DELETE FROM auditLogs WHERE initiatorUserId IN ($users)");
		$con->execute("DELETE FROM notifications WHERE userId IN ($users)");
		$con->execute("DELETE FROM moderationRecords WHERE targetUserId IN ($users) OR moderatorId IN ($users)");
		$con->execute("DELETE FROM fileDownloadTracking WHERE userId IN ($users)");
		$con->execute("DELETE FROM users WHERE userId IN ($users)"); // remaining tokens, team memberships, follows cascade

		foreach($userIds as $userId) {
			self::removeDir(SRC_ROOT.'/files/'.intval($userId));
		}
		if(self::$filesDirExisted === false && is_dir(SRC_ROOT.'/files') && count(scandir(SRC_ROOT.'/files')) === 2) {
			rmdir(SRC_ROOT.'/files');
		}
	}

	private static function removeDir($dir) : void
	{
		if(!is_dir($dir)) return;
		foreach(array_diff(scandir($dir), ['.', '..']) as $entry) {
			$path = "$dir/$entry";
			is_dir($path) ? self::removeDir($path) : unlink($path);
		}
		rmdir($dir);
	}
}

/** Base class: fixtures are removed after every test, the global $user is restored. */
abstract class ApiTokenTestCase extends TestCase
{
	private static $purged = false;
	private $prevUser;

	protected function setUp() : void
	{
		if(!self::$purged) { Fx::purgeLeftovers(); self::$purged = true; }
		$this->prevUser = $GLOBALS['user'] ?? null;
		foreach(['1.17.4', '1.18.1'] as $gv) {
			if(!Fx::con()->getOne('SELECT 1 FROM gameVersions WHERE version = ?', [compileSemanticVersion($gv)])) {
				$this->fail("The tests need game version $gv in the gameVersions table (db/999_sampledata.sql).");
			}
		}
	}

	protected function tearDown() : void
	{
		$GLOBALS['user'] = $this->prevUser;
		Fx::cleanup();
	}

	protected function db()
	{
		return Fx::con();
	}

	protected function countRows($sql, $params = []) : int
	{
		return intval($this->db()->getOne($sql, $params));
	}

	protected function assertJsonError($response, $status, $error, $message = '') : void
	{
		$this->assertSame($status, $response['status'], $message.' Body: '.$response['body']);
		$this->assertSame(['error' => $error], $response['json'], $message.' Body: '.$response['body']);
	}

	protected function assertJsonErrorPrefix($response, $status, $prefix) : void
	{
		$this->assertSame($status, $response['status'], 'Body: '.$response['body']);
		$this->assertIsArray($response['json'], 'Body: '.$response['body']);
		$this->assertSame(['error'], array_keys($response['json']));
		$this->assertStringStartsWith($prefix, $response['json']['error']);
	}

	/** Uploads a release through the api. $fields: gameversions (string[]|string|null), changelog, fileName, fileContent, extraParts, bearer */
	protected function upload($modId, $token, $fields = []) : array
	{
		$parts = [];
		if(!array_key_exists('fileContent', $fields) || $fields['fileContent'] !== null) {
			$parts[] = ['name' => 'file', 'filename' => $fields['fileName'] ?? Fx::unique('mod').'.zip', 'content' => $fields['fileContent'] ?? StoredZip::mod(Fx::identifier(), '1.0.0')];
		}
		$gv = array_key_exists('gameversions', $fields) ? $fields['gameversions'] : ['1.18.1'];
		if(is_array($gv)) foreach($gv as $v)  $parts[] = ['name' => 'gameversions[]', 'value' => $v];
		else if($gv !== null)  $parts[] = ['name' => 'gameversions', 'value' => $gv];
		if(isset($fields['changelog']))  $parts[] = ['name' => 'changelog', 'value' => $fields['changelog']];
		foreach($fields['extraParts'] ?? [] as $p)  $parts[] = $p;

		$opt = ['multipart' => $parts];
		if($token !== null)  $opt['bearer'] = $token;
		if(isset($fields['session']))  $opt['session'] = $fields['session'];
		return Http::request('POST', $fields['path'] ?? "/api/v2/mods/$modId/releases", $opt);
	}

	/** Asserts that a rejected upload left nothing behind for the mod / user. */
	protected function assertNothingCreated($modId, $userId, $fileName = null) : void
	{
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM modReleases WHERE modId = ?', [$modId]), 'No release row');
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM files WHERE userId = ?', [$userId]), 'No files row');
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM assets WHERE createdByUserId = ? AND assetTypeId = ?', [$userId, ASSETTYPE_RELEASE]), 'No release asset');
		$this->assertSame(0, $this->countRows('SELECT COUNT(*) FROM auditLogs WHERE initiatorUserId = ? AND kind IN (?, ?)', [$userId, AUDIT_LOG_KIND_RELEASE_CREATE, AUDIT_LOG_KIND_FILE_CREATE]), 'No audit rows');
		if($fileName !== null)  $this->assertFileDoesNotExist(Fx::storedFilePath($userId, $fileName), 'No stored file');
		$this->assertDirectoryDoesNotExist(SRC_ROOT.'/files/'.$userId, 'No stored files at all for the user');
	}
}
