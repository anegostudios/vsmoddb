<?php

//NOTE(Rennorb): Assume the user object exists.

switch(count($urlparts)) {
	case 0:
		switch($_SERVER['REQUEST_METHOD']) {
			case 'GET':
				$versions = $con->getCol('SELECT version FROM GameVersions ORDER BY version DESC');
				$versions = array_map(fn($s) => formatSemanticVersion(intval($s)), $versions);
		
				good($versions);
		
			case 'POST':
				if(empty($_POST['new'])) fail(HTTP_BAD_REQUEST);

				if($user['rolecode'] !== 'admin') fail(HTTP_FORBIDDEN);
				validateActionTokenAPI();
				validateUserNotBanned();

				$newVersion = compileSemanticVersion($_POST['new']);
				if($newVersion === false) fail(HTTP_BAD_REQUEST);

				$allVersions = array_map('intval', $con->getCol('SELECT `version` FROM GameVersions'));
				$prevCount = count($allVersions);

				$allVersions[] = $newVersion;
				$allVersions = array_unique($allVersions);

				if($prevCount === count($allVersions)) fail(HTTP_CONFLICT, ['reason' => 'This version already exists']);

				sort($allVersions); // sort ascending so the keys are in the correct order :VersionSortIndex

				$foldedValues = implode(', ', array_map(fn($k, $v) => "($v, $k)", array_keys($allVersions), $allVersions));

				// @security: All keys and values are numeric and therefore SQL inert.
				$ok = $con->Execute("
					INSERT INTO GameVersions (version, sortIndex)
						VALUES $foldedValues
					ON DUPLICATE KEY UPDATE
						sortIndex = VALUES(sortIndex)
				");

				if(!$ok) fail(HTTP_INTERNAL_ERROR);
				good();
		
			default:
				header('Allow: GET, POST');
				fail(HTTP_WRONG_METHOD);
		}

	case 1:
		switch($_SERVER['REQUEST_METHOD']) {
			case 'DELETE':
				if($user['rolecode'] !== 'admin') fail(HTTP_FORBIDDEN);
				validateActionTokenAPI();
				validateUserNotBanned();

				$targetVersion = compileSemanticVersion($urlparts[0]);
				if($targetVersion === false) fail(HTTP_BAD_REQUEST);

				$con->startTrans();
				$con->Execute('DELETE FROM GameVersions where version = ?', [$targetVersion]);
				$didDelete = $con->affected_rows() == 1;
				if($didDelete) {
					$con->Execute('UPDATE GameVersions SET sortIndex = sortIndex - 1 where version > ?', [$targetVersion]);
				}
				$ok = $con->completeTrans();

				if(!$didDelete) fail(HTTP_NOT_FOUND);
				if(!$ok) fail(HTTP_INTERNAL_ERROR);
				good();
		
			default:
				header('Allow: DELETE');
				fail(HTTP_WRONG_METHOD);
		}
}
