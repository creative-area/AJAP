<?php

class AjapCache {
	
	private $depname;
	private $filename;
	private $fast;
	
	/**
	 * Constructor
	 *
	 * @param AjapEngine $engine
	 * @param string $module
	 */
	public function __construct(&$engine,$module) {
		$dir = $engine->getOption("cache");
		if (!is_dir($dir)) throw new Exception("Directory '$dir' does not exist");
		$dir .= "/__ajap_cache";
		if (!is_dir($dir)) mkdir($dir);
		$dir = realpath($dir);
		$array = explode(",",$module);
		sort($array);
		$session = $engine->getOption("session");
		ksort($session);
		foreach ($session as $k => &$v) $v = "$k=$v";
		$session = implode("&",$session);
		if ($session!="") $session = "?$session";
		$key = md5(implode("-",$array)."-".$engine->getOption("engine").$session);
		$this->filename = "$dir/$key.js";
		$this->depname = "$this->filename.dep";
		$this->fast = $engine->getOption("production_cache");
	}
	
	public function getContent() {
		return file_get_contents($this->filename);
	}
	
	public function echoContent() {
		echo $this->getContent();
	}
	
	public function includeContent() {
		include($this->filename);
	}
	
	public function isUpToDate() {
		
		if ($this->fast) return file_exists($this->filename);

		if (!file_exists($this->depname)) return FALSE;
		if (!file_exists($this->filename)) return FALSE;
		$time = filemtime($this->depname);
		
		$files = unserialize(file_get_contents($this->depname));
		
		foreach ($files as $file)
			if (!file_exists($file) || filemtime($file)>$time) return FALSE;
		
		return TRUE;
	}
	
	public function write(&$data,$additional_files=array()) {
		
		if (!$this->fast) {
			$dep = serialize(array_merge(get_included_files(),$additional_files));
			$file = fopen($this->depname,"w");
			fwrite($file,$dep);
			fclose($file);
		}
		$file = fopen($this->filename,"w");
		fwrite($file,$data);
		fclose($file);
	}
}

?>