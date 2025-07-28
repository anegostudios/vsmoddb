<?php

function goBackOrRootFallback()
{
	forceRedirect(!contains($_SERVER['HTTP_REFERER'] ?? '', 'notification/') ? $_SERVER['HTTP_REFERER'] : '/');
}

if (empty($user)) {
	goBackOrRootFallback();
	exit();
}

if ($urlparts[1] == 'clearall') {
	$con->Execute('UPDATE notifications SET `read` = 1 WHERE userId = ?', [$user['userId']]);
	goBackOrRootFallback();
	exit();
}

$notification = $con->getRow('SELECT * FROM notifications WHERE notificationId = ?', [$urlparts[1]]);

if (empty($notification)) {
	goBackOrRootFallback();
	exit();
}

switch($notification['kind']) {
	case 'newrelease':
		$con->execute('UPDATE notifications SET `read` = 1 where notificationId = ?', [$notification['notificationId']]);

		$mod = $con->getRow('SELECT assetId, urlAlias FROM mods WHERE modId = ?', [$notification['recordId']]);

		forceRedirect([
			'path'     => formatModPath($mod),
			'fragment' => 'tab-files',
		]);
		exit();

	case "modlocked": case "modunlockrequest": case "modunlocked":
		$con->execute('UPDATE notifications SET `read` = 1 WHERE notificationId = ?', [$notification['notificationId']]);

		$mod = $con->getRow('SELECT assetId, urlAlias FROM mods WHERE modId = ?', [$notification['recordId']]);

		forceRedirect(formatModPath($mod));
		exit();

	case "teaminvite": case "modownershiptransfer":
		$mod = $con->getRow('SELECT assetId, urlAlias FROM mods WHERE modId = ?', [(intval($notification['recordId']) & ((1 << 30) - 1))]); // :InviteEditBit

		forceRedirect(formatModPath($mod));
		exit();

	case "newcomment": case "mentioncomment":
		$mod = $con->getRow(<<<SQL
			SELECT m.assetId, m.urlAlias
			FROM mods m
			JOIN comments c ON c.assetId = m.assetId
			WHERE c.commentId = ?
		SQL, [$notification['recordId']]);

		$con->execute('UPDATE notifications SET `read` = 1 where notificationId = ?', [$notification['notificationId']]); // TODO @setting

		forceRedirect([
			'path'     => formatModPath($mod),
			'fragment' => 'cmt-'.$notification['recordId'],
		]);
		exit();
}
