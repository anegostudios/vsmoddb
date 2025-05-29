<?php

if ($user) {
	validateActionToken();
	$con->execute('UPDATE user SET sessiontoken = NULL WHERE userid = ?', [$user['userid']]);
}

header("Location: /");