<?php

class AjapFileHelper {
	
	public function resolveURI($uri,$base_uri,$base_dir) {
		if ($uri===FALSE) return FALSE;
		static $base_uri_root = array();
		if ( preg_match( "/^!/", $uri ) ) {
			$uri = substr($uri,1);
			if ( preg_match( "#^(?:/|[a-z]\\:\\\\\\\\)#i", $uri ) ) {
				return "!$uri";
			}
			return "!$base_dir".DIRECTORY_SEPARATOR.$uri;
		}
		if ( preg_match( "#^https?//#", $uri ) ) {
			return $uri;
		}
		if ( preg_match( "#^/#", $uri ) ) {
			if (!isset($base_uri_root[$base_uri])) {
				$tmp = parse_url($base_uri);
				$user_pass = "";
				if (isset($tmp['user']) && $tmp['user']!='') {
					if (isset($tmp['pass']) && $tmp['pass']!='') {
						$user_pass = ":$tmp[pass]";
					}
					$user_pass = "$tmp[user]$user_pass@";
				}
				$port = "";
				if (isset($tmp['port']) && $tmp['port']!='') $port = ":$tmp[port]";
				$base_uri_root[$base_uri] = "$tmp[scheme]://$user_pass$tmp[host]$port";
			}
			return $base_uri_root[$base_uri].$uri;
		}
		return $base_uri."/".$uri;
	}

	private static function safe_dirname($path) {
		$dirname = dirname($path);
   		return ($dirname=='/') ? '' : $dirname;
	}
	
	public static function PHP_SELF_dirname() {
		global $_SERVER;
		return
		 ( isset( $_SERVER[ "HTTPS" ] ) ? ( $_SERVER[ "HTTPS" ] == "on" ? "https://" : "http://" ) : "" )
		 .( isset( $_SERVER[ "HTTP_HOST" ] ) ? $_SERVER[ "HTTP_HOST" ] : "" )
		 .AjapFileHelper::safe_dirname( $_SERVER[ "PHP_SELF" ] );
	}

	private static function &getDirectories(&$path) {
		static $directories = array();
		if (!isset($directories[$path])) {
			$directories[$path] = explode(PATH_SEPARATOR,$path);
			for ($i=0; $i<count($directories[$path]); $i++) {
				$directories[$path][$i] = realpath($directories[$path][$i]);
			}
		}
		return $directories[$path];
	}
	
	static private $fileNames = array();
	static private $moduleNames = array();
	
	static public $use = 0;
	static public $cacheUse = 0;
	
	public static function getFileName(&$path,&$module,$extension="php") {
		static $files = null;
		static $modules = null;
		if ($files==null) {
			$files =& AjapFileHelper::$fileNames;
			$modules =& AjapFileHelper::$moduleNames;
		}
		AjapFileHelper::$use++;
		if (!isset($files[$path])) {
			$files[$path] = array();
			$modules[$path] = array();
		}
		if (!isset($files[$path][$extension])) {
			$files[$path][$extension] = array();
		}
		else if (isset($files[$path][$extension][$module])) {
			AjapFileHelper::$cacheUse++;
			return $files[$path][$extension][$module];
		}
		$directories =& AjapFileHelper::getDirectories($path);
		$subMod = explode(".",$module);
		$subMods = array();
		$length = count($subMod);
		$subMods[0] = implode("_",$subMod);
		for ($i=1; $i<$length-1; $i++) {
			$subMods[$i] = implode(DIRECTORY_SEPARATOR,array_slice($subMod,0,$i))
							.DIRECTORY_SEPARATOR
						  	.implode("_",array_slice($subMod,$i));
		}
		$subMods[$length-1] = implode(DIRECTORY_SEPARATOR,$subMod);
		foreach ($directories as $dir) {
			foreach($subMods as &$mod) {
				$filename = $dir.DIRECTORY_SEPARATOR."$mod.$extension";
				if (file_exists($filename)) {
					$files[$path][$extension][$module] = $dir.DIRECTORY_SEPARATOR."$mod.$extension";
					$modules[$path][$dir.DIRECTORY_SEPARATOR."$mod.$extension"] = $module;
					return $filename;
				}
			}
		}
		$files[$path][$extension][$module] = FALSE;
		return FALSE;
	}

	public static function getModuleName(&$path,&$filename,$extension="php") {
		static $files = null;
		static $modules = null;
		if ($files==null) {
			$files =& AjapFileHelper::$fileNames;
			$modules =& AjapFileHelper::$moduleNames;
		}
		AjapFileHelper::$use++;
		if (!isset($files[$path])) {
			$files[$path] = array();
			$modules[$path] = array();
		}
		if (!isset($files[$path][$extension])) {
			$files[$path][$extension] = array();
		}
		else if (isset($modules[$path][$filename])) {
			AjapFileHelper::$cacheUse++;
			return $modules[$path][$filename];
		}
		$directories =& AjapFileHelper::getDirectories($path);
		$dir = dirname($filename);
		$basename = basename($filename,".$extension");
		foreach ($directories as &$directory) {
			if ( substr( $dir, 0, strlen( $directory ) ) === $directory ) {
				$tmp = substr($dir,strlen($directory)+1);
				if ($tmp!="") $tmp .= ".";
				$tmp = str_replace(DIRECTORY_SEPARATOR,".",$tmp).str_replace("_",".",$basename);
				$files[$path][$extension][$tmp] = $dir.DIRECTORY_SEPARATOR."$basename.$extension";
				$modules[$path][$dir.DIRECTORY_SEPARATOR."$basename.$extension"] = $tmp;
				return $tmp;
			}
		}
		$files[$path][$extension][$basename] = $dir.DIRECTORY_SEPARATOR."$basename.$extension";
		$modules[$path][$dir.DIRECTORY_SEPARATOR."$basename.$extension"] = FALSE;
		return FALSE;
	}	
}

?>