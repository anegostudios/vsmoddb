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

		$comment = $con->getRow('SELECT assetId, userId, text FROM Comments WHERE commentId = ? AND !deleted', [$commentId]);
		if(!$comment)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown commentid.']);

		$wasModAction = $user['userid'] != $comment['userId'];
		if($wasModAction && !canModerate(null, $user))  fail(HTTP_FORBIDDEN);

		$commentHtml = trim(sanitizeHtml(file_get_contents('php://input')));
		if(!$commentHtml)  fail(HTTP_BAD_REQUEST, ['reason' => 'Comment must not be empty.']);

		if($wasModAction) {
			$changelog = "Modified someone elses comment ({$comment['text']}) => ($commentHtml)";

			//TODO(Rennorb): Diff the strings and add the diff to the log.
			$lastModAction = logModeratorAction($comment['userId'], $user['userid'], MODACTION_KIND_EDIT, $commentId, SQL_DATE_FOREVER, null);

			$con->execute('UPDATE Comments SET text = ?, lastModaction = ?, contentLastModified = NOW() WHERE commentId = ?', [$commentHtml, $lastModAction, $commentId]);
		}
		else {
			$changelog = "Modified their comment ({$comment['text']}) => ($commentHtml).";

			$con->execute('UPDATE Comments SET text = ?, contentLastModified = NOW() WHERE commentId = ?', [$commentHtml, $commentId]);
		}

		logAssetChanges([$changelog], $comment['assetId']);

		good(['html' => postprocessCommentHtml($commentHtml)]);

	case 'DELETE':
		validateActionTokenAPI();
		validateUserNotBanned();

		$comment = $con->getRow(<<<SQL
			SELECT c.assetId, c.userId, a.createdbyuserid AS modCreatedBy
			FROM Comments c
			JOIN asset a ON a.assetid = c.assetId
			WHERE c.commentId = ? AND !c.deleted
		SQL, [$commentId]);
		if(!$comment)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown commentid.']);

		$wasModAction = $user['userid'] != $comment['userId'];
		//NOTE(Rennorb): Mod authors can also "moderate" their comments by deleting them.
		//TODO(Rennorb): Fine grained team member permissions to inherit this capability to certain team members.
		if($wasModAction && !canModerate(null, $user) && $comment['modCreatedBy'] != $user['userid'])  fail(HTTP_FORBIDDEN);

		if($wasModAction) {
			$lastModAction = logModeratorAction($comment['userId'], $user['userid'], MODACTION_KIND_DELETE, $commentId, SQL_DATE_FOREVER, null);
	
			$con->Execute('UPDATE Comments SET deleted = 1, lastModaction = ? WHERE commentId = ?', [$lastModAction, $commentId]);
			$con->Execute('UPDATE `mod` SET comments = comments - 1 WHERE assetid = ?', [$comment["assetId"]]);
		
			$changelog = "Deleted comment #$commentId of user #{$user['userid']}";
		}
		else {
			$con->Execute('UPDATE Comments SET deleted = 1 WHERE commentId = ?', [$commentId]);
			$con->Execute('UPDATE `mod` SET comments = comments - 1 WHERE assetid = ?', [$comment["assetId"]]);
	
			$changelog = "User #{$user['userid']} deleted own comment #$commentId";
		}
		logAssetChanges([$changelog], $comment['assetId']);
	
		// Mark notifications for this comment as read so they get hidden for the notified user.
		//NOTE(Rennorb): We could also delete them completely, but i opted to just "read" them. Arbitrary decision.
		$con->Execute("UPDATE Notifications SET `read` = 1 WHERE kind IN ('mentioncomment', 'newcomment') AND recordId = ?", [$commentId]);

		good();

	default:
		header('Allow: POST, DELETE');
		fail(HTTP_WRONG_METHOD);
}