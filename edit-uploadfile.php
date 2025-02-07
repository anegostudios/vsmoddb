<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}
if (!$user['roleid']) {
	$view->display("403");
	exit();
}

if ($user['isbanned']) {
	http_response_code(403);
	$view->assign('reason', 'You are currently banned.');
	$view->display("403");
	exit();
}

if (!empty($_POST["upload"]) && @$_FILES["file"]) {
	$file = $_FILES["file"];
	
	if (empty($_REQUEST['assettypeid'])) {
		exit(json_encode(array("status" => "error", "errormessage" => 'Missing assettypeid')));
	}
	
	$res = processFileUpload($file, $_REQUEST['assettypeid'], $_REQUEST["assetid"]);
	
	exit(json_encode($res));
}