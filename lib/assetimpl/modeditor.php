<?php

class ModEditor extends AssetEditor
{


	function __construct()
	{
		$this->editTemplateFile = "edit-mod";

		parent::__construct("mod");

		$this->namesingular = "Mod";
		$this->nameplural = "Mods";

		$this->declareColumn(3, array("title" => "Homepage url", "code" => "homepageurl", "datatype" => "url", "tablename" => "mod"));
		$this->declareColumn(4, array("title" => "Source code url", "code" => "sourcecodeurl", "datatype" => "url", "tablename" => "mod"));
		$this->declareColumn(5, array("title" => "Trailer video url", "code" => "trailervideourl", "datatype" => "url", "tablename" => "mod"));
		$this->declareColumn(6, array("title" => "Issue tracker url", "code" => "issuetrackerurl", "datatype" => "url", "tablename" => "mod"));
		$this->declareColumn(7, array("title" => "Wiki url", "code" => "wikiurl", "datatype" => "url", "tablename" => "mod"));
		$this->declareColumn(13, array("title" => "Donate url", "code" => "donateurl", "datatype" => "url", "tablename" => "mod"));
		$this->declareColumn(8, array("title" => "Side", "code" => "side", "tablename" => "mod"));
		$this->declareColumn(9, array("title" => "Logo image", "code" => "logofileid", "tablename" => "mod"));
		$this->declareColumn(10, array("title" => "Mod Type", "code" => "type", "tablename" => "mod"));
		$this->declareColumn(11, array("title" => "URL Alias", "code" => "urlalias", "tablename" => "mod"));
		$this->declareColumn(12, array("title" => "Summary", "code" => "summary", "tablename" => "mod", "datatype" => "name"));
	}

	function load()
	{
		global $view, $con, $user;

		parent::load();

		if($this->migrateImageSizes()) {
			$view->assign('files', $this->files);
		}

		$view->assign("modtypes", array(
			array('code' => "mod", "name" => "Game mod"),
			array('code' => "externaltool", "name" => "External tool"),
			array('code' => "other", "name" => "Other"),
		));

		if (!$this->assetid) {
			$this->asset['type'] = 'mod';
			$this->asset['createdbyuserid'] = $user['userid'];
		}

		if ($this->assetid && ($this->asset['createdbyuserid'] === $user['userid'])) {
			$modId = $con->getOne("select modid from `mod` where assetid=?", array($this->assetid));

			$teammembers = $con->getAll("
					select u.*, t.canedit, 0 as `pending`
					from user u
					join teammember t on u.userid = t.userid
					where t.modid = ? and u.userid != ?
				union
					select u.*, (n.recordid & 1 << 30) as `canedit`, 1 as `pending`
					from notification n
					join user u on u.userid = n.userid
					where n.type = 'teaminvite' and n.`read` = 0 and (n.recordid & ((1 << 30) - 1)) = ? -- :InviteEditBit
			", array($modId, $user['userid'], $modId));

			$view->assign("teammembers", $teammembers);

			$this->handleRevokeNewOwnership($modId);
		}

		$previewData = array_merge($this->asset, [
			'statuscode'  => 'draft',
			'legacylogo'  => false,
			'logocdnpath' => $this->assetid ? $con->getOne('select file.cdnpath from `mod` join file on file.fileid = `mod`.logofileid where  `mod`.assetid = ?', [$this->assetid]) : null, // @perf
			'modpath'     => '#',
			'downloads'   => 123456,
			'comments'    => 123456
		]);
		$view->assign('mod', $previewData);
	}

	function delete()
	{
		global $con;
		$modid = $con->getOne("select modid from `mod` where assetid=?", array($this->assetid));
		$con->Execute("delete from `release` where modid=?", array($modid));
		parent::delete();
	}

	function saveFromBrowser()
	{
		global $con, $user, $view, $typewhitelist;

		$_POST['summary'] = substr(strip_tags($_POST['summary']), 0, 100);

		$_POST['urlalias'] = preg_replace("/[^a-z]+/", "", strtolower($_POST['urlalias']));
		if (!empty($_POST['urlalias'])) {
			if ($con->getOne("select modid from `mod` where urlalias=? and assetid!=?", array($_POST['urlalias'], $this->assetid))) {
				$view->assign("errormessage", "Not saved. This url alias is already taken. Please choose another.");
				return 'error';
			}

			if (in_array($_POST['urlalias'], $typewhitelist)) {
				$view->assign("errormessage", "Not saved. This url alias is reserved word. Please choose another.");
				return 'error';
			}
		}

		$oldLogoFileId = $con->getOne("select logofileid from `mod` where assetid=?", array($this->assetid));
		$newLogoFileId = $_POST['logofileid'] ?? null;

		$logoCheck = ['status' => 'ok'];
		if (!empty($newLogoFileId) && $newLogoFileId != $oldLogoFileId) {
			$logoCheck = $this->validateLogoImage($newLogoFileId);
			if($logoCheck['status'] === 'error') {
				$_POST['logofileid'] = $oldLogoFileId;
			}
		}

		$result = parent::saveFromBrowser();

		if($logoCheck['status'] === 'error') {
			$view->unsetVar("okmessage");
			$view->assign("errormessage", 'Failed to generate logo image: '.$logoCheck['errormessage']);
			return 'error';
		}

		$modid = $con->getOne("select modid from `mod` where assetid=?", array($this->assetid));
		$hasfiles = $con->getOne("select releaseid from `release` where modid=?", array($modid));
		$statusreverted = false;
		if ($_POST['statusid'] != 1 && !$hasfiles) {
			$statusreverted = true;
			$_POST['statusid'] = 1;
		}

		if ($this->isnew) {
			$con->Execute("update `mod` set lastreleased = `mod`.created where assetid = ?", array($this->assetid));
		}

		if(canEditAsset($this->asset, $user, false)) $this->updateTeamMembers($modid);
		if($this->asset['createdbyuserid'] == $user['userid']) $this->updateNewOwner($modid);

		if ($statusreverted) {
			$view->unsetVar("okmessage");
			$view->assign("warningmessage", "Changes saved, but your mod remains in 'Draft' status. You must upload a playable mod/tool first.");
			return "error";
		}

		return $result;
	}

	/** @return bool true if a file was updated */
	function migrateImageSizes()
	{
		global $con;
		// 1. Mods only have image attachments.
		// 2. Images uploaded before the strict logo change do not have their dimensions stored.
		// :ImageSizeMigration

		// To address this we re-download any such images on opening the editor, and figure out the dimensions.
		// This should be a relatively sparse process, and therefore not overwhelm the server.
		// In the future we can run a derivate of this function to clean up any remaining files, potentially using the still existing backup files on the drive from before we used a CDN.

		$updatedAny = false;
		foreach($this->files as &$file) {
			if($file['imagesize'] === null) {
				$localpath = tempnam(sys_get_temp_dir(), '');
				$originalfile = @file_get_contents(formatCdnUrl($file));
				if (file_put_contents($localpath, $originalfile) === false) {
					unlink($localpath);
					continue;
				}
		
				list($w, $h) = getimagesize($localpath);

				unlink($localpath);

				$file['imagesize'] = "{$w}x{$h}";
				$con->execute('update file set imagesize = POINT(?, ?) where fileid = ?', [$w, $h, $file['fileid']]);

				$updatedAny = true;
			}
		}
		unset($file);

		return $updatedAny;
	}

	/**
	 * @param int $logofileid
	 * @return array{status : string, errormessage? : string}
	 */
	function validateLogoImage($logofileid)
	{
		global $con;

		$file = $con->getRow("select *, CONCAT(ST_X(imagesize), 'x', ST_Y(imagesize)) as imagesize from file where fileid = ?", array($logofileid));
		if (empty($file)) return ['status' => 'error', 'errormessage' => 'Invalid fileid.'];

		if($file['imagesize'] !== '480x320' && $file['imagesize'] !== '480x480') {
			return array("status" => "error", "errormessage" => 'Invalid logo dimensions. Only 480x480 or 480x320 are allowed.'); // :ModLogoDimensions
		}

		return ['status' => 'ok'];
	}

	function handleRevokeNewOwnership($modId)
	{
		global $view, $user, $con;

		if (!$this->assetid || $this->asset['createdbyuserid'] != $user['userid'])  return;

		// Check if ownership transfer invitation has been sent to a user
		$newOwner = $con->getRow("select u.userid, u.name, n.notificationid
			from notification as n
			join user u on u.userid = n.userid
			where type = 'modownershiptransfer' and recordid = ? and `read` = 0
		", array($modId));
			
		if (empty($newOwner)) return;
		$view->assign("ownershipTransferUser", $newOwner['name']);

		if (empty($_GET['revokenewownership'])) return;

		// Mark notification to new owner as read without doing anything else. Effectively ignores the offer.
		$con->Execute("update notification set `read` = 1 WHERE notificationid = ?", [$newOwner['notificationid']]);

		logAssetChanges(['Ownership migration aborted'], $this->assetid);

		$url = parse_url($_SERVER['REQUEST_URI']);
		$url['query'] = stripQueryParam($url['query'], 'revokenewownership');
		forceRedirect($url);
		exit();
	}

	function updateTeamMembers($modId)
	{
		global $con, $user;

		$newMemberIds = filter_input(INPUT_POST, 'teammemberids', FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY) ?? [];

		$newEditorMemberIds = filter_input(INPUT_POST, 'teammembereditids', FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY) ?? [];
		$newEditorMemberIds = array_flip($newEditorMemberIds);

		$oldMembers = $con->getAll("select userid, canedit, teammemberid from teammember where modid = ?", array($modId));
		$oldMembers = array_combine(array_column($oldMembers, 'userid'), $oldMembers);

		$changes = array();

		foreach ($newMemberIds as $newMemberId) {
			//NOTE(Rennorb) @hack: We use the hightes possible bit (#31) to indicate that this invitation should resolve with editor permissions.
			// We do this to simplitfy the teammebers table, as there currently is not complex permission system and we would otherwise need several more columns to keep track of this.
			// :InviteEditBit
			$editBit = array_key_exists($newMemberId, $newEditorMemberIds) ? 1 << 30 : 0;
			$mergedId = $modId | $editBit;

			if (!array_key_exists($newMemberId, $oldMembers)) {
				$invitation = $con->getRow("select notificationid, recordid from notification where type = 'teaminvite' and `read` = 0 and userid = ? and (recordid & ((1 << 30) - 1)) = ?", array($newMemberId, $modId));
				if(empty($invitation)) {
					$con->execute("insert into notification (type, userid, recordid, created) VALUES ('teaminvite', ?, ?, now())", array($newMemberId, $mergedId));

					$changes[] = "User #{$user['userid']} invited user #{$newMemberId} to join the team".($editBit ? ' with edit permissions' : '').'.';
				}
				else if ($invitation['recordid'] != $mergedId) {
					$con->execute("update notification set recordid = ? where notificationid = ?", array($mergedId, $invitation['notificationid']));

					$changes[] = $editBit
						? "User #{$user['userid']} promoted invitation to user #{$newMemberId} to editor."
						: "User #{$user['userid']} demoted invitation to user #{$newMemberId} to normal member.";
				}
			}
			else if (boolval($oldMembers[$newMemberId]['canedit']) !== boolval($editBit)) {
				$con->execute("update teammember set canedit = ? where teammemberid = ?", array($editBit ? 1 : 0, $oldMembers[$newMemberId]['teammemberid']));

				$changes[] = $editBit
					? "User #{$user['userid']} promoted teammember user #{$newMemberId} to editor."
					: "User #{$user['userid']} demoted teammember user #{$newMemberId} to normal member.";
			}

			unset($oldMembers[$newMemberId]);
		}

		foreach ($oldMembers as $member) {
			$con->Execute("delete from teammember where teammemberid = ?", array($member['teammemberid']));
			$changes[] = "User #{$user['userid']} removed teammember user #{$member['userid']}.";
		}

		logAssetChanges($changes, $this->assetid);
	}

	function updateNewOwner($modId)
	{
		global $con, $view, $user;

		$newOwnerId = filter_input(INPUT_POST, 'newownerid', FILTER_VALIDATE_INT);
		if($newOwnerId === false) return;

		$currentNewOwnerId = $con->getOne("select userid from notification where type = 'modownershiptransfer' and `read` = 0 and recordid = ?", array($modId));
		if ($currentNewOwnerId) {
			$view->assign("errormessage", "An invitation to transfer ownership has already been sent to ".($currentNewOwnerId == $newOwnerId ? 'this user.' : 'a different user.'));
			return;
		}

		$isTeamMember = $con->getOne("select 1 from teammember where modid = ? and userid = ?", array($modId, $newOwnerId));
		if ($isTeamMember) {
			$view->assign("errormessage", "The user selected for ownership transfer is not a team member.");
			return;
		}

		$con->Execute("insert into notification (type, userid, recordid, created) VALUES ('modownershiptransfer', ?, ?, now())", array($newOwnerId, $modId));

		logAssetChanges(["User #{$user['userid']} initiated a ownership transfer to user #{$newOwnerId}"], $this->assetid);

		return;
	}
}
