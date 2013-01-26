<?php

require_once dirname(__FILE__)."/../ConfigurableClass.php"; // TODO: remove dependency

require_once dirname(__FILE__)."/AjapReflector.php";
require_once dirname(__FILE__)."/AjapCache.php";
require_once dirname(__FILE__)."/AjapException.php";
require_once dirname(__FILE__)."/AjapTemplate.php";

require_once dirname(__FILE__)."/writer/ModuleWriter.php";

class AjapEngine extends ConfigurableClass {
	
  /**
   * @var AjapEngine
   */
  private static $currentEngine = null;
	
  public static function require_module($module) {
  	if (AjapEngine::$currentEngine==null) throw new Exception("AjapEngine::require: No engine running");
  	$file = AjapFileHelper::getFileName(AjapEngine::$currentEngine->getOption("path"),$module);
  	if ($file==FALSE) throw new Exception("AjapEngine::require: Cannot find module '$module'");
  	require_once($file);
  }
	
  private static $alreadyLoaded = array();
  
  public static function isAlreadyLoaded($name) {
  	return isset(AjapEngine::$alreadyLoaded[$name]);
  }
  
  public static function isFirstLoad() {
  	return count(AjapEngine::$alreadyLoaded)==0;
  }
  
  private $_isRenderingModule = false;
  
  public static function isRenderingModule() {
  	return AjapEngine::$currentEngine!==null && AjapEngine::$currentEngine->_isRenderingModule;
  }
  
  private static $implicit = array();
  
  public static function getImplicit($name) {
  	return isset(AjapEngine::$implicit[$name])?AjapEngine::$implicit[$name]:null;
  }
  
	public static function transformTemplate($str,$separator="##") {
		$code = "";
		$first = true;
		$pieces = explode($separator,$str);
		$javascript = false;
		foreach ($pieces as $piece) {
			$trim = trim($piece);
			if (AjapStringHelper::startsWith($trim,"?")) $javascript=true;
			if ($javascript) $piece = $trim;
			if ($piece!='') {
				if ($javascript) {
					if (!AjapStringHelper::startsWith($piece,'?')) {
					if (!$first) $code .= "+";
					else $first = false;
						$code .= "(".$piece.")";
					}
					else {
						$piece = substr($piece,1);
						$subexpr = explode("#",$piece);
						if (count($subexpr)>1) {
							if (!$first) $code .= "+";
							else $first = false;
							$test = trim($subexpr[0]);
							if ($test=='') $test='true';
							$then = AjapEngine::transformTemplate($subexpr[1],"@@");
							$else = (count($subexpr)>2)?AjapEngine::transformTemplate($subexpr[2],"@@"):'""';
							$code .= "(($test)?($then):($else))";
						}
					}
				}
				else {
					if (!$first) $code .= "+";
					else $first = false;
					$code .= json_encode($piece);
				}
			}
			$javascript = !$javascript;
		}
		return $first?'""':$code;
	}
  
  public static function control($conditions) {
    $errorCodes = array();
    foreach ($conditions as $code => $test) if ($test) array_push($errorCodes,$code);
    if (count($errorCodes)>0) throw new AjapException($errorCodes);
  }

  public function resetOptions() {
    global $_SERVER;
    $this->forceOptions(array());
    $this->setOption("base_uri",AjapFileHelper::PHP_SELF_dirname());
    $this->setOption("base_dir",dirname($_SERVER["SCRIPT_FILENAME"]));
    $this->setOption("cache",FALSE);
    $this->setOption("compact",FALSE);
    $this->setOption("css_packer",FALSE);
    $this->setOption("encoding","utf-8");
    $this->setOption("engine","jQuery");
    $this->setOption("execute_filter",FALSE);
    $this->setOption("js_packer",FALSE);
    $this->setOption("production_cache",FALSE);
    $this->setOption("render_filter",FALSE);
    $this->setOption("session",array());
    $this->setOption("uri",$_SERVER['PHP_SELF']);
    $this->setOption("path",dirname($_SERVER["SCRIPT_FILENAME"]));
  }

  public function __construct($options=NULL) {
    parent::__construct($options);
  }
  
  private function getModuleFileName($module) {
  	return AjapFileHelper::getFileName($this->getOption("path"),$module);

  }
  
  private function getModuleName($filename) {
  	return AjapFileHelper::getModuleName($this->getOption("path"),$module);

  }
  
  private function getCSSFileName($module) {
  	return AjapFileHelper::getFileName($this->getOption("path"),$module,"css");

  }
  
  private function getClassesFor($modules) {
  	return AjapReflector::getClassesFrom($this->getOption("path"),$modules);
  }
  
  /**
   * Enter description here...
   *
   * @param ReflectionAnnotationClass $class
   * @param AjapModuleWriter $writer
   */
  private function renderClass(&$class,&$writer,&$alreadyDone) {

      // Already done?
      if (isset($alreadyDone[$class->getName()])) return;
      $alreadyDone[$class->getName()]=true;
      
      // Is it Ajap?
      if (!AjapReflector::isAjap($this->getOption("path"),$class)) return;
      
      // Handle dependencies
      if (( $tmp = $class->getAnnotation("DependsOn") )) {
      	$dependsOn = array();
      	foreach ($tmp as $a) $dependsOn[] = $a;
      	$classes = $this->getClassesFor($dependsOn);
   		foreach ($classes as &$c) $this->renderClass($c,$writer,$alreadyDone);
      }
      
      // Super class
      $super = $class->getParentClass();
      if (is_object($super)) $this->renderClass($super,$writer,$alreadyDone);
      
      // Start in writer
      $writer->openClass($class);
      
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
	  if (( $tmp = $class->getAnnotation("CSS") )) {
      	foreach ($tmp as $a) {
      		if (AjapStringHelper::startsWith($a,"method:")) {
      			$methodName = substr($a,7);
      			if (!isset($methods_to_ignore[$methodName])) {
	      			$writer->addCascadingStyleSheet($class->getMethod(substr($a,7)));
	      			$methods_to_ignore[$methodName] = true;
      			}
      		}
      		else
      			$writer->addCascadingStyleSheet($a);
      	}
	  }
	  $cssFile = substr($class->getFileName(),0,-4).".css";
	  if (file_exists($cssFile)) {
		$cssFile = "!$cssFile";
		$writer->addCascadingStyleSheet($cssFile);
	  }
	  
      // Deal with JS files
      if (( $tmp = $class->getAnnotation("JS") )) {
      	foreach ($tmp as $a) {
      		if (AjapStringHelper::startsWith($a,"method:")) {
      			$methodName = substr($a,7);
      			if (!isset($methods_to_ignore[$methodName])) {
	      			$writer->addJavascript($class->getMethod(substr($a,7)));
	      			$methods_to_ignore[$methodName] = true;
      			}
      		}
      		else
      			$writer->addJavascript($a);
      	}
      }
      
	  // Aliases
	  if (( $tmp = $class->getAnnotation("Alias") )) {
		foreach ($tmp as $a) $writer->addAlias( $a );
	  }

      // Properties
      $properties = $class->getProperties();
	  foreach ($properties as &$property) {

        // Ignore non public properties
        if (!$property->isPublic()) continue;

        // Ignore properties tagged as local
        if ($property->getAnnotation("Local")) continue;

        // Add to writer
        $writer->addProperty($property);
      }

      // Methods
      $methods = $class->getMethods();
      foreach ($methods as &$method) {

        // Ignore non public methods
        if (!$method->isPublic()) continue;
        
        // Ignore constructor/destructor and JS & CSS methods already handled
        if (isset($methods_to_ignore[$method->getName()])) continue;

        // Ignore methods tagged as local
        if ($method->getAnnotation("Local")) continue;

        // Apply user defined filters
        if ($this->getOption("render_filter")!==FALSE
        && !call_user_func($this->getOption("render_filter"),$method)) continue;
        
        // Check if CSS, if so, add to CSS block
        if ($method->getAnnotation("CSS")) {
        	$writer->addCascadingStyleSheet($method);
        	continue;
        }
        
        // Check if init related javascript, if so add to init_code
        if ($method->getAnnotation("Init")) {
        	$writer->addInitializationJavascript($method);
        	continue;
        }
        
        // If we're here, then we have a method
        $writer->addMethod($method);
      }
      
      $writer->closeClass();

  }
  
  public function renderModule($module,$moduleData=array(),$alreadyLoaded=array()) {
  	
	// Set as current engine
  	AjapEngine::$currentEngine =& $this;
  	$this->_isRenderingModule = true;
  	
  	// Set global variable
    AjapEngine::$implicit = (is_array($moduleData)) ? $moduleData : array();
    AjapEngine::$alreadyLoaded = 
    	(is_array($alreadyLoaded) && count($alreadyLoaded)>0)
    	?array_fill_keys($alreadyLoaded,true)
    	:array();

  	// Start timer (you gotta love timers)
    $time = microtime(true);
    
    // Try it the fast way
    if ($this->getOption("cache")!==FALSE) {
      $cache = new AjapCache($this,$module);
      if ($cache->isUpToDate()) {
      	ob_start();
        $cache->includeContent();
		$t = microtime(true)-$time;
		echo ob_get_clean();
		echo "// Retrieved from cache in $t (".date("D j M Y, G:i:s e",$time).")\n";
		return;
      }
    }

    // Instantiate writer
    $writer = new AjapModuleWriter($this);

    // Original request
    $reqModule = $module;
    
    // Utility array
    $alreadyDoneClasses = array();
    
    // Render classes
    $modules = explode(",",$module);
   	$classes = $this->getClassesFor($modules);
   	foreach ($classes as &$class) {
   		$this->renderClass($class,$writer,$alreadyDoneClasses);
   	}
    
    // Timing
    $afterInspectionTime = microtime(true);
   	$inspectionDelay = $afterInspectionTime-$time;
   	
    // Content
    $body =& $writer->getResultingString();
    		 
	// Footer with timing report
    $generationDelay = microtime(true)-$afterInspectionTime;
   	$footer = "\n// Classes inspected in $inspectionDelay seconds\n"
    		."// Module generated in $generationDelay seconds\n"
    		."// FileHelper cache usage ".AjapFileHelper::$cacheUse."/".AjapFileHelper::$use."\n"
    		."// Reflector  cache usage ".AjapReflector::$cacheUse."/".AjapReflector::$use."\n";
    
    // Header
    $header = "// Module '$module' \n"
    		 ."// Stub for ".$this->getOption("engine")." generated by Ajap on ".$_SERVER['SERVER_NAME']." (".date("D j M Y, G:i:s e",$time).")\n\n";

    // Final output
    $content = "$header".$writer->getResultingString()."$footer";
    
    // Handle cache
    if ($this->getOption("cache")!==FALSE) {
      $cache->write($content,$writer->getLocalFilesLoaded());
      $cache->includeContent();
      $totalDelay = microtime(true)-$time;
      echo "// Total process time is $totalDelay seconds\n";
    } else {
      echo $content;
    }
    
    $this->_isRenderingModule = false;
  }

  // PEAR sucks, I have to ignore its internal errors
  // Get over it or use a REAL library
  private static $pear_errors_to_ignore = array(512,2048);
  
  public static function errorHandler($code,$msg,$filename,$linenum) {
    if (array_search($code,AjapEngine::$pear_errors_to_ignore)===FALSE)
      throw new Exception("$msg ($code) <in '$filename' at line $linenum>");
  }

  private function sendJsonResponse($resp,$callback) {
    echo ($callback?"$callback(":"").'{"r": '.json_encode($resp).'}'.($callback?")":"");
  }

  private function sendJsonException($msg,$callback) {
    echo ($callback?"$callback(":"").'{"e": ['.json_encode("INTERNAL_ERROR (".htmlentities($msg).")").']}'.($callback?")":"");
  }

  private function sendJsonAjapException($codes) {
    echo '{"e": '.json_encode($codes).'}';
  }

  public function execute($action,$data,$callback = FALSE) {

  	// Set current engine
  	AjapEngine::$currentEngine =& $this;
  	$this->_renderingModule = false;

    set_error_handler(array("AjapEngine","errorHandler"));
    
    try {
    	
      // Control if data is an array
      if (!is_array($data)) throw new Exception("Data is not an array");

      // Get elements of action
      $executeElements = explode(":",$action);

      if (count($executeElements)!=2) throw new Exception("Illformed execute command '$action'");

      list($module,$methodName) = $executeElements;
      
      $className = implode( "_" , explode( "." , $module ) );

      // Find file for the module
      $filename = $this->getModuleFileName($module);
      if ($filename=="") throw new Exception("Unable to find module '$module'");

      // Inspect file
      require_once($filename);

      $class =& AjapReflector::getReflectionClass($className);

      if (!$class->getAnnotation("Ajap")) throw new Exception('Class cannot be reached');
      
      // Get implicits
      $implicit = array();
      $order = array();
      $properties = $class->getProperties();
	  foreach ($properties as &$property) {
	
	        // Ignore non public properties
	        if (!$property->isPublic()) continue;

	        // Ignore properties tagged as local
	        if ($property->getAnnotation("Local")) continue;
	
	        // Ignore properties not tagged as Implicit
	        if (!$property->getAnnotation("Implicit")) continue;
	
	        // Add to implicit
	        $name = $property->getName();
	        $implicit[$name] = $property;
	        if ($property->isStatic()) array_unshift($order,$name);
	        else array_push($order,$name);
      }
      
      // Handle implicits
      if (count($implicit)>0) {
      
      		// Do we have enough room for implicits ?
      		if (count($data)<count($implicit)) throw new Exception("Not enough parameters");
      	
      		// Separate implicits from data
      		$implicit_values = array_slice($data,-count($implicit));
      		$data = array_slice($data,0,-count($implicit));
      		
      		// Set global array in case the constructor need the data
      		AjapEngine::$implicit = array();
      		$i=0;
      		foreach ($implicit as $name => $_)
      			AjapEngine::$implicit[$name] = $implicit_values[$i++];

      		// Set properties
      		foreach ($order as $name)
      			AjapReflector::doSet($class,$implicit[$name],AjapEngine::$implicit[$name]);
      } else {
      	AjapEngine::$implicit = array();
      }

      $method = $class->getMethod($methodName);

      // Filter method
      if (!$method->isPublic()
          || $method->getAnnotation("Local")
          || $method->getAnnotation("JS")
          || ($this->getOption("execute_filter")!==FALSE && !call_user_func($this->getOption("execute_filter"),$method))) throw new Exception('Method cannot be executed');
          
      // If non blocking, close session
      if ($method->getAnnotation("NonBlocking")) session_write_close();
      
      // Can be called using jsonp?
      if ( $callback !== FALSE && !$method->getAnnotation("CrossDomain") ) {
      	throw new Exception("Unauthorized");
      }

      // Call
      if ($method->getAnnotation("Post")) {
       if (count($data)==0) throw new Exception("Illformed parameters [< ".var_export($data,TRUE)." >]");
       $post = array();
       parse_str($data[0],$post);
       // id hack
	   if ( isset( $post[ "__ajap_input_name_id_hack" ] ) ) {
		$post[ "id" ] = $post[ "__ajap_input_name_id_hack" ];
		unset( $post[ "__ajap_input_name_id_hack" ] );	
       }
       $params = array($post);
       $tmp = AjapReflector::doCall($class,$method,$params);
       $this->sendJsonResponse($tmp,$callback);
      } else {
       if (!is_array($data)) throw new Exception("Illformed parameters [< ".var_export($data,TRUE)." >]");
       while( count( $data ) && end( $data ) === null ) array_pop( $data );
       $this->sendJsonResponse(AjapReflector::doCall($class,$method,$data),$callback);
      }
    }

    catch (AjapException $ae) {

      $this->sendJsonAjapException($ae->getErrorCodes(),$callback);
    }

    catch (Exception $e) {

      $this->sendJsonException($e->getMessage(),$callback);
    }

    restore_error_handler();
  }

  public function handleRequest($header=true) {

    global $_REQUEST;
    
    $module = isset($_REQUEST["getModule"]) ? $_REQUEST["getModule"] : "";
    $execute = isset($_REQUEST["execute"]) ? $_REQUEST["execute"] : "";
    $callback = isset($_REQUEST["callback"]) ? $_REQUEST["callback"] : FALSE;

    if ($module=="" && $execute=="") return false;

    if ($module!="") {

      if (isset($_REQUEST["__ajap__data"])) $data = json_decode($_REQUEST["__ajap__data"],true);
      else {
      	$data = $_REQUEST;
      	unset($data["getModule"]);
      }

      if ($header) {
      	header('Content-type: application/javascript; charset='.$this->getOption("encoding"));
      	ob_start("ob_gzhandler");
      }
      $alreadyLoaded = isset($_REQUEST["__ajap__already__loaded"])
      		? explode(",",$_REQUEST["__ajap__already__loaded"]) : array();
      $this->renderModule($module,$data,$alreadyLoaded);
      
    } else {

      if ($header) header('Content-Type: application/' . ( $callback === FALSE ? 'json' : 'javascript' ) .'; charset='.$this->getOption("encoding") );
      $data = isset($_REQUEST["__ajap__data"]) ? json_decode($_REQUEST["__ajap__data"],true) : array();
      $this->execute($execute,$data,$callback);
    }
    
    return true;
  }
}

?>