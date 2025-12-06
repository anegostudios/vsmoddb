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

				$textLen = strlen($commentHtml);
				if($textLen > 65535) { // TEXT column max length in comments.text
					$sizeKb = floor($textLen / 1024);
					$reason = "Excessive size ({$sizeKb}KB).";
					if(contains($commentHtml, 'src="data:image')) $reason .= " You cannot paste large images directly. If you need a large image, upload it to an external site and link to that.";
					fail(HTTP_BAD_REQUEST, ['reason' => $reason]);
				}

				$con->startTrans();

				$con->execute('INSERT INTO comments (assetId, userId, text) VALUES (?, ?, ?)', [$assetId, $user['userId'], $commentHtml]);
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

				$ok = $con->completeTrans();
				if(!$ok)  fail(HTTP_INTERNAL_ERROR, ['reason' => 'Database error.']);

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
		validateMethod('POST');
		validateUserNotBanned();
		validateActionTokenAPI();
		if(!canModerate(null, $user)) fail(HTTP_FORBIDDEN);

		$reason = $_POST['reason'] ?? '';
		$reason = trim(sanitizeHtml($reason));
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

	case 'releases':
		switch($urlparts[2]) {
			case 'upload-limit':
				if(count($urlparts) !== 3)   fail(HTTP_BAD_REQUEST);
				
				switch($_SERVER['REQUEST_METHOD']) {
					case 'GET':
						validateUserNotBanned();
						validateActionTokenAPI();
						if(!canModerate(null, $user)) fail(HTTP_FORBIDDEN);

						//NOTE(Rennorb): Can't use getOne here because there would be no difference between 'not found' and 'no overwrite'.
						$modData = $con->getRow('SELECT uploadLimitOverwrite FROM mods WHERE modId = ?', [$modId]);
						if(!$modData) fail(HTTP_NOT_FOUND);

						good($modData['uploadLimitOverwrite']);

					case 'PUT':
						list($_POST, $_) = request_parse_body();
						if($_POST['at'] && empty($_REQUEST['at'])) $_REQUEST['at'] = $_POST['at'];

						validateUserNotBanned();
						validateActionTokenAPI();
						if(!canModerate(null, $user)) fail(HTTP_FORBIDDEN);

						if(empty($_POST['limit'])) $newLimit = null;
						else {
							$newLimit = intval($_POST['limit']);
							if($newLimit != $_POST['limit']) fail(HTTP_BAD_REQUEST, ['reason' => 'Malformed limit.']);
							if($newLimit > parseMaxUploadSizeFromIni()) fail(HTTP_BAD_REQUEST, ['reason' => 'The new limit is above the current server limit.']);
						}

						$con->startTrans();

						$assetId = $con->getOne('SELECT assetId FROM mods WHERE modId = ?', [$modId]);
						if(!$assetId) fail(HTTP_NOT_FOUND);

						$con->execute('UPDATE mods SET uploadLimitOverwrite = ? WHERE modId = ?', [$newLimit, $modId]);
						logAssetChanges(['Changed release upload limit to '.formatByteSize($newLimit)], $assetId);

						$ok = $con->completeTrans();
						if($ok) good();
						else fail(HTTP_INTERNAL_ERROR, ['error' => 'Internal database error.']);

					default:
						header('Allow: GET, PUT');
						fail(HTTP_WRONG_METHOD);
				}

			default: // actions targeting a specific release
				$releaseId = filter_var($urlparts[2], FILTER_VALIDATE_INT);
				if($releaseId === false)  fail(HTTP_BAD_REQUEST, ['reason' => 'Malformed query param.']);

				if(count($urlparts) !== 4)   fail(HTTP_BAD_REQUEST);

				switch($urlparts[3]) {
					case 'retraction':
						validateMethod('PUT');

						list($_POST, $_) = request_parse_body();
						if($_POST['at'] && empty($_REQUEST['at'])) $_REQUEST['at'] = $_POST['at'];

						validateUserNotBanned();
						validateActionTokenAPI();
		
						$prevData = $con->getRow(<<<SQL
							SELECT r.modId, r.assetId, a.createdByUserId, r.retractionReason
							FROM modReleases r
							JOIN assets a ON a.assetId = r.assetId
							WHERE r.releaseId = ?
						SQL, [$releaseId]);
						if(!$prevData)   fail(HTTP_NOT_FOUND);

						$prevData['assetTypeId'] = ASSETTYPE_RELEASE;
						if(!canEditAsset($prevData, $user))   fail(HTTP_FORBIDDEN, ['reason' => 'You may not edit this release.']);

						if($modId !== $prevData['modId'])   fail(HTTP_BAD_REQUEST, ['reason' => 'Release does not belong to mod.']);

						// Moderators can overwrite retraction reasons.
						if(!canModerate(null, $user) && $prevData['retractionReason'])   fail(HTTP_BAD_REQUEST, ['reason' => 'This release is already retracted.']);

						$reasonHtml = trim(sanitizeHtml($_POST['reason']));

						if(empty(textContent($reasonHtml))) fail(HTTP_BAD_REQUEST, ['reason' => 'Missing reason.']);

						include($config['basepath'] . 'lib/edit-release.php');

						$con->startTrans();

						$con->execute('UPDATE modReleases SET retractionReason = ? WHERE releaseId = ?', [$reasonHtml, $releaseId]);

						//TODO(Rennorb) @correctness: Remove / hide unread release notifications for deleted releases.
						// We cannot remove notifications for deleted releases trivially like we do with comment notifications because release notifications are tracked by modid, not by releaseid.
						// Since we only have the modid in the notification entry we could run into the following scenario:
						// 1. new release 1 for mod 1 -> notification 1 (unread)
						// 2. new release 2 for mod 1 -> notification 2 (unread)
						// 3. delete release 2 -> we would delete both notifications even though only one should be removed, because both of them are tracked by the same modid
						// I think it is possible to figure out a solution to this using the creation dates for releases and notifications, or change the notifications to be tracking releaseid instead of modid.
						// Both of those would however be a larger change, and right now I'm just supplying a small fix for notifications.
						// For now we just let these "invalid" notifications exist, as to not potentially remove valid ones which would be a lot worse.

						updateGameVersionsCached($modId);

						// Reset lastReleased to the last release, or the mod creation date if there is no other release.
						$con->execute(<<<SQL
							UPDATE mods m
							SET lastReleased = IFNULL(
								(SELECT r.created FROM modReleases r WHERE r.modId = m.modId AND r.retractionReason IS NULL ORDER BY r.created DESC LIMIT 1),
								m.created
							)
							WHERE m.modId = ?;
						SQL, [$modId]);

						// have to get rid of the images for size reasons.
						//TODO(Rennorb) @cleanup @correctness: This should just get replaced with delta detection (kinda).
						function stripImageForChangelog($html)
						{
							return preg_replace('#src="data:image/png;base64,.*?"#', 'src="x"', $html); //TODO(Rennorb): @perf
						}

						$log = !$prevData['retractionReason'] ? 'Retracted release.' : 'Changed retraction reason. Previous was: '.stripImageForChangelog($prevData['retractionReason']);
						logAssetChanges([$log], $prevData['assetId']);

						$ok = $con->completeTrans();
						if($ok) good();
						else fail(HTTP_INTERNAL_ERROR, ['error' => 'Internal database error.']);
				}
		}
}
