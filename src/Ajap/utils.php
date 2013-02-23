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

	if ( preg_match( "#^https?\\://#", $uri ) ) {
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

function ajap_isAjap( $path, &$class ) {
	return !!( $class && $class->getAnnotation( "Ajap" ) && ajap_inPath( $path, $class->getFileName() ) );
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

function ajap_compileTemplate( $str, $normalizeSpace ) {
	$code = "var __ajap__cumulator = \"\";";
	$parts = preg_split( "/<(js)?[?#@]|[?#@]>/", $str );
	$cumul = array();
	$flush = function() use ( &$code, &$cumul ) {
		if ( count( $cumul ) ) {
			$code .= "__ajap__cumulator+=" . implode( "+", $cumul ) . ";";
		}
		$cumul = array();
	};
	for( $i = 0, $count = count( $parts ); $i < $count; $i++ ) {
		$part = $parts[ $i ];
		if ( $i % 2 /* JavaScript */ ) {
			if( preg_match( "/^=/", $part ) ) {
				$part = trim( substr( $part, 1 ) );
				if ( $part ) {
					$cumul[] = "($part)";
				}
			} else {
				$part = trim( $part );
				if ( $part ) {
					$flush();
					$code .= $part;
				}
			}
		} else {
			if ( $normalizeSpace ) {
				$part = preg_replace( "/\\s+/", " ", $part );
			}
			if ( $part ) {
				$cumul[] = json_encode( $part );
			}
		}
	}
	$flush();
	return $code . "return __ajap__cumulator;";
}

function ajap_compact( $content ) {
	// Remove comments
	$content = preg_replace( "#//.*\\n|/\\*.*?\\*/#", "", $content );

	// Protect important whitespace (strings, keywords, etc )
	$strings = array();

	$content = preg_replace_callback( "/<\\?php([^\\?]|\\?[^>])*\\?>|'(?:\\\\\\\\|\\\\'|[^'])*'|\"(?:\\\\\\\\|\\\\\"|[^\"])*\"|(?:\\sin|delete|function|var|typeof|void|return|else|new)\\s+[^\\{\\(\\[]/", function( $match ) use ( &$strings ) {
		$match = $match[ 0 ];	
		$firstChar = substr( $match, 0, 1 );
		if ( $firstChar != "'" && $firstChar != '"' ) {
			$match = preg_replace( "/\\s+/", " ", $match );
		}
		$strings[] = $match;
		return "@<" . ( count( $strings ) - 1 ) . ">";
	}, $content );

	// Remove whitespaces
	$content = preg_replace( "/\\s+/", "", $content );

	// Put protected stuff back in 
	$content = preg_replace_callback( "/@<([0-9]+)>/", function( $match ) use ( &$strings ) {
		return $strings[ 1 * $match[ 1 ] ];
	}, $content );

	return $content;
}

function ajap_methodExpression( $expr ) {
	if ( $expr !== FALSE ) {
		$tmp = explode( $expr, "::" );
		if ( count( $tmp ) === 2 ) {
			$expr = $tmp;
		}
	}
	return $expr;
}

function ajap_coreJS( $filename, &$options, &$files ) {
	array_push( $files, $filename );
	
	$dir = dirname( $filename );
	
	$code = file_get_contents( $filename );
	
	$code = preg_replace_callback( '/"@([^\n@"]+)@"/', function( $match ) use( &$options ) {
		return json_encode( $options[ $match[ 1 ] ] );
	}, $code );
	
	$code = preg_replace_callback( "/\\binclude\\s*\\(([^)]*?)\\)\\s*;/", function( $match ) use( $dir, &$options, &$files ) {
		return ajap_coreJS( $dir . "/" . json_decode( $match[ 1 ] ), $options, $files );
	}, $code );
	
	return $code;
}

function ajap_outdated( $filename, $dependencies ) {
	$ftime = @filemtime( $filename );
	if ( !$ftime ) {
		return true;
	}
	foreach( $dependencies as $dependency ) {
		$dtime = @filemtime( $dependency );
		if ( $ftime < $dtime ) {
			return true;
		}
	}
	return false;
}

function &ajap_getWriter( $type, $dst ) {
	static $compiler;
	static $instances;
	if ( !$compiler ) {
		$compiler = new AjapCodeTemplate();
		$instances = array();
	}
	if ( isset( $instances[ $dst ] ) ) {
		$instances[ $dst ] = array();
	}
	$cache =& $instances[ $dst ];
	if ( !isset( $cache[ $type ] ) ) {
		$src = implode( DIRECTORY_SEPARATOR, array( __DIR__, "writers", "$type.at" ) );
		$dst = $dst . DIRECTORY_SEPARATOR . $type . "Writer.php";
		$className = "Ajap" . $type . "Writer";
		$compiler->compile( $className, $src, $dst );
		require_once( $dst );
		$cache[ $type ] = new $className();
	}
	return $cache[ $type ];
}

function ajap_beautifyJS( $code, $topLevelArray = false ) {
	$elements = array();
	$remove = function( $match ) use ( &$elements ) {
		$i = count( $elements );
		$elements[] = $match[ 0 ];
		return "@$i";
	};
	$code = preg_replace_callback( "@//.*@", $remove, $code );
	$code = preg_replace_callback( "@/\\*(?:\\*[^/]|[^\\*])\\*/@", $remove, $code );
	$code = preg_replace_callback( "/([\"'])(?:(?=(\\\\?))\\2.)*?\\1/", $remove, $code );
	$code = preg_replace( "/[\\(\\)\\[\\]\\{\\}:;,]/", " \\0 ", $code );
	$code = preg_replace( '/\s+/', ' ', $code );
	$code = preg_replace( "/ ([:;,.])/", "\\1", $code );
	$code = preg_replace( "/(\\{|;) /", "\\1\n", $code ); 
	$code = preg_replace( "/([^\\(\\[\\{,:]) ([\\[\\(])/", "\\1\\2", $code ); 
	$code = preg_replace( "/(\s)return([\\{\\[\\(])/", "\\1return \\2", $code );
	$code = preg_replace( "/(\\{|\\[|\\() (\\}|\\)|\\])/", "\\1\\2", $code );
	$code = preg_replace_callback( "/\\{\\}/", $remove, $code );
	$tab = "";
	$arrayLevel = 0;
	$code = preg_replace_callback( "/\\{|\\s\\}|\\n|, ([^\\[\\{\\]\\},;]+):|[\\[\\(] | [\\)\\]]|, /", function( $match ) use ( &$tab, &$arrayLevel, $topLevelArray ) {
		if ( isset( $match[ 1 ] ) ) {
			return ",\n$tab" . $match[ 1 ] . ":";
		}
		if ( $match[ 0 ] === "\n" ) {
			return "\n$tab";
		}
		if ( $match[ 0 ] === "[ " ) {
			if ( !( $arrayLevel++ ) ) {
				if ( $topLevelArray ) {
					$tab .= "\t";
					return "[\n$tab";
				}
			}
			return $match[ 0 ];
		}
		if ( $match[ 0 ] === "( " ) {
			if ( $arrayLevel ) {
				$arrayLevel++;
			}
			return $match[ 0 ];
		}
		if ( $match[ 0 ] === " )" ) {
			if ( $arrayLevel ) {
				$arrayLevel--;
			}
			return $match[ 0 ];
		}
		if ( $match[ 0 ] === " ]" ) {
			if ( !( --$arrayLevel ) ) {
				if ( $topLevelArray ) {
					$tab = substr( $tab, 1 );
					return "\n$tab]";
				}
			}
			return $match[ 0 ];
		}
		if ( $match[ 0 ] === "{" ) {
			$tab .= "\t";
			return "{";
		}
		if ( $match[ 0 ] === ", " ) {
			if ( $topLevelArray && $arrayLevel === 1 ) {
				return ",\n$tab";
			}
			return $match[ 0 ];
		}
		$tab = substr( $tab, 1 );
		return "\n$tab}";
	}, $code );
	$code = preg_replace_callback( "/@(\\s?)([0-9]+)(\\s?)/", function( $match ) use ( &$elements ) {
		$rep = $elements[ 1 * $match[ 2 ] ];
		if ( preg_match( "#^//#", $rep ) ) {
			return "\n$rep\n";
		}
		return "$match[1]$rep$match[3]";
	}, $code );
	return trim( $code );
}
