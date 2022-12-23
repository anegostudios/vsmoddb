<?php

class ModEditor extends AssetEditor {
	
	
	function __construct() {
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
	
	function load() {
		global $view;
		
		parent::load();
		
		$view->assign("modtypes", array(
			array('code' => "mod", "name" => "Game mod"),
			array('code' => "externaltool", "name" => "External tool"),
			array('code' => "other", "name" => "Other"),
		));
		
		if (!$this->assetid) {
			$this->asset['type'] = 'mod';
		}
	}
	
	function delete() {
		global $con;
		$modid = $con->getOne("select modid from `mod` where assetid=?", array($this->assetid));
		$con->Execute("delete from `release` where modid=?", array($modid));
		parent::delete();
	}
	
	function saveFromBrowser() {
		global $con, $view, $typewhitelist;
		
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
		
		$hasfiles = $con->getOne("select releaseid from `release` where assetid=?", array($this->assetid));
		$statusreverted = false;
		if ($_POST['statusid'] != 1 && !$hasfiles) {
			$statusreverted = true;
			$_POST['statusid']=1;
		}
		
		$oldlogofileid = $con->getOne("select logofileid from `mod` where assetid=?", array($this->assetid));
		$result = parent::saveFromBrowser();
		$newlogofileid = $con->getOne("select logofileid from `mod` where assetid=?", array($this->assetid));
		
		if ($newlogofileid != $oldlogofileid) {
			$this->generateLogoImage($newlogofileid);
		}
		
		if ($this->isnew) {
			$con->Execute("update `mod` set lastreleased=now() where assetid=?", array($this->assetid));
		}
		
		if ($statusreverted) {
			$view->unsetVar("okmessage");
			$view->assign("warningmessage", "Changes saved, but your mod remains in 'Draft' status. You must upload a playable mod/tool first.");
			return "error";
		}

		return $result;
	}
	
	function generateLogoImage($logofileid) {
		global $con, $config;
		
		$file = $con->getRow("select * from file where fileid=?", array($logofileid));
		if (empty($file)) return;
		$srcpath = $config['basepath'] . "files/asset/" . $this->assetid . "/" . $file['filename'];
		
		$filetype = pathinfo($file['filename'], PATHINFO_EXTENSION);
		
		$logofilename = null;
		
		if (file_exists($srcpath)) {
			$logofilename  = "logo." . $filetype;
			$destpath = $config['basepath'] . "files/asset/" . $this->assetid . "/" . $logofilename ;
			$filename = copyImageResized($srcpath, 480, 320, true, 'file', '', $destpath);
		}
		
		$con->Execute("update `mod` set logofilename=? where assetid=?", array($logofilename, $this->assetid));
	}
}
