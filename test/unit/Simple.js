module( "simple" );

test( "alias", function() {
	expect( 1 );
	strictEqual( Simple, SimpleAlias, "alias is created" );
});

test( "properties", function() {
	expect( 6 );
	strictEqual( Simple.$prop, "hello", "prop initialized OK" );
	strictEqual( Simple.$prop2, "world", "prop initialized in constructor OK" );
	ok( !Simple.hasOwnProperty( "$prop3" ), "private prop not serialized" );
	ok( !Simple.hasOwnProperty( "init" ), "init is not generated as a method" );
	ok( Simple.hasOwnProperty( "$implicit" ), "implicit is generated" );
	ok( Simple.$dynamic, "dynamic prop set" );
});

test( "javascript", function() {
	expect( 2 );
	strictEqual( Simple.javascript( 5 ), 10 );
	strictEqual( Simple.javascript( 2 ), 4 );
});

test( "php", function() {
	expect( 3 );
	stop();
	$.when( Simple.php( 5 ), Simple.php( 2 ) ).done(function( ten, four ) {
		strictEqual( ten, 10 );
		strictEqual( four, 4 );
		Simple.php( 3 ).done(function( six ) {
			strictEqual( six, 6 );
			start();
		});
	});
});

test( "dynamic", function() {
	expect( 4 );
	stop();
	var dynamic = Simple.$dynamic;
	window.TestSimpleInit = true;
	Ajap.unloadModule( "Simple" );
	Ajap.loadModule( "Simple" ).done(function() {
		strictEqual( window.TestSimpleInit, undefined, "everything in order" );
		notStrictEqual( dynamic, Simple.$dynamic, "dynamic property is re-computed" );
		start();
	});
});

test( "implicit", function() {
	expect( 1 );
	stop();
	Simple.$implicit = "implicit";
	Simple.getImplicit().done(function( implicit ) {
		strictEqual( implicit, "implicit", "implicit is sent to the server" );
		start();
	});
});

test( "error", function() {
	expect( 1 );
	stop();
	Simple.error( "my message" ).fail(function( message ) {
		strictEqual( message, "my message", "exception propagated" );
		start();
	});
});
