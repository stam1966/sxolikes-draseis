<?php
/**
 * Κανόνες ελέγχου και ειδοποιήσεις.
 *
 * - Κανείς δεν ελέγχει δικό του έντυπο ή έντυπο τμήματός του.
 * - Ό,τι υποβάλλει μέλος της Επιτροπής ή της Διεύθυνσης το ελέγχει η Διεύθυνση.
 * - Email σε όσους μπορούν να ελέγξουν, όταν υποβάλλεται κάτι.
 * - Email στον/στην εκπαιδευτικό, όταν το έντυπο ελέγχεται ή επιστρέφεται.
 * - Υπενθυμίσεις μόνο στις ορισμένες ημέρες πριν από προθεσμία ή ενέργεια Σχεδίου Δράσης
 *   (προεπιλογή 7 και 2)· προαιρετική εβδομαδιαία σύνοψη ελέγχου τη Δευτέρα.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Notify {

	const OPT      = 'sd_notify';
	const OPT_LAST = 'sd_notify_last';
	const CRON     = 'sd_daily_notify';

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'daily' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			// Κάθε πρωί γύρω στις 07:00 (ώρα ιστότοπου). Το WP-Cron τρέχει με την πρώτη επίσκεψη μετά.
			$t = strtotime( 'tomorrow 07:00', current_time( 'timestamp' ) ) - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			wp_schedule_event( $t, 'daily', self::CRON );
		}
	}

	public static function cfg() {
		$v = get_option( self::OPT, array() );
		// Μία φορά: ρυθμίσεις αποθηκευμένες με τις παλιές προεπιλογές (0.13.0) → μόνο 7 και 2 ημέρες, χωρίς σύνοψη.
		if ( is_array( $v ) && $v && empty( $v['v2'] ) ) {
			if ( isset( $v['days'] ) && '7,2,0' === $v['days'] ) {
				$v['days']   = '7,2';
				$v['digest'] = 0;
			}
			$v['v2'] = 1;
			update_option( self::OPT, $v, false );
		}
		return array_merge(
			array(
				'submit'    => 1,
				'decision'  => 1,
				'reminders' => 1,
				'weekly'    => 1, // εβδομαδιαία αναφορά στη Διεύθυνση, κάθε Δευτέρα
				'digest'    => 0, // σύνοψη ελέγχου: κλειστή· αν ανοίξει, μόνο τη Δευτέρα
				'days'      => '7,2',
			),
			is_array( $v ) ? $v : array()
		);
	}

	private static function days() {
		$out = array();
		foreach ( explode( ',', (string) self::cfg()['days'] ) as $d ) {
			if ( '' !== trim( $d ) && is_numeric( trim( $d ) ) ) {
				$out[] = max( 0, min( 60, (int) trim( $d ) ) );
			}
		}
		return $out ? array_unique( $out ) : array( 7, 2 );
	}

	/* =================== Ποιος ελέγχει =================== */

	/**
	 * Μπορεί ο τρέχων χρήστης να ελέγξει έντυπο που υπέβαλε ο $submitter για το τμήμα $class_id;
	 */
	public static function may_review( $submitter, $class_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! user_can( $user_id, SD_Roles::CAP_REVIEW ) ) {
			return false;
		}
		if ( (int) $submitter === $user_id || ( $class_id && SD_Workshops::is_class_teacher( $class_id, $user_id ) ) ) {
			return false;
		}
		if ( $submitter && user_can( (int) $submitter, SD_Roles::CAP_REVIEW ) && ! user_can( $user_id, SD_Roles::CAP_MANAGE ) ) {
			// Έντυπο μέλους Επιτροπής/Διεύθυνσης: οριστικός έλεγχος από τη Διεύθυνση.
			// Εξαίρεση: έντυπο της Διεύθυνσης όταν δεν υπάρχει άλλος χρήστης Διεύθυνσης να το ελέγξει.
			return user_can( (int) $submitter, SD_Roles::CAP_MANAGE ) && ! self::other_manager( $submitter, $class_id );
		}
		return true;
	}

	/** Υπάρχει χρήστης Διεύθυνσης, εκτός από αυτόν που υπέβαλε και τους εκπαιδευτικούς του τμήματος; */
	private static function other_manager( $submitter, $class_id ) {
		static $cache = array();
		$k = (int) $submitter . '-' . (int) $class_id;
		if ( ! isset( $cache[ $k ] ) ) {
			$cache[ $k ] = false;
			foreach ( SD_Roles::people() as $u ) {
				if ( (int) $u->ID !== (int) $submitter && user_can( $u, SD_Roles::CAP_MANAGE ) && ! ( $class_id && SD_Workshops::is_class_teacher( $class_id, $u->ID ) ) ) {
					$cache[ $k ] = true;
					break;
				}
			}
		}
		return $cache[ $k ];
	}

	public static function why_not( $submitter, $class_id ) {
		$me = get_current_user_id();
		if ( (int) $submitter === $me || ( $class_id && SD_Workshops::is_class_teacher( $class_id, $me ) ) ) {
			return 'Δεν ελέγχετε δικό σας έντυπο ή έντυπο τμήματός σας· τον έλεγχο κάνει άλλο μέλος της Επιτροπής ή η Διεύθυνση.';
		}
		return 'Το έντυπο υποβλήθηκε από μέλος της Επιτροπής ή της Διεύθυνσης· τον οριστικό έλεγχο κάνει η Διεύθυνση.';
	}

	/** Για τις οθόνες: true αν ο χρήστης βλέπει το πλαίσιο ελέγχου· αλλιώς τυπώνει γιατί όχι. */
	public static function review_gate( $submitter, $class_id, $status ) {
		if ( self::may_review( $submitter, $class_id ) ) {
			return true;
		}
		if ( current_user_can( SD_Roles::CAP_REVIEW ) && 'ypovlithike' === $status ) {
			echo '<p class="sd-notice sd-ok">' . esc_html( self::why_not( $submitter, $class_id ) ) . '</p>';
		}
		return false;
	}

	/** Χρήστες που μπορούν να ελέγξουν (για email). */
	private static function reviewers( $submitter, $class_id, $manage_only = false ) {
		$out = array();
		foreach ( SD_Roles::people() as $u ) {
			if ( $manage_only ? ( user_can( $u, SD_Roles::CAP_MANAGE ) && (int) $u->ID !== (int) $submitter ) : self::may_review( $submitter, $class_id, $u->ID ) ) {
				$out[ $u->ID ] = $u;
			}
		}
		return $out;
	}

	/** Όσα περιμένουν έλεγχο από τον τρέχοντα χρήστη: [ [τίτλος, url] ... ]. */
	public static function pending_for_user( $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$year    = SD_Data::active_year();
		if ( ! $year || ! user_can( $user_id, SD_Roles::CAP_REVIEW ) ) {
			return array();
		}
		$out = array();
		foreach ( SD_Data::classes( $year->id ) as $c ) {
			$label = 'Τμήμα ' . SD_Data::class_label( $c );
			$f     = SD_Workshops::form_of_class( $c->id );
			if ( $f && 'ypovlithike' === $f->status && self::may_review( $f->submitted_by, $c->id, $user_id ) ) {
				$out[] = array( 'Έντυπο 1Α · ' . $label, SD_Frontend::url( 'ergastiria', array( 'class' => $c->id ) ) );
			}
			foreach ( SD_Citizen::of_class( $c->id ) as $d ) {
				if ( 'ypovlithike' === $d->status && self::may_review( $d->submitted_by, $c->id, $user_id ) ) {
					$out[] = array( 'Έντυπο 2 «' . $d->title . '» · ' . $label, SD_Frontend::url( 'politis', array( 'id' => $d->id ) ) );
				}
			}
			$g = SD_F4::form_of_class( $c->id );
			if ( $g && 'ypovlithike' === $g->status && self::may_review( $g->submitted_by, $c->id, $user_id ) ) {
				$out[] = array( 'Έντυπο 4 · ' . $label, SD_Frontend::url( 'apotimisi', array( 'class' => $c->id ) ) );
			}
		}
		if ( user_can( $user_id, SD_Roles::CAP_MANAGE ) ) {
			$req = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . SD_Requests::t() . " WHERE year_id = %d AND status = 'ypovlithike' AND owner_id <> %d", $year->id, $user_id ) );
			if ( $req ) {
				$out[] = array( 'Επισκέψεις: ' . $req . ' αιτήματα για πράξη Συλλόγου', SD_Frontend::url( 'aitimata' ) );
			}
			$pl = SD_Map::pending_count( $year->id );
			if ( $pl ) {
				$out[] = array( 'Χάρτης τόπου: ' . $pl . ' σημεία για έγκριση', SD_Frontend::url( 'xartis' ) );
			}
		}
		return $out;
	}

	/* =================== Email =================== */

	private static function send( $user, $subject, $lines ) {
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}
		$body  = 'Καλημέρα ' . ( $user->first_name ? $user->first_name : $user->display_name ) . ",\n\n";
		$body .= implode( "\n", $lines );
		$body .= "\n\n— Σχολικές Δράσεις · " . SD_Frontend::school_name() . "\n" . SD_Install::app_url() . "\n(Αυτόματο μήνυμα· οι ειδοποιήσεις ρυθμίζονται από τη Διεύθυνση.)";
		return wp_mail( $user->user_email, '[Σχολικές Δράσεις] ' . $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/** Υποβλήθηκε έντυπο: email σε όσους μπορούν να το ελέγξουν. */
	public static function submitted( $what, $class_id, $url, $manage_only = false ) {
		if ( ! self::cfg()['submit'] ) {
			return;
		}
		$me    = wp_get_current_user();
		$class = $class_id ? SD_Data::get_class( $class_id ) : null;
		foreach ( self::reviewers( $me->ID, $class_id, $manage_only ) as $u ) {
			self::send(
				$u,
				'Για έλεγχο: ' . $what,
				array(
					'Υποβλήθηκε για έλεγχο: ' . $what . ( $class && false === mb_strpos( $what, 'τμήμα' ) ? ' (τμήμα ' . SD_Data::class_label( $class ) . ')' : '' ) . ', από ' . $me->display_name . '.',
					'',
					'Άνοιγμα: ' . $url,
				)
			);
		}
	}

	/** Ελέγχθηκε ή επιστράφηκε: email στον/στην που υπέβαλε και στους εκπαιδευτικούς του τμήματος. */
	public static function decided( $what, $class_id, $submitter, $returned, $note, $url ) {
		if ( ! self::cfg()['decision'] ) {
			return;
		}
		$to = array();
		if ( $submitter ) {
			$to[ (int) $submitter ] = get_userdata( (int) $submitter );
		}
		if ( $class_id ) {
			foreach ( SD_Data::class_teachers( $class_id ) as $t ) {
				$to[ (int) $t->user_id ] = get_userdata( (int) $t->user_id );
			}
		}
		unset( $to[ get_current_user_id() ] );
		$lines = array(
			( $returned ? 'Επιστράφηκε για διόρθωση: ' : 'Ελέγχθηκε: ' ) . $what . '.',
		);
		if ( '' !== trim( (string) $note ) ) {
			$lines[] = '';
			$lines[] = 'Σχόλια: ' . $note;
		}
		$lines[] = '';
		$lines[] = 'Άνοιγμα: ' . $url;
		foreach ( array_filter( $to ) as $u ) {
			self::send( $u, ( $returned ? 'Επιστροφή για διόρθωση: ' : 'Ελέγχθηκε: ' ) . $what, $lines );
		}
	}

	/* =================== Καθημερινές υπενθυμίσεις =================== */

	/** Τι να θυμίσουμε σε έναν χρήστη σήμερα. */
	public static function reminders_for( $user_id ) {
		$year = SD_Data::active_year();
		if ( ! $year ) {
			return array();
		}
		$days  = self::days();
		$today = strtotime( current_time( 'Y-m-d' ) );
		$out   = array();
		foreach ( SD_Data::classes_of_user( $user_id, $year->id ) as $c ) {
			foreach ( SD_Timeline::class_steps( $c ) as $s ) {
				if ( ! $s['deadline'] || ! in_array( $s['state'], array( 'todo', 'fix', 'late' ), true ) ) {
					continue;
				}
				$left = (int) round( ( strtotime( $s['deadline'] ) - $today ) / DAY_IN_SECONDS );
				if ( in_array( $left, $days, true ) ) {
					$when  = $left > 1 ? 'σε ' . $left . ' ημέρες' : ( 1 === $left ? 'αύριο' : 'σήμερα' );
					$out[] = '• Τμήμα ' . SD_Data::class_label( $c ) . ' – ' . $s['title'] . ': προθεσμία ' . mysql2date( 'd/m/Y', $s['deadline'] ) . ' (' . $when . ')' . ( 'fix' === $s['state'] ? ', έχει επιστραφεί για διόρθωση' : '' ) . "\n  " . $s['url'];
				}
			}
		}
		foreach ( SD_Plans::plans( $year->id ) as $p ) {
			if ( (int) $p->coordinator_id !== (int) $user_id ) {
				continue;
			}
			foreach ( SD_Plans::goals( $p->id ) as $gi => $g ) {
				foreach ( SD_Plans::actions( $g->id ) as $a ) {
					foreach ( array( 'start_date' => 'ξεκινά', 'end_date' => 'λήγει' ) as $f => $verb ) {
						if ( ! $a->$f ) {
							continue;
						}
						$left = (int) round( ( strtotime( $a->$f ) - $today ) / DAY_IN_SECONDS );
						if ( in_array( $left, $days, true ) ) {
							$kind  = SD_Eval::ACTION_KINDS[ $a->kind ] ?? '';
							$out[] = '• ' . trim( $p->code . ' ' . $p->title ) . ', στόχος ' . ( $gi + 1 ) . ' – ενέργεια ' . mb_strtolower( $kind ) . ' ' . $verb . ' ' . mysql2date( 'd/m/Y', $a->$f ) . ( $left ? ' (σε ' . $left . ' ημέρες)' : ' (σήμερα)' ) . ': ' . mb_substr( $a->description, 0, 120 ) . ( mb_strlen( $a->description ) > 120 ? '…' : '' ) . "\n  " . SD_Frontend::url( 'sxedia', array( 'id' => $p->id ) );
						}
					}
				}
			}
		}
		return $out;
	}

	/** Εβδομαδιαία αναφορά για τη Διεύθυνση: τι εκκρεμεί, τι ολοκληρώθηκε, τι αναμένεται. */
	public static function weekly_report( $user_id ) {
		global $wpdb;
		$year = SD_Data::active_year();
		if ( ! $year ) {
			return array();
		}
		$today = current_time( 'Y-m-d' );
		$from  = gmdate( 'Y-m-d', strtotime( $today . ' -7 days' ) );
		$to    = gmdate( 'Y-m-d', strtotime( $today . ' +14 days' ) );
		$L     = array( 'Εβδομαδιαία εικόνα των Σχολικών Δράσεων · ' . $year->label, '' );

		// 1. Τι εκκρεμεί.
		$pend = self::pending_for_user( $user_id );
		$late = array();
		foreach ( SD_Data::classes( $year->id ) as $c ) {
			foreach ( SD_Timeline::class_steps( $c ) as $s ) {
				if ( 'late' === $s['state'] ) {
					$late[] = '• Τμήμα ' . SD_Data::class_label( $c ) . ' – ' . $s['title'] . ' (προθεσμία ' . mysql2date( 'd/m/Y', $s['deadline'] ) . ')';
				}
			}
		}
		$L[] = '1. ΤΙ ΕΚΚΡΕΜΕΙ';
		if ( ! $pend && ! $late ) {
			$L[] = 'Τίποτα. Δεν υπάρχει κάτι για έλεγχο ούτε κάτι εκπρόθεσμο.';
		}
		if ( $pend ) {
			$L[] = 'Για έλεγχο:';
			foreach ( $pend as $x ) {
				$L[] = '• ' . $x[0] . ' – ' . $x[1];
			}
		}
		if ( $late ) {
			$L[] = 'Εκπρόθεσμα:';
			$L   = array_merge( $L, $late );
		}

		// 2. Τι ολοκληρώθηκε τις τελευταίες 7 ημέρες.
		$done = array();
		$q    = function ( $table, $col, $status = '' ) use ( $wpdb, $year, $from ) {
			$sql = 'SELECT * FROM ' . SD_Data::table( $table ) . " WHERE year_id = %d AND $col >= %s" . ( $status ? ' AND status = %s' : '' );
			return $status ? $wpdb->get_results( $wpdb->prepare( $sql, $year->id, $from, $status ) ) : $wpdb->get_results( $wpdb->prepare( $sql, $year->id, $from ) );
		};
		$cl = function ( $id ) {
			$c = SD_Data::get_class( $id );
			return $c ? 'τμήμα ' . SD_Data::class_label( $c ) : '';
		};
		foreach ( array( 'f1a' => 'Έντυπο 1Α', 'f4' => 'Έντυπο 4' ) as $t => $label ) {
			foreach ( $q( $t, 'submitted_at' ) as $r ) {
				$done[] = '• Υποβλήθηκε ' . $label . ' – ' . $cl( $r->class_id );
			}
			foreach ( $q( $t, 'reviewed_at', 'elegxthike' ) as $r ) {
				$done[] = '• Ελέγχθηκε ' . $label . ' – ' . $cl( $r->class_id );
			}
		}
		foreach ( $q( 'citizen', 'submitted_at' ) as $r ) {
			$done[] = '• Υποβλήθηκε Έντυπο 2 «' . $r->title . '» – ' . $cl( $r->class_id );
		}
		foreach ( $q( 'citizen', 'reviewed_at', 'elegxthike' ) as $r ) {
			$done[] = '• Ελέγχθηκε Έντυπο 2 «' . $r->title . '» – ' . $cl( $r->class_id );
		}
		foreach ( $q( 'requests', 'reviewed_at', 'egkrithike' ) as $r ) {
			$done[] = '• Εγκρίθηκε επίσκεψη/φορέας (Έντυπο 3) – ' . $cl( $r->class_id ) . ( $r->event_date ? ', ' . mysql2date( 'd/m/Y', $r->event_date ) : '' );
		}
		foreach ( $q( 'ergplan', 'updated_at' ) as $r ) {
			$done[] = '• Ενημερώθηκε Σχέδιο Δράσης Τμήματος – ' . $cl( $r->class_id );
		}
		foreach ( $q( 'places', 'reviewed_at', 'dimosieftike' ) as $r ) {
			$done[] = '• Δημοσιεύτηκε σημείο χάρτη «' . $r->name . '»';
		}
		$L[] = '';
		$L[] = '2. ΤΙ ΟΛΟΚΛΗΡΩΘΗΚΕ (τελευταίες 7 ημέρες)';
		$L   = array_merge( $L, $done ? array_values( array_unique( $done ) ) : array( 'Καμία νέα υποβολή ή έλεγχος.' ) );

		// 3. Τι αναμένεται τις επόμενες 14 ημέρες.
		$next = array();
		$n    = count( SD_Data::classes( $year->id ) );
		foreach ( array(
			'Έντυπα 1Α'         => SD_Workshops::deadline(),
			'Έντυπα 2'          => SD_Timeline::deadline_f2(),
			'Σχέδια τμημάτων'   => SD_Timeline::deadline_sxtm(),
			'Έντυπα 4'          => SD_F4::deadline(),
		) as $label => $dl ) {
			if ( $dl && $dl >= $today && $dl <= $to ) {
				$next[] = '• Προθεσμία ' . $label . ': ' . mysql2date( 'd/m/Y', $dl );
			}
		}
		foreach ( SD_Plans::plans( $year->id ) as $p ) {
			foreach ( SD_Plans::goals( $p->id ) as $gi => $g ) {
				foreach ( SD_Plans::actions( $g->id ) as $a ) {
					foreach ( array( 'start_date' => 'ξεκινά', 'end_date' => 'λήγει' ) as $f => $verb ) {
						if ( $a->$f && $a->$f >= $today && $a->$f <= $to ) {
							$next[] = '• ' . trim( $p->code . ' ' . $p->title ) . ', στόχος ' . ( $gi + 1 ) . ': ενέργεια ' . mb_strtolower( SD_Eval::ACTION_KINDS[ $a->kind ] ?? '' ) . ' ' . $verb . ' ' . mysql2date( 'd/m/Y', $a->$f );
						}
					}
				}
			}
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . SD_Requests::t() . " WHERE year_id = %d AND status = 'egkrithike' AND event_date BETWEEN %s AND %s ORDER BY event_date", $year->id, $today, $to ) ) as $r ) {
			$next[] = '• Επίσκεψη/φορέας – ' . $cl( $r->class_id ) . ', ' . mysql2date( 'd/m/Y', $r->event_date ) . ( $r->destination ? ' (' . $r->destination . ')' : '' );
		}
		$L[] = '';
		$L[] = '3. ΤΙ ΑΝΑΜΕΝΕΤΑΙ (επόμενες 14 ημέρες)';
		$L   = array_merge( $L, $next ? $next : array( 'Καμία προθεσμία ή ενέργεια.' ) );

		// 4. Συνολική εικόνα.
		$L[] = '';
		$L[] = '4. ΣΥΝΟΛΙΚΗ ΕΙΚΟΝΑ (' . $n . ' τμήματα)';
		foreach ( SD_Timeline::school_steps( $year ) as $s ) {
			list( $label ) = SD_Timeline::STATES[ $s['state'] ];
			$L[]           = '• ' . $s['title'] . ': ' . $label . ( $s['detail'] ? ' – ' . $s['detail'] : '' );
		}
		$L[] = '';
		$L[] = 'Αναλυτικά στην αρχική οθόνη: ' . SD_Install::app_url();
		return $L;
	}

	public static function daily( $force = false ) {
		$cfg   = self::cfg();
		$today = current_time( 'Y-m-d' );
		if ( ! $force && get_option( self::OPT_LAST ) === $today ) {
			return 0;
		}
		update_option( self::OPT_LAST, $today, false );
		$sent   = 0;
		$monday = $force || '1' === current_time( 'N' );
		foreach ( SD_Roles::people() as $u ) {
			// Δευτέρα: η Διεύθυνση παίρνει την εβδομαδιαία αναφορά (μαζί και τυχόν υπενθυμίσεις της), σε ένα email.
			if ( $monday && $cfg['weekly'] && user_can( $u, SD_Roles::CAP_MANAGE ) ) {
				$lines = self::weekly_report( $u->ID );
				$r     = $cfg['reminders'] ? self::reminders_for( $u->ID ) : array();
				if ( $r ) {
					$lines = array_merge( $lines, array( '', 'Δικές σας προθεσμίες:' ), $r );
				}
				if ( $lines && self::send( $u, 'Εβδομαδιαία αναφορά ' . mysql2date( 'd/m/Y', $today ), $lines ) ) {
					$sent++;
				}
				continue;
			}
			$lines = array();
			if ( $cfg['reminders'] ) {
				$r = self::reminders_for( $u->ID );
				if ( $r ) {
					$lines[] = 'Πλησιάζουν προθεσμίες για όσα έχετε αναλάβει:';
					$lines[] = '';
					$lines   = array_merge( $lines, $r );
				}
			}
			if ( $cfg['digest'] && ( $force || '1' === current_time( 'N' ) ) ) {
				$p = self::pending_for_user( $u->ID );
				if ( $p ) {
					if ( $lines ) {
						$lines[] = '';
					}
					$lines[] = 'Περιμένουν τον έλεγχό σας:';
					$lines[] = '';
					foreach ( $p as $x ) {
						$lines[] = '• ' . $x[0] . "\n  " . $x[1];
					}
				}
			}
			if ( $lines && self::send( $u, 'Υπενθύμιση προθεσμίας', $lines ) ) {
				$sent++;
			}
		}
		return $sent;
	}

	/* =================== Ρυθμίσεις =================== */

	public static function handle( $do ) {
		if ( ! in_array( $do, array( 'notify_save', 'notify_test', 'notify_report' ), true ) ) {
			return null;
		}
		$back = SD_Frontend::url( 'rythmiseis' ) . '#eidopoiiseis';
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση αλλάζει τις ειδοποιήσεις.' ), $back );
		}
		if ( 'notify_report' === $do ) {
			$ok = self::send( wp_get_current_user(), 'Εβδομαδιαία αναφορά (δοκιμή)', self::weekly_report( get_current_user_id() ) );
			set_transient( 'sd_msg_' . get_current_user_id(), $ok ? array( 'ok', 'Η αναφορά στάλθηκε στο email σας.' ) : array( 'error', 'Η αποστολή απέτυχε. Ελέγξτε τις ρυθμίσεις email του ιστότοπου.' ), 60 );
			return array( true, $back );
		}
		if ( 'notify_test' === $do ) {
			$n = self::daily( true );
			set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', $n ? 'Στάλθηκαν ' . $n . ' μηνύματα.' : 'Σήμερα δεν πέφτει καμία προθεσμία στις ημέρες υπενθύμισης· δεν στάλθηκε μήνυμα.' ), 60 );
			return array( true, $back );
		}
		$days = preg_replace( '/[^0-9,]/', '', (string) wp_unslash( $_POST['days'] ?? '' ) );
		update_option(
			self::OPT,
			array(
				'submit'    => empty( $_POST['submit'] ) ? 0 : 1,
				'decision'  => empty( $_POST['decision'] ) ? 0 : 1,
				'reminders' => empty( $_POST['reminders'] ) ? 0 : 1,
				'digest'    => empty( $_POST['digest'] ) ? 0 : 1,
				'weekly'    => empty( $_POST['weekly'] ) ? 0 : 1,
				'days'      => '' !== $days ? $days : '7,2',
				'v2'        => 1,
			),
			false
		);
		return array( true, $back );
	}

	public static function render_settings() {
		$c = self::cfg();
		$on = array();
		foreach ( array( 'submit' => 'υποβολές', 'decision' => 'αποφάσεις', 'reminders' => 'υπενθυμίσεις', 'weekly' => 'εβδομαδιαία αναφορά' ) as $k => $l ) {
			if ( ! empty( $c[ $k ] ) ) {
				$on[] = '<span>' . esc_html( $l ) . '</span>';
			}
		}
		echo SD_Docs::fold_open( 'Ειδοποιήσεις με email', $on ? implode( '', $on ) : '<span>όλες κλειστές</span>', false, 'id="eidopoiiseis"' ); // phpcs:ignore WordPress.Security.EscapeOutput
		SD_Frontend::form_open( 'notify_save' );
		$box = function ( $name, $label ) use ( $c ) {
			echo '<label class="sd-block"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $c[ $name ] ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		};
		$box( 'submit', 'Όταν υποβάλλεται έντυπο, email σε όσους μπορούν να το ελέγξουν' );
		$box( 'decision', 'Όταν ένα έντυπο ελέγχεται ή επιστρέφεται, email στον/στην εκπαιδευτικό' );
		$box( 'reminders', 'Υπενθύμιση όταν πλησιάζει προθεσμία εντύπου ή ενέργεια Σχεδίου Δράσης' );
		echo '<label class="sd-block">Πόσες ημέρες πριν (χωρισμένες με κόμμα) <input name="days" value="' . esc_attr( $c['days'] ) . '" style="max-width:10em"></label>';
		$box( 'weekly', 'Εβδομαδιαία αναφορά στη Διεύθυνση κάθε Δευτέρα: τι εκκρεμεί, τι ολοκληρώθηκε, τι αναμένεται' );
		$box( 'digest', 'Κάθε Δευτέρα και στα μέλη της Επιτροπής: όσα περιμένουν τον έλεγχό τους' );
		echo '<p class="sd-help">Υπενθύμιση στέλνεται μόνο τις ημέρες που ορίσατε (π.χ. 7 και 2 ημέρες πριν), ένα email ανά άτομο, και μόνο για όσα έχει αναλάβει: τα τμήματά του και τα Σχέδια Δράσης που συντονίζει. Όταν δεν πλησιάζει κάτι, δεν στέλνεται τίποτα. Οι προθεσμίες των εντύπων ορίζονται παραπάνω· των Σχεδίων Δράσης είναι οι ημερομηνίες των ενεργειών τους.</p>';
		echo '<p><button class="sd-button" type="submit">Αποθήκευση</button></p></form>';
		SD_Frontend::form_open( 'notify_report' );
		echo '<button class="sd-button sd-small sd-quiet" type="submit">Στείλτε μου τώρα την εβδομαδιαία αναφορά</button></form> ';
		SD_Frontend::form_open( 'notify_test' );
		echo '<button class="sd-button sd-small sd-quiet" type="submit" onclick="return confirm(\'Να σταλούν τώρα όσες υπενθυμίσεις αντιστοιχούν στη σημερινή ημέρα;\');">Αποστολή των σημερινών υπενθυμίσεων τώρα</button></form>';
		echo SD_Docs::fold_close(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
