<?php

class ReleaseEditor extends AssetEditor {
	
	var $savestatus = null;
	
	
	function __construct() {
		$this->editTemplateFile = "edit-release";
		
		parent::__construct("release");
		
		
		array_shift($this->columns); // remove name
		$this->declareColumn(2, array("title" => "modid", "code" => "modid", "tablename" => "release"));
		$this->declareColumn(3, array("title" => "Mod Version", "code" => "modversion", "tablename" => "release"));
		$this->declareColumn(4, array("title" => "Mod Id", "code" => "modidstr", "tablename" => "release"));
	}
	
	
	public function load() {
		global $con, $view;
		
		$this->assetid = empty($_REQUEST["assetid"]) ? 0 : $_REQUEST["assetid"];
		if ($this->assetid) {
			$modid = $con->getOne("select modid from `release` where assetid=?", array($this->assetid));
		} else {
			$modid = $_REQUEST['modid'];
		}
		
		$this->moddtype = $modtype = $con->getOne("select `type` from `mod` where modid=?", array($modid));
		$view->assign("modtype", $modtype);
		
		parent::load();
		
		if ($this->assetid) {
			$modid = $this->asset["modid"];
		} else {
			if (empty($_REQUEST['modid'])) {
				$view->assign("reason", "modid missing");
				$view->display("404.tpl");
				exit();
			}
			
			$modid = $_REQUEST['modid'];
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
		parent::delete();
		updateGameVersionsCached($modid);
	}
	
	function saveFromBrowser() {
		global $con, $user, $view;
		
		$modid = null;
		$file=null;
	
		$assettypeid = $con->getOne("select assettypeid from assettype where code=?", array($this->tablename));
		if ($this->assetid) $file = $con->getRow("select * from file where assetid=?", array($this->assetid));
		if (!$file) $file = $con->getRow("select * from file where assetid is null and assettypeid=? and userid=?", array($assettypeid, $user['userid']));
		if (!$file) return "missingfile";
		
		if ($this->assetid) {
			$filepath = "files/asset/{$this->assetid}/{$file['filename']}";
		} else {
			$filepath = "tmp/{$user['userid']}/{$file['filename']}";
		}
		
		if ($this->moddtype == "mod") {
			$modinfo = getModInfo($filepath);
			
			if ($modinfo['modparse'] == 'ok') {
				$modidstr = $modinfo['modid']; 
				$modversion = $modinfo['modversion'];
			} else {
				$view->assign("allowinfoedit", true);

				if (empty($_POST['modidstr']) || empty($_POST['modversion'])) {
					return 'missingmodinfo';
				}
				
				$modidstr = $_POST['modidstr'];
				$modversion = $_POST['modversion'];
			}

			
			if (preg_match("/[^0-9a-zA-Z\-_]+/", $modidstr)) {
				return 'invalidmodid';
			}
			
			if (!preg_match("/^[0-9]{1,5}\.[0-9]{1,4}\.[0-9]{1,4}(-(rc|pre|dev)\.[0-9]{1,4})?$/", $modversion)) {
				return 'invalidmodversion';
			}
			
			$this->releaseIdDupl = $con->getOne("select assetid from `release` where modidstr=? and modversion=? and assetid!=?", array($modidstr, $modversion, $this->assetid));
			if ($this->releaseIdDupl) {
				return 'duplicateid';
			}
			
			$userid = $con->getOne("select createdbyuserid from `asset` join `release` on (asset.assetid = `release`.assetid) where modidstr=? and createdbyuserid!=?", array($modidstr, $user['userid']));
			if (!$this->assetid && $userid) {
				$this->inUseByUser = $con->getRow("select * from user where userid=?", $userid);
				return 'modidinuse';
			}
		}
		
		$status = parent::saveFromBrowser();
		
		if ($status == 'saved' || $status == 'savednew') {
			$releaseid = $con->getOne("select releaseid from `release` where assetid=?", array($this->assetid));
			
			if ($modinfo['modparse'] == 'ok') {
				update("release", $releaseid, array("detectedmodidstr" => $modidstr, "modidstr" => $modidstr, "modversion" => $modversion));
			} else {
				update("release", $releaseid, array("detectedmodidstr" => null));
			}
		}
		
		$modid = $con->getOne("select modid from `release` where assetid=?", array($this->assetid));
		
		if ($status == 'savednew') {
			$con->Execute("update `mod` set lastreleased=now() where modid=?", array($modid));
			
			$userids = $con->getCol("select userid from `follow` where modid=?", array($modid));
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
		
		if ($this->savestatus == 'duplicateid') {
			$view->assign("errormessage", "Cannot save release, there already exists a <a href=\"/edit/release/?assetid={$this->releaseIdDupl}\">release</a> with this mod id and version - please ensure a unique modid and avoid uploading of duplicate version numbers.", null, true);
		}
		if ($this->savestatus == 'modidinuse') {
			$name = $this->inUseByUser['name'];
			$view->assign("errormessage", "Cannot save release, this mod id has been claimed by {$name}, please choose another one.", null, true);
		}
		

		
		if ($this->savestatus == 'missingfile') {
			$view->assign("errormessage", "Cannot save release, no file has been uploaded.");
		}
		if ($this->savestatus == 'missingmodinfo') {
			$view->assign("errormessage", "Cannot save release, could not load mod info from file and mod info fields were empty.");
		}
		if ($this->savestatus == 'invalidmodid') {
			$view->assign("errormessage", "Cannot save release, invalid mod info, please use only letters, numbers and -");
		}
		if ($this->savestatus == 'invalidmodversion') {
			$view->assign("errormessage", "Cannot save release, invalid mod version, please use the format n.n.n or n.n.n-(rc|pre|dev).n<br>E.g. 1.0.1 or 1.5.2-rc.1", null, true);
		}
	}
	

	function getBackLink() {
		global $con;
		$assetid = $con->getOne("select `mod`.assetid from `mod` join `release` on (`mod`.modid = `release`.modid) where `release`.assetid=?", array($this->assetid));
		return "/show/mod/{$assetid}?saved=1#tab-files";
	}
		
}
