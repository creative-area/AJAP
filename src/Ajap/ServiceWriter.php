<?php

class AjapServiceWriter extends AjapJSWriter {
	
	private $class;

	private $className;
	private $methodsToIgnore;
	
	public function __construct( &$class ) {
		$this->class =& $class;
		$this->className = $class->getName();
		$this->methodsToIgnore = array(
			$this->className => true,
			$this->className . "Common" => true,
			$this->className . "Service" => true,
			$this->className . "Execute" => true,
			"__construct" => true,
			"__destruct" => true
		);
	}
	
	private function &getInstance() {
		static $instance;
		if ( !$instance ) {
			$instance = $this->class->newInstance();
			if ( !$this->class->getAnnotation( "Base" ) ) {
				foreach( array( "Common", "Service" ) as $type ) {
					if (( $method =& $this->getMethod( $this->className . $type ) )) {
						$method->invoke( $instance );
					}
				}
			}
		}
		return $instance;
	}
	
	private function &getMethod( $name, $thenIgnore = false ) {
		$method = null;
		if ( !isset( $this->methodsToIgnore[ $name ] ) ) {
			if ( $thenIgnore ) {
				$this->methodsToIgnore[ $name ] = true;
			}
			$method = $this->class->getMethod( $name );
		}
		return $method;
	}
	
	private function getPropertyValue( $name ) {
		return json_encode( $this->getInstance()->$name );
	}
	
	private function callMethod( &$method ) {
		$nbParams = $method->getNumberOfRequiredParameters();
		return
			$nbParams 
			? $method->invokeArgs( $this->getInstance(), array_fill( 0, $nbParams, null ) )
			: $method->invoke( $this->getInstance() );
	}
	
	private function importFile( $type, $path ) {
		return json_encode( "$type:$path" );
	}
	
	private function generateTemplateMethod( &$method ) {
		return ajap_compileTemplate( $this->callMethod( $method ), $method->getAnnotation( "Template")->normalizeSpace );
	}
	
	private function generateJSMethod( &$method ) {
		return $this->callMethod( $method );
	}
	
	private function generateAJAPMethod( &$method, &$params ) {
		return $this->call( "__ajap.exec", array( 
			json_encode( $this->className ),
			json_encode( $method->getName() ),
			$this->_array( $params )
		) ) . ";";
	}
	
	private function generatePostMethod( &$method, &$params ) {
		return $this->call( "__ajap.post", array( 
			json_encode( $this->className ),
			json_encode( $method->getName() ),
			$params[ 0 ]
		) ) . ";";
	}
	
	private function generateMethod( $type, $name ) {
		$method =& $this->getMethod( $name );
		$generate = "generate" . $type . "Method";
		$params = array_map( function( $param ) {
			return "\$" . $param->getName();
		}, $method->getParameters() );
		return $this->func( $this->$generate( $method, $params ), $params );
	}

	private $dynamic;
	private $includes;
	private $object;
	
	private function handleDynamic( $method, $args ) {
		$dynCount = count( $this->dynamic );
		$args = array_map( function( $value ) {
			if ( is_string( $value ) ) {
				$value = preg_replace( "/\\\$/", "\\\$", $value );
			}
			return json_encode( $value );
		}, $args );
		$this->dynamic[] = "\$writer->$method( " . implode( ", ", $args ) . " )";
		return "__ajap.dynamic[ " . ( $dynCount ) . " ]";
	}
	
	private function handle( &$reflector, $method ) {
		$args = array_slice( func_get_args(), 2 );
		return $reflector->getAnnotation( "Dynamic" )
			? $this->handleDynamic( $method, $args )
			: call_user_func_array( array( $this, $method ), $args );
	}
	
	private function renderDynamic() {
		if ( !count( $this->dynamic ) ) {
			return false;
		}
		$cname = json_encode( $this->className );
		return
			"require_once " . json_encode( $this->class->getFileName() ) . ";\n"
			. "\$writer =& new AjapServiceWriter( AjapClass::get( $cname ) );\n"
			. "\$dynamic[ $cname ] = array(\n\t" . implode( ",\n\t", $this->dynamic ) . "\n);\n";
	}
	
	public function render() {
		
		$time = microtime( true );
		
		$this->dynamic = array();
		
		$this->includes = array();
		
		foreach( array( "CSS", "JS" ) as $type ) {
			if (( $paths = $this->class->getAnnotation( $type ) )) {
				$lType = strtolower( $type );
				foreach( $paths as $path ) {
					$value = "";
					if ( preg_match( "/^method:/", $path ) ) {
						$method = substr( $path, 7 );
						if (( $method=& $this->getMethod( $method ) )) {
							$value = $this->handle( $method, "importFile", $type, $path );
						} else {
							throw new AjapException( "Unknown or unreachable method $path" );
						}
					} else {
						$value = $this->importFile( $type, $path );
					}
					$this->includes[] = $this->call( "__ajap.$lType", array( $value ) );
				}
			}
		}
		
		$this->includes = $this->statements( $this->includes );
		
		$this->object = array();
		
		foreach( $this->class->getProperties() as $property ) {
			if ( $property->isStatic() || !$property->isPublic()
				|| $property->getAnnotation( "Local" ) ) {
				continue;
			}
			$name = $property->getName();
			$this->object[ "\$$name" ] = $this->handle( $property, "getPropertyValue", $name );
		}

		foreach( $this->class->getMethods() as $method ) {
			if ( $method->isStatic() || !$method->isPublic()
				|| $method->getAnnotation( "Local" )
				|| isset( $this->methodsToIgnore[( $name = $method->getName() )] ) ) {
				continue;
			}
			$type = "AJAP";
			foreach( array( "Template", "JS", "JSONP", "Post" ) as $annotation ) {
				if ( $method->getAnnotation( $annotation ) ) {
					$type = $annotation;
					break;
				}
			}
			$this->object[ $name ] = $this->handle( $method, "generateMethod", $type, $name );
		}
		
		$this->object = $this->obj( $this->object );
		
		$time = microtime( true ) - $time;
		
		$ts = explode( " ", microtime() );
		
		$ts = $ts[ 1 ] . explode( ".", $ts[ 0 ] )[ 1 ];
		
		return array(
			"ts" => $ts,
			"time" => $time,
			"dynamic" => $this->renderDynamic(),
			"static" => $this->func( $this->includes . "return " . $this->object . ";", array( "__ajap" ) ) 
		);
	}
}
