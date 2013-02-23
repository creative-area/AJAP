window.Ajap || ( window.Ajap = function( jQuery ) {

include( "ext/json3.min.js" );

if ( !jQuery.jsonp ) {
	include( "ext/jquery.jsonp.min.js" );
}

include( "lib/utils.js" );
include( "lib/ajax.js" );

include( "core/load.js" );

return {
	load: load,
	unload: unload
};

} )( jQuery );
