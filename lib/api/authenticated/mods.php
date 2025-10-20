<?php

//NOTE(Rennorb): Assume the user object exists.

if(count($urlparts) < 2)   fail(HTTP_BAD_REQUEST);

$modId = filter_var($urlparts[0], FILTER_VALIDATE_INT);
if($modId === false) fail(HTTP_BAD_REQUEST, ['reason' => 'Malformed query param.']);

switch($urlparts[1]) {
	case 'comments':
		if(count($urlparts) !== 2)   fail(HTTP_BAD_REQUEST);

		switch($_SERVER['REQUEST_METHOD']) {
			case 'GET':
				//TODO return comment data for mod
				fail(HTTP_NOT_FOUND);

			case 'PUT':
				validateUserNotBanned();
				validateActionTokenAPI();
				validateContentType('text/html');

				$modData = $con->getRow(<<<SQL
					SELECT m.assetId, a.createdByUserId
					FROM mods m
					JOIN assets a ON a.assetId = m.assetId
					WHERE m.modId = ?
				SQL, [$modId]);
				$assetId = intval($modData['assetId']);
				if(!$assetId)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown modid.']);

				$commentHtml = trim(sanitizeHtml(file_get_contents('php://input')));
				if(!$commentHtml)  fail(HTTP_BAD_REQUEST, ['reason' => 'Comment must not be empty.']);

				$con->startTrans();

				try {
					$con->execute('INSERT INTO comments (assetId, userId, text) VALUES (?, ?, ?)', [$assetId, $user['userId'], $commentHtml]);
				} catch(ADODB_Exception $ex) {
					if($ex->getCode() === 1406) {
						$sizeKb = floor(strlen($commentHtml) / 1024);
						$reason = "Excessive size ({$sizeKb}KB).";
						if(contains($commentHtml, 'src="data:image')) $reason .= " Directly pasted images must be rather small. If you need a large image upload it to an external site and link to that.";
						fail(HTTP_BAD_REQUEST, ['reason' => $reason]);
					}
					else throw $ex;
				}
				$commentId = $con->insert_ID();
				$con->execute('UPDATE mods SET comments = comments + 1 WHERE assetId = ?', [$assetId]);

				$creatorUserId = intval($modData['createdByUserId']);
				$currentUserId = intval($user['userId']);

				// Notifications for user mentions:
				if(preg_match_all('/user-hash="([a-z0-9]{20})"/i', $commentHtml, $rawMatches)) {
					$foldedHashes = implode(',', array_map(fn($h) => "UNHEX('$h')", $rawMatches[1]));
					// The mod author always gets sent their own notification, but users thend to reply to them by @ing them.
					// For this reason we take out any references to the mod author and ourself here (`user.userId not in ($creatorUserId, $currentUserId)`).

					// @security: $rawMatches are validated to be alphanumeric and therefore sql inert by the regex. $commentId, $currentUserId and $creatorUserId are known to be integers.
					$con->execute("
						INSERT INTO notifications (kind, recordId, userId)
						SELECT ".NOTIFICATION_MENTIONED_IN_COMMENT.", $commentId, u.userId
						FROM users u
						WHERE u.`hash` IN ($foldedHashes)
							AND u.userId NOT IN ($creatorUserId, $currentUserId)
					");
				}

				logAssetChanges(['Added a new comment.'], $assetId);

				$con->completeTrans();

				// Send notification about the new comment to the main mod author:
				//TODO(Rennorb): Send notifications to all opt-in contributors, requires adding config option and table changes.
				//NOTE(Rennorb): Not within the transaction. In case this fails for whatever reason we don't want to rewind the comment submission just because the notification didn't go.
				if($currentUserId !== $creatorUserId) { // Don't send a notification to ourself if we are the one commenting.
					// @security: $commentId and $creatorUserId are known to be integers.
					$con->execute("INSERT INTO notifications (kind, recordId, userId) VALUES (".NOTIFICATION_NEW_COMMENT.", $commentId, $creatorUserId)");
				}

				header('Location: #cmt-'.$commentId, true, HTTP_CREATED);
				exit(postprocessCommentHtml($commentHtml));

			default:
				header('Allow: GET, PUT');
				fail(HTTP_WRONG_METHOD);
		}

	case 'lock':
		if($_SERVER['REQUEST_METHOD'] != 'POST') {
			header('Allow: GET, PUT');
			fail(HTTP_WRONG_METHOD);
		}

		validateUserNotBanned();
		validateActionTokenAPI();
		if(!canModerate(null, $user)) fail(HTTP_FORBIDDEN);

		$reason = $_POST['reason'] ?? '';
		$reason = htmlspecialchars(trim($reason));
		if(!$reason) fail(HTTP_BAD_REQUEST, ['error' => 'Reason must not be empty.']);

		$modData = $con->getRow(<<<SQL
			SELECT m.assetId, a.createdByUserId
			FROM mods m
			JOIN assets a ON a.assetId = m.assetId
			WHERE m.modId = ?
		SQL, [$modId]);
		if(!$modData) fail(HTTP_NOT_FOUND);

		$con->startTrans();
		// @security: assetid comes from the db and is an int, therefore sql inert. 
		$con->execute('UPDATE assets SET statusId = '.STATUS_LOCKED.' WHERE assetId = '.$modData['assetId']);
		logAssetChanges(['Locked Mod for reason: '.$reason], $modData['assetId']);

		logModeratorAction($modData['createdByUserId'], $user['userId'], MODACTION_KIND_LOCK, $modId, SQL_DATE_FOREVER, $reason);

		// Just in case we have not "read" a corresponding review-request notification for the mod we are (re-)locking, mark it as read.
		// If we don't do this we we might not get new unlock requests. :BlockedUnlockRequest
		$con->execute('UPDATE notifications SET `read` = 1 WHERE kind = '.NOTIFICATION_MOD_UNLOCK_REQUEST.' AND userId = ? AND recordId = ?', [$user['userId'], $modId]);

		$con->execute('INSERT INTO notifications (userId, kind, recordId) VALUES (?, '.NOTIFICATION_MOD_LOCKED.', ?)', [$modData['createdByUserId'], $modId]);

		$ok = $con->completeTrans();
		if($ok) good();
		else fail(HTTP_INTERNAL_ERROR, ['error' => 'Internal database error.']);
}
