<?php
if (empty($user)) {
	header("Location: /login");
	exit();
}

if (!$user['roleId']) showErrorPage(HTTP_FORBIDDEN);

if ($user['isBanned']) showErrorPage(HTTP_FORBIDDEN, 'You are currently banned.');

if (!empty($_POST["upload"]) && @$_FILES["file"]) {
	$file = $_FILES["file"];
	
	if (empty($_REQUEST['assettypeid'])) {
		exit(json_encode(array("status" => "error", "errormessage" => 'Missing assettypeid')));
	}
	
	$res = processFileUpload($file, $_REQUEST['assettypeid'], $_REQUEST["assetid"]);

	if(isset($res['modversion'])) $res['modversion'] = formatSemanticVersion($res['modversion']);
	
	exit(json_encode($res));
}