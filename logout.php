<?php

if ($user) {
	validateActionToken();

	$con->execute("UPDATE users SET sessionValidUntil = NOW() WHERE userId = ?", [$user['userId']]);
}

header("Location: /");
exit();
