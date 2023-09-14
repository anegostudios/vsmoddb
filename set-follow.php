<?php

if (empty($user)) exit();

$modid = intval($_GET['modid']);
$userid = $user['userid'];

if (!$con->getOne("select modid from `mod` where modid=?", array($modid))) exit(json_encode(array("status" => 404)));

$exists = $con->getOne("select userid from `follow` where modid=? and userid=?", array($modid, $userid));

if ($_GET['op'] == 'follow') {
	if (!$exists) {
		$con->Execute("insert into `follow` (modid, userid, created) values (?, ?, now())", array($modid, $userid));
		$con->Execute("update `mod` set follows=follows+1 where modid=?", array($modid));
	}
} else {
	if ($exists) {
		$con->Execute("delete from `follow` where modid=? and userid=?", array($modid, $userid));
		$con->Execute("update `mod` set follows=follows-1 where modid=?", array($modid));
	}
}

exit(json_encode(array("status" => 200)));