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
		
		$dir = realpath( dirname(__FILE__) . "/../js" );

		$main = "$dir/main.js";
		$code = file_get_contents( $main );
		
		$engine = $this->options[ "engine" ];
		$engineSource = "$dir/engines/$engine.js";
		
		$extensionsCode = "";
		$extensionsFiles = array(
			"$dir/core.js",
			"$dir/loader.js",
			"$dir/net.js",
			"$dir/style.js",
		);
		
		foreach ( $extensionsFiles as $file ) {
    		$extensionsCode .= file_get_contents( $file )."\n";
		}
		
		$this->loadedFiles = array_merge( array( $main, $engineSource ), $extensionsFiles );
		$loadedFiles =& $this->loadedFiles;
		
		$code = str_replace( "@URI", json_encode( $this->options[ "uri" ] ), $code );
		$code = str_replace( "//@engine", file_get_contents( $engineSource ), $code );
		$code = str_replace( "//@extensions", $extensionsCode, $code );
		$code = preg_replace_callback( "#^//@include\\s*(\\S*)\\s*$#m", function( $match ) use( $dir, $engine, &$loadedFiles ) {
			$files = array(
				"$dir/include/$match[1]",
				"$dir/engines/$engine/$match[1]",
			);
			foreach( $files as $file ) {
				if ( file_exists( $file ) ) {
					$loadedFiles[] = $file;
					return file_get_contents( $file ) . "\n";
				}
			}
			throw new Exception( "Cannot include file $match[1]" );
		}, $code );
	    
	    if ( $this->options[ "js_packer" ] !== false ) {
			$code = call_user_func( $this->options[ "js_packer" ], $code );
	    }
		
		return $isForCache ? "<?php if (Ajap::isFirstLoad()) { ?>$code<?php } ?>" : $code;
	}

	/**
	 * Get class writer for given class
	 */	
	public function &classWriter( &$class ) {
		$tmp = new AjapClassWriter($class,$this->options);
		$this->classWriters[] =& $tmp;
		return $tmp; 
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
