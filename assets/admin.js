( function () {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function () {
		const form = document.getElementById( 'doag-generator' );
		if ( ! form ) {
			return;
		}
		form.addEventListener( 'submit', function () {
			form.querySelector( '[type="submit"]' ).disabled = true;
			document.getElementById( 'doag-progress' ).hidden = false;
		} );
	} );
}() );
