<?php

class AssetController {
	var $classname;
	var $classnameplural;
	var $tablename;	
	var $namesingular;
	var $nameplural;

	function __construct($classname) {
	
		$this->classname = $classname;
		$this->classnameplural = $this->classname . "s";
		
		$this->tablename = strtolower($this->classname);
		
		$this->namesingular = ucfirst($this->classname);
		$this->nameplural = $this->namesingular . "s";
		
		$this->columns = array(
		);
	}
	
	function declareColumn($position, $column) {
		if (!isset($column["cssclassname"])) {
			$column["cssclassname"] = "";
		}
		if (!isset($column["datatype"])) {
			$column["datatype"] = "text";
		}
		array_splice($this->columns, $position, 0, array($column));
	}
	
	function deleteColumn($position) {
		unset($this->columns[$position]);
	}
	
	public function displayTemplate($template) {
		global $view;
		
		$view->assign("entrycode", $this->classname);
		$view->assign("entriescode", $this->classnameplural);
		
		$view->assign("entrysingular", $this->namesingular);
		$view->assign("entryplural", $this->nameplural);
		
		$view->assign("columns", $this->columns);
		
		$view->display($template);
	}
}
