( function ( $ ) {
	'use strict';

	let initialData = '';
	let formDirty   = false;

	function syncDirty() {
		const $form = $( '.shp4cf7-admin form' );
		if ( ! $form.length ) {
			return '';
		}
		return $form.serialize();
	}

	function validateRules( value ) {
		const lines  = value.split( /\r?\n/ );
		const errors = [];

		$.each(
			lines,
			function ( i, line ) {
				line = $.trim( line );

				if ( '' === line || 0 === line.indexOf( '#' ) ) {
					return;
				}

				let typed = line;

				if ( '' === typed ) {
					return;
				}

				let isEmail = false;
				let isIp    = false;

				if ( typed.indexOf( '@' ) !== -1 ) {
					isEmail = true;
				} else if ( /^[\d\*][\d.\*\/]+$/.test( typed ) && typed.indexOf( '.' ) !== -1 ) {
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
		let $err = $field.siblings( '.shp4cf7-field-error' );
		if ( ! $err.length ) {
			$err = $( '<p class="shp4cf7-field-error"></p>' );
			$field.after( $err );
		}
		$err.text( message ).addClass( 'is-visible' );
		$field.addClass( 'shp4cf7-field-invalid' );
	}

	function clearFieldError( $field ) {
		$field.closest( 'td' ).find( '.shp4cf7-field-error' ).removeClass( 'is-visible' );
		$field.removeClass( 'shp4cf7-field-invalid' );
	}

	$(
		function () {

			// Confirm dialog system — registered on all admin pages so
			// elements outside the plugin settings form (e.g. the CF7
			// editor "Restore to defaults" link) still receive a
			// confirmation dialog before navigating away.
			let $pendingTrigger = null;
			let $confirmDialog  = null;
			let countdownTimer  = null;

			function getConfirmDialog() {
				if ( $confirmDialog ) {
					return $confirmDialog;
				}

				$confirmDialog = $(
					'<dialog class="shp4cf7-dialog">' +
						'<div class="shp4cf7-dialog-inner">' +
							'<div class="shp4cf7-confirm-header">' +
								'<span class="dashicons dashicons-warning"></span>' +
								'<strong>' + simpleHoneypotCf7.confirmTitle + '</strong>' +
							'</div>' +
							'<p class="shp4cf7-confirm-message"></p>' +
							'<div class="shp4cf7-dialog-actions">' +
								'<button type="button" class="button button-primary shp4cf7-confirm-yes" disabled>' + simpleHoneypotCf7.confirmYes + '</button>' +
								'<button type="button" class="button shp4cf7-confirm-no">' + simpleHoneypotCf7.confirmNo + '</button>' +
							'</div>' +
						'</div>' +
					'</dialog>'
				);

				$( 'body' ).append( $confirmDialog );
				return $confirmDialog;
			}

			function openConfirmDialog( $trigger ) {
				const $dialog  = getConfirmDialog();
				const isDanger = $trigger.data( 'confirm-danger' ) !== undefined;
				const $header  = $dialog.find( '.shp4cf7-confirm-header' );
				const $yes     = $dialog.find( '.shp4cf7-confirm-yes' );
				const $message = $dialog.find( '.shp4cf7-confirm-message' );

				const message     = $trigger.data( 'confirm' );
				const daysInput   = $trigger.data( 'confirm-days' );

				if ( daysInput ) {
					const daysValue = $( '#' + daysInput ).val() || '90';
					$message.text( message.replace( '%d', daysValue ) );
				} else {
					$message.text( message );
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
				let remaining = seconds;
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
				const $dialog = getConfirmDialog();
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
				'.shp4cf7-confirm-yes',
				function () {
					if ( $( this ).prop( 'disabled' ) || ! $pendingTrigger ) {
						return;
					}

					const action = $pendingTrigger.data( 'action' );

					if ( action ) {
						// REST API action (danger zone).
						const payload = { action: action };

						if ( 'purge_events' === action ) {
							payload.days = parseInt( $( '#shp4cf7_purge_days' ).val(), 10 ) || 90;
						}

							closeConfirmDialog();

							const actionMap = {
								'reset_stats':         'stats-reset',
								'reset_settings':      'settings-reset',
								'purge_events':        'purge-events',
								'force_update_check':  'update-check-cleared'
						};

							const redirectKey = actionMap[ action ] || action;

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
									window.location.href = simpleHoneypotCf7.tabUrl + '&updated=' + redirectKey;
								}
							).fail(
								function () {
									window.location.href = simpleHoneypotCf7.tabUrl + '&updated=action-failed';
								}
							);
					} else if ( $pendingTrigger.attr( 'href' ) ) {
						// Direct navigation (e.g. purge link).
						const href = $pendingTrigger.attr( 'href' );
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
				'.shp4cf7-confirm-no',
				function () {
					closeConfirmDialog();
				}
			);

			// Recalculate stats button (Reports tab).
			$( document ).on(
				'click',
				'#shp4cf7-recalculate-stats',
				function () {
					const $btn     = $( this );
					const $spinner = $btn.find( '.dashicons-update' );

					$btn.prop( 'disabled', true );
					$spinner.removeClass( 'hidden' );

					$.ajax(
						{
							url: simpleHoneypotCf7.restUrl,
							method: 'POST',
							contentType: 'application/json',
							beforeSend: function ( xhr ) {
								xhr.setRequestHeader( 'X-WP-Nonce', simpleHoneypotCf7.restNonce );
							},
							data: JSON.stringify( { action: 'recalculate_stats' } ),
							dataType: 'json'
						}
					).done(
						function () {
							window.location.reload();
						}
					).fail(
						function () {
							$spinner.addClass( 'hidden' );
							$btn.prop( 'disabled', false );
						}
					);
				}
			);

			// ── Plugin settings form (Simple Honeypot admin page only) ──

			const $form = $( '.shp4cf7-admin form' );

			if ( ! $form.length ) {
					return;
			}

			// Import: enable button only when file selected.
			const $importFile       = $( '#shp4cf7-import-file' );
			const $importBtn        = $( '#shp4cf7-import-btn' );
			const $importLabel      = $importFile.next( 'label' );
			const importDefaultText = $importLabel.text();

			if ( $importFile.length && $importBtn.length ) {
				$importFile.on(
					'change',
					function () {
						const hasFile = this.files.length > 0;
						$importBtn.prop( 'disabled', ! hasFile );
						$importLabel.text( hasFile ? this.files[ 0 ].name : importDefaultText );
						$importLabel.attr( 'title', hasFile ? this.files[ 0 ].name : '' );
						clearFieldError( $importFile );
					}
				);
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
					let valid        = true;
					const $submitter = $( document.activeElement );
					const isImport   = $submitter.is( '#shp4cf7-import-btn' );

					// Guard: import with no file.
					if ( isImport && ( ! $importFile.length || ! $importFile[ 0 ].files.length ) ) {
						showFieldError( $importFile.next( 'label' ), simpleHoneypotCf7.selectFile );
						valid = false;
					}

					$form.find( 'input[type="number"]' ).each(
						function () {
							const $input = $( this );
							const val    = $input.val();

							if ( '' === val ) {
									return;
							}

							const num   = parseInt( val, 10 );
							const min   = $input.attr( 'min' );
							const max   = $input.attr( 'max' );
							const label = $input.closest( 'tr' ).find( 'label' ).text();

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
					const $rules = $form.find( '.shp4cf7-rules' );
					if ( $rules.length && ! $rules.prop( 'disabled' ) ) {
						const errors = validateRules( $rules.val() );
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
				'.shp4cf7-custom-rules-toggle input',
				function () {
					const $ta = $( this ).closest( '.shp4cf7-custom-rules-group' )
					.find( '.shp4cf7-rules' );
					$ta.prop( 'disabled', ! this.checked ).toggleClass( 'shp4cf7-rules-disabled', ! this.checked );
				}
			);

			// Apply initial disabled state on page load.
			$form.find( '.shp4cf7-custom-rules-toggle input:not(:checked)' ).trigger( 'change' );

	}
	);

	// Settings tab: live outputs and badges for range settings. The
	// minimum-time max follows the token lifetime so it can never exceed
	// the current lifetime.
	const honeypotRange = document.getElementById( 'honeypot_value_max_length' );
	const honeypotOutput = document.getElementById( 'honeypot-value-max-length-value' );

	if ( honeypotRange && honeypotOutput ) {
		honeypotRange.addEventListener(
			'input',
			function () {
				honeypotOutput.textContent = this.value;
			}
		);
	}

	const tokenRange = document.getElementById( 'max_age_minutes' );
	const tokenOutput = document.getElementById( 'max-age-minutes-value' );
	const tokenLabel = document.getElementById( 'max-age-minutes-label' );
	const minTimeInput = document.getElementById( 'min_time_seconds' );
	const minTimeMax = minTimeInput ? parseInt( minTimeInput.getAttribute( 'data-max-min-time' ), 10 ) : 3600;
	const tokenLabels = [
		{ min: 10, max: 10, text: 'Strict', css: 'inactive' },
		{ min: 15, max: 20, text: 'Recommended', css: 'active' },
		{ min: 25, max: 35, text: 'Moderate', css: 'info' },
		{ min: 40, max: 60, text: 'Relaxed', css: 'inherited' }
	];

	function updateToken() {
		const val = parseInt( this.value, 10 );

		tokenOutput.textContent = val;

		if ( minTimeInput ) {
			minTimeInput.setAttribute( 'max', Math.min( minTimeMax, val * 60 ) );
		}

		for ( let i = 0; i < tokenLabels.length; i++ ) {
			if ( val >= tokenLabels[ i ].min && val <= tokenLabels[ i ].max ) {
				tokenLabel.textContent = tokenLabels[ i ].text;
				tokenLabel.className = 'shp4cf7-badge shp4cf7-badge--' + tokenLabels[ i ].css;
				break;
			}
		}
	}

	if ( tokenRange && tokenOutput && tokenLabel ) {
		tokenRange.addEventListener( 'input', updateToken );
		updateToken.call( tokenRange );
	}

	const rateLimitRange = document.getElementById( 'token_rate_limit' );
	const rateLimitOutput = document.getElementById( 'token-rate-limit-value' );
	const rateLimitLabel = document.getElementById( 'token-rate-limit-label' );
	const rateLimitLabels = [
		{ min: 0, max: 0, text: 'Disabled', css: 'inactive' },
		{ min: 5, max: 5, text: 'Strict', css: 'inherited' },
		{ min: 10, max: 15, text: 'Recommended', css: 'active' },
		{ min: 20, max: 25, text: 'Moderate', css: 'info' },
		{ min: 30, max: 30, text: 'Relaxed', css: 'inherited' }
	];

	function updateRateLimit() {
		const val = parseInt( this.value, 10 );

		rateLimitOutput.textContent = val;

		for ( let i = 0; i < rateLimitLabels.length; i++ ) {
			if ( val >= rateLimitLabels[ i ].min && val <= rateLimitLabels[ i ].max ) {
				rateLimitLabel.textContent = rateLimitLabels[ i ].text;
				rateLimitLabel.className = 'shp4cf7-badge shp4cf7-badge--' + rateLimitLabels[ i ].css;
				break;
			}
		}
	}

	if ( rateLimitRange && rateLimitOutput && rateLimitLabel ) {
		rateLimitRange.addEventListener( 'input', updateRateLimit );
		updateRateLimit.call( rateLimitRange );
	}

	const powRange = document.getElementById( 'pow_complexity' );
	const powOutput = document.getElementById( 'pow-complexity-value' );
	const powLabel = document.getElementById( 'pow-complexity-label' );
	const powLabels = [
		{ min: 5, max: 5, text: 'Light', css: 'inactive' },
		{ min: 10, max: 10, text: 'Moderate', css: 'info' },
		{ min: 15, max: 15, text: 'Recommended', css: 'active' },
		{ min: 20, max: 20, text: 'Strong', css: 'inherited' },
		{ min: 25, max: 30, text: 'May slow mobile', css: 'warning' }
	];

	function updatePow() {
		const val = parseInt( this.value, 10 );

		powOutput.textContent = val;

		for ( let i = 0; i < powLabels.length; i++ ) {
			if ( val >= powLabels[ i ].min && val <= powLabels[ i ].max ) {
				powLabel.textContent = powLabels[ i ].text;
				powLabel.className = 'shp4cf7-badge shp4cf7-badge--' + powLabels[ i ].css;
				break;
			}
		}
	}

	if ( powRange && powOutput && powLabel ) {
		powRange.addEventListener( 'input', updatePow );
		updatePow.call( powRange );
	}

	$( window ).on(
		'beforeunload',
		function () {
			if ( formDirty ) {
				return simpleHoneypotCf7.unsavedChanges;
			}
		}
	);
}( jQuery ) );
