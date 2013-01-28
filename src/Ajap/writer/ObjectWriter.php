<?php

require_once dirname(__FILE__)."/WriterException.php";

/**
 * Some constants
 */
define("OBJECT_WRITER_CSS",0);
define("OBJECT_WRITER_JS",1);

/**
 * Object writer class
 */
class AjapObjectWriter {
	
	//------------------------------ TYPE RELATED ---------------------------//
	
	private static $TYPE_STRING = array("CSS","JS");
	private $forceInclude = array(false,false);
	
	//---------------------------- COMMON PROPERTIES ------------------------//
	
	/**
	 * Class being rendered
	 * @var ReflectionAnnotatedClass
	 */
	private $class;
	private $realClassName;
	private $className;
	
	private $module_path;
	
	private $isCompact;
	private $nl;
	private $isForCache;
	private $hasDynamic;
	
	private $_checkSuper = array();
	
	private $base_uri;
	private $base_dir;
	
	private $s_base_uri;
	private $s_base_dir;
	
	private $css_packer;
	private $js_packer;
	
	private $loadedFiles;
	
	private $implicits;
	
	//----------------------------- LOW LEVEL GENERATION --------------------//
	
	/**
	 * Generate comment
	 * @param mixed $object any reflection class
	 * @return string empty if in compact mode
	 */
	private function generateComment(&$object) {
		static $emptyLine = 
		"// -----------------------------------------------------------------------------";
		if ($this->isCompact || ($object!=$this->class && $this->class->getAnnotation("Volatile"))) return "";
		$doc_comment = $object->getDocComment();
		$doc_comment = str_replace("\r\n","\n",substr($doc_comment,2,-2));
		$lines = explode("\n",$doc_comment);
		$out = "";
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line=="" || $line=="*") continue;
			if ( preg_match( "/^\\*/", $line ) ) {
				$line = substr($line,1);
			}
			$out .= "//$line\n";
		}
		if ($out=="") return $emptyLine."\n";
		return "$emptyLine\n$out$emptyLine\n";
	}
	
	/**
	 * Generates title comment
	 * @return string
	 */
	public function generateTitleComment() {
		static $crunch = "#################################################";
		if ($this->isCompact) return;
		$len = strlen($this->className);
		$dec = $len%2;
		$nChars = floor((76-$len)/2);
		return "$this->nl$this->nl//".substr($crunch,0,$nChars)." $this->className ".substr($crunch,0,$nChars+$dec)."\n";
	}
	
	/**
	 * Encapsulate code in an anonymous function
	 * @param string $code javascript code
	 * @param boolean $isPHP true for php code, false for css code
	 */
	private function generateAnonymousFunction($code,$isPHP=false) {
		if ($isPHP) return "'function(){'.($code).'}'";
		return "function(){".$this->nl.$code.$this->nl."}";
	}
	
	/**
	 * Generates an alias
	 * @param string $alias
	 * @return string
	 */
	private function generateAlias($alias) {

		return "window.$alias=\$__ajap__object;";
	}
	
	/**
	 * Generates CSS code
	 * @param string $code whether php code or css code
	 * @param boolean $isPHP true for php code, false for css code
	 * @return string
	 */
	private function generateCSSCode($code,$isPHP=false) {
		
		if ($isPHP) {
			$code = "'Ajap.addStyle('.json_encode($code).');'";
		} else {
			if ($this->isCompact) $code = trim($code);
			if ($code!="") {
				if ($this->css_packer!==FALSE) $code = call_user_func($this->css_packer,$code);
				$code = "Ajap.addStyle(".json_encode($code).");";
			}
		}
		return $code;
	}

	/**
	 * Generates JS code
	 * @param string $code whether php code or js code
	 * @param boolean $isPHP true for php code, false for js code
	 * @return string
	 */
	private function generateJSCode($code,$isPHP=false) {

		if (!$isPHP) {
			if ($this->js_packer!==FALSE)
				$code = call_user_func($this->js_packer,$code);
			else if ($this->isCompact) $code = trim($code);
		}

		return $code;
	}

	/**
	 * Generates code
	 * @param int $type OBJECT_WRITER_CSS or OBJECT_WRITER_JS
	 * @param string $code whether php code or type code
	 * @param boolean $isPHP true for php code, false for type code
	 * @return string
	 */
	private function generateCode($type,$code,$isPHP=false) {

		switch ($type) {
			case OBJECT_WRITER_CSS: return $this->generateCSSCode($code,$isPHP);
			case OBJECT_WRITER_JS: return $this->generateJSCode($code,$isPHP);
			default:
				throw new AjapWriterException("Unknown type '$type'");
		}
	}

	/**
	 * Handle code generating methods
	 *
	 * @param int $type OBJECT_WRITER_CSS or OBJECT_WRITER_JS
	 * @param ReflectionAnnotatedMethod $method the method
	 * @return string
	 */
	private function generateCodeMethodBody($type,&$method) {

		$nbParams = count($method->getParameters());
		$params = $nbParams>0?array_fill(0,$nbParams,0):array();
		$dynamic = ($this->isForCache && $method->getAnnotation("Dynamic"));
		
		if (!$dynamic) $code = AjapReflector::doCall($this->class,$method,$params);
		else {
			$this->hasDynamic = true;
			$params = "array(".implode(",",$params).")";
			$code = "AjapReflector::dynamicDoCall('$this->realClassName','".$method->getName()."',$params)";
		}
		
		if ($method->getAnnotation("Template")) {
			$a = $method->getAnnotation("Template");
			if ($dynamic) {
				$code = "ajap_compileTemplate($code,'$this->nl',".($a->normalizeSpace?"true":"false").")";
			} else {
				$code = ajap_compileTemplate($code,$this->nl,$a->normalizeSpace);
			}
		}
		
		$code = $this->generateCode($type,$code,$dynamic);
		if ($dynamic) $code = "<?php\n\$code=$code; echo (\$code==null?'':\$code).'$this->nl'; ?>";
		else if ($code==null) $code='';
		return $code;
	}

	/**
	 * Handle uri
	 *
	 * @param int $type OBJECT_WRITER_CSS or OBJECT_WRITER_JS
	 * @param string $uri uri of php code
	 * @param boolean $isPHP true for php code, false for uri
	 * @return string
	 */
	private function generateURI($type,$uri,$isPHP=false) {

		if ($isPHP) {
			$force = $this->forceInclude[$type]?"true":"false";
			return '<?php
			$uri = '.$uri.';
			$blocking = "";
			if (substr($uri,0,1)=="+") {
				$blocking = "!";
				$uri = substr($uri,1);
			}
			$uri = ajap_resolveURI($uri,'."'$this->s_base_uri','$this->s_base_dir'".');
			if ($uri!==FALSE) {
				$local = preg_match( "/^!/", $line );
				if ('.$force.' || $local) {
					if ($local) $uri = substr($uri,1);
					$content = file_get_contents($uri);
					echo ('.$this->generateAnonymousFunction($this->generateCode($type,'$content',true),true).');
				} else echo json_encode($blocking."'.AjapObjectWriter::$TYPE_STRING[$type].'|".$uri);
			} else {
				echo "false";
			}'."\n?>";
		} else {
			$blocking = "";
			if (substr($uri,0,1)=="+") {
				$blocking = "!";
				$uri = substr($uri,1);
			}
			$uri = ajap_resolveURI($uri,$this->base_uri,$this->base_dir);
			if ($uri!==FALSE) {
				$local = preg_match( "/^!/", $line );
				if ($this->forceInclude[$type] || $local) {
					if ($local) {
						$uri = substr($uri,1);
						$this->loadedFiles[$uri]=true;
					}
					$content = file_get_contents($uri);
					$code = $this->generateCode($type,$content,false);
					if ($code=="") return "";
					return $this->generateAnonymousFunction($code);
				} else return json_encode($blocking.AjapObjectWriter::$TYPE_STRING[$type]."|".$uri);
			}
			return "";
		}
	}
	
	/**
	 * Handle external code
	 *
	 * @param int $type OBJECT_WRITER_CSS or OBJECT_WRITER_JS
	 * @param ReflectionAnnotatedMethod $method the method
	 * @return string
	 */
	private function generateExternalCode($type,&$method) {
		static $params = array();
		
		// Does it actually generate code?
		if ($method->getAnnotation(AjapObjectWriter::$TYPE_STRING[$type])) {
			$code = $this->generateCodeMethodBody($type,$method);
			if ($code=="") return "";
			return $this->generateAnonymousFunction($code);
		}
		
		// Else it provides an URI
		$dynamic = $this->isForCache && $method->getAnnotation("Dynamic");
		if ($dynamic) {
			$this->hasDynamic = true;
			$uri = 'ajap_resolveURI(AjapReflector::dynamicDoCall("'.$this->realClassName.'","'.$method->getName().'"),"'.$this->base_uri.'","'.$this->base_dir.'")';
		} else {
			$uri = ajap_resolveURI(AjapReflector::doCall($this->class,$method,$params),$this->base_uri,$this->base_dir);
		}
		
		return $this->generateURI($type,$uri,$dynamic);
	}
	
	private $_implicits = null;
	private $_implicits_post = null;
	private $_module = null;
	
	/**
	 * Transforms a URL template into a javascript expression
	 */
	private function transformURLTemplate($template) {
		$input = preg_split("/[{}]/",$template);
		$output = array();
		$isString = false;
		foreach($input as $element) {
			$isString = !$isString;
			if ($isString) {
				if ($element!="") {
					array_push($output,json_encode($element));
				}
				continue;
			}
			$element = trim($element);
			if (substr($element,0,2)=="@@") {
				$element = substr($element,2);
				array_push($output,"Ajap.objectToURLParams((".$element."))");
			} else if (substr($element,0,1)=="@") {
				$element = substr($element,1);
				array_push($output,"(".$element.")");
			} else {
				array_push($output,"escape((".$element."))");				
			}
		}
		return "([".implode(",",$output)."].join(''))";
	}
	
	/**
	 * Generate full remote jsonp method declaration
	 * @param ReflectionAnnotatedMethod $method the method
	 */
	private function generateRemoteJSONPMethod(&$method) {
		
		if ($this->class->getAnnotation("Virtual")
			|| $this->class->getAnnotation("Volatile")) return "";
			
		// url
		$url = $this->transformURLTemplate($method->getAnnotation("RemoteJSONP"));
			
		// Is it cached?
		$cached = $method->getAnnotation("Cached")?"true":"false";
		
		// Get params
		$params = $method->getParameters();
		$paramsObject = array();
		foreach($params as &$param) {
			$param = "$".$param->getName();
		}
		$params = implode(",",$params);
		$comma = $params==""?"":",";
		
		// Get code
		$code = "function(response){".$this->nl
			.$this->generateCodeMethodBody(OBJECT_WRITER_JS,$method).$this->nl
			."}";
			
		// Generate
		return $this->generateComment($method)
			.$method->getName().":function($params){".$this->nl
			."return Ajap.jsonp($url,this,$cached,$code);".$this->nl
			."}";
	}
		
	/**
	 * Generate full ajap method declaration
	 * @param ReflectionAnnotatedMethod $method the method
	 */
	private function generateAjapMethod(&$method) {
		
		if ($this->class->getAnnotation("Virtual")
			|| $this->class->getAnnotation("Volatile")) return "";
		
		if ($this->_implicits==null) {
			$tmp = array();
			foreach ($this->implicits as &$implicit)
				array_push($tmp,"$this->className.$".$implicit->getName());
			$this->_implicits = implode(",",$tmp);
			array_unshift($tmp,"form");
			$this->_implicits_post = "[".implode(",",$tmp)."]";

			$this->_module = str_replace( "_", ".", $this->class->getName() ); 
		}
		
		$name = $method->getName();
		
		// Is it cached?
		$cached = $method->getAnnotation("Cached")?",true":"";
		
		// Is it Post?
		if ($method->getAnnotation("Post")) {
			return $this->generateComment($method)
				."$name:function(form){".$this->nl
				."return Ajap.post('$this->_module','$name',$this->_implicits_post$cached);".$this->nl
				."}";
		}

		// Else get parameters
		$params = $method->getParameters();
		foreach($params as &$param) $param = '$'.$param->getName();
		$params = implode(",",$params);
		$comma = ($params!=""&&$this->_implicits!="")?",":"";
		$commaHead = ($params!="")?",":"";
		
		return $this->generateComment($method)
			."$name:function($params){".$this->nl
			."return Ajap.send('$this->_module','$name',[$params$comma$this->_implicits]$cached);".$this->nl
			."}";
	}
	
	/**
	 * Generate full method declaration
	 * @param ReflectionAnnotatedMethod $method the method
	 */
	private function generateMethod(&$method) {
				
		// Is it Virtual?
		if ($method->getAnnotation("Virtual")) return "";
		
		// Is it non-inherited and are we the declaring class?
		if ($method->getAnnotation("NotInherited")
			&& $method->getDeclaringClass()==$this->class)
			return "";
			
		// Is it RemoteJSONP
		if ($method->getAnnotation("RemoteJSONP"))
			return $this->generateRemoteJSONPMethod($method);
		
		// Is it Ajap?
		if (!$method->getAnnotation("JS")
			&& !$method->getAnnotation("Init")
			&& !$method->getAnnotation("Template"))
			return $this->generateAjapMethod($method);
			
		// Is it inherited?
		if (!$method->getAnnotation("NotInherited")
			&& isset($this->_checkSuper[$method->getDeclaringClass()->getName()]))
			return "";
		
		// Else get parameters
		$params = $method->getParameters();
		foreach($params as &$param) $param = '$'.$param->getName();
		
		// Do we put the name?
		$name = $method->getAnnotation("Init")?"":($method->getName().":");
		
		return $this->generateComment($method)
			.$name."function(".implode(",",$params)."){".$this->nl
			.$this->generateCodeMethodBody(OBJECT_WRITER_JS,$method).$this->nl
			."}";
		
	}
	
	/**
	 * Generate property
	 * @param ReflectionAnnotatedProperty $property
	 */
	private function generateProperty(&$property) {
		
		if ($property->getAnnotation("Inherited")
			&& $property->getDeclaringClass()!=$this->class) return "";
			
		if ($this->class->getAnnotation("Virtual")
			&& !$property->getAnnotation("Inherited")) return "";
		
		$name = $property->getName();
		$code = $this->generateComment($property)."\$$name:";
		
		// Is it dynamic?
		$dynamic = $this->isForCache && $property->getAnnotation("Dynamic");
		if ($dynamic) {
			$this->hasDynamic = true;
			$code .= "<?php echo json_encode(AjapReflector::dynamicDoGet('$this->realClassName','$name')); ?>";
		} else {
			$code .= json_encode(AjapReflector::doGet($this->class,$property));
		}
		
		return $code.$this->nl;
	}
	
	/**
	 * Encapsulate the whole block as needed
	 * @param string $code
	 */
	private function generateEncapsulate($code) {
		
		$module = str_replace( "_", ".", $this->class->getName() );
		
		$setLoaded = "!Ajap._classIsLoaded('$module')";
		
		$code = $this->generateTitleComment()
				.$this->generateComment($this->class).$this->nl
			.($code!=""?"if ($setLoaded){".$code."};":"$setLoaded;").$this->nl;
			
		if ($this->isForCache) {
			$require = "";
			if ($this->hasDynamic) {
				$require = "\n        require_once '".addslashes($this->class->getFileName())."';";
			}
			$code = "<?php if (!Ajap::isAlreadyLoaded('$module')) {".$require." ?>".$code."<?php } ?>";
		}
		
		return $code;
	}
	
	//------------------------------- DATA OF CLASS TO RENDER ---------------------//
	
	private $alias;
	private $external;
	private $properties;
	private $methods;
	private $init;
	
	//-------------------------------- HI LEVEL GENERATORS -----------------------//
	
	/**
	 * Generate all alias
	 * @return string empty is no alias
	 */
	private function generateAllAlias() {
	
		// If empty return nothing
		if (count($this->alias)==0) return "";
		
		$code = "";
		foreach ($this->alias as &$alias) {
			$code .= $this->generateAlias($alias).$this->nl;
		}
		return $code.$this->nl;
	}
	
	/**
	 * Generates javascript object
	 * @return string empty if the object has no property nor method
	 */
	private function generateObject() {
		
		// Check for superclass
		$tmp = $this->class->getParentClass();
		$super = FALSE;
		while (is_object($tmp))  {
			if (AjapReflector::isAjap($this->module_path,$tmp) && !$tmp->getAnnotation("Volatile")) {
				$name = $tmp->getName();
				$this->_checkSuper[$name]=true;
				if ($super===FALSE) $super = $name;
			}
			$tmp = $tmp->getParentClass();
		}
		
		if ($super===FALSE) $this->_checkSuper = array();
		else $super = str_replace("_",".",$super);
		
		// Else
		$fields = array();
		// List properties
		foreach ($this->properties as &$property) {
			$tmp = $this->generateProperty($property);
			if ($tmp!="") array_push($fields,$tmp);
		}
		// List methods
		foreach ($this->methods as &$method) {
			$tmp = $this->generateMethod($method);
			if ($tmp!="") array_push($fields,$tmp);
		}
		
		// Class code
		if (count($fields)==0 && $super==FALSE) return "";
		$code = "{".$this->nl
					.implode(",".$this->nl,$fields).$this->nl
					."}";
		
		
		$code = "Ajap.mergeObjects(".($super?"$super,":"")."window.$this->className?window.$this->className:{},$code)";
		
		// Construct objects in javascript if needed
		$decl = "";
		$tmp = explode(".",$this->className);
		$prec = "window.";
		for ($i=0; $i<count($tmp)-1; $i++) {
			$decl .= "if (typeof($prec$tmp[$i])=='undefined') $prec$tmp[$i]={};$this->nl";
			$prec .= "$tmp[$i].";
		}
		return "{$decl}var \$__ajap__object=$code;{$this->nl}window.$this->className=\$__ajap__object;$this->nl";
	}
	
	/**
	 * Generate all code related to object after external
	 * @return string empty if no init and no object, anonymous function code otherwise
	 */
	private function generateObjectRelated() {
		
		$code = $this->generateObject();
		if ($code!="" && !$this->class->getAnnotation("Volatile")) $code .= $this->generateAllAlias();
		return $code;
	}
	
	/**
	 * Generates the block that is makeInit'd
	 * @return string empty is no init method
	 */
	private function generateInit() {
		
		$object = $this->generateObjectRelated();
		if ($object!="") $object .= $this->nl;
		else if (count($this->init)==0 || $this->class->getAnnotation("Virtual")) return "";

		$initThis = ($object=="")?'{}':'$__ajap__object';

		$code = "";
		if (!$this->class->getAnnotation("Virtual")) {
			foreach ($this->init as &$init) {
				$tmp = $this->generateAnonymousFunction(
					$this->generateCodeMethodBody(OBJECT_WRITER_JS,$init)
				);
				if ($tmp=="") continue;
				$tmp = "($tmp).apply($initThis);$this->nl";
				$code .= $this->generateComment($init).$tmp;
			}
		}
		if ($code!="") $code = $this->generateAnonymousFunction($code);
		if ($code!="") $code = "Ajap.makeInitCode($code);$this->nl";

		return $this->generateAnonymousFunction($object.$code);
	}
	
	/**
	 * Generates all of the loading code
	 * @return string empty if there is nothing in the class (external or internal)
	 */
	private function generateLoad() {
		
		$array = array();
		
		foreach ($this->external as $type => &$list) {
			foreach ($list as &$object) {
				if (is_object($object))
					$code = $this->generateExternalCode($type,$object);
				else
					$code = $this->generateURI($type,$object);
				if ($code!="") array_push($array,$code);
			}
		}
		$code = $this->generateInit();
		if ($code!="") array_push($array,$code);
		if (count($array)==0) return "";
		array_unshift($array,"function(){Ajap.setCurrentStyleNodeId('$this->className');}");
		array_push($array,"function(){Ajap.setCurrentStyleNodeId(0);}");
		$code = implode(",".$this->nl,$array);
		if ($code=="") return "";
		return "Ajap.whenReady([".$this->nl.$this->nl
			.$code
			."]);";
			
	}
	
	/**
	 * Generates the javascript code for this object
	 * @return string empty is nothing
	 */
	public function generate() {
		
		$code = $this->generateLoad();
		return $this->generateEncapsulate($code);
	}

	//---------------------------------- Public interface -------------------------//
	
	private $resultingString = null;
	
	/**
	 * Constructor
	 * @param ReflectionAnnotatedClass $class the class to render
	 * @param array $options current options
	 */
	public function __construct(&$class, &$options) {

		$this->class = $class;
		$this->realClassName = $class->getName();
		$this->className = str_replace("_",".",$this->realClassName);
		
		foreach ($options as $k => &$option) {
			$this->$k =& $option;
		}
		
		$this->hasDynamic = FALSE;

		$this->loadedFiles = array();
		$this->implicits = array();
		
		$this->alias = array();
		$this->properties = array();
		$this->methods = array();
		$this->init = array();
		
		$this->external = array(array(),array());
	}
	
	/**
	 * Adds an alias
	 * @param string $toAdd alias
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addAlias(&$toAdd) {
		array_push($this->alias,$toAdd);
	}

	/**
	 * Adds cascading style sheet
	 * @param mixed $toAdd uri string or method
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addCascadingStyleSheet(&$toAdd) {
		$array =& $this->external[OBJECT_WRITER_CSS];
		$array[count($array)] =& $toAdd;
	}

	/**
	 * Adds javascript
	 * @param mixed $toAdd uri string or method
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addJavascript(&$toAdd) {
		$array =& $this->external[OBJECT_WRITER_JS];
		$array[count($array)] =& $toAdd;
	}
	
	/**
	 * Adds initialization javascript
	 * @param ReflectionAnnotatedMethod $toAdd method
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addInitializationJavascript(&$toAdd) {
		$this->init[count($this->init)] =& $toAdd;
	}
	
	/**
	 * Adds property
	 * @param ReflectionAnnotatedProperty $toAdd property
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addProperty(&$toAdd) {
		$this->properties[count($this->properties)] =& $toAdd;
		if ($toAdd->getAnnotation("Implicit"))
			$this->implicits[count($this->implicits)] =& $toAdd;
	}

	/**
	 * Adds method
	 * @param ReflectionAnnotatedMethod $toAdd method
	 * @throws AjapWriterException
	 * @return null
	 */
	public function addMethod(&$toAdd) {
		$this->methods[count($this->methods)] =& $toAdd;
	}
	
	private $_localFilesLoaded = null;

	/**
	 * Get local files loaded
	 * @throws AjapWriterException
	 * @return array of realpath
	 */
	public function &getLocalFilesLoaded() {
		if ($this->_localFilesLoaded==null) {
			$this->_localFilesLoaded = array_keys($this->loadedFiles);	
		}
		
		return $this->_localFilesLoaded;
	}

	/**
	 * Returns resulting string
	 * @throws AjapWriterException
	 * @return string
	 */
	public function &getResultingString() {
		if ($this->resultingString==null) $this->resultingString = $this->generate();
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