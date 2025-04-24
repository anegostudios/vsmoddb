<?php

if (empty($user)) showErrorPage(HTTP_UNAUTHORIZED);

$view->assign('headerHighlight', HEADER_HIGHLIGHT_NOTIFICATIONS, null, true);
$view->display('notifications');