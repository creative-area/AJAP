<?php

class AjapTemplate {
	
	public static function transform($str,$separator,$normalizeSpace) {
		$cname = "__ajap__cumulator";
		$code = array("var $cname = [];");
		$split = preg_split("/<(js)?[?#@]|[?#@]>/",$str);
		$cumul = array();
		$isJavascript = true;
		foreach ($split as $part) {
			$isJavascript = !$isJavascript;

			if ($isJavascript) {
				
				$count = count($cumul);
				$isExpression = (substr($part,0,1)=='=');
				
				if ($isExpression) {
					
					array_push($cumul,"(".trim(substr($part,1)).")");
					
				} else {
					
					if ($count>0) {
						array_push($code,"$cname.push(".implode(",",$cumul).");");
					}
					array_push($code,trim($part));
					$cumul = array();
					
				}
				
			} else if ($part!="") {
				if ($normalizeSpace) $part = preg_replace("/\s+/"," ",$part);
				array_push($cumul,json_encode($part));
			}
		}
		
		$count = count($cumul);
		if ($count>0) {
			array_push($code,"$cname.push(".implode(",",$cumul).");");
		}
		array_push($code,"return $cname.join('');");
		
		return implode($separator,$code);
	}
	
	public static function inline($str,$normalizeSpace=true) {
		return "((function() {".AjapTemplate::transform($str,"\n",$normalizeSpace)."}).apply(this))";
	}
}

?>