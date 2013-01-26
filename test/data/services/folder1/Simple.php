<?php

/**
 * @Ajap
 * 
 * @Alias SimpleAlias
 */
class Simple {
	
	public $prop = "hello";
	public $prop2;
	
	private $prop3 = "php only";
	
	/**
	 * @Dynamic
	 */
	public $dynamic;
	
	/**
	 * @Implicit
	 */
	public $implicit;
	
	public function __construct() {
		$this->prop2 = "world";
		$this->dynamic = microtime( true );
	}
	
	public function getImplicit() {
		return $this->implicit;
	}
	
	/**
	 * @JS
	 */
	public function __ajap__onunload() {
		return '
		ok( true, "__ajap__onunload called" );
		';
	}
	
	/**
	 * @Init
	 */
	public function init() {
		return '
		if ( window.TestSimpleInit ) {
			ok( true, "init executed" );
			window.TestSimpleInit = undefined;
		}
		';
	}
	
	/**
	 * @JS
	 */
	public function javascript( $value ) {
		return '
		return $value * 2;
		';
	}

	public function php( $value ) {
		return $value * 2;
	}
	
	public function error( $message ) {
		throw new AjapException( $message );
	}
	
	/**
	 * @Post
	 */
	public function post( $data ) {
		return $data[ "my-input" ];
	}
} 