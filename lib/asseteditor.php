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
			array("title" => "Name", "code" => "name", "tablename" => "assets", "datatype" => "name"),
			array("title" => "Text", "code" => "text", "tablename" => "assets"),
			array("title" => "Status", "code" => "statusId", "tablename" => "assets", "default" => 1),
		);
	}

	public function load()
	{
		global $con, $user, $view;

		$this->assetid = empty($_REQUEST["assetid"]) ? 0 : $_REQUEST["assetid"];
		$this->recordid = null;

		if ($this->assetid) {
			
			$this->recordid = $con->getOne("select {$this->namesingular}Id from `{$this->tablename}` where assetId = ?", array($this->assetid));

			$asset = $con->getRow("select * from assets where assetId = ?", array($this->assetid));

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

			$this->asset['numSaved'] = empty($_POST['numsaved']) ? 0 : $_POST['numsaved'];
		}

		if ($status == 'conflict') {
			addMessage(MSG_CLASS_ERROR, 'Cannot save, asset has been modified. Please open the asset in a new tab and manually merge your changes in the new tab and save there.');
		}

		if ($this->assetid) {
			$this->files = $con->getAll(<<<SQL
				SELECT f.*, i.hasThumbnail, CONCAT(ST_X(i.size), 'x', ST_Y(i.size)) AS imageSize
				FROM files f
				LEFT JOIN fileImageData i ON i.fileId = f.fileId
				WHERE assetId = ?
			SQL, [$this->assetid]);
		} else if($this->tablename === 'mods') {
			$this->files = $con->getAll(<<<SQL
				SELECT f.*, i.hasThumbnail, concat(ST_X(i.size), 'x', ST_Y(i.size)) AS imageSize
				FROM files f
				LEFT JOIN fileImageData i ON i.fileId = f.fileId
				WHERE f.assetId IS NULL AND f.assetTypeId = ? AND f.userId = ?
			SQL, [ASSETTYPE_MOD, $user['userId']]);
		}

		foreach ($this->files as &$file) {
			$file['created'] = date('M jS Y, H:i:s', strtotime($file['created']));

			$file['ext'] = substr($file['name'], strrpos($file['name'], '.')+1); // no clue why pathinfo doesnt work here
			$file['url'] = maybeFormatDownloadTrackingUrlDependingOnFileExt($file);
		}
		unset($file);

		$view->assign('files', $this->files);

		$changelogs = $con->getAll(<<<SQL
			SELECT ch.text, ch.lastModified, u.name AS username
			FROM changelogs ch
			JOIN users u ON u.userId = ch.userId
			WHERE ch.assetId = ?
			ORDER BY ch.created DESC
			LIMIT 20
		SQL, [$this->assetid]);

		$view->assign("changelogs", $changelogs);
	}

	function delete()
	{
		global $con;
		$con->Execute("delete from assets where assetId = ?", array($this->assetid));
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
			assert($this->tablename === 'mods');
			$this->asset = array("assetId" => 0, "assetTypeId" => ASSETTYPE_MOD, "name" => "", "statusId" => 0, "text" => "", "numSaved" => 0);

			foreach ($this->columns as $column) {
				$this->asset[$column['code']] = null;
			}
			return;
		}

		$this->asset = $con->getRow("
			select 
				asset.*, 
				`{$tablename}`.*,
				creator.userId as createduserid,
				creator.name as createdusername,
				editor.userId as editeduserid,
				editor.name as editedusername
			from 
				assets asset 
				join `{$tablename}` on asset.assetId = `{$tablename}`.assetId
				left join users as creator on creator.userId = asset.createdByUserId
				left join users as editor on editor.userId = asset.editedByUserId
			where
				asset.assetId = ?
		", array($this->assetid));

		if (empty($this->asset)) return;

		if ($this->tablename === 'mods') {
			$this->asset['tags'] = array_flip($con->getCol('SELECT tagId FROM modTags WHERE modId = ?', array($this->asset['modId'])));
		}
	}

	/**
	 * @return 'savednew'|'saved'|'error'
	 */
	function saveFromBrowser()
	{
		global $con, $user;

		$this->isnew = false;
		$assetdb = array();
		$changes = array();

		$status = 'savednew';

		$oldstatusid = 0;

		validateActionToken();

		if (!$this->assetid) {
			$this->isnew = true;

			assert($this->tablename == 'mods');
			$assetTypeId = ASSETTYPE_MOD;

			$con->execute('INSERT INTO assets (createdByUserId, statusId, assetTypeId) VALUES (?, ?, ?)', 
				[$user['userId'], STATUS_DRAFT, $assetTypeId]
			);
			$this->assetid = $con->Insert_ID();

			$con->execute("INSERT INTO {$this->tablename} (assetId, summary) VALUES (?, '')", [$this->assetid]);
			$this->recordid = $con->Insert_ID();

			$con->execute('UPDATE mods SET assetId = ? WHERE modId = ?', [$this->assetid, $this->recordid]);

			$filesIds = $con->getCol("SELECT fileId FROM files WHERE assetId IS NULL AND userId = ? and assetTypeId = ?", [$user['userId'], $assetTypeId]);

			if (!empty($filesIds)) {
				// @security: We just grabbed the ids two lines above from the database, direct interpolation is fine.
				$con->execute('UPDATE files SET assetId = ? WHERE fileId IN (' . implode(',', $filesIds) . ')', [$this->assetid]);
			}

			$this->asset = [
				'assetId' => $this->assetid,
				'assetTypeId' => $assetTypeId,
				'createdByUserId' => $user['userId'],
			];

			addMessage(MSG_CLASS_OK, $this->namesingular.' created.'); // @escurity: $this->namesingular is manually speciifed and contains no external input.
		} else {
			addMessage(MSG_CLASS_OK, $this->namesingular.' saved.'); // @escurity: $this->namesingular is manually speciifed and contains no external input.
			$this->loadFromDB();
			$assetdb = $this->asset;
			$oldstatusid = $this->asset["statusId"];

			$status = 'saved';
		}

		$assetdata = array("editedByUserId" => $user["userId"]);
		$recorddata = array();

		foreach ($this->columns as $column) {
			$col = $column["code"];
			$val = $column["default"] ?? null;

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

			if ($column["tablename"] == "assets") {
				$assetdata[$col] = $val;
			} else {
				$recorddata[$col] = $val;
			}

			if (!$this->isnew && $assetdb[$col] != $val) {
				$newvalue = empty($_POST[$col]) ? null : $_POST[$col];
				$changes[] = $this->getChangeLogEntry($column, $assetdb[$col], $newvalue);
			}
		}


		if ($oldstatusid != STATUS_3 && $_POST["statusId"] == STATUS_3) {
			$assetdata["readydate"] = date("Y-m-d H:i:s"); //TODO(Rennorb) @cleanup
		}
		else if($oldstatusid == STATUS_LOCKED) {
			$modId = intval($this->asset['modId']);
			if($_POST["statusId"] != STATUS_LOCKED) {
				if(!canModerate(null, $user)) {
					addMessage(MSG_CLASS_ERROR, "Only moderators may change the state of a locked mod.");
					$_POST['statusId'] = STATUS_LOCKED;
					return 'error';
				}

				$createdById = intval($this->asset['createdByUserId']);
				// @security: $modId and $createdById are known to be integers and therefore sql inert.
				$con->execute("INSERT INTO notifications (kind, recordId, userId) values ('modunlocked', $modId, $createdById)");
				// Read the unlock request just in case we didn't before and only publsihed the mod again.
				$con->execute("UPDATE notifications SET `read` = 1 WHERE kind = 'modunlockrequest' AND userId = ? AND recordId = ?", [$user['userId'], $modId]);
			}
			else {
				$moderatorUserId = $con->getOne('
					SELECT moderatorId
					FROM moderationRecords
					WHERE kind = '.MODACTION_KIND_LOCK." and until >= NOW() and recordId = $modId
				");
				// @security: $modId and $moderatorUserId are known to be integers and therefore sql inert.
				$requestExists = $con->getOne("SELECT 1 FROM notifications WHERE kind = 'modunlockrequest' AND !`read` AND recordId = $modId AND userId = $moderatorUserId");
				if(!$requestExists) { // prevent spam :BlockedUnlockRequest
					$con->execute("INSERT INTO notifications (kind, recordId, userId) VALUES ('modunlockrequest', $modId, $moderatorUserId)");
				}
			}
		}

		$assetdata['numSaved'] = $_POST['numsaved'] + 1;

		{
			// @security columns are manually set in the class constructor and do not contain user input
			$assignments = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($assetdata)));
			$values = array_values($assetdata);
			$values[] = $this->assetid;
			$con->execute("UPDATE assets SET $assignments WHERE assetId = ?", $values);
		}

		if (count($recorddata)) {
			assert($this->tablename === 'mods');
		
			{
				// @security columns are manually set in the class constructor and do not contain user input
				$assignments = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($recorddata)));
				$values = array_values($recorddata);
				$values[] = $this->recordid;
				$con->execute("UPDATE mods SET $assignments WHERE modId = ?", $values);
			}
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

		if ($column["code"] == "statusId") {
			$oldstatuscode = $con->getOne("select name from status where statusId = ?", array($oldvalue));
			$newstatuscode = $con->getOne("select name from status where statusId = ?", array($newvalue));

			return  "Modified status ($oldstatuscode => $newstatuscode)";
		}

		return  "Modified {$column['title']}";
	}


	public function display()
	{
		global $con, $view, $user;

		$sqlFilterLockedStatus = $this->asset['statusId'] == STATUS_LOCKED ? '' : ('where statusId != '.STATUS_LOCKED);
		$stati = $con->getAll("select * from status $sqlFilterLockedStatus");
		$view->assign("stati", $stati);

		$view->assign("user", $user);

		$tags = $this->tablename === 'mods' ? $con->getAll("SELECT * FROM tags ORDER BY name") : [];
		$view->assign("tags", $tags);

		$view->assign("asset", $this->asset);

		$this->displayTemplate($this->editTemplateFile);
	}
}
