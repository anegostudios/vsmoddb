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
		global $view, $con; 
		
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
		
		$searchparams = "";
		if (isset($_GET['text'])) {
			$searchparams.="text={$_GET['text']}";
		}
		if (isset($_GET["tagids"])) {
			foreach($_GET["tagids"] as $tagid) {
				$searchparams .= "&tagids[]={$tagid}";
			}
		}
		if (isset($_GET["gameversion"])) {
			$searchparams .= "&gameversion[]={$_GET['gameversion']}";
		}
		if (isset($_GET["userid"])) {
			$searchparams .= "&userid={$_GET['userid']}";
		}
		$view->assign("searchparams", $searchparams);
		
		if ($sortby == "name") $sortby = "asset." . $sortby;
		else $sortby = "mod." . $sortby;
		
		$this->orderby = "{$sortby} {$sortdir}";
		
		if ($sortby == "mod.trendingpoints") $this->orderby="trendingpoints {$sortdir}, `mod`.lastmodified {$sortdir}";
		
		parent::load();
		
		$versions = $con->getAll("select * from tag where assettypeid=?", array(2));
		$versions = sortTags(2, $versions);
		$view->assign("versions", $versions);
		
		$majorversions = $con->getAll("select * from majorversion");
		$majorversions = sortTags(2, $majorversions);
		$view->assign("majorversions", $majorversions);
		
		
		$authors = $con->getAll("select user.userid, user.name from user join asset on asset.createdbyuserid = user.userid group by user.userid order by name asc");
		$view->assign("authors", $authors);
	}
	
	
	public function loadFilters() {
		global $user;
		parent::loadFilters();

		if ($user['rolecode'] != 'admin' || empty($_GET['hidden'])) {
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
