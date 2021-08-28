<?php

if ($user) {
	update("user", $user['userid'], array("sessiontoken" => null));
}

header("Location: /");