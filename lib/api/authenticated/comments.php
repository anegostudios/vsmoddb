<?php

//NOTE(Rennorb): Assume the user object exists.

if(empty($urlparts)) {
	fail(HTTP_BAD_REQUEST);
}

$commentId = filter_var($urlparts[0], FILTER_VALIDATE_INT);
if($commentId === false) fail(HTTP_BAD_REQUEST, ['reason' => 'Malformed query param.']);

switch($_SERVER['REQUEST_METHOD']) {
	case 'POST':
		validateActionTokenAPI();
		validateUserNotBanned();
		validateContentType('text/html');

		$comment = $con->getRow('SELECT assetId, userId, text FROM comments WHERE commentId = ? AND !deleted', [$commentId]);
		if(!$comment)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown commentid.']);

		$wasModAction = $user['userId'] != $comment['userId'];
		if($wasModAction && !canModerate(null, $user))  fail(HTTP_FORBIDDEN);

		$commentHtml = trim(sanitizeHtml(file_get_contents('php://input')));
		if(!$commentHtml)  fail(HTTP_BAD_REQUEST, ['reason' => 'Comment must not be empty.']);

		$con->startTrans();

		try {
			if($wasModAction) {
				$changelog = "Modified someone elses comment ({$comment['text']}) => ($commentHtml)";

				//TODO(Rennorb): Diff the strings and add the diff to the log.
				$lastModAction = logModeratorAction($comment['userId'], $user['userId'], MODACTION_KIND_EDIT, $commentId, SQL_DATE_FOREVER, null);

				$con->execute('UPDATE comments SET text = ?, lastModaction = ?, contentLastModified = NOW() WHERE commentId = ?', [$commentHtml, $lastModAction, $commentId]);
			}
			else {
				$changelog = "Modified their comment ({$comment['text']}) => ($commentHtml).";

				$con->execute('UPDATE comments SET text = ?, contentLastModified = NOW() WHERE commentId = ?', [$commentHtml, $commentId]);
			}
		} catch(ADODB_Exception $ex) {
			if($ex->getCode() === 1406) {
				$sizeKb = floor(strlen($commentHtml) / 1024);
				$reason = "Excessive size ({$sizeKb}KB).";
				if(contains($commentHtml, 'src="data:image')) $reason .= " Directly pasted images must be rather small. If you need a large image upload it to an external site and link to that.";
				fail(HTTP_BAD_REQUEST, ['reason' => $reason]);
			}
			else throw $ex;
		}

		logAssetChanges([$changelog], $comment['assetId']);

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
		if(!$comment)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown commentid.']);

		$wasModAction = $user['userId'] != $comment['userId'];
		//NOTE(Rennorb): Mod authors can also "moderate" their comments by deleting them.
		//TODO(Rennorb): Fine grained team member permissions to inherit this capability to certain team members.
		if($wasModAction && !canModerate(null, $user) && $comment['modCreatedBy'] != $user['userId'])  fail(HTTP_FORBIDDEN);

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
		logAssetChanges([$changelog], $comment['assetId']);
	
		// Mark notifications for this comment as read so they get hidden for the notified user.
		//NOTE(Rennorb): We could also delete them completely, but i opted to just "read" them. Arbitrary decision.
		$con->Execute("UPDATE notifications SET `read` = 1 WHERE kind IN (".NOTIFICATION_MENTIONED_IN_COMMENT.", ".NOTIFICATION_NEW_COMMENT.") AND recordId = ?", [$commentId]);

		good();

	default:
		header('Allow: POST, DELETE');
		fail(HTTP_WRONG_METHOD);
}