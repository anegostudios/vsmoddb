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
	$cmt = $con->getRow("select assetid, userid from comment where commentid=?", array($commentid));
	
	if ($user['userid'] != $cmt['userid'] && $user['rolecode'] != 'admin') {
		$view->display("403");
		exit();
	}
	
	$con->Execute("delete from comment where commentid=?", array($commentid));
	$con->Execute("update `mod` set comments=(select count(*) from comment where assetid=?) where assetid=?", array($cmt["assetid"], $cmt["assetid"]));
	
	logAssetChanges(array("Deleted comment of user " . $user['userid']), $cmt['assetid']);
	
	exit(json_encode(array("ok" => 1)));
}

exit(json_encode(array("ok" => 0)));


