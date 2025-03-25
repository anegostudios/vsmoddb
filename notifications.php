<?php

if (empty($user)) showErrorPage(HTTP_UNAUTHORIZED);

$view->display('notifications');