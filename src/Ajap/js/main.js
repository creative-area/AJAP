window.Ajap || (function( window ){

//@include json3.min.js

var URI = @URI,
	Ajap = window.Ajap = {
	
		// Merging properties of objects into a new one
		mergeObjects: function () {
			var tmp = {},
				obj, key,
				i = 0,
				length = arguments.length;
			for( ; i < length; i++) {
				obj = arguments[ i ];
				if ( obj ) {
					for( key in obj ) {
						tmp[ key ] = obj[ key ];
					}
				}
			}
			return tmp;
		},
	
		// Transform an object into url parameters
		objectToURLParams: function( object ) {
			var tmp = [], key, value;
			for ( key in object ) {
				value = object[ key ] || "";
				tmp.push( escape( key ) + "=" + escape( value ) );
			}
			return tmp.join( "&" );
		}
	};

//@engine

//@extensions

})( window );