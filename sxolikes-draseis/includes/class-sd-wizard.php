<?php
/**
 * Εργαστήρια βήμα-βήμα: λίγες απλές ερωτήσεις ανά θεματικό κύκλο.
 * Οι απαντήσεις γράφονται ταυτόχρονα στο Έντυπο 1Α και στο Σχέδιο Δράσης
 * Τμήματος· ο Προγραμματισμός βγαίνει από αυτά. Αποθήκευση αυτόματα (AJAX)
 * και με το «Επόμενο βήμα» (κανονική φόρμα, δουλεύει και χωρίς JavaScript).
 *
 * Διαδρομή: ?sd=ergastiria&odigos=<τμήμα>&k=<κύκλος>&v=<βήμα>
 *   v=0 σειρά της χρονιάς · v=1..4 βήματα κύκλου · v=9 έλεγχος και υποβολή
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Wizard {

	const STEPS = array(
		1 => 'Θέμα και τίτλος',
		2 => 'Τι θα κάνουν τα παιδιά',
		3 => 'Με τι συνδέεται',
		4 => 'Ποιοι συμμετέχουν',
	);

	/** Πεδίο του οδηγού => [στήλη 1Α ή '', κλειδί Σχεδίου τμήματος ή '']. */
	const FIELDS = array(
		'subtheme'      => array( '', 'subtheme' ),
		'title'         => array( 'title', 'our_title' ),
		'goals'         => array( 'goals', 'goals' ),
		'activities'    => array( 'activities', 'sequence' ),
		'weeks'         => array( 'weeks', '' ),
		'skills'        => array( 'skills', '' ),
		'material'      => array( 'material', 'material' ),
		'action_id'     => array( 'action_id', '' ),
		'citizen_hours' => array( 'citizen_hours', '' ),
		'visits'        => array( 'visits', 'partners' ),
		'deliverable'   => array( 'deliverable', 'products' ),
		'teachers'      => array( '', 'teachers' ),
		'adaptations'   => array( '', 'adaptations' ),
	);

	public static function init() {
		add_action( 'wp_ajax_sd_wiz_save', array( __CLASS__, 'ajax' ) );
	}

	public static function url( $class_id, $k = 0, $v = null ) {
		$args = array( 'odigos' => (int) $class_id );
		if ( $k ) {
			$args['k'] = (int) $k;
		}
		if ( null !== $v ) {
			$args['v'] = (int) $v;
		}
		return SD_Frontend::url( 'ergastiria', $args );
	}

	/* =================== Δεδομένα =================== */

	/** [κύκλος => αριθμός ενότητας 1Α]. */
	private static function cycle_units( $units ) {
		$out = array();
		foreach ( $units as $n => $u ) {
			if ( $u->cycle && ! isset( $out[ (int) $u->cycle ] ) ) {
				$out[ (int) $u->cycle ] = (int) $n;
			}
		}
		return $out;
	}

	/** Οι κύκλοι με τη σειρά της χρονιάς (όσοι δεν μπήκαν ακόμη, στο τέλος). */
	private static function cycle_order( $units ) {
		$cu = self::cycle_units( $units );
		asort( $cu );
		$order = array_keys( $cu );
		foreach ( array_keys( SD_Workshops::CYCLES ) as $c ) {
			if ( ! in_array( $c, $order, true ) ) {
				$order[] = $c;
			}
		}
		return $order;
	}

	private static function order_done( $units ) {
		return 4 === count( self::cycle_units( $units ) );
	}

	/** Τρέχουσες τιμές ενός κύκλου για τον οδηγό. */
	private static function values( $class, $units, $c ) {
		$cu   = self::cycle_units( $units );
		$u    = isset( $cu[ $c ] ) ? $units[ $cu[ $c ] ] : null;
		$vals = SD_ErgPlan::cycle_values( $class )[ $c ] ?? array();
		$out  = array();
		foreach ( self::FIELDS as $k => $map ) {
			list( $col, $ek ) = $map;
			if ( $col && $u ) {
				$out[ $k ] = (string) $u->$col;
				if ( '' === trim( $out[ $k ] ) && $ek ) {
					$out[ $k ] = (string) ( $vals[ $ek ] ?? '' );
				}
			} elseif ( $ek ) {
				$out[ $k ] = (string) ( $vals[ $ek ] ?? '' );
			} else {
				$out[ $k ] = '';
			}
		}
		if ( '' === $out['title'] ) {
			$out['title'] = (string) ( $vals['our_title'] ?? '' );
		}
		$out['subtheme'] = SD_Workshops::class_subtheme_id( $class, $c );
		return array( $out, $u );
	}

	/** Τι λείπει από έναν κύκλο: [βήμα => true]. */
	private static function missing( $vals, $u ) {
		$m = array();
		if ( ! $u || '' === trim( $vals['title'] ) || '' === trim( $vals['goals'] ) ) {
			$m[1] = true;
		}
		if ( '' === trim( $vals['activities'] ) || ! (int) $vals['weeks'] ) {
			$m[2] = true;
		}
		return $m;
	}

	/* =================== Αποθήκευση =================== */

	private static function clean( $field, $value, $class ) {
		switch ( $field ) {
			case 'weeks':
				return min( 20, absint( $value ) );
			case 'citizen_hours':
				return min( 30, absint( $value ) );
			case 'skills':
				$v = is_array( $value ) ? $value : explode( ',', (string) $value );
				return implode( ',', array_values( array_intersect( array_keys( SD_Workshops::SKILLS ), array_map( 'sanitize_key', $v ) ) ) );
			case 'action_id':
				$id = absint( $value );
				foreach ( SD_Plans::year_actions( $class->year_id, $class->grade ) as $row ) {
					if ( (int) $row[2]->id === $id ) {
						return $id;
					}
				}
				return 0;
			case 'title':
			case 'material':
			case 'deliverable':
				return mb_substr( sanitize_text_field( (string) $value ), 0, 255 );
			case 'subtheme':
				return sanitize_text_field( (string) $value );
		}
		return mb_substr( sanitize_textarea_field( (string) $value ), 0, 3000 );
	}

	/**
	 * Αποθηκεύει πεδία ενός κύκλου. Επιστρέφει true ή WP_Error.
	 * $data: [πεδίο => τιμή]. Τα πεδία του 1Α γράφονται μόνο όσο το 1Α είναι επεξεργάσιμο.
	 */
	public static function save( $class, $c, array $data ) {
		global $wpdb;
		if ( ! isset( SD_Workshops::CYCLES[ $c ] ) ) {
			return new WP_Error( 'sd', 'Άγνωστος θεματικός κύκλος.' );
		}
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) && ! SD_Workshops::is_class_teacher( $class->id ) ) {
			return new WP_Error( 'sd', 'Μόνο οι εκπαιδευτικοί του τμήματος συμπληρώνουν τα Εργαστήρια του.' );
		}
		$form     = SD_Workshops::form_of_class( $class->id );
		$f1a_edit = SD_Workshops::can_edit( $class, $form );
		$unit_row = array();
		$erg      = array();
		foreach ( $data as $field => $value ) {
			if ( ! isset( self::FIELDS[ $field ] ) ) {
				continue;
			}
			$v = self::clean( $field, $value, $class );
			list( $col, $ek ) = self::FIELDS[ $field ];
			if ( 'subtheme' === $field && ! isset( SD_Workshops::cycle_subthemes( $c )[ $v ] ) ) {
				continue;
			}
			if ( $col && $f1a_edit ) {
				$unit_row[ $col ] = $v;
			}
			if ( $ek ) {
				$erg[ $ek ] = (string) $v;
			}
		}

		if ( $unit_row ) {
			$form  = SD_Workshops::ensure_form( $class );
			$units = SD_Workshops::units( $form );
			$cu    = self::cycle_units( $units );
			if ( ! isset( $cu[ $c ] ) ) {
				return new WP_Error( 'sd', 'Ορίστε πρώτα σε ποια περίοδο γίνεται αυτός ο κύκλος (βήμα «Η σειρά της χρονιάς»).' );
			}
			self::write_unit( $form, $cu[ $c ], $unit_row );
		}

		if ( $erg ) {
			$plan  = SD_ErgPlan::class_plan( $class->id );
			$saved = $plan ? $plan->cycle_arr : array();
			foreach ( $erg as $k => $v ) {
				$saved[ $c ][ $k ] = $v;
			}
			$row = array(
				'cycles'     => wp_json_encode( $saved, JSON_UNESCAPED_UNICODE ),
				'updated_by' => get_current_user_id(),
				'updated_at' => current_time( 'mysql' ),
			);
			if ( $plan ) {
				$wpdb->update( SD_ErgPlan::t(), $row, array( 'class_id' => (int) $class->id ) );
			} else {
				$wpdb->insert(
					SD_ErgPlan::t(),
					$row + array(
						'year_id'     => (int) $class->year_id,
						'class_id'    => (int) $class->id,
						'students'    => 0,
						'orientation' => '',
					)
				);
			}
		}
		return true;
	}

	private static function write_unit( $form, $n, array $row ) {
		global $wpdb;
		$t      = SD_Data::table( 'f1a_units' );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $t . ' WHERE f1a_id = %d AND unit_no = %d', $form->id, $n ) );
		if ( $exists ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $exists ) );
		} else {
			$base = array( 'cycle' => 0, 'title' => '', 'weeks' => 0, 'skills' => '', 'goals' => '', 'activities' => '', 'material' => '', 'action_id' => 0, 'citizen_hours' => 0, 'visits' => '', 'deliverable' => '' );
			$wpdb->insert( $t, array_merge( $base, $row, array( 'f1a_id' => $form->id, 'unit_no' => $n ) ) );
		}
		$wpdb->update( SD_Data::table( 'f1a' ), array( 'updated_by' => get_current_user_id(), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $form->id ) );
	}

	/** Η σειρά της χρονιάς: [ενότητα => κύκλος]. */
	public static function save_order( $class, array $order ) {
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) && ! SD_Workshops::is_class_teacher( $class->id ) ) {
			return new WP_Error( 'sd', 'Μόνο οι εκπαιδευτικοί του τμήματος συμπληρώνουν τα Εργαστήρια του.' );
		}
		$form = SD_Workshops::form_of_class( $class->id );
		if ( ! SD_Workshops::can_edit( $class, $form ) ) {
			return new WP_Error( 'sd', 'Το Έντυπο 1Α έχει υποβληθεί· η σειρά αλλάζει μόνο αν επιστραφεί για διόρθωση.' );
		}
		$form = SD_Workshops::ensure_form( $class );
		foreach ( SD_Workshops::UNITS as $n => $l ) {
			if ( isset( $order[ $n ] ) ) {
				$c = absint( $order[ $n ] );
				self::write_unit( $form, $n, array( 'cycle' => isset( SD_Workshops::CYCLES[ $c ] ) ? $c : 0 ) );
			}
		}
		return true;
	}

	/* =================== Αιτήματα =================== */

	/** Αυτόματη αποθήκευση ενός πεδίου. */
	public static function ajax() {
		if ( ! check_ajax_referer( 'sd_wiz', 'nonce', false ) || ! current_user_can( SD_Roles::CAP_ACCESS ) ) {
			wp_send_json_error( array( 'msg' => 'Η σύνδεση έληξε. Ανανεώστε τη σελίδα.' ), 403 );
		}
		$class = SD_Data::get_class( absint( $_POST['class_id'] ?? 0 ) );
		if ( ! $class ) {
			wp_send_json_error( array( 'msg' => 'Το τμήμα δεν βρέθηκε.' ) );
		}
		$field = sanitize_key( wp_unslash( $_POST['field'] ?? '' ) );
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- καθαρίζεται στο clean().
		if ( 'order' === $field ) {
			$res = self::save_order( $class, array( absint( $_POST['unit'] ?? 0 ) => absint( $value ) ) );
		} else {
			$res = self::save( $class, absint( $_POST['k'] ?? 0 ), array( $field => $value ) );
		}
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'msg' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'meters' => self::meters( $class ) ) );
	}

	/** «Επόμενο βήμα»: αποθήκευση όλου του βήματος και μετάβαση. */
	public static function handle( $do ) {
		if ( 'wiz_save' !== $do ) {
			return null;
		}
		$post  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security
		$class = SD_Data::get_class( absint( $post['class_id'] ?? 0 ) );
		if ( ! $class ) {
			return array( new WP_Error( 'sd', 'Το τμήμα δεν βρέθηκε.' ), SD_Frontend::url( 'ergastiria' ) );
		}
		$go = esc_url_raw( (string) ( $post['go'] ?? '' ) );
		$go = $go && 0 === strpos( $go, SD_Install::app_url() ) ? $go : self::url( $class->id );
		$k  = absint( $post['k'] ?? 0 );
		if ( isset( $post['order'] ) && is_array( $post['order'] ) ) {
			$res = self::save_order( $class, $post['order'] );
			// Η σειρά μόλις άλλαξε: ο πρώτος κύκλος της χρονιάς υπολογίζεται τώρα.
			$units = SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) );
			if ( ! is_wp_error( $res ) && self::order_done( $units ) ) {
				$go = self::url( $class->id, self::cycle_order( $units )[0], 1 );
			}
		} else {
			$data = array();
			foreach ( array_keys( self::FIELDS ) as $f ) {
				if ( array_key_exists( $f, $post ) ) {
					$data[ $f ] = $post[ $f ];
				}
			}
			$staff = SD_Staff::combine( $post, 'teachers' );
			if ( null !== $staff ) {
				$data['teachers'] = $staff;
			}
			if ( isset( $post['skills_sent'] ) && ! isset( $post['skills'] ) ) {
				$data['skills'] = array();
			}
			$res = $data ? self::save( $class, $k, $data ) : true;
		}
		if ( is_wp_error( $res ) ) {
			return array( $res, self::url( $class->id, $k, absint( $post['v'] ?? 0 ) ) );
		}
		return array( true, $go );
	}

	/* =================== Πρόοδος εντύπων =================== */

	public static function meters( $class ) {
		$units  = SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) );
		$filled = 0;
		foreach ( $units as $u ) {
			$filled += ( $u->cycle && '' !== trim( $u->title ) && '' !== trim( $u->goals ) && '' !== trim( $u->activities ) && $u->weeks ) ? 1 : 0;
		}
		$rows = SD_ErgPlan::programming_rows( $class );
		$prog = 0;
		foreach ( $rows as $r ) {
			$prog += '' !== $r['title'] ? 1 : 0;
		}
		$all  = 0;
		$full = 0;
		foreach ( SD_ErgPlan::cycle_values( $class ) as $v ) {
			foreach ( array( 'our_title', 'goals', 'sequence', 'products', 'partners', 'teachers' ) as $k ) {
				$all++;
				$full += '' !== trim( (string) ( $v[ $k ] ?? '' ) ) ? 1 : 0;
			}
		}
		return array(
			'f1a'  => array( 25 * $filled, $filled . ' από 4 κύκλους' ),
			'prog' => array( $rows ? (int) round( 100 * $prog / count( $rows ) ) : 0, 4 - $prog ? ( 4 - $prog ) . ( 1 === 4 - $prog ? ' τίτλος λείπει' : ' τίτλοι λείπουν' ) : 'Έτοιμος' ),
			'sxtm' => array( $all ? (int) round( 100 * $full / $all ) : 0, 'Συμπληρώνεται και αργότερα' ),
		);
	}

	private static function meters_html( $class ) {
		$m     = self::meters( $class );
		$names = array(
			'f1a'  => 'Έντυπο 1Α',
			'prog' => 'Προγραμματισμός Εκπαιδευτικών',
			'sxtm' => 'Σχέδιο Δράσης Τμήματος',
		);
		$h = '<aside class="sd-wiz-out" aria-label="Τι γεμίζει με αυτά"><h3>Τι γεμίζει με αυτά</h3><p>Δεν χρειάζεται να ανοίξετε κάποιο έντυπο.</p>';
		foreach ( $names as $k => $l ) {
			$h .= '<div class="sd-mini" data-meter="' . esc_attr( $k ) . '"><b>' . esc_html( $l ) . '</b><div class="sd-bar"><i style="width:' . (int) $m[ $k ][0] . '%"></i></div><small>' . esc_html( $m[ $k ][1] ) . '</small></div>';
		}
		return $h . '</aside>';
	}

	/* =================== Οθόνη =================== */

	/** Το επόμενο βήμα που χρειάζεται δουλειά. */
	public static function first_step( $class ) {
		$units = SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) );
		if ( ! self::order_done( $units ) ) {
			return array( 0, 0 );
		}
		foreach ( self::cycle_order( $units ) as $c ) {
			list( $vals, $u ) = self::values( $class, $units, $c );
			$m                = self::missing( $vals, $u );
			if ( $m ) {
				return array( $c, (int) min( array_keys( $m ) ) );
			}
		}
		return array( 0, 9 );
	}

	/** Σειρά όλων των βημάτων: [[k, v], ...]. */
	private static function sequence( $units ) {
		$seq = array( array( 0, 0 ) );
		foreach ( self::cycle_order( $units ) as $c ) {
			foreach ( array_keys( self::STEPS ) as $v ) {
				$seq[] = array( $c, $v );
			}
		}
		$seq[] = array( 0, 9 );
		return $seq;
	}

	public static function render( $class_id ) {
		$class = SD_Data::get_class( $class_id );
		if ( ! $class || ! SD_Workshops::can_view( $class ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτό το τμήμα.</p>';
			return;
		}
		$form  = SD_Workshops::form_of_class( $class->id );
		$units = SD_Workshops::units( $form );
		$edit  = current_user_can( SD_Roles::CAP_MANAGE ) || SD_Workshops::is_class_teacher( $class->id );
		$f1a   = SD_Workshops::can_edit( $class, $form );

		if ( isset( $_GET['v'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$k = isset( $_GET['k'] ) ? absint( $_GET['k'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			$v = absint( $_GET['v'] ); // phpcs:ignore WordPress.Security.NonceVerification
		} else {
			list( $k, $v ) = self::first_step( $class );
		}
		if ( $v && 9 !== $v && ( ! isset( SD_Workshops::CYCLES[ $k ] ) || ! isset( self::STEPS[ $v ] ) ) ) {
			list( $k, $v ) = array( 0, 0 );
		}
		if ( in_array( $v, array( 1, 2, 3, 4 ), true ) && ! isset( self::cycle_units( $units )[ $k ] ) ) {
			$k = 0;
			$v = 0;
		}

		$seq = self::sequence( $units );
		$pos = 0;
		foreach ( $seq as $i => $s ) {
			if ( $s[0] === $k && $s[1] === $v ) {
				$pos = $i;
			}
		}
		$prev  = $pos > 0 ? $seq[ $pos - 1 ] : null;
		$next  = $pos < count( $seq ) - 1 ? $seq[ $pos + 1 ] : null;
		$label = SD_Data::class_label( $class );

		if ( $edit ) {
			SD_AiField::mark( 'f1a', $class->id );
		}
		echo '<p class="sd-back"><a href="' . esc_url( SD_Frontend::url( 'arxiki' ) ) . '">← Αρχική</a></p>';
		echo '<h1>Εργαστήρια του ' . esc_html( $label ) . '</h1>';

		// Ταμπέλες κύκλων με τη σειρά της χρονιάς.
		echo '<nav class="sd-wiz-cycles" aria-label="Θεματικοί κύκλοι"><a class="sd-wiz-cy' . ( 0 === $v ? ' is-cur' : '' ) . '" href="' . esc_url( self::url( $class->id, 0, 0 ) ) . '">Η σειρά της χρονιάς</a>';
		$cu = self::cycle_units( $units );
		foreach ( self::cycle_order( $units ) as $c ) {
			list( $vals, $u ) = self::values( $class, $units, $c );
			$done            = ! self::missing( $vals, $u );
			$period          = isset( $cu[ $c ] ) ? trim( substr( SD_Workshops::UNITS[ $cu[ $c ] ], strpos( SD_Workshops::UNITS[ $cu[ $c ] ], '· ' ) + strlen( '· ' ) ) ) : 'χωρίς περίοδο';
			echo '<a class="sd-wiz-cy sd-k' . (int) $c . ( $k === $c && $v ? ' is-cur' : '' ) . ( isset( $cu[ $c ] ) ? '' : ' is-off' ) . '" href="' . esc_url( isset( $cu[ $c ] ) ? self::url( $class->id, $c, 1 ) : self::url( $class->id, 0, 0 ) ) . '"><i></i><span>' . esc_html( SD_Workshops::CYCLES[ $c ] ) . '<small>' . esc_html( $period ) . ( $done ? ' · ✓' : '' ) . '</small></span></a>';
		}
		echo '<a class="sd-wiz-cy' . ( 9 === $v ? ' is-cur' : '' ) . '" href="' . esc_url( self::url( $class->id, 0, 9 ) ) . '">Έλεγχος</a></nav>';

		if ( $form && ! $f1a ) {
			echo '<p class="sd-notice sd-ok">Το Έντυπο 1Α ' . esc_html( SD_Workshops::STATUS[ $form->status ] ?? '' ) . '. Οι απαντήσεις που αφορούν το 1Α κλείδωσαν· τα υπόλοιπα (υποθεματική, συνεργάτες, προσαρμογές) αλλάζουν ακόμη.</p>';
		}

		echo '<div class="sd-wiz">';
		if ( $v && 9 !== $v ) {
			echo '<ol class="sd-wiz-steps" aria-label="Βήματα του κύκλου">';
			list( $vals, $u ) = self::values( $class, $units, $k );
			$miss            = self::missing( $vals, $u );
			// Τα βήματα 3–4 είναι προαιρετικά: «✓» μόνο αν έχουν κάτι.
			$opt = array(
				3 => (int) $vals['action_id'] || (int) $vals['citizen_hours'] || '' !== trim( $vals['visits'] . $vals['deliverable'] ),
				4 => '' !== trim( $vals['teachers'] . $vals['adaptations'] ),
			);
			foreach ( self::STEPS as $n => $l ) {
				$done = isset( $opt[ $n ] ) ? $opt[ $n ] : ! isset( $miss[ $n ] );
				$cls  = $n === $v ? 'is-cur' : ( $done ? 'is-done' : '' );
				echo '<li class="' . esc_attr( $cls ) . '"><a href="' . esc_url( self::url( $class->id, $k, $n ) ) . '"' . ( $n === $v ? ' aria-current="step"' : '' ) . '><span class="sd-n">' . ( 'is-done' === $cls ? '✓' : (int) $n ) . '</span><span>' . esc_html( $l ) . '</span></a></li>';
			}
			echo '</ol>';
		} else {
			echo '<div class="sd-wiz-steps sd-wiz-steps-empty"></div>';
		}

		echo '<div class="sd-wiz-pane">';
		$go = $next ? self::url( $class->id, $next[0], $next[1] ) : SD_Frontend::url( 'arxiki' );
		if ( 9 === $v ) {
			self::step_check( $class, $form, $units, $f1a );
		} else {
			echo '<form method="post" class="sd-form sd-wiz-form" data-autosave data-class="' . (int) $class->id . '" data-k="' . (int) $k . '">';
			wp_nonce_field( SD_Frontend::NONCE );
			echo '<input type="hidden" name="sd_do" value="wiz_save"><input type="hidden" name="class_id" value="' . (int) $class->id . '"><input type="hidden" name="k" value="' . (int) $k . '"><input type="hidden" name="v" value="' . (int) $v . '"><input type="hidden" name="go" value="' . esc_attr( $go ) . '">';
			$dis = $edit ? '' : ' disabled';
			if ( 0 === $v ) {
				self::step_order( $class, $units, $f1a && $edit );
			} else {
				list( $vals ) = self::values( $class, $units, $k );
				$lock         = $f1a ? $dis : ' disabled';
				call_user_func( array( __CLASS__, 'step_' . $v ), $class, $k, $vals, $dis, $lock );
			}
			echo '<div class="sd-wiz-nav">';
			echo $prev ? '<a class="sd-button sd-quiet" href="' . esc_url( self::url( $class->id, $prev[0], $prev[1] ) ) . '">Προηγούμενο</a>' : '<span></span>';
			echo '<span class="sd-saved" aria-live="polite"></span>';
			echo '<button class="sd-button sd-big-btn" type="submit">' . esc_html( $next && 9 === $next[1] ? 'Έλεγχος' : 'Επόμενο βήμα' ) . '</button>';
			echo '</div></form>';
			echo '<script type="application/json" id="sd-wiz-cfg">' . wp_json_encode( array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sd_wiz' ) ) ) . '</script>';
		}
		echo '</div>';
		echo self::meters_html( $class ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		echo '<p class="sd-help sd-wiz-alt">Προτιμάτε όλα μαζί σε μία σελίδα; <a href="' . esc_url( SD_Frontend::url( 'ergastiria', array( 'class' => $class->id ) ) ) . '">Έντυπο 1Α</a> · <a href="' . esc_url( SD_Frontend::url( 'ergastiria', array( 'sxedio' => $class->id ) ) ) . '">Σχέδιο τμήματος</a></p>';
	}

	private static function q( $title, $hint = '' ) {
		echo '<p class="sd-q">' . esc_html( $title ) . '</p>' . ( $hint ? '<p class="sd-hint">' . esc_html( $hint ) . '</p>' : '' );
	}

	private static function step_order( $class, $units, $can ) {
		self::q( 'Με ποια σειρά θα κάνει η τάξη τους 4 θεματικούς κύκλους;', 'Κάθε κύκλος κρατά περίπου δύο μήνες. Διαλέξτε έναν για κάθε περίοδο· κάθε κύκλος μπαίνει μία φορά.' );
		$dis = $can ? '' : ' disabled';
		foreach ( SD_Workshops::UNITS as $n => $l ) {
			$period = trim( substr( $l, strpos( $l, '· ' ) + strlen( '· ' ) ) );
			echo '<fieldset class="sd-order-row"><legend>' . esc_html( $period ) . '</legend><div class="sd-chips">';
			foreach ( SD_Workshops::CYCLES as $c => $name ) {
				echo '<label class="sd-chip sd-k' . (int) $c . '"><input type="radio" name="order[' . (int) $n . ']" value="' . (int) $c . '" data-unit="' . (int) $n . '"' . checked( (int) $units[ $n ]->cycle, $c, false ) . $dis . '><span>' . esc_html( $name ) . '</span></label>';
			}
			echo '</div></fieldset>';
		}
		if ( ! $can ) {
			echo '<p class="sd-help">Η σειρά δεν αλλάζει τώρα: το Έντυπο 1Α έχει υποβληθεί.</p>';
		}
	}

	private static function step_1( $class, $c, $vals, $dis, $lock ) {
		self::q( 'Ποιο θέμα θα δουλέψει η τάξη σας;', 'Διαλέξτε ένα. Με την ένδειξη «Της τάξης» είναι αυτό που προτείνει το πρόγραμμα για τη ' . ( SD_Data::GRADES[ (int) $class->grade ] ?? '' ) . '.' );
		$default = SD_Workshops::GRADE_SUBTHEMES[ (int) $class->grade ][ $c ] ?? '';
		echo '<div class="sd-choices" role="radiogroup" aria-label="Υποθεματική">';
		foreach ( SD_Workshops::cycle_subthemes( $c ) as $id => $l ) {
			$text = trim( substr( $l, strlen( $id ) ) );
			echo '<label class="sd-choice sd-k' . (int) $c . '"><input type="radio" name="subtheme" value="' . esc_attr( $id ) . '"' . checked( $vals['subtheme'], $id, false ) . $dis . '><span class="sd-pill-n">' . esc_html( $id ) . '</span><b>' . esc_html( $text ) . '</b>' . ( $id === $default ? '<small>Της τάξης</small>' : '' ) . '</label>';
		}
		echo '</div>';
		echo '<label class="sd-field">Πώς θα λέγεται το εργαστήριο;<span class="sd-fhint">Ένας απλός τίτλος, όπως θα τον λέγατε στα παιδιά. Αλλάζει όποτε θέλετε.</span><input type="text" name="title" maxlength="255" value="' . esc_attr( $vals['title'] ) . '"' . $lock . '></label>';
		echo '<label class="sd-field">Τι θέλετε να κερδίσουν τα παιδιά;<span class="sd-fhint">Ένας ή δύο στόχοι, σε απλά λόγια.</span><textarea name="goals" rows="4"' . $lock . '>' . esc_textarea( $vals['goals'] ) . '</textarea></label>';
	}

	private static function step_2( $class, $c, $vals, $dis, $lock ) {
		self::q( 'Τι θα κάνουν τα παιδιά;', 'Τρεις-τέσσερις δραστηριότητες, μία σε κάθε γραμμή. Δεν χρειάζεται λεπτομέρεια.' );
		echo '<label class="sd-field">Δραστηριότητες<textarea name="activities" rows="6"' . $lock . '>' . esc_textarea( $vals['activities'] ) . '</textarea></label>';
		echo '<fieldset class="sd-field"><legend>Πόσες εβδομάδες;</legend><div class="sd-chips">';
		foreach ( array( 5, 6, 7 ) as $w ) {
			echo '<label class="sd-chip"><input type="radio" name="weeks" value="' . (int) $w . '"' . checked( (int) $vals['weeks'], $w, false ) . $lock . '><span>' . (int) $w . ' εβδομάδες</span></label>';
		}
		echo '</div></fieldset>';
		$sel = '' === $vals['skills'] ? array() : explode( ',', $vals['skills'] );
		echo '<fieldset class="sd-field"><legend>Ποιες δεξιότητες καλλιεργούνται;</legend><input type="hidden" name="skills_sent" value="1"><div class="sd-chips">';
		foreach ( SD_Workshops::SKILLS as $key => $l ) {
			echo '<label class="sd-chip"><input type="checkbox" name="skills[]" value="' . esc_attr( $key ) . '"' . ( in_array( $key, $sel, true ) ? ' checked' : '' ) . $lock . '><span>' . esc_html( $l ) . '</span></label>';
		}
		echo '</div></fieldset>';
		echo '<label class="sd-field">Υλικό που θα χρησιμοποιήσετε (προαιρετικό)<span class="sd-fhint">π.χ. το εγκεκριμένο πρόγραμμα του ΙΕΠ.</span><input type="text" name="material" value="' . esc_attr( $vals['material'] ) . '"' . $lock . '></label>';
	}

	private static function step_3( $class, $c, $vals, $dis, $lock ) {
		self::q( 'Με τι συνδέεται το εργαστήριο;', 'Όλα προαιρετικά. Αν δεν ξέρετε ακόμη, προχωρήστε· τα συμπληρώνετε όποτε θέλετε.' );
		$actions = SD_Plans::year_actions( $class->year_id, $class->grade );
		echo '<fieldset class="sd-field"><legend>Υπηρετεί κάποιο Σχέδιο Δράσης του σχολείου;</legend><div class="sd-choices sd-choices-list">';
		echo '<label class="sd-choice"><input type="radio" name="action_id" value="0"' . checked( (int) $vals['action_id'], 0, false ) . $lock . '><b>Όχι</b></label>';
		foreach ( $actions as $row ) {
			list( $p, $g, $a ) = $row;
			echo '<label class="sd-choice"><input type="radio" name="action_id" value="' . (int) $a->id . '"' . checked( (int) $vals['action_id'], (int) $a->id, false ) . $lock . '><b>' . esc_html( $p->title ) . '</b><small>' . esc_html( wp_html_excerpt( $a->description, 140, '…' ) ) . '</small></label>';
		}
		echo '</div></fieldset>';
		echo '<label class="sd-field">Ώρες για Δράση Ενεργού Πολίτη<span class="sd-fhint">0 αν δεν γίνεται Δράση Ενεργού Πολίτη μέσα σε αυτό το εργαστήριο.</span><input type="number" min="0" max="30" name="citizen_hours" value="' . (int) $vals['citizen_hours'] . '"' . $lock . ' style="max-width:8em"></label>';
		echo '<label class="sd-field">Επισκέψεις ή φορείς που θα συνεργαστείτε<span class="sd-fhint">Για κάθε επίσκεψη θα χρειαστεί αίτημα στις «Επισκέψεις».</span><textarea name="visits" rows="2"' . $lock . '>' . esc_textarea( $vals['visits'] ) . '</textarea></label>';
		echo '<label class="sd-field">Τι θα φτιάξουν τα παιδιά στο τέλος;<span class="sd-fhint">Το «παραδοτέο»: π.χ. αφίσα, βίντεο, παρουσίαση στους γονείς.</span><input type="text" name="deliverable" value="' . esc_attr( $vals['deliverable'] ) . '"' . $lock . '></label>';
	}

	private static function step_4( $class, $c, $vals, $dis, $lock ) {
		self::q( 'Ποιοι/ποιες συμμετέχουν;', 'Ο/Η υπεύθυνος/η Εργαστηρίων του τμήματος μπαίνει αυτόματα. Γράψτε όσους/όσες άλλους/ες εκπαιδευτικούς βοηθούν σε αυτόν τον κύκλο, ακόμη κι αν δεν είναι στα Σχέδια Δράσης.' );
		$resp = array();
		foreach ( SD_Data::class_teachers( $class->id ) as $t ) {
			$u = get_userdata( $t->user_id );
			if ( $u && 'ergastiria' === $t->class_role ) {
				$resp[] = $u->display_name;
			}
		}
		echo '<fieldset class="sd-field"><legend>Άλλοι/ες εκπαιδευτικοί</legend><span class="sd-fhint">Πατήστε όσους/όσες συμμετέχουν.</span>' . SD_Staff::picker( 'teachers', $vals['teachers'], $resp, $dis ) . '</fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-field">Χρειάζονται προσαρμογές ώστε να συμμετέχουν όλα τα παιδιά; (προαιρετικό)<span class="sd-fhint">Χωρίς ονόματα μαθητών.</span><textarea name="adaptations" rows="3"' . $dis . '>' . esc_textarea( $vals['adaptations'] ) . '</textarea></label>';
	}

	private static function step_check( $class, $form, $units, $f1a ) {
		self::q( 'Έλεγχος', 'Εδώ φαίνεται αν λείπει κάτι. Πατήστε σε ό,τι λείπει για να πάτε κατευθείαν στο σωστό βήμα.' );
		$list = array();
		if ( ! self::order_done( $units ) ) {
			$list[] = array( 'Ορίστε τη σειρά των 4 κύκλων στη χρονιά.', self::url( $class->id, 0, 0 ) );
		}
		foreach ( self::cycle_order( $units ) as $c ) {
			list( $vals, $u ) = self::values( $class, $units, $c );
			foreach ( self::missing( $vals, $u ) as $n => $x ) {
				$list[] = array( SD_Workshops::CYCLES[ $c ] . ': ' . mb_strtolower( self::STEPS[ $n ] ) . '.', self::url( $class->id, $c, $n ) );
			}
		}
		if ( $list ) {
			echo '<ul class="sd-check-list">';
			foreach ( $list as $l ) {
				echo '<li><a href="' . esc_url( $l[1] ) . '">' . esc_html( $l[0] ) . '</a></li>';
			}
			echo '</ul>';
		} else {
			echo '<p class="sd-allgood">Όλοι οι κύκλοι έχουν τα βασικά.</p>';
		}
		foreach ( SD_Workshops::issues( $units ) as $i ) {
			if ( 'warning' === $i[0] ) {
				echo '<p class="sd-help">ⓘ ' . esc_html( $i[1] ) . '</p>';
			}
		}
		echo SD_Docs::grid( array( SD_Docs::f1a( $class ), SD_Docs::prog( $class ), SD_Docs::sxtm( $class ) ), 'Τα έντυπα που βγήκαν' ); // phpcs:ignore WordPress.Security.EscapeOutput
		$errors = false;
		foreach ( SD_Workshops::issues( $units ) as $i ) {
			$errors = $errors || 'error' === $i[0];
		}
		if ( $f1a && SD_Workshops::is_class_teacher( $class->id ) || ( $f1a && current_user_can( SD_Roles::CAP_MANAGE ) ) ) {
			if ( ! $errors && ( ! $form || in_array( $form->status, array( 'proxeiro', 'epistrafike' ), true ) ) ) {
				SD_Frontend::form_open( 'f1a_submit' );
				echo '<input type="hidden" name="class_id" value="' . (int) $class->id . '">';
				echo '<p>Όταν είστε έτοιμοι, στείλτε το Έντυπο 1Α για έλεγχο. Μετά την υποβολή κλειδώνει· αν χρειαστεί διόρθωση, θα σας επιστραφεί με σχόλιο.</p>';
				echo '<p><button class="sd-button sd-big-btn" type="submit">Υποβολή του 1Α για έλεγχο</button></p></form>';
			}
		}
	}
}
