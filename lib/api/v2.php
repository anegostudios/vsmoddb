<?php

header('Content-Type: application/json');

/** Formats a json error response and exits the program.
 * @param int $statuscode
 * @param array? $data
 */
function fail($statuscode, $data = null)
{
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

if (empty($urlparts)) {
	fail(HTTP_NOT_FOUND);
}

/** Formats the response as json and exits the program.
 * @param array|null $data
 */
function good($data = null, $flags = 0)
{
	exit(($data !== null) ? json_encode($data, $flags) : '{}');
}

include($config["basepath"] . "lib/api/public/_routing.php");
include($config["basepath"] . "lib/api/authenticated/_routing.php");

fail(HTTP_NOT_FOUND);
