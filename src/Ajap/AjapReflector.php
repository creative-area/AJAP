<?php

require_once dirname( __FILE__ ) . "/utils.php";
require_once dirname( __FILE__ ) . "/AjapAnnotations.php";

class AjapReflector {
	
	public static $use = 0;
	public static $cacheUse = 0;
	
	/**
	 * Get singleton reflection class for class name
	 * @param string $className class name
	 * @return AjapClass
	 */
	public static function &getReflectionClass( $className ) {
		$tmp =& AjapClass::get( $className );
		return $tmp;
	}
	
	/**
	 * Get construction parameters given current engine mode
	 * 
	 * @param ReflectionAnnotatedClass $class
	 * @return array construction parameters (empty if no annotation)
	 */
	public static function getConstructionParameters( &$class ) {
		$params = array();
		$constructor = $class->getConstructor();
		if ( !is_object( $constructor ) ) {
			return null;
		}
		if ( $constructor->getAnnotation("Implicit") ) {
			$constructorParams =& $constructor->getParameters();
			foreach ( $constructorParams as &$constructorParam ) {
				array_push( $params, Ajap::getImplicit( $constructorParam->getName() ) );
			}
		}
		return $params;
	}
	
	/**
	 * Get singleton instance of the class
	 *
	 * @param ReflectionAnnotatedClass $class
	 * @return stdClass
	 */
	public static function &getInstance( &$class ) {
		static $instances = array();
		AjapReflector::$use++;
		$className = $class->getName();
		if (!isset($instances[$className])) {
			$args = AjapReflector::getConstructionParameters($class);
			if ($args==null) $instances[$className] = $class->newInstance();
			else $instances[$className] =& $class->newInstanceArgs($args);
		} else AjapReflector::$cacheUse++;
		return $instances[$className];
	}
	
	public static function resolveProperty( &$class, &$property ) {
		$class = AjapReflector::getReflectionClass( $class );
		$property = $class->getProperty( $property );
	}
	
	public static function resolveMethod( &$class, &$method ) {
		$class = AjapReflector::getReflectionClass( $class );
		$method = $class->getMethod( $method );
	}
	
	public static function doGet( &$class, &$property ) {
		$object = null;
		if ( !$property->isStatic() ) {
			$object =& AjapReflector::getInstance( $class );
		}
		return $property->getValue( $object );
	}
	
	public static function doSet( &$class, &$property, $value) {
		$object = null;
		if ( !$property->isStatic() ) {
			$object =& AjapReflector::getInstance( $class );
		}
		return $property->setValue( $object, $value );
	}

	public static function doCall( &$class, &$method, &$args ) {
		$object = null;
		if ( !$method->isStatic() ) {
			$object =& AjapReflector::getInstance( $class );
		}
		return $method->invokeArgs( $object, $args );
	}
	
	public static function dynamicDoGet( $class, $property ) {
		AjapReflector::resolveProperty( $class, $property );
		$object = null;
		if ( !$property->isStatic() ) {
			$object =& AjapReflector::getInstance( $class );
		}
		return $property->getValue($object);
	}
	
	public static function dynamicDoSet( $class, $property, $value ) {
		AjapReflector::resolveProperty( $class, $property );
		$object = null;
		if ( !$property->isStatic() ) {
			$object =& AjapReflector::getInstance( $class );
		}
		return $property->setValue( $object, $value );
	}

	public static function dynamicDoCall( $class, $method, $args=FALSE ) {
		if ( $args===FALSE ) {
			$args = array();
		}
		AjapReflector::resolveMethod( $class, $method );
		$object = null;
		if ( !$method->isStatic() ) {
			$object =& AjapReflector::getInstance( $class );
		}
		return $method->invokeArgs( $object, $args );
	}
	
	public static function isAjap( $path, &$class ) {
		return !!$class->getAnnotation( "Ajap" ) && ajap_inPath( $path, $class->getFileName() );
	}

	/**
	 * All right, so php sucks dry
	 * When you require_once a file with require_once inside,
	 * classes in get_declared_classes are inverted... because
	 * the first file to be parsed is the file you require
	 * so you have to invert everything and it's a bitch, of course
	 * 
	 * You gotta love to make acrobatics
	 *
	 * @param string $path path to search module files in
	 * @param string $module name of the module
	 * @return FALSE if not a module, an array containing the classes if the
	 * 		   the file is requested for the first time, an empty array otherwise
	 */
	public static function getClassesFrom( $path, $modules ) {
		
		static $_included = array();
		if ( !isset( $_included[ $path ] ) ) {
			$_included[ $path ] = array();
		}
		$included =& $_included[ $path ];
		
		if ( !is_array( $modules ) ) {
			$modules = array($modules);
		}
		
		$toInclude = array();
		foreach ( $modules as &$module ) {
			if ( !isset( $included[ $module ] ) ) {
				$tmp = ajap_moduleFile( $path, $module );
				if ( $tmp!==FALSE ) {
					$toInclude[] = $tmp;
				}
				$included[ $module ] = true;
			}
		}
		
		if ( count( $toInclude )==0 ) {
			return array();
		}
			
		$startIndex = count( get_declared_classes() );

		foreach ( $toInclude as &$file ) {
			require_once($file);
		}
		
		$classes = get_declared_classes();
		$endIndex = count( $classes );
		
		$array = array();
		for ( $i=$startIndex; $i<$endIndex; $i++ ) {
			$class =& AjapReflector::getReflectionClass( $classes[ $i ] );
			if ( $class->getAnnotation("Ajap") ) {
				$array[] =& $class;
			}
			unset( $class ); // To avoid problems with references
		}
		return $array;
	}
}

?>