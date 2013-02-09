module( "simple" );

test( "@Alias", function() {
	expect( 1 );
	strictEqual( Simple, SimpleAlias, "alias is created" );
});

test( "@Local", function() {
	expect( 2 );
	ok( !Simple.hasOwnProperty( "$prop4" ), "local prop not serialized" );
	ok( !Simple.hasOwnProperty( "phpMethod2" ), "local method not generated" );
});

test( "generated", function() {
	expect( 4 );
	strictEqual( Simple.$prop, "hello", "prop initialized OK" );
	strictEqual( Simple.$prop2, "world", "prop initialized in constructor OK" );
	ok( Simple.hasOwnProperty( "$implicit" ), "implicit is generated" );
	ok( Simple.$dynamic, "dynamic prop set" );
});

test( "not generated", function() {
	expect( 3 );
	ok( !Simple.hasOwnProperty( "$prop3" ), "private prop not serialized" );
	ok( !Simple.hasOwnProperty( "phpMethod" ), "private method not generated" );
	ok( !Simple.hasOwnProperty( "init" ), "init is not generated as a method" );
});

test( "@JS (method)", function() {
	expect( 2 );
	strictEqual( Simple.javascript( 5 ), 10, "5 * 2 = 10" );
	strictEqual( Simple.javascript( 2 ), 4, "2 * 2 = 4" );
});

test( "remote method", function() {
	expect( 3 );
	stop();
	$.when( Simple.php( 5 ), Simple.php( 2 ) ).done(function( ten, four ) {
		strictEqual( ten, 10, "5 * 2 = 10" );
		strictEqual( four, 4, "2 * 2 = 4" );
		Simple.php( 3 ).done(function( six ) {
			strictEqual( six, 6, "3 * 2 = 6" );
			start();
		});
	});
});

test( "@Dynamic", function() {
	expect( 5 );
	stop();
	var dynamic = Simple.$dynamic,
		dynamicMethod = Simple.dynamicMethod();
	window.TestSimpleInit = true;
	Ajap.unloadService( "Simple" );
	Ajap.loadService( "Simple" ).done(function() {
		strictEqual( window.TestSimpleInit, undefined, "everything in order" );
		notStrictEqual( dynamic, Simple.$dynamic, "dynamic property is re-computed" );
		notStrictEqual( dynamicMethod, Simple.dynamicMethod(), "dynamic method is re-generated" );
		start();
	});
});

test( "@Template", function() {
	expect( 6 );
	strictEqual( Simple.template( 1 ), "We have 1 apple" );
	strictEqual( Simple.template( 2 ), "We have 2 apples" );
	strictEqual( Simple.templatePreserveSpaces( 1 ), "We  have  1  apple" );
	strictEqual( Simple.templatePreserveSpaces( 2 ), "We  have  2  apples" );
	strictEqual( Simple.templateDynamic( 1 ), "We have 1 apple" );
	strictEqual( Simple.templateDynamic( 2 ), "We have 2 apples" );
});

test( "@Implicit", function() {
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

test( "@Post", function() {
	expect( 1 );
	stop();
	var form = jQuery( "<form>" ).append( $( "<input name='my-input'>" ).val( "my-value" ) )[ 0 ];
	Simple.post( form ).done(function( value ) {
		strictEqual( value, "my-value", "form properly serialized" );
		start();
	});
});

test( "@Volatile", function() {
	expect( 3 );
	stop();
	Ajap.loadService( "Simple.Volatile" ).done(function() {
		ok( !( Simple.hasOwnProperty( "Volatile" ) ), "Volatile is not in Simple after init" );
		start();
	});
});
