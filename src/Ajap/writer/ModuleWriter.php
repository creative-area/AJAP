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
	 * Javascript engine to be used
	 */
	private $js_engine;
	
	/**
	 * Uri of Ajap
	 */
	private $ajap_uri;
	
	/**
	 * Current object writer
	 * @var AjapObjectWriter
	 */
	private $currentObjectWriter;
	
	/**
	 * Object writers
	 * @var array
	 */
	private $objectWriters;
	
	/**
	 * Warnings
	 * @var array
	 */
	private $warnings;
	
	/**
	 * Transforms a packer into an array if needed
	 * @param string $packer
	 */
	private function transformPacker(&$packer) {
		if ($packer===FALSE) return;
		$tmp = explode($packer,"::");
		if (count($tmp)==2) $packer = $tmp;
	}
	
	/**
	 * Initiating engine
	 * @param Ajap $engine
	 */
	public function __construct(&$engine) {
		$this->options = array();
		
		$this->options["isCompact"] = $engine->getOption("compact");
		$this->options["nl"] = $this->options["isCompact"]?"":"\n";
		$this->options["isForCache"] = $engine->getOption("cache")!==FALSE;
		$this->options["base_uri"] = $engine->getOption("base_uri");
		$this->options["base_dir"] = realpath($engine->getOption("base_dir"));
		if ($this->options["isForCache"]) {
			$this->options["s_base_uri"] = addslashes($this->options["base_uri"]);
			$this->options["s_base_dir"] = addslashes($this->options["base_dir"]);
		}
		$this->options["module_path"] = $engine->getOption("path");
		$this->options["js_packer"] = $engine->getOption("js_packer");
		$this->options["css_packer"] = $engine->getOption("css_packer");
		
		$this->js_engine = $engine->getOption("engine");
		$this->ajap_uri = $engine->getOption("uri");
		
		$this->transformPacker($this->options["js_packer"]);
		$this->transformPacker($this->options["css_packer"]);

		$this->engine =& $engine;
		$this->currentObjectWriter = null;
		$this->objectWriters = array();
		$this->warnings = array();
	}
	
	/**
	 * Generates warnings
	 * @return string the formatted warnings
	 */
	private function generateWarnings() {
		if (count($this->warnings)==0) return "";
		$code = "/* ##WARNINGS##\n\n";
		foreach ($this->warnings as $warning) {
			$code .= "$warning\n";
		}
		return $code."*/\n\n";
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

		$main = "$dir/Ajap.js";
		$code = file_get_contents( $main );
		
		$engine = $this->js_engine;
		$engineSource = "$dir/engines/$engine.js";
		
		$extensionsCode = "";
		$extensionsFiles = array(
			"$dir/Ajap.Loader.js",
			"$dir/Ajap.Net.js",
			"$dir/Ajap.Style.js",
		);
		
		foreach ( $extensionsFiles as $file ) {
    		$extensionsCode .= file_get_contents($file).$newline;
		}
		
		$this->loadedFiles = array_merge( array( $main, $engineSource ), $extensionsFiles );
		$loadedFiles =& $this->loadedFiles;
		
		$code = str_replace( "@URI", json_encode( $this->ajap_uri ), $code );
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
	 * Adds a warning
	 * @param string $warning message
	 */
	public function addWarning($warning) {
		array_push($this->warnings,$warning);
	}
	
	/**
	 * Throws an exception if current object writer is not set
	 */
	private function checkCurrent() {
		if ($this->currentObjectWriter==null)
			throw new AjapWriterException("Wrong State");
	}
	
	/**
	 * Starts a class
	 * @param ReflectionAnnotatedClass $class the class
	 * @throws AjapWriterException
	 */
	public function openClass(&$class) {
		if ($this->currentObjectWriter!=null)
			throw new AjapWriterException("Wrong State");
		$index = count($this->objectWriters);
		$this->objectWriters[$index] = new AjapObjectWriter($class,$this->options);
		$this->currentObjectWriter =& $this->objectWriters[$index];
	}
	
	/**
	 * Closes current class
	 * @throws AjapWriterException
	 */
	public function closeClass() {
		$this->checkCurrent();
		unset($this->currentObjectWriter);
		$this->currentObjectWriter = null;
	}

	/**
	 * Adds an alias
	 * @param string $toAdd alias
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addAlias(&$toAdd) {
		$this->checkCurrent();
		$this->currentObjectWriter->addAlias($toAdd);
	}

	/**
	 * Adds cascading style sheet
	 * @param mixed $toAdd uri string or method
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addCascadingStyleSheet(&$toAdd) {
		$this->checkCurrent();
		$this->currentObjectWriter->addCascadingStyleSheet($toAdd);
	}

	/**
	 * Adds javascript
	 * @param mixed $toAdd uri string or method
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addJavascript(&$toAdd) {
		$this->checkCurrent();
		$this->currentObjectWriter->addJavascript($toAdd);
	}
	
	/**
	 * Adds initialization javascript
	 * @param ReflectionAnnotatedMethod $toAdd method
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addInitializationJavascript(&$toAdd) {
		$this->checkCurrent();
		$this->currentObjectWriter->addInitializationJavascript($toAdd);
	}
	
	/**
	 * Adds property
	 * @param ReflectionAnnotatedProperty $toAdd property
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addProperty(&$toAdd) {
		$this->checkCurrent();
		$this->currentObjectWriter->addProperty($toAdd);
	}

	/**
	 * Adds method
	 * @param ReflectionAnnotatedMethod $toAdd method
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addMethod(&$toAdd) {
		$this->checkCurrent();
		$this->currentObjectWriter->addMethod($toAdd);
	}

	/**
	 * Get local files loaded
	 * @throws AjapWriterException
	 * @return array of realpath
	 */
	public function &getLocalFilesLoaded() {
		static $array = null;
		if ($array==null) {
			$array = $this->loadeFiles;
			foreach ($this->objectWriters as &$objectWriter) {
				$array = array_merge($array,$objectWriter->getLocalFilesLoaded());
			}
		}
		
		return $array;
	}
	
	private $resultingString = null;

	/**
	 * Returns resulting string
	 * @throws AjapWriterException
	 * @return string
	 */
	public function &getResultingString() {
		if ($this->resultingString==null) {
			$nl = $this->options["nl"];
			$this->resultingString = $this->generateWarnings().$nl
									.$this->generateAjapCore().$nl;
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

?>