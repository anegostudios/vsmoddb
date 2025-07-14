<?php

if ($user) {
	if ($user['actionToken'] != $_GET['at']) exit("invalid token");

	$con->execute("UPDATE Users SET sessionToken = '\0' WHERE userId = ?", [$user['userId']]);
}

header("Location: /");