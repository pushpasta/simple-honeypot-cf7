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

	function validateRules( value ) {
		var lines  = value.split( /\r?\n/ );
		var errors = [];

		$.each(
			lines,
			function ( i, line ) {
				line = $.trim( line );

				if ( '' === line || 0 === line.indexOf( '#' ) ) {
					return;
				}

				var typed = line;
				if ( /^(ip|email):/i.test( typed ) ) {
					typed = typed.replace( /^(ip|email):/i, '' );
				}

				if ( '' === typed ) {
					return;
				}

				var isEmail = false;
				var isIp    = false;

				if ( typed.indexOf( '@' ) !== -1 ) {
					isEmail = true;
				} else if ( /^\d[\d.\*\/]+$/.test( typed ) && typed.indexOf( '.' ) !== -1 ) {
					isIp = true;
				} else if ( /^[0-9a-fA-F:\*\/]+$/.test( typed ) && ( typed.split( ':' ).length - 1 ) >= 2 ) {
					isIp = true;
				}

				if ( ! isEmail && ! isIp ) {
					errors.push( line );
				}
			}
		);

		return errors;
	}

	function showFieldError( $field, message ) {
		var $err = $field.siblings( '.simple-honeypot-cf7-field-error' );
		if ( ! $err.length ) {
			$err = $( '<p class="simple-honeypot-cf7-field-error"></p>' );
			$field.after( $err );
		}
		$err.text( message ).addClass( 'is-visible' );
		$field.addClass( 'simple-honeypot-cf7-field-invalid' );
	}

	function clearFieldError( $field ) {
		$field.closest( 'td' ).find( '.simple-honeypot-cf7-field-error' ).removeClass( 'is-visible' );
		$field.removeClass( 'simple-honeypot-cf7-field-invalid' );
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

			// Clear errors on input.
			$form.on(
				'input change',
				'input[type="number"], input[type="text"], textarea',
				function () {
					clearFieldError( $( this ) );
				}
			);

			$form.on(
				'submit',
				function ( e ) {
					var valid      = true;
					var $submitter = $( document.activeElement );
					var isImport   = $submitter.is( '#simple-honeypot-cf7-import-btn' );

					// Guard: import with no file.
					if ( isImport && ( ! $importFile.length || ! $importFile[ 0 ].files.length ) ) {
						showFieldError( $importFile.next( 'label' ), simpleHoneypotCf7.selectFile );
						valid = false;
					}

					$form.find( 'input[type="number"]' ).each(
						function () {
							var $input = $( this );
							var val    = $input.val();

							if ( '' === val ) {
									return;
							}

							var num   = parseInt( val, 10 );
							var min   = $input.attr( 'min' );
							var max   = $input.attr( 'max' );
							var label = $input.closest( 'tr' ).find( 'label' ).text();

							if ( min !== undefined && num < parseInt( min, 10 ) ) {
								showFieldError( $input, label + ': ' + simpleHoneypotCf7.valueTooLow.replace( '%s', min ) );
								valid = false;
							} else if ( max !== undefined && num > parseInt( max, 10 ) ) {
								showFieldError( $input, label + ': ' + simpleHoneypotCf7.valueTooHigh.replace( '%s', max ) );
								valid = false;
							}
						}
					);

					// Validate rules textarea.
					var $rules = $form.find( '.simple-honeypot-cf7-rules' );
					if ( $rules.length && ! $rules.prop( 'disabled' ) ) {
						var errors = validateRules( $rules.val() );
						if ( errors.length ) {
							showFieldError( $rules, simpleHoneypotCf7.invalidRules.replace( '%s', errors.join( ', ' ) ) );
							valid = false;
						}
					}

					if ( ! valid ) {
						e.preventDefault();
						formDirty = false;
					} else {
						formDirty = false;
						if ( $submitter.is( 'input[type="submit"], button[type="submit"]' ) && ! isImport ) {
							$submitter.prop( 'disabled', true );
						}
					}
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

			// Import: enable button only when file selected.
			var $importFile       = $( '#simple-honeypot-cf7-import-file' );
			var $importBtn        = $( '#simple-honeypot-cf7-import-btn' );
			var $importLabel      = $importFile.next( 'label' );
			var importDefaultText = $importLabel.text();

			if ( $importFile.length && $importBtn.length ) {
				$importFile.on(
					'change',
					function () {
						var hasFile = this.files.length > 0;
						$importBtn.prop( 'disabled', ! hasFile );
						$importLabel.text( hasFile ? this.files[ 0 ].name : importDefaultText );
						$importLabel.attr( 'title', hasFile ? this.files[ 0 ].name : '' );
						clearFieldError( $importFile );
					}
				);
			}

			// Confirm modal for destructive actions.
			var $pendingButton = null;

			$form.on(
				'click',
				'[data-confirm]',
				function ( e ) {
					e.preventDefault();
					$pendingButton = $( this );
					var $dialog    = $( '#simple-honeypot-cf7-confirm-dialog' );
					$dialog.find( '.simple-honeypot-cf7-confirm-message' ).text( $pendingButton.data( 'confirm' ) );
					$dialog[ 0 ].showModal();
				}
			);

			$( document ).on(
				'click',
				'.simple-honeypot-cf7-confirm-yes',
				function () {
					var $dialog = $( '#simple-honeypot-cf7-confirm-dialog' );
					$dialog[ 0 ].close();
					if ( $pendingButton ) {
						var name  = $pendingButton.attr( 'name' );
						var value = $pendingButton.attr( 'value' );
						$form.append( $( '<input>', { type: 'hidden', name: name, value: value } ) );
						HTMLFormElement.prototype.submit.call( $form[0] );
						$pendingButton = null;
					}
				}
			);

			$( document ).on(
				'click',
				'.simple-honeypot-cf7-confirm-no',
				function () {
					$( '#simple-honeypot-cf7-confirm-dialog' )[ 0 ].close();
					$pendingButton = null;
				}
			);

			// Close on backdrop click.
			$( document ).on(
				'click',
				'.simple-honeypot-cf7-dialog-backdrop',
				function () {
					$( '#simple-honeypot-cf7-confirm-dialog' )[ 0 ].close();
					$pendingButton = null;
				}
			);
		}
	);
	// Confirm dialog for reset per-form settings (lives outside the guarded block).
	$( document ).on(
		'click',
		'.simple-honeypot-cf7-reset-form-settings[data-reset-message]',
		function ( e ) {
			e.preventDefault();
			var $button = $( this );
			var $dialog = $( '#simple-honeypot-cf7-confirm-dialog' );
			$dialog.find( '.simple-honeypot-cf7-confirm-message' ).text( $button.data( 'reset-message' ) );
			$dialog.attr( 'data-confirm-href', $button.attr( 'href' ) );
			$dialog[ 0 ].showModal();
		}
	);

	// Confirm dialog for purge events button.
	$( document ).on(
		'click',
		'.simple-honeypot-cf7-purge-events-btn[data-confirm]',
		function ( e ) {
			e.preventDefault();
			var $button = $( this );
			var $dialog = $( '#simple-honeypot-cf7-confirm-dialog' );
			var days    = $( '#shp4cf7_purge_days' ).val() || 90;
			var href    = $button.attr( 'href' ) + '&days=' + parseInt( days, 10 );
			$dialog.find( '.simple-honeypot-cf7-confirm-message' ).text( $button.data( 'confirm' ) );
			$dialog.attr( 'data-confirm-href', href );
			$dialog[ 0 ].showModal();
		}
	);

	$( document ).on(
		'click',
		'.simple-honeypot-cf7-confirm-yes',
		function () {
			var $dialog = $( '#simple-honeypot-cf7-confirm-dialog' );
			var href    = $dialog.attr( 'data-confirm-href' );
			if ( href ) {
				$dialog.removeAttr( 'data-confirm-href' );
				$dialog[ 0 ].close();
				window.location.href = href;
			}
		}
	);

	$( document ).on(
		'click',
		'.simple-honeypot-cf7-confirm-no',
		function () {
			var $dialog = $( '#simple-honeypot-cf7-confirm-dialog' );
			if ( $dialog.attr( 'data-confirm-href' ) ) {
				$dialog.removeAttr( 'data-confirm-href' );
				$dialog[ 0 ].close();
			}
		}
	);

	$( window ).on(
		'beforeunload',
		function () {
			if ( formDirty ) {
				return simpleHoneypotCf7.unsavedChanges;
			}
		}
	);
}( jQuery ) );
