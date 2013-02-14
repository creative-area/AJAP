var doExecute = grouper( function ( requestStrings ) {
	return jQuery.ajax( URL, {
		cache: false,
		dataType: "json",
		data: {
			execute: "[" + requestStrings + "]"
		}
	}).pipe( handleAppError );
}, handleAppError );

var execCache = cacheFactory({
	value: doExecute
});
	
Ajap.execute = function( service, method, args, cache ) {
	var requestString = JSON.stringify({
			service: service,
			method: method,
			args: args
		});
	return ( cache ? execCache : doExecute )( requestString );
};
