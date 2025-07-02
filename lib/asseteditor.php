<?php

class AssetEditor extends AssetController
{
	var $asset;
	var $files;

	var $assetid;
	var $recordid;

	var $editTemplateFile = "edit-asset";
	var $isnew;

	var $savestatus;

	function __construct($classname)
	{
		parent::__construct($classname);

		$this->columns = array(
			array("title" => "Name", "code" => "name", "tablename" => "asset", "datatype" => "name"),
			array("title" => "Text", "code" => "text", "tablename" => "asset"),
			array("title" => "Status", "code" => "statusid", "tablename" => "asset"),
		);
	}

	public function load()
	{
		global $con, $user, $view, $config, $maxFileUploadSizeMB;

		$this->assetid = empty($_REQUEST["assetid"]) ? 0 : $_REQUEST["assetid"];
		$this->recordid = null;

		$maxAssetFileUploadSizeKB = $con->getOne("select maxfilesizekb from assettype where code = '{$this->tablename}'");
		$maxFileUploadSizeMB = min($maxFileUploadSizeMB, round($maxAssetFileUploadSizeKB / 1024, 1));
		$view->assign("fileuploadmaxsize", $maxFileUploadSizeMB);

		if ($this->assetid) {
			$this->recordid = $con->getOne("select {$this->tablename}id from `{$this->tablename}` where assetid=?", array($this->assetid));

			$asset = $con->getRow("select * from asset where assetid=?", array($this->assetid));

			if (!canEditAsset($asset, $user)) showErrorPage(HTTP_FORBIDDEN);
		}

		if (!empty($_POST["delete"])) {
			validateActionToken();

			$this->delete();
			exit();
		}


		$status = '';
		if (!empty($_POST["save"])) {
			$this->savestatus = $status = $this->saveFromBrowser();

			if ($status == 'saved' || $status == 'savednew') {
				if (!empty($_POST['saveandback'])) {
					forceRedirect($this->getBackLink());
				}
				else if ($status == 'savednew') {
					forceRedirect("/edit/{$this->classname}?assetid={$this->assetid}");
				}
				else {
					forceRedirectAfterPOST();
				}
				exit();
			}
		}



		$this->loadFromDB();

		if ($this->assetid && !$this->asset) {
			$view->assign("reason", "This asset does not exist (anymore)");
			$view->display("404");
			exit();
		}


		if ($status != 'saved' && $status != 'savednew') {
			foreach ($this->columns as $column) {
				$col = $column["code"];
				if (!empty($_POST[$col])) {
					$this->asset[$col] = $_POST[$col];
				}
			}

			if (!empty($_POST['tagids'])) {
				$this->asset['tags'] = array_combine($_POST['tagids'], $_POST['tagids']);
			}

			$this->asset['numsaved'] = empty($_POST['numsaved']) ? 0 : $_POST['numsaved'];
		}

		if ($status == 'conflict') {
			addMessage(MSG_CLASS_ERROR, 'Cannot save, asset has been modified. Please open the asset in a new tab and manually merge your changes in the new tab and save there.');
		}

		if ($this->assetid) {
			$this->files = $con->getAll("select *, concat(ST_X(imagesize), 'x', ST_Y(imagesize)) as imagesize from file where assetid=?", array($this->assetid));
		} else {
			$assettypeid = $con->getOne("select assettypeid from assettype where code=?", array($this->tablename)); // @perf

			$this->files = $con->getAll("select *, concat(ST_X(imagesize), 'x', ST_Y(imagesize)) as imagesize from file where assetid is null and assettypeid=? and userid=?", array($assettypeid, $user['userid']));
		}

		foreach ($this->files as &$file) {
			$file["created"] = date("M jS Y, H:i:s", strtotime($file["created"]));

			$file["ext"] = substr($file["filename"], strrpos($file["filename"], ".")+1); // no clue why pathinfo doesnt work here
			$file["url"] = maybeFormatDownloadTrackingUrlDependingOnFileExt($file);
		}
		unset($file);

		$view->assign("files", $this->files);

		$comments = $con->getAll(<<<SQL
			SELECT comment.*, user.name AS username, IFNULL(user.banneduntil >= NOW(), 0) AS `isbanned`
			FROM comment 
			JOIN user ON user.userid = comment.userid
			WHERE assetid = ? AND comment.deleted = 0
			ORDER BY comment.created DESC
		SQL, [$this->assetid]);

		$view->assign("comments", $comments, null, true);


		$changelogs = $con->getAll(<<<SQL
			SELECT ch.text, ch.lastModified, user.name AS username
			FROM Changelogs ch
			JOIN user ON user.userid = ch.userId
			WHERE ch.assetId = ?
			ORDER BY ch.created DESC
			LIMIT 20
		SQL, [$this->assetid]);

		$view->assign("changelogs", $changelogs);

		$assettypes = $con->getAll('SELECT * FROM assettype');
		$view->assign("assettypes", $assettypes);
	}

	function delete()
	{
		global $con;
		$con->Execute("delete from asset where assetid=?", array($this->assetid));
		$con->Execute("delete from `{$this->tablename}` where {$this->tablename}id=?", array($this->recordid));

		logAssetChanges(array("Deleted asset"), $this->assetid);

		header("Location: /list/{$this->classname}?deleted=1");
	}

	function getBackLink()
	{
		return "/list/{$this->classname}?saved=1";
	}

	function loadFromDB()
	{
		global $con;
		$tablename = $this->tablename;

		if (!$this->assetid) {
			$assettypeid = $con->getOne("select assettypeid from assettype where code=?", array($this->tablename));
			$this->asset = array("assetid" => 0, "assettypeid" => $assettypeid, "name" => "", "statusid" => 0, "text" => "", "numsaved" => 0);

			foreach ($this->columns as $column) {
				$this->asset[$column['code']] = null;
			}
			return;
		}

		$this->asset = $con->getRow("
			select 
				asset.*, 
				`{$tablename}`.*,
				createduser.userid as createduserid,
				createduser.name as createdusername,
				editeduser.userid as editeduserid,
				editeduser.name as editedusername
			from 
				asset 
				join `{$tablename}` on asset.assetid=`{$tablename}`.assetid
				left join user as createduser on asset.createdbyuserid = createduser.userid
				left join user as editeduser on asset.editedbyuserid = editeduser.userid
				left join status on asset.statusid = status.statusid
			where
				asset.assetid = ?
		", array($this->assetid));

		if (empty($this->asset)) return;

		$rows = $con->getCol("select tagid from assettag where assetid=?", array($this->assetid));
		$tags = array_combine($rows, array_fill(0, count($rows), 1));
		$this->asset["tags"] = $tags;
	}

	/**
	 * @return 'savednew'|'saved'|'error'
	 */
	function saveFromBrowser()
	{
		global $con, $user, $view;

		$this->isnew = false;
		$assetdb = array();
		$changes = array();

		$status = 'savednew';

		$oldstatusid = 0;

		validateActionToken();

		if (!$this->assetid) {
			$this->assetid = insert("asset");
			$this->recordid = insert($this->tablename);
			$this->isnew = true;

			$assettypeid = $con->getOne("select assettypeid from assettype where code=?", array($this->tablename));

			update("asset", $this->assetid, array("createdbyuserid" => $user["userid"], "assettypeid" => $assettypeid));
			update($this->tablename, $this->recordid, array("assetid" => $this->assetid));

			$filesIds = $con->getCol("select * from file where assetid is null and userid=? and assettypeid=?", array($user['userid'], $assettypeid));

			if (!empty($filesIds)) {
				// @security: We just grabbed the ids two lines above from the database, direct interpolation is fine.
				$con->execute('update file set assetid = ? where fileid in (' . implode(',', $filesIds) . ')', $this->assetid);
			}

			$this->asset = [
				'assetid' => $this->assetid,
				'assettypeid' => $assettypeid,
				'createdbyuserid' => $user['userid'],
			];

			addMessage(MSG_CLASS_OK, $this->namesingular.' created.'); // @escurity: $this->namesingular is manually speciifed and contains no external input.
		} else {
			addMessage(MSG_CLASS_OK, $this->namesingular.' saved.'); // @escurity: $this->namesingular is manually speciifed and contains no external input.
			$this->loadFromDB();
			$assetdb = $this->asset;
			$oldstatusid = $this->asset["statusid"];

			$status = 'saved';
		}

		$assetdata = array("editedbyuserid" => $user["userid"]);
		$recorddata = array();

		foreach ($this->columns as $column) {
			$col = $column["code"];
			$val = null;

			if (!empty($_POST[$col])) {
				$val = $_POST[$col];

				$datatype = "text";
				if (!empty($column['datatype'])) $datatype = $column['datatype'];

				if ($datatype == "url") {
					if (!isUrl($val)) {
						addMessage(MSG_CLASS_ERROR, "Not saved. {$column['title']} is not valid. Please use only allowed characters and prefix with http(s)://"); // @security: Column titles are manually defined, no external input.
						return 'error';
					}
				}

				if ($datatype == "text") {
					if (!isNumber($val)) {
						$val = sanitizeHtml($val, array('safe' => 1));
					}
				}
			}

			if ($column["tablename"] == "asset") {
				$assetdata[$col] = $val;
			} else {
				$recorddata[$col] = $val;
			}

			if (!$this->isnew && $assetdb[$col] != $val) {
				$newvalue = empty($_POST[$col]) ? null : $_POST[$col];
				$changes[] = $this->getChangeLogEntry($column, $assetdb[$col], $newvalue);
			}
		}


		$tagchanges = $this->updateTags($this->assetid);
		$changes = array_merge($changes, $tagchanges);

		if ($oldstatusid != STATUS_3 && $_POST["statusid"] == STATUS_3) {
			$assetdata["readydate"] = date("Y-m-d H:i:s");
		}
		else if($oldstatusid == STATUS_LOCKED) {
			$modId = intval($this->asset['modid']);
			if($_POST["statusid"] != STATUS_LOCKED) {
				if(!canModerate(null, $user)) {
					addMessage(MSG_CLASS_ERROR, "Only moderators may change the state of a locked mod.");
					$_POST['statusid'] = STATUS_LOCKED;
					return 'error';
				}

				$createdById = intval($this->asset['createdbyuserid']);
				// @security: $modId and $createdById are known to be integers and therefore sql inert.
				$con->execute("INSERT INTO Notifications (kind, recordId, userId) values ('modunlocked', $modId, $createdById)");
				// Read the unlock request just in case we didn't before and only publsihed the mod again.
				$con->execute("UPDATE Notifications SET `read` = 1 WHERE kind = 'modunlockrequest' AND userId = ? AND recordId = ?", [$user['userid'], $modId]);
			}
			else {
				$moderatorUserId = $con->getOne('
					SELECT moderatorid
					FROM moderationrecord
					WHERE kind = '.MODACTION_KIND_LOCK." and until >= NOW() and recordid = $modId
				");
				// @security: $modId and $moderatorUserId are known to be integers and therefore sql inert.
				$requestExists = $con->getOne("SELECT 1 FROM Notifications WHERE kind = 'modunlockrequest' AND !`read` AND recordId = $modId AND userId = $moderatorUserId");
				if(!$requestExists) { // prevent spam :BlockedUnlockRequest
					$con->execute("INSERT INTO Notifications (kind, recordId, userid) VALUES ('modunlockrequest', $modId, $moderatorUserId)");
				}
			}
		}

		$assetdata['numsaved'] = $_POST['numsaved'] + 1;

		update("asset", $this->assetid, $assetdata);

		if (count($recorddata)) {
			update($this->tablename, $this->recordid, $recorddata);
		}

		if ($this->isnew) {
			logAssetChanges(array("Created asset"), $this->assetid);
		} else {
			logAssetChanges($changes, $this->assetid);
		}

		return $status;
	}


	function getChangeLogEntry($column, $oldvalue, $newvalue)
	{
		global $con;

		if ($column["code"] == "statusid") {
			$oldstatuscode = $con->getOne("select name from status where statusid=?", array($oldvalue));
			$newstatuscode = $con->getOne("select name from status where statusid=?", array($newvalue));

			return  "Modified Status ($oldstatuscode => $newstatuscode)";
		}

		return  "Modified {$column['title']}";
	}


	public function display()
	{
		global $con, $view, $user;

		$sqlFilterLockedStatus = $this->asset['statusid'] == STATUS_LOCKED ? '' : ('where statusid != '.STATUS_LOCKED);
		$stati = $con->getAll("select * from status $sqlFilterLockedStatus");
		$view->assign("stati", $stati);

		$view->assign("user", $user);

		$tags = $this->tablename === 'mod' ? $con->getAll("SELECT * FROM Tags ORDER BY name") : [];
		$view->assign("tags", $tags);

		$view->assign("asset", $this->asset);

		$this->displayTemplate($this->editTemplateFile);
	}

	/**
	 * @param int $assetId
	 * @return string[] changelog
	 */
	function updateTags($assetId)
	{
		global $con;

		$tagIds = array_flip($con->getCol("SELECT tagid FROM assettag WHERE assetid = ?", [$assetId]));

		$changes = [];
		$tagData = [];

		if (!empty($_POST["tagids"])) {

			foreach ($_POST["tagids"] as $tagId) {
				$tag = $con->getRow('SELECT * FROM Tags WHERE tagId = ?', [$tagId]);

				$assetTagId = $con->getOne("SELECT assettagid from assettag where assetid = ? and tagid = ?", [$assetId, $tagId]);
				if (!$assetTagId) {
					$assetTagId = insert("assettag");
					$con->execute('UPDATE assettag SET assetid = ?, tagid = ? WHERE assettagid = ?', [$assetId, $tagId, $assetTagId]);
					$changes[] = "Added tag '{$tag['name']}'";
				}

				unset($tagIds[$tagId]);

				$tagData[] = $tag["name"] . ",#" . str_pad(dechex($tag["color"]), 8, '0') . "," . $tag["tagId"];
			}
		}

		foreach ($tagIds as $tagId => $_) {
			$con->Execute('DELETE FROM assettag WHERE assetid = ? AND tagid = ?', [$assetId, $tagId]);

			$tagname = $con->getOne('SELECT name FROM Tags WHERE tagId = ?', [$tagId]);
			$changes[] = "Deleted tag '{$tagname}'";
		}

		$con->execute('UPDATE asset SET tagscached = ? WHERE assetid = ?', [implode("\r\n", $tagData), $assetId]);

		return $changes;
	}
}
