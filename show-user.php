<?php

$usertoken = $urlparts[2] ?? null;
$shownuser = null;

if (strlen($usertoken) > 20) {
	$view->display("404");
	exit();
}

if (empty($usertoken) || empty($shownuser = getUserByHash($usertoken, $con))) {
	$view->display("404");
	exit();
}

$view->assign("usertoken", $usertoken);

$sql = "
			select 
				asset.*, 
				`mod`.*,
				status.code as statuscode
			from 
				asset 
				join `mod` on asset.assetid = `mod`.assetid
				left join status on asset.statusid = status.statusid
			where
				asset.createdbyuserid = ?
			order by asset.created desc
		";

$authormods = $con->getAll($sql, array($shownuser['userid']));

foreach ($authormods as &$row) {
	unset($row['text']);
	$row["tags"] = array();
	$row['from'] = $shownuser['name'];

	$tagscached = trim($row["tagscached"]);
	if (empty($tagscached)) continue;

	$tagdata = explode("\r\n", $tagscached);
	$tags = array();

	foreach ($tagdata as $tagrow) {
		$parts = explode(",", $tagrow);
		$tags[] = array('name' => $parts[0], 'color' => $parts[1], 'tagid' => $parts[2]);
	}

	$row['tags'] = $tags;
}

unset($row);
$view->assign("mods", $authormods);
$view->assign("user", $user);
$view->assign("shownuser", $shownuser);
$view->display("show-user");
