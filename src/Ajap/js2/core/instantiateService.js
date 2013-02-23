function serviceProxy( name ) {
    return {
        exec: function( method, args, cache ) {
            return exec( name, method, args, cache );
        }
    };
}

var serviceAction = {
	define: function( def ) {
		jQuery.extend( this.service, jQuery.isFunction( def ) ? def( serviceProxy( this.name ) ) : def );
	},
	dependsOn: load,
	extend: function( name ) {
		var self = this;
		return load( name ).done( function( parent ) {
			jQuery.extend( self.service, parent );
		} );
	},
	init: function( func ) {
		jQuery.ready( jQuery.proxy( this.service, func ) );
	},
	inlineScript: jQuery.globalEval,
	inlineStyle: function( style ) {
		var node = $( "<style>" ).attr( {
			"class": "ajap_style_" + this.name
		} ).appendTo( "head" );
		if ( node[ 0 ].cssText ) {
			node[ 0 ].cssText = style;
		} else {
			node.text( style );
		}
	},
	remoteScript: jQuery.getScript,
	remoteStyle: function( url ) {
		$( "<link>" ).attr({
			"class": "ajap_style_" + this.name,
			href: url,
			rel: "stylesheet"
		}).appendTo( "head" );
	}
};

var serviceBlocker = {
	dependsOn: true,
	extend: true,
	remoteScript: true
};

function instantiateService( name, actions ) {
	var service = {};
	var promise = jQuery.Deferred().resolve( service );
	var originalPromise = promise;
	var self = {
		name: name,
		service: service
	};
	each( static, function( item ) {
		if ( jQuery.isFunction( item ) ) {
			promise.done( item );			
		} else {
			var method = item[ 0 ];
			var arg = item[ 1 ];			
            promise = promise[ serviceBlocker[ method ] ? "pipe" : "done" ]( function() {
                return serviceAction[ method ][ jQuery.isArray( arg ) ? "apply" : "call" ]( self, arg );
            } );
		}
	} );
	return promise.pipe( function() {
		return originalPromise;
	} );
}
