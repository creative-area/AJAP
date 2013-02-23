function later() {
	var defer = $.Deferred();
	setTimeout( defer.resolve );
	return defer.promise();
}

function cache( fn ) {
	var cache = {};
	function cacheFN( key, value ) {
		return ( key in cache )
			? cache[ key ]
			: ( cache[ key ] = fn( key, value ) );
	}
	cacheFN.cache = cache;
	return cacheFN;
}

function each( array, func ) {
	var i = 0;
	var length = array.length;
	for ( ; i < length; i++ ) {
		func( array[ i ] );
	}
}

function map( array, func ) {
	var output = [];
	var i = 0;
	var length = array.length;
	for ( ; i < length; i++ ) {
		output[ i ] = func( array[ i ] );
	}
	return output;
}
