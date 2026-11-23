<?php
/**
 * Έντυπο 3 – Αίτημα επίσκεψης ή συνεργασίας.
 *
 * Μέρος Α: εκπαιδευτική δράση εκτός σχολείου (απόφαση Συλλόγου τουλάχιστον 5 ημέρες πριν).
 * Μέρος Β: φορέας ή ομιλητής στο σχολείο (σύμφωνη γνώμη Συλλόγου, ενημέρωση ΠΕΘ).
 * Εσωτερική προθεσμία του σχολείου: υποβολή τουλάχιστον 10 εργάσιμες ημέρες πριν.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Requests {

	const STATUS = array(
		'proxeiro'    => 'Πρόχειρο',
		'ypovlithike' => 'Υποβλήθηκε',
		'epistrafike' => 'Επιστράφηκε για διόρθωση',
		'egkrithike'  => 'Εγκρίθηκε (πράξη Συλλόγου)',
	);

	const A_KINDS = array(
		'ekpaideftiki' => 'Εκπαιδευτική επίσκεψη (έως 2 διδακτικές ώρες)',
		'didaktiki'    => 'Διδακτική επίσκεψη (εντός ωραρίου)',
		'imerisia'     => 'Ημερήσια εκπαιδευτική επίσκεψη',
		'programma'    => 'Εγκεκριμένο πρόγραμμα / ΚΕΠΕΑ',
		'allo'         => 'Άλλο',
	);

	const TRANSPORT = array(
		'pezi'      => 'Πεζή',
		'leoforeio' => 'Λεωφορείο',
		'allo'      => 'Άλλο',
	);

	const LINKS = array(
		'ergastiria' => 'Εργαστήρια Δεξιοτήτων',
		'politis'    => 'Δράση Ενεργού Πολίτη',
		'sxedio'     => 'Σχέδιο Δράσης',
		'mathima'    => 'Μάθημα',
	);

	const MIN_WORKDAYS = 10;
	const MIN_DAYS_PRAXI = 5;

	public static function t() {
		return SD_Data::table( 'requests' );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ) );
	}

	public static function of_year( $year_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE year_id = %d ORDER BY event_date, id', $year_id ) );
	}

	public static function pending_count( $year_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . " WHERE year_id = %d AND status = 'ypovlithike'", $year_id ) );
	}

	/** Εργάσιμες (Δευτέρα–Παρασκευή) από την $from (χωρίς αυτήν) ως την $to (χωρίς αυτήν). Αργίες δεν αφαιρούνται. */
	public static function workdays_between( $from, $to ) {
		$a = strtotime( substr( (string) $from, 0, 10 ) );
		$b = strtotime( substr( (string) $to, 0, 10 ) );
		if ( ! $a || ! $b || $b <= $a ) {
			return 0;
		}
		$n = 0;
		for ( $d = strtotime( '+1 day', $a ); $d < $b; $d = strtotime( '+1 day', $d ) ) {
			if ( (int) gmdate( 'N', $d ) <= 5 ) {
				$n++;
			}
		}
		return $n;
	}

	/** Προειδοποιήσεις προθεσμιών (δεν εμποδίζουν την υποβολή). */
	public static function warnings( $r ) {
		$out = array();
		if ( ! $r->event_date ) {
			return $out;
		}
		$ref = $r->submitted_at ? $r->submitted_at : current_time( 'mysql' );
		$wd  = self::workdays_between( $ref, $r->event_date );
		if ( strtotime( $r->event_date ) < strtotime( substr( $ref, 0, 10 ) ) ) {
			$out[] = 'Η ημερομηνία της δράσης έχει περάσει.';
		} elseif ( $wd < self::MIN_WORKDAYS ) {
			$out[] = 'Μένουν ' . $wd . ' εργάσιμες ως τη δράση. Η εσωτερική προθεσμία του σχολείου είναι ' . self::MIN_WORKDAYS . ' εργάσιμες, για να προλάβει ο Σύλλογος.';
		}
		if ( 'A' === $r->part && $r->praxi_date ) {
			$days = (int) floor( ( strtotime( $r->event_date ) - strtotime( $r->praxi_date ) ) / DAY_IN_SECONDS );
			if ( $days < self::MIN_DAYS_PRAXI ) {
				$out[] = 'Η πράξη του Συλλόγου είναι ' . max( 0, $days ) . ' ημέρες πριν από τη δράση· απαιτούνται τουλάχιστον ' . self::MIN_DAYS_PRAXI . ' (άρθρο 13, ΥΑ Φ.12/109328/Δ1/2026).';
			}
		}
		return $out;
	}

	/** Τι λείπει για υποβολή. */
	public static function missing( $r ) {
		$m = array();
		if ( 'A' === $r->part ) {
			foreach ( array( 'destination' => 'προορισμός / φορέας', 'event_date' => 'ημερομηνία', 'purpose' => 'παιδαγωγικός σκοπός' ) as $f => $l ) {
				if ( '' === trim( (string) $r->$f ) ) {
					$m[] = $l;
				}
			}
			if ( '' === $r->a_kind ) {
				$m[] = 'είδος επίσκεψης';
			}
		} else {
			foreach ( array( 'b_person' => 'ονοματεπώνυμο / φορέας', 'event_date' => 'ημερομηνία', 'b_topic' => 'θέμα παρουσίασης', 'b_link' => 'σύνδεση με τη δράση' ) as $f => $l ) {
				if ( '' === trim( (string) $r->$f ) ) {
					$m[] = $l;
				}
			}
		}
		if ( '' === $r->link_kinds ) {
			$m[] = 'σε τι εντάσσεται';
		}
		$links = explode( ',', (string) $r->link_kinds );
		if ( in_array( 'politis', $links, true ) && ! $r->citizen_id ) {
			$m[] = 'ποια Δράση Ενεργού Πολίτη';
		}
		if ( in_array( 'sxedio', $links, true ) && ! $r->plan_id ) {
			$m[] = 'ποιο Σχέδιο Δράσης';
		}
		return $m;
	}

	public static function can_view( $class ) {
		return current_user_can( SD_Roles::CAP_REVIEW ) || SD_Workshops::is_class_teacher( $class->id );
	}

	public static function can_edit( $class, $r ) {
		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return true;
		}
		$status = $r ? $r->status : 'proxeiro';
		return $class && SD_Workshops::is_class_teacher( $class->id ) && in_array( $status, array( 'proxeiro', 'epistrafike' ), true );
	}

	/* =================== Ενέργειες =================== */

	private static function text( $field, $limit = 2000, $multiline = true ) {
		$v = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security
		$v = $multiline ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
		return mb_substr( $v, 0, $limit );
	}

	private static function date( $field ) {
		$v = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : null;
	}

	private static function time( $field ) {
		$v = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security
		return preg_match( '/^\d{2}:\d{2}$/', $v ) ? $v : '';
	}

	private static function key( $field, array $allowed ) {
		$v = isset( $_POST[ $field ] ) ? sanitize_key( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security
		return isset( $allowed[ $v ] ) ? $v : '';
	}

	public static function handle( $do ) {
		global $wpdb;
		if ( ! in_array( $do, array( 'req_save', 'req_submit', 'req_review', 'req_delete' ), true ) ) {
			return null;
		}
		$list = SD_Frontend::url( 'aitimata' );
		$year = SD_Data::active_year();
		$id   = isset( $_POST['req_id'] ) ? absint( $_POST['req_id'] ) : 0;
		$r    = $id ? self::get( $id ) : null;
		if ( $id && ! $r ) {
			return array( new WP_Error( 'sd', 'Το αίτημα δεν βρέθηκε.' ), $list );
		}

		if ( 'req_save' === $do ) {
			$class = SD_Data::get_class( $r ? (int) $r->class_id : ( isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0 ) );
			if ( ! $class || ! $year || ( ! $r && (int) $class->year_id !== (int) $year->id ) || ! self::can_edit( $class, $r ) ) {
				return array( new WP_Error( 'sd', 'Δεν μπορείτε να αλλάξετε αιτήματα αυτού του τμήματος.' ), $list );
			}
			$links = isset( $_POST['link_kinds'] ) && is_array( $_POST['link_kinds'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['link_kinds'] ) ) : array(); // phpcs:ignore WordPress.Security
			$links = array_values( array_intersect( array_keys( self::LINKS ), $links ) );

			$citizen_id = isset( $_POST['citizen_id'] ) ? absint( $_POST['citizen_id'] ) : 0;
			$dep        = $citizen_id ? SD_Citizen::get( $citizen_id ) : null;
			if ( ! $dep || (int) $dep->class_id !== (int) $class->id ) {
				$citizen_id = 0;
			}
			$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
			$plan    = $plan_id ? SD_Plans::get_plan( $plan_id ) : null;
			if ( ! $plan || (int) $plan->year_id !== (int) $class->year_id ) {
				$plan_id = 0;
			}
			$total = isset( $_POST['students_total'] ) ? min( 99, absint( $_POST['students_total'] ) ) : 0;
			$part  = isset( $_POST['students_part'] ) ? min( 99, absint( $_POST['students_part'] ) ) : 0;

			$data = array(
				'part'            => ( isset( $_POST['part'] ) && 'B' === $_POST['part'] ) ? 'B' : 'A',
				'link_kinds'      => implode( ',', $links ),
				'link_ergastiria' => self::text( 'link_ergastiria', 200, false ),
				'citizen_id'      => $citizen_id,
				'plan_id'         => $plan_id,
				'link_mathima'    => self::text( 'link_mathima', 200, false ),
				'a_kind'          => self::key( 'a_kind', self::A_KINDS ),
				'a_kind_other'    => self::text( 'a_kind_other', 200, false ),
				'destination'     => self::text( 'destination', 255, false ),
				'event_date'      => self::date( 'event_date' ),
				'time_from'       => self::time( 'time_from' ),
				'time_to'         => self::time( 'time_to' ),
				'transport'       => self::key( 'transport', self::TRANSPORT ),
				'transport_other' => self::text( 'transport_other', 200, false ),
				'students_total'  => $total,
				'students_part'   => min( $part, $total ? $total : 99 ),
				'escorts'         => self::text( 'escorts', 500 ),
				'purpose'         => self::text( 'purpose' ),
				'b_person'        => self::text( 'b_person', 255, false ),
				'b_role'          => self::text( 'b_role', 255, false ),
				'b_when'          => self::text( 'b_when', 255, false ),
				'b_topic'         => self::text( 'b_topic' ),
				'b_link'          => self::text( 'b_link' ),
				'updated_at'      => current_time( 'mysql' ),
			);
			if ( $r ) {
				$wpdb->update( self::t(), $data, array( 'id' => $r->id ) );
				$rid = (int) $r->id;
			} else {
				$data += array(
					'year_id'     => (int) $year->id,
					'class_id'    => (int) $class->id,
					'owner_id'    => get_current_user_id(),
					'status'      => 'proxeiro',
					'review_note' => '',
					'praxi_no'    => '',
					'created_at'  => current_time( 'mysql' ),
				);
				$wpdb->insert( self::t(), $data );
				$rid = (int) $wpdb->insert_id;
			}
			return array( true, SD_Frontend::url( 'aitimata', array( 'id' => $rid ) ) );
		}

		$class = $r ? SD_Data::get_class( $r->class_id ) : null;
		$back  = $r ? SD_Frontend::url( 'aitimata', array( 'id' => $r->id ) ) : $list;

		if ( 'req_submit' === $do ) {
			if ( ! $r || ! self::can_edit( $class, $r ) ) {
				return array( new WP_Error( 'sd', 'Δεν μπορείτε να υποβάλετε αυτό το αίτημα.' ), $back );
			}
			$missing = self::missing( $r );
			if ( $missing ) {
				return array( new WP_Error( 'sd', 'Συμπληρώστε πρώτα: ' . implode( ', ', $missing ) . '.' ), $back );
			}
			$wpdb->update(
				self::t(),
				array(
					'status'       => 'ypovlithike',
					'submitted_at' => current_time( 'mysql' ),
				),
				array( 'id' => $r->id )
			);
			SD_Notify::submitted( 'Αίτημα επίσκεψης ή φορέα (Έντυπο 3) · τμήμα ' . SD_Data::class_label( $class ), $class->id, $back, true );
			return array( true, $back );
		}

		if ( 'req_review' === $do ) {
			if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
				return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση καταχωρίζει την απόφαση του Συλλόγου.' ), $back );
			}
			if ( ! $r || ! in_array( $r->status, array( 'ypovlithike', 'egkrithike' ), true ) ) {
				return array( new WP_Error( 'sd', 'Το αίτημα δεν έχει υποβληθεί.' ), $back );
			}
			$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
			$note     = self::text( 'review_note', 1000 );
			if ( 'return' === $decision ) {
				if ( '' === trim( $note ) ) {
					return array( new WP_Error( 'sd', 'Γράψτε τι χρειάζεται διόρθωση.' ), $back );
				}
				$wpdb->update( self::t(), array( 'status' => 'epistrafike', 'review_note' => $note, 'reviewed_by' => get_current_user_id(), 'reviewed_at' => current_time( 'mysql' ) ), array( 'id' => $r->id ) );
				SD_Notify::decided( 'Αίτημα επίσκεψης ή φορέα (Έντυπο 3)', $r->class_id, $r->owner_id, true, $note, $back );
				return array( true, $back );
			}
			$praxi_no   = self::text( 'praxi_no', 30, false );
			$praxi_date = self::date( 'praxi_date' );
			if ( '' === $praxi_no || ! $praxi_date ) {
				return array( new WP_Error( 'sd', 'Συμπληρώστε αριθμό και ημερομηνία της πράξης του Συλλόγου.' ), $back );
			}
			$pie = empty( $_POST['pie_informed'] ) ? 0 : 1;
			if ( 'B' === $r->part && ! $pie ) {
				return array( new WP_Error( 'sd', 'Για το Μέρος Β σημειώστε ότι ενημερώθηκε ο/η Προϊστάμενος/η Εκπαιδευτικών Θεμάτων.' ), $back );
			}
			$wpdb->update(
				self::t(),
				array(
					'status'       => 'egkrithike',
					'praxi_no'     => $praxi_no,
					'praxi_date'   => $praxi_date,
					'pie_informed' => $pie,
					'review_note'  => $note,
					'reviewed_by'  => get_current_user_id(),
					'reviewed_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $r->id )
			);
			SD_Notify::decided( 'Αίτημα επίσκεψης ή φορέα (Έντυπο 3): εγκρίθηκε με την πράξη ' . $praxi_no . '/' . mysql2date( 'd-m-Y', $praxi_date ), $r->class_id, $r->owner_id, false, $note, $back );
			return array( true, $back );
		}

		// req_delete
		if ( ! $r || ! self::can_edit( $class, $r ) ) {
			return array( new WP_Error( 'sd', 'Δεν μπορείτε να διαγράψετε αυτό το αίτημα.' ), $back );
		}
		$wpdb->delete( self::t(), array( 'id' => $r->id ) );
		return array( true, $list );
	}

	/* =================== Εμφάνιση =================== */

	public static function badge( $status ) {
		$cls = array(
			'ypovlithike' => 'sd-badge-info',
			'epistrafike' => 'sd-badge-warn',
			'egkrithike'  => 'sd-badge-ok',
		);
		return '<span class="sd-badge ' . esc_attr( $cls[ $status ] ?? '' ) . '">' . esc_html( self::STATUS[ $status ] ?? $status ) . '</span>';
	}

	public static function render() {
		if ( isset( $_GET['print'] ) ) {
			self::render_print( absint( $_GET['print'] ) );
		} elseif ( isset( $_GET['id'] ) || isset( $_GET['nea'] ) ) {
			self::render_form( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		} else {
			self::render_list();
		}
	}

	private static function render_list() {
		$year = SD_Data::active_year();
		echo '<h2>Επισκέψεις και φορείς · Έντυπο 3</h2>';
		echo '<p class="sd-help">Υποβάλλεται στη Διεύθυνση τουλάχιστον ' . (int) self::MIN_WORKDAYS . ' εργάσιμες πριν, ώστε να ληφθεί εγκαίρως η απόφαση του Συλλόγου Διδασκόντων (για επίσκεψη: τουλάχιστον ' . (int) self::MIN_DAYS_PRAXI . ' ημέρες πριν).</p>';
		if ( ! $year ) {
			return;
		}
		$review  = current_user_can( SD_Roles::CAP_REVIEW );
		$mine    = array();
		foreach ( SD_Data::classes_of_user( get_current_user_id(), $year->id ) as $c ) {
			$mine[ (int) $c->id ] = true;
		}
		$can_new = current_user_can( SD_Roles::CAP_MANAGE ) || $mine;
		if ( $can_new ) {
			echo '<p><a class="sd-button" href="' . esc_url( SD_Frontend::url( 'aitimata', array( 'nea' => 1 ) ) ) . '">Νέο αίτημα</a></p>';
		}
		$rows = array();
		foreach ( self::of_year( $year->id ) as $r ) {
			if ( $review || isset( $mine[ (int) $r->class_id ] ) ) {
				$rows[] = $r;
			}
		}
		if ( ! $rows ) {
			echo '<p>Δεν υπάρχουν αιτήματα.</p>';
			return;
		}
		echo '<table class="sd-table"><thead><tr><th>Ημερομηνία</th><th>Τμήμα</th><th>Μέρος</th><th>Προορισμός / φορέας</th><th>Κατάσταση</th><th>Προθεσμίες</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$c    = SD_Data::get_class( $r->class_id );
			$what = 'A' === $r->part ? $r->destination : $r->b_person;
			$warn = self::warnings( $r );
			echo '<tr><td>' . esc_html( $r->event_date ? mysql2date( 'd/m/Y', $r->event_date ) : '—' ) . '</td><td>' . esc_html( $c ? SD_Data::class_label( $c ) : '' ) . '</td><td>' . ( 'A' === $r->part ? 'Α · επίσκεψη' : 'Β · φορέας στο σχολείο' ) . '</td>';
			echo '<td><a href="' . esc_url( SD_Frontend::url( 'aitimata', array( 'id' => $r->id ) ) ) . '">' . esc_html( $what ? $what : '(χωρίς τίτλο)' ) . '</a></td><td>' . self::badge( $r->status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td>' . ( $warn && 'egkrithike' !== $r->status ? '<span class="sd-badge sd-badge-warn">' . esc_html( count( $warn ) . ' προειδοποίηση' . ( count( $warn ) > 1 ? 'εις' : '' ) ) . '</span>' : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_form( $id ) {
		$year = SD_Data::active_year();
		$r    = $id ? self::get( $id ) : null;
		if ( $id && ! $r ) {
			echo '<p class="sd-notice sd-error">Το αίτημα δεν βρέθηκε.</p>';
			return;
		}
		$class = $r ? SD_Data::get_class( $r->class_id ) : null;
		if ( $r && ( ! $class || ! self::can_view( $class ) ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτό το αίτημα.</p>';
			return;
		}
		$classes = $r ? array( $class ) : ( current_user_can( SD_Roles::CAP_MANAGE ) ? SD_Data::classes( $year ? $year->id : 0 ) : SD_Data::classes_of_user( get_current_user_id(), $year ? $year->id : 0 ) );
		if ( ! $r && ! $classes ) {
			echo '<p class="sd-notice sd-error">Δεν σας έχει αντιστοιχιστεί τμήμα.</p>';
			return;
		}
		$edit = self::can_edit( $r ? $class : $classes[0], $r );

		echo '<p><a href="' . esc_url( SD_Frontend::url( 'aitimata' ) ) . '">← Όλα τα αιτήματα</a>';
		if ( $r ) {
			echo ' · <a href="' . esc_url( SD_Frontend::url( 'aitimata', array( 'print' => $r->id ) ) ) . '">Εκτυπώσιμη μορφή</a> · ' . SD_Exports::links( 'f3', $r->id, 'Λήψη σε Word' ) . '';
		}
		echo '</p><h2>Έντυπο 3 · ' . ( $r ? esc_html( 'A' === $r->part ? $r->destination : $r->b_person ) : 'Νέο αίτημα' ) . '</h2>';
		if ( $r ) {
			echo '<p>' . self::badge( $r->status ) . ' · Τμήμα ' . esc_html( SD_Data::class_label( $class ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $r->review_note && in_array( $r->status, array( 'epistrafike', 'egkrithike' ), true ) ) {
				echo '<p class="sd-notice ' . ( 'epistrafike' === $r->status ? 'sd-error' : 'sd-ok' ) . '"><strong>Σχόλια Διεύθυνσης:</strong> ' . esc_html( $r->review_note ) . '</p>';
			}
			if ( 'egkrithike' === $r->status ) {
				echo '<p class="sd-notice sd-ok">Πράξη Συλλόγου Διδασκόντων αρ. <strong>' . esc_html( $r->praxi_no ) . '</strong> / ' . esc_html( mysql2date( 'd/m/Y', $r->praxi_date ) ) . ( 'B' === $r->part && $r->pie_informed ? ' · Ενημερώθηκε ο/η ΠΕΘ' : '' ) . '</p>';
			}
			foreach ( self::warnings( $r ) as $w ) {
				echo '<p class="sd-notice sd-error">' . esc_html( $w ) . '</p>';
			}
		}

		if ( $edit ) {
			self::edit_form( $r, $classes, $year );
		} else {
			self::view( $r, $class );
		}

		if ( $r && current_user_can( SD_Roles::CAP_MANAGE ) && in_array( $r->status, array( 'ypovlithike', 'egkrithike' ), true ) ) {
			echo '<section class="sd-card"><h3>Για τη Διεύθυνση: απόφαση Συλλόγου Διδασκόντων</h3>';
			SD_Frontend::form_open( 'req_review' );
			echo '<input type="hidden" name="req_id" value="' . (int) $r->id . '"><div class="sd-row">';
			echo '<label>Πράξη αρ. <input name="praxi_no" size="6" value="' . esc_attr( $r->praxi_no ) . '"></label>';
			echo '<label>Ημερομηνία πράξης <input type="date" name="praxi_date" value="' . esc_attr( (string) $r->praxi_date ) . '"></label>';
			if ( 'B' === $r->part ) {
				echo '<label class="sd-inline"><input type="checkbox" name="pie_informed" value="1"' . checked( (int) $r->pie_informed, 1, false ) . '> Ενημερώθηκε ο/η Προϊστάμενος/η Εκπαιδευτικών Θεμάτων</label>';
			}
			echo '</div><label class="sd-block">Σχόλια (υποχρεωτικά για επιστροφή)<textarea name="review_note" rows="2">' . esc_textarea( $r->review_note ) . '</textarea></label>';
			echo '<p><button class="sd-button" type="submit" name="decision" value="ok">Καταχώριση πράξης – έγκριση</button> <button class="sd-button sd-danger" type="submit" name="decision" value="return">Επιστροφή για διόρθωση</button></p></form></section>';
		}
	}

	private static function link_options( $class, $year ) {
		$deps  = $class ? SD_Citizen::of_class( $class->id ) : array();
		$plans = $year ? SD_Plans::plans( $year->id ) : array();
		return array( $deps, $plans );
	}

	private static function edit_form( $r, $classes, $year ) {
		$v = function ( $f, $d = '' ) use ( $r ) {
			return $r ? (string) $r->$f : $d;
		};
		list( $deps, $plans ) = self::link_options( $r ? SD_Data::get_class( $r->class_id ) : $classes[0], $year );
		$links = '' === $v( 'link_kinds' ) ? array() : explode( ',', $v( 'link_kinds' ) );
		$part  = $v( 'part', 'A' );
		if ( $r ) {
			SD_AiField::mark( 'f3', $r->id );
		}

		echo '<form method="post" class="sd-form" data-sd-req>';
		wp_nonce_field( SD_Frontend::NONCE );
		echo '<input type="hidden" name="sd_do" value="req_save">';
		if ( $r ) {
			echo '<input type="hidden" name="req_id" value="' . (int) $r->id . '">';
		}
		echo '<section class="sd-card">';
		if ( ! $r ) {
			echo '<label class="sd-block">Τάξη / Τμήμα <select name="class_id">';
			foreach ( $classes as $c ) {
				echo '<option value="' . (int) $c->id . '">' . esc_html( SD_Data::class_label( $c ) ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<fieldset><legend>Τι αφορά το αίτημα</legend><label class="sd-inline"><input type="radio" name="part" value="A"' . checked( $part, 'A', false ) . '> Μέρος Α · Εκπαιδευτική δράση εκτός σχολείου</label><br><label class="sd-inline"><input type="radio" name="part" value="B"' . checked( $part, 'B', false ) . '> Μέρος Β · Φορέας ή ομιλητής στο σχολείο</label></fieldset>';
		echo '<fieldset><legend>Εντάσσεται σε</legend>';
		echo '<label class="sd-inline"><input type="checkbox" name="link_kinds[]" value="ergastiria"' . checked( in_array( 'ergastiria', $links, true ), true, false ) . '> Εργαστήρια Δεξιοτήτων:</label> <input name="link_ergastiria" size="34" value="' . esc_attr( $v( 'link_ergastiria' ) ) . '" placeholder="ενότητα / πρόγραμμα"><br>';
		echo '<label class="sd-inline"><input type="checkbox" name="link_kinds[]" value="politis"' . checked( in_array( 'politis', $links, true ), true, false ) . '> Δράση Ενεργού Πολίτη:</label> <select name="citizen_id"><option value="0">—</option>';
		foreach ( $deps as $d ) {
			echo '<option value="' . (int) $d->id . '"' . selected( (int) $v( 'citizen_id', 0 ), (int) $d->id, false ) . '>' . esc_html( $d->title ) . '</option>';
		}
		echo '</select><br><label class="sd-inline"><input type="checkbox" name="link_kinds[]" value="sxedio"' . checked( in_array( 'sxedio', $links, true ), true, false ) . '> Σχέδιο Δράσης:</label> <select name="plan_id"><option value="0">—</option>';
		foreach ( $plans as $p ) {
			echo '<option value="' . (int) $p->id . '"' . selected( (int) $v( 'plan_id', 0 ), (int) $p->id, false ) . '>' . esc_html( trim( $p->code . ' ' . $p->title ) ) . '</option>';
		}
		echo '</select><br><label class="sd-inline"><input type="checkbox" name="link_kinds[]" value="mathima"' . checked( in_array( 'mathima', $links, true ), true, false ) . '> Μάθημα:</label> <input name="link_mathima" size="30" value="' . esc_attr( $v( 'link_mathima' ) ) . '"></fieldset>';
		echo '<label class="sd-block">Ημερομηνία <input type="date" name="event_date" value="' . esc_attr( $v( 'event_date' ) ) . '"></label></section>';

		// Μέρος Α
		echo '<section class="sd-card" data-part="A"><h3>Μέρος Α · Εκπαιδευτική δράση εκτός σχολείου</h3><p class="sd-help">Απαιτείται απόφαση του Συλλόγου Διδασκόντων τουλάχιστον 5 ημέρες πριν και ορισμός υπεύθυνου/ης (άρθρα 13 και 14 της ΥΑ Φ.12/109328/Δ1/19-08-2026, Β΄ 5344).</p>';
		echo '<fieldset><legend>Είδος</legend>';
		foreach ( self::A_KINDS as $k => $l ) {
			echo '<label class="sd-inline"><input type="radio" name="a_kind" value="' . esc_attr( $k ) . '"' . checked( $v( 'a_kind' ), $k, false ) . '> ' . esc_html( $l ) . '</label><br>';
		}
		echo '<input name="a_kind_other" size="40" value="' . esc_attr( $v( 'a_kind_other' ) ) . '" placeholder="Άλλο: ……"></fieldset>';
		echo '<label class="sd-block">Προορισμός / φορέας <input name="destination" list="sd-topoi" value="' . esc_attr( $v( 'destination' ) ) . '"></label>';
		echo '<datalist id="sd-topoi">';
		foreach ( SD_Map::official()['places'] ?? array() as $pl ) {
			echo '<option value="' . esc_attr( $pl['name'] ) . '">';
		}
		echo '<option value="ΚΕΠΕΑ Στυλίδας–Υπάτης"></datalist>';
		echo '<div class="sd-row"><label>Αναχώρηση <input type="time" name="time_from" value="' . esc_attr( $v( 'time_from' ) ) . '"></label><label>Επιστροφή <input type="time" name="time_to" value="' . esc_attr( $v( 'time_to' ) ) . '"></label>';
		echo '<label>Μετακίνηση <select name="transport"><option value="">—</option>';
		foreach ( self::TRANSPORT as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $v( 'transport' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label><label>Άλλο μέσο <input name="transport_other" size="14" value="' . esc_attr( $v( 'transport_other' ) ) . '"></label></div>';
		echo '<div class="sd-row"><label>Μαθητές τμήματος <input type="number" min="0" max="99" name="students_total" value="' . esc_attr( $v( 'students_total', '' ) ) . '"></label><label>Συμμετέχοντες <input type="number" min="0" max="99" name="students_part" value="' . esc_attr( $v( 'students_part', '' ) ) . '"></label></div>';
		echo '<label class="sd-block">Συνοδοί εκπαιδευτικοί <input name="escorts" value="' . esc_attr( $v( 'escorts' ) ) . '"></label>';
		echo '<label class="sd-block">Παιδαγωγικός σκοπός και σύνδεση με τη δράση' . ( $r ? '' : ' <span class="sd-meta">(το ✨ για πρόταση AI εμφανίζεται μετά την πρώτη αποθήκευση)</span>' ) . ' <textarea name="purpose" rows="4">' . esc_textarea( $v( 'purpose' ) ) . '</textarea></label>';
		echo '</section>';

		// Μέρος Β
		echo '<section class="sd-card" data-part="B"><h3>Μέρος Β · Φορέας ή ομιλητής στο σχολείο</h3><p class="sd-help">Η παρουσία τρίτων κατά το διδακτικό ωράριο προϋποθέτει σύμφωνη γνώμη του Συλλόγου Διδασκόντων και ενημέρωση του/της Προϊσταμένου/ης Εκπαιδευτικών Θεμάτων (παρ. 12 άρθρου 16 π.δ. 79/2017). Ο/Η εκπαιδευτικός παραμένει στην τάξη και δεν δίνονται στοιχεία μαθητών.</p>';
		echo '<label class="sd-block">Ονοματεπώνυμο / φορέας <input name="b_person" value="' . esc_attr( $v( 'b_person' ) ) . '"></label>';
		echo '<label class="sd-block">Ιδιότητα <input name="b_role" value="' . esc_attr( $v( 'b_role' ) ) . '"></label>';
		echo '<label class="sd-block">Ώρα και διάρκεια <input name="b_when" value="' . esc_attr( $v( 'b_when' ) ) . '" placeholder="π.χ. 10:00, 2 διδακτικές ώρες"></label>';
		echo '<label class="sd-block">Θέμα παρουσίασης ή εργαστηρίου <textarea name="b_topic" rows="2">' . esc_textarea( $v( 'b_topic' ) ) . '</textarea></label>';
		echo '<label class="sd-block">Σύνδεση με τη δράση <textarea name="b_link" rows="2">' . esc_textarea( $v( 'b_link' ) ) . '</textarea></label></section>';

		echo '<p><button class="sd-button" type="submit">Αποθήκευση</button></p></form>';

		if ( $r ) {
			$missing = self::missing( $r );
			if ( $missing ) {
				echo '<p class="sd-help">Για υποβολή λείπουν: ' . esc_html( implode( ', ', $missing ) ) . '.</p>';
			}
			echo '<div class="sd-row">';
			SD_Frontend::form_open( 'req_submit' );
			echo '<input type="hidden" name="req_id" value="' . (int) $r->id . '"><button class="sd-button" type="submit"' . ( $missing ? ' disabled' : '' ) . '>Υποβολή στη Διεύθυνση</button></form>';
			SD_Frontend::form_open( 'req_delete' );
			echo '<input type="hidden" name="req_id" value="' . (int) $r->id . '"><button class="sd-button sd-danger" type="submit" onclick="return confirm(\'Διαγραφή του αιτήματος;\');">Διαγραφή</button></form></div>';
		}
	}

	/** Ζεύγη ετικέτα–τιμή του εντύπου (για προβολή, εκτύπωση και Word). */
	public static function rows( $r, $class ) {
		$owner = get_userdata( $r->owner_id );
		$links = array();
		foreach ( '' === $r->link_kinds ? array() : explode( ',', $r->link_kinds ) as $k ) {
			$extra = '';
			if ( 'ergastiria' === $k ) {
				$extra = $r->link_ergastiria;
			} elseif ( 'politis' === $k && $r->citizen_id ) {
				$d     = SD_Citizen::get( $r->citizen_id );
				$extra = $d ? $d->title : '';
			} elseif ( 'sxedio' === $k && $r->plan_id ) {
				$p     = SD_Plans::get_plan( $r->plan_id );
				$extra = $p ? trim( $p->code . ' ' . $p->title ) : '';
			} elseif ( 'mathima' === $k ) {
				$extra = $r->link_mathima;
			}
			$links[] = self::LINKS[ $k ] . ( $extra ? ': ' . $extra : '' );
		}
		$head = array(
			array( 'Τάξη / Τμήμα', $class ? SD_Data::class_label( $class ) : '' ),
			array( 'Υπεύθυνος/η εκπαιδευτικός', $owner ? $owner->display_name : '' ),
			array( 'Εντάσσεται σε', implode( "\n", $links ) ),
		);
		$date = $r->event_date ? mysql2date( 'd/m/Y', $r->event_date ) : '';
		if ( 'A' === $r->part ) {
			$kind = $r->a_kind ? self::A_KINDS[ $r->a_kind ] . ( 'allo' === $r->a_kind && $r->a_kind_other ? ': ' . $r->a_kind_other : '' ) : '';
			$tr   = $r->transport ? self::TRANSPORT[ $r->transport ] . ( 'allo' === $r->transport && $r->transport_other ? ': ' . $r->transport_other : '' ) : '';
			$body = array(
				array( 'Είδος', $kind ),
				array( 'Προορισμός / φορέας', $r->destination ),
				array( 'Ημερομηνία και ώρες', trim( $date . ( $r->time_from ? ' · Αναχώρηση: ' . $r->time_from : '' ) . ( $r->time_to ? ' · Επιστροφή: ' . $r->time_to : '' ) ) ),
				array( 'Μετακίνηση', $tr ),
				array( 'Μαθητές', 'Σύνολο τμήματος: ' . ( $r->students_total ? $r->students_total : '……' ) . ' · Συμμετέχοντες: ' . ( $r->students_part ? $r->students_part : '……' ) ),
				array( 'Συνοδοί εκπαιδευτικοί', $r->escorts ),
				array( 'Παιδαγωγικός σκοπός και σύνδεση με τη δράση', $r->purpose ),
			);
		} else {
			$body = array(
				array( 'Ονοματεπώνυμο / φορέας', $r->b_person ),
				array( 'Ιδιότητα', $r->b_role ),
				array( 'Ημερομηνία, ώρα, διάρκεια', trim( $date . ( $r->b_when ? ' · ' . $r->b_when : '' ) ) ),
				array( 'Θέμα παρουσίασης ή εργαστηρίου', $r->b_topic ),
				array( 'Σύνδεση με τη δράση', $r->b_link ),
			);
		}
		return array( $head, $body );
	}

	private static function view( $r, $class ) {
		list( $head, $body ) = self::rows( $r, $class );
		echo '<section class="sd-card"><dl class="sd-dl">';
		foreach ( array_merge( $head, $body ) as $kv ) {
			echo '<dt>' . esc_html( $kv[0] ) . '</dt><dd>' . ( '' !== trim( (string) $kv[1] ) ? nl2br( esc_html( $kv[1] ) ) : '—' ) . '</dd>';
		}
		echo '</dl></section>';
	}

	/** Μπλοκ για το Word (ίδια διάταξη με το σχολικό έντυπο). */
	public static function doc_blocks( $r ) {
		$class               = SD_Data::get_class( $r->class_id );
		list( $head, $body ) = self::rows( $r, $class );
		$sub                 = $r->submitted_at ? mysql2date( 'd/m/Y', $r->submitted_at ) : '…… / …… / 20……';
		$b                   = array(
			array( 'p', SD_Frontend::school_name(), true ),
			array( 'title', 'ΕΝΤΥΠΟ 3 – Αίτημα επίσκεψης ή συνεργασίας' ),
			array( 'p', 'Εκπαιδευτική δράση εκτός σχολείου ή παρουσία φορέα/ομιλητή στο σχολείο. Υποβάλλεται στον Διευθυντή τουλάχιστον 10 εργάσιμες ημέρες πριν (εσωτερική προθεσμία του σχολείου).' ),
			array( 'kv', $head ),
			array( 'h', 'A' === $r->part ? 'Μέρος Α. Εκπαιδευτική δράση εκτός σχολείου' : 'Μέρος Β. Φορέας ή ομιλητής στο σχολείο' ),
			array( 'kv', $body ),
			array( 'p', 'Ημερομηνία υποβολής: ' . $sub ),
			array( 'p', 'Ο/Η εκπαιδευτικός (ονοματεπώνυμο – υπογραφή):' ),
			array( 'h', 'Για τη Διεύθυνση' ),
			array( 'p', 'Πράξη Συλλόγου Διδασκόντων αρ. ' . ( $r->praxi_no ? $r->praxi_no : '………' ) . ' / ' . ( $r->praxi_date ? mysql2date( 'd/m/Y', $r->praxi_date ) : '…… - …… - 20……' ) . ( 'B' === $r->part ? ( $r->pie_informed ? ' · Ενημερώθηκε ο/η Προϊστάμενος/η Εκπαιδευτικών Θεμάτων' : ' · Ενημέρωση Προϊσταμένου/ης Εκπαιδευτικών Θεμάτων: ……' ) : '' ) ),
		);
		return $b;
	}

	private static function render_print( $id ) {
		$r     = self::get( $id );
		$class = $r ? SD_Data::get_class( $r->class_id ) : null;
		if ( ! $r || ! $class || ! self::can_view( $class ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτό το αίτημα.</p>';
			return;
		}
		echo '<div class="sd-print"><p class="sd-noprint"><button class="sd-button" type="button" onclick="window.print()">Εκτύπωση / Αποθήκευση ως PDF</button> <a class="sd-button" href="' . esc_url( SD_Exports::url( 'f3', $r->id ) ) . '">Λήψη σε Word</a></p>';
		SD_Exports::print_blocks( self::doc_blocks( $r ) );
		echo '</div>';
	}

	/** Γραμμές για το Excel. */
	public static function sheet( $year_id ) {
		$rows = array( array( 'Ημερομηνία', 'Τμήμα', 'Μέρος', 'Είδος', 'Προορισμός / φορέας', 'Μετακίνηση', 'Μαθητές', 'Συμμετέχοντες', 'Κατάσταση', 'Πράξη Συλλόγου', 'Ημ/νία πράξης' ) );
		foreach ( self::of_year( $year_id ) as $r ) {
			$c      = SD_Data::get_class( $r->class_id );
			$rows[] = array(
				$r->event_date ? mysql2date( 'd/m/Y', $r->event_date ) : '',
				$c ? SD_Data::class_label( $c ) : '',
				'A' === $r->part ? 'Α · εκτός σχολείου' : 'Β · φορέας στο σχολείο',
				'A' === $r->part ? ( self::A_KINDS[ $r->a_kind ] ?? '' ) : $r->b_role,
				'A' === $r->part ? $r->destination : $r->b_person,
				'A' === $r->part ? ( self::TRANSPORT[ $r->transport ] ?? '' ) : '',
				(int) $r->students_total,
				(int) $r->students_part,
				self::STATUS[ $r->status ] ?? $r->status,
				$r->praxi_no,
				$r->praxi_date ? mysql2date( 'd/m/Y', $r->praxi_date ) : '',
			);
		}
		return $rows;
	}
}
