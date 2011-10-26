(function( Ajap ) {

var // List of style nodes
	styleNodes = {},
	// Current style node
	currentStyleNodeId = 0,
	// Cached head
	head = document.getElementsByTagName( "head" )[ 0 ];

// Add css style to a page on a specific node
function addStyle( id, cssText ) {

	var tmp = styleNodes[ id ];

	if ( tmp === undefined ) {
		styleNodes[ id ] = tmp = document.createElement( "style" );
		tmp.type = "text/css";
		tmp.id = "__ajap_styleFor_" + id;
		head.appendChild( tmp );
	}

	if ( tmp.styleSheet ) {
		// IE Specifics
		tmp.styleSheet.cssText += cssText;
	} else {
		// DOM Compliant browsers
		tmp.appendChild( document.createTextNode( cssText ) );
	}
}

// HOOKS
Ajap.setCurrentStyleNodeId = function( styleNodeId ) {
	currentStyleNodeId = styleNodeId;
};

Ajap.addStyle = function( cssText ) {
	addStyle( currentStyleNodeId, cssText );
};

})( Ajap );