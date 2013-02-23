<?php

class AjapJSWriter {

private function _loop( &$array, $method, $encode, $field, $join ) {
	if ( $method || $encode || $field ) {
		$output = array();
		array_walk( $array, function( &$value, $key, &$self ) use ( &$output, $method, $encode, $field ) {
			$result = $value;
			if ( $method ) {
				$result = $self->$method( $result, $key );
			}
			if ( $encode ) {
				$result = json_encode( $result );
			}
			if ( $field ) {
				$result = json_encode( $key ) . ": " . $result;
			}
			$output[ $key ] = trim( $result );
		}, $this );
	} else {
		$output =& $array;
	}
	if ( $join === ";" || $field ) {
		$join .= "\n";
		if ( $join === ";" ) {
			$output[] = "";
		}
	} else {
		$join .= " ";
	}
	return implode( $join , $output );
}

public function dependency( &$data, $index = null ) {
	return '[ "dependsOn", [ ' .
	$this->_loop( $data, '', true, false, ',' ) .
	' ] ]
';
}

public function parent( &$data, $index = null ) {
	return '[ "extend", ' .
	json_encode( $data ) .
	' ]
';
}

public function js( &$data, $index = null ) {
	return '' .
	( isset( $data[ 'dynamic' ] )  ? 'this[ ' .
	json_encode( $data[ 'dynamic' ] ) .
	' ]
' : "" ) .
	( isset( $data[ 'text' ] )  ? '[ "inlineScript", ' .
	json_encode( $data[ 'text' ] ) .
	' ]
' : "" ) .
	( isset( $data[ 'url' ] )  ? '[ "remoteScript", ' .
	json_encode( $data[ 'url' ] ) .
	' ]
' : "" ) .
	( isset( $data[ 'code' ] )  ? 'function() { ' .
	$data[ 'code' ] .
	' }
' : "" );
}

public function css( &$data, $index = null ) {
	return '' .
	( isset( $data[ 'dynamic' ] )  ? 'this[ ' .
	json_encode( $data[ 'dynamic' ] ) .
	' ]
' : "" ) .
	( isset( $data[ 'text' ] )  ? '[ "inlineStyle", ' .
	json_encode( $data[ 'text' ] ) .
	' ]
' : "" ) .
	( isset( $data[ 'url' ] )  ? '[ "remoteStyle", ' .
	json_encode( $data[ 'url' ] ) .
	' ]
' : "" );
}

public function method_ajap( &$data, $index = null ) {
	return 'function( ' .
	$this->_loop( $data[ 'args' ], '', false, false, ',' ) .
	'  ) {
	return proxy.exec( ' .
	json_encode( $index ) .
	', [ ' .
	$this->_loop( $data[ 'args' ], '', false, false, ',' ) .
	' ], ' .
	json_encode( $data[ 'cache' ] ) .
	' );
}
';
}

public function method_post( &$data, $index = null ) {
	return 'function( form ) {
	return proxy.exec( ' .
	json_encode( $index ) .
	', [ $( form ).serialize() ], ' .
	json_encode( $data[ 'cache' ] ) .
	' );
}
';
}

public function method_remote( &$data, $index = null ) {
	return '' .
	( isset( $data[ 'type' ] ) && ( $data[ 'type' ] == 'Ajap' ) ? '' .
	$this->method_ajap( $data, $index ) .
	'
' : "" ) .
	( isset( $data[ 'type' ] ) && ( $data[ 'type' ] == 'Post' ) ? '' .
	$this->method_post( $data, $index ) .
	'
' : "" );
}

public function property_member( &$data, $index = null ) {
	return '' .
	( isset( $data[ 'value' ] )  ? '' .
	json_encode( $data[ 'value' ] ) .
	'
' : "" ) .
	( !isset( $data[ 'value' ] )  ? 'null
' : "" );
}

public function actual_member( &$data, $index = null ) {
	return '' .
	( isset( $data[ 'code' ] )  ? 'function( ' .
	$this->_loop( $data[ 'args' ], '', false, false, ',' ) .
	' ) { ' .
	$data[ 'code' ] .
	' }
' : "" ) .
	( !isset( $data[ 'code' ] )  ? '' .
	$this->property_member( $data, $index ) .
	'
' : "" );
}

public function member( &$data, $index = null ) {
	return '' .
	( isset( $data[ 'dynamic' ] )  ? 'this[ ' .
	json_encode( $data[ 'dynamic' ] ) .
	' ]
' : "" ) .
	( !isset( $data[ 'dynamic' ] )  ? '' .
	$this->actual_member( $data, $index ) .
	'
' : "" );
}

public function init( &$data, $index = null ) {
	return '[ "init",
	' .
	( !isset( $data[ 'dynamic' ] )  ? '' .
	$this->member( $data, $index ) .
	'
' : "" ) .
	'	' .
	( isset( $data[ 'dynamic' ] )  ? 'this[ ' .
	json_encode( $data[ 'dynamic' ] ) .
	' ]
' : "" ) .
	']
';
}

public function service( &$data, $index = null ) {
	return 'function() {
	return [
		' .
	( isset( $data[ 'dependency' ] )  ? $this->dependency( $data[ 'dependency' ], $index ) .
	',
' : "" ) .
	'		' .
	( isset( $data[ 'parent' ] )  ? '' .
	$this->parent( $data[ 'parent' ], $index ) .
	',
' : "" ) .
	'		' .
	( isset( $data[ 'css' ] )  ? '' .
	$this->_loop( $data[ 'css' ], 'css', false, false, ',' ) .
	',
' : "" ) .
	'		' .
	( isset( $data[ 'js' ] )  ? '' .
	$this->_loop( $data[ 'js' ], 'js', false, false, ',' ) .
	',
' : "" ) .
	'		' .
	( isset( $data[ 'member' ] )  ? '[ "define", { ' .
	$this->_loop( $data[ 'member' ], 'member', false, true, ',' ) .
	' } ],
' : "" ) .
	'		' .
	( isset( $data[ 'remote' ] )  ? '[ "define", function( proxy ) { return { ' .
	$this->_loop( $data[ 'remote' ], 'method_remote', false, true, ',' ) .
	' }; } ],
' : "" ) .
	'		' .
	( isset( $data[ 'name' ] )  ? '[ "name", [ ' .
	$this->_loop( $data[ 'name' ], '', true, false, ',' ) .
	' ] ],
' : "" ) .
	'		' .
	( isset( $data[ 'init' ] )  ? '' .
	$this->_loop( $data[ 'init' ], 'init', false, false, ',' ) .
	',
' : "" ) .
	'		true
	];
}
';
}

public function services( &$data, $index = null ) {
	return '{ ' .
	$this->_loop( $data, 'service', false, true, ',' ) .
	' }
';
}

public function dynamic( &$data, $index = null ) {
	return '[ ' .
	$this->_loop( $data, '', true, false, ',' ) .
	' ]
';
}

public function dynamics( &$data, $index = null ) {
	return '{ ' .
	$this->_loop( $data, 'service', false, true, ',' ) .
	' }
';
}

}
