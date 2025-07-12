<?php

$userToken = $urlparts[2] ?? null;
if(empty($userToken)) showErrorPage(HTTP_BAD_REQUEST, 'Missing usertoken.');

$targetUser = getUserByHash($userToken, $con);
if(empty($targetUser)) showErrorPage(HTTP_NOT_FOUND, 'User not found.');

if(!canModerate($targetUser, $user)) showErrorPage(HTTP_FORBIDDEN);

if(isset($_POST['submit']) && $_POST['submit'] == 'ban') {
	$postData = filter_input_array(INPUT_POST, [
		'modreason' => FILTER_SANITIZE_SPECIAL_CHARS,
		'forever'   => FILTER_VALIDATE_BOOLEAN,
		'until'     => FILTER_UNSAFE_RAW,
	]);

	$errorReasons = '';
	if(empty($postData['modreason'])) {
		$errorReasons = 'reason';
	}
	
	if($postData['forever']) {
		$until = SQL_DATE_FOREVER;
	}
	//NOTE(Rennorb): i would prefer doing this on the client, so we can take timezone into account. 
	else if($until = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $postData['until'])) {
		$until = $until->format(SQL_DATE_FORMAT);
	}
	else {
		if($errorReasons)  $errorReasons .= ' and ';
		$errorReasons .= ' either end date or forever checkbox';
	}

	if($errorReasons) {
		http_response_code(HTTP_BAD_REQUEST);
		addMessage(MSG_CLASS_ERROR, "Missing $errorReasons for ban."); // @security no external input in $errorReasons
	}
	else {
		$con->execute('UPDATE user SET banneduntil = ? WHERE userid = ?', [$until, $targetUser['userid']]);
		logModeratorAction($targetUser['userid'], $user['userid'], MODACTION_KIND_BAN, $targetUser['userid'], $until, $postData['modreason']);

		forceRedirectAfterPOST();
		exit();
	}
}
else if(isset($_POST['submit']) && $_POST['submit'] == 'redeem') {
	$reason = filter_input(INPUT_POST, 'modreason', FILTER_SANITIZE_SPECIAL_CHARS);
	if(empty($reason)) {
		http_response_code(HTTP_BAD_REQUEST);
		addMessage(MSG_CLASS_ERROR, 'Missing reason for redemption.');
	}
	else {
		$con->execute('UPDATE user SET banneduntil = NOW() WHERE userid = ?', [$targetUser['userid']]);
		$con->execute('UPDATE moderationrecord SET until = NOW() WHERE kind = '.MODACTION_KIND_BAN.' AND until > NOW()');
		logModeratorAction($targetUser['userid'], $user['userid'], MODACTION_KIND_REDEEM, $targetUser['userid'], SQL_DATE_FOREVER, $reason);

		forceRedirectAfterPOST();
		exit();
	}
}

$shownUser = $con->getRow('SELECT * FROM user WHERE userid = ?', [$targetUser['userid']]);

$records = $con->getAll(<<<SQL
	select rec.created, rec.kind, rec.until, moderator.name as moderatorName, rec.reason, c.commentId, c.assetId
	from moderationrecord as rec
	join user as moderator on moderator.userid = rec.moderatorid
	left join Comments c on c.lastModaction = rec.actionid
	where rec.targetuserid = ?
	order by rec.created desc
SQL, array($targetUser['userid']));

foreach($records as &$row) {
	$row['until'] = parseSqlDateTime($row['until']);
}
unset($row);

$sourceCommentId = $_GET['source-comment'] ?? null;
$banReasonSuggestion = $sourceCommentId == null ? '' 
	: 'Offensive comment: '.strip_tags($con->getOne('SELECT text FROM Comments WHERE commentId = ?', [$sourceCommentId]));

$view->assign('pagetitle', "Moderate {$shownUser['name']}");

$view->assign('shownUser', $shownUser);
$view->assign('records', $records);
$view->assign('banReasonSuggestion', $banReasonSuggestion);
$view->display('moderate-user');
