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

if (!empty($_POST["delete"])) {
	$cmt = $con->getRow("select assetid, userid, text from comment where commentid=?", array($commentid));
	$asset = $con->getRow("SELECT assetid, createdbyuserid FROM asset WHERE assetid=?", array($cmt['assetid']));

	$wasmodaction = $user['userid'] != $cmt['userid'];
	if ($user['userid'] != $asset['createdbyuserid'] && $wasmodaction && $user['rolecode'] != 'admin' && $user['rolecode'] != 'moderator') {
    		$view->display("403");
		exit();
	}

	if(!$wasmodaction) {
		$con->Execute("update comment set deleted=1 where commentid=?", array($commentid));
		$con->Execute("update `mod` set comments=(select count(*) from comment where assetid=? and deleted=0) where assetid=?", array($cmt["assetid"], $cmt["assetid"]));

		$changelog = array("Deleted own comment");
		logAssetChanges($changelog, $cmt['assetid']);
	}
	else {
		$modreason = $_POST["modreason"] ?: null;
		$modactionid = logModeratorAction($cmt['userid'], $user['userid'], null, $modreason);

		$con->Execute("update comment set deleted=1, lastmodaction=? where commentid=?", array($modactionid, $commentid));
		$con->Execute("update `mod` set comments=(select count(*) from comment where assetid=? and deleted=0) where assetid=?", array($cmt["assetid"], $cmt["assetid"]));
	
		$changelog = array("Deleted comment (".$cmt["text"].") of user " . $user['userid'] . "\nComment text was:\n" . $cmt["text"]);
		logAssetChanges($changelog, $cmt['assetid']);
	}
	
	exit(json_encode(array("ok" => 1)));
}

exit(json_encode(array("ok" => 0)));


