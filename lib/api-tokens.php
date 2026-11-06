<?php

// Per-mod api tokens, used to upload releases from CI. (upstream issue #18)
// A token belongs to exactly one mod, acts as its creator and can only be used to create releases for that mod.
// Only the sha256 hash of a token is stored, the plaintext is shown exactly once at creation.

const API_TOKEN_PREFIX = 'vsmoddb_';

/** True if $user is the mod owner or a team member with canEdit. NO moderator bypass.
 * @security: Unlike canEditMod() this does not let moderators / admins through, use this to check who may create (and use) api tokens.
 * @param array{modId:int, createdByUserId:int} $mod
 * @param array $user
 * @return bool
 */
function isModOwnerOrEditor($mod, $user) : bool
{
	global $con;

	if(!isset($user['userId'])) return false;
	if($user['userId'] == $mod['createdByUserId']) return true;

	return (bool)$con->getOne(<<<SQL
		SELECT 1
		FROM modTeamMembers
		WHERE modId = ? AND userId = ? AND canEdit = 1
	SQL, [$mod['modId'], $user['userId']]);
}

/** Creates a token. Enforces name (1..64 chars after trim), lifetime (1..API_TOKEN_MAX_LIFETIME_DAYS days) and the active-token cap. Logs the audit event.
 * @security: Also refuses if $user is not owner / editor of the mod, but does not check for bans or the action token. The caller has to do that.
 * @param array{modId:int, createdByUserId:int} $mod
 * @param array $user The creator, the token will act as this user.
 * @param string $name
 * @param int|string $lifetimeDays
 * @return array{ok:true, tokenId:int, token:string, expires:string}|array{ok:false, status:int, error:string}
 */
function createModApiToken($mod, $user, $name, $lifetimeDays) : array
{
	global $con;

	if(!isModOwnerOrEditor($mod, $user))  return ['ok' => false, 'status' => HTTP_FORBIDDEN, 'error' => 'Only the mod owner and team members with edit permissions may create api tokens.'];

	$name = trim(strval($name ?? ''));
	if(!mb_check_encoding($name, 'UTF-8') || preg_match('/\p{Cc}/u', $name))  return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Token name must be valid UTF-8 and must not contain control characters.'];
	$nameLen = mb_strlen($name);
	if($nameLen < 1 || $nameLen > 64)  return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Token name must be between 1 and 64 characters long.'];

	$lifetimeDays = filter_var($lifetimeDays, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => API_TOKEN_MAX_LIFETIME_DAYS]]);
	if($lifetimeDays === false)  return ['ok' => false, 'status' => HTTP_BAD_REQUEST, 'error' => 'Token lifetime must be between 1 and '.API_TOKEN_MAX_LIFETIME_DAYS.' days.'];

	$token = API_TOKEN_PREFIX.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

	$transDepth = $con->transOff;
	try {
		$con->startTrans();

		//NOTE: FOR UPDATE takes a (next-key) lock on the modId_userId index range, so concurrent creations can't both pass the cap check.
		$activeTokens = intval($con->getOne(<<<SQL
			SELECT COUNT(*)
			FROM modApiTokens
			WHERE modId = ? AND userId = ? AND expires > NOW()
			FOR UPDATE
		SQL, [$mod['modId'], $user['userId']]));
		if($activeTokens >= API_TOKEN_MAX_ACTIVE_PER_USER_PER_MOD) {
			$con->failTrans();
			$con->completeTrans();
			return ['ok' => false, 'status' => HTTP_CONFLICT, 'error' => 'You already have '.API_TOKEN_MAX_ACTIVE_PER_USER_PER_MOD.' active api tokens for this mod. Revoke one first.'];
		}

		$con->execute(<<<SQL
			INSERT INTO modApiTokens (modId, userId, name, tokenHash, expires)
			VALUES (?, ?, ?, ?, NOW() + INTERVAL $lifetimeDays DAY)
		SQL, [$mod['modId'], $user['userId'], $name, hash('sha256', $token, true)]); // @security: $lifetimeDays is validated as int above and therefore SQL inert.
		$tokenId = intval($con->insert_ID());

		$expires = $con->getOne('SELECT expires FROM modApiTokens WHERE tokenId = ?', [$tokenId]);

		logAuditEvent(AUDIT_LOG_KIND_MOD_API_TOKEN_CREATE, $mod['modId'], $name);

		$ok = $con->completeTrans();
	}
	catch(Throwable $ex) {
		// adodb throws on sql errors (e.g. a deadlock between two concurrent creations) even inside smart transactions, so we have to unwind the (potentially nested) transaction ourselves.
		$con->failTrans();
		while($con->transOff > $transDepth) $con->completeTrans();
		ErrorHandler::logException($ex);
		$ok = false;
	}

	if(!$ok)  return ['ok' => false, 'status' => HTTP_INTERNAL_ERROR, 'error' => 'Internal database error.'];

	return ['ok' => true, 'tokenId' => $tokenId, 'token' => $token, 'expires' => $expires];
}

/** Tokens of a mod, never including hash/plaintext. If $onlyUserId is given, only that user's.
 * Expired tokens are included (isExpired = true).
 * @param int $modId
 * @param int|null $onlyUserId
 * @return array<array{tokenId:int, modId:int, userId:int, creatorName:string, name:string, created:string, expires:string, lastUsed:?string, isExpired:bool}>
 */
function listModApiTokens($modId, $onlyUserId = null) : array
{
	global $con;

	$params = [$modId];
	$userFilter = '';
	if($onlyUserId !== null) {
		$userFilter = 'AND t.userId = ?';
		$params[] = $onlyUserId;
	}

	$rows = $con->getAll(<<<SQL
		SELECT t.tokenId, t.modId, t.userId, u.name AS creatorName, t.name, t.created, t.expires, t.lastUsed, t.expires <= NOW() AS isExpired
		FROM modApiTokens t
		JOIN users u ON u.userId = t.userId
		WHERE t.modId = ? $userFilter
		ORDER BY t.created DESC, t.tokenId DESC
	SQL, $params);

	return array_map(fn($r) => [
		'tokenId'     => intval($r['tokenId']),
		'modId'       => intval($r['modId']),
		'userId'      => intval($r['userId']),
		'creatorName' => $r['creatorName'],
		'name'        => $r['name'],
		'created'     => $r['created'],
		'expires'     => $r['expires'],
		'lastUsed'    => $r['lastUsed'],
		'isExpired'   => boolval($r['isExpired']),
	], $rows);
}

/** Deletes the token row and logs the audit event (initiator is the global $user). Performs NO permission check.
 * @security: The caller has to make sure the current user may revoke this token.
 * @param int $tokenId
 * @param int $modId The token must belong to this mod, otherwise nothing happens.
 * @return bool false if no such token exists for the mod.
 */
function revokeModApiToken($tokenId, $modId) : bool
{
	global $con, $user;

	$con->startTrans();

	$token = $con->getRow(<<<SQL
		SELECT t.name, a.createdByUserId
		FROM modApiTokens t
		JOIN mods m ON m.modId = t.modId
		JOIN assets a ON a.assetId = m.assetId
		WHERE t.tokenId = ? AND t.modId = ?
		FOR UPDATE
	SQL, [$tokenId, $modId]);
	if(!$token) {
		$con->completeTrans();
		return false;
	}

	$con->execute('DELETE FROM modApiTokens WHERE tokenId = ?', [$tokenId]);

	// Moderators revoking tokens of mods they are not part of get hidden, same as other moderator actions.
	$isModAction = canModerate(null, $user) && !isModOwnerOrEditor(['modId' => $modId, 'createdByUserId' => $token['createdByUserId']], $user);
	logAuditEvent(AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $modId, $token['name'], $isModAction ? AUDIT_LOG_FLAG_MODACTION : 0);

	return $con->completeTrans();
}

/** Deletes all tokens of a user for a mod and logs one audit event per token (initiator is the global $user). Performs NO permission check.
 * Call this whenever a user stops being owner / editor of a mod, so their tokens don't start working again if they get edit rights back later.
 * @param int $modId
 * @param int $userId
 * @param int $logFlags Additional flags for the audit events, e.g. AUDIT_LOG_FLAG_MODACTION.
 * @return int number of revoked tokens
 */
function revokeModApiTokensOfUser($modId, $userId, $logFlags = 0) : int
{
	global $con;

	$con->startTrans();

	$names = $con->getCol('SELECT name FROM modApiTokens WHERE modId = ? AND userId = ? FOR UPDATE', [$modId, $userId]);
	if($names) {
		$con->execute('DELETE FROM modApiTokens WHERE modId = ? AND userId = ?', [$modId, $userId]);

		foreach($names as $name) {
			logAuditEvent(AUDIT_LOG_KIND_MOD_API_TOKEN_REVOKE, $modId, $name, $logFlags);
		}
	}

	$con->completeTrans();

	return count($names);
}

/** Reads the bearer token from the request (HTTP_AUTHORIZATION, REDIRECT_HTTP_AUTHORIZATION).
 * An Authorization header with the Bearer scheme but no / an empty token returns an empty string, so the caller can reject it.
 * @return string|null null when no Bearer header is present
 */
function getBearerTokenFromRequest() : ?string
{
	$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
	if($header === null)  return null;

	if(!preg_match('/^\s*Bearer(?:\s+(.*))?$/is', $header, $matches))  return null;

	return trim($matches[1] ?? '');
}

/** Looks up a non-expired token by plaintext, constant-time safe (lookup by hash).
 * Does not touch globals and does not update lastUsed.
 * @param string $plaintext
 * @return array{tokenId:int, modId:int, userId:int, name:string}|null
 */
function findValidModApiToken($plaintext) : ?array
{
	global $con;

	// 8 byte prefix + 43 chars of unpadded base64url(32 bytes). Cheap reject for garbage, the actual comparison happens on the hash.
	if(!is_string($plaintext) || !preg_match('/^'.API_TOKEN_PREFIX.'[A-Za-z0-9_-]{43}$/', $plaintext))  return null;

	$row = $con->getRow(<<<SQL
		SELECT tokenId, modId, userId, name
		FROM modApiTokens
		WHERE tokenHash = ? AND expires > NOW()
	SQL, [hash('sha256', $plaintext, true)]);
	if(!$row)  return null;

	return [
		'tokenId' => intval($row['tokenId']),
		'modId'   => intval($row['modId']),
		'userId'  => intval($row['userId']),
		'name'    => $row['name'],
	];
}

/** Sets lastUsed = NOW().
 * @param int $tokenId
 */
function touchModApiToken($tokenId) : void
{
	global $con;
	$con->execute('UPDATE modApiTokens SET lastUsed = NOW() WHERE tokenId = ?', [$tokenId]);
}

/** Number of token-made releases for the mod within the last hour (uses AUDIT_LOG_FLAG_VIA_API_TOKEN).
 * NOTE: Release create log entries reference the releaseId, so this joins modReleases to filter by mod.
 * Releases are never deleted individually (only retracted, which keeps the row), only together with their mod, which also deletes the mod's tokens.
 * Therefore the join can't be used to escape the limit.
 * @param int $modId
 * @return int
 */
function countRecentApiReleases($modId) : int
{
	global $con;

	return intval($con->getOne(<<<SQL
		SELECT COUNT(*)
		FROM modReleases r
		JOIN auditLogs l ON l.referenceId = r.releaseId AND l.kind = ?
		WHERE r.modId = ? AND (l.flags & ?) AND l.created > NOW() - INTERVAL 1 HOUR
	SQL, [AUDIT_LOG_KIND_RELEASE_CREATE, $modId, AUDIT_LOG_FLAG_VIA_API_TOKEN]));
}

/** Seconds until enough of the releases counted by countRecentApiReleases() have left the one hour window to allow another one. Uses the same filter.
 * @param int $modId
 * @return int at least 1
 */
function secondsUntilApiReleaseAllowed($modId) : int
{
	global $con;

	$offset = API_RELEASE_LIMIT_PER_MOD_PER_HOUR - 1; // @security: config constant, sql inert.
	$seconds = intval($con->getOne(<<<SQL
		SELECT TIMESTAMPDIFF(SECOND, NOW(), l.created + INTERVAL 1 HOUR)
		FROM modReleases r
		JOIN auditLogs l ON l.referenceId = r.releaseId AND l.kind = ?
		WHERE r.modId = ? AND (l.flags & ?) AND l.created > NOW() - INTERVAL 1 HOUR
		ORDER BY l.created DESC
		LIMIT 1 OFFSET $offset
	SQL, [AUDIT_LOG_KIND_RELEASE_CREATE, $modId, AUDIT_LOG_FLAG_VIA_API_TOKEN]));

	return max(1, $seconds);
}
