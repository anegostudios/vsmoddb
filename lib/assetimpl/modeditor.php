<?php

class ModEditor extends AssetEditor
{


	function __construct()
	{
		$this->editTemplateFile = "edit-mod";

		parent::__construct("mod");

		$this->tablename = "mods";
		$this->namesingular = "Mod";
		$this->nameplural = "Mods";

		$this->declareColumn(3, array("title" => "Homepage url", "code" => "homepageUrl", "datatype" => "url", "tablename" => "mods"));
		$this->declareColumn(4, array("title" => "Source code url", "code" => "sourceCodeUrl", "datatype" => "url", "tablename" => "mods"));
		$this->declareColumn(5, array("title" => "Trailer video url", "code" => "trailerVideoUrl", "datatype" => "url", "tablename" => "mods"));
		$this->declareColumn(6, array("title" => "Issue tracker url", "code" => "issueTrackerUrl", "datatype" => "url", "tablename" => "mods"));
		$this->declareColumn(7, array("title" => "Wiki url", "code" => "wikiUrl", "datatype" => "url", "tablename" => "mods"));
		$this->declareColumn(13, array("title" => "Donate url", "code" => "donateUrl", "datatype" => "url", "tablename" => "mods"));
		$this->declareColumn(8, array("title" => "Side", "code" => "side", "tablename" => "mods"));
		$this->declareColumn(9, array("title" => "Logo image", "code" => "cardLogoFileId", "tablename" => "mods"));
		$this->declareColumn(9, array("title" => "Logo image", "code" => "embedLogoFileId", "tablename" => "mods"));
		$this->declareColumn(10, array("title" => "Mod Type", "code" => "type", "tablename" => "mods"));
		$this->declareColumn(11, array("title" => "URL Alias", "code" => "urlAlias", "tablename" => "mods"));
		$this->declareColumn(12, array("title" => "Summary", "code" => "summary", "tablename" => "mods", "datatype" => "name"));
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
			$this->asset['createdByUserId'] = $user['userId'];

			$view->assign('headerHighlight', HEADER_HIGHLIGHT_SUBMIT_MOD, null, true);
		}

		if ($this->assetid && canEditAsset($this->asset, $user, false)) {
			$modId = $con->getOne('SELECT modId FROM mods WHERE assetId = ?', [$this->assetid]);

			$teamMembers = $con->getAll('
					SELECT u.*, t.canEdit, 0 AS pending
					FROM modTeamMembers t
					JOIN users u ON u.userId = t.userId
					WHERE t.modId = ? AND u.userId != ?
				UNION
					SELECT u.*, (n.recordId & 1 << 30) AS canEdit, 1 AS pending
					FROM notifications n
					JOIN users u ON u.userId = n.userId
					WHERE n.kind = '.NOTIFICATION_TEAM_INVITE.' AND !n.`read` AND (n.recordId & ((1 << 30) - 1)) = ? -- :InviteEditBit
			', [$modId, $user['userId'], $modId]);

			$view->assign('teamMembers', $teamMembers);

			$this->handleRevokeNewOwnership($modId);
		}

		$logoData = $this->assetid ? $con->getRow(<<<SQL
			SELECT fileDb.cdnPath AS pathDb, fileExternal.cdnPath AS pathExternal
			FROM mods m
			LEFT JOIN files AS fileDb ON fileDb.fileId = m.cardLogoFileId
			LEFT JOIN files AS fileExternal ON fileExternal.fileId = m.embedLogoFileId
			WHERE m.assetId = ?
		SQL, [$this->assetid]) : null; // @perf
		$previewData = array_merge($this->asset, [
			'statusCode'    => 'draft',
			'hasLegacyLogo' => false,
			'logoCdnPath'   => $logoData['pathDb'] ?? null, 
			'logoCdnPathExternal' => $logoData['pathExternal'] ?? null, 
			'dbPath'       => '#',
			'downloads'     => 123456,
			'comments'      => 123456
		]);
		$view->assign('mod', $previewData);

		if($this->asset['statusId'] == STATUS_LOCKED) {
			$lockInfo = $con->getRow('
				SELECT rec.reason, n.notificationId
				FROM moderationRecords rec
				LEFT JOIN notifications n ON n.kind = '.NOTIFICATION_MOD_UNLOCK_REQUEST.' AND n.recordId = ? AND n.created >= rec.created
				WHERE rec.kind = '.MODACTION_KIND_LOCK.' AND rec.until >= NOW() AND rec.recordId = ?
				ORDER BY rec.until DESC, rec.actionId DESC
			', [$modId, $modId]);
			$lockReason = htmlspecialchars($lockInfo['reason']);
			if($lockInfo['notificationId']) {
				$nextStepHint = !canModerate(null, $user) 
					? 'You have submitted for review and will receive a notification once the review has concluded.'
					: 'The author has submitted the current state for review.';
				$callToAction = '';
			}
			else {
				$nextStepHint = '';
				$callToAction = '<p>Address these issues and submit for a review to get your mod published again.</p>';
			}
			addMessage(MSG_CLASS_ERROR.' permanent', <<<HTML
				<h3 style='text-align: center;'>This mod has been locked by a moderator.</h3>
				<p>
					<h4 style='margin-bottom: 0.25em;'>Reason:</h4>
					<blockquote>{$lockReason}</blockquote>
				</p>
				$callToAction
			HTML);
			if($nextStepHint) addMessage(MSG_CLASS_OK.' permanent', $nextStepHint);
		}
	}

	function delete()
	{
		global $con;
		$modId = $con->getOne("select modId from mods where assetId = ?", array($this->assetid));
		$con->Execute("delete from modReleases where modId = ?", array($modId));
		parent::delete();
	}

	const RESERVED_URL_PREFIXES = ['api', 'home', 'terms', 'accountsettings', 'login', 'logout', 'edit-uploadfile', 'edit-deletefile', 'download', 'notifications', 'updateversiontags', 'notification', 'list', 'show', 'edit', 'moderate', 'cmd']; // :ReservedUrlPrefixes

	function saveFromBrowser()
	{
		global $con, $user;

		$_POST['summary'] = substr(strip_tags($_POST['summary']), 0, 100);

		$_POST['urlAlias'] = preg_replace("/[^a-z]+/", "", strtolower($_POST['urlAlias']));
		if (!empty($_POST['urlAlias'])) {
			if ($con->getOne("select modId from mods where urlAlias = ? and assetId != ?", array($_POST['urlAlias'], $this->assetid))) {
				addMessage(MSG_CLASS_ERROR, 'Not saved. This url alias is already taken. Please choose another.');
				return 'error';
			}

			if (in_array($_POST['urlAlias'], static::RESERVED_URL_PREFIXES)) {
				addMessage(MSG_CLASS_ERROR, 'Not saved. This url alias is reserved word. Please choose another.');
				return 'error';
			}
		}

		$oldLogoData = $con->getRow("select cardLogoFileId, embedLogoFileId from mods where assetId = ?", array($this->assetid));
		$oldLogoFileIdDb = $oldLogoData['cardLogoFileId'] ?? null;
		$newLogoFileIdDb = $_POST['cardLogoFileId'] ?? null;
		$oldLogoFileIdExternal = $oldLogoData['embedLogoFileId'] ?? null;
		$newLogoFileIdExternal = $_POST['embedLogoFileId'] ?? null;

		$logoCheck = ['status' => 'ok', 'errormessage' => ''];
		if (!empty($newLogoFileIdDb) && $newLogoFileIdDb != $oldLogoFileIdDb) {
			$logoCheck = $this->validateLogoImage($newLogoFileIdDb);
			if($logoCheck['status'] === 'error') {
				$_POST['cardLogoFileId'] = $oldLogoFileIdDb;
			}
		}

		if (!empty($newLogoFileIdExternal) && $newLogoFileIdExternal != $oldLogoFileIdExternal) { // actual selected external logo changed
			$logoCheckExternal = $this->validateLogoImage($newLogoFileIdExternal);
			if($logoCheckExternal['status'] === 'error') {
				// merge error
				$logoCheck['status'] = 'error';
				$logoCheck['errormessage'] .= $logoCheckExternal['errormessage'];

				$_POST['embedLogoFileId'] = $oldLogoFileIdExternal;
			}
		}
		else if(empty($newLogoFileIdExternal)) {
			if($logoCheck['status'] === 'ok') { // dblogo didn't change, need to fetch size
				$imageSize = $con->getOne("SELECT CONCAT(ST_X(size), 'x', ST_Y(size)) FROM fileImageData WHERE fileId = ?", [$newLogoFileIdDb]);
				//NOTE(Rennorb): This can fail for old images, but at that point we just give up. migration copies over the dbimages either way.
				if($imageSize) {
					$logoCheck['status'] = 'size';
					$logoCheck['size'] = $imageSize;
				}
			}

			if($logoCheck['status'] === 'size') { // no selected external logo but we have a db logo
				if($logoCheck['size'] === '480x320') {
					$_POST['embedLogoFileId'] = $newLogoFileIdDb;
				}
				else {
					// External image can be generated form the db one for ease of use.
					$cropResult = $this->cropLogoImageAndUploadToCDN($newLogoFileIdDb);
					if($cropResult['status'] === 'error') {
						$logoCheck['status'] = 'error';
						$logoCheck['errormessage'] .= $cropResult['errormessage'];

						$_POST['embedLogoFileId'] = $oldLogoFileIdExternal;
					}
					else {
						$_POST['embedLogoFileId'] = $cropResult['fileid'];
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


		$modid = $con->getOne("select modId from mods where assetId = ?", array($this->assetid));
		$hasfiles = $con->getOne("select releaseId from modReleases where modId = ?", array($modid));
		$statusreverted = false;
		if (!$hasfiles && $_POST['statusId'] != STATUS_DRAFT && $this->asset['statusId'] != STATUS_LOCKED) {
			$statusreverted = true;
			$_POST['statusId'] = STATUS_DRAFT;
		}

		$tagchanges = $this->updateTags($modid);
		logAssetChanges($tagchanges, $this->assetid);

		if ($this->isnew) {
			$con->Execute("update mods set lastReleased = created where assetId = ?", array($this->assetid));
		}

		$con->execute('update mods set descriptionSearchable = ? where assetId = ?', [textContent($_POST['text']), $this->assetid]);

		if(canEditAsset($this->asset, $user, false)) $this->updateTeamMembers($modid);
		if($this->asset['createdByUserId'] == $user['userId']) {
			if(!$this->updateNewOwner($modid)) {
				return "error";
			}
		}

		if ($statusreverted) {
			static::unsetOkMessages();

			$dbPath = formatModPath($this->asset);
			addMessage(MSG_CLASS_WARN, "Changes saved, but your mod remains hidden as a 'Draft'.</br>To make your mod public you need to first create at least one <a href='{$dbPath}#tab-files'>release</a>.");

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
			if($file['imageSize'] === null) {
				$localpath = tempnam(sys_get_temp_dir(), '');
				$originalfile = @file_get_contents(formatCdnUrl($file));
				if ($originalfile === false || file_put_contents($localpath, $originalfile) === false) {
					unlink($localpath);
					continue;
				}
		
				list($w, $h) = getimagesize($localpath);

				unlink($localpath);

				$file['imageSize'] = "{$w}x{$h}";
				$con->execute(<<<SQL
					INSERT INTO fileImageData (fileId, size)
						VALUES (?, POINT(?, ?))
					ON DUPLICATE KEY UPDATE
						size = VALUES(size)
				SQL, [$file['fileId'], $w, $h]);

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

		$imageSize = $con->getOne("SELECT CONCAT(ST_X(size), 'x', ST_Y(size)) FROM fileImageData WHERE fileId = ?", [$imageFileId]);
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

		$file = $con->getRow('select * from files where fileId = ?', [$imageFileId]);
		if(empty($file)) return ['status' => 'error', 'errormessage' => 'Invalid fileid.'];

		splitOffExtension($file['name'], $filebasename, $ext);
		$filename = "{$filebasename}_480_320.{$ext}";

		// Test for exisitng cropped image, so we dont create duplicates.
		if($this->assetid) {
			$candidate = $con->getOne(<<<SQL
				SELECT f.fileId
				FROM files f
				JOIN fileImageData i ON i.fileId = f.fileId
				WHERE f.assetId = ? AND i.size = POINT(480, 320) AND f.name = ?
			SQL, [$this->assetid, $filename]);
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

		splitOffExtension($file['cdnPath'], $ogCdnBasePath, $ext);
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

		$con->execute(
			'INSERT INTO files (assetId, assetTypeId, userId, name, cdnPath) VALUES (?, ?, ?, ?, ?)',
			[$file['assetId'], $file['assetTypeId'], $file['userId'], $filename, $cdnPath]
		);
		$fileId = $con->Insert_ID();
		$con->execute('INSERT INTO fileImageData (fileId, hasThumbnail, size) VALUES (?, 1, POINT(480, 320))', [$fileId]);

		return ['status' => 'ok', 'fileid' => $fileId];
	}

	function handleRevokeNewOwnership($modId)
	{
		global $view, $user, $con;

		if (!$this->assetid || $this->asset['createdByUserId'] != $user['userId'])  return;

		// Check if ownership transfer invitation has been sent to a user
		$newOwner = $con->getRow(<<<SQL
			SELECT u.userId, u.name, n.notificationId
			FROM notifications AS n
			JOIN users u ON u.userId = n.userId
			WHERE n.kind = ? AND n.recordId = ? AND !n.`read`
		SQL, [NOTIFICATION_MOD_OWNERSHIP_TRANSFER, $modId]);
			
		if (empty($newOwner)) return;
		$view->assign("ownershipTransferUser", $newOwner['name']);

		if (empty($_GET['revokenewownership'])) return;

		// Mark notification to new owner as read without doing anything else. Effectively ignores the offer.
		$con->Execute('UPDATE notifications SET `read` = 1 WHERE notificationId = ?', [$newOwner['notificationId']]);

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

		$oldMembers = $con->getAll('SELECT userId, canEdit, teamMemberId FROM modTeamMembers WHERE modId = ?', [$modId]);
		$oldMembers = array_combine(array_column($oldMembers, 'userId'), $oldMembers);

		$changes = array();

		foreach ($newMemberIds as $newMemberId) {
			//NOTE(Rennorb) @hack: We use the hightes possible bit (#31) to indicate that this invitation should resolve with editor permissions.
			// We do this to simplitfy the teammebers table, as there currently is not complex permission system and we would otherwise need several more columns to keep track of this.
			// :InviteEditBit
			$editBit = array_key_exists($newMemberId, $newEditorMemberIds) ? 1 << 30 : 0;
			$mergedId = $modId | $editBit;

			if (!array_key_exists($newMemberId, $oldMembers)) {
				$invitation = $con->getRow('SELECT notificationId, recordId FROM notifications WHERE kind = '.NOTIFICATION_TEAM_INVITE.' AND !`read` AND userId = ? AND (recordId & ((1 << 30) - 1)) = ?', [$newMemberId, $modId]);
				if(empty($invitation)) {
					$con->execute('INSERT INTO notifications (kind, userId, recordId) VALUES ('.NOTIFICATION_TEAM_INVITE.', ?, ?)', [$newMemberId, $mergedId]);

					$changes[] = "User #{$user['userId']} invited user #{$newMemberId} to join the team".($editBit ? ' with edit permissions' : '').'.';
				}
				else if ($invitation['recordId'] != $mergedId) {
					$con->execute('UPDATE notifications SET recordId = ? WHERE notificationId = ?', [$mergedId, $invitation['notificationId']]);

					$changes[] = $editBit
						? "User #{$user['userId']} promoted invitation to user #{$newMemberId} to editor."
						: "User #{$user['userId']} demoted invitation to user #{$newMemberId} to normal member.";
				}
			}
			else if (boolval($oldMembers[$newMemberId]['canEdit']) !== boolval($editBit)) {
				$con->execute('UPDATE modTeamMembers SET canEdit = ? WHERE teamMemberId = ?', [$editBit ? 1 : 0, $oldMembers[$newMemberId]['teamMemberId']]);

				$changes[] = $editBit
					? "User #{$user['userId']} promoted teammember user #{$newMemberId} to editor."
					: "User #{$user['userId']} demoted teammember user #{$newMemberId} to normal member.";
			}

			unset($oldMembers[$newMemberId]);
		}

		foreach ($oldMembers as $member) {
			$con->Execute('DELETE FROM modTeamMembers WHERE teamMemberId = ?', [$member['teamMemberId']]);
			$changes[] = "User #{$user['userId']} removed teammember user #{$member['userId']}.";
		}

		logAssetChanges($changes, $this->assetid);
	}

	/**
	 * @param int $modId
	 * @return bool
	 */
	function updateNewOwner($modId)
	{
		global $con, $user;

		$newOwnerId = filter_input(INPUT_POST, 'newownerid', FILTER_VALIDATE_INT);
		if(!$newOwnerId) return true;

		$currentNewOwnerId = $con->getOne("SELECT userId FROM notifications WHERE kind = ? AND !`read` AND recordId = ?", [NOTIFICATION_MOD_OWNERSHIP_TRANSFER, $modId]);
		if ($currentNewOwnerId) {
			addMessage(MSG_CLASS_ERROR, 'An invitation to transfer ownership has already been sent to '.($currentNewOwnerId == $newOwnerId ? 'this user.' : 'a different user.'));
			return false;
		}

		$isTeamMember = $con->getOne('SELECT 1 FROM modTeamMembers WHERE modId = ? AND userId = ?', [$modId, $newOwnerId]);
		if (!$isTeamMember) {
			addMessage(MSG_CLASS_ERROR, 'The user selected for ownership transfer is not a team member.');
			return false;
		}

		$con->Execute("INSERT INTO notifications (kind, userId, recordId) VALUES (?, ?, ?)", [NOTIFICATION_MOD_OWNERSHIP_TRANSFER, $newOwnerId, $modId]);

		logAssetChanges(["User #{$user['userId']} initiated a ownership transfer to user #{$newOwnerId}"], $this->assetid);

		return true;
	}

	/**
	 * @param int $modId
	 * @return string[] changelog
	 */
	function updateTags($modId)
	{
		global $con;

		$oldTags = $con->getAssoc('SELECT t.tagId, t.name, t.color FROM modTags mt JOIN tags t ON t.tagId = mt.tagId WHERE mt.modId = ?', [$modId]);

		$changes = [];
		$tagData = [];

		if (!empty($_POST['tagids'])) {
			$addedNamesFolded = '';

			foreach ($_POST['tagids'] as $tagId) {
				$tag = $oldTags[$tagId] ?? null;

				if($tag === null) {
					$con->execute('INSERT INTO modTags (modId, tagId) VALUES (?, ?)', [$modId, $tagId]);

					$tag = $con->getRow('SELECT name, color FROM tags WHERE tagId = ?', [$tagId]);

					if ($addedNamesFolded) $addedNamesFolded .= "', '";
					$addedNamesFolded .= $tag['name'];
				}
				else {
					unset($oldTags[$tagId]);
				}

				$tagData[] = $tag['name'] . ',#' . str_pad(dechex($tag['color']), 8, '0') . ',' . $tagId;
			}

			if ($addedNamesFolded) {
				$s = contains($addedNamesFolded, ',') ? 's' : '';
				$changes[] = "Added tag{$s} '$addedNamesFolded'.";
			}
		}

		if (!empty($oldTags)) {
			$removedTagIdsFolded = implode(',', array_keys($oldTags));
			// @security: $oldTags and its keys are obtained form the database, are numeric and therefore sql inert.
			$con->Execute("DELETE FROM modTags WHERE modId = ? AND tagId IN ($removedTagIdsFolded)", [$modId]);

			$removedTagNamesFolded = implode("', '", array_map(fn ($t) => $t['name'], $oldTags));
			$s = count($oldTags) !== 1 ? 's' : '';
			$changes[] = "Deleted tag{$s} '$removedTagNamesFolded'.";
		}

		// TODO(Rennorb) @cleanup @perf: Is tagscached really needed ?
		$con->execute('UPDATE assets a JOIN mods m ON m.assetId = a.assetId SET a.tagsCached = ? WHERE m.modId = ?', [implode("\r\n", $tagData), $modId]);

		return $changes;
	}
}
