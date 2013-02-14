var slice = [].slice;

// Creates a style tag for the given service
function createStyle( name, style ) {
	var tag = $( "<style>" ).attr( "id", "__ajap_styleFor_" + name  ).appendTo( "head" ),
		dom = tag[ 0 ];
	if ( dom.styleSheet ) {
		// IE Specifics
		dom.styleSheet.cssText = style;
	} else {
		// DOM Compliant browsers
		tag.text( style );
	}
}

// Map function generator
function map( fn, returnMap ) {
	return function() {
		var result = $.map( slice.call( arguments ), fn );
		return returnMap ? $.when.apply( $, result ) : this;
	};
}

// Get the keys of an object in an array
function getKeys( object, except ) {
	var key,
		array = [];
	except = except || {};
	for ( key in object ) {
		if ( !( key in except ) ) {
			array.push( key );
		}
	}
	return array;
}

// Groups calls together (internal timeout)
function grouper( fn, filter ) {
	var pending;
	return function( key ) {
		if ( !pending ) {
			pending = $.Deferred(function( defer ) {
				pending.keys = {};
				setTimeout(function() {
					var keys = getKeys( pending.keys );
					pending = undefined;
					fn( keys )
						.done( defer.resolve )
						.fail( defer.reject )
						.progress( defer.notify );
				});
			});
		}
		pending.keys[ key ] = true;
		return pending.pipe(function( results ) {
			return filter ? filter( results[ key ] ) : results[ key ];
		});
	};
}

/*
 * Cache factory
 * options:
 * - key - optional - fn(...) - generates the key
 * - value - mandatory - fn( key, this, arguments ) - generates the value
 * - fail - boolean( false ) - tells to cache failures
 * - object - boolean( false ) - return an object with get & unset methods
 */
function cacheFactory( options ) {
	if ( $.isFunction( options ) ) {
		options = {
			value: options
		};
	}
	var keyGenerator = options.key,
		cache = {};
	function get() {
		var key =
				keyGenerator
				? keyGenerator.apply( this, arguments )
				: arguments[ 0 ],
			value = cache[ key ];
		if ( !value ) {
			value = cache[ key ] = options.value( key, arguments, this );
			if ( !options.fail ) {
				value.fail(function() {
					if ( key in cache ) {
						delete cache[ key ];
					}
				});
			}
		}
		return value;
	}
	return options.object ? {
		get: get,
		unset: function() {
			var key =
					keyGenerator
					? keyGenerator.apply( this, arguments )
					: arguments[ 0 ];
			if ( cache[ key ] ) {
				delete cache[ key ];
			}
			return this;
		}
	} : get;
}
