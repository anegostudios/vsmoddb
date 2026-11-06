<?php

/**
 * Mod api token management. Session (cookie) + action token (`at`) authenticated, like other v2 write endpoints.
 *   GET    /api/v2/mods/{modid}/api-tokens
 *   POST   /api/v2/mods/{modid}/api-tokens            body: name, lifetimeDays
 *   DELETE /api/v2/mods/{modid}/api-tokens/{tokenid}
 * Dispatched from lib/api/authenticated/mods.php.
 *
 * In scope when this file is entered:
 * - $con       the db connection.
 * - $urlparts  [modid, 'api-tokens', ...] (the leading 'mods' is already shifted off), count >= 2.
 *              Trailing segments are NOT validated, this handler has to check count($urlparts) itself.
 * - $modId     int, filter_var(FILTER_VALIDATE_INT) of $urlparts[0]. Not yet checked for existence.
 * - $user      the session user (non-empty). NOT yet checked for bans or the action token.
 * - $apiToken  always null here (token authenticated requests are rejected with 403 before this file).
 *
 * This handler performs all permission checks itself (see lib/api-tokens.php and DESIGN.md).
 *
 * Permissions (mirrored by the token section in edit-mod.php): :ApiTokenPermissions
 * - list:   mod owner, moderators and admins see all tokens of the mod, team members with edit permissions only their own, everyone else 403.
 * - create: only the mod owner and team members with edit permissions (no moderator bypass), not while banned.
 *           Refused (400) for mods outside the game mod category, because the upload endpoint does not support them yet.
 *           Allowed for locked mods, the lock is temporary and the upload endpoint refuses uploads while it is in place.
 * - revoke: the token's creator, the mod owner, moderators and admins.
 *           Banned users may still list and revoke their OWN tokens (that only ever reduces access), but nothing else.
 *           Every token the current user may not revoke is reported as 'not found', so ids of other users' tokens can't be probed.
 *
 * @var object $con
 * @var array $user
 * @var array<string> $urlparts
 * @var int $modId
 * @var null $apiToken
 */

// @security: Responses are user specific and the create response contains the plaintext token, which must not end up in any cache.
header('Cache-Control: no-store');

/** Loads the mod (modId, createdByUserId, category) or `fail`s with 404. */
$loadMod = function() use($con, $modId) {
	$mod = $con->getRow(<<<SQL
		SELECT m.modId, a.createdByUserId, m.category
		FROM mods m
		JOIN assets a ON a.assetId = m.assetId
		WHERE m.modId = ?
	SQL, [$modId]);
	if(!$mod)  fail(HTTP_NOT_FOUND, 'Unknown modid.');

	$mod['modId'] = intval($mod['modId']);
	$mod['createdByUserId'] = intval($mod['createdByUserId']);
	$mod['category'] = intval($mod['category']);
	return $mod;
};

switch(count($urlparts)) {
	case 2: // /mods/{modid}/api-tokens
		switch($_SERVER['REQUEST_METHOD']) {
			case 'GET':
				validateActionTokenAPI();

				$mod = $loadMod();

				$seesAllTokens = $user['userId'] == $mod['createdByUserId'] || canModerate(null, $user);
				if(!$seesAllTokens && !isModOwnerOrEditor($mod, $user))  fail(HTTP_FORBIDDEN, 'You may not view the api tokens of this mod.');

				// Banned users only get to see their own tokens, so they can still revoke them.
				good(listModApiTokens($modId, ($seesAllTokens && !$user['isBanned']) ? null : $user['userId']));

			case 'POST':
				validateUserNotBanned();
				validateActionTokenAPI();

				$mod = $loadMod();

				// @security: Moderators and admins must not be able to create tokens for mods they are not the owner / an editor of, therefore not canEditMod().
				if(!isModOwnerOrEditor($mod, $user))  fail(HTTP_FORBIDDEN, 'Only the mod owner and team members with edit permissions may create api tokens.');

				if(($mod['category'] & CATEGORY__MASK) !== CATEGORY_GAME_MOD)  fail(HTTP_BAD_REQUEST, 'Uploading releases via the api is currently only supported for game mods.');

				$result = createModApiToken($mod, $user, $_POST['name'] ?? '', $_POST['lifetimeDays'] ?? '');
				if(!$result['ok'])  fail($result['status'], $result['error']);

				//NOTE: The plaintext token is only ever part of this response, it is not stored or logged anywhere.
				http_response_code(HTTP_CREATED);
				good([
					'tokenId' => $result['tokenId'],
					'token'   => $result['token'],
					'expires' => $result['expires'],
				]);

			default:
				header('Allow: GET, POST');
				fail(HTTP_WRONG_METHOD);
		}

	case 3: // /mods/{modid}/api-tokens/{tokenid}
		$tokenId = filter_var($urlparts[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if($tokenId === false)  fail(HTTP_BAD_REQUEST, 'Malformed query param tokenid.');

		switch($_SERVER['REQUEST_METHOD']) {
			case 'DELETE':
				//NOTE: request_parse_body() throws for requests without (or with an unsupported) content type, but `at` may also be passed in the query string.
				if(!empty($_SERVER['CONTENT_TYPE'])) {
					try { parseRequestBody(); }
					catch(RequestParseBodyException $e) { fail(HTTP_BAD_REQUEST, 'Malformed request body.'); }
				}
				validateActionTokenAPI();

				$mod = $loadMod();

				$tokenCreatorId = intval($con->getOne('SELECT userId FROM modApiTokens WHERE tokenId = ? AND modId = ?', [$tokenId, $modId]));

				$mayRevoke = $tokenCreatorId && (
					$tokenCreatorId == $user['userId']
					// Banned users may only revoke their own tokens.
					|| (!$user['isBanned'] && ($user['userId'] == $mod['createdByUserId'] || canModerate(null, $user)))
				);
				// @security: Same response for 'does not exist' and 'not yours', so team members can't probe for other members' token ids.
				if(!$mayRevoke)  fail(HTTP_NOT_FOUND, 'Unknown api token.');

				if(!revokeModApiToken($tokenId, $modId))  fail(HTTP_NOT_FOUND, 'Unknown api token.'); // revoked concurrently

				good();

			default:
				header('Allow: DELETE');
				fail(HTTP_WRONG_METHOD);
		}

	default:
		fail(HTTP_BAD_REQUEST);
}
