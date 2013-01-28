<?php

require_once dirname(__FILE__)."/Ajap/AjapReflector.php";
require_once dirname(__FILE__)."/Ajap/AjapCache.php";
require_once dirname(__FILE__)."/Ajap/AjapException.php";

require_once dirname(__FILE__)."/Ajap/writer/ModuleWriter.php";

class Ajap {

	private static $currentEngine = null;
	
	public static function require_module( $module ) {
		if ( Ajap::$currentEngine==null ) {
			throw new Exception("Ajap::require: No engine running");
		}
		$file = ajap_moduleFile( Ajap::$currentEngine->getOption( "path" ), $module );
		if ( $file === false ) {
			throw new Exception( "Ajap::require: Cannot find module $module" );
		}
		require_once($file);
	}

	private static $alreadyLoaded = array();

	public static function isAlreadyLoaded($name) {
		return isset( Ajap::$alreadyLoaded[ $name ] );
	}

	public static function isFirstLoad() {
		return count( Ajap::$alreadyLoaded ) === 0;
	}

	private $_isRenderingModule = false;

	public static function isRenderingModule() {
  		return Ajap::$currentEngine !== null && Ajap::$currentEngine->_isRenderingModule;
	}

	private static $implicit = array();

	public static function getImplicit( $name ) {
		return isset( Ajap::$implicit[ $name ] ) ? Ajap::$implicit[ $name ] : null;
	}

	public static function control( $conditions ) {
		$errorCodes = array();
		foreach ( $conditions as $code => $test ) {
			if ( $test ) {
				array_push($errorCodes,$code);
			}
		}
		if ( count( $errorCodes ) > 0 ) {
			throw new AjapException( $errorCodes );
		}
	}

	private $options;

	public function getOption( $name ) {
		return isset( $this->options[ $name ] ) ? $this->options[ $name ] : null;
	}

	public function __construct( $options = null ) {
		global $_SERVER;
		$url = dirname( $_SERVER[ "PHP_SELF" ] );
		if ( $url === "/" ) {
			$url = "";
		}
		$url = ( isset( $_SERVER[ "HTTPS" ] ) ? ( $_SERVER[ "HTTPS" ] == "on" ? "https://" : "http://" ) : "" )
			. ( isset( $_SERVER[ "HTTP_HOST" ] ) ? $_SERVER[ "HTTP_HOST" ] : "" )
			. $url;
		$this->options = ( is_array( $options ) ? $options : array() ) + array(
			"base_uri" => $url,
			"base_dir" => dirname($_SERVER["SCRIPT_FILENAME"]),
			"cache" => false,
			"compact" => false,
			"css_packer" => false,
			"encoding" => "utf-8",
			"engine" => "jquery",
			"execute_filter" => false,
			"js_packer" => false,
			"production_cache" => false,
			"render_filter" => false,
			"session" => array(),
			"uri" => $_SERVER['PHP_SELF'],
			"path" => dirname($_SERVER["SCRIPT_FILENAME"])
		);
	}

	private function getClassesFor( $modules ) {
		return AjapReflector::getClassesFrom( $this->getOption( "path" ), $modules );
	}

	private function renderClass(&$class,&$writer,&$alreadyDone) {

		// Already done?
		if ( isset( $alreadyDone[ $class->getName() ] ) ) {
			return;
		}
		$alreadyDone[ $class->getName() ] = true;
	  
		// Is it Ajap?
		if ( !ajap_isAjap( $this->getOption("path"), $class ) ) {
			return;
		}

		// Handle dependencies
		if (( $dependsOn = $class->getAnnotation( "DependsOn" ) )) {
			$classes = $this->getClassesFor( $dependsOn );
			foreach ( $classes as &$c ) {
				$this->renderClass( $c, $writer, $alreadyDone );
			}
		}

		// Super class
		$super = $class->getParentClass();
		if ( is_object( $super ) ) {
			$this->renderClass( $super, $writer, $alreadyDone );
		}

		// Start in writer
		$writer->openClass( $class );

		// Method to ignore by name
		$methods_to_ignore = array(
			$class->getName() => true,
			$class->getName()."Render" => true,
			$class->getName()."Execute" => true,
			"__construct" => true,
			"__construct_render" => true,
			"__construct_execute" => true,
			"__destruct" => true
		);

		// Deal with CSS if needed
		if (( $tmp = $class->getAnnotation( "CSS" ) )) {
			foreach ( $tmp as $a ) {
				if ( preg_match( "/^method:/", $a ) ) {
					$methodName = substr( $a, 7 );
					if ( !isset( $methods_to_ignore[ $methodName ] ) ) {
						$writer->addCascadingStyleSheet( $class->getMethod( $methodName ) );
						$methods_to_ignore[ $methodName ] = true;
					}
				} else {
					$writer->addCascadingStyleSheet( $a );
				}
			}
		}
		$cssFile = substr( $class->getFileName(), 0, -4 ).".css";
		if ( file_exists( $cssFile ) ) {
			$cssFile = "!$cssFile";
			$writer->addCascadingStyleSheet( $cssFile );
		}

		// Deal with JS files
		if (( $tmp = $class->getAnnotation( "JS" ) )) {
			foreach ( $tmp as $a ) {
				if ( preg_match( "/^method:/", $a ) ) {
				$methodName = substr( $a, 7 );
					if ( !isset( $methods_to_ignore[ $methodName ] ) ) {
						$writer->addJavascript( $class->getMethod( $methodName ) );
						$methods_to_ignore[ $methodName ] = true;
					}
				} else {
					$writer->addJavascript( $a );
				}
			}
		}

		// Aliases
		if (( $tmp = $class->getAnnotation( "Alias" ) )) {
			foreach ( $tmp as $a ) {
				$writer->addAlias( $a );
			}
		}

		// Properties
		$properties = $class->getProperties();
		foreach ( $properties as &$property ) {

			// Ignore non public properties
			if ( !$property->isPublic() ) {
				continue;
			}

			// Ignore properties tagged as local
			if ( $property->getAnnotation( "Local" ) ) {
				continue;
			}

			// Add to writer
			$writer->addProperty($property);
		}

		// Methods
		$methods = $class->getMethods();
		foreach ( $methods as &$method ) {

			// Ignore non public methods
			if ( !$method->isPublic() ) {
				continue;
			}

			// Ignore constructor/destructor and JS & CSS methods already handled
			if ( isset( $methods_to_ignore[ $method->getName() ] ) ) {
				continue;
			}

			// Ignore methods tagged as local
			if ( $method->getAnnotation( "Local" ) ) {
				continue;
			}

			// Apply user defined filters
			if ( $this->getOption( "render_filter" ) !== false && !call_user_func( $this->getOption( "render_filter" ), $method ) ) {
				continue;
			}

			// Check if CSS, if so, add to CSS block
			if ( $method->getAnnotation( "CSS" ) ) {
				$writer->addCascadingStyleSheet( $method );
				continue;
			}

			// Check if init related javascript, if so add to init_code
			if ( $method->getAnnotation( "Init" ) ) {
				$writer->addInitializationJavascript( $method );
				continue;
			}

			// If we're here, then we have a method
			$writer->addMethod($method);
		}

		$writer->closeClass();

	}

	public function renderModule( $module, $moduleData=array(), $alreadyLoaded=array() ) {

		// Set as current engine
		Ajap::$currentEngine =& $this;
		$this->_isRenderingModule = true;

		// Set global variable
		Ajap::$implicit = ( is_array( $moduleData ) ) ? $moduleData : array();
		Ajap::$alreadyLoaded = 
			( is_array( $alreadyLoaded ) && count( $alreadyLoaded ) )
			? array_fill_keys( $alreadyLoaded, true )
			: array();

		// Start timer (you gotta love timers)
		$time = microtime( true );

		// Try it the fast way
		if ( $this->getOption( "cache" ) !== false ) {
			$cache = new AjapCache( $this, $module );
			if ( $cache->isUpToDate() ) {
				ob_start();
				$cache->includeContent();
				$t = microtime( true ) - $time;
				echo ob_get_clean();
				echo "// Retrieved from cache in $t (" . date( "D j M Y, G:i:s e", $time ) . ")\n";
				return;
			}
		}

		// Instantiate writer
		$writer = new AjapModuleWriter( $this );

		// Original request
		$reqModule = $module;

		// Utility array
		$alreadyDoneClasses = array();

		// Render classes
		$modules = explode( ",", $module );
		$classes = $this->getClassesFor( $modules );
		foreach ( $classes as &$class ) {
			$this->renderClass( $class, $writer, $alreadyDoneClasses );
		}

		// Timing
		$afterInspectionTime = microtime( true );
		$inspectionDelay = $afterInspectionTime - $time;

		// Content
		$body =& $writer->getResultingString();
	 
		// Footer with timing report
		$generationDelay = microtime( true ) - $afterInspectionTime;
		$footer = "\n\n// Classes inspected in $inspectionDelay seconds\n"
			."// Module generated in $generationDelay seconds\n";

		// Header
		$header = "// Module '$module' \n"
			. "// Stub for " . $this->getOption( "engine" )
			. " generated by Ajap on " . $_SERVER[ 'SERVER_NAME' ] . " (" . date( "D j M Y, G:i:s e", $time ) . ")\n\n";

		// Get content
		$content = $writer->getResultingString();

		// Compact if asked to
		if ( $this->getOption( "compact" ) ) {
			$content = ajap_compact( $content );
		}

		// Final output
		$content = "$header$content$footer";

		// Handle cache
		if ( $this->getOption( "cache" ) !== false ) {
			$cache->write( $content, $writer->getLocalFilesLoaded() );
			$cache->includeContent();
			$totalDelay = microtime( true ) - $time;
			echo "// Total process time is $totalDelay seconds\n";
		} else {
			echo $content;
		}

		$this->_isRenderingModule = false;
	}

	// PEAR sucks, I have to ignore its internal errors
	// Get over it or use a REAL library
	private static $pear_errors_to_ignore = array( 512, 2048 );

	public static function errorHandler( $code, $msg, $filename, $linenum ) {
		if ( array_search( $code, Ajap::$pear_errors_to_ignore ) === false ) {
			throw new Exception( "$msg ($code) <in '$filename' at line $linenum>" );
		}
	}

	private function sendJsonResponse( $resp, $callback ) {
		echo ( $callback ? "$callback(" : "" )
			. '{"r": ' . json_encode( $resp ) . '}'
			. ( $callback ? ")" : "" );
	}

	private function sendJsonException( $msg, $callback ) {
		echo ( $callback ? "$callback(" : "" )
			. '{"e": [' . json_encode( "INTERNAL_ERROR (" . htmlentities($msg) . ")" ) . ']}'
			. ( $callback ? ")" : "" );
	}

	private function sendJsonAjapException( $codes ) {
		echo '{"e": ' . json_encode( $codes ) . '}';
	}

	public function execute( $action, $data, $callback = false ) {

		// Set current engine
		Ajap::$currentEngine =& $this;
		$this->_renderingModule = false;

		set_error_handler( array( "Ajap", "errorHandler" ) );

		try {

			// Control if data is an array
			if ( !is_array( $data ) ) {
				throw new Exception( "Data is not an array" );
			}

			// Get elements of action
			$executeElements = explode( ":", $action );

			if ( count( $executeElements ) !== 2 ) {
				throw new Exception( "Illformed execute command '$action'" );
			}

			list( $module, $methodName ) = $executeElements;

			$className = str_replace( "." , "_", $module );

			// Find file for the module
			$filename = ajap_moduleFile( $this->getOption( "path" ), $module );
			if ( !$filename ) {
				throw new Exception( "Unable to find module '$module'" );
			}

			// Inspect file
			require_once( $filename );

			$class =& AjapClass::get( $className );

			if ( !$class->getAnnotation( "Ajap" ) ) {
				throw new Exception( "Class cannot be reached" );
			}

			// Get implicits
			$implicit = array();
			$order = array();
			$properties = $class->getProperties();
			foreach ( $properties as &$property ) {

				// Ignore non public properties
				if ( !$property->isPublic() ) {
					continue;
				}

				// Ignore properties tagged as local
				if ( $property->getAnnotation("Local") ) {
					continue;
				}

				// Ignore properties not tagged as Implicit
				if ( !$property->getAnnotation( "Implicit" ) ) {
					continue;
				}

				// Add to implicit
				$name = $property->getName();
				$implicit[ $name ] = $property;
				if ( $property->isStatic() ) {
					array_unshift( $order, $name );
				} else {
					array_push( $order, $name );
				}
			}
  
			// Handle implicits
			if ( count( $implicit ) > 0 ) {

				// Do we have enough room for implicits ?
				if ( count( $data ) < count( $implicit ) ) {
					throw new Exception( "Not enough parameters" );
				}

				// Separate implicits from data
				$implicit_values = array_slice( $data, -count( $implicit ) );
				$data = array_slice( $data, 0, -count( $implicit ) );

				// Set global array in case the constructor need the data
				Ajap::$implicit = array();
				$i = 0;
				foreach ( $implicit as $name => $_ ) {
					Ajap::$implicit[ $name ] = $implicit_values[ $i++ ];
				}

				// Set properties
				foreach ( $order as $name ) {
					AjapReflector::doSet( $class, $implicit[ $name ], Ajap::$implicit[ $name ] );
				}
			} else {
				Ajap::$implicit = array();
			}

			$method = $class->getMethod( $methodName );

			// Filter method
			if ( !$method->isPublic()
			|| $method->getAnnotation( "Local" )
			|| $method->getAnnotation( "JS" )
			|| ( $this->getOption( "execute_filter" ) !== false && !call_user_func( $this->getOption( "execute_filter" ), $method ) ) ) {
				throw new Exception( "Method cannot be executed" );
			}

			// If non blocking, close session
			if ( $method->getAnnotation( "NonBlocking" ) ) {
				session_write_close();
			}

			// Can be called using jsonp?
			if ( $callback !== false && !$method->getAnnotation("CrossDomain") ) {
				throw new Exception("Unauthorized");
			}

			// Call
			if ( $method->getAnnotation( "Post" ) ) {
				if ( !count( $data ) ) {
					throw new Exception( "Illformed parameters [< " . var_export( $data, true ) . " >]" );
				}
				$post = array();
				parse_str( $data[ 0 ], $post );
				// id hack
				if ( isset( $post[ "__ajap_input_name_id_hack" ] ) ) {
					$post[ "id" ] = $post[ "__ajap_input_name_id_hack" ];
					unset( $post[ "__ajap_input_name_id_hack" ] );	
				}
				$params = array( $post );
				$tmp = AjapReflector::doCall( $class, $method, $params );
				$this->sendJsonResponse( $tmp, $callback );
			} else {
				if ( !is_array( $data ) ) {
					throw new Exception( "Illformed parameters [< " . var_export( $data, TRUE ) . " >]" );
				}
				while( count( $data ) && end( $data ) === null ) {
					array_pop( $data );
				}
				$this->sendJsonResponse( AjapReflector::doCall( $class, $method, $data ), $callback );
			}
			
		} catch ( AjapException $ae ) {
			$this->sendJsonAjapException( $ae->getErrorCodes(), $callback );
		} catch ( Exception $e ) {
			$this->sendJsonException($e->getMessage(),$callback);
		}

		restore_error_handler();
	}

	public function handleRequest($header=true) {

		global $_REQUEST;
    
		$module = isset( $_REQUEST[ "getModule" ] ) ? $_REQUEST[ "getModule" ] : "";
		$execute = isset( $_REQUEST[ "execute" ] ) ? $_REQUEST[ "execute" ] : "";
		$callback = isset( $_REQUEST[ "callback" ] ) ? $_REQUEST[ "callback" ] : false;

		if ( $module == "" && $execute == "" ) {
			return false;
		}

		if ( $module != "" ) {

			if ( isset( $_REQUEST[ "__ajap__data" ] ) ) {
				$data = json_decode( $_REQUEST["__ajap__data"], true );
			} else {
				$data = $_REQUEST;
				unset( $data[ "getModule" ] );
			}

			if ( $header ) {
				header( "Content-type: application/javascript; charset=" . $this->getOption( "encoding" ) );
				ob_start( "ob_gzhandler" );
			}
			$alreadyLoaded =
				isset( $_REQUEST[ "__ajap__already__loaded" ] )
				? explode( ",", $_REQUEST[ "__ajap__already__loaded" ] )
				: array();
			$this->renderModule( $module, $data, $alreadyLoaded );

		} else {

			if ( $header ) {
				header( "Content-Type: application/" . ( $callback === false ? "json" : "javascript" )
					. "; charset=" . $this->getOption( "encoding" ) );
			}	
			$data = isset( $_REQUEST[ "__ajap__data" ] ) ? json_decode( $_REQUEST[ "__ajap__data" ], true ) : array();
			$this->execute( $execute, $data, $callback );
		}

		return true;
	}
}
