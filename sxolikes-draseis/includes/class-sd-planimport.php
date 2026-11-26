<?php
/**
 * Εισαγωγή Σχεδίου Δράσης από την πλατφόρμα αξιολόγησης του ΙΕΠ με αντιγραφή–επικόλληση.
 *
 * Ο/Η χρήστης ανοίγει την «Προβολή» του σχεδίου στην πλατφόρμα, πατά Ctrl+A, Ctrl+C
 * και επικολλά εδώ. Η εφαρμογή αναγνωρίζει στόχους, ενέργειες (υλοποίηση/αποτίμηση),
 * ημερομηνίες, κριτήρια επιτυχίας, εργαλεία και τρόπους εργασίας, δείχνει
 * προεπισκόπηση και μόνο μετά από επιβεβαίωση αντικαθιστά τη στοχοθεσία του σχεδίου.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_PlanImport {

	const MAX_TEXT = 60000;

	private static function key( $plan_id ) {
		return 'sd_imp_' . get_current_user_id() . '_' . (int) $plan_id;
	}

	/* =================== Ανάλυση κειμένου =================== */

	/** Κανονικοποίηση για συγκρίσεις: πεζά, χωρίς τόνους, μονά κενά. */
	public static function norm( $s ) {
		$s = mb_strtolower( (string) $s, 'UTF-8' );
		$s = strtr(
			$s,
			array(
				'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω',
				'ϊ' => 'ι', 'ΐ' => 'ι', 'ϋ' => 'υ', 'ΰ' => 'υ', 'ς' => 'σ',
			)
		);
		$s = preg_replace( '#\s*/\s*[-‐–]\s*#u', '/', $s ); // «μαθητών/-τριών» = «μαθητών/τριών»
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	public static function lines( $text ) {
		$text = str_replace( array( "\r\n", "\r", "\xC2\xA0", "\t" ), array( "\n", "\n", ' ', "\n" ), (string) $text );
		$out  = array();
		foreach ( explode( "\n", $text ) as $l ) {
			$l = trim( preg_replace( '/[ \x{2000}-\x{200B}]+/u', ' ', $l ) );
			if ( '' !== $l ) {
				$out[] = $l;
			}
		}
		return $out;
	}

	private static function date_ymd( $d ) {
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $d, $m ) && checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
			return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		}
		return '';
	}

	/** Ποιες ετικέτες μιας λίστας (εργαλεία/τρόποι) εμφανίζονται στο κείμενο· τι περισσεύει. */
	private static function match_list( $text, $list ) {
		$n     = ' ' . self::norm( $text ) . ' ';
		$found = array();
		foreach ( $list as $k => $label ) {
			$l = self::norm( $label );
			if ( false !== mb_strpos( $n, $l ) ) {
				$found[] = $k;
				$n       = str_replace( $l, ' ', $n );
			}
		}
		$rest = trim( preg_replace( '/\s+/u', ' ', $n ) );
		return array( $found, $rest );
	}

	private static function is_heading( $line, $words ) {
		$n = self::norm( rtrim( $line, ':' ) );
		foreach ( $words as $w ) {
			if ( $n === self::norm( $w ) ) {
				return true;
			}
		}
		return false;
	}

	/** Γραμμές που δεν είναι περιεχόμενο (κεφαλίδες σελίδων PDF, κουμπιά της πλατφόρμας). */
	const NOISE = array(
		'/^Εξαγωγή Σχεδίου Δράσης$/u',
		'/^Σχολικό [Έέ]τος:?\s*\d{4}/u',
		'/^Ημερομηνία εξαγωγής:/u',
		'/^Σελίδα:?\s*\d+/u',
		'/^Πληροφορίες Σχολείου/u',
		'/^Κωδικός Σχολείου/u',
		'/^\d{6,8}\s+\S/u',
		'/^ΑΞΙΟΛΟΓΗΣΗ ΣΧΟΛΙΚΩΝ ΜΟΝΑΔΩΝ$/u',
		'/^Κατάλογος σχολείων/u',
		'/^Τρέχουσα Περίοδος/u',
		'/^(Επιλογή Σχεδίου|Επεξεργασία φόρμας|Επισκόπηση και Υποβολή|Προτάσεις|Διαγραφή|\+?\s*Προσθήκη Ενέργειας|Αποθήκευση Στόχου|Ακύρωση|Σε επεξεργασία|Εργαλεία\s*\/\s*Τρόπος Εργασίας|Έξυπνος Βοηθός ΙΕΠ|Βοήθεια πεδίου|Εξαγωγή Σχεδίου|Υποβολή Σχεδίου Δράσης)$/u',
		'/^\d\s*\/\s*\d\s+ενέργειες$/u',
		'/^Στόχοι\s*\(\d\s*\/\s*\d\)$/u',
		'/^\*\s*Η αποθήκευση/u',
		'/^Προσθέστε άλλο/u',
		'/^\d+\s+(ενέργει|Υλοποίηση|Αποτίμηση)/u',
	);

	const MONTHS_N = array( 'ιανουαριοσ' => 1, 'φεβρουαριοσ' => 2, 'μαρτιοσ' => 3, 'απριλιοσ' => 4, 'μαιοσ' => 5, 'ιουνιοσ' => 6, 'ιουλιοσ' => 7, 'αυγουστοσ' => 8, 'σεπτεμβριοσ' => 9, 'οκτωβριοσ' => 10, 'νοεμβριοσ' => 11, 'δεκεμβριοσ' => 12 );

	/** Τι είδους επικεφαλίδα είναι μια γραμμή: [είδος, τιμή στην ίδια γραμμή] ή null. */
	private static function heading( $line ) {
		// «Ετικέτα: τιμή» στην ίδια γραμμή.
		$inline = '';
		$label  = $line;
		if ( preg_match( '/^([^:]{3,60}):\s*(.*)$/u', $line, $m ) ) {
			$label  = $m[1];
			$inline = trim( $m[2] );
		}
		$n = self::norm( preg_replace( '/\s*\((?:έως\s*)?\d+\s*χαρ\.?\)\s*/u', ' ', $label ) );
		$n = trim( preg_replace( '/\s*\(.*$/u', '', $n ) ); // «Εργαλεία (με τι θα δουλέψετε) …»
		$map = array(
			'title'    => array( 'τιτλοσ σχεδιου', 'τιτλοσ σχεδιου δρασησ', 'τιτλοσ', 'σχεδιο δρασησ' ),
			'axis'     => array( 'αξονασ', 'αξονασ ομαδα' ),
			'skip'     => array( 'ομαδα', 'ομαδα δρασησ', 'λειτουργια', 'δομικα στοιχεια', 'στοχοθεσια', 'πληροφοριεσ σχεδιου δρασησ', 'χρονοδιαγραμμα σχεδιου', 'χρονοδιαγραμμα', 'ανατροφοδοτηση', 'συντονιστησ / μελη ομαδασ' ),
			'purpose'  => array( 'σκοποσ', 'σκοποσ / βασικη κατευθυνση', 'σκοποσ/βασικη κατευθυνση', 'βασικη κατευθυνση', 'σκοποσ του σχεδιου', 'σκοποσ του σχεδιου δρασησ' ),
			'need'     => array( 'αρχικη αναγκη', 'τεκμηριωση αναγκαιοτητασ', 'τεκμηριωση', 'τεκμηριωση τησ αναγκαιοτητασ' ),
			'sources'  => array( 'πηγεσ τεκμηριωσησ' ),
			'praxi'    => array( 'πραξη συλλογου διδασκοντων' ),
			'cont'     => array( 'συνεχεια προηγουμενου σχεδιου δρασησ', 'συνεχεια προηγουμενου σχεδιου' ),
			'collab'   => array( 'συνεργασια με αλλο σχολειο' ),
			'gdesc'    => array( 'περιγραφη στοχου', 'διατυπωση στοχου', 'διατυπωση' ),
			'tools'    => array( 'εργαλεια' ),
			'ways'     => array( 'τροποσ εργασιασ', 'τροποι εργασιασ', 'τροποσ δουλειασ' ),
			'other'    => array( 'αλλο' ),
			'acts'     => array( 'ενεργειεσ' ),
			'mode_y'   => array( 'ενεργειεσ υλοποιησησ' ),
			'mode_a'   => array( 'ενεργειεσ αποτιμησησ' ),
			'dates'    => array( 'μηνασ εναρξησ', 'μηνασ εναρξησ μηνασ ληξησ', 'μηνασ εναρξησ – ληξησ', 'μηνασ εναρξησ - ληξησ', 'ημερομηνια εναρξησ – ληξησ', 'ημερομηνια εναρξησ - ληξησ' ),
			'date_end' => array( 'μηνασ ληξησ' ),
		);
		foreach ( $map as $kind => $labels ) {
			if ( in_array( $n, $labels, true ) ) {
				return array( $kind, $inline );
			}
		}
		if ( preg_match( '/^ενεργεια υλοποιησησ(?: \d)?$/u', $n ) ) {
			return array( 'act_y', $inline );
		}
		if ( preg_match( '/^ενεργεια αποτιμησησ(?: \d)?$/u', $n ) ) {
			return array( 'act_a', $inline );
		}
		if ( preg_match( '/^περιγραφη ενεργειασ(?: \d)?$/u', $n ) ) {
			return array( 'adesc', $inline );
		}
		// «Κριτήριο Επιτυχίας», και με τη βοηθητική πρόταση της πλατφόρμας δίπλα.
		if ( preg_match( '/^κριτηρι[οα] επιτυχιασ\b/u', self::norm( $line ) ) ) {
			$rest = preg_replace( '/^Κριτήρι[οα]\s+[Εε]πιτυχίας\s*:?\s*/u', '', $line );
			if ( false !== mb_strpos( self::norm( $rest ), 'απο ποια στοιχεια' ) ) {
				$rest = '';
			}
			return array( 'crit', trim( $rest ) );
		}
		return null;
	}

	/** Ημερομηνίες ή μήνες σε μια γραμμή: [[Y-m-d, …]] με σειρά εμφάνισης (μήνας → 1η ή τελευταία ημέρα). */
	private static function find_dates( $line ) {
		$out = array();
		if ( preg_match_all( '#\b(\d{1,2})/(\d{1,2})/(\d{4})\b#', $line, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $d ) {
				if ( checkdate( (int) $d[2], (int) $d[1], (int) $d[3] ) ) {
					$out[] = sprintf( '%04d-%02d-%02d', $d[3], $d[2], $d[1] );
				}
			}
			return $out;
		}
		if ( preg_match_all( '/(\p{L}+)\s+(\d{4})/u', $line, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $d ) {
				$k = self::norm( $d[1] );
				if ( isset( self::MONTHS_N[ $k ] ) ) {
					$out[] = sprintf( '%04d-%02d-01', $d[2], self::MONTHS_N[ $k ] );
				}
			}
		}
		return $out;
	}

	/** Επιλογές (εργαλεία, τρόποι, πηγές): με ☒/☐ μετρούν μόνο τα ☒· «Άλλο:» χωριστά. */
	private static function pick( $text, $list ) {
		$other = '';
		if ( preg_match( '/Άλλο\s*:\s*(.*)$/u', $text, $m ) ) {
			$other = trim( $m[1] );
			$text  = trim( str_replace( $m[0], '', $text ) );
		}
		if ( preg_match( '/[☒☐]/u', $text ) ) {
			$on = array();
			foreach ( preg_split( '/(?=[☒☐])/u', $text ) as $part ) {
				if ( 0 === mb_strpos( $part, '☒' ) ) {
					$on[] = trim( mb_substr( $part, 1 ) );
				}
			}
			$text = implode( ' · ', $on );
			list( $found ) = self::match_list( $text, $list );
			return array( $found, $other );
		}
		list( $found, $rest ) = self::match_list( $text, $list );
		$rest = trim( $rest, " ·,;" );
		if ( '' === $other && mb_strlen( $rest ) > 2 ) {
			$other = $rest;
		}
		return array( $found, $other );
	}

	/**
	 * Ανάλυση. Δέχεται: «Προβολή» της πλατφόρμας (παλιά και νέα μορφή), την
	 * «Εξαγωγή Σχεδίου Δράσης» της πλατφόρμας σε PDF, και το Word της εφαρμογής.
	 * Επιστρέφει ['title','purpose','need','axis','sources','praxi','continuation','collab','goals','warnings'].
	 */
	public static function parse( $text ) {
		$res = array(
			'title'        => '',
			'purpose'      => '',
			'need'         => '',
			'axis'         => 0,
			'sources'      => array(),
			'praxi'        => '',
			'continuation' => '',
			'collab'       => '',
			'goals'        => array(),
			'warnings'     => array(),
		);
		$lines = array();
		foreach ( self::lines( $text ) as $l ) {
			$noise = false;
			foreach ( self::NOISE as $re ) {
				$noise = $noise || preg_match( $re, $l );
			}
			if ( ! $noise ) {
				$lines[] = $l;
			}
		}

		$buf   = array( 'purpose' => array(), 'need' => array(), 'sources' => array() );
		$goals = array();
		$g     = null;
		$act   = null;
		$field = 'skip';
		$mode  = 'ylopoiisi';
		$new_g = function ( $no, $head ) {
			return array( 'no' => $no, 'head' => $head, 'desc' => array(), 'tools' => array(), 'ways' => array(), 'other_t' => '', 'other_w' => '', 'acts' => array(), 'crit' => array() );
		};
		$new_a = function ( $kind ) {
			return array( 'kind' => $kind, 'desc' => array(), 'crit' => array(), 'start' => '', 'end' => '' );
		};
		$close_a = function () use ( &$g, &$act ) {
			if ( $act && $g ) {
				$g['acts'][] = $act;
			}
			$act = null;
		};
		$close_g = function () use ( &$g, &$goals, $close_a ) {
			$close_a();
			if ( $g ) {
				$goals[] = $g;
			}
			$g = null;
		};

		foreach ( $lines as $l ) {
			// Νέος στόχος: «Στόχος 1», «Στόχος 1 / 3», «Στόχος 1: …».
			if ( preg_match( '/^Στόχος\s*(\d)(?:\s*\/\s*\d)?\b\s*[:.]?\s*(.*)$/u', $l, $m ) ) {
				$close_g();
				$g     = $new_g( (int) $m[1], trim( $m[2] ) );
				$field = 'ghead';
				$mode  = 'ylopoiisi';
				continue;
			}
			// Τίτλος από την κεφαλίδα της πλατφόρμας: «Σχέδιο Δράσης <τίτλος>».
			if ( ! $g && '' === $res['title'] && preg_match( '/^Σχέδιο Δράσης\s+(?!εσωτερικ)(.{4,})$/u', $l, $m ) ) {
				$res['title'] = trim( preg_replace( '/^ΣΔ\d+\s*/u', '', trim( $m[1], ' «»"' ) ), ' «»"' );
				continue;
			}
			$h = self::heading( $l );
			if ( $h ) {
				list( $kind, $inline ) = $h;
				switch ( $kind ) {
					case 'act_y':
					case 'act_a':
						$close_a();
						$act   = $new_a( 'act_a' === $kind ? 'apotimisi' : 'ylopoiisi' );
						$field = 'adesc';
						break;
					case 'mode_y':
					case 'mode_a':
						$close_a();
						$mode  = 'mode_a' === $kind ? 'apotimisi' : 'ylopoiisi';
						$field = 'skip';
						break;
					case 'adesc':
						// PDF/πλατφόρμα: μέσα σε «Ενέργεια …»· Word: κάθε «Περιγραφή ενέργειας N» είναι νέα ενέργεια.
						if ( ! $act || $act['desc'] ) {
							$close_a();
							$act = $new_a( $mode );
						}
						$field = 'adesc';
						break;
					case 'dates':
						$field = 'dates';
						break;
					case 'date_end':
						// «Μήνας Έναρξης» και «Μήνας Λήξης» μπορεί να έρθουν πριν από τις τιμές.
						$field = ( 'dates' === $field && $act && '' === $act['start'] ) ? 'dates' : 'date_end';
						break;
					case 'crit':
						$field = 'crit';
						break;
					case 'other':
						$field = 'tools' === $field || 'other_t' === $field ? 'other_t' : ( 'ways' === $field || 'other_w' === $field ? 'other_w' : 'skip' );
						break;
					case 'tools':
					case 'ways':
						$close_a();
						$field = $kind;
						break;
					case 'gdesc':
						$field = $g ? 'gdesc' : 'skip';
						break;
					default:
						$field = $kind;
				}
				if ( '' === $inline ) {
					continue;
				}
				$l = $inline; // η τιμή στην ίδια γραμμή επεξεργάζεται παρακάτω
			}

			switch ( $field ) {
				case 'title':
					$res['title'] = '' === $res['title'] ? $l : $res['title'];
					$field        = 'skip';
					break;
				case 'axis':
					$v = trim( preg_replace( '/\s*\d+\s*συντονιστ.*$/u', '', $l ) );
					foreach ( SD_Eval::AXES as $n => $a ) {
						if ( self::norm( $v ) === self::norm( $a[0] ) || ( mb_strlen( $v ) > 8 && false !== mb_strpos( self::norm( $v ), self::norm( $a[0] ) ) ) ) {
							$res['axis'] = $n;
						}
					}
					$field = 'skip';
					break;
				case 'purpose':
				case 'need':
				case 'sources':
					$buf[ $field ][] = $l;
					break;
				case 'praxi':
					$res['praxi'] = mb_substr( $l, 0, 60 );
					$field        = 'skip';
					break;
				case 'cont':
				case 'collab':
					$yn = self::norm( $l );
					$res[ 'cont' === $field ? 'continuation' : 'collab' ] = 0 === mb_strpos( $yn, 'ναι' ) ? 'nai' : ( 0 === mb_strpos( $yn, 'οχι' ) ? 'oxi' : '' );
					$field = 'skip';
					break;
				case 'ghead':
					if ( $g && '' === $g['head'] ) {
						$g['head'] = $l;
					}
					break;
				case 'gdesc':
					$g['desc'][] = $l;
					break;
				case 'tools':
					$g['tools'][] = $l;
					break;
				case 'ways':
					$g['ways'][] = $l;
					break;
				case 'other_t':
					$g['other_t'] = trim( $g['other_t'] . ' ' . $l );
					break;
				case 'other_w':
					$g['other_w'] = trim( $g['other_w'] . ' ' . $l );
					break;
				case 'acts':
					// Παλιά «Προβολή»: «Υλοποίηση» / «Αποτίμηση» και μετά το κείμενο.
					if ( preg_match( '/^(Υλοποίηση|Αποτίμηση)\b\s*(.*)$/u', $l, $m ) ) {
						$close_a();
						$act = $new_a( 'Αποτίμηση' === $m[1] ? 'apotimisi' : 'ylopoiisi' );
						$l   = trim( $m[2] );
					}
					if ( ! $act || '' === $l ) {
						break;
					}
					$d = self::find_dates( $l );
					if ( count( $d ) >= 2 ) {
						$act['start'] = $d[0];
						$act['end']   = $d[1];
						// Κείμενο και ημερομηνίες στην ίδια γραμμή: κρατάμε το κείμενο.
						$l = trim( preg_replace( '#\d{1,2}/\d{1,2}/\d{4}\s*(?:έως|εως|-|–|—)?\s*(?:\d{1,2}/\d{1,2}/\d{4})?#u', '', $l ) );
					}
					if ( '' !== $l ) {
						$act['desc'][] = $l;
					}
					break;
				case 'adesc':
					if ( $act ) {
						$act['desc'][] = $l;
					}
					break;
				case 'dates':
				case 'date_end':
					$d = self::find_dates( $l );
					if ( $act && $d ) {
						if ( count( $d ) >= 2 ) {
							$act['start'] = $d[0];
							$act['end']   = $d[1];
						} elseif ( 'date_end' === $field || $act['start'] ) {
							$act['end'] = $d[0];
						} else {
							$act['start'] = $d[0];
						}
					}
					break;
				case 'crit':
					if ( $act ) {
						$act['crit'][] = $l;
					} elseif ( $g ) {
						$g['crit'][] = $l;
					}
					break;
			}
		}
		$close_g();

		// Πεδία σχεδίου.
		$res['purpose'] = trim( implode( ' ', $buf['purpose'] ) );
		$res['need']    = trim( implode( ' ', $buf['need'] ) );
		if ( $buf['sources'] ) {
			list( $res['sources'] ) = self::pick( implode( ' ', $buf['sources'] ), SD_Eval::SOURCES );
		}

		// Ο ίδιος στόχος μπορεί να εμφανίζεται δύο φορές· κρατάμε το πληρέστερο.
		$by_no = array();
		foreach ( $goals as $b ) {
			$size = count( $b['desc'] ) + count( $b['acts'] ) * 3;
			if ( ! isset( $by_no[ $b['no'] ] ) || $size > $by_no[ $b['no'] ][1] ) {
				$by_no[ $b['no'] ] = array( $b, $size );
			}
		}
		ksort( $by_no );
		foreach ( $by_no as $pair ) {
			// Καρτέλες «Στόχος 2», «Στόχος 3» χωρίς περιεχόμενο (από την οθόνη της πλατφόρμας): δεν είναι στόχοι.
			if ( ! $pair[0]['desc'] && ! $pair[0]['acts'] && '' === $pair[0]['head'] ) {
				continue;
			}
			$res['goals'][] = self::finish_goal( $pair[0], $res['warnings'] );
		}
		if ( ! $res['goals'] ) {
			$res['warnings'][] = 'Δεν βρέθηκε κανένας «Στόχος». Αντιγράψτε όλη τη σελίδα (Ctrl+A, Ctrl+C) ή όλο το PDF/Word του σχεδίου.';
		}
		if ( count( $res['goals'] ) > SD_Eval::MAX_GOALS ) {
			$res['warnings'][] = 'Βρέθηκαν ' . count( $res['goals'] ) . ' στόχοι· η φόρμα δέχεται έως ' . SD_Eval::MAX_GOALS . '. Θα εισαχθούν οι πρώτοι ' . SD_Eval::MAX_GOALS . '.';
			$res['goals']      = array_slice( $res['goals'], 0, SD_Eval::MAX_GOALS );
		}
		if ( $res['goals'] && '' === $res['purpose'] && '' === $res['need'] ) {
			$res['warnings'][] = 'Δεν βρέθηκαν Σκοπός και Αρχική Ανάγκη (το PDF της πλατφόρμας δεν τα περιέχει). Αν τα θέλετε, αντιγράψτε και την κορυφή της «Επεξεργασίας φόρμας» της πλατφόρμας ή το Word της εφαρμογής στο ίδιο κουτί.';
		}
		return $res;
	}

	private static function finish_goal( $b, &$warn ) {
		$description = trim( implode( ' ', $b['desc'] ) );
		if ( '' === $description && '' !== $b['head'] ) {
			$description = $b['head'];
		}
		list( $tools, $t_oth ) = self::pick( implode( ' ', $b['tools'] ), SD_Eval::TOOLS );
		list( $ways, $w_oth )  = self::pick( implode( ' ', $b['ways'] ), SD_Eval::WAYS );
		$t_oth = trim( $b['other_t'] . ' ' . $t_oth );
		$w_oth = trim( $b['other_w'] . ' ' . $w_oth );

		// Σύντομος τίτλος μόνο αν διαφέρει από την αρχή της περιγραφής.
		$title = trim( preg_replace( '/\s*\S*(\.\.\.|…)$/u', '', $b['head'] ) );
		if ( '' !== $title && 0 === mb_strpos( self::norm( $description ), self::norm( $title ) ) ) {
			$title = '';
		}
		$out = array(
			'title'       => mb_substr( rtrim( $title, " /-–,·:" ), 0, 255 ),
			'description' => $description,
			'tools'       => $tools,
			'tools_other' => mb_substr( $t_oth, 0, SD_Eval::OTHER_LIMIT ),
			'ways'        => $ways,
			'ways_other'  => mb_substr( $w_oth, 0, SD_Eval::OTHER_LIMIT ),
			'actions'     => array(),
		);
		foreach ( $b['acts'] as $a ) {
			$end = $a['end'];
			if ( $end && '01' === substr( $end, 8, 2 ) && $a['start'] && substr( $a['start'], 8, 2 ) === '01' ) {
				$end = gmdate( 'Y-m-t', strtotime( $end ) ); // μήνας λήξης → τελευταία ημέρα
			}
			$out['actions'][] = array(
				'kind'        => $a['kind'],
				'description' => trim( preg_replace( '/^\d+\.\s*/u', '', implode( ' ', $a['desc'] ) ) ),
				'start_date'  => $a['start'],
				'end_date'    => $end,
				'criterion'   => trim( implode( ' ', $a['crit'] ) ),
			);
		}
		// Word της εφαρμογής: τα «Κριτήρια επιτυχίας» είναι κάτω από τις ενέργειες αποτίμησης.
		if ( $b['crit'] ) {
			for ( $i = count( $out['actions'] ) - 1; $i >= 0; $i-- ) {
				if ( 'apotimisi' === $out['actions'][ $i ]['kind'] && '' === $out['actions'][ $i ]['criterion'] ) {
					$out['actions'][ $i ]['criterion'] = trim( implode( ' ', $b['crit'] ) );
					break;
				}
			}
		}
		$n = 'Στόχος ' . (int) $b['no'];
		if ( '' === $description ) {
			$warn[] = $n . ': δεν βρέθηκε η «Περιγραφή Στόχου».';
		}
		if ( ! $out['actions'] ) {
			$warn[] = $n . ': δεν βρέθηκαν ενέργειες.';
		} else {
			$c = array_count_values( wp_list_pluck( $out['actions'], 'kind' ) );
			if ( 2 !== ( $c['ylopoiisi'] ?? 0 ) || 1 !== ( $c['apotimisi'] ?? 0 ) ) {
				$warn[] = $n . ': βρέθηκαν ' . (int) ( $c['ylopoiisi'] ?? 0 ) . ' ενέργειες υλοποίησης και ' . (int) ( $c['apotimisi'] ?? 0 ) . ' αποτίμησης· κάθε στόχος χρειάζεται 2 και 1.';
			}
		}
		if ( ! $tools && '' === $out['tools_other'] ) {
			$warn[] = $n . ': δεν βρέθηκαν εργαλεία (στην «Επεξεργασία φόρμας» της πλατφόρμας τα τικ δεν αντιγράφονται· προτιμήστε το PDF ή την «Επισκόπηση»).';
		}
		if ( $out['tools_other'] ) {
			$warn[] = $n . ': εργαλεία «Άλλο»: «' . $out['tools_other'] . '».';
		}
		if ( $out['ways_other'] ) {
			$warn[] = $n . ': τρόπος εργασίας «Άλλο»: «' . $out['ways_other'] . '».';
		}
		foreach ( $out['actions'] as $i => $a ) {
			if ( ! $a['start_date'] || ! $a['end_date'] ) {
				$warn[] = $n . ', ενέργεια ' . ( $i + 1 ) . ': δεν βρέθηκε μήνας έναρξης ή λήξης.';
			}
		}
		return $out;
	}

	/* =================== Ενέργειες =================== */

	public static function handle( $do ) {
		if ( ! in_array( $do, array( 'plan_import_preview', 'plan_import_apply', 'plan_import_cancel' ), true ) ) {
			return null;
		}
		$plan = SD_Plans::get_plan( absint( $_POST['plan_id'] ?? 0 ) );
		$list = SD_Frontend::url( 'sxedia' );
		if ( ! $plan || ! SD_Plans::can_edit( $plan ) ) {
			return array( new WP_Error( 'sd', 'Δεν έχετε δικαίωμα να αλλάξετε αυτό το σχέδιο.' ), $list );
		}
		$page = SD_Frontend::url( 'sxedia', array( 'eisagogi' => $plan->id ) );
		if ( 'plan_import_cancel' === $do ) {
			delete_transient( self::key( $plan->id ) );
			return array( true, SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) ) );
		}
		if ( 'plan_import_preview' === $do ) {
			$text = isset( $_POST['text'] ) ? (string) wp_unslash( $_POST['text'] ) : '';
			$text = mb_substr( wp_strip_all_tags( $text ), 0, self::MAX_TEXT );
			if ( '' === trim( $text ) ) {
				return array( new WP_Error( 'sd', 'Επικολλήστε πρώτα το κείμενο από την πλατφόρμα.' ), $page );
			}
			$res = self::parse( $text );
			set_transient( self::key( $plan->id ), $res, HOUR_IN_SECONDS );
			set_transient( 'sd_msg_' . get_current_user_id(), array( $res['goals'] ? 'ok' : 'error', $res['goals'] ? 'Βρέθηκαν ' . count( $res['goals'] ) . ' στόχοι. Ελέγξτε την προεπισκόπηση και πατήστε «Εισαγωγή».' : 'Δεν αναγνωρίστηκε σχέδιο στο κείμενο.' ), 60 );
			return array( true, $page . '#proepiskopisi' );
		}
		// Εφαρμογή.
		$res = get_transient( self::key( $plan->id ) );
		if ( ! is_array( $res ) || empty( $res['goals'] ) ) {
			return array( new WP_Error( 'sd', 'Η προεπισκόπηση έληξε. Επικολλήστε ξανά το κείμενο.' ), $page );
		}
		$use = array();
		foreach ( array( 'title', 'axis', 'purpose', 'need', 'sources', 'praxi', 'continuation', 'collab' ) as $f ) {
			$use[ $f ] = ! empty( $_POST[ 'use_' . $f ] );
		}
		self::apply( $plan->id, $res, $use );
		delete_transient( self::key( $plan->id ) );
		set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', 'Η εισαγωγή ολοκληρώθηκε. Στο «Λείπουν ακόμη» φαίνεται ό,τι χρειάζεται συμπλήρωση.' ), 60 );
		return array( true, SD_Frontend::url( 'sxedia', array( 'id' => $plan->id, 'tab' => 'episkopisi' ) ) );
	}

	/**
	 * Γράφει στο σχέδιο ό,τι αναγνωρίστηκε: αντικαθιστά τη στοχοθεσία και,
	 * όπου $use[πεδίο] είναι αληθές, ενημερώνει και τα δομικά στοιχεία.
	 */
	public static function apply( $plan_id, $res, $use ) {
		$plan_id = (int) $plan_id;
		global $wpdb;
		foreach ( SD_Plans::goals( $plan_id ) as $g ) {
			SD_Plans::delete_goal( $g->id );
		}
		foreach ( $res['goals'] as $gi => $g ) {
			$wpdb->insert(
				SD_Data::table( 'goals' ),
				array(
					'plan_id'     => $plan_id,
					'ord'         => $gi + 1,
					'title'       => sanitize_text_field( $g['title'] ),
					'description' => sanitize_textarea_field( $g['description'] ),
					'tools'       => implode( ',', $g['tools'] ),
					'tools_other' => sanitize_text_field( $g['tools_other'] ),
					'ways'        => implode( ',', $g['ways'] ),
					'ways_other'  => sanitize_text_field( $g['ways_other'] ),
				)
			);
			$goal_id = (int) $wpdb->insert_id;
			foreach ( $g['actions'] as $ai => $a ) {
				$wpdb->insert(
					SD_Data::table( 'goal_actions' ),
					array(
						'goal_id'     => $goal_id,
						'kind'        => isset( SD_Eval::ACTION_KINDS[ $a['kind'] ] ) ? $a['kind'] : 'ylopoiisi',
						'ord'         => $ai + 1,
						'description' => sanitize_textarea_field( $a['description'] ),
						'start_date'  => $a['start_date'] ? $a['start_date'] : null,
						'end_date'    => $a['end_date'] ? $a['end_date'] : null,
						'criterion'   => sanitize_textarea_field( $a['criterion'] ),
						'grades'      => '',
					)
				);
			}
		}
		$upd = array( 'updated_at' => current_time( 'mysql' ) );
		if ( ! empty( $use['title'] ) && '' !== $res['title'] ) {
			$upd['title'] = sanitize_text_field( mb_substr( $res['title'], 0, 255 ) );
		}
		foreach ( array( 'purpose', 'need' ) as $f ) {
			if ( ! empty( $use[ $f ] ) && '' !== $res[ $f ] ) {
				$upd[ $f ] = sanitize_textarea_field( mb_substr( $res[ $f ], 0, SD_Eval::PURPOSE_LIMIT ) );
			}
		}
		if ( ! empty( $use['axis'] ) && ! empty( $res['axis'] ) && isset( SD_Eval::AXES[ (int) $res['axis'] ] ) ) {
			$upd['axis'] = (int) $res['axis'];
		}
		if ( ! empty( $use['sources'] ) && ! empty( $res['sources'] ) ) {
			$upd['sources'] = implode( ',', array_intersect( array_keys( SD_Eval::SOURCES ), $res['sources'] ) );
		}
		if ( ! empty( $use['praxi'] ) && ! empty( $res['praxi'] ) ) {
			$upd['praxi'] = sanitize_text_field( $res['praxi'] );
		}
		foreach ( array( 'continuation', 'collab' ) as $f ) {
			if ( ! empty( $use[ $f ] ) && ! empty( $res[ $f ] ) && isset( SD_Eval::YES_NO[ $res[ $f ] ] ) ) {
				$upd[ $f ] = $res[ $f ];
			}
		}
		$wpdb->update( SD_Data::table( 'plans' ), $upd, array( 'id' => $plan_id ) );
	}

	/* =================== Οθόνη =================== */

	private static function names( $keys, $list, $other ) {
		$out = array();
		foreach ( $keys as $k ) {
			$out[] = $list[ $k ] ?? $k;
		}
		if ( $other ) {
			$out[] = 'Άλλο: ' . $other;
		}
		return $out ? implode( ' · ', $out ) : '—';
	}

	/** Τα δομικά στοιχεία που αναγνωρίστηκαν: [[πεδίο, ετικέτα, τιμή]]. */
	public static function found_fields( $res ) {
		$out = array();
		if ( '' !== ( $res['title'] ?? '' ) ) {
			$out[] = array( 'title', 'Τίτλος σχεδίου', $res['title'] );
		}
		if ( ! empty( $res['axis'] ) ) {
			$out[] = array( 'axis', 'Άξονας', SD_Eval::axis_label( $res['axis'] ) );
		}
		if ( '' !== ( $res['purpose'] ?? '' ) ) {
			$out[] = array( 'purpose', 'Σκοπός / Βασική κατεύθυνση', $res['purpose'] );
		}
		if ( '' !== ( $res['need'] ?? '' ) ) {
			$out[] = array( 'need', 'Αρχική Ανάγκη', $res['need'] );
		}
		if ( ! empty( $res['sources'] ) ) {
			$out[] = array( 'sources', 'Πηγές τεκμηρίωσης', implode( ' · ', array_intersect_key( SD_Eval::SOURCES, array_flip( $res['sources'] ) ) ) );
		}
		if ( ! empty( $res['praxi'] ) ) {
			$out[] = array( 'praxi', 'Πράξη Συλλόγου Διδασκόντων', $res['praxi'] );
		}
		foreach ( array( 'continuation' => 'Συνέχεια προηγούμενου σχεδίου', 'collab' => 'Συνεργασία με άλλο σχολείο' ) as $f => $l ) {
			if ( ! empty( $res[ $f ] ) ) {
				$out[] = array( $f, $l, SD_Eval::YES_NO[ $res[ $f ] ] ?? $res[ $f ] );
			}
		}
		return $out;
	}

	/** Προεπισκόπηση: δομικά στοιχεία και στόχοι με τις ενέργειές τους. */
	public static function preview( $res ) {
		$found = self::found_fields( $res );
		if ( $found ) {
			echo '<h4>Δομικά στοιχεία που βρέθηκαν</h4><dl class="sd-dl">';
			foreach ( $found as $f ) {
				echo '<dt>' . esc_html( $f[1] ) . '</dt><dd>' . esc_html( $f[2] ) . '</dd>';
			}
			echo '</dl>';
		}
		foreach ( $res['goals'] as $i => $g ) {
			echo '<div class="sd-imp-goal"><h4>Στόχος ' . (int) ( $i + 1 ) . ( $g['title'] ? ': ' . esc_html( $g['title'] ) : '' ) . '</h4>';
			echo '<p>' . esc_html( $g['description'] ) . ' <span class="sd-meta">(' . (int) SD_Eval::length( $g['description'] ) . '/' . (int) SD_Eval::LIMIT . ')</span></p>';
			echo '<p class="sd-meta"><strong>Εργαλεία:</strong> ' . esc_html( self::names( $g['tools'], SD_Eval::TOOLS, $g['tools_other'] ) ) . '<br><strong>Τρόποι εργασίας:</strong> ' . esc_html( self::names( $g['ways'], SD_Eval::WAYS, $g['ways_other'] ) ) . '</p><ol class="sd-imp-acts">';
			foreach ( $g['actions'] as $a ) {
				echo '<li><span class="sd-badge sd-kind-' . esc_attr( $a['kind'] ) . '">' . esc_html( SD_Eval::ACTION_KINDS[ $a['kind'] ] ) . '</span> ' . esc_html( $a['description'] );
				$mn = function ( $d ) {
					return $d ? ( SD_PlanUI::MONTHS[ (int) substr( $d, 5, 2 ) ] ?? '' ) . ' ' . substr( $d, 0, 4 ) : '—';
				};
				echo ' <span class="sd-meta">' . esc_html( $mn( $a['start_date'] ) . ' – ' . $mn( $a['end_date'] ) ) . '</span>';
				if ( $a['criterion'] ) {
					echo '<div class="sd-criterion"><strong>Κριτήριο επιτυχίας:</strong> ' . esc_html( $a['criterion'] ) . '</div>';
				}
				echo '</li>';
			}
			echo '</ol></div>';
		}
	}

	public static function render( $plan_id ) {
		$plan = SD_Plans::get_plan( $plan_id );
		if ( ! $plan || ! SD_Plans::can_edit( $plan ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε δικαίωμα να αλλάξετε αυτό το σχέδιο.</p>';
			return;
		}
		echo '<p class="sd-noprint"><a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) ) ) . '">← ' . esc_html( trim( $plan->code . ' ' . $plan->title ) ) . '</a></p>';
		echo '<h2>Εισαγωγή από την πλατφόρμα αξιολόγησης</h2>';
		echo '<section class="sd-card"><h3>1. Αντιγραφή</h3><p>Από οποιαδήποτε από τις τρεις πηγές: ανοίξτε το, πατήστε <kbd>Ctrl</kbd>+<kbd>A</kbd> (επιλογή όλων), <kbd>Ctrl</kbd>+<kbd>C</kbd> (αντιγραφή) και επικολλήστε παρακάτω με <kbd>Ctrl</kbd>+<kbd>V</kbd>.</p><ul class="sd-steps">';
		echo '<li><strong>PDF της πλατφόρμας</strong> («Εξαγωγή Σχεδίου»): ο πιο σίγουρος τρόπος για στόχους, ενέργειες, μήνες, κριτήρια, εργαλεία. Δεν περιέχει Σκοπό και Αρχική Ανάγκη.</li>';
		echo '<li><strong>Οθόνη της πλατφόρμας</strong>: η κορυφή της «Επεξεργασίας φόρμας» δίνει Σκοπό / Βασική κατεύθυνση και Αρχική Ανάγκη. (Τα τικ στα εργαλεία δεν αντιγράφονται από τη φόρμα· γι’ αυτά το PDF.)</li>';
		echo '<li><strong>Word της εφαρμογής</strong> (Λήψη σε Word): όλα, μαζί με πηγές, πράξη Συλλόγου, συνέχεια/συνεργασία.</li></ul>';
		echo '<p class="sd-help">Μπορείτε να επικολλήσετε δύο κείμενα το ένα κάτω από το άλλο, π.χ. το PDF και την κορυφή της «Επεξεργασίας φόρμας». Με την «Ανάλυση» δεν αλλάζει τίποτα ακόμη.</p>';
		SD_Frontend::form_open( 'plan_import_preview' );
		echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '">';
		echo '<textarea name="text" rows="10" placeholder="Επικόλληση εδώ (Ctrl+V)"></textarea>';
		echo '<p><button class="sd-button" type="submit">Ανάλυση</button></p></form></section>';

		$res = get_transient( self::key( $plan->id ) );
		if ( ! is_array( $res ) ) {
			return;
		}
		echo '<section class="sd-card" id="proepiskopisi"><h3>2. Προεπισκόπηση</h3>';
		if ( $res['warnings'] ) {
			echo '<div class="sd-issues"><ul>';
			foreach ( $res['warnings'] as $w ) {
				echo '<li class="sd-issue-warning">' . esc_html( $w ) . '</li>';
			}
			echo '</ul></div>';
		}
		self::preview( $res );
		$found = self::found_fields( $res );
		if ( $res['goals'] ) {
			SD_Frontend::form_open( 'plan_import_apply' );
			echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '">';
			if ( $found ) {
				echo '<fieldset class="sd-sub"><legend>Να ενημερωθούν και τα δομικά στοιχεία</legend>';
				foreach ( $found as $f ) {
					echo '<label class="sd-inline"><input type="checkbox" name="use_' . esc_attr( $f[0] ) . '" value="1" checked> ' . esc_html( $f[1] ) . '</label><br>';
				}
				echo '</fieldset>';
			}
			$old = count( SD_Plans::goals( $plan->id ) );
			echo '<p class="sd-notice sd-error">Η εισαγωγή <strong>αντικαθιστά</strong> τη στοχοθεσία του σχεδίου' . ( $old ? ' (τώρα ' . (int) $old . ' στόχοι με τις ενέργειές τους)' : '' ) . '. Η ομάδα και ο/η συντονιστής/τρια δεν αλλάζουν.</p>';
			echo '<p><button class="sd-button" type="submit" onclick="return confirm(\'Να αντικατασταθεί η στοχοθεσία του σχεδίου με την εισαγωγή;\');">Εισαγωγή</button></p></form>';
		}
		SD_Frontend::form_open( 'plan_import_cancel' );
		echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '"><button class="sd-button sd-small sd-quiet" type="submit">Ακύρωση</button></form>';
		echo '</section>';
	}
}
