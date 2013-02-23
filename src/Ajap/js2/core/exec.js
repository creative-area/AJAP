var execKey = 0;

function exec( module, method, args, cache ) {
	var data = [ module, method, args ];
	return cache ?
		cacheExecute( module + ":" + method + ":" + JSON.stringify( args ), data ) :
		execute( execKey++, data );
}

var execute = multiplexRequest({
	action: "exec",
	cache: false,
	beforeSend: function() {
		execKey = 0;
	}
});

var cacheExecute = cache( function( _, data ) {
	return execute( execKey++, data );
} );

function post( module, method, form, cache ) {
	return exec( module, method, [ $( form ).serialize() ], cache );
}

function jsonp( module, method, url, self, filter, cache ) {
	if (( cache = cache && cacheExecute.cache )) {
		var key = module + ":" + method + ":" + url;
		return cache[ key ] || ( cache[ key ] = jsonp( module, method, url, self, filter ) );
	}
	return jQuery.jsonp( {
		url: url
	} ).pipe( function( response ) {
		return filter ? filter.call( self, response ) : response;
	} );
}
