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

				$modData = $con->getRow('
					select m.assetid, a.createdbyuserid
					from `mod` m
					join asset a on a.assetid = m.assetid
					where modid = ?
				', [$modId]);
				$assetId = intval($modData['assetid']);
				if(!$assetId)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown modid.']);

				$commentHtml = trim(sanitizeHtml(file_get_contents('php://input')));
				if(!$commentHtml)  fail(HTTP_BAD_REQUEST, ['reason' => 'Comment must not be empty.']);

				$con->execute('insert into comment (assetid, userid, text, created) values (?, ?, ?, now())', [$assetId, $user['userid'], $commentHtml]);
				$commentId = $con->insert_ID();
				$con->execute('update `mod` set comments = comments + 1 where assetid = ?', [$assetId]);

				$creatorUserId = intval($modData['createdbyuserid']);
				$currentUserId = intval($user['userid']);

				// Notifications for user mentions:
				if(preg_match_all('/user-hash="([a-z0-9]{20})"/i', $commentHtml, $rawMatches)) {
					$foldedHashes = implode(',', array_map(fn($h) => "'$h'", $rawMatches[1]));
					// The mod author always gets sent their own notification, but users thend to reply to them by @ing them.
					// For this reason we take out any references to the mod author and ourself here (`user.userid not in ($creatorUserId, $currentUserId)`).

					// @security: $rawMatches are validated to be alphanumeric and therefore sql inert by the regex. $commentId, $currentUserId and $creatorUserId are known to be integers.
					$con->execute("
						insert into notification (type, recordid, userid)
						select 'mentioncomment', $commentId, userid
						from user
						where
							substring(sha2(concat(user.userid, user.created), 512), 1, 20) in ($foldedHashes)
							and user.userid not in ($creatorUserId, $currentUserId)
					");
				}

				// Send notification about the new comment to the main mod author:
				//TODO(Rennorb): Send notifications to all opt-in contributors, requires adding config option and table changes.
				if($currentUserId !== $creatorUserId) { // Don't send a notification to ourself if we are the one commenting.
					// @security: $commentId and $creatorUserId are known to be integers.
					$con->execute("insert into notification (type, recordid, userid) values ('newcomment', $commentId, $creatorUserId)");
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

		$modData = $con->getRow('
			select m.assetid, a.createdbyuserid
			from `mod` m
			join asset a on a.assetid = m.assetid
			where m.modid = ?
		', [$modId]);
		if(!$modData) fail(HTTP_NOT_FOUND);

		$con->startTrans();
		// @security: assetid comes from the db and is an int, therefore sql inert. 
		$con->execute('update asset set statusid = '.STATUS_LOCKED.' where assetid = '.$modData['assetid']);
		logAssetChanges(['Locked Mod for reason: '.$reason], $modData['assetid']);

		logModeratorAction($modData['createdbyuserid'], $user['userid'], MODACTION_KIND_LOCK, $modId, SQL_DATE_FOREVER, $reason);

		// Just in case we have not "read" a corresponding review-request notification for the mod we are (re-)locking, mark it as read.
		// If we don't do this we we might not get new unlock requests. :BlockedUnlockRequest
		$con->execute("update notification set `read` = 1 where type = 'modunlockrequest' and userid = ? and recordid = ?", [$user['userid'], $modId]);

		$con->execute("insert into notification (userid, type, recordid) values (?, 'modlocked', ?)", [$modData['createdbyuserid'], $modId]);

		$ok = $con->completeTrans();
		if($ok) good();
		else fail(HTTP_INTERNAL_ERROR, ['error' => 'Internal database error.']);
}
