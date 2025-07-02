<?php

//NOTE(Rennorb): Assume the user object exists.

if(empty($urlparts)) {
	$ids = $con->getCol('SELECT notificationId FROM Notifications WHERE !`read` AND userId = ?', [$user['userid']]);
	good($ids);
}

switch($urlparts[0]) {
	case 'all':
		fail(404);

	case 'clear':
		validateMethod('POST');
		$ids = isset($_POST['ids']) && is_string($_POST['ids']) ? explode(',', $_POST['ids']) : ($_POST['ids'] ?? null);
		$ids = filter_var($ids, FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY);
		if(empty($ids)) fail(400, ['reason' => 'No valid ids provided.']);
		
		$foldedIds = implode(',', $ids);

		// @security: Ids ($foldedIds) are knows / filtered to be integers, and therefore sql inert.
		$idsWithoutPermission = $con->getCol("SELECT notificationId FROM Notifications WHERE notificationId IN ($foldedIds) AND userId != ?", [$user['userid']]);
		if(!empty($idsWithoutPermission)) fail(403, ['reason' => 'Invalid ids provided.', 'invalid_ids' => $idsWithoutPermission]);

		$con->execute("UPDATE Notifications SET `read` = 1 WHERE notificationId in ($foldedIds)");
		good();

	case 'settings':
		if(count($urlparts) < 2)   fail(400);

		switch ($urlparts[1]) {
			case 'followed-mods':
				validateMethod('POST');
				if(count($urlparts) < 3)   fail(400, ['reason' => 'Missing id.']);

				$modid = filter_var($urlparts[2], FILTER_VALIDATE_INT);
				if($modid === false)   fail(400, ['reason' => 'Malformed id query param.']);

				if(count($urlparts) === 3) {
					$newFlags = filter_input(INPUT_POST, 'new', FILTER_VALIDATE_INT);
					if($newFlags === null)   fail(400, ['reason' => 'Missing new settings value.']);
					if($newFlags === false)   fail(400, ['reason' => 'Malformed new settings value.']);

					$con->execute(<<<SQL
						INSERT INTO UserFollowedMods
							(modId, userId, flags) VALUES (?, ?, ?)
						ON DUPLICATE KEY
							UPDATE flags = ?
					SQL, [$modid, $user['userid'], $newFlags, $newFlags]);
					if($con->affected_rows() == 1) {
						//NOTE(Rennorb): MariaDB / MySQL returns two rows affected on update.
						// For this reason we are able to differentiate between update and new insert without extra queries.
						$con->execute('UPDATE `mod` SET follows = follows + 1 WHERE modid = ?', [$modid]);
					}

					good();
				}

				switch($urlparts[3]) {
					case 'unfollow':
						validateMethod('POST');
						$con->execute('DELETE FROM UserFollowedMods WHERE modId = ? AND userId = ?', [$modid, $user['userid']]);
						if($con->affected_rows()) {
							$con->execute('UPDATE `mod` SET follows = follows - 1 WHERE modid = ?', [$modid]);
						}

						good();
				}
		}
}
