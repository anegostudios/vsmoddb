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
		$this->declareColumn(9, array("title" => "Logo image", "code" => "cardlogofileid", "tablename" => "mod"));
		$this->declareColumn(9, array("title" => "Logo image", "code" => "embedlogofileid", "tablename" => "mod"));
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

			$view->assign('headerHighlight', HEADER_HIGHLIGHT_SUBMIT_MOD, null, true);
		}

		if ($this->assetid && canEditAsset($this->asset, $user, false)) {
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

		$logoData = $this->assetid ? $con->getRow('
			select file_db.cdnpath as path_db, file_external.cdnpath as path_external
			from `mod` 
			left join file as file_db on file_db.fileid = `mod`.cardlogofileid
			left join file as file_external on file_external.fileid = `mod`.embedlogofileid
			where `mod`.assetid = ?
		', [$this->assetid]) : null; // @perf
		$previewData = array_merge($this->asset, [
			'statuscode'  => 'draft',
			'legacylogo'  => false,
			'logocdnpath' => $logoData['path_db'] ?? null, 
			'logocdnpath_external' => $logoData['path_external'] ?? null, 
			'modpath'     => '#',
			'downloads'   => 123456,
			'comments'    => 123456
		]);
		$view->assign('mod', $previewData);

		if($this->asset['statusid'] == STATUS_LOCKED) {
			$lockInfo = $con->getRow("
				select r.reason, n.notificationid
				from moderationrecord r
				left join notification n on n.type = 'modunlockrequest' and n.recordid = ? and n.created >= r.created
				where r.kind = ".MODACTION_KIND_LOCK.' and r.until >= NOW() and r.recordid = ?
				order by r.until desc, r.actionid desc
			', [$modId, $modId]);
			$lockReason = htmlspecialchars($lockInfo['reason']);
			$nextStepHint = $lockInfo['notificationid']
				? (!canModerate(null, $user) ? 'You have submitted for review and will receive a notification once the review has concluded.' : 'The author has submitted the current state for review.')
				: 'Address these issues and submit for a review to get your mod published again.';
			addMessage(MSG_CLASS_ERROR.' permanent', "
				<h3 style='text-align: center;'>This mod has been locked by a moderator.</h3>
				<p>
					<h4 style='margin-bottom: 0.25em;'>Reason:</h4>
					<blockquote>{$lockReason}</blockquote>
				</p>
				<p>$nextStepHint</p>
			");
		}
	}

	function delete()
	{
		global $con;
		$modid = $con->getOne("select modid from `mod` where assetid=?", array($this->assetid));
		$con->Execute("delete from `release` where modid=?", array($modid));
		parent::delete();
	}

	const RESERVED_URL_PREFIXES = ['api', 'home', 'terms', 'accountsettings', 'login', 'logout', 'edit-uploadfile', 'edit-deletefile', 'download', 'notifications', 'updateversiontags', 'notification', 'list', 'show', 'edit', 'moderate', 'cmd']; // :ReservedUrlPrefixes

	function saveFromBrowser()
	{
		global $con, $user;

		$_POST['summary'] = substr(strip_tags($_POST['summary']), 0, 100);

		$_POST['urlalias'] = preg_replace("/[^a-z]+/", "", strtolower($_POST['urlalias']));
		if (!empty($_POST['urlalias'])) {
			if ($con->getOne("select modid from `mod` where urlalias=? and assetid!=?", array($_POST['urlalias'], $this->assetid))) {
				addMessage(MSG_CLASS_ERROR, 'Not saved. This url alias is already taken. Please choose another.');
				return 'error';
			}

			if (in_array($_POST['urlalias'], static::RESERVED_URL_PREFIXES)) {
				addMessage(MSG_CLASS_ERROR, 'Not saved. This url alias is reserved word. Please choose another.');
				return 'error';
			}
		}

		$oldLogoData = $con->getRow("select cardlogofileid, embedlogofileid from `mod` where assetid = ?", array($this->assetid));
		$oldLogoFileIdDb = $oldLogoData['cardlogofileid'] ?? null;
		$newLogoFileIdDb = $_POST['cardlogofileid'] ?? null;
		$oldLogoFileIdExternal = $oldLogoData['embedlogofileid'] ?? null;
		$newLogoFileIdExternal = $_POST['embedlogofileid'] ?? null;

		$logoCheck = ['status' => 'ok', 'errormessage' => ''];
		if (!empty($newLogoFileIdDb) && $newLogoFileIdDb != $oldLogoFileIdDb) {
			$logoCheck = $this->validateLogoImage($newLogoFileIdDb);
			if($logoCheck['status'] === 'error') {
				$_POST['cardlogofileid'] = $oldLogoFileIdDb;
			}
		}

		if (!empty($newLogoFileIdExternal) && $newLogoFileIdExternal != $oldLogoFileIdExternal) { // actual selected external logo changed
			$logoCheckExternal = $this->validateLogoImage($newLogoFileIdExternal);
			if($logoCheckExternal['status'] === 'error') {
				// merge error
				$logoCheck['status'] = 'error';
				$logoCheck['errormessage'] .= $logoCheckExternal['errormessage'];

				$_POST['embedlogofileid'] = $oldLogoFileIdExternal;
			}
		}
		else if(empty($newLogoFileIdExternal)) {
			if($logoCheck['status'] === 'ok') { // dblogo didn't change, need to fetch size
				$imageSize = $con->getOne("select CONCAT(ST_X(imagesize), 'x', ST_Y(imagesize)) from file where fileid = ?", array($newLogoFileIdDb));
				//NOTE(Rennorb): This can fail for old images, but at that point we just give up. migration copies over the dbimages either way.
				if($imageSize) {
					$logoCheck['status'] = 'size';
					$logoCheck['size'] = $imageSize;
				}
			}

			if($logoCheck['status'] === 'size') { // no selected external logo but we have a db logo
				if($logoCheck['size'] === '480x320') {
					$_POST['embedlogofileid'] = $newLogoFileIdDb;
				}
				else {
					// External image can be generated form the db one for ease of use.
					$cropResult = $this->cropLogoImageAndUploadToCDN($newLogoFileIdDb);
					if($cropResult['status'] === 'error') {
						$logoCheck['status'] = 'error';
						$logoCheck['errormessage'] .= $cropResult['errormessage'];

						$_POST['embedlogofileid'] = $oldLogoFileIdExternal;
					}
					else {
						$_POST['embedlogofileid'] = $cropResult['fileid'];
					}
				}
			}
		}

		$result = parent::saveFromBrowser();

		if($logoCheck['status'] === 'error') {
			static::unsetOkMessages();

			addMessage(MSG_CLASS_ERROR, 'Failed to update logo image: '.$logoCheck['errormessage'], true);

			return 'error';
		}

		$modid = $con->getOne("select modid from `mod` where assetid=?", array($this->assetid));
		$hasfiles = $con->getOne("select releaseid from `release` where modid=?", array($modid));
		$statusreverted = false;
		if (!$hasfiles && $_POST['statusid'] != STATUS_DRAFT && $this->asset['statusid'] != STATUS_LOCKED) {
			$statusreverted = true;
			$_POST['statusid'] = STATUS_DRAFT;
		}

		if ($this->isnew) {
			$con->Execute("update `mod` set lastreleased = `mod`.created where assetid = ?", array($this->assetid));
		}

		$con->execute('update `mod` set descriptionsearchable = ? where assetid = ?', [textContent($_POST['text']), $this->assetid]);

		if(canEditAsset($this->asset, $user, false)) $this->updateTeamMembers($modid);
		if($this->asset['createdbyuserid'] == $user['userid']) $this->updateNewOwner($modid);

		if ($statusreverted) {
			static::unsetOkMessages();

			$modPath = formatModPath($this->asset);
			addMessage(MSG_CLASS_WARN, "Changes saved, but your mod remains hidden as a 'Draft'.</br>To make your mod public you need to first create at least one <a href='{$modPath}#tab-files'>release</a>.");

			return "error";
		}

		return $result;
	}

	/* TODO @cleanup: This is a transitional artefact from when there was only one message of each kind. We should remove this this when refactoring the asset editors. */
	static function unsetOkMessages()
	{
		global $messages;
		foreach($messages as $k => $message) {
			if($message['class'] === 'bg-success text-success') {
				unset($messages[$k]);
			}
		}
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
	 * @param int $imageFileId
	 * @return array{status : 'ok'|'error'|'size', errormessage? : string, size? : '480x320'|'480x480'}
	 */
	function validateLogoImage($imageFileId)
	{
		global $con;

		$imageSize = $con->getOne("select CONCAT(ST_X(imagesize), 'x', ST_Y(imagesize)) as imagesize from file where fileid = ?", array($imageFileId));
		if (empty($imageSize)) return ['status' => 'error', 'errormessage' => 'Invalid fileid.'];

		if($imageSize !== '480x320' && $imageSize !== '480x480') {
			return array("status" => "error", "errormessage" => 'Invalid logo dimensions. Only 480x480 or 480x320 are allowed.'); // :ModLogoDimensions
		}

		return ['status' => 'size', 'size' => $imageSize];
	}

	/** Assumes the file is 480x480, crops to 480x320. Validates that the cropped image doesnt already exist as best as it can. 
	 * @param int $assetId
	 * @param int $imageFileId
	 * @return array{status : string, errormessage? : string, fileid? : int}
	 */
	function cropLogoImageAndUploadToCDN($imageFileId)
	{
		global $con;

		$file = $con->getRow('select * from `file` where fileid = ? ', [$imageFileId]);
		if(empty($file)) return ['status' => 'error', 'errormessage' => 'Invalid fileid.'];

		splitOffExtension($file['filename'], $filebasename, $ext);
		$filename = "{$filebasename}_480_320.{$ext}";

		// Test for exisitng cropped image, so we dont create duplicates.
		if($this->assetid) {
			$candidate = $con->getOne('select fileid from `file` where assetid = ? and imagesize = POINT(480, 320) and filename = ?', [$this->assetid, $filename]);
			if($candidate) return ['status' => 'ok', 'fileid' => intval($candidate)];
		}

		// Since we don't have the files locally anymore we unfortunately have to do this stunt and re-download the image thats supposed to be used as a logo.
		// Upload happens asynchronously during drag-n-drop, so when the user saves the asset the files already don't exist locally anymore.
		// Since changing the logo is not a action repeated very often this is ok for now, especially since the alternative would be to keep files around, but not abandon them if the user just navigates away from the asset editor, which is non-tirvial.

		$localPath = tempnam(sys_get_temp_dir(), '');
		$originalFile = @file_get_contents(formatCdnUrl($file));
		if (!file_put_contents($localPath, $originalFile)) {
			@unlink($localPath);
			return ['status' => 'error', 'errormessage' => 'The logo file seems to be gone.'];
		}

		$croppedPath = tempnam(sys_get_temp_dir(), '');
		$cropResult = cropImage($localPath, $croppedPath, 0, 0, 480, 320);
		
		if(!$cropResult) {
			unlink($localPath);
			unlink($croppedPath);
			return ['status' => 'error', 'errormessage' => 'Failed to crop image.'];
		}

		splitOffExtension($file['cdnpath'], $ogCdnBasePath, $ext);
		$cdnBasePath = "{$ogCdnBasePath}_480_320";

		$thumbStatus = createThumbnailAndUploadToCDN($localPath, $cdnBasePath, $ext);
		unlink($localPath);

		if($thumbStatus['status'] !== 'ok') {
			unlink($croppedPath);
			return $thumbStatus;
		}


		$cdnPath = "$cdnBasePath.$ext";
		$uploadResult = uploadToCdn($croppedPath, $cdnPath);
		unlink($croppedPath);

		if($uploadResult['error']) {
			return ['status' => 'error', 'errormessage' => 'CDN Error: '.$uploadResult['error']];
		}

		$con->execute("
			insert into file (created, assetid, assettypeid, userid, filename, cdnpath, hasthumbnail, imagesize)
			values (now(), ?, ?, ?, ?, ?, 1, POINT(480, 320))
		", [$file['assetid'], $file['assettypeid'], $file['userid'], $filename, $cdnPath]);

		return ['status' => 'ok', 'fileid' => $con->Insert_ID()];
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
					$con->execute("insert into notification (type, userid, recordid) VALUES ('teaminvite', ?, ?)", array($newMemberId, $mergedId));

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
			addMessage(MSG_CLASS_ERROR, 'An invitation to transfer ownership has already been sent to '.($currentNewOwnerId == $newOwnerId ? 'this user.' : 'a different user.'));
			return;
		}

		$isTeamMember = $con->getOne("select 1 from teammember where modid = ? and userid = ?", array($modId, $newOwnerId));
		if ($isTeamMember) {
			addMessage(MSG_CLASS_ERROR, 'The user selected for ownership transfer is not a team member.');
			return;
		}

		$con->Execute("insert into notification (type, userid, recordid) VALUES ('modownershiptransfer', ?, ?)", array($newOwnerId, $modId));

		logAssetChanges(["User #{$user['userid']} initiated a ownership transfer to user #{$newOwnerId}"], $this->assetid);

		return;
	}
}
