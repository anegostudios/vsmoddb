<?php

//NOTE(Rennorb): Assume the user object exists.

if(count($urlparts) < 2)   fail(HTTP_BAD_REQUEST);

$modId = filter_var($urlparts[0], FILTER_VALIDATE_INT);
if($modId === false) fail(HTTP_BAD_REQUEST, ['reason' => 'Malformed query param modid.']);

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

				$responseTo = filter_input(INPUT_GET, 'response-to', FILTER_VALIDATE_INT);
				if($responseTo) {
					$responseTarget = $con->getRow('SELECT COALESCE(conversationRoot, commentId) AS conversationRoot, responseDepth, userId FROM comments WHERE commentId = ?', [$responseTo]);
					if(!$responseTarget)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown response-to id.']);
				}
				else {
					$responseTarget = ['conversationRoot' => null, 'responseDepth' => -1, 'userId' => 0];
				}

				$commentHtml = trimHtml(sanitizeHtml(file_get_contents('php://input')));
				if(!$commentHtml)  fail(HTTP_BAD_REQUEST, ['reason' => 'Comment must not be empty.']);

				$textLen = strlen($commentHtml);
				if($textLen > 65535) { // TEXT column max length in comments.text
					$sizeKb = floor($textLen / 1024);
					$reason = "Excessive size ({$sizeKb}KB).";
					if(str_contains($commentHtml, 'src="data:image')) $reason .= " You cannot paste large images directly. If you need a large image, upload it to an external site and link to that.";
					fail(HTTP_BAD_REQUEST, ['reason' => $reason]);
				}

				$commentTextShort = mb_substr(textContent($commentHtml), 0, 255); // stored for comment replies

				$con->startTrans();

				$con->execute('INSERT INTO comments (assetId, responseTo, conversationRoot, responseDepth, userId, text, textShort) VALUES (?, ?, ?, ?, ?, ?, ?)',
					[$assetId, $responseTo ?: null, $responseTarget['conversationRoot'], min($responseTarget['responseDepth'] + 1, 255), $user['userId'], $commentHtml, $commentTextShort]
				);
				$commentId = $con->insert_ID();

				$con->execute('UPDATE mods SET comments = comments + 1 WHERE assetId = ?', [$assetId]);

				$creatorUserId = intval($modData['createdByUserId']);
				$currentUserId = intval($user['userId']);

				// Notifications for user mentions:
				if(preg_match_all('/user-hash="([a-z0-9]{20})"/i', $commentHtml, $rawMatches)) {
					$foldedHashes = implode(',', array_map(fn($h) => "UNHEX('$h')", $rawMatches[1]));
					// The mod author always gets sent their own notification, but users tend to reply to them by @ing them.
					// For this reason we take out any references to the mod author, ourself and the potentially responded to comment's author here (`user.userId not in ($creatorUserId, $currentUserId, $responseTarget['userId'])`).

					// @security: $rawMatches are validated to be alphanumeric and therefore sql inert by the regex. $commentId, $currentUserId, $creatorUserId and $responseTarget['userId'] are known to be integers.
					$con->execute("
						INSERT INTO notifications (kind, recordId, userId)
						SELECT ".NOTIFICATION_MENTIONED_IN_COMMENT.", $commentId, u.userId
						FROM users u
						WHERE u.`hash` IN ($foldedHashes)
							AND u.userId NOT IN ($creatorUserId, $currentUserId, {$responseTarget['userId']})
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

				// Send notification to the responded-to party:
				//NOTE(Rennorb): Not within the transaction. In case this fails for whatever reason we don't want to rewind the comment submission just because the notification didn't go.
				if($responseTo && $responseTarget['userId'] !== $creatorUserId && $responseTarget['userId'] !== $currentUserId) {
					// @security: $commentId and $responseTarget['userId'] are known to be integers.
					$con->execute("INSERT INTO notifications (kind, recordId, userId) VALUES (".NOTIFICATION_RESPONDED_TO_COMMENT.", $commentId, {$responseTarget['userId']})");
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
		$reason = trimHtml(sanitizeHtml($reason));
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
						if(!empty($_POST['at']) && empty($_REQUEST['at'])) $_REQUEST['at'] = $_POST['at'];

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
						if(!empty($_POST['at']) && empty($_REQUEST['at'])) $_REQUEST['at'] = $_POST['at'];

						validateUserNotBanned();
						validateActionTokenAPI();
		
						$prevData = $con->getRow(<<<SQL
							SELECT r.modId, r.assetId, a.createdByUserId, rr.reason AS retractionReason, lastRetractedBy.roleId IN (?,?) AS retractedByModerator
							FROM modReleases r
							JOIN assets a ON a.assetId = r.assetId
							LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
							LEFT JOIN users lastRetractedBy ON lastRetractedBy.userId = rr.lastModifiedBy
							WHERE r.releaseId = ?
						SQL, [ROLE_ADMIN, ROLE_MODERATOR, $releaseId]);
						if(!$prevData)   fail(HTTP_NOT_FOUND);

						$prevData['assetTypeId'] = ASSETTYPE_RELEASE;
						if(!canEditAsset($prevData, $user))   fail(HTTP_FORBIDDEN, ['reason' => 'You may not edit this release.']);

						if($modId !== $prevData['modId'])   fail(HTTP_BAD_REQUEST, ['reason' => 'Release does not belong to mod.']);

						// Moderators can overwrite retraction reasons.
						if($prevData['retractedByModerator'] && !canModerate(null, $user))   fail(HTTP_BAD_REQUEST, ['reason' => 'This release is already retracted.']);

						$reasonHtml = trimHtml(sanitizeHtml($_POST['reason']));

						if(empty(textContent($reasonHtml))) fail(HTTP_BAD_REQUEST, ['reason' => 'Missing reason.']);

						include($config['basepath'] . 'lib/edit-release.php');

						$con->startTrans();

						$con->execute(<<<SQL
							INSERT INTO modReleaseRetractions (releaseId, reason, lastModifiedBy) VALUES (?,?,?)
							ON DUPLICATE KEY UPDATE reason = VALUES(reason), lastModifiedBy = VALUES(lastModifiedBy)
						SQL, [$releaseId, $reasonHtml, $user['userId']]);

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
								(
									SELECT r.created
									FROM modReleases r
									LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
									WHERE r.modId = m.modId AND rr.reason IS NULL
									ORDER BY r.created DESC
									LIMIT 1
								),
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

	case 'tags':
		if(DISABLE_USER_TAGS) fail(HTTP_SERVICE_UNAVAILABLE, ['reason' => 'User tags are currently disabled.']);

		if(count($urlparts) === 2) {
			validateMethod('POST');
			validateUserNotBanned();
			validateActionTokenAPI();

			$tags = filter_input(INPUT_POST, 'tags', FILTER_UNSAFE_RAW, FILTER_FORCE_ARRAY);
			if($tags === null) fail(HTTP_BAD_REQUEST, ['reason' => 'Missing post param tags.']);

			$tags = array_filter(array_map('trim', $tags));
			if(empty($tags)) fail(HTTP_BAD_REQUEST, ['reason' => 'Malformed post param tags.']);

			$response = [];

			$con->startTrans();

			$preparedTagInsert = $con->prepare("
				INSERT INTO tags (kind, name, text, color)
					VALUES (".TAG_KIND_USER_DEFINED.", ?, '', 0x92C96AFF)
				ON DUPLICATE KEY UPDATE
					tagId = LAST_INSERT_ID(tagId) -- fix to be able to read the last insert id
			");

			foreach($tags as $tagName) {
				$con->execute($preparedTagInsert, [$tagName]);
				$response[$con->insert_ID()] = ['name' => $tagName, 'color' => '#92C96AFF'];
			}
			$allAddedTagIds = array_keys($response);

			// 1 - vote == -vote + 1, removes the old vote and sets it to 1
			$voteDiffsFromThisUser = $con->getAssoc("SELECT tagId, 1 - vote FROM modTagVotes WHERE modId = $modId AND  userId = {$user['userId']}");

			$foldedValues = '';
			foreach($allAddedTagIds as $tagId) {
				if($foldedValues) $foldedValues .= ',';
				$newVoteOrDiff = $voteDiffsFromThisUser[$tagId] ?? 1;
				$foldedValues .= "($modId, $tagId, $newVoteOrDiff)";
			}

			// Figure out which of hte tags already exist on the mod so we can later on figure out which ones to log as 'new':
			// @perf: Should we need to optimize this we could also obtain this information dynamically form the first vote on a tag.
			$tagsThatAreAlreadyOnThisMod = $con->getCol("SELECT tagId FROM modTags WHERE modId = $modId AND tagId IN (".implode(',', $allAddedTagIds).")");

			// :TagUpdateOrder
			$con->execute(<<<SQL
				INSERT INTO modTags (modId, tagId, votes)
					VALUES $foldedValues
				ON DUPLICATE KEY UPDATE
					votes = votes + VALUES(votes)
			SQL); // @security: All of these values are filtered to be integers, and therefore sql inert.

			$foldedValues = implode(",", array_map(fn($tagId) => "($modId, $tagId, {$user['userId']}, 1)", $allAddedTagIds));

			// :TagUpdateOrder
			$con->execute(<<<SQL
				INSERT INTO modTagVotes (modID, tagId, userId, vote)
					VALUES $foldedValues
				ON DUPLICATE KEY UPDATE
					vote = VALUES(vote)
			SQL); // @security: All of these values are filtered to be integers, and therefore sql inert.

			$newTagIds = array_diff($allAddedTagIds, $tagsThatAreAlreadyOnThisMod);
			if(!empty($newTagIds)) {
				$newTagNames = array_map(fn($t) => $t['name'], array_intersect_key($response, array_flip($newTagIds)));
				logAssetChanges(['Added '.formatGrammaticallyCorrectEnumeration(array_values($newTagNames)).' as tags to the mod.'], $con->getOne("SELECT assetId FROM mods WHERE modId = $modId"));
			}

			$ok = $con->completeTrans();
			if($ok) good($response, JSON_FORCE_OBJECT);
			else fail(HTTP_INTERNAL_ERROR, ['error' => 'Internal database error.']);
		}
		if(count($urlparts) === 4 && $urlparts[3] === 'vote') { // {modid}/tags/{tagid}/vote
			validateMethod('PUT');

			list($_POST, $_) = request_parse_body();
			if(!empty($_POST['at']) && empty($_REQUEST['at'])) $_REQUEST['at'] = $_POST['at'];

			validateUserNotBanned();
			validateActionTokenAPI();

			$tagId = filter_var($urlparts[2], FILTER_VALIDATE_INT);
			if($tagId === false) fail(HTTP_BAD_REQUEST, ['reason' => 'Malformed query param tagid.']);

			$vote = filter_input(INPUT_POST, 'vote', FILTER_VALIDATE_INT);
			if($vote === null) fail(HTTP_BAD_REQUEST, ['reason' => 'Missing post param vote.']);
			if($vote === false || $vote < -1 || $vote > 1) fail(HTTP_BAD_REQUEST, ['reason' => 'Malformed post param vote.']);

			$con->startTrans();

			//NOTE(Rennorb): If we update we also need to remove the previous vote - we cannot just votes + new vote.
			// Doing so would cause desyncing when going from -1 to +1 (or vice versa), as -1 + +1 = 0 instead of the +1 it should be. :TagUpdateOrder
			// @perf: This might be bad performance wise, but i couldn't determine anything concrete. need to keep an eye out.
			$con->execute(<<<SQL
				UPDATE modTags
				SET votes = votes - IFNULL((SELECT vote FROM modTagVotes WHERE (modId, tagId, userId) = ($modId, $tagId, {$user['userId']}) LIMIT 1), 0) + $vote
				WHERE (modId, tagId) = ($modId, $tagId)
			SQL); // @security: All of these values are filtered to be integers, and therefore sql inert.

			if(!$con->affected_rows()) {
				// In the case this tag didn't already exist on the mod, and the vote is positive, add it.
				//NOTE(Rennorb): We do this separately because update should be the very common case, but there is no update or insert, only insert or update.
				if($vote > 0) {
					// @security: All of these values are filtered to be integers, and therefore sql inert.
					$con->execute("INSERT INTO modTags (modId, tagId, votes) VALUES ($modId, $tagId, $vote)");
				}
			}

			// Update this table after the modTags table so we still have the old update for that. :TagUpdateOrder
			$con->execute(<<<SQL
				INSERT INTO modTagVotes (modId, tagId, userId, vote)
					VALUES ($modId, $tagId, {$user['userId']}, $vote)
				ON DUPLICATE KEY UPDATE
					vote = VALUES(vote)
			SQL); // @security: All of these values are filtered to be integers, and therefore sql inert.

			$ok = $con->completeTrans();
			if($ok) good();
			else fail(HTTP_INTERNAL_ERROR, ['error' => 'Internal database error.']);
		}
}
