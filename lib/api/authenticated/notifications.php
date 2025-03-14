<?php

//NOTE(Rennorb): Assume the user object exists.

if(empty($urlparts)) {
	$ids = $con->getCol('select notificationid from notification where `read` = 0 and userid = ?', [$user['userid']]);
	good($ids);
}

switch($urlparts[0]) {
	case 'all':
		fail(404);
		break;

	case 'clear':
		$ids = isset($_POST['ids']) && is_string($_POST['ids']) ? explode(',', $_POST['ids']) : ($_POST['ids'] ?? null);
		$ids = filter_var($ids, FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY);
		if(empty($ids)) fail(400, ['reason' => 'No valid ids provided.']);
		
		$foldedIds = implode(',', $ids);

		$idsWithoutPermission = $con->getCol("select notificationid from notification where notificationid in ($foldedIds) and userid != ?", [$user['userid']]);
		if(!empty($idsWithoutPermission)) fail(403, ['reason' => 'Invalid ids provided.', 'invalid_ids' => $idsWithoutPermission]);

		$con->execute("update notification set `read` = 1 where notificationid in ($foldedIds)");
		good();
}
