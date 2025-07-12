<?php

$sessiontoken = $_COOKIE['vs_websessionkey'] ?? null;

$user = null;
$cnt = 0;

// check `DEBUGUSER` first, $sessiontoken could be set by mods.vintagestory.at even if we're browsing stage.mods.vintagestory.at
if (DEBUGUSER === 1) {
	$userid = empty($_GET['showas']) ? 1 : (intval($_GET['showas']) ?: 1); // append ?showas=<id> to view the page as a different user
	$user = $con->getRow('
		SELECT user.*, r.code AS rolecode, ifnull(user.banneduntil >= now(), 0) AS `isbanned`, rec.reason AS bannedreason
		FROM user 
		LEFT JOIN Roles r ON r.roleId = user.roleid
		LEFT JOIN moderationrecord rec ON (rec.kind = ' . MODACTION_KIND_BAN . ' AND rec.targetuserid = user.userid AND rec.until = user.banneduntil AND rec.until >= NOW())
		WHERE user.userid = ?
	', [$userid]);
}

if ($sessiontoken) {
	$user = $con->getRow('
		SELECT user.*, r.code AS rolecode, ifnull(user.banneduntil >= now(), 0) AS `isbanned`, rec.reason AS bannedreason
		FROM user 
		LEFT JOIN Roles r ON r.roleId = user.roleid
		LEFT JOIN moderationrecord rec ON (rec.kind = ' . MODACTION_KIND_BAN . ' AND rec.targetuserid = user.userid AND rec.until = user.banneduntil AND rec.until >= NOW())
		WHERE sessiontoken = ? AND sessiontokenvaliduntil > NOW()
	', [$sessiontoken]);
}

global $messages;
$messages = [];
$view->assignRefUnfiltered('messages', $messages);

if (!empty($user)) {
	$user['hash'] = getUserHash($user['userid'], $user['created']);
	loadNotifications(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) == '/notifications'); // @cleanup

	$view->assign("user", $user);

	if($user['isbanned']) {
		$until = formatDateWhichMightBeForever(parseSqlDateTime($user['banneduntil']), 'M jS Y, H:i:s', 'further notice');
		$messages[] = [
			'class' => MSG_CLASS_ERROR.' permanent',
			'html'  => <<<HTML
				<h3 style='text-align: center;'>You are currently banned until {$until}.</h3>
				<p>
					<h4 style='margin-bottom: 0.25em;'>Reason:</h4>
					<blockquote>{$user['bannedreason']}</blockquote>
				</p>
			HTML
		];
	}
} else {
	$view->assign("notificationcount", 0);
}


const MSG_CLASS_OK = 'bg-success text-success';
const MSG_CLASS_WARN = 'bg-warning';
const MSG_CLASS_ERROR = 'bg-error text-error';

/** Add a non-permanent message to the list of messages. These don't persist thought reloads.
 * @param string $html
 * @param bool $escapeMessage Wether to html escape the message or not.
 */
function addMessage($class, $html, $escapeMessage = false)
{
	global $messages;
	$messages[] = ['class' => $class, 'html' => $escapeMessage ? htmlSpecialChars($html) : $html];
}


const ASSETTYPE_MOD = 1;
const ASSETTYPE_RELEASE = 2;

const STATUS_DRAFT = 1;
const STATUS_RELEASED = 2;
const STATUS_3 = 3;
const STATUS_LOCKED = 4;

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
		$canEditAsTeamMember = $con->getOne(<<<SQL
			SELECT 1 
			FROM ModTeamMembers t 
			JOIN `mod` ON `mod`.modid = t.modId
			WHERE assetid = ? AND t.userId = ? AND t.canEdit = 1
		SQL, array($asset['assetid'], $user['userid']));
	}
	else if ($includeTeam && $asset['assettypeid'] === ASSETTYPE_RELEASE) {
		//NOTE(Rennorb): The second case checks if we are owner of the mod this release belongs to.
		$canEditAsTeamMember = $con->getOne(<<<SQL
				SELECT 1 
				FROM ModTeamMembers t 
				JOIN `release` ON `release`.modid = t.modId
				WHERE assetid = ? AND t.userId = ? AND t.canEdit = 1
			union
				SELECT 1
				FROM `mod`
				JOIN `release` ON `release`.modid = `mod`.modid AND `release`.assetid = ?
				JOIN asset ON asset.assetid = `mod`.assetid AND asset.createdbyuserid = ?
		SQL, array($asset['assetid'], $user['userid'], $asset['assetid'], $user['userid']));
	}

	return isset($user['userid']) && ($user['userid'] == $asset['createdbyuserid'] || $user['rolecode'] == 'admin' || $user['rolecode'] == "moderator" || $canEditAsTeamMember);
}

/**
 * @param array{createdbyuserid:int, 'statusid':int} $asset
 * @param array{userid:int, rolecode:string}         $user    the permission source
 */
function canDeleteAsset($asset, $user)
{
	return isset($user['userid']) && (
		   ($user['userid'] == $asset['createdbyuserid'] && $asset['statusid'] != STATUS_LOCKED)
		|| $user['rolecode'] == 'admin' || $user['rolecode'] == "moderator"
	);
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
	return $user['rolecode'] === 'admin' || $user['rolecode'] === 'moderator';
}

/** Load all notifications and assign relevant fields in the view.
 * @param bool $loadAll Wether to load all notifications or only 10. (used for the notifications page).
 */
function loadNotifications($loadAll)
{
	global $con, $view, $user;

	$view->assign('notificationcount', $con->getOne('SELECT COUNT(*) FROM Notifications WHERE userId = ? AND !`read`', [$user['userid']]));

	$limit = $loadAll ? '' : 'LIMIT 10';
	$notifications = $con->getAll("SELECT * FROM Notifications WHERE userId = ? AND !`read` ORDER BY created DESC $limit", [$user['userid']]);

	foreach ($notifications as &$notification) {
		switch ($notification['kind']) {
			case 'newrelease':
				$cmt = $con->getRow(<<<SQL
					SELECT a.name AS modname, u.name AS username
					FROM `mod` m
					JOIN asset a ON a.assetid = m.assetid
					JOIN user u ON u.userid = a.createdbyuserid
					WHERE modid = ?
				SQL, [$notification['recordId']]);

				$notification['text'] = "{$cmt['username']} uploaded a new version of {$cmt['modname']}";
				break;

			case 'teaminvite':
				$cmt = $con->getRow(<<<SQL
					SELECT a.name AS modname, u.name AS username
					FROM `mod` m
					JOIN asset a ON a.assetid = m.assetid
					JOIN user u ON u.userid = a.createdbyuserid
					WHERE m.modid = ? 
				SQL, [intval($notification['recordId']) & ((1 << 30) - 1)]);  // :InviteEditBit

				$notification['text'] = "{$cmt['username']} invited you to join the team of {$cmt['modname']}";
				break;

			case 'modownershiptransfer':
				$cmt = $con->getRow(<<<SQL
					SELECT a.name AS modname, u.name AS username
					FROM `mod` m
					JOIN asset a ON a.assetid = m.assetid
					JOIN user u ON u.userid = a.createdbyuserid
					WHERE m.modid = ?
				SQL, [$notification['recordId']]);

				$notification['text'] = "{$cmt['username']} offered you ownership of {$cmt['modname']}";
				break;

			case 'newcomment': case 'mentioncomment':
				$cmt = $con->getRow(<<<SQL
					SELECT a.name AS modname, u.name AS username
					FROM Comments c
					JOIN asset a ON a.assetid = c.assetId
					JOIN user u ON u.userid = c.userId
					WHERE c.commentId = ?
				SQL, [$notification['recordId']]);

				if ($notification['kind'] === 'newcomment') {
					$notification['text'] = "{$cmt['username']} commented on {$cmt['modname']}";
				}
				else {
					$notification['text'] = "{$cmt['username']} mentioned you in a comment on {$cmt['modname']}";
				}
				break;

			case 'modlocked':
				$modName = $con->getOne('SELECT name FROM `mod` m JOIN asset a ON a.assetid = m.assetid WHERE m.modid = ?', [$notification['recordId']]);
				$notification['text'] = "Your mod '{$modName}' got locked by a moderator";
				break;

			case 'modunlockrequest':
				$modName = $con->getOne('SELECT name FROM `mod` m JOIN asset a ON a.assetid = m.assetid WHERE m.modid = ?', [$notification['recordId']]);
				$notification['text'] = "A review-request was issued for a mod locked by you ('{$modName}')";
				break;

			case 'modunlocked':
				$modName = $con->getOne('SELECT name from `mod` m JOIN asset a ON a.assetid = m.assetid WHERE m.modid = ?', [$notification['recordId']]);
				$notification['text'] = "Your mod '{$modName}' got unlocked by a moderator";
				break;
		}
		$notification['link'] = "/notification/{$notification['notificationId']}";
	}
	unset($notification);

	$view->assign('notifications', $notifications);
}

/** Validates the `at` parameter of the request (post or get) and terminates with an error page if a mismatch is found. */
function validateActionToken()
{
	global $user;
	if (!isset($_REQUEST['at']) || $user['actiontoken'] != $_REQUEST['at']) {
		showErrorPage(HTTP_BAD_REQUEST, 'Invalid action token. To prevent CSRF, you can only submit froms directly on the site. If you believe this is an error, please contact Rennorb <a class="external" href="https://discord.com/channels/302152934249070593/810541931469078568">on Discord</a>.', false, true);
	}
}
