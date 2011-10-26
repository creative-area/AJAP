(function( Ajap ) {

	var last = Ajap.Deferred().resolve().promise(),
		r_url = /^(!?)(.*)\|(.*)$/,
		urls = {},
		head = document.getElementsByTagName( "head" )[ 0 ];
		handlers = {
			JS: Ajap.getScript,
			CSS: function( url ) {
				var link = document.createElement("link");
				link.href = url;
				link.type = "text/css";
				link.rel = "stylesheet";
				head.appendChild(link);
				return Ajap.Deferred().resolve().promise();
			}
		};

	Ajap.whenReady = function( action ) {
		var type = Ajap.type( action ), realAction;
		if ( type === "array" ) {
			var i = 0,
				length = action.length;
			for( ; i < length ; i++ ) {
				Ajap.whenReady( action[ i ] );
			}
		} else if ( type ===  "function" ) {
			realAction = function() {
				return Ajap.when( action() );
			};
		} else if ( type === "string" ) {
			realAction = urls[ action ];
			if ( !realAction ) {
				Ajap.Deferred(function( defer ) {
					var url = r_url.exec( action ), load, cache;
					if ( !url || !url[ 3 ] || !handlers[ url[2] ] ) {
						defer.reject( "Illformed URL or Unknown Format", url && url[ 2 ] );
					} else {
						realAction = function() {
							return cache || ( cache = handlers[ tmp[2] ]( url[ 3 ] ) );
						};
						if ( !tmp[ 1 ] ) {
							defer = realAction();
							realAction = undefined;
						}
					}
					if ( !realAction ) {
						realAction = function() {
							return defer.promise();
						};
					}
				});
				urls[ action ] = realAction;
			}
		}
		if ( realAction ) {
			last = last.pipe( realAction );
		}
	};

})( window.Ajap );