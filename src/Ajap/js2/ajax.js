// Register our own dirty json dataType
$.ajaxSetup({
	converters: {
		"text ajapJSON": $.globalEval 
	}
});

function handleAppError( response ) {
	if ( "fail" in response ) {
		return $.Deferred().rejectWith( this, [ response.fail ] );
	}
	return response.done;
}
