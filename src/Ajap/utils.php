<?php

function ajap_resolveURI( $uri, $base_uri, $base_dir ) {

	if ( $uri === false ) {
		return false;
	}

	if ( preg_match( "/^!/", $uri ) ) {
		$uri = substr( $uri, 1 );
		if ( preg_match( "#^(?:/|[a-z]\\:\\\\)#i", $uri ) ) {
			return "!$uri";
		}
		return "!$base_dir" . DIRECTORY_SEPARATOR . $uri;
	}

	if ( preg_match( "#^https?//#", $uri ) ) {
		return $uri;
	}

	if ( preg_match( "#^/#", $uri ) ) {
		static $base_uri_root = array();
		if ( !isset( $base_uri_root[ $base_uri ] ) ) {
			$tmp = parse_url( $base_uri );
			$user_pass = "";
			if ( isset( $tmp[ "user" ] ) && $tmp[ "user" ] ) {
				if ( isset( $tmp[ "pass" ] ) && $tmp[ "pass" ] ) {
					$user_pass = ":$tmp[pass]";
				}
				$user_pass = "$tmp[user]$user_pass@";
			}
			$port = "";
			if ( isset( $tmp[ "port" ] ) && $tmp[ "port" ] ) {
				$port = ":$tmp[port]";
			}
			$base_uri_root[$base_uri] = "$tmp[scheme]://$user_pass$tmp[host]$port";
		}
		return $base_uri_root[$base_uri].$uri;
	}
	
	return $base_uri."/".$uri;
}

function &ajap_realPath( $path ) {
	static $cache = array();
	if ( !isset( $cache[ $path ] ) ) {
		$cache[ $path ] = array_map( function( $path ) {
			return realpath( $path );
		}, explode( PATH_SEPARATOR, $path ) );
	}
	return $cache[ $path ];
}

function ajap_inPath( $path, $filename ) {
	$filename = realpath( $filename );
	static $cache = array();
	if ( !isset( $cache[ $filename ] ) ) {
		$dirs =& ajap_realPath( $path );
		foreach( $dirs as $dir ) {
			if ( substr( $filename, 0, strlen( $dir ) ) === $dir ) {
				return true;
			}
		}
	}
	return false;
}

function ajap_moduleFile( $path, $module, $extension = "php" ) {
	static $cache = array();
	if ( !isset( $cache[ $path ] ) ) {
		$cache[ $path ] = array();
	}
	if ( !isset( $cache[ $path ][ $module ] ) ) {
		$dirs =& ajap_realPath( $path );
		$result = false;
		foreach( $dirs as $dir ) {
			$file = explode( ".", $module );
			while( count( $file ) > 1 && is_dir( $dir . DIRECTORY_SEPARATOR . $file[ 0 ] ) ) {
				$dir = $dir . DIRECTORY_SEPARATOR . array_shift( $file );
			}
			$file = implode( "_", $file ) . ".$extension";
			if ( file_exists( $dir . DIRECTORY_SEPARATOR . $file ) ) {
				$result = $dir . DIRECTORY_SEPARATOR . $file;
				break;
			}
		}
		$cache[ $path ][ $module ] = $result;
	}
	return $cache[ $path ][ $module ];
}
