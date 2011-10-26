(function( Ajap ) {

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

	Ajap.send = function ( moduleName, methodName, data, cache ) {
		var url = Ajap.URI + ( /\?/.test( Ajap.URI )? '&' : '?' ) + 'execute=' + moduleName + ":" + methodName,
			strData = Ajap.jsonEncode( data ),
			key, request;
		if ( cache ) {
			key = url + "/" + strData;
			if (( request = sendCache[ key ] )) {
				return request;
			}
		}
		request = Ajap.ajax( url, "json", {
			__ajap__data: strData
		}).pipe( pipeSuccess, pipeError );
		if ( cache ) {
			sendCache[ key ] = request;
		}
		return request;
	};

	Ajap.post = function ( moduleName, methodName, form, cache ) {
		var data = Ajap.Engine.serializeForm( form );
		return Ajap.send( moduleName, methodName, data, cache );
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

	// ################################################## MODULE LOADER

	// Keep track of loadedModules
	var loadedModules = {},
		loadedModulesString = "";

	// Set a class as loaded
	Ajap._classIsLoaded = function( moduleName ) {
		var tmp = loadedModules[ moduleName ];
		if ( ! tmp ) {
			loadedModules[ moduleName ] = true;
			if ( loadedModulesString === "" ) {
				loadedModulesString = moduleName;
			} else {
				loadedModulesString += "," + moduleName;
			}
		}
		return !!tmp;
	};

	// Unload a module
	Ajap.unloadModule = function( moduleName ) {
		var object = getObject( moduleName, true );
		if ( object ) {
			if ( object.__ajap__onunload ) {
				object.__ajap__onunload();
			}
			delete loadedModules[ moduleName ];
			delete module[ moduleName ];
			var tmp = [],
				name;
			for ( name in loadedModules ) {
				if ( loadedModules[ name ] ) {
					tmp.push( loadedModules[ name ] );
				}
			}
			loadedModulesString = tmp.join(",");
		}
	};

	var modules = {};

	// Load a module
	Ajap.loadModule = function( module, data ) {
		if ( module ) {
			if ( Ajap.type( module ) !== "array" ) {
				module = ( "" + module ).split( "," );
			}
			var i = 0,
				length = module.length,
				request,
				actual = [],
				promises = [];
			for( ; i < length; i++ ) {
				if ( modules[ module[i] ] ) {
					promises.push( modules[ module[i] ] );
				} else {
					actual.push( module[i] );
				}
			}
			i = 0;
			length = actual.length;
			if ( length ) {
				request = Ajap.ajax( Ajap.URI + ( /\?/.test( Ajap.URI )? '&' : '?' ) + 'getModule=' + actual.join( "," ) , "script", {
					"__ajap__data": Ajap.jsonEncode(data),
					"__ajap__already__loaded": loadedModulesString
				});
				for ( ; i < length; i++ ) {
					modules[ actual[i] ] = request;
				}
				promises.push( request );
			}
			return Ajap.when.apply( Ajap, promises );
		}
	};

})( window.Ajap );