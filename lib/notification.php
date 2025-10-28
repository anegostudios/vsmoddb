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
	if (DB_READONLY) showReadonlyPage();
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
	case NOTIFICATION_NEW_RELEASE:
		if (!DB_READONLY) $con->execute('UPDATE notifications SET `read` = 1 where notificationId = ?', [$notification['notificationId']]);

		$mod = $con->getRow('SELECT assetId, urlAlias FROM mods WHERE modId = ?', [$notification['recordId']]);

		forceRedirect([
			'path'     => formatModPath($mod),
			'fragment' => 'tab-files',
		]);
		exit();

	case NOTIFICATION_MOD_LOCKED: case NOTIFICATION_MOD_UNLOCK_REQUEST: case NOTIFICATION_MOD_UNLOCKED:
		if (!DB_READONLY) $con->execute('UPDATE notifications SET `read` = 1 WHERE notificationId = ?', [$notification['notificationId']]);

		$mod = $con->getRow('SELECT assetId, urlAlias FROM mods WHERE modId = ?', [$notification['recordId']]);

		forceRedirect(formatModPath($mod));
		exit();

	case NOTIFICATION_TEAM_INVITE: case NOTIFICATION_MOD_OWNERSHIP_TRANSFER_REQUEST:
		$mod = $con->getRow('SELECT assetId, urlAlias FROM mods WHERE modId = ?', [(intval($notification['recordId']) & ((1 << 30) - 1))]); // :InviteEditBit

		forceRedirect(formatModPath($mod));
		exit();

	case NOTIFICATION_MOD_OWNERSHIP_TRANSFER_RESOLVED:
		$assetId = $con->getOne('SELECT assetId FROM mods WHERE modId = ?', [(intval($notification['recordId']) & ((1 << 31) - 1))]); // :PackedTransferSuccess

		if (!DB_READONLY) $con->execute('UPDATE notifications SET `read` = 1 where notificationId = ?', [$notification['notificationId']]);

		forceRedirect([
			'path'     => '/edit/mod/',
			'query'    => 'assetid='.$assetId,
			'fragment' => 'ownership-transfer',
		]);
		exit();

	case NOTIFICATION_NEW_COMMENT: case NOTIFICATION_MENTIONED_IN_COMMENT:
		$mod = $con->getRow(<<<SQL
			SELECT m.assetId, m.urlAlias
			FROM mods m
			JOIN comments c ON c.assetId = m.assetId
			WHERE c.commentId = ?
		SQL, [$notification['recordId']]);

		if (!DB_READONLY) $con->execute('UPDATE notifications SET `read` = 1 where notificationId = ?', [$notification['notificationId']]); // TODO @setting

		forceRedirect([
			'path'     => formatModPath($mod),
			'fragment' => 'cmt-'.$notification['recordId'],
		]);
		exit();

	case NOTIFICATION_ONEOFF_MALFORMED_RELEASE:
		forceRedirect("/edit/release?assetid={$notification['recordId']}");
		exit();
}
