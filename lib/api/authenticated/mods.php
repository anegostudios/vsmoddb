<?php

//NOTE(Rennorb): Assume the user object exists.

if(count($urlparts) < 2) {
	fail(HTTP_BAD_REQUEST);
}

$modId = filter_var($urlparts[0], FILTER_VALIDATE_INT);
if($modId === false) fail(HTTP_BAD_REQUEST, ['reason' => 'Malformed query param.']);

switch($urlparts[1]) {
	case 'comments':
		switch(count($urlparts)) {
			case 2:
				//TODO return comment data for mod
				fail(HTTP_NOT_FOUND);

			case 3:
				if($urlparts[2] !== 'new')  fail(HTTP_NOT_FOUND);
				validateMethod('POST');
				validateUserNotBanned();
				validateActionTokenAPI();
				validateContentType('text/html');

				$assetId = intval($con->getOne('select assetid from `mod` where modid = ?', [$modId]));
				if(!$assetId)  fail(HTTP_NOT_FOUND, ['reason' => 'Unknown modid.']);

				$commentHtml = trim(sanitizeHtml(file_get_contents('php://input')));
				if(!$commentHtml)  fail(HTTP_BAD_REQUEST, ['reason' => 'Comment must not be empty.']);

				$con->execute('insert into comment (assetid, userid, text, created) values (?, ?, ?, now())', [$assetId, $user['userid'], $commentHtml]);
				$commentId = $con->insert_ID();
				$con->execute('update `mod` set comments = comments + 1 where assetid = ?', [$assetId]);

				// user mentions
				if(preg_match_all('/user-hash="([a-z0-9]{20})"/i', $commentHtml, $rawMatches)) {
					// @security: $rawMatches are validated to be alphanumeric and therefore sql inert by the regex. $commentId is known to be an integer
					$foldedHashes = implode(',', array_map(fn($h) => "'$h'", $rawMatches[1]));
					$con->execute("
						insert into notification (type, recordid, userid)
						select 'mentioncomment', $commentId, userid
						from user
						where substring(sha2(concat(user.userid, user.created), 512), 1, 20) in ($foldedHashes)
					");
				}

				logAssetChanges(['Added a new comment.'], $assetId);

				good(['id' => $commentId, 'html' => postprocessCommentHtml($commentHtml)]);
		}
}
