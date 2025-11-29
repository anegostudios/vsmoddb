<?php
if (DB_READONLY) {
	http_response_code(HTTP_SERVICE_UNAVAILABLE);
	exit('{"status": "error", "errormessage": "We are currently in readonly mode."}');
}

if (empty($user)) {
	header('Location: /login');
	exit();
}

if (!$user['roleId']) showErrorPage(HTTP_FORBIDDEN);

if ($user['isBanned']) showErrorPage(HTTP_FORBIDDEN, 'You are currently banned.');

if (!empty($_POST['upload']) && @$_FILES['file']) {
	$file = $_FILES['file'];
	
	if (empty($_REQUEST['assettypeid'])) {
		exit(json_encode(array('status' => 'error', 'errormessage' => 'Missing assettypeid')));
	}
	
	$res = processFileUpload($file, intval($_REQUEST['assettypeid']), intval($_REQUEST['assetid']), intval($_REQUEST['modId'] ?? 0));

	if(isset($res['modversion'])) $res['modversion'] = formatSemanticVersion($res['modversion']);
	
	exit(json_encode($res));
}