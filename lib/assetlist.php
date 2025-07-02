<?php

class AssetList extends AssetController {
	var $rows;
	var $searchvalues;

	var $extracolumns;
	var $orderby = "asset.created desc";

	function __construct($classname) {
		parent::__construct($classname);
		
		$this->declareColumn(0, array("title" => "Tags", "code" => "tagscached", "datatype" => "tags"));
		$this->declareColumn(1, array("title" => "Name", "code" => "name"));
		$this->declareColumn(2, array("title" => "Status", "code" => "statusname"));
		$this->declareColumn(3, array("title" => "Created By", "code" => "from"));
	}
	
	var $wheresql = array();
	var $wherevalues = array();
	
	public function load() {
		global $con;

		
		$this->searchvalues = array("text" => "", "statusid" => null);
		
		$this->loadFilters();	
		
		$sql = "
			select 
				asset.*, 
				`{$this->tablename}`.*,
				user.name as `from`,
				status.code as statuscode,
				status.name as statusname{$this->extracolumns}
			from 
				asset 
				join `{$this->tablename}` on asset.assetid = `{$this->tablename}`.assetid
				left join user on asset.createdbyuserid = user.userid
				left join status on asset.statusid = status.statusid
			" . (count($this->wheresql) ? "where " . implode(" and ", $this->wheresql) : "") . "
			order by {$this->orderby}
		";

		
		$rows = $con->getAll($sql, $this->wherevalues);
		$this->rows = array();

		
		foreach ($rows as $row) {
			unset($row['text']);
			$tags = array();
			
			$tagscached = trim($row["tagscached"]);
			if (!empty($tagscached)) { 
			
				$tagdata = explode("\r\n", $tagscached);
				
				foreach($tagdata as $tagrow) {
					$parts = explode(",", $tagrow);
					$tags[] = array('name' => $parts[0], 'color' => $parts[1], 'tagId' => $parts[2]);
				}
			
				$row['tags'] = $tags;
			}
			$this->rows[] = $row;
		}
	}
	
	public function loadFilters() {
		if (!empty($_GET["text"])) {
			$this->wheresql[] = "(asset.name like ? or asset.text like ?)";
			
			$this->wherevalues[] = "%" . escapeStringForLikeQuery($_GET["text"]) . "%";
			$this->wherevalues[] = "%" . escapeStringForLikeQuery($_GET["text"]) . "%";

			$this->searchvalues["text"] = $_GET["text"];
		}
		
		if(!empty($_GET["tagids"])) {
			$wheresql = "";
			foreach($_GET["tagids"] as $tagId) {
				if (!empty($wheresql)) $wheresql .= " or ";
				$wheresql .= "exists (select assettag.tagid from assettag where assettag.assetid=asset.assetid and assettag.tagid = ?)";
				$this->wherevalues[] = $tagId;
			}
			
			$this->wheresql[] .= "(" . $wheresql . ")";
			
			$this->searchvalues["tagids"] = array_combine($_GET["tagids"], array_fill(0, count($_GET["tagids"]), 1));
		}
	}
	
	
	public function display() {
		global $view, $con;
		$view->assign("rows", $this->rows);
		
		$view->assign("searchvalues", $this->searchvalues);
		
		$stati = $con->getAll("select * from status order by sortorder");
		$view->assign("stati", $stati);
		
		if (!empty($_GET["deleted"])) {
			addMessage(MSG_CLASS_OK, $this->namesingular.' deleted.'); // @escurity: $this->namesingular is manually speciifed and contains no external input.
		}
		if (!empty($_GET["saved"])) {
			addMessage(MSG_CLASS_OK, $this->namesingular.' saved.'); // @escurity: $this->namesingular is manually speciifed and contains no external input.
		}
		
		if (file_exists("templates/list-{$this->classname}.tpl")) {
			$this->displayTemplate("list-{$this->classname}.tpl");
		} else {
			$this->displayTemplate("list-asset");
		}
	}
}
