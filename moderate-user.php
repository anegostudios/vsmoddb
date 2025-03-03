<?php

$usertoken = $urlparts[2] ?? null;

if(empty($usertoken) || empty($targetuser = getUserByHash($usertoken, $con))) {
	http_response_code(404);
	$view->display("404");
	exit();
}

if(!canModerate($targetuser, $user)) {
	http_response_code(403);
	$view->display("403");
	exit();
}

if(isset($_POST['submit']) && $_POST['submit'] == 'ban') {
	$fpost = filter_input_array(INPUT_POST, array(
		'modreason' => FILTER_SANITIZE_SPECIAL_CHARS,
		'forever' => FILTER_VALIDATE_BOOLEAN,
		'until' => FILTER_UNSAFE_RAW,
	));

	$errorreasons = '';
	if(empty($fpost['modreason'])) {
		$errorreasons = 'reason';
	}
	
	if($fpost['forever']) {
		$until = SQL_DATE_FOREVER;
	}
	//NOTE(Rennorb): i would prefer doing this on the client, so we can take timezone into account. 
	else if($until = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $fpost['until'])) {
		$until = $until->format(SQL_DATE_FORMAT);
	}
	else {
		if($errorreasons)  $errorreasons .= ' and ';
		$errorreasons .= ' either end date or forever checkbox';
	}

	if($errorreasons) {
		http_response_code(400);
		$view->assign('errormessage', "Missing $errorreasons for ban.");
	}
	else {
		$con->execute("update user set banneduntil = ? where userid = ?", array($until, $targetuser['userid']));
		logModeratorAction($targetuser['userid'], $user['userid'], MODACTION_KIND_BAN, $until, $fpost['modreason']);

		forceRedirectAfterPOST();
		exit();
	}
}
else if(isset($_POST['submit']) && $_POST['submit'] == 'redeem') {
	$reason = filter_input(INPUT_POST, 'modreason', FILTER_SANITIZE_SPECIAL_CHARS);
	if(empty($reason)) {
		http_response_code(400);
		$view->assign('errormessage', 'Missing reason for redemption.');
	}
	else {
		$con->execute("update user set banneduntil = now() where userid = ?", array($targetuser['userid']));
		$con->execute("update moderationrecord set until = now() where kind = ".MODACTION_KIND_BAN." and until > now()");
		logModeratorAction($targetuser['userid'], $user['userid'], MODACTION_KIND_REDEEM, SQL_DATE_FOREVER, $reason);

		forceRedirectAfterPOST();
		exit();
	}
}

$shownuser = $con->getRow("select * from user where userid = ?", array($targetuser['userid']));

$sql = "
			select rec.created, rec.kind, rec.until, moderator.name as moderatorname, rec.reason
			from  moderationrecord as rec
			join user as moderator on moderator.userid = rec.moderatorid
			where rec.targetuserid = ?
			order by rec.created desc
		";
$moderationrecord = $con->getAll($sql, array($targetuser['userid']));

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
