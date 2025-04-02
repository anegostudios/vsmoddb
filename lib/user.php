<?php

$sessiontoken = empty($_COOKIE['vs_websessionkey']) ? null : $_COOKIE['vs_websessionkey'];

$user = null;
$cnt = 0;

// check `DEBUGUSER` first, $sessiontoken could be set by mods.vintagestory.at even if we're browsing stage.mods.vintagestory.at
if (DEBUGUSER === 1) {
	$userid = empty($_GET['showas']) ? 1 : (intval($_GET['showas']) ?: 1); // append ?showas=<id> to view the page as a different user
	$user = $con->getRow("
		select user.*, role.code as rolecode, ifnull(user.banneduntil >= now(), 0) as `isbanned`, rec.reason as bannedreason
		from user 
		left join role on (user.roleid = role.roleid)
		left join moderationrecord as rec on (rec.kind = " . MODACTION_KIND_BAN . " and rec.targetuserid = user.userid and rec.until = user.banneduntil and rec.until >= NOW())
		where user.userid = ?
	", array($userid));
}

if ($sessiontoken) {
	$user = $con->getRow("
		select user.*, role.code as rolecode, ifnull(user.banneduntil >= now(), 0) as `isbanned`, rec.reason as bannedreason
		from user 
		left join role on (user.roleid = role.roleid) 
		left join moderationrecord as rec on (rec.kind = " . MODACTION_KIND_BAN . " and rec.targetuserid = user.userid and rec.until = user.banneduntil and rec.until >= NOW())
		where sessiontoken=? and sessiontokenvaliduntil > now()
	", array($_COOKIE['vs_websessionkey'])
	);
}

global $messages;
$messages = [];
$view->assignRefUnfiltered('messages', $messages);

if (!empty($user)) {
	$user['hash'] = getUserHash($user['userid'], $user['created']);
	loadNotifications();

	$view->assign("user", $user);

	if($user['isbanned']) {
		$until = formatDateWhichMightBeForever(parseSqlDateTime($user['banneduntil']), 'M jS Y, H:i:s', 'further notice');
		$messages[] = [
			'class' => 'bg-error text-error permanent',
			'html'  => "
				<h3 style='text-align: center;'>You are currently banned until {$until}.</h3>
				<p>
					<h4 style='margin-bottom: 0.25em;'>Reason:</h4>
					{$user['bannedreason']}
				</p>
			"
		];
	}
} else {
	$view->assign("notificationcount", 0);
}


const MSG_CLASS_OK = 'bg-success text-success';
const MSG_CLASS_WARN = 'bg-warning';
const MSG_CLASS_ERROR = 'bg-error text-error';

/** Add a non-permanent message to the list of messages. These don't persist thought reloads.
 * @param string $html !UNFILTERED! html message
 */
function addMessage($class, $html)
{
	global $messages;
	$messages[] = ['class' => $class, 'html' => $html];
}


const ASSETTYPE_MOD = 1;
const ASSETTYPE_RELEASE = 2;

/**
 * @param array $asset
 * @param array $user
 * @param bool  $includeTeam
 * @return bool
 */
function canEditAsset($asset, $user, $includeTeam = true)
{
	global $con;

	$canEditAsTeamMember = false;

	// @cleanup: cursed hackery, breaking the point of the oop asseteditor
	if ($includeTeam && $asset['assettypeid'] === ASSETTYPE_MOD) {
		$canEditAsTeamMember = $con->getOne("
			select 1 
			from teammember 
			join `mod` on `mod`.modid = teammember.modid
			where assetid = ? and userid = ? and canedit = 1
		", array($asset['assetid'], $user['userid']));
	}
	else if ($includeTeam && $asset['assettypeid'] === ASSETTYPE_RELEASE) {
		//NOTE(Rennorb): The second case checks if we are owner of the mod this release belongs to.
		$canEditAsTeamMember = $con->getOne("
				select 1 
				from teammember 
				join `release` on `release`.modid = teammember.modid
				where assetid = ? and userid = ? and canedit = 1
			union
				select 1
				from `mod`
				join `release` on `release`.modid = `mod`.modid and `release`.assetid = ?
				join asset on asset.assetid = `mod`.assetid and asset.createdbyuserid = ?
		", array($asset['assetid'], $user['userid'], $asset['assetid'], $user['userid']));
	}

	return isset($user['userid']) && ($user['userid'] == $asset['createdbyuserid'] || $user['rolecode'] == 'admin' || $user['rolecode'] == "moderator" || $canEditAsTeamMember);
}

function canDeleteAsset($asset, $user)
{
	return isset($user['userid']) && ($user['userid'] == $asset['createdbyuserid'] || $user['rolecode'] == 'admin' || $user['rolecode'] == "moderator");
}

function canEditProfile($shownuser, $user)
{
	return isset($user['userid']) && ($user['userid'] == $shownuser['userid'] || canModerate($shownuser, $user));
}

/**
 * @param unused $shownuser  the moderation target (ignored for now, moderators are global for now)
 * @param array  $user       the permission source 
 */
function canModerate($shownuser, $user)
{
	return $user['rolecode'] == 'admin' || $user['rolecode'] == 'moderator';
}

function loadNotifications()
{
	global $con, $view, $user;

	$view->assign("notificationcount", $con->getOne("select count(*) from notification where userid=? and `read`=0", array($user['userid'])));

	$notifications = $con->getAll("select * from notification where userid=? and `read`=0 order by created desc limit 10", array($user['userid']));

	foreach ($notifications as &$notification) {
		switch ($notification['type']) {
			case "newrelease":
				$cmt = $con->getRow("
					select 
						`asset`.name as modname,
						user.name as username
					from
						`mod`
						join asset on (`mod`.assetid = asset.assetid)
						join user on (asset.createdbyuserid = user.userid)
					where modid=?
				", [$notification['recordid']]);

				$notification['text'] = "{$cmt['username']} uploaded a new version of {$cmt['modname']}";
				break;

			case "teaminvite":
				$cmt = $con->getRow("
					select 
						`asset`.name as modname,
						user.name as username
					from
						`mod`
						join asset on (`mod`.assetid = asset.assetid)
						join user on (asset.createdbyuserid = user.userid)
					where `mod`.modid = ? 
				", [intval($notification['recordid']) & ((1 << 30) - 1)]);  // :InviteEditBit

				$notification['text'] = "{$cmt['username']} invited you to join the team of {$cmt['modname']}";
				break;

			case "modownershiptransfer":
				$cmt = $con->getRow("
					select 
						`asset`.name as modname,
						user.name as username
					from
						`mod`
						join asset on (`mod`.assetid = asset.assetid)
						join user on (asset.createdbyuserid = user.userid)
					where `mod`.modid=? 
				", [$notification['recordid']]);

				$notification['text'] = "{$cmt['username']} offered you ownership of {$cmt['modname']}";
				break;

			case "newcomment": case "mentioncomment":
				$cmt = $con->getRow("
					select 
						asset.assetid,
						asset.name as modname,
						user.name as username,
						`mod`.urlalias as modalias
					from
						comment 
						join asset on (comment.assetid = asset.assetid)
						join `mod` on (comment.assetid = `mod`.assetid)
						join user on (comment.userid = user.userid)
					where commentid=?
				", [$notification['recordid']]);

				if ($notification['type'] == "newcomment") {
					$notification['text'] = "{$cmt['username']} commented on {$cmt['modname']}";
				}
				else {
					$notification['text'] = "{$cmt['username']} mentioned you in a comment on {$cmt['modname']}";
				}
				break;
		}
		$notification['link'] = "/notification/{$notification['notificationid']}";
	}
	unset($notification);

	$view->assign("notifications", $notifications);
}

/** Validates the `at` parameter of the request (post or get) and terminates with an error page if a mismatch is found. */
function validateActionToken()
{
	global $user;
	if ($user['actiontoken'] != $_REQUEST['at']) {
		showErrorPage(HTTP_BAD_REQUEST, 'Invalid action token. To prevent CSRF, you can only submit froms directly on the site. If you believe this is an error, please contact Rennorb <a class="external" href="https://discord.com/channels/302152934249070593/810541931469078568">on Discord</a>.', false, true);
	}
}
