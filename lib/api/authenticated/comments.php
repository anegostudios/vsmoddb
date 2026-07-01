<?php

/** @var array $user */
/** @var array $config */
/** @var object $con */

if(empty($urlparts)) {
	fail(HTTP_BAD_REQUEST);
}

$commentId = filter_var($urlparts[0], FILTER_VALIDATE_INT);
if($commentId === false) fail(HTTP_BAD_REQUEST, 'Malformed query param.');

switch($urlparts[1] ?? null) {
	case 'report':
		validateMethod('PUT');
		validateUserNotBanned();

		list($_POST, $_) = request_parse_body();
		if(!empty($_POST['at']) && empty($_REQUEST['at'])) $_REQUEST['at'] = $_POST['at'];

		validateActionTokenAPI();

		require $config['basepath'] . 'lib/moderation.php';

		$category = filter_input(INPUT_POST, 'category', FILTER_VALIDATE_INT, [ 'options' => [ 'min' => 0, 'max' => 3 ]]); // :MaxReportCategoryComment
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
			 FROM moderationRequests WHERE (referenceId, kind, category, initiatorUserId) = ($commentId, ".MOD_REQUEST_KIND_REPORT_COMMENT.", $category, {$user['userId']}) 
			   AND (resolved IS NULL OR resolved >= DATE_SUB(NOW(), INTERVAL ".COMMENT_REPORT_DEDUPLICATION_TIMESPAN.' DAY))'
		); // @security: $modId, $category and $user['userId'] are all validated to be int, therefore sql inert.

		if($previousRequest) {
			fail(HTTP_TOO_MANY_REQUESTS, $previousRequest['resolved']
				? "You have already reported this comment for the same reason some time ago, which has been resolved <a href='/t/{$previousRequest['requestId']}' target='_blank'>here</a>."
				: "You have already reported this comment for the same reason some time ago, which can be viewed <a href='/t/{$previousRequest['requestId']}' target='_blank'>here</a>."
			);
		}

		$requestsInLast7Days = $con->getOne('SELECT COUNT(*) FROM moderationRequests WHERE kind = '.MOD_REQUEST_KIND_REPORT_COMMENT." AND initiatorUserId = {$user['userId']} AND created >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
		if($requestsInLast7Days > COMMENT_REPORT_LIMIT_PER_WEEK) {
			fail(HTTP_TOO_MANY_REQUESTS, "You have reached your allotted quota for reporting comments this week. Your reports can be found <a href='/t/u/self' target='_blank'>here</a>.");
		}

		$con->startTrans();

		$con->execute(
			"INSERT INTO moderationRequests (referenceId, kind, category, initiatorUserId, request, requestSearchable)
			 VALUES ($commentId, ".MOD_REQUEST_KIND_REPORT_COMMENT.", $category, {$user['userId']}, ?, ?)",
			[$requestHtml, $requestSearchable]
		);
		$requestId = intval($con->insert_ID());

		logAuditEvent(AUDIT_LOG_KIND_REPORT_CREATE, $requestId);

		$ok = $con->completeTrans();
		if(!$ok) fail(HTTP_INTERNAL_ERROR, 'Internal database error.');

		header('Location: /t/'.$requestId, true, HTTP_CREATED);
		exit();

	case null:
		switch($_SERVER['REQUEST_METHOD']) {
			case 'POST':
				validateActionTokenAPI();
				validateUserNotBanned();
				validateContentType('text/html');
		
				$comment = $con->getRow('SELECT assetId, userId, text FROM comments WHERE commentId = ? AND !deleted', [$commentId]);
				if(!$comment)  fail(HTTP_NOT_FOUND, 'Unknown commentid.');
		
				$wasModAction = $user['userId'] != $comment['userId'];
				if($wasModAction && !canModerate(null, $user))  fail(HTTP_FORBIDDEN);
		
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
		
				$diff = createAuditLogDiff($comment['text'], $commentHtml);
		
				$con->startTrans();
		
				if($wasModAction) {
					//TODO(Rennorb): Diff the strings and add the diff to the log.
					$lastModAction = logModeratorAction($comment['userId'], $user['userId'], MODACTION_KIND_EDIT, $commentId, SQL_DATE_FOREVER, null);
		
					$con->execute('UPDATE comments SET text = ?, textShort = ?, lastModaction = ?, contentLastModified = NOW() WHERE commentId = ?', [$commentHtml, $commentTextShort, $lastModAction, $commentId]);
				}
				else {
					$con->execute('UPDATE comments SET text = ?, textShort = ?, contentLastModified = NOW() WHERE commentId = ?', [$commentHtml, $commentTextShort, $commentId]);
				}
		
				logAuditEvent(AUDIT_LOG_KIND_COMMENT_EDIT, $commentId, $diff, $wasModAction ? AUDIT_LOG_FLAG_MODACTION : 0);
		
				$con->completeTrans();
		
				good(['html' => postprocessCommentHtml($commentHtml)]);
		
			case 'DELETE':
				validateActionTokenAPI();
				validateUserNotBanned();
		
				$comment = $con->getRow(<<<SQL
					SELECT c.assetId, c.userId, a.createdByUserId AS modCreatedBy
					FROM comments c
					JOIN assets a ON a.assetId = c.assetId
					WHERE c.commentId = ? AND !c.deleted
				SQL, [$commentId]);
				if(!$comment)  fail(HTTP_NOT_FOUND, 'Unknown commentid.');
		
				$wasModAction = $user['userId'] != $comment['userId'];
				//NOTE(Rennorb): Mod authors can also "moderate" their comments by deleting them.
				//TODO(Rennorb): Fine grained team member permissions to inherit this capability to certain team members.
				if($wasModAction && !canModerate(null, $user) && $comment['modCreatedBy'] != $user['userId'])  fail(HTTP_FORBIDDEN);
		
				$con->startTrans();
		
				if($wasModAction) {
					$lastModAction = logModeratorAction($comment['userId'], $user['userId'], MODACTION_KIND_DELETE, $commentId, SQL_DATE_FOREVER, null);
			
					$con->Execute('UPDATE comments SET deleted = 1, lastModaction = ? WHERE commentId = ?', [$lastModAction, $commentId]);
					$con->Execute('UPDATE mods SET comments = comments - 1 WHERE assetId = ?', [$comment['assetId']]);
				
					$changelog = "Deleted comment #$commentId of user #{$user['userId']}";
				}
				else {
					$con->Execute('UPDATE comments SET deleted = 1 WHERE commentId = ?', [$commentId]);
					$con->Execute('UPDATE mods SET comments = comments - 1 WHERE assetId = ?', [$comment['assetId']]);
			
					$changelog = "User #{$user['userId']} deleted own comment #$commentId";
				}
		
				logAuditEvent(AUDIT_LOG_KIND_COMMENT_DELETE, $commentId, null, $wasModAction ? AUDIT_LOG_FLAG_MODACTION : 0);
		
				// Mark notifications for this comment as read so they get hidden for the notified user.
				//NOTE(Rennorb): We could also delete them completely, but i opted to just "read" them. Arbitrary decision.
				$con->Execute("UPDATE notifications SET `read` = 1 WHERE kind IN (".NOTIFICATION_MENTIONED_IN_COMMENT.", ".NOTIFICATION_NEW_COMMENT.','.NOTIFICATION_RESPONDED_TO_COMMENT.") AND recordId = ?", [$commentId]);
		
				$con->completeTrans();
		
				good();
		
			default:
				header('Allow: POST, DELETE');
				fail(HTTP_WRONG_METHOD, 'invalid method.');
		}
}
