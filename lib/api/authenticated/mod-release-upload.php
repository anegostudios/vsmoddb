<?php

/**
 * POST /api/v2/mods/{modid}/releases
 * Creates a new release from a multipart/form-data upload. Authenticated ONLY by a mod api token.
 * Dispatched from lib/api/authenticated/mods.php.
 *
 * In scope when this file is entered:
 * - $con       the db connection.
 * - $urlparts  exactly [modid, 'releases'] (the leading 'mods' is already shifted off).
 * - $modId     int, filter_var(FILTER_VALIDATE_INT) of $urlparts[0]. Not yet checked for existence.
 * - $apiToken  array{tokenId:int, modId:int, userId:int, name:string}, never null here.
 * - $user      the token creator's row (USER_QUERY_SQL_BASE shape incl. isBanned, roleCode). Never the session user.
 *
 * Already guaranteed by lib/api/authenticated/_routing.php and the dispatch in mods.php:
 * - request method is POST and the request was authenticated with a valid, non-expired token,
 * - $apiToken['modId'] === $modId,
 * - the creator is not banned and is still owner or editor (isModOwnerOrEditor) of the mod,
 * - lastUsed of the token has been updated.
 * Not yet checked: mod lock status, category, content type, rate limit, file / form validation.
 *
 * @var object $con
 * @var array $user
 * @var array<string> $urlparts
 * @var int $modId
 * @var array{tokenId:int, modId:int, userId:int, name:string} $apiToken
 */

require_once(SRC_ROOT.'/lib/edit-release.php');

if(!preg_match('#^multipart/form-data\s*(;|$)#i', $_SERVER['CONTENT_TYPE'] ?? '')) {
	fail(HTTP_BAD_REQUEST, "This endpoint requires requests with Content-Type 'multipart/form-data'.");
}

//NOTE: If the request body exceeds post_max_size php drops it entirely and we get empty $_POST and $_FILES, which would otherwise look like a missing file.
$postMaxSize = parseIniSize(ini_get('post_max_size'));
if(!$_POST && !$_FILES && $postMaxSize > 0 && intval($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMaxSize) {
	fail(HTTP_PAYLOAD_TOO_LARGE, 'Request too large! Limit is '.formatByteSize($postMaxSize).'.');
}

$mod = $con->getRow(<<<SQL
	SELECT a.assetId, a.createdByUserId, a.name, a.statusId, m.modId, m.category, m.uploadLimitOverwrite, m.urlAlias
	FROM mods m
	JOIN assets a ON a.assetId = m.assetId
	WHERE m.modId = ?
SQL, [$modId]);
if(!$mod)  fail(HTTP_NOT_FOUND, 'Mod not found.'); // Only possible if the mod got deleted after the router checked the token.

if($mod['statusId'] == STATUS_LOCKED)  fail(HTTP_FORBIDDEN, 'This mod has been locked by a moderator. New releases can not be created until it is unlocked.');

if(($mod['category'] & CATEGORY__MASK) !== CATEGORY_GAME_MOD)  fail(HTTP_BAD_REQUEST, 'Uploading releases through the api is not supported yet for this mod category.');

if(countRecentApiReleases($modId) >= API_RELEASE_LIMIT_PER_MOD_PER_HOUR) {
	header('Retry-After: '.secondsUntilApiReleaseAllowed($modId));
	fail(HTTP_TOO_MANY_REQUESTS, 'Too many releases. At most '.API_RELEASE_LIMIT_PER_MOD_PER_HOUR.' releases per hour can be created through the api.');
}

$file = $_FILES['file'] ?? null;
if(!$file || $file['error'] === UPLOAD_ERR_NO_FILE)  fail(HTTP_BAD_REQUEST, "Missing file. Upload the mod file in the 'file' field.");
if(is_array($file['name']) || count($_FILES) > 1)  fail(HTTP_BAD_REQUEST, 'Exactly one file must be uploaded per release.');

// `gameversions[]` (repeated) is the documented form. A single plain `gameversions` value is unambiguous, so it is accepted as well.
$gameVersionStrings = $_POST['gameversions'] ?? null;
if(is_string($gameVersionStrings))  $gameVersionStrings = [$gameVersionStrings];
$gameVersions = compileAndValidateGameVersions($gameVersionStrings);
if(!$gameVersions['ok'])  fail($gameVersions['status'], $gameVersions['error']);

$changelog = $_POST['changelog'] ?? '';
if(!is_string($changelog))  fail(HTTP_BAD_REQUEST, 'Malformed changelog.');
$changelogHtml = trimHtml(sanitizeHtml($changelog));
$changelogLen = strlen($changelogHtml);
if($changelogLen > 65535) { // TEXT column max length in assets.text
	$sizeKb = floor($changelogLen / 1024);
	$reason = "Changelog has excessive size ({$sizeKb}KB).";
	if(str_contains($changelogHtml, 'src="data:image')) $reason .= " You cannot paste large images directly. If you need a large image, upload it to an external site and link to that.";
	fail(HTTP_BAD_REQUEST, $reason);
}

try {
	$result = createReleaseFromUploadedFile($mod, $file, $gameVersions['versions'], $changelogHtml, AUDIT_LOG_FLAG_VIA_API_TOKEN);
}
catch(Throwable $ex) {
	// The global error handler would answer with html (and a stack trace in debug mode). Api clients get json without internals.
	ErrorHandler::logException($ex);
	fail(HTTP_INTERNAL_ERROR, 'Internal server error.');
}
if(!$result['ok'])  fail($result['status'], $result['error']);

$releaseId = $result['releaseId'];
// @security: $modId and $releaseId are ints, therefore sql inert.
header("Location: /api/v2/mods/{$modId}/releases/{$releaseId}", true, HTTP_CREATED);
good(getApiRelease("r.modId = {$modId} AND r.releaseId = {$releaseId}"));
