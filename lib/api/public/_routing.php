<?php

switch($urlparts[0]) {
	case 'tags':
		array_shift($urlparts);
		require(__DIR__ . '/tags.php');
		break;

	case 'users':
		array_shift($urlparts);
		require(__DIR__ . '/users.php');
		break;

	case 'mods':
		$__urlparts = $urlparts; //TODO(Rennorb) @hammer @cleanup
		array_shift($urlparts);
		require(__DIR__ . '/mods.php');
		// If we have not returned within the handler; restore parts and continue.
		$urlparts = $__urlparts; 
		break;
}
