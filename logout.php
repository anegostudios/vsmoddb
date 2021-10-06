<?php

if ($user) {
	if ($user['actiontoken'] != $_GET['at']) exit("invalid token");

	update("user", $user['userid'], array("sessiontoken" => null));
}

header("Location: /");