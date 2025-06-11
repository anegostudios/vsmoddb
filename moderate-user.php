<?php

$usertoken = $urlparts[2] ?? null;
if(empty($usertoken)) showErrorPage(HTTP_BAD_REQUEST, 'Missing suertoken.');

$targetuser = getUserByHash($usertoken, $con);
if(empty($targetuser)) showErrorPage(HTTP_NOT_FOUND, 'User not found.');

if(!canModerate($targetuser, $user)) showErrorPage(HTTP_FORBIDDEN);

if(isset($_POST['submit']) && $_POST['submit'] == 'ban') {
	$fpost = filter_input_array(INPUT_POST, array(
		'modreason' => FILTER_SANITIZE_SPECIAL_CHARS,
		'forever' => FILTER_VALIDATE_BOOLEAN,
		'until' => FILTER_UNSAFE_RAW,
	));

	$errorReasons = '';
	if(empty($fpost['modreason'])) {
		$errorReasons = 'reason';
	}
	
	if($fpost['forever']) {
		$until = SQL_DATE_FOREVER;
	}
	//NOTE(Rennorb): i would prefer doing this on the client, so we can take timezone into account. 
	else if($until = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $fpost['until'])) {
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
		$con->execute("update user set banneduntil = ? where userid = ?", array($until, $targetuser['userid']));
		logModeratorAction($targetuser['userid'], $user['userid'], MODACTION_KIND_BAN, $targetuser['userid'], $until, $fpost['modreason']);

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
		$con->execute("update user set banneduntil = now() where userid = ?", array($targetuser['userid']));
		$con->execute("update moderationrecord set until = now() where kind = ".MODACTION_KIND_BAN." and until > now()");
		logModeratorAction($targetuser['userid'], $user['userid'], MODACTION_KIND_REDEEM, $targetuser['userid'], SQL_DATE_FOREVER, $reason);

		forceRedirectAfterPOST();
		exit();
	}
}

$shownuser = $con->getRow("select * from user where userid = ?", array($targetuser['userid']));

$moderationrecord = $con->getAll("
	select rec.created, rec.kind, rec.until, moderator.name as moderatorname, rec.reason, comment.commentid, asset.assetid
	from moderationrecord as rec
	join user as moderator on moderator.userid = rec.moderatorid
	left join comment on comment.lastmodaction = rec.actionid
	left join asset on asset.assetid = comment.assetid
	where rec.targetuserid = ?
	order by rec.created desc
", array($targetuser['userid']));

foreach($moderationrecord as &$row) {
	$row['until'] = parseSqlDateTime($row['until']);
}
unset($row);

$sourcecommentid = $_GET['source-comment'] ?? null;
$banreasonautocomplete = $sourcecommentid == null ? '' 
	: 'Offensive comment: '.strip_tags($con->getOne("select text from comment where commentid = ?", array($sourcecommentid)));

$view->assign('pagetitle', "Moderate {$shownuser['name']}");

$view->assign("shownuser", $shownuser);
$view->assign("moderationrecord", $moderationrecord);
$view->assign("banreasonautocomplete", $banreasonautocomplete);
$view->display("moderate-user");
