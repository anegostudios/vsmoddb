<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}
if (!$user['roleid']) {
	$view->display("403");
	exit();
}

$commentid = empty($_POST["commentid"]) ? 0 : $_POST["commentid"];

if (!empty($_POST["save"])) {
	if ($user['actiontoken'] != $_REQUEST['at']) {
		$view->assign("reason", "Invalid action token. To prevent CSRF, you can only submit froms directly on the site. If you believe this is an error, please contact Tyron");
		$view->display("400");
		exit();
	}
	
	$isnew = false;
	$text = sanitizeHtml($_POST["text"], array('safe'=>1));


	if (!$commentid) {
		$isnew = true;
		$commentid = insert("comment");
		update("comment", $commentid, array("userid" => $user['userid'], "assetid" => $_POST["assetid"]));
		
		$con->Execute("update `mod` set comments=(select count(*) from comment where assetid=?) where assetid=?", array($_POST["assetid"], $_POST["assetid"]));
		
		$touserid = $con->getOne("select createdbyuserid from `asset` where assetid=?", array($_POST['assetid']));
		if ($user['userid'] != $touserid) {
			$notid = insert("notification");
			update("notification", $notid, array("userid" => $touserid, "type" => "newcomment", "recordid" => $commentid));
		}
		
		
		preg_match_all("#<span class=\"mention username\">(.*)</span>#Ui", $text, $matches);
		
		foreach ($matches[1] as $name) {
			$userid = $con->getOne("select userid from user where name=?", array($name));
			
			if ($userid) {
				$notid = insert("notification");
				update("notification", $notid, array("userid" => $userid, "type" => "mentioncomment", "recordid" => $commentid));
			}
		}
		
		logAssetChanges(array("Added a new comment."), $_POST["assetid"]);
	} else {
		$cmt = $con->getRow("select assetid, userid, text from comment where commentid=?", array($commentid));
		$assetid = $cmt['assetid'];
		
		if ($user['userid'] != $cmt['userid'] && $user['rolecode'] != 'admin' && $user['rolecode'] != 'moderator') {
			$view->display("403");
			exit();
		}
		
		$changelog = array("Modified his comment.");
		if ($user['userid'] != $cmt['userid']) {
			$changelog = array("Modified someone else comment (".$cmt["text"].") => (".$text.")");
		}
		
		logAssetChanges($changelog, $assetid);
	}
	

	update("comment", $commentid, array("text" => $text));
	
	$row = $con->getRow("
		select 
			comment.*, 
			user.name as username 
		from 
			comment
			join user on (comment.userid = user.userid)
		where commentid=?
	", array($commentid));
	
	$row['created'] = fancyDate($row['created']);

	exit(json_encode(array("comment" => $row)));
}


