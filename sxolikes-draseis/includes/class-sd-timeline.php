<?php
/**
 * Αρχική οθόνη ως γραμμή χρόνου: για κάθε χρήστη τα βήματα της χρονιάς,
 * τι ολοκληρώθηκε, τι εκκρεμεί και τι καθυστερεί.
 *
 * Τρεις οπτικές:
 * - εκπαιδευτικός τμήματος: τα έντυπα κάθε τμήματός του·
 * - συντονιστής/τρια Σχεδίου Δράσης: τα βήματα του σχεδίου·
 * - Διεύθυνση: η εικόνα όλου του σχολείου.
 *
 * Τίποτα δεν αποθηκεύεται εδώ· όλα υπολογίζονται από τα υπάρχοντα δεδομένα.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Timeline {

	const OPT_DEADLINE_F2   = 'sd_deadline_f2';
	const OPT_DEADLINE_SXTM = 'sd_deadline_sxtm';

	/** Καταστάσεις βήματος: [ετικέτα, κλάση]. */
	const STATES = array(
		'done'     => array( 'Ολοκληρώθηκε', 'done' ),
		'review'   => array( 'Υποβλήθηκε · περιμένει έλεγχο', 'review' ),
		'fix'      => array( 'Επιστράφηκε για διόρθωση', 'fix' ),
		'todo'     => array( 'Εκκρεμεί', 'todo' ),
		'late'     => array( 'Εκπρόθεσμο', 'late' ),
		'optional' => array( 'Όταν χρειαστεί', 'optional' ),
		'later'    => array( 'Αργότερα στη χρονιά', 'later' ),
	);

	public static function deadline_f2() {
		return (string) get_option( self::OPT_DEADLINE_F2, '' );
	}

	public static function deadline_sxtm() {
		return (string) get_option( self::OPT_DEADLINE_SXTM, '' );
	}

	/* =================== Βοηθητικά =================== */

	private static function today() {
		return current_time( 'Y-m-d' );
	}

	/** Εκκρεμές βήμα με προθεσμία που πέρασε γίνεται «εκπρόθεσμο». */
	private static function with_deadline( $state, $deadline ) {
		if ( $deadline && in_array( $state, array( 'todo', 'fix' ), true ) && self::today() > $deadline ) {
			return 'late';
		}
		return $state;
	}

	private static function deadline_text( $deadline, $state ) {
		if ( ! $deadline ) {
			return '';
		}
		$txt = 'Προθεσμία ' . mysql2date( 'd/m/Y', $deadline );
		if ( in_array( $state, array( 'todo', 'fix' ), true ) ) {
			$days = (int) floor( ( strtotime( $deadline ) - strtotime( self::today() ) ) / DAY_IN_SECONDS );
			if ( 0 === $days ) {
				$txt .= ' · λήγει σήμερα';
			} elseif ( $days > 0 && $days <= 7 ) {
				$txt .= ' · σε ' . $days . ( 1 === $days ? ' ημέρα' : ' ημέρες' );
			}
		}
		return $txt;
	}

	/** Κατάσταση εντύπου με ροή πρόχειρο → υποβολή → έλεγχος. */
	private static function form_state( $status ) {
		switch ( (string) $status ) {
			case 'elegxthike':
			case 'egkrithike':
				return 'done';
			case 'ypovlithike':
				return 'review';
			case 'epistrafike':
				return 'fix';
		}
		return 'todo';
	}

	/**
	 * Ένα βήμα: title, state, detail, deadline, url, cta.
	 */
	private static function step( $title, $state, $detail = '', $url = '', $cta = '', $deadline = '' ) {
		$state = self::with_deadline( $state, $deadline );
		return compact( 'title', 'state', 'detail', 'url', 'cta', 'deadline' );
	}

	/* =================== Βήματα τμήματος =================== */

	public static function class_steps( $class ) {
		$steps = array();

		// 1. Έντυπο 1Α.
		$f1a   = SD_Workshops::form_of_class( $class->id );
		$state = $f1a ? self::form_state( $f1a->status ) : 'todo';
		$steps[] = self::step(
			'Έντυπο 1Α · Εργαστήρια Δεξιοτήτων',
			$state,
			$f1a ? '' : 'Δεν έχει ξεκινήσει.',
			SD_Frontend::url( 'ergastiria', array( 'class' => $class->id ) ),
			in_array( $state, array( 'done', 'review' ), true ) ? 'Προβολή' : 'Συνέχεια',
			SD_Workshops::deadline()
		);

		// 2. Ενεργός Πολίτης (Έντυπο 2): όλες οι δράσεις του τμήματος.
		$deps = SD_Citizen::of_class( $class->id );
		if ( ! $deps ) {
			$steps[] = self::step( 'Ενεργός Πολίτης · Έντυπο 2', 'todo', 'Δεν έχει καταχωριστεί δράση.', SD_Frontend::url( 'politis' ), 'Νέα δράση', self::deadline_f2() );
		} else {
			$states = array();
			foreach ( $deps as $d ) {
				$states[] = self::form_state( $d->status );
			}
			$state = in_array( 'fix', $states, true ) ? 'fix' : ( in_array( 'todo', $states, true ) ? 'todo' : ( in_array( 'review', $states, true ) ? 'review' : 'done' ) );
			$names = array();
			foreach ( $deps as $d ) {
				$names[] = '«' . $d->title . '»';
			}
			$first = $deps[0];
			foreach ( $deps as $i => $d ) {
				if ( $states[ $i ] === $state ) {
					$first = $d;
					break;
				}
			}
			$steps[] = self::step( 'Ενεργός Πολίτης · Έντυπο 2', $state, implode( ', ', $names ), SD_Frontend::url( 'politis', array( 'id' => $first->id ) ), in_array( $state, array( 'done', 'review' ), true ) ? 'Προβολή' : 'Συνέχεια', self::deadline_f2() );
		}

		// 3. Σχέδιο Δράσης Τμήματος (Πλατφόρμα 21+).
		$plan    = SD_ErgPlan::class_plan( $class->id );
		$steps[] = self::step(
			'Σχέδιο Δράσης Τμήματος · Πλατφόρμα 21+',
			$plan ? 'done' : 'todo',
			$plan ? 'Αποθηκεύτηκε ' . mysql2date( 'd/m/Y', $plan->updated_at ) . '. Μπορεί να συμπληρώνεται όλη τη χρονιά.' : 'Συμπληρώνεται εν μέρει μόνο του από το 1Α.',
			SD_Frontend::url( 'ergastiria', array( 'sxedio' => $class->id ) ),
			$plan ? 'Άνοιγμα' : 'Συμπλήρωση',
			self::deadline_sxtm()
		);

		// 4. Επισκέψεις και φορείς (Έντυπο 3): προαιρετικό.
		global $wpdb;
		$reqs  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, status FROM ' . SD_Requests::t() . ' WHERE class_id = %d', $class->id ) );
		$count = array();
		foreach ( $reqs as $r ) {
			$count[ $r->status ] = ( $count[ $r->status ] ?? 0 ) + 1;
		}
		if ( ! empty( $count['epistrafike'] ) ) {
			$state = 'fix';
		} elseif ( ! empty( $count['proxeiro'] ) ) {
			$state = 'todo';
		} elseif ( ! empty( $count['ypovlithike'] ) ) {
			$state = 'review';
		} else {
			$state = 'optional';
		}
		$parts = array();
		foreach ( SD_Requests::STATUS as $k => $l ) {
			if ( ! empty( $count[ $k ] ) ) {
				$parts[] = $l . ': ' . $count[ $k ];
			}
		}
		$steps[] = self::step( 'Επισκέψεις και φορείς · Έντυπο 3', $state, $parts ? implode( ' · ', $parts ) : 'Μόνο όταν η τάξη βγαίνει από το σχολείο ή έρχεται φορέας. Υποβάλλεται 10 εργάσιμες νωρίτερα.', SD_Frontend::url( 'aitimata' ), $reqs ? 'Άνοιγμα' : 'Νέο αίτημα' );

		// 5. Αποτίμηση (Έντυπο 4).
		$f4 = SD_F4::form_of_class( $class->id );
		if ( $f4 ) {
			$state = self::form_state( $f4->status );
		} else {
			$dl    = SD_F4::deadline();
			$state = ( $dl && strtotime( $dl ) - strtotime( self::today() ) < 45 * DAY_IN_SECONDS ) || (int) current_time( 'n' ) >= 4 && (int) current_time( 'n' ) <= 7 ? 'todo' : 'later';
		}
		$steps[] = self::step( 'Αποτίμηση · Έντυπο 4', $state, 'later' === $state ? 'Στο τέλος της χρονιάς. Προσυμπληρώνεται από το 1Α και το Έντυπο 2.' : '', SD_Frontend::url( 'apotimisi', array( 'class' => $class->id ) ), in_array( $state, array( 'done', 'review' ), true ) ? 'Προβολή' : ( 'later' === $state ? 'Ματιά' : 'Συνέχεια' ), SD_F4::deadline() );

		return $steps;
	}

	/* =================== Βήματα Σχεδίου Δράσης =================== */

	public static function plan_steps( $plan ) {
		$issues = SD_Plans::issues( $plan );
		$url    = SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) );
		$errors = array(
			'team'  => array(),
			'basic' => array(),
			'goals' => array(),
		);
		foreach ( $issues as $i ) {
			$t = $i[1];
			if ( false !== strpos( $t, 'Συντονιστ' ) || false !== strpos( $t, 'ομάδα' ) ) {
				$errors['team'][] = $t;
			} elseif ( false !== strpos( $t, 'σκοπ' ) || false !== strpos( $t, 'αναγκαιότητ' ) || false !== strpos( $t, 'πηγές' ) ) {
				if ( 'error' === $i[0] ) {
					$errors['basic'][] = $t;
				}
			} elseif ( 'error' === $i[0] ) {
				$errors['goals'][] = $t;
			}
		}
		$steps   = array();
		$steps[] = self::step( 'Ομάδα Δράσης (συντονιστής/τρια και 2–5 εκπαιδευτικοί)', $errors['team'] ? 'todo' : 'done', implode( ' ', $errors['team'] ), $url, 'Άνοιγμα' );
		$steps[] = self::step( 'Δομικά στοιχεία (σκοπός, τεκμηρίωση, πηγές)', $errors['basic'] ? 'todo' : 'done', implode( ' ', array_slice( $errors['basic'], 0, 2 ) ), $url, 'Άνοιγμα' );
		$steps[] = self::step( 'Στοχοθεσία (στόχοι, ενέργειες, κριτήρια)', $errors['goals'] ? 'todo' : 'done', $errors['goals'] ? count( $errors['goals'] ) . ' σημεία θέλουν συμπλήρωση. ' . $errors['goals'][0] : '', $url, 'Άνοιγμα' );

		$rows  = SD_F4::plan_rows( $plan );
		$done  = 0;
		foreach ( $rows as $r ) {
			if ( in_array( $r[3], array( SD_Workshops::STATUS['ypovlithike'], SD_Workshops::STATUS['elegxthike'] ), true ) ) {
				$done++;
			}
		}
		$steps[] = self::step(
			'Αποτίμηση (συμβολή των τάξεων από τα Έντυπα 4)',
			$rows && $done === count( $rows ) ? 'done' : ( $rows ? 'todo' : 'later' ),
			$rows ? 'Τμήματα που δήλωσαν συμβολή: ' . count( $rows ) . ', με υποβληθέν Έντυπο 4: ' . $done . '.' : 'Συγκεντρώνεται μόνο του όταν οι τάξεις συμπληρώσουν το Έντυπο 4.',
			SD_Frontend::url( 'apotimisi', array( 'sxedio' => $plan->id ) ),
			'Συγκεντρωτικό'
		);
		return $steps;
	}

	/* =================== Βήματα Διεύθυνσης =================== */

	public static function school_steps( $year ) {
		$classes = SD_Data::classes( $year->id );
		$n       = count( $classes );
		$steps   = array();

		// Τμήματα και υπεύθυνοι.
		$no_lead = 0;
		foreach ( $classes as $c ) {
			$has = false;
			foreach ( SD_Data::class_teachers( $c->id ) as $t ) {
				if ( 'ergastiria' === $t->class_role ) {
					$has = true;
				}
			}
			$no_lead += $has ? 0 : 1;
		}
		$steps[] = self::step( 'Τμήματα και υπεύθυνοι εκπαιδευτικοί', $n && ! $no_lead ? 'done' : 'todo', $n ? $n . ' τμήματα' . ( $no_lead ? ', ' . $no_lead . ' χωρίς υπεύθυνο Εργαστηρίων.' : '.' ) : 'Δεν έχουν οριστεί τμήματα.', SD_Frontend::url( 'taxeis' ), 'Τμήματα' );

		// Σχέδια Δράσης.
		$plans = SD_Plans::plans( $year->id );
		$ready = 0;
		foreach ( $plans as $p ) {
			$err = false;
			foreach ( SD_Plans::issues( $p ) as $i ) {
				$err = $err || 'error' === $i[0];
			}
			$ready += $err ? 0 : 1;
		}
		$steps[] = self::step( 'Σχέδια Δράσης εσωτερικής αξιολόγησης', $plans && $ready === count( $plans ) ? 'done' : 'todo', $plans ? 'Πλήρη ' . $ready . ' από ' . count( $plans ) . '.' : 'Δεν έχουν καταχωριστεί.', SD_Frontend::url( 'sxedia' ), 'Σχέδια' );

		// Έντυπα με ροή υποβολής/ελέγχου: 1Α, 2, 4.
		$f1a = array();
		$f4  = array();
		foreach ( $classes as $c ) {
			$f      = SD_Workshops::form_of_class( $c->id );
			$f1a[]  = $f ? $f->status : '';
			$g      = SD_F4::form_of_class( $c->id );
			$f4[]   = $g ? $g->status : '';
		}
		$deps = array();
		foreach ( SD_Citizen::of_year( $year->id ) as $d ) {
			$deps[] = $d->status;
		}
		$steps[] = self::count_step( 'Έντυπα 1Α', $f1a, $n, SD_Frontend::url( 'ergastiria' ), SD_Workshops::deadline() );
		$steps[] = self::count_step( 'Ενεργός Πολίτης · Έντυπα 2', $deps, $n, SD_Frontend::url( 'politis' ), self::deadline_f2() );

		// Σχέδια τμημάτων και σχολικής μονάδας.
		$with = 0;
		foreach ( $classes as $c ) {
			$with += SD_ErgPlan::class_plan( $c->id ) ? 1 : 0;
		}
		$school  = SD_ErgPlan::school_plan( $year->id );
		$steps[] = self::step( 'Εργαστήρια: Σχέδια τμημάτων και Σχέδιο σχολικής μονάδας', $n && $with === $n && $school ? 'done' : 'todo', 'Σχέδια τμημάτων: ' . $with . ' από ' . $n . '. Σχέδιο σχολικής μονάδας: ' . ( $school ? 'αποθηκεύτηκε' : 'όχι ακόμη' ) . '.', SD_Frontend::url( 'ergastiria', array( 'sxolio' => 1 ) ), 'Σχέδιο σχολικής μονάδας', self::deadline_sxtm() );

		// Επισκέψεις που περιμένουν πράξη.
		$pending = SD_Requests::pending_count( $year->id );
		$steps[] = self::step( 'Επισκέψεις · πράξεις Συλλόγου', $pending ? 'review' : 'optional', $pending ? $pending . ' αιτήματα περιμένουν πράξη Συλλόγου.' : 'Κανένα αίτημα σε αναμονή.', SD_Frontend::url( 'aitimata' ), 'Επισκέψεις' );

		$steps[] = self::count_step( 'Αποτίμηση · Έντυπα 4', $f4, $n, SD_Frontend::url( 'apotimisi' ), SD_F4::deadline(), true );

		$steps[] = self::step( 'Πίνακες για Σύμβουλο, myschool και πλατφόρμες', 'optional', 'Έτοιμοι πάντα από τα στοιχεία που έχουν καταχωριστεί.', SD_Frontend::url( 'exagoges' ), 'Εξαγωγές' );
		return $steps;
	}

	/** Βήμα για σύνολο εντύπων: πόσα υποβλήθηκαν, πόσα περιμένουν έλεγχο. */
	private static function count_step( $title, $statuses, $classes, $url, $deadline, $end_of_year = false ) {
		$c = array_count_values( array_map( 'strval', $statuses ) );
		$checked   = $c['elegxthike'] ?? 0;
		$submitted = $c['ypovlithike'] ?? 0;
		$returned  = $c['epistrafike'] ?? 0;
		$total     = max( count( $statuses ), $classes );
		$open      = $total - $checked - $submitted;
		if ( $submitted ) {
			$state = 'review';
		} elseif ( $total && 0 === $open ) {
			$state = 'done';
		} elseif ( $end_of_year && ! $checked && ! $returned && ! array_filter( $statuses ) ) {
			$state = 'later';
		} else {
			$state = 'todo';
		}
		$detail = 'Ελέγχθηκαν ' . $checked . ' · για έλεγχο ' . $submitted . ' · σε εξέλιξη ή επιστροφή ' . max( 0, $open ) . ( $total ? ' (σύνολο ' . $total . ')' : '' ) . '.';
		// Μετά την προθεσμία, όσα δεν υποβλήθηκαν κάνουν το βήμα «εκπρόθεσμο».
		return self::step( $title, $state, $detail, $url, $submitted ? 'Έλεγχος' : 'Άνοιγμα', $deadline );
	}

	/* =================== Εμφάνιση =================== */

	/** Η λίστα των βημάτων ως κάθετη γραμμή χρόνου. */
	public static function render_steps( $steps ) {
		echo '<ol class="sd-tl">';
		foreach ( $steps as $s ) {
			list( $label, $cls ) = self::STATES[ $s['state'] ];
			echo '<li class="sd-tl-step sd-tl-' . esc_attr( $cls ) . '"><span class="sd-tl-dot" aria-hidden="true"></span><div class="sd-tl-body">';
			echo '<div class="sd-tl-head"><strong>' . esc_html( $s['title'] ) . '</strong> <span class="sd-tl-state">' . esc_html( $label ) . '</span></div>';
			$dl = self::deadline_text( $s['deadline'], $s['state'] );
			if ( $s['detail'] || $dl ) {
				echo '<div class="sd-tl-detail">' . esc_html( $s['detail'] );
				if ( $dl ) {
					echo ( $s['detail'] ? ' ' : '' ) . '<span class="sd-tl-dl">' . esc_html( $dl ) . '</span>';
				}
				echo '</div>';
			}
			echo '</div>';
			if ( $s['url'] ) {
				$primary = in_array( $s['state'], array( 'todo', 'fix', 'late' ), true );
				echo '<a class="sd-button sd-small' . ( $primary ? '' : ' sd-quiet' ) . '" href="' . esc_url( $s['url'] ) . '">' . esc_html( $s['cta'] ? $s['cta'] : 'Άνοιγμα' ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ol>';
	}

	/** Πρόοδος: «3 από 5». Τα προαιρετικά και τα «αργότερα» δεν μετρούν. */
	public static function progress( $steps ) {
		$all  = 0;
		$done = 0;
		foreach ( $steps as $s ) {
			if ( in_array( $s['state'], array( 'optional', 'later' ), true ) ) {
				continue;
			}
			$all++;
			$done += in_array( $s['state'], array( 'done', 'review' ), true ) ? 1 : 0;
		}
		return array( $done, $all );
	}

	public static function progress_html( $steps ) {
		list( $done, $all ) = self::progress( $steps );
		if ( ! $all ) {
			return '';
		}
		$pct = (int) round( 100 * $done / $all );
		return '<div class="sd-progress" role="img" aria-label="' . esc_attr( $done . ' από ' . $all . ' βήματα' ) . '"><span style="width:' . $pct . '%"></span></div><span class="sd-progress-txt">' . (int) $done . ' από ' . (int) $all . ' βήματα</span>';
	}

	/** «Τι να κάνω τώρα»: τα επείγοντα βήματα όλων των ενοτήτων. */
	public static function urgent( $groups ) {
		$order = array(
			'late' => 0,
			'fix'  => 1,
			'todo' => 2,
		);
		$out = array();
		foreach ( $groups as $g ) {
			foreach ( $g['steps'] as $s ) {
				if ( isset( $order[ $s['state'] ] ) ) {
					$s['group'] = $g['title'];
					$out[]      = $s;
				}
			}
		}
		usort(
			$out,
			function ( $a, $b ) use ( $order ) {
				$x = $order[ $a['state'] ] <=> $order[ $b['state'] ];
				if ( $x ) {
					return $x;
				}
				return strcmp( $a['deadline'] ? $a['deadline'] : '9999', $b['deadline'] ? $b['deadline'] : '9999' );
			}
		);
		return $out;
	}
}
