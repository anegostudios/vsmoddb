<?php

class FeaturereleaseList extends AssetList {
	
	
	function __construct() {
		parent::__construct("featurerelease");
		
		$this->deleteColumn(0);
		$this->deleteColumn(2);
		$this->deleteColumn(3);
		$this->deleteColumn(4);
		
		$this->declareColumn(2, array("title" => "Release Progress", "code" => "releaseprogress"));
		
		$this->orderby = "releaseorder desc";
	}
	
}