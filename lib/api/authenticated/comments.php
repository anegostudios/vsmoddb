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

		$comment = $con->getRow("SELECT assetid, commentid, userid, text FROM comment WHERE commentid = ? AND !deleted", [$commentId]);
		if(!$comment)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown commentid.']);

		$wasModAction = $user['userid'] != $comment['userid'];
		if($wasModAction && !canModerate(null, $user))  fail(HTTP_FORBIDDEN);

		$commentHtml = trim(sanitizeHtml(file_get_contents('php://input')));
		if(!$commentHtml)  fail(HTTP_BAD_REQUEST, ['reason' => 'Comment must not be empty.']);

		if($wasModAction) {
			$changelog = "Modified someone elses comment (".$comment["text"].") => (".$text.")";

			//TODO(Rennorb): Diff the strings and add the diff to the log.
			$lastModAction = logModeratorAction($comment['userid'], $user['userid'], MODACTION_KIND_EDIT, $comment['commentid'], SQL_DATE_FOREVER, null);

			$con->execute('UPDATE comment SET text = ?, lastmodaction = ? WHERE commentid = ?', [$commentHtml, $lastModAction, $commentId]);
		}
		else {
			$changelog = "Modified their comment.";

			$con->execute('UPDATE comment SET text = ? WHERE commentid = ?', [$commentHtml, $commentId]);
		}

		logAssetChanges([$changelog], $comment['assetid']);

		good(['html' => postprocessCommentHtml($commentHtml)]);

	case 'DELETE':
		validateActionTokenAPI();
		validateUserNotBanned();

		$comment = $con->getRow('
			SELECT comment.assetid, comment.commentid, comment.userid, asset.createdbyuserid AS modcreatedby
			FROM comment
			JOIN asset ON asset.assetid = comment.assetid
			WHERE commentid = ? AND !deleted
		', [$commentId]);
		if(!$comment)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown commentid.']);

		$wasModAction = $user['userid'] != $comment['userid'];
		//NOTE(Rennorb): Mod authors can also "moderate" their comments by deleting them.
		//TODO(Rennorb): Fine grained team member permissions to inherit this capability to certain team members.
		if($wasModAction && !canModerate(null, $user) && $comment['modcreatedby'] != $user['userid'])  fail(HTTP_FORBIDDEN);

		if($wasModAction) {
			$lastModAction = logModeratorAction($comment['userid'], $user['userid'], MODACTION_KIND_DELETE, $comment['commentid'], SQL_DATE_FOREVER, null);
	
			$con->Execute('UPDATE comment SET deleted = 1, lastmodaction = ? WHERE commentid = ?', [$lastModAction, $commentId]);
			$con->Execute('UPDATE `mod` SET comments = comments - 1 WHERE assetid = ?', [$comment["assetid"]]);
		
			$changelog = "Deleted comment #$commentId of user #{$user['userid']}";
		}
		else {
			$con->Execute('UPDATE comment SET deleted = 1 WHERE commentid = ?', [$commentId]);
			$con->Execute('UPDATE `mod` SET comments = comments - 1 WHERE assetid = ?', [$comment["assetid"]]);
	
			$changelog = "User #{$user['userid']} deleted own comment #$commentId";
		}
		logAssetChanges([$changelog], $comment['assetid']);
	
		// Mark notifications for this comment as read so they get hidden for the notified user.
		//NOTE(Rennorb): We could also delete them completely, but i opted to just "read" them. Arbitrary decision.
		$con->Execute("UPDATE Notifications SET `read` = 1 WHERE kind IN ('mentioncomment', 'newcomment') AND recordId = ?", [$commentId]);

		good();

	default:
		header('Allow: POST, DELETE');
		fail(HTTP_WRONG_METHOD);
}