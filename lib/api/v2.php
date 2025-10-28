<?php

/** Formats a json error response and exits the program.
 * @param int $statuscode
 * @param array? $data
 */
function fail($statuscode, $data = null)
{
	header('Content-Type: application/json');
	http_response_code($statuscode);
	exit(($data !== null) ? json_encode($data) : '{}');
}

/** Validates that the script was called using the correct HTTP method and `fail`s with a reason if it was not.
 * @param 'PUT'|'GET'|'POST'|'DELETE' $allowedMethod
 */
function validateMethod($allowedMethod)
{
	if($_SERVER['REQUEST_METHOD'] !== $allowedMethod) {
		header('Allow: '.$allowedMethod);
		fail(HTTP_WRONG_METHOD, ['reason' => "This endpoint does not support {$_SERVER['REQUEST_METHOD']} requests. Try again using $allowedMethod."]);
	}
}

/** Validates that the script was called using the correct content-type header.
 * @param string $allowedType
 */
function validateContentType($allowedType)
{
	if(!isset($_SERVER['CONTENT_TYPE'])) {
		fail(HTTP_BAD_REQUEST, ['reason' => "This endpoint requires a requests with Content-Type '$allowedType'."]);
	}
	else if($_SERVER['CONTENT_TYPE'] !== $allowedType) {
		fail(HTTP_BAD_REQUEST, ['reason' => "This endpoint does not support requests of Content-Type '{$_SERVER['CONTENT_TYPE']}'. Try again using '$allowedType'."]);
	}
}

if(DB_READONLY) {
	switch($_SERVER['REQUEST_METHOD']) {
		case 'GET': case 'HEAD': case 'OPTIONS': /* ok */
			break;

		default:
			header('Retry-After: 1800' /* 30min */, true, HTTP_SERVICE_UNAVAILABLE);
			header('Content-Type: application/json');
			exit('{"reason": "We are currently in readonly mode."}');
	}
}

if (empty($urlparts)) {
	fail(HTTP_NOT_FOUND);
}

/** Formats the response as json and exits the program.
 * @param array|null $data
 */
function good($data = null, $flags = 0)
{
	header('Content-Type: application/json');
	exit(($data !== null) ? json_encode($data, $flags) : '{}');
}

include($config["basepath"] . "lib/api/public/_routing.php");
include($config["basepath"] . "lib/api/authenticated/_routing.php");

fail(HTTP_NOT_FOUND);
