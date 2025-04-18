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

		// @security: $commentId is validated to be an integer
		$comment = $con->getRow("select assetid, userid, text from comment where commentid = $commentId and !deleted");
		if(!$comment)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown commentid.']);

		$wasModAction = $user['userid'] != $comment['userid'];
		if($wasModAction && !canModerate(null, $user))  fail(HTTP_FORBIDDEN);

		$commentText = trim(sanitizeHtml(file_get_contents('php://input')));
		if(!$commentText)  fail(HTTP_BAD_REQUEST, ['reason' => 'Comment must not be empty.']);

		if($wasModAction) {
			$changelog = "Modified someone elses comment (".$comment["text"].") => (".$text.")";

			//TODO(Rennorb): Diff the strings and add the diff to the log.
			$lastModAction = logModeratorAction($comment['userid'], $user['userid'], MODACTION_KIND_EDIT, SQL_DATE_FOREVER, null);

			$con->execute('update comment set text = ?, lastmodaction = ? where commentid = ?', [$commentText, $lastModAction, $commentId]);
		}
		else {
			$changelog = "Modified their comment.";

			$con->execute('update comment set text = ? where commentid = ?', [$commentText, $commentId]);
		}

		logAssetChanges([$changelog], $comment['assetid']);

		good();

	case 'DELETE':
		validateActionTokenAPI();
		validateUserNotBanned();

		// @security: $commentId is validated to be an integer
		$comment = $con->getRow("
			select comment.assetid, comment.userid, asset.createdbyuserid as modcreatedby
			from comment
			join asset on asset.assetid = comment.assetid
			where commentid = $commentId and !deleted
		");
		if(!$comment)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown commentid.']);

		$wasModAction = $user['userid'] != $comment['userid'];
		//NOTE(Rennorb): Mod authors can also "moderate" their comments by deleting them.
		//TODO(Rennorb): Fine grained team member permissions to inherit this capability to certain team members.
		if($wasModAction && !canModerate(null, $user) && $comment['modcreatedby'] != $user['userid'])  fail(HTTP_FORBIDDEN);

		if($wasModAction) {
			$lastModAction = logModeratorAction($comment['userid'], $user['userid'], MODACTION_KIND_DELETE, SQL_DATE_FOREVER, null);
	
			$con->Execute('update comment set deleted = 1, lastmodaction = ? where commentid = ?', [$lastModAction, $commentId]);
			// @security: $comment['assetid'] is validated to be an integer by way of being sourced from an int column.
			$con->Execute("update `mod` set comments = comments - 1 where assetid = {$comment["assetid"]}");
		
			$changelog = "Deleted comment #$commentId of user #{$user['userid']}";
		}
		else {
			// @security: $commentId is validated to be an integer
			$con->Execute("update comment set deleted = 1 where commentid = $commentId");
			// @security: $comment['assetid'] is validated to be an integer by way of being sourced from an int column.
			$con->Execute("update `mod` set comments = comments - 1 where assetid = {$comment["assetid"]}");
	
			$changelog = "User #{$user['userid']} deleted own comment #$commentId";
		}
		logAssetChanges([$changelog], $comment['assetid']);
	
		// Mark notifications for this comment as read so they get hidden for the notified user.
		//NOTE(Rennorb): We could also delete them completely, but i opted to just "read" them. Arbitrary decision.
		// @security: $commentId is validated to be an integer
		$con->Execute("update notification set `read` = 1 where `type` in ('mentioncomment', 'newcomment') and recordid = $commentId");

		good();

	default:
		header('Allow: POST, DELETE');
		fail(HTTP_WRONG_METHOD);
}