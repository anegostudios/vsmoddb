<?php

if ($user) {
	if ($user['actionToken'] != $_GET['at']) exit("invalid token");

	$con->execute("UPDATE users SET sessionValidUntil = NOW() WHERE userId = ?", [$user['userId']]);
}

header("Location: /");
exit();
