<?php

switch($urlparts[0]) {
	case 'users':
		array_shift($urlparts);
		include(__DIR__ . '/users.php');
		break;
}
