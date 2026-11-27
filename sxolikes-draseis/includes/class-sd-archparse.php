<?php
/**
 * Ανάγνωση εγγράφων της παλιάς πλατφόρμας αξιολόγησης (2021–2024) για το Αρχείο:
 * «Συλλογικός Προγραμματισμός», «Φόρμα Αποτύπωσης Σχεδίου Δράσης» (σχεδιασμός,
 * υλοποίηση, αποτίμηση) και «Ετήσια Έκθεση Εσωτερικής Αξιολόγησης».
 * Ονόματα (συντονιστές, εισηγητές) δεν κρατούνται.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_ArchParse {

	/** Βαθμός επίτευξης στόχων, όπως στην πλατφόρμα. */
	const DEGREES = array(
		4 => 'Πλήρως',
		3 => 'Σε μεγάλο βαθμό',
		2 => 'Σε μικρό βαθμό',
		1 => 'Καθόλου',
	);

	/** Ενότητες της αποτίμησης που κρατούνται ως κείμενο: κλειδί => ετικέτα. */
	const SECTIONS = array(
		'actions'      => 'Ενέργειες & χρονοδιάγραμμα υλοποίησης',
		'resources'    => 'Πόροι – μέσα – ερευνητικά εργαλεία',
		'criteria'     => 'Κριτήρια επιτυχίας της Δράσης',
		'evalproc'     => 'Διαδικασίες αξιολόγησης της Δράσης',
		'implement'    => 'Υλοποίηση της Δράσης',
		'changes'      => 'Αλλαγές σε σχέση με τον αρχικό σχεδιασμό',
		'facilitators' => 'Παράμετροι που διευκόλυναν την επίτευξη των στόχων',
		'material'     => 'Υλικό που παρήχθη ή αξιοποιήθηκε',
		'trainings'    => 'Επιμορφώσεις που υλοποιήθηκαν',
		'practices'    => 'Προτάσεις για αξιοποίηση των πρακτικών',
		'train_needs'  => 'Προτάσεις για αναγκαίες επιμορφώσεις',
		'feedback'     => 'Ανατροφοδότηση – Προτάσεις Συμβούλου Εκπαίδευσης',
	);

	/** Ετικέτες που στην αντιγραφή από PDF βρίσκονται μέσα σε γραμμή: ξεκινούν νέα γραμμή. */
	const SPLIT_LEGACY = '/\s*(Στόχος Βελτίωσης:|Σχέδιο Δράσης:|Άξονας:|Συντονιστ(?:ής|ρια|ές) Δράσης:)/u';
	const SPLIT_REPORT = '/\s*(\(Αξιολόγηση:\s*[1-4]\)|(?<![\p{L}])Θετικά σημεία(?=\s)|(?<![\p{L}])Σημεία προς βελτίωση(?=\s))\s*/u';

	private static function n( $s ) {
		return SD_PlanImport::norm( $s );
	}

	/** Μόνο γράμματα και ψηφία, για συγκρίσεις ετικετών. */
	private static function key( $s ) {
		$s = self::n( $s );
		$s = str_replace( 'μαθητριων', 'τριων', $s );
		return preg_replace( '/[^\p{L}\p{N}]+/u', '', $s );
	}

	/** Άξονας από ετικέτα (όλη η γραμμή) ή 0. */
	public static function axis( $line ) {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			foreach ( SD_Eval::AXES as $i => $a ) {
				$map[ self::key( $a[0] ) ] = $i;
			}
		}
		return $map[ self::key( $line ) ] ?? 0;
	}

	private static function is_function( $line ) {
		$k = self::key( $line );
		foreach ( SD_Eval::FUNCTIONS as $f ) {
			if ( self::key( $f ) === $k || self::key( str_replace( ' των ', ' ', $f ) ) === $k ) {
				return true;
			}
		}
		return false;
	}

	/** Τι έγγραφο είναι: report, collective, legacy ή plan. */
	public static function detect( $text ) {
		$head = self::n( mb_substr( (string) $text, 0, 1500 ) );
		if ( false !== mb_strpos( $head, 'εκθεση εσωτερικησ αξιολογησησ' ) ) {
			return 'report';
		}
		if ( false !== mb_strpos( $head, 'συλλογικοσ προγραμματισμοσ' ) ) {
			return 'collective';
		}
		$all = self::n( $text );
		if ( false !== mb_strpos( $head, 'φορμα αποτυπωσησ σχεδιου δρασησ' ) || ( false !== mb_strpos( $all, 'στοχοσ βελτιωσησ' ) && false !== mb_strpos( $all, 'σχεδιασμοσ τησ δρασησ' ) ) ) {
			return 'legacy';
		}
		return 'plan';
	}

	/** Σχολικό έτος από το «Σχολικό Έτος: 2023 - 2024» (έτος έναρξης) ή 0. */
	public static function year( $text ) {
		if ( preg_match( '/Σχολικό\s+Έτος:?\s*(20\d\d)\s*[-–\/]\s*(20\d\d)/u', (string) $text, $m ) && (int) $m[2] === (int) $m[1] + 1 ) {
			return (int) $m[1];
		}
		return 0;
	}

	/** Γραμμές χωρίς κεφαλίδες εξαγωγής και αριθμούς σελίδων. */
	private static function lines( $text, $split = '' ) {
		$out = array();
		if ( $split ) {
			$text = preg_replace( $split, "
$1
", (string) $text );
		}
		foreach ( SD_PlanImport::lines( $text ) as $l ) {
			$n = self::n( $l );
			if ( preg_match( '/^\d{1,2}$/', $l ) || preg_match( '/^(σχολικη μοναδα|σχολικο ετοσ|ημερομηνια εξαγωγησ)\b/u', $n ) ) {
				continue;
			}
			$out[] = $l;
		}
		return $out;
	}

	/** Ενώνει γραμμές: νέα γραμμή μετά από τελεία ή σε κουκκίδα/αρίθμηση, αλλιώς κενό. */
	private static function add( &$buf, $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			return;
		}
		if ( '' === $buf ) {
			$buf = $line;
		} elseif ( preg_match( '/[.;:!·»)]$/u', $buf ) || preg_match( '/^(•|-|\d+[.)]|[α-ω]\))/u', $line ) ) {
			$buf .= "\n" . $line;
		} else {
			$buf .= ' ' . $line;
		}
	}

	/** «4 = Πλήρως» → 4. */
	private static function degree( $line ) {
		if ( preg_match( '/^([1-4])\s*=/u', trim( $line ), $m ) ) {
			return (int) $m[1];
		}
		$n = self::n( $line );
		foreach ( self::DEGREES as $d => $l ) {
			if ( self::n( $l ) === $n ) {
				return $d;
			}
		}
		return 0;
	}

	/** Αντιστοιχίζει γραμμή σε επικεφαλίδα από λίστα [πρόθεμα => είδος]: [είδος, υπόλοιπο] ή null. */
	private static function heading( $line, $map ) {
		$n = self::n( $line );
		if ( mb_strlen( $n ) > 110 ) {
			return null;
		}
		foreach ( $map as $prefix => $kind ) {
			if ( 0 === mb_strpos( $n, $prefix ) ) {
				$rest = '';
				$pos  = mb_strpos( $line, ':' );
				if ( false !== $pos ) {
					$rest = trim( mb_substr( $line, $pos + 1 ) );
				}
				return array( $kind, $rest );
			}
		}
		return null;
	}

	/* =================== Φόρμα Αποτύπωσης Σχεδίου Δράσης =================== */

	const LEGACY = array(
		'στοχοσ βελτιωσησ'                        => 'goal',
		'σχεδιο δρασησ:'                          => 'title',
		'αξονασ:'                                 => 'axis',
		'συντονιστησ δρασησ'                      => 'skip',
		'συντονιστρια δρασησ'                     => 'skip',
		'α. σχεδιασμοσ τησ δρασησ'                => 'keep',
		'αναγκαιοτητα'                            => 'need',
		'ενεργειεσ & χρονοδιαγραμμα'              => 'actions',
		'ενεργειεσ και χρονοδιαγραμμα'            => 'actions',
		'ποροι'                                   => 'resources',
		'κριτηρια επιτυχιασ'                      => 'criteria',
		'διαδικασιεσ αξιολογησησ'                 => 'evalproc',
		'ανατροφοδοτηση'                          => 'feedback',
		'β. υλοποιηση τησ δρασησ'                 => 'implement',
		'περιγραφη'                               => 'implement',
		'γ. αποτιμηση τησ δρασησ'                 => 'keep',
		'αλλαγεσ σε σχεση'                        => 'changes',
		'αποτελεσματα τησ δρασησ'                 => 'achieved',
		'σημαντικοτερα αποτελεσματα'              => 'achieved',
		'βαθμοσ επιτευξησ'                        => 'degree',
		'παραμετροι που διευκολυναν'              => 'facilitators',
		'δυσκολιεσ'                               => 'difficulties',
		'υλικο που παρηχθη'                       => 'material',
		'επιμορφωσεισ που τυχον'                  => 'trainings',
		'προτασεισ για αξιοποιηση'                => 'practices',
		'προτασεισ για αναγκαιεσ επιμορφωσεισ'    => 'train_needs',
		'προτασεισ για συνεχιση'                  => 'next_steps',
		'προτασεισ για τη συνεχεια'               => 'next_steps',
	);

	/**
	 * Ένα σχέδιο της παλιάς πλατφόρμας με την αποτίμησή του.
	 * Επιστρέφει title, axis, goal, need και eval (degree, achieved, difficulties, next_steps, sections).
	 */
	public static function legacy( $text ) {
		$b    = array_fill_keys( array( 'goal', 'title', 'axis', 'need', 'achieved', 'degree', 'difficulties', 'next_steps' ), '' );
		$b   += array_fill_keys( array_keys( self::SECTIONS ), '' );
		$cur  = '';
		foreach ( self::lines( $text, self::SPLIT_LEGACY ) as $line ) {
			$h = self::heading( $line, self::LEGACY );
			if ( $h && 'keep' === $h[0] ) {
				continue;
			}
			if ( $h && 'feedback' === $h[0] ) {
				$cur = 'feedback';
				continue;
			}
			if ( $h ) {
				$cur = $h[0];
				if ( 'skip' !== $cur && '' !== $h[1] ) {
					self::add( $b[ $cur ], $h[1] );
				}
				continue;
			}
			if ( '' === $cur || 'skip' === $cur ) {
				continue;
			}
			self::add( $b[ $cur ], $line );
		}
		$title = trim( preg_replace( '/\s+/u', ' ', $b['title'] ) );
		$out   = array(
			'title' => trim( $title, " \t«»\"'" ),
			'axis'  => self::axis( $b['axis'] ),
			'goal'  => $b['goal'],
			'need'  => $b['need'],
			'eval'  => array(
				'degree'       => self::degree( $b['degree'] ),
				'achieved'     => $b['achieved'],
				'difficulties' => $b['difficulties'],
				'next_steps'   => $b['next_steps'],
				'sections'     => array(),
			),
		);
		foreach ( array_keys( self::SECTIONS ) as $k ) {
			if ( '' !== $b[ $k ] ) {
				$out['eval']['sections'][ $k ] = $b[ $k ];
			}
		}
		return $out;
	}

	/** Έχει η αποτίμηση περιεχόμενο; */
	public static function has_eval( $ev ) {
		return ! empty( $ev['degree'] ) || '' !== trim( ( $ev['achieved'] ?? '' ) . ( $ev['difficulties'] ?? '' ) . ( $ev['next_steps'] ?? '' ) ) || ! empty( $ev['sections'] );
	}

	/* =================== Συλλογικός Προγραμματισμός =================== */

	/** Όλα τα σχέδια της χρονιάς: [[title, axis, goal]]. */
	public static function collective( $text ) {
		$plans = array();
		$axis  = 0;
		$block = array();
		$flush = function () use ( &$plans, &$axis, &$block ) {
			if ( ! $block || ! $axis ) {
				$block = array();
				return;
			}
			$joined = implode( "\n", $block );
			$title  = '';
			if ( preg_match_all( '/«([^»]+)»/u', str_replace( "\n", ' ', $joined ), $m ) ) {
				$title = trim( preg_replace( '/\s+/u', ' ', end( $m[1] ) ) );
				$joined = preg_replace( '/«[^»]+»/u', '', str_replace( "\n", ' ', $joined ) );
			}
			$goal = trim( preg_replace( '/\s+/u', ' ', $joined ) );
			if ( '' !== $goal || '' !== $title ) {
				$plans[] = array(
					'title' => $title,
					'axis'  => $axis,
					'goal'  => $goal,
				);
			}
			$block = array();
		};
		foreach ( self::lines( $text ) as $line ) {
			$n = self::n( $line );
			$a = self::axis( $line );
			if ( $a ) {
				$flush();
				$axis = $a;
				continue;
			}
			if ( self::is_function( $line ) || 0 === mb_strpos( $n, 'συλλογικοσ προγραμματισμοσ' ) || 0 === mb_strpos( $n, 'δεν εχει τεθει' ) ) {
				continue;
			}
			if ( preg_match( '/^(a\/a|α\/α|aα|αα)?\s*στοχοσ βελτιωσησ|^(σχεδιο|ομαδασ|δρασησ|συντονιστησ|συντονιστρια)( |$)|^ομαδασ δρασησ$/u', $n ) || preg_match( '/στοχοσ βελτιωσησ/u', $n ) && mb_strlen( $n ) < 60 ) {
				continue;
			}
			// Ονόματα συντονιστών: μόνο κεφαλαία, έως 4 λέξεις, χωρίς σημεία στίξης.
			if ( preg_match( '/^[\p{Lu}\s.\-]+$/u', $line ) && ! preg_match( '/\p{Ll}/u', $line ) && count( preg_split( '/\s+/u', trim( $line ) ) ) <= 4 && false === mb_strpos( $line, '«' ) ) {
				continue;
			}
			// Στο PDF οι στήλες ανακατεύονται: λέξεις μόνο με κεφαλαία μέσα σε κείμενο με πεζά είναι ονόματα συντονιστών.
			if ( preg_match( '/\p{Ll}/u', $line ) ) {
				$line = trim( preg_replace( '/(?<![\p{L}])\p{Lu}{4,}(?![\p{L}.])/u', '', $line ) );
				$line = preg_replace( '/\s{2,}/u', ' ', $line );
			}
			$block[] = preg_replace( '/^\d+\s+(?=\p{Lu})/u', '', $line );
		}
		$flush();
		return $plans;
	}

	/* =================== Ετήσια Έκθεση Εσωτερικής Αξιολόγησης =================== */

	/**
	 * axes: [n => [rating, text]], strengths/improve: [λειτουργία => κείμενο],
	 * degree, results, difficulties, practices, train_needs: [κείμενο].
	 */
	public static function report( $text ) {
		$r    = array(
			'axes'         => array(),
			'strengths'    => array(),
			'improve'      => array(),
			'degree'       => 0,
			'results'      => '',
			'difficulties' => '',
			'practices'    => '',
			'train_needs'  => array(),
		);
		$mode = 'identity';
		$axis = 0;
		$func = '';
		$item = null;
		$in_c = false;
		foreach ( self::lines( $text, self::SPLIT_REPORT ) as $line ) {
			$n = self::n( $line );
			if ( 0 === mb_strpos( $n, 'β. συνολικη αποτιμηση' ) ) {
				$mode = '';
				continue;
			}
			if ( 0 === mb_strpos( $n, 'γ. αποτιμηση των δρασεων' ) ) {
				$in_c = true;
				$mode = 'skip';
				continue;
			}
			if ( 'identity' === $mode ) {
				continue;
			}
			if ( ! $in_c ) {
				if ( self::is_function( $line ) ) {
					$func = trim( $line );
					$mode = '';
					continue;
				}
				$a = self::axis( $line );
				if ( $a ) {
					$axis                = $a;
					$r['axes'][ $axis ] = array( 0, '' );
					$mode               = 'axis';
					continue;
				}
				if ( preg_match( '/^\(?αξιολογηση:?\s*([1-4])\)?$/u', $n, $m ) && $axis ) {
					$r['axes'][ $axis ][0] = (int) $m[1];
					continue;
				}
				if ( 'θετικα σημεια' === $n ) {
					$mode = 'strengths';
					continue;
				}
				if ( 'σημεια προσ βελτιωση' === $n ) {
					$mode = 'improve';
					continue;
				}
				if ( 'axis' === $mode ) {
					self::add( $r['axes'][ $axis ][1], $line );
				} elseif ( 'strengths' === $mode || 'improve' === $mode ) {
					$k = $func ? $func : 'Γενικά';
					$r[ $mode ][ $k ] = $r[ $mode ][ $k ] ?? '';
					$r[ $mode ][ $k ] .= ( '' === $r[ $mode ][ $k ] ? '' : "\n" ) . $line;
				}
				continue;
			}
			// Ενότητα Γ.
			if ( 0 === mb_strpos( $n, 'βαθμοσ επιτευξησ' ) ) {
				$mode = 'degree';
				continue;
			}
			if ( 0 === mb_strpos( $n, 'σημαντικοτερα αποτελεσματα' ) ) {
				$mode = 'results';
				continue;
			}
			if ( 0 === mb_strpos( $n, 'δυσκολιεσ' ) && mb_strlen( $n ) < 60 ) {
				$mode = 'difficulties';
				continue;
			}
			if ( 0 === mb_strpos( $n, 'αναδειξη πρακτικων' ) ) {
				$mode = 'practices';
				continue;
			}
			if ( 0 === mb_strpos( $n, 'προτασεισ για αναγκαιεσ επιμορφωσεισ' ) ) {
				$mode = 'train';
				continue;
			}
			if ( 'degree' === $mode ) {
				$r['degree'] = self::degree( $line );
				$mode        = 'skip';
			} elseif ( 'results' === $mode || 'difficulties' === $mode || 'practices' === $mode ) {
				if ( '(προαιρετικα).' === $n || '(προαιρετικα)' === $n ) {
					continue;
				}
				self::add( $r[ $mode ], $line );
			} elseif ( 'train' === $mode ) {
				if ( preg_match( '/^θεμα\s*\d+$/u', $n ) ) {
					if ( $item && '' !== $item[1] ) {
						$r['train_needs'][] = ( $item[0] ? $item[0] . ': ' : '' ) . $item[1];
					}
					$item = array( '', '' );
					continue;
				}
				if ( ! $item || 'αξονασ' === $n || 'ειδικοτερο θεμα' === $n ) {
					continue;
				}
				$a = self::axis( $line );
				if ( $a && '' === $item[0] ) {
					$item[0] = SD_Eval::axis_label( $a );
				} else {
					self::add( $item[1], $line );
				}
			}
		}
		if ( $item && '' !== $item[1] ) {
			$r['train_needs'][] = ( $item[0] ? $item[0] . ': ' : '' ) . $item[1];
		}
		return $r;
	}

	/** Ομοιότητα τίτλων (κοινές λέξεις 4+ γραμμάτων ως προς τον μικρότερο). */
	public static function similar( $a, $b ) {
		$w = function ( $s ) {
			$s = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', self::n( $s ) );
			return array_unique( array_filter( preg_split( '/\s+/u', $s ), function ( $x ) {
				return mb_strlen( $x ) >= 4;
			} ) );
		};
		$x = $w( $a );
		$y = $w( $b );
		if ( ! $x || ! $y ) {
			return 0;
		}
		return count( array_intersect( $x, $y ) ) / min( count( $x ), count( $y ) );
	}
}
