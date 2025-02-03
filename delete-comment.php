<?php
global $view, $con;
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

	if ($user['userid'] != $asset['createdbyuserid'] && $user['userid'] != $cmt['userid'] && $user['rolecode'] != 'admin' && $user['rolecode'] != 'moderator') {
    		$view->display("403");
		exit();
	}

    $con->Execute("delete from comment where commentid=?", array($commentid));
    $con->Execute("delete from notification where recordid=?", array($commentid));
	$con->Execute("update `mod` set comments=(select count(*) from comment where assetid=?) where assetid=?", array($cmt["assetid"], $cmt["assetid"]));
	
	$changelog = array("Deleted own comment");
	if ($user['userid'] != $cmt['userid']) {
		$changelog = array("Deleted comment (".$cmt["text"].") of user " . $user['userid'] . "\nComment text was:\n" . $cmt["text"]);
	}
	
	logAssetChanges($changelog, $cmt['assetid']);
	
	exit(json_encode(array("ok" => 1)));
}

exit(json_encode(array("ok" => 0)));


