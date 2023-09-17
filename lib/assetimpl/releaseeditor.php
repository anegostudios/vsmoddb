<?php

class ReleaseEditor extends AssetEditor {
	
	var $savestatus = null;
	var $fileuploadstatus;
	var $mod;
	var $moddtype;
	var $releaseIdDupl;
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
		
		if ($this->savestatus == "invalidfile") {
			$view->assign("errormessage", $this->fileuploadstatus['errormessage']);
		}
		if ($this->savestatus == "onlyonefile") {
			$view->assign("errormessage", "Can't save. Already a file uploaded. Please delete old file first");
		}
		
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
		global $con;
		
		$mod = $con->getRow("select `mod`.* from `mod` join `release` on (`release`.modid = `mod`.modid) where release.assetid=?", array($this->assetid));
		
		$con->Execute("delete from asset where assetid=?", array($this->assetid));
		$con->Execute("delete from `{$this->tablename}` where {$this->tablename}id=?", array($this->recordid));

		if (!empty($mod)) {
			updateGameVersionsCached($mod['modid']);
		
			if ($mod['urlalias']) {
				header("Location: /{$mod['urlalias']}#tab-files");
			} else {
				header("Location: /show/mod/{$mod['assetid']}#tab-files");
			}
		} else {
			header("Location: /");
		}
	}
	
	function saveFromBrowser() {
		global $con, $user, $view, $config;
		
		$modid = null;
		$file=null;
		$assettypeid = $con->getOne("select assettypeid from assettype where code=?", array($this->tablename));


		if (!empty($_FILES["newfile"]) && $_FILES["newfile"]["error"] != 4) {
			if ($this->assetid && $con->getRow("select * from file where assetid=?", array($this->assetid))) return "onlyonefile";
		
			$this->fileuploadstatus = processFileUpload($_FILES["newfile"], $assettypeid, 0);
			
			if ($this->fileuploadstatus["status"] != "ok") {
				return "invalidfile";
			}
			
			if ($this->assetid) {
				update("file", $this->fileuploadstatus['fileid'], array("assetid" => $this->assetid));
			}
		}
	
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

				if ($this->assetid && (empty($_POST['modidstr']) || empty($_POST['modversion']))) {
					$release = $con->getRow("select * from `release` where assetid=?", array($this->assetid));
					$modidstr = $release['modidstr'];
					$modversion = $release['modversion'];
				} else {
					$modidstr = $_POST['modidstr'];
					$modversion = $_POST['modversion'];
				}
				
				if (empty($modidstr) || empty($modversion)) {
					return 'missingmodinfo';
				}
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
			
			if ($modidstr == "game" || $modidstr == "creative" || $modidstr == "survival") {
				$this->inUseByUser = array("userid"=>1, "name" => "the creators of this very game - gasp! And so");
				return 'modidinuse';
			}
		}
		
		$status = parent::saveFromBrowser();
		$release = NULL;
		if ($status == 'saved' || $status == 'savednew') {
			$release = $con->getRow("select releaseid,modid from `release` where assetid=?", array($this->assetid));
			$releaseid = $release["releaseid"];
			if ($modinfo['modparse'] == 'ok') {
				update("release", $releaseid, array("detectedmodidstr" => $modidstr, "modidstr" => $modidstr, "modversion" => $modversion));
			} else {
				update("release", $releaseid, array("detectedmodidstr" => null));
			}
		}
		
		if ($status == "invalidfile" || $status == "onlyonefile") {
			foreach ($this->columns as $column) {
				$col = $column["code"];
				$val = null;
				if (!empty($_POST[$col])) {
					$this->asset[$col] = $_POST[$col];
				}
			}
		
			$view->assign("errormessage", $this->fileuploadstatus["errormessage"]);
		}
		
		if ($status == 'savednew') {

			$modid = $release["modid"];
			$modAsset = $con->getRow("
			select
				modasset.name as assetname,
				user.name as username,
				file.fileid as fileid
			from
				`mod`
				join `release` on (release.releaseid = {$release["releaseid"]})
				join `asset` as modasset on (mod.assetid = modasset.assetid)
				join `user` on (modasset.createdbyuserid = user.userid)
				join `file` on (release.assetid = file.assetid)
			where mod.modid=?", array($modid));
			
			$webhookdata =  createWebhookFollow($modAsset, $config, $modid, $modversion);

			$con->Execute("update `mod` set lastreleased=now() where modid=?", array($modid));
			
			$userids = $con->getCol("select userid from `follow` where modid=?", array($modid));
			foreach ($userids as $userid) {
				$con->Execute("insert into notification (userid, type, recordid, created) values (?,?,?, now())", array($userid, 'newrelease', $modid));
				$webhookurl = $con->getOne("select followwebhook from `user` where userid=?", array($userid));
				if(!empty($webhookurl))
				{
					sendWebhook($webhookdata ,$webhookurl);
				}
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

function sendWebhook($data, $webhookUrl){

	// Initialize cURL session
	$ch = curl_init($webhookUrl);

	// Set cURL options
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Convert data to JSON
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); // Set the content type

	// Execute cURL session and capture the response
	$response = curl_exec($ch);
	// Close cURL session
	curl_close($ch);

}

function createWebhookFollow($modAsset, $config, $modid, $modversion){
	return [
		"content" => null, 
		"embeds" => [
			  [
				 "title" => "New Mod Release", 
				 "color" => 9544535, 
				 "fields" => [
					[
					   "name" => "Mod:", 
					   "value" => "[{$modAsset["assetname"]}]({$config["serverurl"]}/show/mod/{$modid})", 
					   "inline" => true 
					], 
					[
						"name" => "Author", 
						"value" => $modAsset["username"], 
						"inline" => true 
					], 
					[
						"name" => "Version", 
						"value" => "[{$modversion}]({$config["serverurl"]}/download?fileid={$modAsset["fileid"]})",
						"inline" => true 
					] 
				 ], 
				 "thumbnail" => [
					"url" => "https://mods.vintagestory.at/web/img/vsmoddb-logo.png" 
					]
			  ] 
		   ],
		"attachments" => [] 
	 ]; 
}