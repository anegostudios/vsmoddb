<?php

if (empty($user)) showErrorPage(HTTP_UNAUTHORIZED);

cspReplaceAllowedFetchSources("{$_SERVER['HTTP_HOST']}/api/v2/notifications/");

$view->assign('headerHighlight', HEADER_HIGHLIGHT_NOTIFICATIONS, null, true);
$view->display('notifications');