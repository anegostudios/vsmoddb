<?php

/**
 * @var object $con
 * @var array $user
 * @var array $config
 * @var array<string> $urlparts
 */

const PREVIOUS_OWNER_HANDLING_PRESERVE = 0;
const PREVIOUS_OWNER_HANDLING_DEMOTE   = 1;
const PREVIOUS_OWNER_HANDLING_REMOVE   = 2;

if(count($urlparts) < 2)   fail(HTTP_BAD_REQUEST);

$modId = filter_var($urlparts[0], FILTER_VALIDATE_INT);
if($modId === false) fail(HTTP_BAD_REQUEST, 'Malformed query param modid.');

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
				if(!$assetId)  fail(HTTP_NOT_FOUND, 'Unknown modid.');

				$responseTo = filter_input(INPUT_GET, 'response-to', FILTER_VALIDATE_INT);
				if($responseTo) {
					$responseTarget = $con->getRow('SELECT COALESCE(conversationRoot, commentId) AS conversationRoot, responseDepth, userId FROM comments WHERE commentId = ?', [$responseTo]);
					if(!$responseTarget)  fail(HTTP_NOT_FOUND, 'Unknown response-to id.');
				}
				else {
					$responseTarget = ['conversationRoot' => null, 'responseDepth' => -1, 'userId' => 0];
				}

				$commentHtml = trimHtml(sanitizeHtml(file_get_contents('php://input')));
				if(!$commentHtml)  fail(HTTP_BAD_REQUEST, 'Comment must not be empty.');

				$textLen = strlen($commentHtml);
				if($textLen > 65535) { // TEXT column max length in comments.text
					$sizeKb = floor($textLen / 1024);
					$reason = "Excessive size ({$sizeKb}KB).";
					if(str_contains($commentHtml, 'src="data:image')) $reason .= " You cannot paste large images directly. If you need a large image, upload it to an external site and link to that.";
					fail(HTTP_BAD_REQUEST, $reason);
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

				logAuditEvent(AUDIT_LOG_KIND_COMMENT_CREATE, $commentId);

				$ok = $con->completeTrans();
				if(!$ok)  fail(HTTP_INTERNAL_ERROR, 'Database error.');

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
		if(!$reason) fail(HTTP_BAD_REQUEST, 'Reason must not be empty.');

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
		logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_STATUS, $modId, $reason, AUDIT_LOG_FLAG_MODACTION);

		logModeratorAction($modData['createdByUserId'], $user['userId'], MODACTION_KIND_LOCK, $modId, SQL_DATE_FOREVER, $reason);

		// Just in case we have not "read" a corresponding review-request notification for the mod we are (re-)locking, mark it as read.
		// If we don't do this we we might not get new unlock requests. :BlockedUnlockRequest
		$con->execute('UPDATE notifications SET `read` = 1 WHERE kind = '.NOTIFICATION_MOD_UNLOCK_REQUEST.' AND userId = ? AND recordId = ?', [$user['userId'], $modId]);

		$con->execute('INSERT INTO notifications (userId, kind, recordId) VALUES (?, '.NOTIFICATION_MOD_LOCKED.', ?)', [$modData['createdByUserId'], $modId]);

		$ok = $con->completeTrans();
		if($ok) good();
		else fail(HTTP_INTERNAL_ERROR, 'Internal database error.');

	case 'transfer':
		validateMethod('POST');
		validateUserNotBanned();
		validateActionTokenAPI();

		$newOwnerId = filter_input(INPUT_POST, 'newOwnerId', FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ]]);
		$transferAccepted = isset($_POST['accept']) ? (filter_input(INPUT_POST, 'accept', FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? -1) : null;
		if($newOwnerId === null && $transferAccepted === null)  fail(HTTP_BAD_REQUEST, "Missing either 'newOwnerId' or 'accepted'.");
		if($newOwnerId !== null && $transferAccepted !== null)  fail(HTTP_BAD_REQUEST, "You must only provide one of 'newOwnerId' or 'accepted'.");
		if($newOwnerId === false)  fail(HTTP_BAD_REQUEST, "Malformed parameter 'newOwnerId'.");
		if($transferAccepted === -1)  fail(HTTP_BAD_REQUEST, "Malformed parameter 'accepted'.");

		$mod = $con->getRow("
			SELECT m.assetId, a.createdByUserId, m.created, t.userId AS currentTransferUserId, t.name AS currentTransferUserName, n.notificationId
			FROM mods m
			JOIN assets a ON a.assetId = m.assetId
			LEFT JOIN notifications AS n ON n.kind = ".NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST." AND n.recordId = $modId AND !n.`read`
			LEFT JOIN users t ON t.userId = n.userId
			WHERE m.modId = $modId
		"); // @security $modId is validate to be int, therefor sql inert
		$mod['assetTypeId'] = ASSETTYPE_MOD;
		if(!$mod)  fail(HTTP_NOT_FOUND);

		if($transferAccepted !== null) {
			if($mod['currentTransferUserId'] != $user['userId']) fail(HTTP_BAD_REQUEST, 'This mod is not currently being transferred to you.');

			$con->startTrans();

			$con->execute("UPDATE notifications SET `read` = 1 WHERE notificationId = {$mod['notificationId']}");

			if($transferAccepted) {
				// swap owner and teammember that accepted in the teammembers table
				$con->execute(<<<SQL
					UPDATE modTeamMembers
					SET userId = {$mod['createdByUserId']}, canEdit = 1, created = '{$mod['created']}'
					WHERE modId = $modId AND userId = {$user['userId']}
				SQL);
				$con->execute("UPDATE assets SET createdByUserId = {$user['userId']} WHERE assetId = {$mod['assetId']}");

				$responseNotificationFlag = (1 << 30); // Use the 31st bit of the modId to indicate success :PackedTransferSuccess
				$auditLogFlag = AUDIT_LOG_FLAG_ACCEPTED;
			}
			else {
				$responseNotificationFlag = (0 << 30); // :PackedTransferSuccess
				$auditLogFlag = AUDIT_LOG_FLAG_REJECTED;
			}

			// Send notification to the original author:
			$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES ('.NOTIFICATION_MOD_OWNERSHIP_TRANSFER_RESOLVED.', ?, ?) ', [$mod['createdByUserId'], $modId | $responseNotificationFlag]);

			logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_OWNER_RESOLVED, $modId, null, $auditLogFlag);

			$con->completeTrans();

			good();
		}

		if(!canEditAsset($mod, $user, false))  fail(HTTP_FORBIDDEN);

		$bypassChecks = filter_input(INPUT_POST, 'immediate', FILTER_VALIDATE_BOOL);
		if($bypassChecks && !canModerate(null, $user))  fail(HTTP_FORBIDDEN, 'Only moderators can immediately transfer mods without a notification.');

		$previousOwnerHandling = filter_input(INPUT_POST, 'previousOwnerHandling', FILTER_UNSAFE_RAW);
		if(!$previousOwnerHandling) {
			$previousOwnerHandling = PREVIOUS_OWNER_HANDLING_PRESERVE;
		}
		else {
			if(!canModerate(null, $user))   fail(HTTP_BAD_REQUEST, 'Only moderators can specify previousOwnerHandling.');
			if(!$bypassChecks)   fail(HTTP_BAD_REQUEST, 'previousOwnerHandling requires immediate mode.');

			switch($previousOwnerHandling) {
				case 'preserve': $previousOwnerHandling = PREVIOUS_OWNER_HANDLING_PRESERVE; break;
				case 'demote'  : $previousOwnerHandling = PREVIOUS_OWNER_HANDLING_DEMOTE;   break;
				case 'remove'  : $previousOwnerHandling = PREVIOUS_OWNER_HANDLING_REMOVE;   break;
				default: fail(HTTP_BAD_REQUEST, "Malformed param previousOwnerHandling'. Must be one of 'preserve', 'demote' or 'remove'.");
			}
		}

		require_once($config['basepath'].'lib/mod.php');

		if(!$bypassChecks) {
			if($mod['currentTransferUserId']) {
				fail(HTTP_BAD_REQUEST, 'An invitation to transfer ownership has already been sent to '.($mod['currentTransferUserId'] == $newOwnerId ? 'this user.' : "'{$mod['currentTransferUserName']}'."));
			}
			if(!isTeamMember($modId, $newOwnerId)) {
				fail(HTTP_BAD_REQUEST, 'The new owner is not a team member of the mod.');
			}
		}
		else {
			// Got to at least check the user exists, even if we bypass other checks:
			if(!$con->getOne("SELECT 1 FROM users WHERE userId = $newOwnerId")) { // @security $newOwnerId is validate to be int, therefor sql inert
				fail(HTTP_BAD_REQUEST, 'The new owner does not exist.');
			}
		}

		$con->startTrans();

		if(!$bypassChecks) {
			$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES (?, ?, ?)', [NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST, $newOwnerId, $modId]);
			logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_OWNER_INITIATED, $modId, "{$mod['createdByUserId']}");
		}
		else if($mod['createdByUserId'] != $newOwnerId) {
			// Abort pending transfers:
			$con->execute("
				INSERT INTO auditLogs (kind, flags, referenceId, initiatorUserId)
					SELECT ".AUDIT_LOG_KIND_MOD_CHANGE_OWNER_RESOLVED.", ".AUDIT_LOG_FLAG_ABORTED.", $modId, {$user['userId']}
					FROM notifications
					WHERE (kind, recordId, `read`) = (".NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST.", $modId, 0)
			");
			$con->execute("UPDATE notifications SET `read` = 1 WHERE (kind, recordId, `read`) = (".NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST.", $modId, 0)");

			if($previousOwnerHandling === PREVIOUS_OWNER_HANDLING_REMOVE) {
				// Remove the new owner from team members if the entry exists, but don't swap it with the old owner:
				$con->execute("DELETE FROM modTeamMembers WHERE userId = $newOwnerId");
			}
			else {
				$canEdit = $previousOwnerHandling === PREVIOUS_OWNER_HANDLING_PRESERVE ? 1 : 0;
				// Swap owner and teammember that accepted in the teammembers table:
				$con->execute(<<<SQL
					UPDATE modTeamMembers
					SET userId = {$mod['createdByUserId']}, canEdit = $canEdit, created = '{$mod['created']}'
					WHERE modId = $modId AND userId = $newOwnerId
				SQL);

				if(!$con->affected_rows()) {
					// If the new owner was not a member before we need to create a new entry here:
					$con->execute("INSERT INTO modTeamMembers (modId, userId, canEdit) VALUES ($modId, {$mod['createdByUserId']}, $canEdit)");
				}
			}

			$con->execute("UPDATE assets SET createdByUserId = $newOwnerId WHERE assetId = {$mod['assetId']}");
			
			logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_OWNER_INITIATED, $modId, "{$mod['createdByUserId']}");
			logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_OWNER_RESOLVED, $modId, null, AUDIT_LOG_FLAG_ACCEPTED);
		}

		$ok = $con->completeTrans();
		if(!$ok) fail(HTTP_INTERNAL_ERROR, 'Internal database error.');

		good();

	case 'report':
		validateMethod('PUT');
		validateUserNotBanned();

		list($_POST, $_) = request_parse_body();
		if(!empty($_POST['at']) && empty($_REQUEST['at'])) $_REQUEST['at'] = $_POST['at'];

		validateActionTokenAPI();

		require $config['basepath'] . 'lib/moderation.php';

		$category = filter_input(INPUT_POST, 'category', FILTER_VALIDATE_INT, [ 'options' => [ 'min' => 0, 'max' => 9 ]]); // :MaxReportCategoryMod
		if($category === null)  fail(HTTP_BAD_REQUEST, 'Missing category.');
		if($category === false)  fail(HTTP_BAD_REQUEST, 'Malformed category.');

		$requestHtml = $_POST['reason'] ?? '';
		$requestHtml = trimHtml(sanitizeHtml($requestHtml));
		if(!$requestHtml) fail(HTTP_BAD_REQUEST, 'Reason must not be empty.');

		$requestSearchable = textContent($requestHtml);
		if(!$requestSearchable) fail(HTTP_BAD_REQUEST, 'Reason must not be empty.');
		if(strlen($requestSearchable) < 50) fail(HTTP_BAD_REQUEST, 'Reason not substantial.');



		$previousRequest = $con->getRow(
			"SELECT requestId, resolved
			 FROM moderationRequests WHERE (referenceId, kind, category, initiatorUserId) = ($modId, ".MOD_REQUEST_KIND_REPORT_MOD.", $category, {$user['userId']}) 
			   AND (resolved IS NULL OR resolved >= DATE_SUB(NOW(), INTERVAL ".MOD_REPORT_DEDUPLICATION_TIMESPAN.' DAY))'
		); // @security: $modId, $category and $user['userId'] are all validated to be int, therefore sql inert.

		if($previousRequest) {
			fail(HTTP_TOO_MANY_REQUESTS, 
				$previousRequest['resolved']
					? "You have already reported this mod for the same reason some time ago, which has been resolved <a href='/t/{$previousRequest['requestId']}' target='_blank'>here</a>."
					: "You have already reported this mod for the same reason some time ago, which can be viewed <a href='/t/{$previousRequest['requestId']}' target='_blank'>here</a>.",
			);
		}

		$requestsInLast7Days = $con->getOne('SELECT SUM(IF(category = '.REPORT_CATEGORY_MOD_LOW_EFFORT_AI.', '.MOD_REPORT_LIMIT_LOW_EFFORT_WEIGHT.', 1)) FROM moderationRequests WHERE kind = '.MOD_REQUEST_KIND_REPORT_MOD." AND initiatorUserId = {$user['userId']} AND created >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
		if($requestsInLast7Days > MOD_REPORT_LIMIT_PER_WEEK) {
			fail(HTTP_TOO_MANY_REQUESTS, "You have reached your allotted quota for reporting mods this week. Your reports can be found <a href='/t/u/self' target='_blank'>here</a>.");
		}

		$con->startTrans();

		$con->execute(
			"INSERT INTO moderationRequests (referenceId, kind, category, initiatorUserId, request, requestSearchable)
			 VALUES ($modId, ".MOD_REQUEST_KIND_REPORT_MOD.", $category, {$user['userId']}, ?, ?)",
			[$requestHtml, $requestSearchable]
		);
		$requestId = intval($con->insert_ID());

		logAuditEvent(AUDIT_LOG_KIND_REPORT_CREATE, $requestId);

		$ok = $con->completeTrans();
		if(!$ok) fail(HTTP_INTERNAL_ERROR, 'Internal database error.');

		header('Location: /t/'.$requestId, true, HTTP_CREATED);
		exit();

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
							if($newLimit != $_POST['limit']) fail(HTTP_BAD_REQUEST, 'Malformed limit.');
							if($newLimit > parseMaxUploadSizeFromIni()) fail(HTTP_BAD_REQUEST, 'The new limit is above the current server limit.');
						}

						$con->startTrans();

						$oldData = $con->getRow('SELECT uploadLimitOverwrite FROM mods WHERE modId = ?', [$modId]);
						if(!$oldData) fail(HTTP_NOT_FOUND);

						$con->execute('UPDATE mods SET uploadLimitOverwrite = ? WHERE modId = ?', [$newLimit, $modId]);

						$old = $oldData['uploadLimitOverwrite'] ? formatByteSize($oldData['uploadLimitOverwrite']) : 'default';
						$diff = createAuditLogDiff($old, formatByteSize($newLimit));
						logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_UPLOAD_LIMIT, $modId, $diff, AUDIT_LOG_FLAG_MODACTION);

						$ok = $con->completeTrans();
						if($ok) good();
						else fail(HTTP_INTERNAL_ERROR, 'Internal database error.');

					default:
						header('Allow: GET, PUT');
						fail(HTTP_WRONG_METHOD);
				}

			default: // actions targeting a specific release
				$releaseId = filter_var($urlparts[2], FILTER_VALIDATE_INT);
				if($releaseId === false)  fail(HTTP_BAD_REQUEST, 'Malformed query param.');

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
						if(!canEditAsset($prevData, $user))   fail(HTTP_FORBIDDEN, 'You may not edit this release.');

						if($modId !== $prevData['modId'])   fail(HTTP_BAD_REQUEST, 'Release does not belong to mod.');

						// Moderators can overwrite retraction reasons.
						if($prevData['retractedByModerator'] && !canModerate(null, $user))   fail(HTTP_BAD_REQUEST, 'This release is already retracted.');

						$reasonHtml = trimHtml(sanitizeHtml($_POST['reason']));

						if(empty(textContent($reasonHtml))) fail(HTTP_BAD_REQUEST, 'Missing reason.');

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

						$logFlags = canModerate(null, $user) ? AUDIT_LOG_FLAG_MODACTION : 0; // @correctness: this needs to filter out team members.
						if(!$prevData['retractionReason']) {
							logAuditEvent(AUDIT_LOG_KIND_RELEASE_RETRACT, $releaseId, $reasonHtml, $logFlags);
						}
						else {
							$diff = createAuditLogDiff($prevData['retractionReason'], $reasonHtml);
							logAuditEvent(AUDIT_LOG_KIND_RELEASE_CHANGE_RETRACTION, $releaseId, $diff, $logFlags);
						}

						$ok = $con->completeTrans();
						if($ok) good();
						else fail(HTTP_INTERNAL_ERROR, 'Internal database error.');
				}
		}

	case 'tags':
		if(DISABLE_USER_TAGS) fail(HTTP_SERVICE_UNAVAILABLE, 'User tags are currently disabled.');

		if(count($urlparts) === 2) {
			validateMethod('POST');
			validateUserNotBanned();
			validateActionTokenAPI();

			$tags = filter_input(INPUT_POST, 'tags', FILTER_UNSAFE_RAW, FILTER_FORCE_ARRAY);
			if($tags === null) fail(HTTP_BAD_REQUEST, 'Missing post param tags.');

			$tags = array_filter(array_map('trim', $tags));
			if(empty($tags)) fail(HTTP_BAD_REQUEST, 'Malformed post param tags.');

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

			if(count($allAddedTagIds) !== count($tagsThatAreAlreadyOnThisMod)) {
				$old = implode("\n", array_column(array_intersect_key($response, array_flip($tagsThatAreAlreadyOnThisMod)), 'name'));
				$new = implode("\n", array_column($response, 'name'));
				logAuditEvent(AUDIT_LOG_KIND_MOD_CHANGE_TAGS, $modId, createAuditLogDiff($old, $new));
			}

			$ok = $con->completeTrans();
			if($ok) good($response, JSON_FORCE_OBJECT);
			else fail(HTTP_INTERNAL_ERROR, 'Internal database error.');
		}
		if(count($urlparts) === 4 && $urlparts[3] === 'vote') { // {modid}/tags/{tagid}/vote
			validateMethod('PUT');

			list($_POST, $_) = request_parse_body();
			if(!empty($_POST['at']) && empty($_REQUEST['at'])) $_REQUEST['at'] = $_POST['at'];

			validateUserNotBanned();
			validateActionTokenAPI();

			$tagId = filter_var($urlparts[2], FILTER_VALIDATE_INT);
			if($tagId === false) fail(HTTP_BAD_REQUEST, 'Malformed query param tagid.');

			$vote = filter_input(INPUT_POST, 'vote', FILTER_VALIDATE_INT);
			if($vote === null) fail(HTTP_BAD_REQUEST, 'Missing post param vote.');
			if($vote === false || $vote < -1 || $vote > 1) fail(HTTP_BAD_REQUEST, 'Malformed post param vote.');

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
			else fail(HTTP_INTERNAL_ERROR, 'Internal database error.');
		}
}
