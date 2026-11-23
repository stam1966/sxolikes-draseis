<?php
/**
 * Έντυπο 1Α – Ετήσιος σχεδιασμός Εργαστηρίων Δεξιοτήτων ανά τμήμα.
 *
 * Πηγές: ΦΕΚ Β΄ 3567/2021 (θεματικές, υποθεματικές ανά τάξη, κύκλοι δεξιοτήτων),
 * ΦΕΚ Β΄ 3791/2021 (4 ενότητες, 5–7 εβδομάδες), σχολικό Έντυπο 1Α.
 *
 * Ροή: Πρόχειρο → Υποβλήθηκε → (Επιστράφηκε) → Ελέγχθηκε.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Workshops {

	const CYCLES = array(
		1 => 'Ζω καλύτερα – Ευ ζην',
		2 => 'Φροντίζω το περιβάλλον',
		3 => 'Ενδιαφέρομαι και ενεργώ',
		4 => 'Δημιουργώ και καινοτομώ',
	);

	const SUBTHEMES = array(
		'1.1' => 'Υγεία: Διατροφή – Αυτομέριμνα, Οδική Ασφάλεια',
		'1.2' => 'Ψυχική και Συναισθηματική Υγεία – Πρόληψη',
		'1.3' => 'Γνωρίζω το σώμα μου – Σεξουαλική Διαπαιδαγώγηση',
		'2.1' => 'Οικολογία – Παγκόσμια και Τοπική Φυσική Κληρονομιά',
		'2.2' => 'Φυσικές Καταστροφές, Πολιτική Προστασία',
		'2.3' => 'Παγκόσμια και Τοπική Πολιτιστική Κληρονομιά',
		'3.1' => 'Ανθρώπινα Δικαιώματα',
		'3.2' => 'Εθελοντισμός – Διαμεσολάβηση',
		'3.3' => 'Συμπερίληψη: Αλληλοσεβασμός, Διαφορετικότητα',
		'4.1' => 'STEM – Εκπαιδευτική Ρομποτική',
		'4.2' => 'Επιχειρηματικότητα – Αγωγή Σταδιοδρομίας – Γνωριμία με επαγγέλματα',
	);

	/** Υποχρεωτική υποθεματική ανά τάξη και θεματική (ΦΕΚ 3567/2021, Β.2.2). */
	const GRADE_SUBTHEMES = array(
		1 => array( 1 => '1.1', 2 => '2.1', 3 => '3.1', 4 => '4.1' ),
		2 => array( 1 => '1.2', 2 => '2.2', 3 => '3.2', 4 => '4.2' ),
		3 => array( 1 => '1.3', 2 => '2.3', 3 => '3.3', 4 => '4.2' ),
		4 => array( 1 => '1.1', 2 => '2.1', 3 => '3.1', 4 => '4.1' ),
		5 => array( 1 => '1.2', 2 => '2.2', 3 => '3.2', 4 => '4.1' ),
		6 => array( 1 => '1.3', 2 => '2.3', 3 => '3.3', 4 => '4.2' ),
	);

	const SKILLS = array(
		'aionas'     => '21ου αιώνα',
		'zois'       => 'Ζωής',
		'texnologia' => 'Τεχνολογίας – επιστήμης',
		'nou'        => 'Του νου',
	);

	const UNITS = array(
		1 => '1η ενότητα · Οκτ.–Νοέ.',
		2 => '2η ενότητα · Δεκ.–Ιαν.',
		3 => '3η ενότητα · Φεβ.–Μαρ.',
		4 => '4η ενότητα · Απρ.–Μάι.',
	);

	const STATUS = array(
		'proxeiro'    => 'Πρόχειρο',
		'ypovlithike' => 'Υποβλήθηκε',
		'epistrafike' => 'Επιστράφηκε για διόρθωση',
		'elegxthike'  => 'Ελέγχθηκε',
	);

	const OPT_DEADLINE = 'sd_deadline_f1a';

	public static function subtheme( $grade, $cycle ) {
		$id = self::GRADE_SUBTHEMES[ (int) $grade ][ (int) $cycle ] ?? '';
		return $id ? $id . ' ' . self::SUBTHEMES[ $id ] : '';
	}

	/** Οι υποθεματικές μιας θεματικής: [id => «id τίτλος»]. */
	public static function cycle_subthemes( $cycle ) {
		$out = array();
		foreach ( self::SUBTHEMES as $id => $l ) {
			if ( 0 === strpos( $id, (int) $cycle . '.' ) ) {
				$out[ $id ] = $id . ' ' . $l;
			}
		}
		return $out;
	}

	/** Η υποθεματική που διάλεξε το τμήμα (Σχέδιο τμήματος), αλλιώς αυτή της τάξης. */
	public static function class_subtheme_id( $class, $cycle ) {
		$plan = SD_ErgPlan::class_plan( $class->id );
		$id   = $plan ? ( $plan->cycle_arr[ (int) $cycle ]['subtheme'] ?? '' ) : '';
		if ( $id && isset( self::SUBTHEMES[ $id ] ) && 0 === strpos( $id, (int) $cycle . '.' ) ) {
			return $id;
		}
		return self::GRADE_SUBTHEMES[ (int) $class->grade ][ (int) $cycle ] ?? '';
	}

	public static function class_subtheme( $class, $cycle ) {
		$id = self::class_subtheme_id( $class, $cycle );
		return $id ? $id . ' ' . self::SUBTHEMES[ $id ] : '';
	}

	public static function deadline() {
		return (string) get_option( self::OPT_DEADLINE, '2026-11-27' );
	}

	/* =================== Δεδομένα =================== */

	private static function t( $n ) {
		return SD_Data::table( $n );
	}

	public static function form_of_class( $class_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'f1a' ) . ' WHERE class_id = %d', $class_id ) );
	}

	/** Οι 4 ενότητες, με κενές εγγραφές όπου δεν υπάρχουν ακόμη. */
	public static function units( $form ) {
		global $wpdb;
		$rows = $form ? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'f1a_units' ) . ' WHERE f1a_id = %d', $form->id ) ) : array();
		$out  = array();
		foreach ( array_keys( self::UNITS ) as $n ) {
			$out[ $n ] = (object) array(
				'unit_no'       => $n,
				'cycle'         => 0,
				'title'         => '',
				'weeks'         => 0,
				'skills'        => '',
				'goals'         => '',
				'activities'    => '',
				'material'      => '',
				'action_id'     => 0,
				'citizen_hours' => 0,
				'visits'        => '',
				'deliverable'   => '',
			);
		}
		foreach ( $rows as $r ) {
			$out[ (int) $r->unit_no ] = $r;
		}
		return $out;
	}

	private static function ensure_form( $class ) {
		global $wpdb;
		$form = self::form_of_class( $class->id );
		if ( $form ) {
			return $form;
		}
		$wpdb->insert(
			self::t( 'f1a' ),
			array(
				'year_id'     => (int) $class->year_id,
				'class_id'    => (int) $class->id,
				'status'      => 'proxeiro',
				'review_note' => '',
				'updated_by'  => get_current_user_id(),
				'updated_at'  => current_time( 'mysql' ),
			)
		);
		return self::form_of_class( $class->id );
	}

	public static function is_class_teacher( $class_id, $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		foreach ( SD_Data::class_teachers( $class_id ) as $t ) {
			if ( (int) $t->user_id === (int) $user_id ) {
				return true;
			}
		}
		return false;
	}

	public static function can_view( $class ) {
		return current_user_can( SD_Roles::CAP_REVIEW ) || self::is_class_teacher( $class->id );
	}

	public static function can_edit( $class, $form ) {
		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return true;
		}
		$status = $form ? $form->status : 'proxeiro';
		return self::is_class_teacher( $class->id ) && in_array( $status, array( 'proxeiro', 'epistrafike' ), true );
	}

	/** Έλεγχοι: [ ['error'|'warning', κείμενο], ... ]. Τα «error» εμποδίζουν την υποβολή. */
	public static function issues( $units ) {
		$out    = array();
		$used   = array();
		$weeks  = 0;
		foreach ( $units as $n => $u ) {
			$label = $n . 'η ενότητα';
			$empty = ! $u->cycle && '' === trim( $u->title ) && ! $u->weeks && '' === trim( $u->goals ) && '' === trim( $u->activities );
			if ( $empty ) {
				$out[] = array( 'error', $label . ': δεν έχει συμπληρωθεί ακόμη.' );
				continue;
			}
			if ( ! $u->cycle ) {
				$out[] = array( 'error', $label . ': διαλέξτε θεματική ενότητα.' );
			} else {
				$used[] = (int) $u->cycle;
			}
			if ( '' === trim( $u->title ) ) {
				$out[] = array( 'error', $label . ': λείπει ο τίτλος προγράμματος.' );
			}
			if ( $u->weeks && ( $u->weeks < 5 || $u->weeks > 7 ) ) {
				$out[] = array( 'warning', $label . ': ' . (int) $u->weeks . ' εβδομάδες. Το πλαίσιο προβλέπει 5–7 ανά θεματική.' );
			}
			if ( ! $u->weeks ) {
				$out[] = array( 'warning', $label . ': δεν έχουν δηλωθεί εβδομάδες.' );
			}
			if ( '' === trim( $u->goals ) || '' === trim( $u->activities ) ) {
				$out[] = array( 'warning', $label . ': συμπληρώστε βασικούς στόχους και δραστηριότητες.' );
			}
			$weeks += (int) $u->weeks;
		}
		foreach ( self::CYCLES as $c => $name ) {
			$times = count( array_keys( $used, $c, true ) );
			if ( 0 === $times && $used ) {
				$out[] = array( 'error', 'Δεν καλύπτεται η θεματική «' . $name . '». Υποχρεωτικά και οι 4 μέσα στο έτος.' );
			} elseif ( $times > 1 ) {
				$out[] = array( 'error', 'Η θεματική «' . $name . '» εμφανίζεται ' . $times . ' φορές.' );
			}
		}
		if ( $weeks && ( $weeks < 20 || $weeks > 28 ) ) {
			$out[] = array( 'warning', 'Σύνολο εβδομάδων: ' . $weeks . '. Με 5–7 εβδομάδες ανά θεματική το σύνολο είναι 20–28.' );
		}
		return $out;
	}

	private static function has_errors( $issues ) {
		foreach ( $issues as $i ) {
			if ( 'error' === $i[0] ) {
				return true;
			}
		}
		return false;
	}

	/* =================== Ενέργειες (POST) =================== */

	public static function handle( $do ) {
		global $wpdb;
		if ( ! in_array( $do, array( 'f1a_save_unit', 'f1a_submit', 'f1a_review' ), true ) ) {
			return null;
		}
		$class = SD_Data::get_class( isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0 );
		$list  = SD_Frontend::url( 'ergastiria' );
		if ( ! $class ) {
			return array( new WP_Error( 'sd', 'Το τμήμα δεν βρέθηκε.' ), $list );
		}
		$back = SD_Frontend::url( 'ergastiria', array( 'class' => $class->id ) );
		$form = self::form_of_class( $class->id );

		if ( 'f1a_review' === $do ) {
			if ( ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
				return array( new WP_Error( 'sd', 'Μόνο η Επιτροπή και η Διεύθυνση ελέγχουν τα έντυπα.' ), $back );
			}
			if ( ! $form || ! in_array( $form->status, array( 'ypovlithike', 'elegxthike' ), true ) ) {
				return array( new WP_Error( 'sd', 'Το έντυπο δεν έχει υποβληθεί.' ), $back );
			}
			if ( ! SD_Notify::may_review( $form->submitted_by, $class->id ) ) {
				return array( new WP_Error( 'sd', SD_Notify::why_not( $form->submitted_by, $class->id ) ), $back );
			}
			$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
			$note     = isset( $_POST['review_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_note'] ) ) : '';
			if ( 'return' === $decision && '' === trim( $note ) ) {
				return array( new WP_Error( 'sd', 'Γράψτε τι χρειάζεται διόρθωση.' ), $back );
			}
			$wpdb->update(
				self::t( 'f1a' ),
				array(
					'status'      => 'return' === $decision ? 'epistrafike' : 'elegxthike',
					'review_note' => $note,
					'reviewed_by' => get_current_user_id(),
					'reviewed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $form->id )
			);
			SD_Notify::decided( 'Έντυπο 1Α · τμήμα ' . SD_Data::class_label( $class ), $class->id, $form->submitted_by, 'return' === $decision, $note, $back );
			return array( true, $back );
		}

		if ( ! self::can_edit( $class, $form ) ) {
			return array( new WP_Error( 'sd', 'Το έντυπο δεν μπορεί να αλλάξει τώρα (το επεξεργάζονται οι εκπαιδευτικοί του τμήματος όσο είναι πρόχειρο ή έχει επιστραφεί).' ), $back );
		}
		$form = self::ensure_form( $class );

		if ( 'f1a_submit' === $do ) {
			if ( self::has_errors( self::issues( self::units( $form ) ) ) ) {
				return array( new WP_Error( 'sd', 'Υπάρχουν ελλείψεις που πρέπει να διορθωθούν πριν την υποβολή.' ), $back );
			}
			$wpdb->update(
				self::t( 'f1a' ),
				array(
					'status'       => 'ypovlithike',
					'submitted_by' => get_current_user_id(),
					'submitted_at' => current_time( 'mysql' ),
				),
				array( 'id' => $form->id )
			);
			SD_Notify::submitted( 'Έντυπο 1Α · τμήμα ' . SD_Data::class_label( $class ), $class->id, $back );
			return array( true, $back );
		}

		// f1a_save_unit
		$n = isset( $_POST['unit_no'] ) ? absint( $_POST['unit_no'] ) : 0;
		if ( ! isset( self::UNITS[ $n ] ) ) {
			return array( new WP_Error( 'sd', 'Άγνωστη ενότητα.' ), $back );
		}
		$cycle  = isset( $_POST['cycle'] ) ? absint( $_POST['cycle'] ) : 0;
		$skills = isset( $_POST['skills'] ) && is_array( $_POST['skills'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['skills'] ) ) : array();
		$action = isset( $_POST['action_id'] ) ? absint( $_POST['action_id'] ) : 0;
		if ( $action ) {
			// Μόνο ενέργεια σχεδίου του ίδιου έτους που αφορά την τάξη.
			$valid = false;
			foreach ( SD_Plans::year_actions( $class->year_id, $class->grade ) as $row ) {
				if ( (int) $row[2]->id === $action ) {
					$valid = true;
				}
			}
			$action = $valid ? $action : 0;
		}
		$data = array(
			'cycle'         => isset( self::CYCLES[ $cycle ] ) ? $cycle : 0,
			'title'         => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'weeks'         => isset( $_POST['weeks'] ) ? min( 20, absint( $_POST['weeks'] ) ) : 0,
			'skills'        => implode( ',', array_values( array_intersect( array_keys( self::SKILLS ), $skills ) ) ),
			'goals'         => isset( $_POST['goals'] ) ? sanitize_textarea_field( wp_unslash( $_POST['goals'] ) ) : '',
			'activities'    => isset( $_POST['activities'] ) ? sanitize_textarea_field( wp_unslash( $_POST['activities'] ) ) : '',
			'material'      => isset( $_POST['material'] ) ? sanitize_text_field( wp_unslash( $_POST['material'] ) ) : '',
			'action_id'     => $action,
			'citizen_hours' => isset( $_POST['citizen_hours'] ) ? min( 30, absint( $_POST['citizen_hours'] ) ) : 0,
			'visits'        => isset( $_POST['visits'] ) ? sanitize_textarea_field( wp_unslash( $_POST['visits'] ) ) : '',
			'deliverable'   => isset( $_POST['deliverable'] ) ? sanitize_text_field( wp_unslash( $_POST['deliverable'] ) ) : '',
		);
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t( 'f1a_units' ) . ' WHERE f1a_id = %d AND unit_no = %d', $form->id, $n ) );
		if ( $exists ) {
			$wpdb->update( self::t( 'f1a_units' ), $data, array( 'id' => (int) $exists ) );
		} else {
			$data['f1a_id']  = $form->id;
			$data['unit_no'] = $n;
			$wpdb->insert( self::t( 'f1a_units' ), $data );
		}
		$wpdb->update(
			self::t( 'f1a' ),
			array(
				'updated_by' => get_current_user_id(),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $form->id )
		);
		return array( true, $back . '#enotita-' . $n );
	}

	/* =================== Οθόνες =================== */

	private static function status_badge( $form ) {
		$s   = $form ? $form->status : '';
		$cls = array(
			'proxeiro'    => '',
			'ypovlithike' => 'sd-badge-info',
			'epistrafike' => 'sd-badge-warn',
			'elegxthike'  => 'sd-badge-ok',
		);
		$label = $s ? self::STATUS[ $s ] : 'Δεν έχει ξεκινήσει';
		return '<span class="sd-badge ' . esc_attr( $cls[ $s ] ?? '' ) . '">' . esc_html( $label ) . '</span>';
	}

	private static function action_label( $action_id, $year_id ) {
		if ( ! $action_id ) {
			return '';
		}
		foreach ( SD_Plans::year_actions( $year_id ) as $row ) {
			list( $p, $g, $a ) = $row;
			if ( (int) $a->id === (int) $action_id ) {
				return self::short_action( $p, $g, $a );
			}
		}
		return '';
	}

	private static function short_action( $p, $g, $a ) {
		$text = wp_html_excerpt( $a->description, 90, '…' );
		return trim( $p->code ) . ' › ' . ( $g->title ? $g->title : 'Στόχος ' . $g->ord ) . ' › ' . ( SD_Eval::ACTION_KINDS[ $a->kind ] ?? '' ) . ': ' . $text;
	}

	public static function render() {
		if ( isset( $_GET['print'] ) ) {
			self::render_print( absint( $_GET['print'] ) );
		} elseif ( isset( $_GET['sxedio'] ) ) {
			SD_ErgPlan::render_class( absint( $_GET['sxedio'] ) );
		} elseif ( isset( $_GET['sxolio'] ) ) {
			SD_ErgPlan::render_school();
		} elseif ( isset( $_GET['class'] ) ) {
			self::render_form( absint( $_GET['class'] ) );
		} else {
			self::render_list();
		}
	}

	/** Αρχική των Εργαστηρίων: ο Προγραμματισμός κάθε τμήματος, όπως στο έντυπο. */
	private static function render_list() {
		$year = SD_Data::active_year();
		if ( ! $year ) {
			echo '<p class="sd-notice sd-error">Δεν υπάρχει τρέχον σχολικό έτος.</p>';
			return;
		}
		echo '<h2>Εργαστήρια Δεξιοτήτων · Προγραμματισμός ' . esc_html( $year->label ) . '</h2>';
		echo '<p class="sd-help">Για κάθε τμήμα: ποιος/ποια υλοποιεί, θεματική, υποθεματική, τίτλος και περίοδος. Συμπληρώνεται από το <strong>Έντυπο 1Α</strong> (σειρά ενοτήτων, περίοδος) και το <strong>Σχέδιο τμήματος</strong> (υποθεματική, τίτλος, άλλοι/ες εκπαιδευτικοί). Προθεσμία υποβολής 1Α: <strong>' . esc_html( mysql2date( 'd/m/Y', self::deadline() ) ) . '</strong>.</p>';

		$classes = current_user_can( SD_Roles::CAP_REVIEW ) ? SD_Data::classes( $year->id ) : SD_Data::classes_of_user( get_current_user_id(), $year->id );
		if ( ! $classes ) {
			echo '<p>Δεν σας έχει αντιστοιχιστεί τμήμα.</p>';
			return;
		}
		if ( current_user_can( SD_Roles::CAP_REVIEW ) ) {
			echo '<p><a class="sd-button sd-small" href="' . esc_url( SD_Frontend::url( 'ergastiria', array( 'sxolio' => 1 ) ) ) . '">Σχέδιο Δράσης Σχολικής Μονάδας</a> <span class="sd-meta">Προγραμματισμός όλων των τμημάτων: ' . SD_Exports::links( 'progall', 0 ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		foreach ( $classes as $c ) {
			$form  = self::form_of_class( $c->id );
			$weeks = 0;
			$links = 0;
			foreach ( self::units( $form ) as $u ) {
				$weeks += (int) $u->weeks;
				$links += $u->action_id ? 1 : 0;
			}
			echo '<section class="sd-card sd-prog"><h3>Τμήμα ' . esc_html( SD_Data::class_label( $c ) ) . ' ' . self::status_badge( $form ) . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<div class="sd-table-wrap"><table class="sd-table"><thead><tr><th>Εκπαιδευτικός</th><th>Θεματική ενότητα</th><th>Υποθεματική</th><th>Τίτλος</th><th>Περίοδος</th></tr></thead><tbody>';
			foreach ( SD_ErgPlan::programming_rows( $c ) as $r ) {
				echo '<tr><td>' . esc_html( $r['teacher'] ? $r['teacher'] : '—' ) . '</td><td>' . esc_html( $r['cycle'] ) . '</td><td>' . esc_html( $r['subtheme'] ) . '</td><td>' . ( '' !== $r['title'] ? '«' . esc_html( $r['title'] ) . '»' : '<span class="sd-meta">χωρίς τίτλο ακόμη</span>' ) . '</td><td>' . esc_html( $r['period'] ? $r['period'] : '—' ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
			echo '<p class="sd-meta">Εβδομάδες: ' . (int) $weeks . ' · Σύνδεση με Σχέδια Δράσης: ' . (int) $links . ' / 4</p>';
			echo '<p><a class="sd-button sd-small" href="' . esc_url( self::url_form( $c ) ) . '">Έντυπο 1Α</a> <a class="sd-button sd-small sd-quiet" href="' . esc_url( SD_Frontend::url( 'ergastiria', array( 'sxedio' => $c->id ) ) ) . '">Σχέδιο τμήματος</a> <span class="sd-meta">Προγραμματισμός: ' . SD_Exports::links( 'prog', $c->id ) . '</span></p></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	private static function url_form( $class ) {
		return SD_Frontend::url( 'ergastiria', array( 'class' => $class->id ) );
	}

	private static function render_form( $class_id ) {
		$class = SD_Data::get_class( $class_id );
		if ( ! $class || ! self::can_view( $class ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτό το τμήμα.</p>';
			return;
		}
		$form   = self::form_of_class( $class->id );
		$units  = self::units( $form );
		$edit   = self::can_edit( $class, $form );
		$issues = self::issues( $units );
		if ( $edit ) {
			SD_AiField::mark( 'f1a', $class->id );
		}

		$by_role = array();
		foreach ( SD_Data::class_teachers( $class->id ) as $t ) {
			$u = get_userdata( $t->user_id );
			if ( $u ) {
				$by_role[ $t->class_role ][] = $u->display_name;
			}
		}

		echo '<p class="sd-back"><a href="' . esc_url( SD_Frontend::url( 'ergastiria' ) ) . '">← Όλα τα τμήματα</a></p>';
		echo '<h2>Έντυπο 1Α · Τμήμα ' . esc_html( SD_Data::class_label( $class ) ) . ' ' . self::status_badge( $form ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="sd-meta">Υπεύθυνος/η Εργαστηρίων: ' . esc_html( isset( $by_role['ergastiria'] ) ? implode( ', ', $by_role['ergastiria'] ) : '—' );
		echo ' · ΠΕ70 τμήματος: ' . esc_html( isset( $by_role['pe70'] ) ? implode( ', ', $by_role['pe70'] ) : '—' );
		echo ' · Εβδομαδιαίες ώρες: ' . (int) $class->workshop_hours . '</p>';
		echo SD_Docs::grid( array( SD_Docs::f1a( $class ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $form && 'epistrafike' === $form->status && $form->review_note ) {
			echo '<p class="sd-notice sd-error"><strong>Σχόλια ελέγχου:</strong> ' . nl2br( esc_html( $form->review_note ) ) . '</p>';
		} elseif ( $form && 'elegxthike' === $form->status ) {
			echo '<p class="sd-notice sd-ok">Το έντυπο ελέγχθηκε' . ( $form->review_note ? ': ' . esc_html( $form->review_note ) : '.' ) . '</p>';
		}

		if ( $issues ) {
			echo '<section class="sd-card sd-issues"><h3>Έλεγχος</h3><ul>';
			foreach ( $issues as $i ) {
				echo '<li class="sd-issue-' . esc_attr( $i[0] ) . '">' . esc_html( $i[1] ) . '</li>';
			}
			echo '</ul></section>';
		}

		$actions = SD_Plans::year_actions( $class->year_id, $class->grade );
		$map     = array();
		foreach ( self::CYCLES as $c => $name ) {
			$map[ $c ] = self::class_subtheme( $class, $c );
		}

		foreach ( $units as $n => $u ) {
			echo '<section class="sd-card sd-unit" id="enotita-' . (int) $n . '"><h3>' . esc_html( self::UNITS[ $n ] ) . '</h3>';
			if ( $edit ) {
				self::unit_form( $class, $u, $actions, $map );
			} else {
				self::unit_view( $class, $u );
			}
			echo '</section>';
		}

		if ( $edit && ( ! $form || in_array( $form->status, array( 'proxeiro', 'epistrafike' ), true ) ) ) {
			echo '<section class="sd-card"><h3>Υποβολή</h3>';
			if ( self::has_errors( $issues ) ) {
				echo '<p>Η υποβολή γίνεται όταν διορθωθούν οι ελλείψεις του ελέγχου.</p>';
			} else {
				SD_Frontend::form_open( 'f1a_submit' );
				echo '<input type="hidden" name="class_id" value="' . (int) $class->id . '">';
				echo '<p>Μετά την υποβολή το έντυπο κλειδώνει. Αν χρειαστεί διόρθωση, η Επιτροπή ή η Διεύθυνση θα το επιστρέψει.</p>';
				echo '<button class="sd-button" type="submit">Υποβολή στη Διεύθυνση</button></form>';
			}
			echo '</section>';
		}

		if ( current_user_can( SD_Roles::CAP_REVIEW ) && $form && in_array( $form->status, array( 'ypovlithike', 'elegxthike' ), true ) && SD_Notify::review_gate( $form->submitted_by, $class->id, $form->status ) ) {
			echo '<section class="sd-card"><h3>Έλεγχος Επιτροπής / Διεύθυνσης</h3>';
			SD_Frontend::form_open( 'f1a_review' );
			echo '<input type="hidden" name="class_id" value="' . (int) $class->id . '">';
			echo '<label class="sd-block">Σχόλια (υποχρεωτικά για επιστροφή)<textarea name="review_note" rows="3">' . esc_textarea( $form->review_note ) . '</textarea></label>';
			echo '<p><button class="sd-button" type="submit" name="decision" value="ok">Σημείωση ως ελεγμένο</button> ';
			echo '<button class="sd-button sd-danger" type="submit" name="decision" value="return">Επιστροφή για διόρθωση</button></p></form></section>';
		}
	}

	private static function unit_form( $class, $u, $actions, $map ) {
		SD_Frontend::form_open( 'f1a_save_unit' );
		echo '<input type="hidden" name="class_id" value="' . (int) $class->id . '"><input type="hidden" name="unit_no" value="' . (int) $u->unit_no . '">';
		echo '<div class="sd-row"><label class="sd-grow">Θεματική ενότητα <select name="cycle" data-subthemes="' . esc_attr( wp_json_encode( $map ) ) . '"><option value="0">—</option>';
		foreach ( self::CYCLES as $c => $name ) {
			echo '<option value="' . (int) $c . '"' . selected( (int) $u->cycle, $c, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>Εβδομάδες (5–7) <input type="number" name="weeks" min="0" max="20" value="' . ( $u->weeks ? (int) $u->weeks : '' ) . '"></label></div>';
		echo '<p class="sd-subtheme">Υποθεματική (αλλάζει στο Σχέδιο τμήματος): <strong data-subtheme-out>' . esc_html( $u->cycle ? self::class_subtheme( $class, $u->cycle ) : '—' ) . '</strong></p>';
		echo '<label class="sd-block">Τίτλος προγράμματος <input name="title" value="' . esc_attr( $u->title ) . '"></label>';
		echo '<fieldset class="sd-sub"><legend>Κύκλοι δεξιοτήτων</legend>';
		$sel = '' === $u->skills ? array() : explode( ',', $u->skills );
		echo '<div class="sd-checks">';
		foreach ( self::SKILLS as $k => $l ) {
			printf( '<label><input type="checkbox" name="skills[]" value="%s"%s> %s</label>', esc_attr( $k ), in_array( $k, $sel, true ) ? ' checked' : '', esc_html( $l ) );
		}
		echo '</div></fieldset>';
		echo '<label class="sd-block">Βασικοί στόχοι (1–2)<textarea name="goals" rows="3">' . esc_textarea( $u->goals ) . '</textarea></label>';
		echo '<label class="sd-block">Βασικές δραστηριότητες (3–4)<textarea name="activities" rows="4">' . esc_textarea( $u->activities ) . '</textarea></label>';
		echo '<label class="sd-block">Υλικό (π.χ. εγκεκριμένο ΠΚΔ του ΙΕΠ) <input name="material" value="' . esc_attr( $u->material ) . '"></label>';

		echo '<label class="sd-block">Σύνδεση με Σχέδιο Δράσης (ενέργεια) <select name="action_id"><option value="0">Δεν συνδέεται</option>';
		$last_plan = 0;
		foreach ( $actions as $row ) {
			list( $p, $g, $a ) = $row;
			if ( (int) $p->id !== $last_plan ) {
				if ( $last_plan ) {
					echo '</optgroup>';
				}
				echo '<optgroup label="' . esc_attr( trim( $p->code . ' ' . $p->title ) ) . '">';
				$last_plan = (int) $p->id;
			}
			echo '<option value="' . (int) $a->id . '"' . selected( (int) $u->action_id, (int) $a->id, false ) . '>' . esc_html( self::short_action( $p, $g, $a ) ) . '</option>';
		}
		if ( $last_plan ) {
			echo '</optgroup>';
		}
		echo '</select></label>';
		if ( ! $actions ) {
			echo '<p class="sd-help">Δεν υπάρχουν ακόμη ενέργειες Σχεδίων Δράσης που να αφορούν αυτή την τάξη.</p>';
		}

		echo '<div class="sd-row"><label>Ώρες για Δράση Ενεργού Πολίτη (0 = όχι) <input type="number" name="citizen_hours" min="0" max="30" value="' . (int) $u->citizen_hours . '"></label></div>';
		echo '<label class="sd-block">Επισκέψεις / φορείς (για καθεμία υποβάλλεται Έντυπο 3)<textarea name="visits" rows="2">' . esc_textarea( $u->visits ) . '</textarea></label>';
		echo '<label class="sd-block">Παραδοτέο / τεκμήριο <input name="deliverable" value="' . esc_attr( $u->deliverable ) . '"></label>';
		echo '<p><button class="sd-button" type="submit">Αποθήκευση ενότητας</button></p></form>';
	}

	private static function skills_text( $csv ) {
		$out = array();
		foreach ( '' === $csv ? array() : explode( ',', $csv ) as $k ) {
			if ( isset( self::SKILLS[ $k ] ) ) {
				$out[] = self::SKILLS[ $k ];
			}
		}
		return implode( ', ', $out );
	}

	private static function unit_view( $class, $u ) {
		$rows = array(
			'Θεματική ενότητα'    => $u->cycle ? self::CYCLES[ $u->cycle ] : '',
			'Υποθεματική'         => $u->cycle ? self::class_subtheme( $class, $u->cycle ) : '',
			'Τίτλος προγράμματος' => $u->title,
			'Εβδομάδες'           => $u->weeks ? (string) $u->weeks : '',
			'Κύκλοι δεξιοτήτων'   => self::skills_text( $u->skills ),
			'Βασικοί στόχοι'      => $u->goals,
			'Βασικές δραστηριότητες' => $u->activities,
			'Υλικό'               => $u->material,
			'Σχέδιο Δράσης'       => $u->action_id ? self::action_label( $u->action_id, $class->year_id ) : 'Δεν συνδέεται',
			'Ώρες Ενεργού Πολίτη' => $u->citizen_hours ? $u->citizen_hours . ' ώρες' : 'Όχι',
			'Επισκέψεις / φορείς' => $u->visits,
			'Παραδοτέο / τεκμήριο' => $u->deliverable,
		);
		echo '<dl class="sd-dl">';
		foreach ( $rows as $k => $v ) {
			echo '<dt>' . esc_html( $k ) . '</dt><dd>' . ( '' !== trim( (string) $v ) ? nl2br( esc_html( $v ) ) : '—' ) . '</dd>';
		}
		echo '</dl>';
	}

	/** Εκτυπώσιμη μορφή, με τη διάταξη του σχολικού Εντύπου 1Α. */
	private static function render_print( $class_id ) {
		$class = SD_Data::get_class( $class_id );
		if ( ! $class || ! self::can_view( $class ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτό το τμήμα.</p>';
			return;
		}
		$form  = self::form_of_class( $class->id );
		$units = self::units( $form );
		$by    = array();
		foreach ( SD_Data::class_teachers( $class->id ) as $t ) {
			$u = get_userdata( $t->user_id );
			if ( $u ) {
				$by[ $t->class_role ][] = $u->display_name;
			}
		}
		$year = SD_Data::active_year();
		echo '<div class="sd-print">';
		echo '<p class="sd-noprint"><button class="sd-button" type="button" onclick="window.print()">Εκτύπωση / Αποθήκευση ως PDF</button> <a class="sd-button" href="' . esc_url( SD_Exports::url( 'f1a', $class->id ) ) . '">Λήψη σε Word</a></p>';
		echo '<p><strong>' . esc_html( SD_Frontend::school_name() ) . '</strong></p>';
		echo '<h2>ΕΝΤΥΠΟ 1Α – Ετήσιος σχεδιασμός Εργαστηρίων Δεξιοτήτων</h2>';
		echo '<p>Σχολικό έτος ' . esc_html( $year ? $year->label : '' ) . ' · Τμήμα <strong>' . esc_html( SD_Data::class_label( $class ) ) . '</strong> · Εβδομαδιαίες ώρες: ' . (int) $class->workshop_hours . '</p>';
		echo '<p>Υπεύθυνος/η: ' . esc_html( isset( $by['ergastiria'] ) ? implode( ', ', $by['ergastiria'] ) : '' ) . ' · ΠΕ70 τμήματος: ' . esc_html( isset( $by['pe70'] ) ? implode( ', ', $by['pe70'] ) : '' ) . '</p>';
		echo '<table class="sd-print-table"><thead><tr><th></th>';
		foreach ( self::UNITS as $label ) {
			echo '<th>' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		$fields = array(
			'Θεματική ενότητα'       => function ( $u ) {
				return $u->cycle ? self::CYCLES[ $u->cycle ] : '';
			},
			'Υποθεματική'            => function ( $u ) use ( $class ) {
				return $u->cycle ? self::class_subtheme( $class, $u->cycle ) : '';
			},
			'Τίτλος προγράμματος'    => function ( $u ) {
				return $u->title;
			},
			'Εβδομάδες'              => function ( $u ) {
				return $u->weeks ? (string) $u->weeks : '';
			},
			'Κύκλοι δεξιοτήτων'      => function ( $u ) {
				return self::skills_text( $u->skills );
			},
			'Βασικοί στόχοι'         => function ( $u ) {
				return $u->goals;
			},
			'Βασικές δραστηριότητες' => function ( $u ) {
				return $u->activities;
			},
			'Υλικό'                  => function ( $u ) {
				return $u->material;
			},
			'Σχέδιο Δράσης σχολείου' => function ( $u ) use ( $class ) {
				return $u->action_id ? self::action_label( $u->action_id, $class->year_id ) : 'Καμία';
			},
			'Ώρες για Δράση Ενεργού Πολίτη' => function ( $u ) {
				return $u->citizen_hours ? 'Ναι: ' . $u->citizen_hours . ' ώρες' : 'Όχι';
			},
			'Επισκέψεις / φορείς'    => function ( $u ) {
				return $u->visits;
			},
			'Παραδοτέο / τεκμήριο'   => function ( $u ) {
				return $u->deliverable;
			},
		);
		foreach ( $fields as $label => $fn ) {
			echo '<tr><th>' . esc_html( $label ) . '</th>';
			foreach ( $units as $u ) {
				echo '<td>' . nl2br( esc_html( (string) $fn( $u ) ) ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		$sub = $form && $form->submitted_at ? mysql2date( 'd/m/Y', $form->submitted_at ) : '…… / …… / 20……';
		echo '<p>Ημερομηνία υποβολής: ' . esc_html( $sub ) . '</p><p>Ο/Η εκπαιδευτικός (ονοματεπώνυμο – υπογραφή):</p></div>';
	}

	/** Μπλοκ για το Word: ίδια διάταξη με την εκτυπώσιμη μορφή (στήλες = ενότητες). */
	public static function doc_blocks( $class ) {
		$form  = self::form_of_class( $class->id );
		$units = self::units( $form );
		$by    = array();
		foreach ( SD_Data::class_teachers( $class->id ) as $t ) {
			$u = get_userdata( $t->user_id );
			if ( $u ) {
				$by[ $t->class_role ][] = $u->display_name;
			}
		}
		$year   = SD_Data::active_year();
		$fields = array(
			'Θεματική ενότητα'              => function ( $u ) {
				return $u->cycle ? self::CYCLES[ $u->cycle ] : '';
			},
			'Υποθεματική'                   => function ( $u ) use ( $class ) {
				return $u->cycle ? self::class_subtheme( $class, $u->cycle ) : '';
			},
			'Τίτλος προγράμματος'           => function ( $u ) {
				return $u->title;
			},
			'Εβδομάδες'                     => function ( $u ) {
				return $u->weeks ? (string) $u->weeks : '';
			},
			'Κύκλοι δεξιοτήτων'             => function ( $u ) {
				return self::skills_text( $u->skills );
			},
			'Βασικοί στόχοι'                => function ( $u ) {
				return $u->goals;
			},
			'Βασικές δραστηριότητες'        => function ( $u ) {
				return $u->activities;
			},
			'Υλικό'                         => function ( $u ) {
				return $u->material;
			},
			'Σχέδιο Δράσης σχολείου'        => function ( $u ) use ( $class ) {
				return $u->action_id ? self::action_label( $u->action_id, $class->year_id ) : 'Καμία';
			},
			'Ώρες για Δράση Ενεργού Πολίτη' => function ( $u ) {
				return $u->citizen_hours ? 'Ναι: ' . $u->citizen_hours . ' ώρες' : 'Όχι';
			},
			'Επισκέψεις / φορείς'           => function ( $u ) {
				return $u->visits;
			},
			'Παραδοτέο / τεκμήριο'          => function ( $u ) {
				return $u->deliverable;
			},
		);
		$rows = array();
		foreach ( $fields as $label => $fn ) {
			$row = array( $label );
			foreach ( $units as $u ) {
				$row[] = (string) $fn( $u );
			}
			$rows[] = $row;
		}
		return array(
			array( 'p', SD_Frontend::school_name(), true ),
			array( 'title', 'ΕΝΤΥΠΟ 1Α – Ετήσιος σχεδιασμός Εργαστηρίων Δεξιοτήτων' ),
			array( 'p', 'Σχολικό έτος ' . ( $year ? $year->label : '' ) . ' · Τμήμα ' . SD_Data::class_label( $class ) . ' · Εβδομαδιαίες ώρες: ' . (int) $class->workshop_hours ),
			array( 'p', 'Υπεύθυνος/η: ' . ( isset( $by['ergastiria'] ) ? implode( ', ', $by['ergastiria'] ) : '' ) . ' · ΠΕ70 τμήματος: ' . ( isset( $by['pe70'] ) ? implode( ', ', $by['pe70'] ) : '' ) ),
			array( 'table', array_merge( array( '' ), array_values( self::UNITS ) ), $rows, array( 0.16, 0.21, 0.21, 0.21, 0.21 ), true ),
			array( 'p', 'Ημερομηνία υποβολής: ' . ( $form && $form->submitted_at ? mysql2date( 'd/m/Y', $form->submitted_at ) : '…… / …… / 20……' ) ),
			array( 'p', 'Ο/Η εκπαιδευτικός (ονοματεπώνυμο – υπογραφή):' ),
		);
	}

	/** Για την αρχική σελίδα: η κατάσταση των εντύπων του χρήστη ή όλων. */
	public static function dashboard_rows( $year, $all ) {
		$classes = $all ? SD_Data::classes( $year->id ) : SD_Data::classes_of_user( get_current_user_id(), $year->id );
		$out     = array();
		foreach ( $classes as $c ) {
			$out[] = array( $c, self::form_of_class( $c->id ) );
		}
		return $out;
	}

	public static function badge( $form ) {
		return self::status_badge( $form );
	}
}
