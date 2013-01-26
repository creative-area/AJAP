(function( Ajap ) {

jQuery.extend( Ajap, {

	jsonEncode: jQuery.toJSON,
	jsonDecode: jQuery.parseJSON,

	ajax: function( url, dataType, data ) {
		return jQuery.ajax({
			url: url,
			type: "POST",
			cache: true,
			data: data,
			dataType: dataType
		});
	},

	type: jQuery.type,

	Deferred: jQuery.Deferred,
	when: jQuery.when,

	jsonp: function( url ) {
		return $.Ajap.Engine.Deferred(function( defer ) {
			jQuery.jsonp({
				url: url,
				success: defer.resolve,
				error: defer.reject,
				cache: true
			});
		}).promise();
	},

	serializeForm: function( formElement ) {
		return jQuery( formElement ).serialize();
	},

	makeInitCode: function( fn ) {
		jQuery( fn );
		return this;
	},

	getIFrameText: function( frame , callback ) {
		return frame.unbind().bind( "load" , function() {
			frame.unbind();
			callback( frame.contents().text() );
		});
	}
});

})( Ajap );