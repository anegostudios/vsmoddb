<?php

if ($user) {
	header('Location: /');
	exit();
}

if (!$sessionToken) {
	if (empty($_GET['redir'])) {
		header('Location: https://account.vintagestory.at/?loginredir=mods');
		exit();
	}
}

$profileRequestContext = stream_context_create([
	"http" => [
		"method"  => "POST",
		"header"  => "Content-type: application/x-www-form-urlencoded" . "\r\n",
		"content" => http_build_query(['sessionkey' => $sessionToken]),
	],
]);
$response = file_get_contents("https://{$config['authserver']}/webprofile", false, $profileRequestContext);
$response = json_decode($response, true);



if (empty($response['valid'])) {
	$view->assign('headerHighlight', HEADER_HIGHLIGHT_CURRENT_USER, null, true);
	showErrorPage(HTTP_UNAUTHORIZED, 'Hm, the auth server tells me your session is not valid. Please <a href="https://account.vintagestory.at/login?loginredir=mods">log in again</a>.', null, true);
}


$account = $response;
$actionToken = bin2hex(openssl_random_pseudo_bytes(8));

// If a user buys 2 accounts and switches emails, the below update will error due to duplicate email.
$con->execute("UPDATE users SET email = CONCAT(userId, '.outdated+', email) WHERE email = ? AND uid != FROM_BASE64(?)", [$account["email"], $account['uid']]);

if($userId = $con->getOne('SELECT userId from users where uid = FROM_BASE64(?)', [$account['uid']])) {
	// update existing user
	$con->execute(<<<SQL
		UPDATE users
		SET name = ?, email = ?, uid = FROM_BASE64(?), actionToken = UNHEX(?), sessionToken = FROM_BASE64(?), sessionValidUntil = DATE_ADD(NOW(), INTERVAL 14 DAY)
		WHERE userId = ?
	SQL, [$account['playername'], $account['email'], $account['uid'], $actionToken, $sessionToken, $userId]);
}
else {
	// new user
	$con->execute(<<<SQL
		INSERT INTO users (name, email, uid, actionToken, sessionToken, sessionValidUntil, hash, timezone)
		VALUES (?, ?, FROM_BASE64(?), UNHEX(?), FROM_BASE64(?), DATE_ADD(NOW(), INTERVAL 14 DAY), '\0', '(GMT) London')
	SQL, [$account['playername'], $account['email'], $account['uid'], $actionToken, $sessionToken]);
	//TODO(Rennorb) @perf @cleanup: try to wrangle this into one query
	$con->execute(<<<SQL
		UPDATE users
		SET hash = UNHEX(SUBSTRING(SHA2(CONCAT(userId, created), 512), 1, 20))
		WHERE uid = FROM_BASE64(?) AND hash = '\0'
		LIMIT 1
	SQL, [$account['uid']]);
}

header('Location: /');
exit();
