function pipeSuccess( data ) {
	if ( data.e ) {
		return Ajap.Deferred().rejectWith( this, data.e );
	}
	return data.r;
}

function pipeError( _1, type, msg ) {
	return type + ": " + msg;
}

function pipeNoop() {
}

var sendCache = {};

Ajap.send = function ( service, method, data, cache ) {
	var request,
		reqParam = JSON.stringify({
			service: service,
			method: method,
			data: data
		});
	if ( cache ) {
		if (( request = sendCache[ reqParam ] )) {
			return request;
		}
	}
	request = Ajap.ajax( URI, "json", {
		execute: reqParam
	} ).pipe( pipeSuccess, pipeError );
	if ( cache ) {
		sendCache[ reqParam ] = request;
	}
	return request;
};

Ajap.post = function ( service, method, data, cache ) {
	data[ 0 ] = Ajap.serializeForm( data[ 0 ] );
	return Ajap.send( service, method, data, cache );
};

Ajap.jsonp = function( url, object, cache, filter ) {
	var request;
	if ( cache ) {
		if (( request = sendCache[ url ] )) {
			return request;
		}
	}
	request = Ajap.jsonp( url ).pipe( function( response ) {
		return filter ? filter.call( object, response ) : response;
	}, pipeError );
	if ( cache ) {
		sendCache[ key ] = request;
	}
	return request;
};

function getObject( expr, del ) {
	expr = ( expr || "" ).split( "." );
	var i = 0,
		length = expr.length,
		prev, obj = window;
	while( i < length && ( prev = obj ) && ( obj = prev[ expr[( i++ )] ] ) );
	if ( i && ( i === length ) ) {
		if ( del ) {
			try {
				delete prev[ expr[ length - 1 ] ];
			} catch( e ) {
				prev[ expr[ length - 1 ] ] = undefined;
			}
		}
		return obj;
	}
}

// ################################################## SERVICE LOADER

// Keep track of loaded services
var loadedServices = {},
	loadedServicesString = "";

// Registers a service
Ajap._registerService = function( service, construct ) {
	if ( !loadedServices[ service ] ) {
		loadedServices[ service ] = true;
		if ( loadedServicesString === "" ) {
			loadedServicesString = service;
		} else {
			loadedServicesString += "," + service;
		}
		construct();
	}
};

// Unload a service
Ajap.unloadService = function( service ) {
	var object = getObject( service, true );
	if ( object ) {
		if ( object.__ajap__onunload ) {
			object.__ajap__onunload();
		}
		delete loadedServices[ service ];
		delete services[ service ];
		var tmp = [],
			name;
		for ( name in loadedServices ) {
			if ( loadedServices[ name ] ) {
				tmp.push( loadedServices[ name ] );
			}
		}
		loadedServicesString = tmp.join(",");
	}
};

var services = {};

// Load a service
Ajap.loadService = function( service, data ) {
	if ( service ) {
		if ( Ajap.type( service ) !== "array" ) {
			service = ( "" + service ).split( "," );
		}
		var i = 0,
			length = service.length,
			request,
			actual = [],
			promises = [];
		for( ; i < length; i++ ) {
			if ( services[ service[i] ] ) {
				promises.push( services[ service[i] ] );
			} else {
				actual.push( service[i] );
			}
		}
		i = 0;
		length = actual.length;
		if ( length ) {
			request = Ajap.ajax( URI + ( /\?/.test( Ajap.URI )? '&' : '?' ) + 'service=' + actual.join( "," ) , "script", {
				"data": JSON.stringify( data ),
				"loaded": loadedServicesString
			});
			for ( ; i < length; i++ ) {
				services[ actual[i] ] = request;
			}
			promises.push( request );
		}
		return Ajap.when.apply( Ajap, promises );
	}
};
