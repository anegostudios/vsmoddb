<?php

class ModList extends AssetList {
	
	var $sortbys = array(
		"trendingpoints" => array("Most trending", "Least trending"),
		"downloads" => array("Most downloaded", "Least downloaded"),
		"comments" => array("Most comments", "Least comments"),
		"name" => array("Name descending", "Name ascending"),
		"lastreleased" => array("Recently updated", "Least updated"),
		"created" => array("Recently added", "First added")
	);
	
	function __construct() {
		
		parent::__construct("mod");
		
		$this->namesingular = "Mod";
		$this->nameplural = "Mods";
		$this->extracolumns=",urlalias";
	}
	
	public function load() {
		global $view, $con, $user;
		
		$sortdefaults = array("lastreleased", "desc");
		if (!empty($_COOKIE["vsmoddb_modlist_sort"])) {
			//$sortdefaults = explode(" ", $_COOKIE["vsmoddb_modlist_sort"]);
		}
		
		$sortby = $sortdefaults[0];
		if (isset($_GET['sortby']) && isset($this->sortbys[$_GET['sortby']])) {
			$sortby = $_GET['sortby'];
		}

		$sortdir = $sortdefaults[1];
		$nowdir = 0;
		if (isset($_GET['sortdir']) && $_GET['sortdir'] == "a") { $nowdir = 1; $sortdir = "asc"; }
		if (isset($_GET['sortdir']) && $_GET['sortdir'] == "d") { $nowdir = 0; $sortdir = "desc"; }
		
		$view->assign("sortby", $sortby);
		$view->assign("sortbypretty", strtolower($this->sortbys[$sortby][$nowdir]));
		$view->assign("sortdir", $sortdir);
		$view->assign("sortbys", $this->sortbys);
		
		setcookie("vsmoddb_modlist_sort", $this->orderby, time() + 24*365*3600);
		
		$searchparams = array();
		if (isset($_GET['text'])) {
			$view->assign("search", 1);
		}
		
		if (isset($_GET['text'])) {
			$searchparams[] = "text={$_GET['text']}";
		}
		if (isset($_GET["tagids"])) {
			foreach($_GET["tagids"] as $tagid) {
				if (!empty($tagid)) {
					$searchparams[] = "tagids[]={$tagid}";
				}
			}
		}
		if (isset($_GET["gameversion"])) {
			$searchparams[] = "gameversion[]={$_GET['gameversion']}";
		}
		if (isset($_GET["gv"])) {
			if (is_array($_GET['gv'])) {
				foreach ($_GET['gv'] as $gv) $searchparams[] = "gv[]={$gv}";
			}
		}
		if (isset($_GET["userid"])) {
			$searchparams[] = "userid={$_GET['userid']}";
		}
		if (isset($_GET['side']) && ($_GET['side']=='client' || $_GET['side']=='server' || $_GET['side']=='both')) {
			$searchparams[] = "side={$_GET['side']}";
		}
		if (isset($_GET['mv'])) {
			$searchparams[] = "mv={$_GET['mv']}";
		}
		
		if (count($searchparams) > 0) {
			$view->assign("searchparams", implode("&", $searchparams));
		} else {
			$view->assign("searchparams", "");
		}
		
		if ($sortby == "name") $sortby = "asset." . $sortby;
		else $sortby = "mod." . $sortby;
		
		$this->orderby = "{$sortby} {$sortdir}";
		
		if ($sortby == "mod.trendingpoints") $this->orderby="trendingpoints {$sortdir}, `mod`.lastmodified {$sortdir}";
		
		$this->searchvalues = array("text" => "", "statusid" => null);
		
		$this->loadFilters();
		
		//TODO(Rennorb) @cleanup
		// I was not able to find a better solution for this, without rewriting the whole "assetcontroller" inheritance system.
		// This is ugly, but should not incur any noticable overhead.
		$logopathselector = $this->tablename === 'mod' ? "logofile.cdnpath as logocdnpath, logofile.created < '".SQL_MOD_CARD_TRANSITION_DATE."' as legacylogo," : '';
		$logopathjoiner   = $this->tablename === 'mod' ? 'left join file as logofile on `mod`.logofileiddb = logofile.fileid' : '';

		$selfuserid = $user['userid'] ?? -1;
		$sql = "
			select 
				asset.createdbyuserid,
				asset.editedbyuserid,
				asset.statusid,
				asset.name,
			 	asset.assettypeid,
				asset.code,
				asset.created,
				asset.lastmodified,
				asset.tagscached,
				`{$this->tablename}`.*,
				{$logopathselector}
				user.name as `from`,
				status.code as statuscode,
				status.name as statusname{$this->extracolumns},
				`follow`.userid as following
			from 
				asset 
				join `{$this->tablename}` on asset.assetid = `{$this->tablename}`.assetid
				left join user on asset.createdbyuserid = user.userid
				left join status on asset.statusid = status.statusid
				left join `follow` on `mod`.modid = follow.modid and follow.userid = {$selfuserid}
				{$logopathjoiner}
			" . (count($this->wheresql) ? "where " . implode(" and ", $this->wheresql) : "") . "
			order by {$this->orderby}
		";

		$rows = $con->getAll($sql, $this->wherevalues);
		$this->rows = array();

		foreach ($rows as $row) {
			unset($row['text']);
			$row['modpath'] = formatModPath($row);

			if (isset($_GET['text'])) {
				$row['weight'] = $this->getModMatchWeight($row, $_GET['text']);
			}
			
			$this->rows[] = $row;
		}
		
		if (!empty($_GET['text'])) {
			usort($this->rows, 'modWeightCmp');
		}
		
		
		$versions = $con->getAll("select * from tag where assettypeid=?", array(2));
		$versions = sortTags(2, $versions);
		$view->assign("versions", $versions);
		
		$majorversions = $con->getAll("select * from majorversion");
		$majorversions = sortTags(2, $majorversions);
		$view->assign("majorversions", $majorversions);
		
		
		$authors = $con->getAll("select user.userid, user.name from user join asset on asset.createdbyuserid = user.userid group by user.userid order by name asc");
		$view->assign("authors", $authors);
	}
	
	
	function getModMatchWeight($mod, $text) {
		if (empty($text)) return 5;
		
		// Exact mod name match
		if (strcasecmp($mod['name'], $text) == 0) return 1;
		$pos = stripos($mod['name'], $text);
		// Mod name starts with text
		if ($pos === 0) return 2;
		// Mod name contains text
		if ($pos > 0) return 3;
		// Summary contains text
		if (strstr($mod['summary'], $text)) return 4;
		// Contained somewhere
		return 5;
	}
	
	public function loadFilters() {
		global $user, $con;

		if (!empty($_GET["text"])) {

			$this->wheresql[] = "(asset.name like ? or asset.text like ? or `mod`.summary like ?)";
			
			$this->wherevalues[] = "%" . $_GET["text"] . "%";
			$this->wherevalues[] = "%" . $_GET["text"] . "%";
			$this->wherevalues[] = "%" . $_GET["text"] . "%";

			$this->searchvalues["text"] = $_GET["text"];
		}
		
		if(!empty($_GET["tagids"])) {
			$wheresql = "";
			foreach($_GET["tagids"] as $tagid) {
				if (empty($tagid)) continue;
				if (!empty($wheresql)) $wheresql .= " or ";
				$wheresql .= "exists (select assettag.tagid from assettag where assettag.assetid=asset.assetid and assettag.tagid=?)";
				$this->wherevalues[] = $tagid;
			}
			
			if (!empty($wheresql)) {
				$this->wheresql[] .= "(" . $wheresql . ")";
				
				$assettypeid = $con->getOne("select assettypeid from assettype where code=?", array($this->tablename));
				$this->searchvalues["tagids"] = array_combine($_GET["tagids"], array_fill(0, count($_GET["tagids"]), 1));
			}
		}
		
		if (empty($user) || $user['rolecode'] != 'admin' || empty($_GET['hidden'])) {
			$this->wheresql[] = "asset.statusid=2";
		}

		$gvs = null;
		if (!empty($_GET["gameversions"])) {
			$gvs = $_GET["gameversions"];
		}
		if (!empty($_GET["gv"])) {
			$gvs = $_GET["gv"];
		}
		if (!empty($_GET['side']) && ($_GET['side']=='client' || $_GET['side']=='server' || $_GET['side']=='both')) {
			$this->wheresql[] = "side=?";
			$this->wherevalues[] = $_GET['side'];
			$this->searchvalues['side'] = $_GET['side'];
		}
		
		if (!empty($_GET['mv'])) {
			$this->wheresql[] = "exists (select modid from majormodversioncached where majorversionid=? and majormodversioncached.modid=`mod`.modid)";
			$this->wherevalues[] = $_GET['mv'];
			$this->searchvalues["mv"] = $_GET['mv'];
		}


		if ($gvs) {
			$gamevers = array();
			foreach($gvs as $gameversion) {
				$gamevers[] = intval($gameversion);
			}
			
			$this->wheresql[] = "exists (select 1 from modversioncached where `mod`.modid =`modversioncached`.modid and modversioncached.tagid in (".implode(",", $gamevers)."))";
			$this->searchvalues["gameversions"] = array_combine($gvs, array_fill(0, count($gvs), 1));
		}
		
		if (!empty($_GET["userid"])) {
			$this->wheresql[] = "asset.createdbyuserid=?";
			$this->wherevalues[] = intval($_GET["userid"]);
			$this->searchvalues["userid"] =  intval($_GET["userid"]);
		}

	}
}

function modWeightCmp($moda, $modb) {
	return $moda['weight'] <=> $modb['weight'];
}

