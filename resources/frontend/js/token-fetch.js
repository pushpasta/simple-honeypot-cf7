( function () {
	'use strict';

	var fetched = {};
	var ready   = {};

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
		if ( typeof crypto === 'undefined' || typeof crypto.subtle === 'undefined' ) {
			return -1;
		}

		var nonce = 0, buffer, hash, MAX_NONCE = 10000000;

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
			return;
		}

		nonce = await solvePow( challenge, parseInt( parts[ 1 ], 10 ) );

		if ( nonce >= 0 ) {
			powField.value = challenge + '.' + nonce;
		}
	}

	function fetchSecurityFields( form ) {
		var formIdInput = form.querySelector( 'input[name="wpcf7_contact_form_id"]' );
		var tokenField  = form.querySelector( '.shp4cf7-token-field' );

		if ( ! formIdInput || ! tokenField || tokenField.value ) {
			return;
		}

		var formId = formIdInput.value;

		if ( fetched[ formId ] ) {
			return;
		}

		fetched[ formId ] = true;

		var data = new FormData();
		data.append( 'action', 'shp4cf7_get_token' );
		data.append( 'form_id', formId );
		data.append( 'nonce', shp4cf7.nonce );

		fetch(
			shp4cf7.ajaxUrl,
			{
				method: 'POST',
				body: data,
				credentials: 'same-origin',
			}
		)
			.then(
				function ( r ) {
					return r.json();
				}
			)
			.then(
				async function ( r ) {
					if ( ! r.success ) {
						return;
					}

					tokenField.value = r.data.token;

					if ( r.data.pow ) {
						var powField = form.querySelector( '.shp4cf7-pow-field' );

						if ( powField ) {
							powField.value = r.data.pow;
							await solvePowField( powField );
						}
					}

					ready[ formId ] = true;
				}
			);
	}

	document.addEventListener(
		'submit',
		function ( e ) {
			var form = e.target.closest( '.wpcf7 form' );

			if ( form ) {
				var formIdInput = form.querySelector( 'input[name="wpcf7_contact_form_id"]' );

				if ( formIdInput && ! ready[ formIdInput.value ] ) {
					e.preventDefault();
				}
			}
		},
		true
	);

	document.addEventListener(
		'focus',
		function ( e ) {
			var form = e.target.closest( '.wpcf7 form' );

			if ( form ) {
				fetchSecurityFields( form );
			}
		},
		true
	);
}() );
