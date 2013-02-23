<?php

class AjapServiceWriter {
	
	private $class;
	private $options;

	private $methodsToIgnore;
	
	public function __construct( &$class, &$options ) {
		$this->class =& $class;
		$this->options =& $options;
		$className = $class->name;
		$this->methodsToIgnore = array(
			$className => true,
			$className . "Common" => true,
			$className . "Service" => true,
			$className . "Execute" => true,
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
					if (( $method =& $this->getMethod( $this->class->name . $type ) )) {
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

	private function &getArgs( &$method ) {
		$args = array();
		foreach( $method->getParameters() as $param ) {
			$args[] = '$' . $param->name;
		}
		return $args;
	}
	
	private function callMethod( &$method ) {
		$nbParams = $method->getNumberOfRequiredParameters();
		return
			$nbParams 
			? $method->invokeArgs( $this->getInstance(), array_fill( 0, $nbParams, null ) )
			: $method->invoke( $this->getInstance() );
	}

	public function property( $name ) {
		return array(
			"value" => $this->getInstance()->$name,
		);
	}

	public function Template( $name ) {
		$method =& $this->getMethod( $name );
		return array(
			"code" => ajap_compileTemplate(
				$this->callMethod( $method ),
				$method->getAnnotation( "Template" )->normalizeSpace
			),
			"args" => $this->getArgs( $method ),
		);
	}
	
	public function JS( $name ) {
		$method =& $this->getMethod( $name );
		return array(
			"code" => $this->callMethod( $method ),
			"args" => $this->getArgs( $method ),
		);
	}

	private $service;
	private $dynamic;

	private function handle( &$reflector, $method ) {
		$args = array_slice( func_get_args(), 2 );
		if ( $reflector && $reflector->getAnnotation( "Dynamic" ) ) {
			$this->dynamic[] = array(
				"method" => $method,
				"args" => $args,
			);
			return array(
				"dynamic" => count( $this->dynamic ) - 1,
			);
		}
		return call_user_func_array( array( $this, $method ), $args );
	}
	
	public function parse() {

		$this->service = array();

		$service =& $this->service;

		$class =& $this->class;

		$this->dynamic = array();

		if (( $dependsOn = $class->getAnnotation( "dependsOn" ) )) {
			$service[ "dependency" ]  = $dependsOn;
		}
		do {
			$parent = $class->getParentClass();
			if ( ajap_isAjap( $this->options[ "path" ], $parent ) ) {
				$service[ "parent" ] = $parent->name;
				break;
			}
		} while( $parent );

		$names = $class->getAnnotation( "Alias" );
		if ( !$class->getAnnotation( "Volatile" ) ) {
			$defaultName = array( $class->name );
			$names = $names ? array_merge( $defaultName, $names ) : $defaultName;
		}
		if ( $names ) {
			$service[ "name" ] = $names;
		}

		foreach( array( "CSS", "JS" ) as $type ) {
			if (( $paths = $this->class->getAnnotation( $type ) )) {
				$lType = strtolower( $type );
				foreach( $paths as $path ) {
					$method = null;
					$match = array();
					if ( preg_match( "/^method:(.+)$/", $path, $match ) ) {
						$method =& $this->getMethod( $match[ 1 ] );
						if ( !$method ) {
							throw new AjapException( "Unknown or unreachable method $match[1]" );
						}
					}
					$service[ $lType ][] = $this->handle( $method, "import$type", $path );
					if ( $method ) {
						$this->methodsToIgnore[ $method->name ] = true;
					}
				}
			}
		}
		
		foreach( $this->class->getProperties() as $property ) {
			if ( $property->isStatic() || !$property->isPublic()
				|| $property->getAnnotation( "Local" ) ) {
				continue;
			}
			$name = $property->name;
			$service[ "member" ][ "\$$name" ] = $this->handle( $property, "property", $name );
		}

		foreach( $this->class->getMethods() as $method ) {
			if ( $method->isStatic() || !$method->isPublic()
				|| $method->getAnnotation( "Local" )
				|| isset( $this->methodsToIgnore[( $name = $method->name )] ) ) {
				continue;
			}
			if ( $method->getAnnotation( "Init" ) ) {
				$service[ "init" ][] = $this->handle( $method, "JS", $name );
				continue;
			}
			
			$type = "Ajap";
			$simpleRemote = true;
			foreach( array(
				"Template" => false,
				"JS" => false,
				"JSONP" => false,
				"Post" => true
			) as $annotation => $r ) {
				if ( $method->getAnnotation( $annotation ) ) {
					$type = $annotation;
					$simpleRemote = $r;
					break;
				}
			}
			if ( $simpleRemote ) {
				$service[ "remote" ][ $name ] = array(
					"type" => $type,
					"args" => $this->getArgs( $method ),
					"cache" => $method->getAnnotation( "cache" ),
				);
			} else {
				$service[ "member" ][ $name ] = $this->handle( $method, $type, $name );
			}
		}
		
		return array(
			"ts" => ( new DateTime() )->format( "YmdHisu" ),
			"name" => $class->name,
			"data" => &$this->dynamic,
			"service" => &$service,
		);
	}
}
