<?php

class Asset {
	var $classname;
	var $tablename;

	var $id;
	var $data;

	function __construct($objectdata) {
		$this->classname = get_class($this);
		$this->tablename = strtolower($this->classname);
		
		if (is_array($objectdata)) {
			$this->data = $objectdata;
			$this->id = $objectdata["{$this->tablename}id"];
		} else {
			$this->id = $objectdata;
		}
	}
	
	// Returns if this object exists in the Database
	function exists() {
		if (!$this->data) $this->loadData();
		return $this->data["{$this->tablename}id"] > 0;
	}
	
	public static function createNew() {
		$assetid = insert("asset");
	
		$tablename = strtolower(get_called_class());
		$id = insert($tablename);
		
		update($tablename, "{$tablename}id", array('assetid' => $assetid));
		
		return self::loadFromDB($objectid); //TODO(Rennorb) @cleanup @explain: objectid is not defined, what's going on here.
	}
	
	
	public static function loadFromDB($id) {
		$classname = get_called_class();
		
		$asset = new $classname($id);
		$asset->loadData();
		
		return $object; //TODO(Rennorb) @cleanup @explain: objectid is not defined, what's going on here.
	}
	
	private function loadData() {
		global $con;
		
		$this->data = $con->getRow("select asset.*, {$this->tablename}.* from asset join {$this->tablename} on asset.assetid = {$this->tablename}.assetid where {$this->tablename}id = ?", array($this->id));
		$this->id = $this->data["{$this->tablename}id"];
	}
	
	public function save($data) {
		update($this->tablename, $this->id, $data);
		$this->data = array_merge($this->data, $data); 
	}
}