<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}
if ($user['rolecode'] != 'admin')  {
	$view->display("403");
	exit();
}

$view->assign("columns", array(array("cssclassname" => "", "code" => "code", "title" => "Code"), array("cssclassname" => "", "code" => "Name", "title" => "Name")));
$view->assign("entrycode", "tag");
$view->assign("entryplural", "Connection types");
$view->assign("entrysingular", "Connection type");

$tagid = empty($_REQUEST["tagid"]) ? 0 : $_REQUEST["tagid"];

$save = !empty($_POST["save"]);
$delete =!empty($_POST["delete"]);

if (($save || $delete) && $user['actiontoken'] != $_REQUEST['at']) {
	$view->assign("reason", "Invalid action token. To prevent CSRF, you can only submit froms directly on the site. If you believe this is an error, please contact Rennorb");
	$view->display("400");
	exit();
}

if ($save) {
	$isnew = false;
	
	if (!$tagid) {
		$isnew = true;
		
		$tagid = insert("tag");
		$view->assign("okmessage", "Tag created.");
	} else {
		$view->assign("okmessage", "Tag saved.");
	}
	
	update("tag", $tagid, array("name" => $_POST["name"], "text" => $_POST["text"], "color" => $_POST["code"], "assettypeid" => $_POST["assettypeid"]));
	
	if (!empty($_POST['saveandback'])) {
		header("Location: /list/tag?saved=1");
		exit();
	} else {
		if ($isnew) {
			header("Location: /edit/tag?tagid=$tagid");
			exit();
		}
	}
}

if ($delete) {
	$con->Execute("delete from tag where tagid=?", array($tagid));
	header("Location: /list/tag?deleted=1");
	exit();
}

if ($tagid) {
	$row = $con->getRow("select * from tag where tagid=?", array($_REQUEST["tagid"]));
} else {
	$row = array("tagid" => 0, "name" => "", "text" => "", "color" => "", "assettypeid" => ""); //, "tagtypeid" => ""
}

$view->assign("assettypes", $con->getAll("select * from assettype order by name"));
$view->assign("tagtypes", $con->getAll("select * from tagtype order by name"));


$view->assign("row", $row);
$view->display("edit-tag");
