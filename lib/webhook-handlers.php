<?php

if(count($urlparts) < 1) {
	http_response_code(HTTP_BAD_REQUEST);
	exit();
}

switch($urlparts[0]) {
	case 'game-tag':
		if(count($urlparts) !== 1) {
			http_response_code(HTTP_BAD_REQUEST);
			exit();
		}

		if(!$_SERVER['REQUEST_METHOD'] === 'POST') {
			header('Allow: POST', true, HTTP_WRONG_METHOD);
			exit();
		}

		if(empty($_SERVER['HTTP_X_SECRET'])) {
			http_response_code(HTTP_UNAUTHORIZED);
			echo 'Missing secret.';
			exit();
		}
		if($_SERVER['HTTP_X_SECRET'] !== $config['wh-secret-gv']) {
			http_response_code(HTTP_FORBIDDEN);
			echo 'Wrong secret.';
			exit();
		}

		$newVersion = compileSemanticVersion(trim(file_get_contents('php://input')));
		if(!$newVersion) {
			http_response_code(HTTP_BAD_REQUEST);
			echo 'Malformed version string.';
			exit();
		}

		// Do actual work:

		$con->startTrans();
		$exists = $con->getOne('SELECT 1 FROM gameVersions WHERE version = ?', [$newVersion]);
		if($exists) {
			echo 'Version already exists.';
		}
		else {
			$allVersions = array_map('intval', $con->getCol('SELECT version FROM gameVersions'));
			$allVersions[] = $newVersion;

			sort($allVersions); // sort ascending so the keys are in the correct order
			$foldedValues = implode(', ', array_map(fn($k, $v) => "($v, $k)", array_keys($allVersions), $allVersions));

			// @security: All keys and values are numeric and therefore SQL inert.
			$con->execute(<<<SQL
				INSERT INTO gameVersions (version, sortIndex)
					VALUES $foldedValues
				ON DUPLICATE KEY UPDATE
					sortIndex = VALUES(sortIndex)
			SQL);

			echo 'Version inserted.';
		}
		$con->completeTrans();

		exit();

	default:
		http_response_code(HTTP_NOT_FOUND);
		exit();
}