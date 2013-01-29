<?php

require_once dirname(__FILE__)."/ObjectWriter.php";

/**
 * Interface of a module writer
 */
class AjapModuleWriter {
	
	/**
	 * Initiating engine options reworked
	 * @var array
	 */
	private $options;
	
	/**
	 * Object writers
	 * @var array
	 */
	private $objectWriters;
	
	/**
	 * Transforms a packer into an array if needed
	 * @param string $packer
	 */
	private function transformPacker( $packer ) {
		if ( $packer !== FALSE ) {
			$tmp = explode( $packer, "::" );
			if ( count( $tmp ) === 2 ) {
				$packer = $tmp;
			}
		}
		return $packer;
	}
	
	/**
	 * Initiating engine
	 * @param Ajap $engine
	 */
	public function __construct( &$options ) {
		$this->options = array(
			"uri" => $options[ "uri" ],
			"isCompact" => $options[ "compact" ],
			"nl" => $options[ "compact" ] ? "" : "\n",
			"isForCache" => $options[ "cache" ] !== FALSE,
			"base_uri" => $options[ "base_uri" ],
			"base_dir" => realpath( $options[ "base_dir" ] ),
			"module_path" => $options[ "path" ],
			"js_packer" => $this->transformPacker( $options[ "js_packer" ] ),
			"css_packer" => $this->transformPacker( $options[ "css_packer" ] ),
			"js_engine" => $options[ "engine" ],
			"ajap_uri" => $options[ "uri" ],
		);

		if ( $this->options[ "isForCache" ] ) {
			$this->options[ "s_base_uri" ] = addslashes( $this->options[ "base_uri" ] );
			$this->options[ "s_base_dir" ] = addslashes( $this->options[ "base_dir" ] );
		}

		$this->objectWriters = array();
	}
	
	private $loadedFiles = null;
	
	/**
	 * Gets Ajap Core code
	 */
	private function generateAjapCore() {
		$isForCache = $this->options[ "isForCache" ];
		if ( !$isForCache && !Ajap::isFirstLoad() ) {
			return "";
		}
		
		$newline = $this->options[ "nl" ];
		
		$dir = realpath( dirname(__FILE__) . "/../js" );

		$main = "$dir/main.js";
		$code = file_get_contents( $main );
		
		$engine = $this->options[ "js_engine" ];
		$engineSource = "$dir/engines/$engine.js";
		
		$extensionsCode = "";
		$extensionsFiles = array(
			"$dir/core.js",
			"$dir/loader.js",
			"$dir/net.js",
			"$dir/style.js",
		);
		
		foreach ( $extensionsFiles as $file ) {
    		$extensionsCode .= file_get_contents( $file ).$newline;
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
		$tmp = new AjapObjectWriter($class,$this->options);
		$this->objectWriters[] =& $tmp;
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
			foreach ($this->objectWriters as &$objectWriter) {
				$array = array_merge($array,$objectWriter->getLocalFilesLoaded());
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
			$nl = $this->options["nl"];
			$this->resultingString = $this->generateAjapCore().$nl;
			if (count($this->objectWriters)>0) {
				$code = "";
				foreach ($this->objectWriters as &$objectWriter) {
					$tmp = $objectWriter->getResultingString();
					if ($tmp!="") $code .= $tmp.$nl;
				}
				if ($code!="")
					$this->resultingString .=
						"Ajap.whenReady(function(){".$nl
						.$code.$nl
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
