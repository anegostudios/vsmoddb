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
	$view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);
	showErrorPage(HTTP_UNAUTHORIZED, 'Hm, the auth server tells me your session is not valid. Please <a href="https://account.vintagestory.at/login?loginredir=mods">log in again</a>.', null, true);
}
else {
	$account = $jsonresponse;

	// If a user buys 2 accounts and switches emails, the below update will error due to duplicate email.
	//TODO(Rennorb) @security: does this allow an attacker to just remove a victims email?
	$con->execute("UPDATE user SET email = 'outdated' WHERE email = ? AND uid != ?", [$account['email'], $account['uid']]);

	$existingUserId = $con->getOne('SELECT userid FROM user WHERE uid = ?', [$account['uid']]);
	createOrUpdate('user', $existingUserId, [
		'name'                   => $account['playername'],
		'email'                  => $account['email'],
		'uid'                    => $account['uid'],
		'actiontoken'            => generateActionToken(),
		'sessiontoken'           => $sessiontoken,
		'sessiontokenvaliduntil' => date(SQL_DATE_FORMAT, time() + 14*24*3600)
	]);

	header('Location: /');
}


/** @return int */
function generateActionToken() {
	return unpack('Q', openssl_random_pseudo_bytes(8))[1];
}
