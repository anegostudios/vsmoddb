<?php

if(empty($user)) {
	fail(401);
}


/** Validates that the current user is not banned and `fail`s with a reason if they are. */
function validateUserNotBanned()
{
	global $user;
	if($user['isbanned'])  fail(HTTP_FORBIDDEN, ['reason' => 'You are currently banned.']);
}

/** Validates the action token within the request and `fail`s with a reason it is not. */
function validateActionTokenAPI()
{
	global $user;
	if(!isset($_REQUEST['at']) || $user['actiontoken'] != $_REQUEST['at'])  fail(HTTP_FORBIDDEN, ['reason' => 'Invalid action token. Need to log in again?']);
}

switch($urlparts[0]) {
	case 'notifications':
		array_shift($urlparts);
		include(__DIR__ . '/notifications.php');
		break;

	case 'comments':
		array_shift($urlparts);
		include(__DIR__ . '/comments.php');
		break;

	case 'mods':
		array_shift($urlparts);
		include(__DIR__ . '/mods.php');
		break;

	case 'game-versions':
		array_shift($urlparts);
		include(__DIR__ . '/game-versions.php');
		break;
}
