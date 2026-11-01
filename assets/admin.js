( function () {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( document.getElementById( 'doag-job-pending' ) ) {
			window.setTimeout( function () {
				window.location.reload();
			}, 10000 );
		}
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
