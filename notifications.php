<?php

if (empty($user)) {
	$view->display("401");
	exit();
}

$view->display('notifications');