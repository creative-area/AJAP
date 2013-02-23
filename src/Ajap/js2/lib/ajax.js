function multiplexRequest( options ) {
	var items;
	var promise;
	cache = options.cache !== false;
	function init() {
		items = [];
		promise = later().pipe(function() {
			var _items = items;
			items = promise = undefined;
			return $.ajax( "@URL@", {
				cache: options.cache,
				dataType: options.dataType,
				beforeSend: options.beforeSend,
				converters: {
					"text json": function( text ) {
						return ( new Function( "return " + text ) )();
					}
				},
				data: {
					action: options.action,
					data: JSON.stringify( _items )
				},
				global: false,
				method: cache ? "GET" : "POST"
			} ).done( options.done );
		});
	}
	return function( key, item ) {
		item = item || key;
		if ( !items ) {
			init();
		}
		items.push( item );
		return promise.pipe( function( results ) {
			return results[ key ];
		} );
	};
}
