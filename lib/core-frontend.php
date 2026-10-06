<?php

/** Validates that the current user is not banned and terminates with an error page if they are. */
function validateUserNotBanned()
{
	global $user;
	if($user['isBanned'])   showErrorPage(HTTP_FORBIDDEN, 'You are currently banned.');
}

/** Validates the `at` parameter of the request (post or get) and terminates with an error page if a mismatch is found. */
function validateActionToken()
{
	global $user;
	if (!isset($_REQUEST['at']) || $user['actionToken'] != $_REQUEST['at']) {
		showErrorPage(HTTP_BAD_REQUEST, 'Invalid action token. To prevent CSRF, you can only submit forms directly on the site. If you believe this is an error, please contact Rennorb <a class="external" href="https://discord.com/channels/302152934249070593/810541931469078568">on Discord</a>.', false, true);
	}
}
