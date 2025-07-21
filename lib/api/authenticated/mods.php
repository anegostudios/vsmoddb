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
					SELECT m.assetid, a.createdByUserId
					FROM `mod` m
					JOIN Assets a ON a.assetId = m.assetid
					WHERE m.modid = ?
				SQL, [$modId]);
				$assetId = intval($modData['assetid']);
				if(!$assetId)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown modid.']);

				$commentHtml = trim(sanitizeHtml(file_get_contents('php://input')));
				if(!$commentHtml)  fail(HTTP_BAD_REQUEST, ['reason' => 'Comment must not be empty.']);

				$con->execute('INSERT INTO Comments (assetId, userId, text) VALUES (?, ?, ?)', [$assetId, $user['userId'], $commentHtml]);
				$commentId = $con->insert_ID();
				$con->execute('UPDATE `mod` SET comments = comments + 1 WHERE assetid = ?', [$assetId]);

				$creatorUserId = intval($modData['createdByUserId']);
				$currentUserId = intval($user['userId']);

				// Notifications for user mentions:
				if(preg_match_all('/user-hash="([a-z0-9]{20})"/i', $commentHtml, $rawMatches)) {
					$foldedHashes = implode(',', array_map(fn($h) => "UNHEX('$h')", $rawMatches[1]));
					// The mod author always gets sent their own notification, but users thend to reply to them by @ing them.
					// For this reason we take out any references to the mod author and ourself here (`user.userId not in ($creatorUserId, $currentUserId)`).

					// @security: $rawMatches are validated to be alphanumeric and therefore sql inert by the regex. $commentId, $currentUserId and $creatorUserId are known to be integers.
					$con->execute(<<<SQL
						INSERT INTO Notifications (kind, recordId, userId)
						SELECT 'mentioncomment', $commentId, u.userId
						FROM Users u
						WHERE u.`hash` IN ($foldedHashes)
							AND u.userId NOT IN ($creatorUserId, $currentUserId)
					SQL);
				}

				// Send notification about the new comment to the main mod author:
				//TODO(Rennorb): Send notifications to all opt-in contributors, requires adding config option and table changes.
				if($currentUserId !== $creatorUserId) { // Don't send a notification to ourself if we are the one commenting.
					// @security: $commentId and $creatorUserId are known to be integers.
					$con->execute("INSERT INTO Notifications (kind, recordId, userId) VALUES ('newcomment', $commentId, $creatorUserId)");
				}

				logAssetChanges(['Added a new comment.'], $assetId);

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
			SELECT m.assetid, a.createdByUserId
			FROM `mod` m
			JOIN Assets a ON a.assetId = m.assetid
			WHERE m.modid = ?
		SQL, [$modId]);
		if(!$modData) fail(HTTP_NOT_FOUND);

		$con->startTrans();
		// @security: assetid comes from the db and is an int, therefore sql inert. 
		$con->execute('UPDATE Assets SET statusId = '.STATUS_LOCKED.' WHERE assetId = '.$modData['assetid']);
		logAssetChanges(['Locked Mod for reason: '.$reason], $modData['assetid']);

		logModeratorAction($modData['createdByUserId'], $user['userId'], MODACTION_KIND_LOCK, $modId, SQL_DATE_FOREVER, $reason);

		// Just in case we have not "read" a corresponding review-request notification for the mod we are (re-)locking, mark it as read.
		// If we don't do this we we might not get new unlock requests. :BlockedUnlockRequest
		$con->execute("UPDATE Notifications SET `read` = 1 WHERE kind = 'modunlockrequest' AND userId = ? AND recordId = ?", [$user['userId'], $modId]);

		$con->execute("INSERT INTO Notifications (userId, kind, recordId) VALUES (?, 'modlocked', ?)", [$modData['createdByUserId'], $modId]);

		$ok = $con->completeTrans();
		if($ok) good();
		else fail(HTTP_INTERNAL_ERROR, ['error' => 'Internal database error.']);
}
