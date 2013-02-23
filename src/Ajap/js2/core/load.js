function load() {
	return jQuery.when.apply( jQuery, map( arguments, loadOne ) );
}

function unload() {
	each( arguments, function( name ) {
		if ( name in loadOne.cache ) {
			// TODO: Strengthen here ( dependencies, unload event )
			delete loadOne.cache[ name ];
		}
	} );
	return this;
}

var loadOne = cache( function( name ) {
	return loadInfo( name ).pipe( loadOneWithInfo );
} );

var loadInfo = multiplexRequest( {
	action: "info",
	done: function( infos ) {
		for( var name in infos ) {
			if ( !( name in loadOne.cache ) ) {
				loadOne.cache[ name ] = loadOneWithInfo( infos[ name ] );
			}
		}
	}
} );

function loadOneWithInfo( info ) {
	if ( info ) {
		return getOrLoadVersioned( name + "@" + info.ts ).pipe( function( func ) {
			return instantiateService( name, func.call( info.data || {} ) );
		} );
	}
}

var getOrLoadVersioned = cache( function( localStorage, loadVersioned ) {
	var internal = localStorage ?
		function( vName ) {
			var tmp = vName.split( "@" );
			var name = tmp[ 0 ];
			var ts = tmp[ 1 ];
			var key = "ajap/" + name;
			var service = localStorage[ key + "/ts" ] === ts && localStorage[ key ];
			return service ?
				jQuery.when( service ) :
				loadVersioned( vName ).done( function( service ) {
					localStorage[ key + "/ts" ] = ts;
					localStorage[ key ] = service;
				} );
		} :
		loadVersioned;
	return function( vName ) {
		return internal.pipe( jQuery.globalEval );
	};
})(
	window.localStorage,
	multiplexRequest( {
		action: "service",
		dataType: "text"
	} )
);	
