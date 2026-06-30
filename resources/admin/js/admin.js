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

			// Select tag generator output on focus.
			$( document ).on(
				'focus',
				'.insert-box input.tag.code',
				function () {
					$( this ).select();
				}
			);

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

			// Confirm dialog system.
			var $pendingTrigger = null;
			var $confirmDialog  = null;
			var countdownTimer  = null;

			function getConfirmDialog() {
				if ( $confirmDialog ) {
					return $confirmDialog;
				}

				$confirmDialog = $(
					'<dialog class="simple-honeypot-cf7-dialog">' +
						'<div class="simple-honeypot-cf7-dialog-inner">' +
							'<div class="simple-honeypot-cf7-confirm-header">' +
								'<span class="dashicons dashicons-warning"></span>' +
								'<strong>' + simpleHoneypotCf7.confirmTitle + '</strong>' +
							'</div>' +
							'<p class="simple-honeypot-cf7-confirm-message"></p>' +
							'<div class="simple-honeypot-cf7-dialog-actions">' +
								'<button type="button" class="button button-primary simple-honeypot-cf7-confirm-yes" disabled>' + simpleHoneypotCf7.confirmYes + '</button>' +
								'<button type="button" class="button simple-honeypot-cf7-confirm-no">' + simpleHoneypotCf7.confirmNo + '</button>' +
							'</div>' +
						'</div>' +
					'</dialog>'
				);

				$( 'body' ).append( $confirmDialog );
				return $confirmDialog;
			}

			function openConfirmDialog( $trigger ) {
				var $dialog  = getConfirmDialog();
				var isDanger = $trigger.data( 'confirm-danger' ) !== undefined;
				var $header  = $dialog.find( '.simple-honeypot-cf7-confirm-header' );
				var $yes     = $dialog.find( '.simple-honeypot-cf7-confirm-yes' );

				var message   = $trigger.data( 'confirm' );
				var daysInput = $trigger.data( 'confirm-days' );

				if ( daysInput ) {
					var daysValue = $( '#' + daysInput ).val() || '90';
					message       = message.replace( '%d', daysValue );
					$dialog.find( '.simple-honeypot-cf7-confirm-message' ).html( message );
				} else {
					$dialog.find( '.simple-honeypot-cf7-confirm-message' ).text( message );
				}

				if ( isDanger ) {
					$header.show();
					$yes.prop( 'disabled', true );
					startCountdown( $yes, 3 );
				} else {
					$header.hide();
					$yes.prop( 'disabled', false );
				}

				$pendingTrigger = $trigger;
				$dialog[ 0 ].showModal();
			}

			function startCountdown( $button, seconds ) {
				var remaining = seconds;
				clearInterval( countdownTimer );
				$button.text( simpleHoneypotCf7.confirmYes + ' (' + remaining + 's)' );

				countdownTimer = setInterval(
					function () {
						remaining--;
						if ( remaining <= 0 ) {
							clearInterval( countdownTimer );
							$button.text( simpleHoneypotCf7.confirmYes ).prop( 'disabled', false );
						} else {
							$button.text( simpleHoneypotCf7.confirmYes + ' (' + remaining + 's)' );
						}
					},
					1000
				);
			}

			function closeConfirmDialog() {
				var $dialog = getConfirmDialog();
				$dialog[ 0 ].close();
				clearInterval( countdownTimer );
				$pendingTrigger = null;
			}

			// Open dialog for elements with data-confirm.
			$( document ).on(
				'click',
				'[data-confirm]',
				function ( e ) {
					e.preventDefault();
					openConfirmDialog( $( this ) );
				}
			);

			// Confirm action.
			$( document ).on(
				'click',
				'.simple-honeypot-cf7-confirm-yes',
				function () {
					if ( $( this ).prop( 'disabled' ) || ! $pendingTrigger ) {
						return;
					}

					var action = $pendingTrigger.data( 'action' );

					if ( action ) {
						// REST API action (danger zone).
						var payload = { action: action };

						if ( 'purge_events' === action ) {
							payload.days = parseInt( $( '#shp4cf7_purge_days' ).val(), 10 ) || 90;
						}

						closeConfirmDialog();

						$.ajax(
							{
								url: simpleHoneypotCf7.restUrl,
								method: 'POST',
								contentType: 'application/json',
								beforeSend: function ( xhr ) {
									xhr.setRequestHeader( 'X-WP-Nonce', simpleHoneypotCf7.restNonce );
								},
								data: JSON.stringify( payload ),
								dataType: 'json'
							}
						).done(
							function () {
								window.location.href = simpleHoneypotCf7.tabUrl + '&updated=' + action.replace( 'reset_', '' ).replace( '_', '-' );
							}
						).fail(
							function () {
								window.location.href = simpleHoneypotCf7.tabUrl + '&updated=action-failed';
							}
						);
					} else if ( $pendingTrigger.attr( 'href' ) ) {
						// Direct navigation (e.g. purge link).
						var href = $pendingTrigger.attr( 'href' );
						closeConfirmDialog();
						window.location.href = href;
					} else {
						closeConfirmDialog();
					}
				}
			);

			// Cancel / close.
			$( document ).on(
				'click',
				'.simple-honeypot-cf7-confirm-no',
				function () {
					closeConfirmDialog();
				}
			);

			$( document ).on(
				'click',
				'.simple-honeypot-cf7-dialog-backdrop',
				function () {
					closeConfirmDialog();
				}
			);
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
