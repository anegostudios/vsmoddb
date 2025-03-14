<?php

if(empty($user)) {
	fail(401);
}

switch($urlparts[0]) {
	case 'notifications':
		array_shift($urlparts);
		include(__DIR__ . '/notifications.php');
		break;
}
