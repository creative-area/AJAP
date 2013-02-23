jQuery.ajaxPrefilter( function( options ) {
	if ( options.ajapTunnel ) {
		return "ajapTunnel";
	}
} );

jQuery.ajaxTransport( "ajapTunnel", function( options ) {
	var aborted;
	return {
		send: function( _, complete ) {
			exec.apply( null, options.ajapTunnel ).done(function( response ) {
				if ( !aborted ) {
					complete(
						response.status,
						response.statusText,
						{
							text: response.body
						},
						response.headers
					);
				}
			} );
		},
		abort: function() {
			aborted = true;
		}
	}
} );
