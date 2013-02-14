var getFromCache;
var setToCache;
var r_func = /^\s*function\s*\(\s*\)\s*\{|\}\s*$/;
	
if ( typeof window.localStorage !== "undefined" ) {
	getFromCache = function( infos ) {
		var name, key,
			services = {};
		for ( name in infos ) {
			key = URL + "/" + name;
			if ( localStorage[ key + "/ts" ] === infos.ts ) {
				services[ name ] = $.globalEval( localStorage[ key ] );
			}
		}
		return services;
	};
	setToCache = function( name, service, ts ) {
		var key = URL + "/" + name;
		localStorage[ key + "/ts" ] = ts;
		localStorage[ key ] = "" + service;
	};
} else {
	getFromCache = function() {
		return {};
	};
	setToCache = $.noop;
}

function requestServices( services ) {
	services.sort();
	services = "" + services;
	return $.ajax( URL, {
		cache: false,
		dataType: "ajapJSON",
		data: {
			serviceInfo: services
		}
	})
	.pipe( handleAppError )
	.pipe(function( info ) {
		var infos = info.services,
			cache = getFromCache( infos ),
			services = getKeys( infos, cache );
		services.sort();
		return $.ajax( URL, {
			cache: true,
			dataType: "ajapJSON",
			data: {
				ts: info.ts,
				services: "" + services
			}
		})
		.pipe( handleAppError )
		.pipe(function( services ) {
			$.each( infos, function( name, info ) {
				var i, length;
				var init = [];
				var style = "";
				if ( !cache[ name ] ) {
					setToCache( name, services[ name ], info.ts );
				}
				services[ name ] = ( cache[ name ] || services[ name ] )({
					init: function( func ) {
						init.push( func );
					},
					dynamic: info.dynamic,
					execute: execute,
					css: function( fragment ) {
						style += fragment;
					},
					js: function( func ) {
						$.globalEval( ( "" + func ).replace( r_func, "" ) );
					}
				});
				if ( style ) {
					createStyle( name, style );
				}
				for ( i = 0, length = init.length; i < length; i++ ) {
					$( $.proxy( init[ i ], services[ name ] ) );
				}
				style = init = undefined;
			});
			return services;
		});
	});
}

var pending;

var services = cacheFactory({
	value: grouper( requestServices ),
	object: true
});

jQuery.extend( Ajap, {
	load: map( services.get, true ),
	unload: map( services.unset )
});
