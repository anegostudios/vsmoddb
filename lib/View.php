<?php

/* Tiny Template Engine ;-)
 *
 * - Allows full PHP
 * - Assign view data via assign()
 * - In the View print variables via {$variablename}  or <?php echo $variablename; ?>
 * - In the View call functions via {test($variablename)}  or <?php echo test($variablename) ?>
 * - Escaping via \{} - so \{$bla} is not being parsed
 *
 * NOT possible:
 * - PHP Terms inside {} - e.g. {$num+$num} or {test($bla+$bla)}
 */
class View {
	private $data = array();
	var $templatedir = "";
	var $compiledir = "";
	
	private $viewfilename;
	
	var $config;
	
	function __construct() {
		global $website, $config;
		
		$this->templatedir = $config["basepath"] . "/templates/";
		$this->compiledir = $config["basepath"] . "/templates_c/";
		$this->config = $GLOBALS['config'];
	}
	
	function assign($name, $value, $defaultvalue = null, $unfiltered = false) {
		if (empty($value) && $defaultvalue != null) $value = $defaultvalue;
		
		// Data is filtered by default, access to raw data via [variablename]raw
		
		if ($unfiltered ) {
			$this->data[$name] = $value;
		} else {
			$this->data[$name] = $this->escape($value);
			$this->data[$name . "raw"] = $value;
		}
		
	}
	
	function unsetVar($name) {
		unset($this->data[$name]);
		unset($this->data[$name."raw"]);
	}
	
	
	function escape($value) {
		if (is_array($value)) {
			foreach ($value as &$singular) {
				$singular = $this->escape($singular);
			}
		}
		if (is_string($value)) {
			$value = htmlspecialchars($value);
		}
		return $value;
	}


	function display($viewfilename) {
		$this->load($viewfilename);
	}
	
	function fetch($viewfilename) {
		return $this->load($viewfilename, true);
	}
	
	/* Used internally inside templates */
	private function includeFile($viewfilename) {
		$this->load($viewfilename);
	}
	
	protected function load($viewfilename, $fetch = false) {
		$view = $this;
		
		// May be called without the .php extension
		if (!preg_match("/\.tpl$/", $viewfilename)) {
			$viewfilename.= ".tpl";
		}
		$this->viewfilename = $viewfilename;
		
		foreach ($this->data as $name => $value) {
			$$name = $value;
		}
				
		$str = file_get_contents($this->templatedir. $viewfilename);
		
		$str = $this->parseTemplate($str);

		if ($fetch) {
			ob_start();
			echo $str;
			ob_end_clean();
			return $str;
		} else {
			eval('?>' . $str . '<?php');
		}
	}
	
	
	function setTemplatesDirectory($dir) {
		$this->templatedir= $dir;
	}
	function getTemplatesDirectory() {
		return $this->templatedir;
	}
	
    function parseTemplate($content) {
		$content = preg_replace_callback (
			"/(?<!\\\)\{(.*)\}/sU", 
			function($matches) {
				$match = $matches[1];
				
				// {foreach from=$asd item=value key=key}
				if (preg_match("/^foreach\s+/i", $match)) {
					$match = preg_replace_callback("/
						foreach\s+
						from=(\\$[\w\d\"_'\[\]]+)\s+
						item=(\w+)
						(\s+key=(\w+))?
					/x", function($matches) {
						$arrayname = $matches[1];
						$itemname = '$' . $matches[2];
						$keyname = isset($matches[4]) ? '$' . $matches[4] . '=>' : null;
						
						$keyassign = isset($matches[4]) ? "\$view->assign(\"{$matches[4]}\",  \${$matches[4]}, null, true);" : "";
						
						return "<?php if (!empty({$arrayname})) { foreach({$arrayname} as {$keyname}{$itemname}) { {$keyassign} \$view->assign(\"{$matches[2]}\",  {$itemname}, null, true); ?>";
					}, $match );
					
					return $match;
				}
				
				// {capture name="test"} and {/capture}
				if (preg_match("/^capture\s+name=(?|\"([^\"]*)\"|'([^']*)')/", $match, $capturematches)) {
					$name = $capturematches[1];
					return "<?php \$capturename=\"{$name}\"; ob_start(); ?>";
				}
				if ($match == "/capture") {
					return "<?php \$view->assign(\$capturename, \$\$capturename = ob_get_contents(), null, true); ob_end_clean();  ?>";
				}
				
				// {include file="test"} or {include file='test'}
				if (preg_match("/^include\s+file=(?|\"([^\"]*)\"|'([^']*)')(.*)/s", $match, $includematches)) {
					// "But wait, there's more!"
					
					$variables = "";
					$unsets = "";
					
					if (isset($includematches[2])) {
						preg_match_all("/\s+(\w+)=((?|\"[^\"]*\"|'[^']*')|(\\$[\w\d\"_'\[\]]+))/", $includematches[2], $variablematches, PREG_SET_ORDER);
						foreach ($variablematches as $variablematch) {
							$name = $variablematch[1];
							$value = $variablematch[2];
							
							
							$value = preg_replace("/`\\$([^`]+)`/", "{\$\\1}", $value);
						
							$variables .= "\$view->assign(\"{$name}\",  ".$value."); ";
							$unsets .= "\$view->unsetVar(\"{$name}\"); ";
						}
					}
					
					$file = $includematches[1];
					$file = preg_replace("/`\\$([^`]+)`/", "{\$\\1}", $file);
					return "<?php {$variables} \$view->load(\"{$file}\"); {$unsets}  ?>";
				}
				
				// elseif(sdf) { ... }
				if (preg_match("/^elseif\s+(.+)/i", $match, $ifmatch)) {
					return "<?php } else if({$ifmatch[1]}) { ?>";
				}
				
				// {/foreach}, {else}, {/if}
				if (preg_match("#(/foreach|/if|else)#", $match)) {
					return str_replace(array("/foreach", "/if", "else"), array("<?php } } ?>", "<?php } ?>", "<?php } else { ?>"), $match);
				}
				
				// if(sdf) { ... }
				if (preg_match("/^if\s+(.+)/i", $match, $ifmatch)) {
					return "<?php if({$ifmatch[1]}) { ?>";
				}



				// {assign var="name" value=$val} or assign var='name' value=$val}
				if (preg_match("/^assign\s+var=(?|\"([^\"]*)\"|'([^']*)')\s+value=(.+)/i", $match, $assignmatch)) {
					
					return "<?php \$view->assign(\"{$assignmatch[1]}\", \${$assignmatch[1]} = {$assignmatch[2]});  ?>";
				}
				
				// {$testi}
				if ($match[0] == '$') {
					//return $this->data[substr($match,1)];
					return "<?php echo {$match}; ?>";
				}
				
				// {testi("sdf")}
				if (preg_match("/^([\w0-9_]+)\(/i", $match)) {
					return "<?php echo {$match} ; ?>";
				}
				
				return $matches[0];
			},
			$content
		);
		
		$content = str_replace("\{", "{", $content);
		
		return $content;
	}
}
