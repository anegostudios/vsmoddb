<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}

if (!$user['roleid']) showErrorPage(HTTP_FORBIDDEN);

if ($user['isbanned']) showErrorPage(HTTP_FORBIDDEN, 'You are currently banned.');

if (!empty($_POST["upload"]) && @$_FILES["file"]) {
	$file = $_FILES["file"];
	
	if (empty($_REQUEST['assettypeid'])) {
		exit(json_encode(array("status" => "error", "errormessage" => 'Missing assettypeid')));
	}
	
	$res = processFileUpload($file, $_REQUEST['assettypeid'], $_REQUEST["assetid"]);
	
	exit(json_encode($res));
}