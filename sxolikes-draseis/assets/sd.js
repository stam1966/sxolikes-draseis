/* Σχολικές Δράσεις — μετρητές χαρακτήρων και αντιγραφή κειμένων. */
( function () {
	'use strict';

	// Όπως στον server: κάθε αλλαγή γραμμής μετρά ένας χαρακτήρας.
	function len( text ) {
		return Array.from( text.replace( /\r\n/g, '\n' ) ).length;
	}

	document.querySelectorAll( 'textarea[data-limit]' ).forEach( function ( ta ) {
		var limit = parseInt( ta.getAttribute( 'data-limit' ), 10 );
		var out = document.createElement( 'span' );
		out.className = 'sd-counter';
		out.setAttribute( 'aria-live', 'polite' );
		ta.insertAdjacentElement( 'afterend', out );
		function update() {
			var n = len( ta.value );
			out.textContent = n + ' / ' + limit + ' χαρακτήρες';
			out.classList.toggle( 'sd-over', n > limit );
		}
		ta.addEventListener( 'input', update );
		update();
	} );

	// Έντυπο 1Α: η υποθεματική αλλάζει με τη θεματική (ορίζεται από την τάξη).
	document.querySelectorAll( 'select[data-subthemes]' ).forEach( function ( sel ) {
		var map = JSON.parse( sel.getAttribute( 'data-subthemes' ) || '{}' );
		var form = sel.closest( 'form' );
		var out = form ? form.querySelector( '[data-subtheme-out]' ) : null;
		if ( ! out ) {
			return;
		}
		sel.addEventListener( 'change', function () {
			out.textContent = map[ sel.value ] || '—';
		} );
	} );

	// Έντυπο 2: ζωντανό άθροισμα ωρών (μάθημα / στάδια).
	var sums = {};
	document.querySelectorAll( 'input[data-sum]' ).forEach( function ( inp ) {
		var key = inp.getAttribute( 'data-sum' );
		( sums[ key ] = sums[ key ] || [] ).push( inp );
	} );
	Object.keys( sums ).forEach( function ( key ) {
		var out = document.getElementById( key );
		function update() {
			var total = 0;
			sums[ key ].forEach( function ( i ) {
				total += parseInt( i.value, 10 ) || 0;
			} );
			if ( out ) {
				out.textContent = total;
				out.classList.toggle( 'sd-over', total !== 10 );
			}
		}
		sums[ key ].forEach( function ( i ) {
			i.addEventListener( 'input', update );
		} );
		update();
	} );

	document.querySelectorAll( '[data-copy]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var box = btn.closest( '.sd-copy' );
			var ta = box ? box.querySelector( 'textarea' ) : null;
			if ( ! ta ) {
				return;
			}
			function done() {
				var old = btn.textContent;
				btn.textContent = 'Αντιγράφηκε';
				setTimeout( function () {
					btn.textContent = old;
				}, 1500 );
			}
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( ta.value ).then( done );
			} else {
				ta.select();
				document.execCommand( 'copy' );
				done();
			}
		} );
	} );
}() );
