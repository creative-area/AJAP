<?php

class AjapJSWriter {
	
	protected function __construct() {
	}
	
	private function args( $args ) {
		if ( !$args ) {
			$args = array();
		}
		foreach( $args as &$arg ) {
			if ( !is_string( $arg ) ) {
				$arg = json_encode( $arg );
			}
		}
		$args = implode( ", ", $args );
		return $args ? " $args " : "";
	}
	
	protected function statements( $array ) {
		return
			count( $array )
			? implode( ";\n", $array ) . ";\n"
			: "";
	}
	
	protected function tab( $block ) {
		return preg_replace( "/^/m", "\t", $block );
	}
	
	protected function func( $body, $args = null, $name = null ) {
		if ( !$name ) {
			$name = "";
		}	
		$args = $this->args( $args );
		$body = $this->tab( $body );
		if ( $name ) {
			$name = " $name";
		}
		return "function$name($args) {\n$body\n}";
	}
	
	protected function anon( $body ) {
		return $this->parens( $this->func( $body ) );
	}
	
	protected function obj( $fields ) {
		$body = array();
		foreach( $fields as $key => $value ) {
			$body[] = json_encode( $key ) . ": $value";
		}
		$body = $this->tab( implode( ",\n", $body ) );
		return "{\n$body\n}";
	}
	
	protected function _array( $elems ) {
		if ( !count( $elems ) ) {
			return "[]";
		}
		return "[ " . implode( ", ", $elems ) . " ]";
	}
	
	protected function parens( $expr ) {
		return "( $expr )";
	}
	
	protected function call( $func, $args = null ) {
		$args = $this->args( $args );
		return "$func($args)";
	}
};
