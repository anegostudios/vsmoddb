<?php

//NOTE(Rennorb): Assume the user object exists.

if(empty($urlparts)) {
	$ids = $con->getCol('select notificationid from notification where `read` = 0 and userid = ?', [$user['userid']]);
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

		$idsWithoutPermission = $con->getCol("select notificationid from notification where notificationid in ($foldedIds) and userid != ?", [$user['userid']]);
		if(!empty($idsWithoutPermission)) fail(403, ['reason' => 'Invalid ids provided.', 'invalid_ids' => $idsWithoutPermission]);

		$con->execute("update notification set `read` = 1 where notificationid in ($foldedIds)");
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

					$con->execute('
						insert into follow
							(modid, userid, flags, created) values (?, ?, ?, now())
						on duplicate key
							update flags = ?
					', [$modid, $user['userid'], $newFlags, $newFlags]);
					if($con->affected_rows() == 1) {
						//NOTE(Rennorb): MariaDB / MySQL returns two rows affected on update.
						// For this reason we are able to differentiate between update and new insert without extra queries.
						$con->execute('update `mod` set follows = follows + 1 where modid = ?', [$modid]);
					}

					good();
				}

				switch($urlparts[3]) {
					case 'unfollow':
						validateMethod('POST');
						$con->execute('delete from follow where modid = ? and userid = ?', [$modid, $user['userid']]);
						if($con->affected_rows()) {
							$con->execute('update `mod` set follows = follows - 1 where modid = ?', [$modid]);
						}

						good();
				}
		}
}
