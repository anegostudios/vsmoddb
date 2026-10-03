<?php

/** @var array<string> $urlparts */

global $user, $apiToken;

/** The mod api token this request was authenticated with, null for session (cookie) authenticated requests.
 * @var array{tokenId:int, modId:int, userId:int, name:string}|null $apiToken
 */
$apiToken = null;

// `POST mods/{modid}/releases` is the one and only route that may be used with an api token, and it can only be used with an api token.
$isReleaseUploadRoute = $_SERVER['REQUEST_METHOD'] === 'POST'
	&& count($urlparts) === 3 && $urlparts[0] === 'mods' && $urlparts[2] === 'releases';

$bearerToken = getBearerTokenFromRequest();
if($bearerToken !== null) {
	// @security: A bearer token replaces the session entirely. The cookie user must never be used for token authenticated requests.
	$user = null;

	$apiToken = findValidModApiToken($bearerToken);
	if(!$apiToken) {
		header('WWW-Authenticate: Bearer error="invalid_token"');
		fail(HTTP_UNAUTHORIZED, 'Invalid or expired api token.');
	}

	// @security: SECURITY INVARIANT: A token authenticated request may ONLY execute `POST mods/{modid}/releases`.
	// Several authenticated routes are protected by the session alone (no action token), so this must be enforced here and not per endpoint.
	// The public router does not restore $urlparts for all of its sub-routers, so we additionally check the original request path
	// to make sure some other prefix that happened to get stripped can't be used to reach the endpoint.
	$requestPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), " \n\r\t\v\0/");
	if(!$isReleaseUploadRoute || !preg_match('#^api/v2/mods/([^/]+)/releases$#', $requestPath, $pathMatch) || $pathMatch[1] !== $urlparts[1]) {
		fail(HTTP_FORBIDDEN, 'Api tokens can only be used to upload releases (POST /api/v2/mods/{modid}/releases).');
	}

	//NOTE: 403 instead of 404 so tokens can't be used to probe mod ids.
	if(filter_var($urlparts[1], FILTER_VALIDATE_INT) !== $apiToken['modId'])  fail(HTTP_FORBIDDEN, 'This api token belongs to a different mod.');

	// The token acts as its creator. Built from the same base query as the session user so it has the same shape.
	$user = $con->getRow(USER_QUERY_SQL_BASE.'WHERE u.userId = ?', [$apiToken['userId']]);
	if(!$user)  fail(HTTP_UNAUTHORIZED, 'Invalid or expired api token.'); // can't really happen, tokens get deleted with their user

	if($user['isBanned'])  fail(HTTP_FORBIDDEN, 'The creator of this api token is currently banned.');

	$tokenMod = $con->getRow(<<<SQL
		SELECT m.modId, a.createdByUserId
		FROM mods m
		JOIN assets a ON a.assetId = m.assetId
		WHERE m.modId = ?
	SQL, [$apiToken['modId']]);
	if(!$tokenMod || !isModOwnerOrEditor($tokenMod, $user))  fail(HTTP_FORBIDDEN, 'The creator of this api token is no longer the owner or an editor of this mod.');

	touchModApiToken($apiToken['tokenId']);
}
else if($isReleaseUploadRoute) {
	header('WWW-Authenticate: Bearer');
	fail(HTTP_UNAUTHORIZED, 'This endpoint requires an api token (Authorization: Bearer <token>).');
}

if(empty($user)) {
	fail(401);
}


/** Validates that the current user is not banned and `fail`s with an error if they are. */
function validateUserNotBanned()
{
	global $user;
	if($user['isBanned'])  fail(HTTP_FORBIDDEN, ['error' => 'You are currently banned.']);
}

/** Validates the action token within the request and `fail`s with a error it is not. */
function validateActionTokenAPI()
{
	global $user;
	if(!isset($_REQUEST['at']) || $user['actionToken'] != $_REQUEST['at'])  fail(HTTP_FORBIDDEN, ['error' => 'Invalid action token. Need to log in again?']);
}

switch($urlparts[0]) {
	case 'notifications':
		array_shift($urlparts);
		require(__DIR__ . '/notifications.php');
		break;

	case 'settings':
		array_shift($urlparts);
		require(__DIR__ . '/settings.php');
		break;

	case 'comments':
		array_shift($urlparts);
		require(__DIR__ . '/comments.php');
		break;

	case 'mods':
		array_shift($urlparts);
		require(__DIR__ . '/mods.php');
		break;

	case 'game-versions':
		array_shift($urlparts);
		require(__DIR__ . '/game-versions.php');
		break;
}
