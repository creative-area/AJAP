<?php

class AjapDynamicWriter {

private function _loop( &$array, $method, $encode, $field, $join ) {
	if ( $method || $encode || $field ) {
		$output = array();
		array_walk( $array, function( &$value, $key, &$self ) use ( &$output, $method, $encode, $field ) {
			$result = $value;
			if ( $method ) {
				$result = $self->$method( $result, $key );
			}
			if ( $encode ) {
				$result = var_export( $result, true );
			}
			if ( $field ) {
				$result = var_export( $key, true ) . " => " . $result;
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

public function element( &$data, $index = null ) {
	return '$writer->' .
	$data[ 'method' ] .
	'( ' .
	$this->_loop( $data[ 'args' ], '', true, false, ',' ) .
	' )
';
}

public function main( &$data, $index = null ) {
	return '<?php

$writer = new AjapServiceWriter( AjapClass::get( ' .
	var_export( $data[ 'name' ], true ) .
	' ) );
$output[ ' .
	var_export( $data[ 'name' ], true ) .
	' ] = array( \'ts\' => ' .
	var_export( $data[ 'ts' ], true ) .
	', \'data\' => array(
' .
	$this->_loop( $data[ 'data' ], 'element', false, true, ',' ) .
	'
) );
';
}

}
