<?php

class AjapCodeTemplate {

	private function &split( $expr, &$src, $parts, $keepFirst = false ) {
		$result = array();
		$matches = array();
		preg_match_all( $expr, $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		if ( count( $matches ) ) {
			$start = 0;
			foreach( $matches as $i => $match ) {
				$end = $match[ 0 ][ 1 ];
				$prev = substr( $src, $start, $end - $start );
				$start = $end + strlen( $match[ 0 ][ 0 ] );
				if ( $i || $keepFirst ) {
					$result[] = $prev;
				}
				$item = array(
					"*" => $match[ 0 ][ 0 ],
				);
				foreach( $parts as $i => $name ) {
					$item[ $name ] = isset( $match[ $i + 1 ] ) ? trim( $match[ $i + 1 ][ 0 ] ) : "";
				}
				$result[] = $item;
			}
			$tmp = substr( $src, $start );
			if ( $tmp ) {
				$result[] = $tmp;
			}
		} else if ( $keepFirst && $src ) {
			$result[] = &$src;
		}
		return $result;
	}

	private function &parseConds( &$body ) {
		$output = array();
		for( $i = 0, $length = count( $body ); $i < $length; $i++ ) {
			$part =& $body[ $i ];
			if ( is_array( $part ) && $part[ "if" ] ) {
				$dup = $part[ "loop" ] || $part[ "method" ] ? array_merge( $part, array(
					"if" => ""
				) ) : null;
				$i++;
				if ( $i < $length && is_string( $body[ $i ] ) ) {
					$body[ $i ] = preg_replace( '/^[ \t]+/', '', $body[ $i ] );
				}
				$then = array();
				$else = array();
				if ( $dup ) {
					$then[] = $dup;
				}
				$target =& $then;
				$after = "";
				$level = 0;
				for ( ; $i < $length; $i++ ) {
					$item =& $body[ $i ];
					if ( is_array( $item ) ) {
						if ( !$level ) {
							if ( $target === $then && $item[ "orElse" ] ) {
								$target =& $else;
								continue;
							} else if ( $item[ "endif" ] ) {
								break;
							}
						} else if ( $item[ "if" ] ) {
							$level++;
						} else if ( $item[ "endif" ] ) {
							$level--;
						}
					}
					$target[] =& $item;
				}
				if ( !count( $then ) ) {
					$then = array( "" );
				}
				if ( !count( $else ) ) {
					$else = array( "" );
				}
				$part[ "then" ] = $this->parseConds( $then );
				$part[ "else" ] = $this->parseConds( $else );
				$output[] = $part;
				if ( $after ) {
					$output[] = $after;
				}
			} else {
				$output[] = $body[ $i ];
			}
		}
		return $output;
	}

	private function &parseEscaped( &$body ) {
		$output = array();
		$count = 0;
		foreach( $body as $part ) {
			if ( is_array( $part ) && $part[ "escaped" ] ) {
				$part = substr( $part[ "*" ], 1 );
			}
			if ( $count && is_string( $part ) && is_string( $output[ $count - 1 ] ) ) {
				$output[ $count - 1 ] .= $part;
			} else {
				$output[ $count++ ] = $part;
			}
		}
		return $output;
	}

	private function &parse( &$src ) {
		$mode = array();
		preg_match( '/<<\s*(js|php)\s*>>/i', $src, $mode );
		$mode = isset( $mode[ 1 ] ) ? strtolower( $mode[ 1 ] ) : "js";
		$src = str_replace( "\r\n", "\n", $src );
		$parts = $this->split( '#//\:\s*([^\n]+)\s*\n#', $src, array( "name" ) );
		$output = array();
		for( $i = 0, $length = count( $parts ); $i < $length; $i += 2 ) {
			if ( !( $i % 2 ) ) {
				$body = rtrim( $parts[ $i + 1 ] ) . "\n";
				$output[ $parts[ $i ][ "name" ] ] = $this->parseConds( $this->parseEscaped( $this->split(
					'#(["`]?)((?:\?!?)?)(\\\\?)(@|[A-Z](?:[A-Z_]|[.][A-Z])+)\1(?:\{([^\}\n]*)\})?(?:/([a-z][a-z_]+))?(;;|,,|:,)?|(\?:)|(\?\?)#',
					$body,
					array(
						"encode",
						"if",
						"escaped",
						"name",
						"compare",
						"method",
						"loop",
						"orElse",
						"endif",
					),
					true
				) ) );
			}
		}
		$return = array(
			"mode" => $mode,
			"tree" => &$output,
		);
		return $return;
	}

	private $modes = array(
		"js" => array(
			"encode" => 'json_encode( X )',
			"fieldSep" => ": ",
		),
		"php" => array(
			"encode" => 'var_export( X, true )',
			"fieldSep" => " => ",
		),
	);

	private $mode;

	private function writeEncode( $item ) {
		var_export( $item, true ) . "\n";
		return str_replace( "X", $item, $this->mode[ "encode" ] );
	}

	private function writeLoop( &$item ) {
		return '$this->_loop( ' . implode( ", ", array( 
			$this->writeItem( $item ),
			var_export( $item[ "method" ] ? "__" . $item[ "method" ] : $item[ "method" ], true ),
			var_export( !!$item[ "encode" ], true ),
			var_export( $item[ "loop" ] === ":,", true ),
			var_export( substr( $item[ "loop" ], 1 ), true ),
		) ) . ' )';
	}
	
	private function writeItem( &$item ) {
		static $predefined = array(
			'THIS' => '$data',
			'@' => '$index',
		);
		$name = $item[ "name" ];
		if ( isset( $predefined[ $name ] ) ) {
			return $predefined[ $name ];
		}
		$name = str_replace( ".", "' ][ '", strtolower( $name ) );
		return "\$data[ '$name' ]";
	}
	
	private function writeBody( &$body ) {
		$output = array();
		foreach( $body as &$item ) {
			if ( is_string( $item ) ) {
				$output[] = var_export( $item, true );
			} else if ( $item[ "if" ] ) {
				$tmp = $this->writeItem( $item );
				$not = substr( $item[ "if" ], 1 );
				$notOrEqual = $not ? $not : "=";
				$output[] = '( ' . $not . 'isset( ' . $tmp . ' ) ' .
					(
						$item[ "compare" ]
						? ( "&& ( $tmp $notOrEqual= " . var_export( $item[ "compare" ], true ) . " )" )
						: ""
					) .
					' ? ( ' .
					$this->writeBody( $item[ "then" ] ) .
					' ) : ( ' .
					$this->writeBody( $item[ "else" ] ) .
					' ) )';
			} else if ( $item[ "loop" ] ) {
				$output[] = $this->writeLoop( $item );
			} else if ( $item[ "method" ] ) {
				$output[] = '$this->__' . $item[ "method" ] . '( ' . $this->writeItem( $item ) . ', $index )';
			} else if ( $item[ "encode" ] ) {
				$output[] = $this->writeEncode( $this->writeItem( $item ) );
			} else if ( $item ) {
				$output[] = $this->writeItem( $item );
			}
		}
		return implode( " .\n\t", $output );
	}
	
	private function write( $className, &$parsed ) {
		$this->mode =& $this->modes[ $parsed[ "mode" ] ];

		$output = '
private function _loop( &$array, $method, $encode, $field, $join ) {
	if ( $method || $encode || $field ) {
		$output = array();
		array_walk( $array, function( &$value, $key, &$self ) use ( &$output, $method, $encode, $field ) {
			$result = $value;
			if ( $method ) {
				$result = $self->$method( $result, $key );
			}
			if ( $encode ) {
				$result = ' . $this->writeEncode( '$result' ) . ';
			}
			if ( $field ) {
				$result = ' . $this->writeEncode( '$key' ) . ' . "' . $this->mode[ 'fieldSep' ] . '" . $result;
			}
			$output[ $key ] = trim( $result );
		}, $this );
	} else {
		$output =& $array;
	}
	if ( $join === ";" || $field ) {
		$join .= "\n";
		if ( $join === ";" ) {
			$output[] = "";
		}
	} else {
		$join .= " ";
	}
	return implode( $join , $output );
}
';
		foreach( $parsed[ "tree" ] as $name => &$body ) {
			$match = array();
			if (( $public = preg_match( '/^@(.+)/', $name, $match ) )) {
				$name = $match[ 1 ];
			}
			$output .= '
private function __' . $name . '( &$data, $index = null ) {
	return ' . $this->writeBody( $body ) . ';
}
';
			if ( $public ) {
				$output .= '
public function ' . $name . '( $data ) {
	return $this->__' . $name . '( $data );
}
';
			}
		}

		return "<?php

class $className {
$output
}
";
	}

	public function compile( $className, $src, $dst ) {
		if ( ajap_outdated( $dst, array( __FILE__, $src ) ) ) {
			$src = file_get_contents( $src );
			$src = $this->parse( $src );
			$src = $this->write( $className, $src );
			file_put_contents( $dst, $src );
		}
	}
}
