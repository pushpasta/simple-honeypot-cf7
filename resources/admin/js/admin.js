( function ( $ ) {
	'use strict';

	var initialData = '';
	var formDirty   = false;

	function syncDirty() {
		var $form = $( '.simple-honeypot-cf7-admin form' );
		if ( ! $form.length ) {
			return '';
		}
		return $form.serialize();
	}

	$(
		function () {
			var $form = $( '.simple-honeypot-cf7-admin form' );

			if ( ! $form.length ) {
					return;
			}

			initialData = syncDirty();

			$form.on(
				'change input',
				'input, select, textarea',
				function () {
					formDirty = syncDirty() !== initialData;
				}
			);

			$form.on(
				'submit',
				function () {
					formDirty = false;
				}
			);

			// Rules toggle: disable/enable the textarea.
			$form.on(
				'change',
				'.simple-honeypot-cf7-custom-rules-toggle input',
				function () {
					var $ta = $( this ).closest( '.simple-honeypot-cf7-custom-rules-group' )
					.find( '.simple-honeypot-cf7-rules' );
					$ta.prop( 'disabled', ! this.checked ).toggleClass( 'simple-honeypot-cf7-rules-disabled', ! this.checked );
				}
			);

			// Apply initial disabled state on page load.
			$form.find( '.simple-honeypot-cf7-custom-rules-toggle input:not(:checked)' ).trigger( 'change' );
		}
	);

	$( window ).on(
		'beforeunload',
		function () {
			if ( formDirty ) {
				return 'You have unsaved changes.';
			}
		}
	);
}( jQuery ) );
