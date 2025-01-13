<?php

if ($user) {
	header("Location: /");
	exit();
}

if (!$sessiontoken) {
	if (empty($_GET['redir'])) {
		header("Location: https://account.vintagestory.at/?loginredir=mods");
	}
}

$response = sendPostData("webprofile", array("sessionkey" => $sessiontoken));
$jsonresponse = json_decode($response, true);



if (empty($jsonresponse["valid"])) {
	$view->assign("errormessage", "Hm, the auth server tells me your session is not valid. Please <a href=\"https://account.vintagestory.at/login?loginredir=mods\">log in again</>.", null, true);
	$view->display("error");
	
} else {
	$account = $jsonresponse;
	
	$userid = $con->getOne("select userid from user where uid=?", array($account['uid']));
	if (!$userid) $userid = insert("user");
	
	// If a user buys 2 accounts and switches emails, the below update will error due to duplicate email
	$con->Execute("update user set email='outdated' where email=? and uid!=?", array($account["email"], $account['uid']));
	
	update("user", $userid, array(
		"name" => $account["playername"],
		"email" => $account["email"],
		"uid" => $account["uid"],
		"actiontoken" => str_replace(array("=", "/", "+"), array("", "", ""), genShortToken()),
		"sessiontoken" => $sessiontoken,
		"sessiontokenvaliduntil" => date("Y-m-d H:i:s", time() + 14*24*3600)
	));
	
	header("Location: /");
	
}


function genShortToken() {
	return base64_encode(openssl_random_pseudo_bytes(8));
}	
