<?php 

class AjapAnnotations {

	private static $definitions = array(
		"Class" => array(
			"Abstract" => "flag",
			"Ajap" => "flag",
			"Alias" => "strings",
			"CSS" => "strings",
			"DependsOn" => "strings",
			"JS" => "strings",
			"Volatile" => "flag",
		),
		"Method" => array(
			"Cached" => "flag",
			"CrossDomain" => "flag",
			"CSS" => "flag",
			"Dynamic" => "flag",
			"Init" => "flag",
			"JS" => "flag",
			"Local" => "flag",
			"NonBlocking" => "flag",
			"Post" => "flag",
			"RemoteJSONP" => "string",
			"Template" => array(
				"normalizeSpace" => true
			),
			"XDomain" => "=>CrossDomain",
		),
		"Property" => array(
			"Dynamic" => "flag",
			"Implicit" => "flag",
			"Local" => "flag",
		),
	);

	private $cache;
	private $type;
	private $defs;

	public function __construct( &$reflection ) {
		$type = substr( get_class( $reflection ), 4 );
		$this->cache = array();
		$this->type = $type;
		$this->defs =& AjapAnnotations::$definitions[ $type ];
		if (( $comment = $reflection->getDocComment() )) {
			$matches = array();
			preg_match_all( 
				'/@([A-Z][a-zA-Z0-9]*)((?:[^"\)\n]|(?:"(?:\\"|[^"\n])*"))*)/',
				$comment,
				$matches,
				PREG_SET_ORDER
			);
			foreach( $matches as $match ) {
				$this->add( $match[ 1 ], $match[ 2 ] );
			}
		}
	}

	private function decodeStringValue( $string ) {
		if ( $string && ( $string = trim( $string ) )) {
			$value = json_decode( $string );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$value = $string;
			}
		} else {
			$value = NULL;
		}
		return $value;
	}

	private function add_flag( $name, $value ) {
		if ( isset( $this->cache[ $name ] ) ) {
			throw new Exception( "$this->type annotation $name cannot be used twice" );
		}
		if ( $value !== NULL ) {
			throw new Exception( "$this->type annotation $name does not accept any value" );
		}
		$this->cache[ $name ] = true;
	}

	private function add_object( $name, $value, &$default ) {
		if ( isset( $this->cache[ $name ] ) ) {
			throw new Exception( "$this->type annotation $name cannot be used twice" );
		}
		if ( $value === NULL ) {
			$value = new stdClass();
		}
		if ( !is_object( $value ) ) {
			throw new Exception( "$this->type annotation $name only accepts an object" );
		}
		$this->cache[ $name ] =& $value;
		foreach( $default as $field => $defaultValue ) {
			if( !property_exists( $value, $field ) ) {
				$value->$field = $defaultValue;
			}
		}
	}

	private function add_string( $name, $value ) {
		if ( isset( $this->cache[ $name ] ) ) {
			throw new Exception( "$this->type annotation $name cannot be used twice" );
		}
		if ( !is_string( $value ) ) {
			throw new Exception( "$this->type annotation $name only accepts a string" );
		}
		$this->cache[ $name ] = $value;
	}

	private function add_strings( $name, $value ) {
		if ( is_string( $value ) ) {
			$value = array( $value );
		}
		if ( !is_array( $value ) ) {
			throw new Exception( "$this->type annotation $name only accepts a string or a list of strings" );
		}
		if ( !isset( $this->cache[ $name ] ) ) {
			$this->cache[ $name ] = array();
		}
		foreach( $value as $string ) {
			if ( !is_string( $string ) ) {
				throw new Exception( "$this->type annotation $name only accepts a string or a list of strings" );
			}
			$this->cache[ $name ][] = $string;
		}
	}

	private function add( $name, $value ) {
		if ( !isset( $this->defs[ $name ] ) ) {
			throw new Exception( "$this->type annotation $name unknown" );
		}
		$def =& $this->defs[ $name ];
		if( is_string( $def ) && preg_match( "/^=>/", $def ) ) {
			$def =& $this->defs[ substr( $def, 2 ) ];
		}
		$value = $this->decodeStringValue( $value );
		if ( is_array( $def ) ) {
			$this->add_object( $name, $value, $def );
		} else {
			$method = "add_$def";
			$this->$method( $name, $value );
		}
		return $this;
	}

	public function get( $name ) {
		if ( isset( $this->cache[ $name ] ) ) {
			return $this->cache[ $name ];
		}
		return false;
	}
}

class AjapClass extends ReflectionClass {

	public static function &get( $class ) {
		static $cache = array();
		if ( !isset( $cache[ $class ] ) ) {
			$cache[ $class ] = new AjapClass( $class );
		}
		return $cache[ $class ];
	}

	private $annotations;

	public function getAnnotation( $name ) {
		if ( !$this->annotations ) {
			$this->annotations = new AjapAnnotations( $this );	
		}
		return $this->annotations->get( $name );
	}

	private function AjapClass( &$class ) {
		parent::__construct( $class );
	}

	public function getParentClass() {
		static $class;
		static $done;
		if ( !$done ) {
			$pClass = parent::getParentClass();
			$class =
				$pClass
				? AjapClass::get( $pClass->getName() )
				: $pClass;
		}
		return $class;
	}

	public function getConstructor() {
		static $constructor;
		static $done;
		if ( !$done ) {
			$pConstructor = parent::getConstructor();
			$constructor =
				$pConstructor
				? AjapMethod::get( $this->getName(), $pConstructor->getName() )
				: $constructor;
		}
		return $constructor;
	}

	public function getMethod( $name ) {
		return AjapMethod::get( $this->getName(), $name );
	}

	public function getProperty( $name ) {
		return AjapProperty::get( $this->getName(), $name );
	}

	public function getMethods( $filter = -1 ) {
		static $cache = array();
		if( !isset( $cache[ $filter ] ) ) {
			$methods = array();
			foreach( parent::getMethods( $filter ) as $method ) {
				$methods[] = AjapMethod::get( $this->getName(), $method->getName() );
			}
			$cache[ $filter ] =& $methods;
		}
		return $cache[ $filter ];
	}

	public function getProperties( $filter = -1 ) {
		static $cache = array();
		if( !isset( $cache[ $filter ] ) ) {
			$properties = array();
			foreach( parent::getProperties( $filter ) as $property ) {
				$properties[] =& AjapProperty::get( $this->getName(), $property->getName() );
			}
			$cache[ $filter ] =& $properties;
		}
		return $cache[ $filter ];
	}

	public function getInterfaces() {
		static $interfaces;
		if( !$interfaces ) {
			$interfaces = array();
			foreach( parent::getInterfaceNames() as $interface ) {
				$interfaces[] =& AjapClass::get( $interface );
			}
		}
		return $interfaces;
	}
}

class AjapMethod extends ReflectionMethod {

	public static function &get( $class, $name ) {
		static $cache = array();
		$key = "$class/$name";
		if ( !isset( $cache[ $key ] ) ) {
			$cache[ $key ] = new AjapMethod( $class, $name );
		}
		return $cache[ $key ];
	}

	private $annotations;

	public function getAnnotation( $name ) {
		if ( !$this->annotations ) {
			$this->annotations = new AjapAnnotations( $this );	
		}
		return $this->annotations->get( $name );
	}

	private $parentClassName;

	private function AjapMethod( $class, $name ) {
		parent::__construct( $class, $name );
		$this->parentClassName = $class;
	}

	public function getDeclaringClass() {
		return AjapClass::get( $this->parentClassName );
	}
}

class AjapProperty extends ReflectionProperty {

	public static function &get( $class, $name ) {
		static $cache = array();
		$key = "$class/$name";
		if ( !isset( $cache[ $key ] ) ) {
			$cache[ $key ] = new AjapProperty( $class, $name );
		}
		return $cache[ $key ];
	}

	private $annotations;

	public function getAnnotation( $name ) {
		if ( !$this->annotations ) {
			$this->annotations = new AjapAnnotations( $this );	
		}
		return $this->annotations->get( $name );
	}

	private $parentClassName;

	protected function AjapProperty( &$class, $name ) {
		parent::__construct( $class, $name );
		$this->parentClassName = $class;
	}

	public function getDeclaringClass() {
		return AjapClass::get( $this->parentClassName );
	}
}
