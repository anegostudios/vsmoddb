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

if (empty($urlparts)) {
	fail(404);
}

/** Formats the response as json and exits the program.
 * @param array|null $data
 */
function good($data = null)
{
	exit(($data !== null) ? json_encode($data) : '{}');
}

include($config["basepath"] . "lib/api/public/_routing.php");
include($config["basepath"] . "lib/api/authenticated/_routing.php");

fail(404);
