<?php

if (!empty($user)) {
	$sql = "
		select 
			asset.*, 
			`mod`.*,
			logofile.cdnpath as logocdnpath,
			status.code as statuscode
		from 
			asset 
			join `mod` on asset.assetid = `mod`.assetid
			left join status on asset.statusid = status.statusid
			left join file as logofile on `mod`.logofileid = logofile.fileid
			left join teammember on `mod`.modid = teammember.modid
		where
			(asset.createdbyuserid = ? or teammember.userid = ?)
		order by asset.created desc
	";
	
	$ownmods = $con->getAll($sql, array($user['userid'], $user['userid']));
	
	foreach($ownmods as &$row) {
		unset($row['text']);
		$row["tags"] = array();
		$row['from'] = $user['name'];
		
		$tagscached = trim($row["tagscached"]);
		if (empty($tagscached)) continue;
		
		$tagdata = explode("\r\n", $tagscached);
		$tags=array();
		
		foreach($tagdata as $tagrow) {
			$parts = explode(",", $tagrow);
			$tags[] = array('name' => $parts[0], 'color' => $parts[1], 'tagid' => $parts[2]);
		}
		
		$row['tags'] = $tags;
	}
	
	unset($row);
	
	$view->assign("mods", $ownmods);
}

if (!empty($user)) {

	$followedmods = $con->getAll("
		select 
			asset.*,
			`mod`.*,
			logofile.cdnpath as logocdnpath,
			user.name as `from`,
			status.code as statuscode,
			status.name as statusname,
			rd.created as releasedate,
			rd.modversion as releaseversion
		from
			asset
			join `mod` on asset.assetid = `mod`.assetid
			join user on (asset.createdbyuserid = user.userid)
			join status on (asset.statusid = status.statusid)
			join follow on (`mod`.modid = follow.modid and follow.userid=?)
			left join file as logofile on `mod`.logofileid = logofile.fileid
			left join (select * from `release`) rd on (rd.modid = `mod`.modid)
		where
			asset.statusid=2
			and rd.created is null or rd.created = (select max(created) from `release` where `release`.modid = mod.modid)
		order by
			releasedate desc
	", array($user['userid']));

	

	$view->assign("followedmods", $followedmods);
} else {
	$view->assign("followedmods", array());
}


$latestentries = $con->getAll("
	select 
		asset.*,
		`mod`.*,
		logofile.cdnpath as logocdnpath,
		user.name as `from`,
		status.code as statuscode,
		status.name as statusname
	from 
		asset
		join `mod` on asset.assetid = `mod`.assetid
		join user on (asset.createdbyuserid = user.userid)
		join status on (asset.statusid = status.statusid)
		left join file as logofile on mod.logofileid = logofile.fileid
	where
		asset.statusid=2
		and `mod`.created > date_sub(now(), interval 30 day)
	order by
		asset.created desc
	limit 10
");

$view->assign("latestentries", $latestentries);

$latestcomments = $con->getAll("
	select 
		comment.*,
		asset.name as assetname,
		assettype.name as assettypename,
		assettype.code as assettypecode,
		user.name as username,
		ifnull(user.banneduntil >= now(), 0) as `isbanned`
	from 
		comment
		join user on (comment.userid = user.userid)
		join asset on (comment.assetid = asset.assetid)
		join assettype on (asset.assettypeid = assettype.assettypeid)
	where 
		asset.statusid=2
		and comment.deleted = 0
		and comment.created > date_sub(now(), interval 14 day)
	order by
		comment.created desc
	limit 20
");

$view->assign("latestcomments", $latestcomments, null, true);

$view->display("home.tpl");
