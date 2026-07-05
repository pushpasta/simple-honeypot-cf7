( function () {
	'use strict';

	var states = new WeakMap();

	function countLeadingZeroBits( hex ) {
		var bits = 0, i, byte, nibble, hexLen = hex.length;

		for ( i = 0; i < hexLen; i++ ) {
			byte = parseInt( hex[ i ], 16 );

			if ( byte === 0 ) {
				bits += 4;
				continue;
			}

			nibble = byte;

			if ( nibble & 8 ) {
				return bits; }
			if ( nibble & 4 ) {
				return bits + 1; }
			if ( nibble & 2 ) {
				return bits + 2; }
			if ( nibble & 1 ) {
				return bits + 3; }

			return bits + 4;
		}

		return bits;
	}

	function hexFromBuffer( buffer ) {
		var view  = new DataView( buffer ),
			hexes = [],
			i, val;

		for ( i = 0; i < view.byteLength; i++ ) {
			val = view.getUint8( i ).toString( 16 );
			hexes.push( val.length === 1 ? '0' + val : val );
		}

		return hexes.join( '' );
	}

	function sha256( message ) {
		var encoder = new TextEncoder();
		return crypto.subtle.digest( 'SHA-256', encoder.encode( message ) );
	}

	async function solvePow( challenge, bits ) {
		var nonce = 0, buffer, hash, MAX_NONCE = 10000000;

		if ( typeof crypto === 'undefined' || typeof crypto.subtle === 'undefined' ) {
			return -1;
		}

		while ( nonce < MAX_NONCE ) {
			buffer = await sha256( challenge + '.' + nonce );
			hash   = hexFromBuffer( buffer );

			if ( countLeadingZeroBits( hash ) >= bits ) {
				return nonce;
			}

			nonce++;
		}

		return -1;
	}

	async function solvePowField( powField ) {
		var challenge = powField.value,
			parts     = challenge.split( '.' ),
			nonce;

		if ( parts.length !== 5 ) {
			return false;
		}

		nonce = await solvePow( challenge, parseInt( parts[ 1 ], 10 ) );

		if ( nonce < 0 ) {
			return false;
		}

		powField.value = challenge + '.' + nonce;
		return true;
	}

	function getSecurityField( form, fieldName ) {
		var field = form.elements.namedItem( fieldName ),
			container;

		if ( field ) {
			return field;
		}

		container = form.querySelector( '.hidden-fields-container' );

		if ( ! container ) {
			return null;
		}

		field       = document.createElement( 'input' );
		field.type  = 'hidden';
		field.name  = fieldName;
		field.value = '';
		container.appendChild( field );

		return field;
	}

	function refreshHoneypotNames( form, response ) {
		var wraps     = form.querySelectorAll( '.wpcf7-form-control-wrap[data-name]' ),
			fields    = [],
			nameCount = response.honeypot_names.length,
			wrapCount = wraps.length,
			i, j, input;

		for ( i = 0; i < nameCount; i++ ) {
			input = null;

			for ( j = 0; j < wrapCount; j++ ) {
				if ( wraps[ j ].getAttribute( 'data-name' ) === response.honeypot_names[ i ] ) {
					input = wraps[ j ].querySelector( 'input, textarea' );
					break;
				}
			}

			if ( ! input || ! response.dynamic_names[ i ] ) {
				return false;
			}

			fields.push( input );
		}

		for ( i = 0; i < nameCount; i++ ) {
			fields[ i ].name = response.dynamic_names[ i ];
		}

		return nameCount > 0;
	}

	async function populateSecurityFields( form, response ) {
		var tokenField, powField;

		if ( ! response.token || ! response.token_field || ! refreshHoneypotNames( form, response ) ) {
			return false;
		}

		tokenField = getSecurityField( form, response.token_field );

		if ( ! tokenField ) {
			return false;
		}

		tokenField.value = response.token;

		if ( response.pow ) {
			powField = getSecurityField( form, response.pow_field );

			if ( ! powField ) {
				return false;
			}

			powField.value = response.pow;

			if ( ! await solvePowField( powField ) ) {
				return false;
			}
		}

		return true;
	}

	function prepareForm( form ) {
		var formIdInput = form.querySelector( 'input[name="_wpcf7"]' ),
			state       = states.get( form ),
			now         = Date.now(),
			data;

		if ( ! formIdInput || typeof shp4cf7 === 'undefined' || ! shp4cf7.ajaxUrl ) {
			return Promise.reject();
		}

		if ( state && state.ready && state.readyUntil > now ) {
			return Promise.resolve( true );
		}

		if ( state && state.ready ) {
			state.ready   = false;
			state.promise = null;
		}

		if ( state && state.promise ) {
			return state.promise;
		}

		state = {
			promise:   null,
			ready:     false,
			readyUntil: 0,
		};
		states.set( form, state );

		data = new FormData();
		data.append( 'action', 'shp4cf7_get_token' );
		data.append( 'form_id', formIdInput.value );

		state.promise = fetch(
			shp4cf7.ajaxUrl,
			{
				method: 'POST',
				body: data,
				credentials: 'same-origin',
			}
		)
			.then(
				function ( response ) {
					if ( ! response.ok ) {
						throw new Error();
					}

					return response.json();
				}
			)
			.then(
				async function ( response ) {
					if ( ! response.success || ! await populateSecurityFields( form, response.data ) ) {
						throw new Error();
					}

					state.ready      = true;
					state.readyUntil = Date.now() + ( response.data.expires_in * 1000 );
					return true;
				}
			)
			.catch(
				function () {
					state.promise = null;
					throw new Error();
				}
			);

		return state.promise;
	}

	document.addEventListener(
		'submit',
		function ( event ) {
			var form = event.target.closest( '.wpcf7 form' ),
				state, submitter;

			if ( ! form ) {
				return;
			}

			state = states.get( form );

			if ( state && state.ready && state.readyUntil > Date.now() ) {
				return;
			}

			event.preventDefault();
			event.stopImmediatePropagation();
			submitter = event.submitter;

			prepareForm( form )
				.then(
					function () {
						form.requestSubmit( submitter );
					}
				)
				.catch(
					function () {
						// Keep the form available so another submit can retry.
					}
				);
		},
		true
	);

	document.addEventListener(
		'focus',
		function ( event ) {
			var form = event.target.closest( '.wpcf7 form' );

			if ( form ) {
				prepareForm( form ).catch(
					function () {
						// A later interaction or submission will retry.
					}
				);
			}
		},
		true
	);
}() );
