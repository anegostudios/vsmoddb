<?php

class ReleaseEditor extends AssetEditor {
	
	var $savestatus = null;
	var $fileuploadstatus;
	var $mod;
	
	var $modid;

	var $moddtype;
	var $releaseIdDupl;
	var $modAssetIdDupl;
	var $inUseByUser;

	function __construct() {
		$this->editTemplateFile = "edit-release";
		
		parent::__construct("release");
		
		
		array_shift($this->columns); // remove name
		$this->declareColumn(2, array("title" => "modid", "code" => "modid", "tablename" => "release"));
		$this->declareColumn(3, array("title" => "Mod Version", "code" => "modversion", "tablename" => "release"));
		$this->declareColumn(4, array("title" => "Mod Id", "code" => "modidstr", "tablename" => "release"));
	}
	
	
	public function load() {
		global $con, $view, $user;
		
		//TODO(Rennorb): If the assetid doesn't exist this errors.
		$this->assetid = empty($_REQUEST["assetid"]) ? 0 : $_REQUEST["assetid"];
		if ($this->assetid) {
			$this->modid = $modid = $con->getOne("select modid from `release` where assetid=?", array($this->assetid));
		} else {
			$this->modid = $modid = $_REQUEST['modid'];
			
			$asset = $con->getRow("select asset.* from asset join `mod` on (`mod`.assetid=asset.assetid) where mod.modid = ?", array($this->modid));
			$this->assetid=$asset['assetid'];
			if (!canEditAsset($asset, $user)) showErrorPage(HTTP_FORBIDDEN);
		}
		
		$this->moddtype = $modtype = $con->getOne("select `type` from `mod` where modid=?", array($modid));
		$view->assign("modtype", $modtype);
		
		parent::load();
		
		if ($this->savestatus == "invalidfile") {
			addMessage(MSG_CLASS_ERROR, $this->fileuploadstatus['errormessage'], true);
		}
		else if ($this->savestatus == "onlyonefile") {
			addMessage(MSG_CLASS_ERROR, 'There can only be one file per release. Please delete the old file first');
		}
		
		if ($this->assetid) {
			$modid = $this->asset["modid"];
		} else {
			if (empty($_REQUEST['modid'])) showErrorPage(HTTP_BAD_REQUEST, 'Missing modid.');
			
			$modid = $_REQUEST['modid'];

			if(!empty($this->files)) {
				$row = $con->getRow('select detectedmodidstr, detectedmodversion from modpeek_result where fileid = ?', [$this->files[0]['fileid']]);
				if(!empty($row)) {
					$this->asset['modidstr'] = $row['detectedmodidstr'];
					$this->asset['modversion'] = $row['detectedmodversion'];
				}
			}
		}
		
		$mod = $con->getRow("
			select
				asset.*, 
				`mod`.*
			from 
				asset 
				join `mod` on asset.assetid=`mod`.assetid
			where
				mod.modid = ?
		", array($modid));
		
		$view->assign("mod", $mod);
	}
	
	function delete() {
		global $con;
		
		$mod = $con->getRow("select `mod`.* from `mod` join `release` on (`release`.modid = `mod`.modid) where release.assetid=?", array($this->assetid));
		
		$con->Execute("delete from asset where assetid=?", array($this->assetid));
		$con->Execute("delete from `{$this->tablename}` where {$this->tablename}id=?", array($this->recordid));

		//TODO(Rennorb) @correctness: Remove / hide unread release notifications for deleted releases.
		// We cannot remove notifications for deleted releases trivially like we do with comment notifications because release notifications are tracked by modid, not by releaseid.
		// Since we only have the modid in the notification entry we could run into the following scenario:
		// 1. new release 1 for mod 1 -> notification 1 (unread)
		// 2. new release 2 for mod 1 -> notification 2 (unread)
		// 3. delete release 2 -> we would delete both notifications even though only one should be removed, because both of them are tracked by the same modid
		// I think it is possible to figure out a solution to this using the creation dates for releases and notifications, or change the notifications to be tracking releaseid instead of modid.
		// Both of those would however be a larger change, and right now I'm just supplying a small fix for notifications.
		// For now we just let these "invalid" notifications exist, as to not potentially remove valid ones which would be a lot worse.

		if (!empty($mod)) {
			updateGameVersionsCached($mod['modid']);

			$con->execute("
				update `mod`
				set lastreleased = IFNULL((select created from `release` where modid = `mod`.modid order by created desc limit 1), `mod`.created)
				where modid = ?;
			", [$mod['modid']]);
		
			forceRedirect(formatModPath($mod).'#tab-files');
		} else {
			header("Location: /");
		}
	}
	
	/**
	 * @return 'invalidfile'|'missingfile'|'missingmodinfo'|'invalidmodid'|'invalidmodversion'|'duplicateid'|'modidinuse'|'duplicatemod'|'onlyonefile'|'savednew'|'saved'|'error'
	 */
	function saveFromBrowser() {
		global $con, $user, $view;
		
		$modid = null;
		$file  = null;
		$assettypeid = $con->getOne("select assettypeid from assettype where code=?", array($this->tablename));


		//TODO(Rennorb) @cleanup: This only exists for the case that the user used the "Browse" button instead of drag and drop, because that doesn't immediately upload the file. 
		if (!empty($_FILES["newfile"]) && $_FILES["newfile"]["error"] != 4) {
			if ($this->assetid && $con->getRow("select * from file where assetid=?", array($this->assetid))) return "onlyonefile";
		
			$this->fileuploadstatus = processFileUpload($_FILES["newfile"], $assettypeid, $this->assetid ?? 0);
			
			if ($this->fileuploadstatus["status"] != "ok") {
				return "invalidfile";
			}
		}

		$sql_join_with_modpeek = "left join modpeek_result mpr on mpr.fileid = file.fileid";
		if ($this->assetid) $file = $con->getRow("select * from file $sql_join_with_modpeek where assetid=?", array($this->assetid));
		if (!$file) $file = $con->getRow("select * from file $sql_join_with_modpeek where assetid is null and assettypeid=? and userid=?", array($assettypeid, $user['userid']));
		if (!$file) return "missingfile";
				
		if ($this->moddtype == "mod") {

			if(!empty($file['detectedmodidstr']) && !empty($file['detectedmodversion'])) {
				$modidstr = $file['detectedmodidstr'];
				$modversion = $file['detectedmodversion'];
			}
			else {
				$view->assign("allowinfoedit", true);

				if (!empty($_POST['modidstr']) && !empty($_POST['modversion'])) {
					$modidstr = $_POST['modidstr'];
					$modversion = $_POST['modversion'];
				} else {
					return 'missingmodinfo';
				}
			}

			
			if (!preg_match("/^[0-9a-zA-Z]+$/", $modidstr)) {
				return 'invalidmodid';
			}
			
			if (!preg_match("/^[0-9]{1,5}\.[0-9]{1,4}\.[0-9]{1,4}(-(rc|pre|dev)\.[0-9]{1,4})?$/", $modversion)) {
				return 'invalidmodversion';
			}
			
			// Make sure there isn't an exact duplicate of this
			$this->releaseIdDupl = $con->getOne("select assetid from `release` where modidstr=? and modversion=? and assetid!=?", array($modidstr, $modversion, $this->assetid));
			if ($this->releaseIdDupl) {
				return 'duplicateid';
			}

			// Make sure another user doesn't use this modid while allowing team members to release.
			$inUseBy = $con->getRow("
				select user.*, asset.*, user.name
				from `release`
				join asset on asset.assetid = `release`.assetid
				join user on user.userid = asset.createdbyuserid
				where `release`.modidstr = ? and asset.createdbyuserid != ?
			", array($modidstr, $user['userid']));
			if (!$this->assetid && !empty($inUseBy) && !canEditAsset($inUseBy, $user)) {
				$this->inUseByUser = $inUseBy;
				return 'modidinuse';
			}

			// Make sure another mod (but same user) doesn't use this modid 
			$modIdDupl = $con->getOne("select modid from `release` where modidstr=? and modid!=?", array($modidstr, $this->modid));
			if ($modIdDupl) {
				$this->modAssetIdDupl = $con->getOne("select assetid from `mod` where `modid`=?", array($modIdDupl));
				return 'duplicatemod';
			}
			
			// Reserve special mod ids
			if ($modidstr == "game" || $modidstr == "creative" || $modidstr == "survival") {
				$this->inUseByUser = array("userid" => 1, "name" => "the creators of this very game - gasp!");
				return 'modidinuse';
			}
		}
		
		$status = parent::saveFromBrowser();
		
		if ($this->moddtype === 'mod' /* detection will stil run even on external tools */ && ($status == 'saved' || $status == 'savednew')) {
			if (!empty($file['detectedmodidstr']) && !empty($file['detectedmodversion'])) {
				$con->execute('update `release` set modidstr = ?, modversion = ? where assetid = ?', array($modidstr, $modversion, $this->assetid));
			}
		}

		$modid = $con->getOne("select modid from `release` where assetid=?", array($this->assetid));
		
		if ($status == 'savednew') {
			$con->Execute("update `mod` set lastreleased=now() where modid=?", array($modid));
			
			$userids = $con->getCol("select userid from follow where modid = ? and flags & ".FOLLOW_FLAG_CREATE_NOTIFICATIONS, array($modid));
			foreach ($userids as $userid) {
				$con->Execute("insert into notification (userid, type, recordid, created) values (?,?,?, now())", array($userid, 'newrelease', $modid));
			}
		}
	
		updateGameVersionsCached($modid);
		
		return $status;
	}
	
	public function loadFromDB() {
		global $view;
				
		parent::loadFromDB();

		switch($this->savestatus) {
			case 'duplicateid':
				addMessage(MSG_CLASS_ERROR, "Cannot save release, there already exists a <a href=\"/edit/release/?assetid={$this->releaseIdDupl}\">release</a> with this mod id and version - please ensure a unique modid and avoid uploading of duplicate version numbers.");
				break;

			case 'duplicatemod':
				addMessage(MSG_CLASS_ERROR, "Cannot save release, there already exists <a href=\"/show/mod/{$this->modAssetIdDupl}\">another mod</a> that uses this mod id - please ensure a unique modid.");
				break;

			case 'modidinuse':
				$name = htmlspecialchars($this->inUseByUser['name']);
				addMessage(MSG_CLASS_ERROR, "Cannot save release, this mod id has been claimed by {$name}, please choose another one.", true); // @security: Just in case the name gets escaped.
				break;

			case 'missingfile':
				addMessage(MSG_CLASS_ERROR, 'Cannot save release, no file has been uploaded.');
				break;

			case 'missingmodinfo':
				addMessage(MSG_CLASS_ERROR, 'Cannot save release, could not load mod info from file and mod info fields were empty.');
				break;

			case 'invalidmodid':
				addMessage(MSG_CLASS_ERROR, 'Cannot save release, invalid mod id, please use only letters and numbers.');
				break;

			case 'invalidmodversion':
				addMessage(MSG_CLASS_ERROR, 'Cannot save release, invalid mod version, please use the format <code>n.n.n</code> or <code>n.n.n-(rc|pre|dev).n</code><br/>E.g. <code>1.0.1</code> or <code>1.5.2-rc.1</code>');
				break;
		}
	}
	

	function getBackLink() {
		global $con;
		$assetid = $con->getOne("select `mod`.assetid from `mod` join `release` on (`mod`.modid = `release`.modid) where `release`.assetid=?", array($this->assetid));
		return "/show/mod/{$assetid}?saved=1#tab-files";
	}
		
}
