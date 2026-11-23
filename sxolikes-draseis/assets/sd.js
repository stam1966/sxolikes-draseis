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

	/* Έντυπο 3: εμφανίζεται μόνο το μέρος (Α ή Β) που επιλέχθηκε. */
	document.querySelectorAll( '[data-sd-req]' ).forEach( function ( form ) {
		function sync() {
			var on = form.querySelector( 'input[name="part"]:checked' );
			var part = on ? on.value : 'A';
			form.querySelectorAll( '[data-part]' ).forEach( function ( sec ) {
				sec.hidden = sec.getAttribute( 'data-part' ) !== part;
			} );
		}
		form.querySelectorAll( 'input[name="part"]' ).forEach( function ( r ) {
			r.addEventListener( 'change', sync );
		} );
		sync();
	} );
}() );

/* Προειδοποίηση για μη αποθηκευμένες αλλαγές σε φόρμες της εφαρμογής. */
( function () {
	'use strict';
	var dirty = null;
	document.querySelectorAll( 'form.sd-form' ).forEach( function ( form ) {
		if ( form.hasAttribute( 'data-autosave' ) ) {
			return; // αποθηκεύεται μόνη της
		}
		// Μόνο φόρμες με πεδία κειμένου (όχι φόρμες-κουμπιά ενεργειών).
		if ( ! form.querySelector( 'textarea, input[type="text"], input:not([type]), input[type="date"], input[type="number"]' ) ) {
			return;
		}
		var submit = form.querySelector( 'button[type="submit"], button:not([type])' );
		// Φόρμες ελέγχου (κουμπιά με απόφαση): χωρίς κουμπί γρήγορης αποθήκευσης,
		// για να μη σταλεί κατά λάθος απόφαση· μένει μόνο η προειδοποίηση.
		if ( submit && submit.name ) {
			submit = null;
		}
		function mark( e ) {
			if ( e && e.target && e.target.closest && e.target.closest( '.sd-copy' ) ) {
				return; // τα κουτιά των prompts δεν είναι στοιχεία της φόρμας
			}
			if ( dirty === form ) {
				return;
			}
			dirty = form;
			if ( submit && ! form.querySelector( '.sd-dirty-bar' ) ) {
				var bar = document.createElement( 'div' );
				bar.className = 'sd-dirty-bar';
				bar.setAttribute( 'role', 'status' );
				var txt = document.createElement( 'span' );
				txt.textContent = 'Έχετε αλλαγές που δεν αποθηκεύτηκαν.';
				var btn = document.createElement( 'button' );
				btn.type = 'submit';
				btn.className = 'sd-button sd-small';
				btn.textContent = 'Αποθήκευση';
				bar.appendChild( txt );
				bar.appendChild( btn );
				form.appendChild( bar );
			}
		}
		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );
		form.addEventListener( 'submit', function () {
			dirty = null;
		} );
	} );
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( dirty ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );
}() );

/* Κουμπί ✨ σε κάθε πεδίο κειμένου: πρόταση AI (με κλειδί API) ή έτοιμο prompt για δωρεάν AI. */
( function () {
	'use strict';
	var node = document.getElementById( 'sd-ai-cfg' );
	if ( ! node ) {
		return;
	}
	var cfg;
	try {
		cfg = JSON.parse( node.textContent );
	} catch ( e ) {
		return;
	}

	function baseName( name ) {
		return ( name || '' ).replace( /\[.*$/, '' );
	}
	function skipped( el ) {
		return ! el.name || el.hasAttribute( 'data-noai' ) || cfg.skip.indexOf( baseName( el.name ) ) !== -1;
	}
	// Η ετικέτα ενός πεδίου: το κείμενο του <label> χωρίς το ίδιο το πεδίο, ή η επικεφαλίδα της γραμμής πίνακα.
	function labelOf( el ) {
		var lab = el.closest( 'label' );
		var txt = '';
		if ( lab ) {
			lab.childNodes.forEach( function ( n ) {
				if ( n.nodeType === 3 || ( n.nodeType === 1 && ! n.matches( 'textarea, input, select, button, .sd-counter, .sd-ai-btn, .sd-ai-panel' ) ) ) {
					txt += ' ' + n.textContent;
				}
			} );
		}
		if ( ! txt.trim() ) {
			var row = el.closest( 'tr' );
			var th = row ? row.querySelector( 'th' ) : null;
			txt = th ? th.textContent : '';
		}
		if ( ! txt.trim() ) {
			var fs = el.closest( 'fieldset' );
			var lg = fs ? fs.querySelector( 'legend' ) : null;
			txt = lg ? lg.textContent : el.name;
		}
		// Η ενότητα όπου βρίσκεται το πεδίο (π.χ. θεματικός κύκλος, στόχος), για να μην μπερδεύονται ίδιες ετικέτες.
		var box = el.closest( 'fieldset, section' );
		var hd = box ? box.querySelector( 'legend, h3, h4' ) : null;
		var hs = hd ? hd.textContent.replace( /\s+/g, ' ' ).trim() : '';
		var ctx = hs && txt.indexOf( hs ) === -1 ? hs + ' – ' : '';
		return ( ctx + txt ).replace( /\s+/g, ' ' ).trim().slice( 0, 200 );
	}
	function others( form, target ) {
		var out = [];
		form.querySelectorAll( 'textarea, input[type="text"], input:not([type])' ).forEach( function ( el ) {
			if ( el === target || skipped( el ) || el.closest( '.sd-ai-panel' ) || ! el.value.trim() ) {
				return;
			}
			out.push( { name: el.name, label: labelOf( el ), value: el.value.slice( 0, 600 ) } );
		} );
		return out.slice( 0, 30 );
	}
	function request( el, mode ) {
		var body = new URLSearchParams();
		body.append( 'action', 'sd_ai_field' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'type', cfg.type );
		body.append( 'id', cfg.id );
		body.append( 'field', el.name );
		body.append( 'label', labelOf( el ) );
		body.append( 'current', el.value );
		body.append( 'limit', el.getAttribute( 'data-limit' ) || el.getAttribute( 'maxlength' ) || '' );
		body.append( 'mode', mode );
		body.append( 'others', JSON.stringify( others( el.form, el ) ) );
		return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json().catch( function () {
					return { success: false, data: { msg: 'Μη αναμενόμενη απάντηση (' + r.status + ').' } };
				} );
			} );
	}
	function btn( text, cls ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'sd-button sd-small ' + ( cls || '' );
		b.textContent = text;
		return b;
	}
	function copy( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		document.body.appendChild( ta );
		ta.select();
		document.execCommand( 'copy' );
		ta.remove();
		return Promise.resolve();
	}
	function setValue( el, v ) {
		el.value = v;
		el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	// Πάνελ «δωρεάν AI»: αντιγραφή του prompt και άνοιγμα της σελίδας που διαλέγει ο/η εκπαιδευτικός.
	function promptPanel( panel, prompt, note ) {
		panel.innerHTML = '';
		var p = document.createElement( 'p' );
		p.className = 'sd-help';
		p.textContent = ( note ? note + ' ' : '' ) + 'Πατήστε ένα εργαλείο: το prompt αντιγράφεται και ανοίγει νέα καρτέλα. Εκεί κάντε επικόλληση (Ctrl+V), και φέρτε την απάντηση εδώ στο πεδίο.';
		panel.appendChild( p );
		var row = document.createElement( 'div' );
		row.className = 'sd-ai-actions';
		cfg.sites.forEach( function ( s ) {
			var b = btn( 'Αντιγραφή και άνοιγμα ' + s.label );
			b.addEventListener( 'click', function () {
				copy( prompt ).then( function () {
					b.textContent = 'Αντιγράφηκε · ' + s.label;
				} );
				window.open( s.url, '_blank', 'noopener' );
			} );
			row.appendChild( b );
		} );
		var only = btn( 'Μόνο αντιγραφή', 'sd-quiet' );
		only.addEventListener( 'click', function () {
			copy( prompt ).then( function () {
				only.textContent = 'Αντιγράφηκε';
			} );
		} );
		row.appendChild( only );
		var close = btn( 'Κλείσιμο', 'sd-quiet' );
		close.addEventListener( 'click', function () {
			panel.remove();
		} );
		row.appendChild( close );
		panel.appendChild( row );
		var det = document.createElement( 'details' );
		det.innerHTML = '<summary>Δείτε το prompt</summary>';
		var ta = document.createElement( 'textarea' );
		ta.readOnly = true;
		ta.rows = 8;
		ta.value = prompt;
		det.appendChild( ta );
		panel.appendChild( det );
	}

	function answerPanel( panel, el, text, prompt ) {
		panel.innerHTML = '';
		var h = document.createElement( 'p' );
		h.className = 'sd-ai-title';
		h.textContent = 'Πρόταση AI · ελέγξτε την πριν τη χρησιμοποιήσετε';
		panel.appendChild( h );
		var box = document.createElement( 'div' );
		box.className = 'sd-ai-text';
		box.textContent = text;
		panel.appendChild( box );
		var limit = parseInt( el.getAttribute( 'data-limit' ) || '0', 10 );
		if ( limit && Array.from( text ).length > limit ) {
			var w = document.createElement( 'p' );
			w.className = 'sd-ai-warn';
			w.textContent = 'Ξεπερνά το όριο των ' + limit + ' χαρακτήρων· θα χρειαστεί συντόμευση.';
			panel.appendChild( w );
		}
		var row = document.createElement( 'div' );
		row.className = 'sd-ai-actions';
		var rep = btn( 'Αντικατάσταση' );
		rep.addEventListener( 'click', function () {
			setValue( el, text );
			panel.remove();
			el.focus();
		} );
		var add = btn( 'Προσθήκη στο τέλος', 'sd-quiet' );
		add.addEventListener( 'click', function () {
			setValue( el, el.value.trim() ? el.value.replace( /\s+$/, '' ) + '\n' + text : text );
			panel.remove();
			el.focus();
		} );
		var again = btn( 'Άλλη πρόταση', 'sd-quiet' );
		again.addEventListener( 'click', function () {
			run( el, panel );
		} );
		var close = btn( 'Απόρριψη', 'sd-quiet' );
		close.addEventListener( 'click', function () {
			panel.remove();
		} );
		[ rep, add, again, close ].forEach( function ( b ) {
			row.appendChild( b );
		} );
		panel.appendChild( row );
		if ( prompt ) {
			// Δεύτερη γνώμη: prompt που ζητά από άλλο AI να ελέγξει την πρόταση.
			var limitTxt = limit ? ' (όριο ' + limit + ' χαρακτήρες)' : '';
			var review = prompt +
				'\n\n---\nΟι οδηγίες μορφής παραπάνω αφορούσαν την αρχική πρόταση. Τώρα ζητώ έλεγχο.' +
				'\nΈνα εργαλείο AI πρότεινε για το πεδίο «' + labelOf( el ) + '»' + limitTxt + ' το εξής κείμενο:\n«' + text + '»' +
				'\n\nΈλεγξε την πρόταση ως δεύτερη γνώμη:' +
				'\n1. Είναι ακριβής και ρεαλιστική για δημόσιο Δημοτικό Σχολείο; Υπάρχουν τοπικά στοιχεία (μνημεία, φορείς, προγράμματα, ημερομηνίες) που μπορεί να είναι λάθος ή επινοημένα;' +
				'\n2. Ταιριάζει στο πεδίο του εντύπου και στην ηλικία των παιδιών;' +
				'\n3. Σέβεται το όριο χαρακτήρων, αν υπάρχει;' +
				'\nΓράψε σύντομα τι θα άλλαζες και γιατί, και μετά μια διορθωμένη εκδοχή, έτοιμη να μπει στο έντυπο.';
			var chk = document.createElement( 'div' );
			chk.className = 'sd-ai-check';
			var lab = document.createElement( 'span' );
			lab.className = 'sd-ai-check-label';
			lab.textContent = 'Έλεγχος σε άλλο AI:';
			chk.appendChild( lab );
			cfg.sites.forEach( function ( s ) {
				var b = btn( s.label, 'sd-quiet' );
				b.title = 'Αντιγράφει prompt ελέγχου (με την πρόταση) και ανοίγει το ' + s.label;
				b.addEventListener( 'click', function () {
					copy( review ).then( function () {
						b.textContent = 'Αντιγράφηκε · ' + s.label;
					} );
					window.open( s.url, '_blank', 'noopener' );
				} );
				chk.appendChild( b );
			} );
			var onlyR = btn( 'Μόνο αντιγραφή', 'sd-quiet' );
			onlyR.addEventListener( 'click', function () {
				copy( review ).then( function () {
					onlyR.textContent = 'Αντιγράφηκε';
				} );
			} );
			chk.appendChild( onlyR );
			panel.appendChild( chk );
			var hint = document.createElement( 'p' );
			hint.className = 'sd-help';
			hint.textContent = 'Στη νέα καρτέλα κάντε επικόλληση (Ctrl+V). Το άλλο AI ελέγχει την πρόταση και δίνει διορθωμένη εκδοχή.';
			panel.appendChild( hint );

			var det = document.createElement( 'details' );
			det.innerHTML = '<summary>Αρχικό prompt (για ανεξάρτητη δεύτερη πρόταση)</summary>';
			var inner = document.createElement( 'div' );
			det.appendChild( inner );
			det.addEventListener( 'toggle', function () {
				if ( det.open && ! inner.childNodes.length ) {
					promptPanel( inner, prompt, '' );
					inner.querySelectorAll( 'button' ).forEach( function ( b ) {
						if ( b.textContent === 'Κλείσιμο' ) {
							b.remove();
						}
					} );
				}
			} );
			panel.appendChild( det );
		}
	}

	function run( el, panel ) {
		panel.innerHTML = '<p class="sd-ai-wait" role="status">' + ( cfg.api ? 'Το AI γράφει πρόταση…' : 'Ετοιμάζεται το prompt…' ) + '</p>';
		request( el, cfg.api ? 'api' : 'prompt' ).then( function ( res ) {
			var d = res && res.data ? res.data : {};
			if ( res && res.success && d.text ) {
				answerPanel( panel, el, d.text, d.prompt );
			} else if ( res && res.success && d.prompt ) {
				promptPanel( panel, d.prompt, '' );
			} else if ( d.prompt ) {
				promptPanel( panel, d.prompt, d.msg || '' );
			} else {
				panel.innerHTML = '';
				var e = document.createElement( 'p' );
				e.className = 'sd-ai-warn';
				e.textContent = d.msg || 'Κάτι πήγε στραβά. Δοκιμάστε ξανά.';
				panel.appendChild( e );
			}
		} ).catch( function () {
			panel.innerHTML = '<p class="sd-ai-warn">Δεν υπάρχει σύνδεση. Δοκιμάστε ξανά.</p>';
		} );
	}

	document.querySelectorAll( 'form.sd-form textarea' ).forEach( function ( el ) {
		if ( el.readOnly || el.disabled || skipped( el ) || el.closest( '.sd-copy' ) ) {
			return;
		}
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'sd-ai-btn';
		b.title = cfg.api ? 'Πρόταση AI για αυτό το πεδίο' : 'Prompt για αυτό το πεδίο, για δωρεάν εργαλείο AI';
		b.setAttribute( 'aria-label', b.title );
		b.innerHTML = '<span aria-hidden="true">✨</span> AI';
		var anchor = el.nextElementSibling && el.nextElementSibling.classList.contains( 'sd-counter' ) ? el.nextElementSibling : el;
		anchor.insertAdjacentElement( 'afterend', b );
		b.addEventListener( 'click', function () {
			var panel = b.nextElementSibling && b.nextElementSibling.classList.contains( 'sd-ai-panel' ) ? b.nextElementSibling : null;
			if ( ! panel ) {
				panel = document.createElement( 'div' );
				panel.className = 'sd-ai-panel';
				b.insertAdjacentElement( 'afterend', panel );
			}
			run( el, panel );
		} );
	} );
}() );
// Ακορντεόν και μενού κορυφής.
( function () {
	'use strict';

	// Ανοίγει ένας θεματικός κύκλος, κλείνουν οι άλλοι (και σε browser χωρίς <details name>).
	var acc = document.querySelectorAll( 'details[data-accordion]' );
	acc.forEach( function ( det ) {
		det.addEventListener( 'toggle', function () {
			if ( det.open ) {
				acc.forEach( function ( o ) {
					if ( o !== det && o.open ) {
						o.open = false;
					}
				} );
			}
		} );
	} );

	// Τα μενού της κορυφής κλείνουν με κλικ έξω από αυτά ή με Esc.
	var menus = document.querySelectorAll( '.sd-nav details' );
	function closeOutside( target ) {
		menus.forEach( function ( d ) {
			if ( d.open && ! d.contains( target ) && ! ( d.classList.contains( 'sd-nav-toggle' ) && target && target.closest && target.closest( '.sd-nav' ) ) ) {
				d.open = false;
			}
		} );
	}
	document.addEventListener( 'click', function ( e ) {
		closeOutside( e.target );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			closeOutside( null );
		}
	} );
}() );

/* Εργαστήρια βήμα-βήμα: αυτόματη αποθήκευση κάθε πεδίου. */
( function () {
	'use strict';
	var form = document.querySelector( 'form[data-autosave]' );
	var node = document.getElementById( 'sd-wiz-cfg' );
	if ( ! form || ! node || ! window.fetch ) {
		return;
	}
	var cfg = JSON.parse( node.textContent );
	var out = form.querySelector( '.sd-saved' );
	var timers = {};
	var pending = 0;
	function status( txt, bad ) {
		if ( out ) {
			out.textContent = txt;
			out.classList.toggle( 'is-bad', !! bad );
		}
	}
	function meters( m ) {
		Object.keys( m || {} ).forEach( function ( k ) {
			var box = document.querySelector( '[data-meter="' + k + '"]' );
			if ( box ) {
				box.querySelector( 'i' ).style.width = m[ k ][ 0 ] + '%';
				box.querySelector( 'small' ).textContent = m[ k ][ 1 ];
			}
		} );
	}
	function send( field, value, extra ) {
		var body = new FormData();
		body.append( 'action', 'sd_wiz_save' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'class_id', form.getAttribute( 'data-class' ) );
		body.append( 'k', form.getAttribute( 'data-k' ) );
		body.append( 'field', field );
		body.append( 'value', value );
		Object.keys( extra || {} ).forEach( function ( x ) {
			body.append( x, extra[ x ] );
		} );
		pending++;
		status( 'Αποθήκευση…' );
		fetch( cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( j ) {
				pending--;
				if ( j && j.success ) {
					meters( j.data.meters );
					if ( ! pending ) {
						status( '✓ Αποθηκεύτηκε' );
					}
				} else {
					status( ( j && j.data && j.data.msg ) || 'Δεν αποθηκεύτηκε. Πατήστε «Επόμενο βήμα».', true );
				}
			} )
			.catch( function () {
				pending--;
				status( 'Δεν αποθηκεύτηκε (σύνδεση). Πατήστε «Επόμενο βήμα».', true );
			} );
	}
	function fieldOf( el ) {
		return ( el.name || '' ).replace( /\[\]$/, '' );
	}
	function save( el ) {
		var name = fieldOf( el );
		if ( ! name || name === 'skills_sent' || el.type === 'hidden' ) {
			return;
		}
		var m = name.match( /^order\[(\d+)\]$/ );
		if ( m ) {
			send( 'order', el.value, { unit: m[ 1 ] } );
			return;
		}
		if ( name === 'skills' ) {
			var vals = [];
			form.querySelectorAll( 'input[name="skills[]"]:checked' ).forEach( function ( c ) {
				vals.push( c.value );
			} );
			send( 'skills', vals.join( ',' ) );
			return;
		}
		send( name, el.value );
	}
	form.addEventListener( 'change', function ( e ) {
		if ( e.target.matches( 'input[type="radio"], input[type="checkbox"]' ) ) {
			save( e.target );
		}
	} );
	form.addEventListener( 'input', function ( e ) {
		var el = e.target;
		if ( ! el.matches( 'textarea, input[type="text"], input[type="number"]' ) ) {
			return;
		}
		status( '' );
		clearTimeout( timers[ el.name ] );
		timers[ el.name ] = setTimeout( function () {
			save( el );
		}, 900 );
	} );
	// Κάθε κύκλος μπαίνει μία φορά στη σειρά της χρονιάς.
	function lockOrder() {
		var used = {};
		form.querySelectorAll( 'input[name^="order"]:checked' ).forEach( function ( r ) {
			used[ r.value ] = r.name;
		} );
		form.querySelectorAll( 'input[name^="order"]' ).forEach( function ( r ) {
			var taken = used[ r.value ] && used[ r.value ] !== r.name;
			r.closest( 'label' ).classList.toggle( 'is-taken', !! taken );
		} );
	}
	if ( form.querySelector( 'input[name^="order"]' ) ) {
		form.addEventListener( 'change', lockOrder );
		lockOrder();
	}
}() );
