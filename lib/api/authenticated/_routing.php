<?php

if(empty($user)) {
	fail(401);
}

/** @var array<string> $urlparts */


/** Validates that the current user is not banned and `fail`s with an error if they are. */
function validateUserNotBanned()
{
	global $user;
	if($user['isBanned'])  fail(HTTP_FORBIDDEN, ['error' => 'You are currently banned.']);
}

/** Validates the action token within the request and `fail`s with a error it is not. */
function validateActionToken()
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
