<?php

/**
 * Interface of a module writer
 */
class AjapWriter {
	
	/**
	 * Initiating engine options reworked
	 * @var array
	 */
	private $options;
	
	/**
	 * Object writers
	 * @var array
	 */
	private $classWriters;
	
	/**
	 * Initiating engine
	 * @param Ajap $engine
	 */
	public function __construct( &$options ) {
		$this->options =& $options;
		$this->classWriters = array();
	}
	
	private $loadedFiles = null;
	
	/**
	 * Gets Ajap Core code
	 */
	private function generateAjapCore() {
		$isForCache = $this->options[ "cache" ];
		if ( !$isForCache && !Ajap::isFirstLoad() ) {
			return "";
		}
		
		$this->loadedFiles = array();
		
		$code = ajap_coreJS(  dirname(__FILE__) . "/js/main.js", $this->options, $this->loadedFiles ); 
	    
	    if ( $this->options[ "js_packer" ] !== false ) {
			$code = call_user_func( $this->options[ "js_packer" ], $code );
	    }
		
		return $isForCache ? "<?php if (Ajap::isFirstLoad()) { ?>$code<?php } ?>" : $code;
	}

	public function addClass( &$class ) {

		$className = $class->getName();
		
		// Already added?
		if ( isset( $this->classWriters[ $className ] ) ) {
			return;
		}
		
		$this->classWriters[ $className ] = null;
		
		// Is it Ajap?
		if ( !ajap_isAjap( $this->options[ "path" ], $class ) ) {
			return;
		}
		
		$this->classWriters[ $className ] = new AjapClassWriter( $class, $this->options );
		
		$classWriter =& $this->classWriters[ $className ];
		
		// Already done or not an Ajap class?
		if ( $classWriter === null ) {
			return;
		}

		// Handle dependencies
		if (( $dependsOn = $class->getAnnotation( "DependsOn" ) )) {
			$classes = AjapReflector::getClassesFrom( $this->options[ "path" ], $dependsOn );
			foreach ( $classes as &$c ) {
				$this->addClass( $c );
			}
		}

		// Super class
		$super = $class->getParentClass();
		if ( is_object( $super ) ) {
			$this->addClass( $super );
		}

		// Method to ignore by name
		$methods_to_ignore = array(
			$className => true,
			$className . "Render" => true,
			$className . "Execute" => true,
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
						$classWriter->addCascadingStyleSheet( $class->getMethod( $methodName ) );
						$methods_to_ignore[ $methodName ] = true;
					}
				} else {
					$classWriter->addCascadingStyleSheet( $a );
				}
			}
		}
		$cssFile = substr( $class->getFileName(), 0, -4 ).".css";
		if ( file_exists( $cssFile ) ) {
			$cssFile = "!$cssFile";
			$classWriter->addCascadingStyleSheet( $cssFile );
		}

		// Deal with JS files
		if (( $tmp = $class->getAnnotation( "JS" ) )) {
			foreach ( $tmp as $a ) {
				if ( preg_match( "/^method:/", $a ) ) {
				$methodName = substr( $a, 7 );
					if ( !isset( $methods_to_ignore[ $methodName ] ) ) {
						$classWriter->addJavascript( $class->getMethod( $methodName ) );
						$methods_to_ignore[ $methodName ] = true;
					}
				} else {
					$classWriter->addJavascript( $a );
				}
			}
		}

		// Aliases
		if (( $tmp = $class->getAnnotation( "Alias" ) )) {
			foreach ( $tmp as $a ) {
				$classWriter->addAlias( $a );
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
			$classWriter->addProperty($property);
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
			if ( $this->options[ "render_filter" ] !== false && !call_user_func( $this->options[ "render_filter" ], $method ) ) {
				continue;
			}

			// Check if CSS, if so, add to CSS block
			if ( $method->getAnnotation( "CSS" ) ) {
				$classWriter->addCascadingStyleSheet( $method );
				continue;
			}

			// Check if init related javascript, if so add to init_code
			if ( $method->getAnnotation( "Init" ) ) {
				$classWriter->addInitializationJavascript( $method );
				continue;
			}

			// If we're here, then we have a method
			$classWriter->addMethod($method);
		}
	}
	
	/**
	 * Get local files loaded
	 * @return array of realpath
	 */
	public function &getLocalFilesLoaded() {
		static $array = null;
		if ($array==null) {
			$array = $this->loadedFiles;
			foreach ($this->classWriters as &$classWriter) {
				$array = array_merge($array,$classWriter->getLocalFilesLoaded());
			}
		}
		
		return $array;
	}
	
	private $resultingString = null;

	/**
	 * Returns resulting string
	 * @return string
	 */
	public function &getResultingString() {
		if ($this->resultingString==null) {
			$this->resultingString = $this->generateAjapCore()."\n";
			if (count($this->classWriters)>0) {
				$code = "";
				foreach ($this->classWriters as &$classWriter) {
					$tmp = $classWriter->getResultingString();
					if ($tmp!="") $code .= $tmp."\n";
				}
				if ($code!="")
					$this->resultingString .=
						"Ajap.whenReady(function(){"."\n"
						.$code."\n"
						."});";
			}
		}
		return $this->resultingString;
	}

	/**
	 * Writes to a file
	 *
	 * @param handle $fh file handle (file must be opened in write mode)
	 */
	public function writeTo($fh) {
		fwrite($fh,$this->getResultingString());
	}
}
