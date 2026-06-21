( function () {
	'use strict';

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

	async function fillPowInputs() {
		var inputs, i, len, challenge, parts, nonce;

		try {
			inputs = document.querySelectorAll( 'input[type="hidden"][name$="_pow"]' );
			len    = inputs.length;

			for ( i = 0; i < len; i++ ) {
				challenge = inputs[ i ].value;
				parts     = challenge.split( '.' );

				if ( parts.length !== 5 ) {
					continue;
				}

				nonce = await solvePow( challenge, parseInt( parts[ 1 ], 10 ) );

				if ( nonce < 0 ) {
					continue;
				}

				inputs[ i ].value = challenge + '.' + nonce;
			}
		} catch ( e ) {
			// Crypto failed silently — server-side validation will reject the submission.
		}

		powReady = true;
	}

	if ( typeof crypto === 'undefined' || typeof crypto.subtle === 'undefined' ) {
		return;
	}

	var powReady = false;

	function blockSubmit( e ) {
		if ( ! powReady ) {
			e.preventDefault();
			e.stopImmediatePropagation();
			return false;
		}
		return true;
	}

	function onReady() {
		fillPowInputs();

		var forms = document.querySelectorAll( '.wpcf7' ), i, len = forms.length;
		for ( i = 0; i < len; i++ ) {
			forms[ i ].addEventListener( 'submit', blockSubmit, true );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', onReady );
	} else {
		onReady();
	}
}() );
