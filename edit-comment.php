<?php
global $config, $view, $con;
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
    if(empty($text)){
        exit();
    }

	if (!$commentid) {
		$isnew = true;
		$commentid = insert("comment");
		update("comment", $commentid, array("userid" => $user['userid'], "assetid" => $_POST["assetid"]));
		
		$con->Execute("update `mod` set comments=(select count(*) from comment where assetid=?) where assetid=?", array($_POST["assetid"], $_POST["assetid"]));

		$touserid = $con->getOne("select createdbyuserid from `asset` where assetid=?", array($_POST['assetid']));
		
		if ($user['userid'] != $touserid) {
			$notid = insert("notification");
			update("notification", $notid, array("userid" => $touserid, "type" => "newcomment", "recordid" => $commentid));
			
			$webhookurl = $con->getone("select commentwebhook from user where userid=?", array($touserid));
			$modAsset = getModIdAndName($con, $_POST["assetid"]);

			if(!empty($webhookurl) && $user["cwhFails"] < 5)
			{
                $linkurl = "[{$modAsset["modname"]}]({$config["serverurl"]}/show/mod/{$modAsset["modid"]}/#cmt-$commentid)";
                saveCommentWebhook($touserid, $linkurl, $user["name"], true);
			}
		}
		
		
		preg_match_all("#<span class=\"mention username\">(.*)</span>#Ui", $text, $matches);
		
		foreach ($matches[1] as $name) {
			$mentionedUser = $con->getRow("select userid,commentwebhook from user where name=?", array($name));
			$mentionUserID = $mentionedUser["userid"];
			if ($mentionUserID) {
				$notid = insert("notification");
				update("notification", $notid, array("userid" => $mentionUserID, "type" => "mentioncomment", "recordid" => $commentid));

				$webhookurl = $mentionedUser["commentwebhook"];
				$modAsset = getModIdAndName($con, $_POST["assetid"]);
				
				if(!empty($webhookurl) && $mentionedUser["cwhFails"] < 5)
				{
                    $linkurl = "[{$modAsset["modname"]}]({$config["serverurl"]}/show/mod/{$modAsset["modid"]}/#cmt-$commentid)";
                    saveCommentWebhook($mentionUserID, $linkurl, $user["name"], false);
				}
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


function getModIdAndName($con, $assetit){
	return $con->getRow("
	select
		mod.modid as modid,
		asset.name as modname 
	from
		`asset`
		join `mod` on (asset.assetid = mod.assetid)
	where asset.assetid=?", array($assetit));
}
