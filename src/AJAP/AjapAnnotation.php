<?php

require_once dirname(__FILE__)."/../annotations_fast.php"; // TODO: include in AjapEngine constructor

class AjapAnnotation extends Annotation {}

class Ajap extends AjapAnnotation {}
class Virtual extends AjapAnnotation {}
class Volatile extends AjapAnnotation {}
class Alias extends AjapAnnotation {}
class DependsOn extends AjapAnnotation {}
class JS extends AjapAnnotation {}
class Template extends AjapAnnotation {
	public $normalizeSpace = true;
}
class CSS extends AjapAnnotation {}

class RemoteJSONP extends AjapAnnotation {}
class CrossDomain extends AjapAnnotation {}

class Local extends AjapAnnotation {}
class Inherited extends AjapAnnotation {}
class NotInherited extends AjapAnnotation {}

class Implicit extends AjapAnnotation {}

class AjapPost extends AjapAnnotation {}
class Cached extends AjapAnnotation {}

class NonBlocking extends AjapAnnotation {}

class Init extends AjapAnnotation {}
class SelfSerialized extends AjapAnnotation {}

class Dynamic extends AjapAnnotation {}

?>