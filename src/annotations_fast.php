<?php

/**
 * Faster version of annotations
 * (Julian Aubourg)
 **/
	
require_once(dirname(__FILE__).'/annotations/annotation_parser.php');
	
class Annotation {
	public $value;
	
	public final function __construct($data, $target) {
		foreach($data as $key => $value) $this->$key = $value;
	}
}

class Target extends Annotation {}

class Addendum {
	private static $rawMode;
	private static $ignore;
	
	public static function getDocComment($reflection) {
		if(self::checkRawDocCommentParsingNeeded()) {
			$docComment = new DocComment();
			return $docComment->get($reflection);
		} else {
			return $reflection->getDocComment();
		}
	}
	
	/** Raw mode test */
	private static function checkRawDocCommentParsingNeeded() {
		if(self::$rawMode === null) {
			$reflection = new ReflectionClass('Addendum');
			$method = $reflection->getMethod('checkRawDocCommentParsingNeeded');
			self::setRawMode($method->getDocComment() === false);
		}
		return self::$rawMode;
	}
	
	public static function setRawMode($enabled = true) {
		if($enabled) {
			require_once(dirname(__FILE__).'/annotations/doc_comment.php');
		}
		self::$rawMode = $enabled;
	}
	
	public static function resetIgnoredAnnotations() {
		self::$ignore = array();
	}
	
	public static function ignores($class) {
		return isset(self::$ignore[$class]);
	}
	
	public static function ignore() {
		foreach(func_get_args() as $class) {
			self::$ignore[$class] = true;
		}
	}
}

class AnnotationsBuilder {
	public static function &build(&$targetReflection) {
		$data = self::parse($targetReflection);
		$result = array();
		foreach($data as $class => $parameters) {
			if(!Addendum::ignores($class)) {
				foreach($parameters as $params) {
					$annotationReflection = new ReflectionClass($class);
					$result[$class][] = $annotationReflection->newInstance($params, $targetReflection);
				}
			}
		}
		return $result;
	}
	
	private static function parse($reflection) {
		$parser = new AnnotationsMatcher;
		$parser->matches(Addendum::getDocComment($reflection), $data);
		return $data;
	}

	public static function clearCache() {
		ReflectionAnnotatedCache::clearCache();
	}
}

class ReflectionAnnotatedCache {
	
	public static $cache = array();
	
	public static function &getFor($reflection) {
		$key = $reflection->getIDString();
		if (!isset(self::$cache[$key])) {
			self::$cache[$key] = new ReflectionAnnotatedCache();
		}
		return self::$cache[$key];
	}
	
	public static function clearCache() {
		self::$cache = array();
	}
	
	public $data = array();
	public $annotations = FALSE;
}

class ReflectionAnnotatedClass extends ReflectionClass {
	private $cache;
	
	public function getIDString() {
		return $this->getName();
	}
	
	public function __construct($class) {
		parent::__construct($class);
		$this->cache =& ReflectionAnnotatedCache::getFor($this);
	}
	
	private function buildAnnotations() {
		if ($this->cache->annotations===FALSE)
			$this->cache->annotations =& AnnotationsBuilder::build($this);
	}
	
	public function hasAnnotation($annotation) {
		$this->buildAnnotations();
		return isset($this->cache->annotations[$annotation]);
	}
	
	public function getAnnotation($annotation) {
		$this->buildAnnotations();
		return isset($this->cache->annotations[$annotation]) ? end($this->cache->annotations[$annotation]) : false;
	}
	
	public function getAnnotations() {
		$this->buildAnnotations();
		$result = array();
		foreach($this->cache->annotations as $instances) {
			$result[] = end($instances);
		}
		return $result;
	}
	
	public function getAllAnnotations($restriction = false) {
		$this->buildAnnotations();
		$result = array();
		foreach($this->cache->annotations as $class => $instances) {
			if(!$restriction || $restriction == $class) {
				$result = array_merge($result, $instances);
			}
		}
		return $result;
	}
	
	public function getConstructor() {
		$construct = parent::getConstructor();
		if ($construct===null) return null;
		return $this->getMethod($construct->getName());
	}
	
	public function getMethod($name) {
		if (!isset($this->cache->data[$name])) {
			$method = parent::getMethod($name);
			if ($method===null) {
				$this->cache->data[$name] = null;
			} else {
				$this->cache->data[$name] = new ReflectionAnnotatedMethod($this->getName(),$name);
			}
		}
		return $this->cache->data[$name];
	}
	
	public function getMethods($filter = -1) {
		$result = array();
		foreach(parent::getMethods($filter) as $method) {
			$name = $method->getName();
			if (!isset($this->cache->data[$name]))
				$this->cache->data[$name] = new ReflectionAnnotatedMethod($this->getName(),$name);
			$result[] = $this->cache->data[$name];
		}
		return $result;
	}
	
	public function getProperty($name) {
		$key = "\$$name";
		if (!isset($this->cache->data[$key])) {
			$method = parent::getProperty($name);
			if ($method===null) {
				$this->cache->data[$key] = null;
			} else {
				$this->cache->data[$key] = new ReflectionAnnotatedProperty($this->getName(),$name);
			}
		}
		return $this->cache->data[$key];
	}
	
	public function getProperties($filter = -1) {
		$result = array();
		foreach(parent::getProperties($filter) as $method) {
			$name = $method->getName();
			$key = "\$$name";
			if (!isset($this->cache->data[$key]))
				$this->cache->data[$key] = new ReflectionAnnotatedProperty($this->getName(),$name);
			$result[] = $this->cache->data[$key];
		}
		return $result;
	}
	
	public function getInterfaces() {
		if (!isset($this->cache->data['$$interfaces'])) {
			$this->cache->data['$$interfaces'] = array();
			foreach(parent::getInterfaces() as $interface) {
				$this->cache->data['$$interfaces'][] = new ReflectionAnnotatedClass($interface->getName());
			}
		}
		return $this->cache->data['$$interfaces'];
	}
	
	public function getParentClass() {
		if (!isset($this->cache->data['$$parent'])) {
			$class = parent::getParentClass();
			if ($class===false) {
				$this->cache->data['$$parent'] = false;
			} else {
				$this->cache->data['$$parent'] = new ReflectionAnnotatedClass($class->getName());
			}
		}
		return $this->cache->data['$$parent'];
	}
}

class ReflectionAnnotatedMethod extends ReflectionMethod {
	private $cache;
	
	public function getIDString() {
		return parent::getDeclaringClass()->getName()."::".$this->getName();
	}
	
	public function __construct($class, $name) {
		parent::__construct($class, $name);
		$this->cache =& ReflectionAnnotatedCache::getFor($this);
	}
	
	private function buildAnnotations() {
		if ($this->cache->annotations===FALSE)
			$this->cache->annotations =& AnnotationsBuilder::build($this);
	}
	
	public function hasAnnotation($annotation) {
		$this->buildAnnotations();
		return isset($this->cache->annotations[$annotation]);
	}
	
	public function getAnnotation($annotation) {
		$this->buildAnnotations();
		return isset($this->cache->annotations[$annotation]) ? end($this->cache->annotations[$annotation]) : false;
	}

	public function getAnnotations() {
		$this->buildAnnotations();
		$result = array();
		foreach($this->cache->annotations as $instances) {
			$result[] = end($instances);
		}
		return $result;
	}
	
	public function getAllAnnotations($restriction = false) {
		$this->buildAnnotations();
		$result = array();
		foreach($this->cache->annotations as $class => $instances) {
			if(!$restriction || $restriction == $class) {
				$result = array_merge($result, $instances);
			}
		}
		return $result;
	}
	
	public function getDeclaringClass() {
		if (!isset($this->cache->data['$$class'])) {
			$class = parent::getDeclaringClass();
			$this->cache->data['$$class'] = new ReflectionAnnotatedClass($class->getName());
		}
		return $this->cache->data['$$class'];
	}
}

class ReflectionAnnotatedProperty extends ReflectionProperty {
	private $cache;
	
	public function getIDString() {
		return parent::getDeclaringClass()->getName()."::$".$this->getName();
	}
	
	public function __construct($class, $name) {
		parent::__construct($class, $name);
		$this->cache =& ReflectionAnnotatedCache::getFor($this);
	}
	
	private function buildAnnotations() {
		if ($this->cache->annotations===FALSE)
			$this->cache->annotations =& AnnotationsBuilder::build($this);
	}
	
	public function hasAnnotation($annotation) {
		$this->buildAnnotations();
		return isset($this->cache->annotations[$annotation]);
	}
	
	public function getAnnotation($annotation) {
		$this->buildAnnotations();
		return isset($this->cache->annotations[$annotation]) ? end($this->cache->annotations[$annotation]) : false;
	}
	
	public function getAnnotations() {
		$this->buildAnnotations();
		$result = array();
		foreach($this->cache->annotations as $instances) {
			$result[] = end($instances);
		}
		return $result;
	}
	
	public function getAllAnnotations($restriction = false) {
		$this->buildAnnotations();
		$result = array();
		foreach($this->cache->annotations as $class => $instances) {
			if(!$restriction || $restriction == $class) {
				$result = array_merge($result, $instances);
			}
		}
		return $result;
	}
		
	public function getDeclaringClass() {
		if (!isset($this->cache->data['$$class'])) {
			$class = parent::getDeclaringClass();
			$this->cache->data['$$class'] = new ReflectionAnnotatedClass($class->getName());
		}
		return $this->cache->data['$$class'];
	}
}
	

?>
