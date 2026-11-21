<?php
/**
 * Υποδείγματα των Εργαστηρίων Δεξιοτήτων (Πλατφόρμα 21+), δίπλα στο επίσημο 1Α:
 *
 * - Προγραμματισμός Εκπαιδευτικών: βγαίνει αυτόματα από το 1Α.
 * - Σχέδιο Δράσης του Τμήματος: ανά θεματικό κύκλο. Προσυμπληρώνεται από 1Α και
 *   Έντυπο 4· ο/η εκπαιδευτικός το συμπληρώνει όποτε θέλει (δεν κλειδώνει).
 * - Σχέδιο Δράσης της Σχολικής Μονάδας: φόρμα της Διεύθυνσης με αυτόματα στοιχεία.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_ErgPlan {

	/** Κείμενο προσανατολισμού του υποδείγματος (μπορεί να αλλάξει ανά τμήμα). */
	const ORIENTATION = "Βασική αρχή του ετήσιου Σχεδίου Δράσης των Εργαστηρίων Δεξιοτήτων είναι να συνδυάζουν το γνωστικό πεδίο των Προγραμμάτων Σπουδών με την ανάπτυξη βασικών ικανοτήτων των μαθητριών και των μαθητών με σκοπό τη διάπλασή τους σε ελεύθερους και υπεύθυνους πολίτες. Σκοπός μας είναι η ενίσχυση της καλλιέργειας ήπιων δεξιοτήτων, δεξιοτήτων ζωής και δεξιοτήτων τεχνολογίας και επιστήμης στις μαθήτριες και στους μαθητές της τάξης μας.";

	const CLASS_FIELDS = array(
		'iep_title'   => 'Τίτλος/τίτλοι προγραμμάτων (από την πλατφόρμα του ΙΕΠ)',
		'our_title'   => 'Ο δικός μας τίτλος',
		'goals'       => 'Στόχοι Σχεδίου Δράσης (δεξιότητες, στόχοι θεματικού κύκλου, σύνδεση με τα μαθήματα)',
		'sequence'    => 'Ακολουθία εργαστηρίων (μία γραμμή ανά εργαστήριο, έως 7)',
		'adaptations' => 'Προσαρμογές για τη συμμετοχή και την ένταξη όλων των μαθητών/τριών',
		'partners'    => 'Φορείς και άλλες συνεργασίες που θα εμπλουτίσουν το πρόγραμμά μας',
		'products'    => 'Τελικά προϊόντα που παρήχθησαν από τους/τις μαθητές/τριες',
		'material'    => 'Εκπαιδευτικό υλικό και εργαλεία που παρήχθησαν από τους/τις μαθητές/τριες',
		'assessment'  => 'Αξιολόγηση – Αναστοχασμός πάνω στην υλοποίηση',
		'diffusion'   => 'Εκδηλώσεις διάχυσης και συνολική αποτίμηση (έως 100 λέξεις)',
	);

	const SCHOOL_TEXT = array(
		'orientation'  => 'Βασικός προσανατολισμός του ετήσιου Σχεδίου Δράσης (πλεονεκτήματα – μειονεκτήματα)',
		'vision'       => 'Το όραμά μας',
		'goals'        => 'Στόχοι της σχολικής μονάδας σε σχέση με τις τοπικές και ενδοσχολικές ανάγκες',
		'climate'      => 'Αναμενόμενο όφελος ως προς το σχολικό κλίμα',
		'specific'     => 'Ειδικότερα οφέλη',
		'community'    => 'Αναμενόμενος αντίκτυπος για την ανάπτυξη της σχολικής κοινότητας',
		'local'        => 'Αντίκτυπος στην τοπική κοινότητα',
		'adaptations'  => 'Προσαρμογές για τη συμμετοχή και την ένταξη όλων των μαθητών',
		'partners'     => 'Φορείς και άλλες συνεργασίες που θα εμπλουτίσουν το σχέδιο δράσης',
		'products'     => 'Τελικά προϊόντα (ενδεικτικά) των εργαστηρίων που υλοποιήθηκαν',
		'material'     => 'Εκπαιδευτικό υλικό και εργαλεία που χρησιμοποιήθηκαν εκτός της Πλατφόρμας του ΙΕΠ',
		'end_climate'  => 'Οφέλη συνολικά: ως προς το σχολικό κλίμα γενικά',
		'end_school'   => 'Οφέλη συνολικά: ως προς την ανάπτυξη της σχολικής κοινότητας (μαθητές, εκπαιδευτικοί, γονείς)',
		'end_local'    => 'Οφέλη συνολικά: ως προς την τοπική κοινότητα',
		'difficulties' => 'Δυσκολίες και εμπόδια (ξεπεράστηκαν / ήταν ανυπέρβλητα)',
		'proposals'    => 'Προτάσεις (λίστα, έως 150 λέξεις)',
	);

	public static function t() {
		return SD_Data::table( 'ergplan' );
	}

	/* =================== Δεδομένα =================== */

	public static function class_plan( $class_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE class_id = %d', $class_id ) );
		if ( $row ) {
			$v             = json_decode( (string) $row->cycles, true );
			$row->cycle_arr = is_array( $v ) ? $v : array();
		}
		return $row;
	}

	/** Η ενότητα του 1Α για κάθε θεματικό κύκλο: [κύκλος => ενότητα]. */
	private static function unit_by_cycle( $class ) {
		$out = array();
		foreach ( SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) ) as $n => $u ) {
			if ( $u->cycle ) {
				$out[ (int) $u->cycle ] = $u;
			}
		}
		return $out;
	}

	/** Τιμές για κάθε κύκλο: αποθηκευμένες, αλλιώς προσυμπλήρωση από 1Α και Έντυπο 4. */
	public static function cycle_values( $class ) {
		$plan  = self::class_plan( $class->id );
		$saved = $plan ? $plan->cycle_arr : array();
		$units = self::unit_by_cycle( $class );
		$f4    = SD_F4::form_of_class( $class->id );
		$out   = array();
		foreach ( array_keys( SD_Workshops::CYCLES ) as $c ) {
			$u   = $units[ $c ] ?? null;
			$f   = $f4 ? ( $f4->cycles_arr[ $c ] ?? array() ) : array();
			$pre = array(
				'iep_title'   => '',
				'our_title'   => $u ? $u->title : '',
				'goals'       => $u ? $u->goals : '',
				'sequence'    => $u ? $u->activities : '',
				'adaptations' => '',
				'partners'    => $u ? $u->visits : '',
				'products'    => ! empty( $f['deliverables'] ) ? $f['deliverables'] : ( $u ? $u->deliverable : '' ),
				'material'    => $u ? $u->material : '',
				'assessment'  => '',
				'diffusion'   => '',
			);
			$val = $saved[ $c ] ?? array();
			foreach ( $pre as $k => $v ) {
				if ( ! isset( $val[ $k ] ) || '' === trim( (string) $val[ $k ] ) ) {
					$val[ $k ] = (string) $v;
				}
			}
			$out[ $c ] = $val;
		}
		return $out;
	}

	private static function opt( $year_id ) {
		return 'sd_school_ergplan_' . (int) $year_id;
	}

	public static function school_plan( $year_id ) {
		$v = get_option( self::opt( $year_id ), array() );
		return is_array( $v ) ? $v : array();
	}

	/** Αυτόματα στοιχεία της σχολικής μονάδας. */
	public static function school_stats( $year ) {
		$classes  = SD_Data::classes( $year->id );
		$teachers = array();
		$used     = array();
		$titles   = array();
		foreach ( $classes as $c ) {
			foreach ( SD_Data::class_teachers( $c->id ) as $t ) {
				if ( 'ergastiria' === $t->class_role ) {
					$teachers[ (int) $t->user_id ] = true;
				}
			}
			foreach ( self::unit_by_cycle( $c ) as $cy => $u ) {
				$id = SD_Workshops::GRADE_SUBTHEMES[ (int) $c->grade ][ $cy ] ?? '';
				if ( $id ) {
					$used[ $id ][] = SD_Data::class_label( $c );
				}
				$titles[ $cy ][] = SD_Data::class_label( $c ) . ': ' . ( $u->title ? $u->title : '(χωρίς τίτλο)' ) . ' – ' . SD_Workshops::UNITS[ (int) $u->unit_no ];
			}
		}
		return array(
			'classes'  => count( $classes ),
			'teachers' => count( $teachers ),
			'used'     => $used,
			'titles'   => $titles,
		);
	}

	/* =================== Ενέργειες =================== */

	private static function clean( $v, $limit = 3000 ) {
		return mb_substr( sanitize_textarea_field( (string) $v ), 0, $limit );
	}

	public static function handle( $do ) {
		global $wpdb;
		if ( ! in_array( $do, array( 'ergplan_save', 'school_ergplan_save' ), true ) ) {
			return null;
		}
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security

		if ( 'school_ergplan_save' === $do ) {
			$year = SD_Data::active_year();
			$back = SD_Frontend::url( 'ergastiria', array( 'sxolio' => 1 ) );
			if ( ! $year || ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
				return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση συμπληρώνει το σχέδιο της σχολικής μονάδας.' ), $back );
			}
			$data = array(
				'students' => min( 2000, absint( $post['students'] ?? 0 ) ),
				'staff'    => min( 300, absint( $post['staff'] ?? 0 ) ),
			);
			foreach ( array_keys( self::SCHOOL_TEXT ) as $k ) {
				$data[ $k ] = self::clean( $post[ $k ] ?? '' );
			}
			foreach ( array_keys( SD_Workshops::CYCLES ) as $c ) {
				$data['decision'][ $c ] = self::clean( $post['decision'][ $c ] ?? '' );
				$data['final'][ $c ]    = self::clean( $post['final'][ $c ] ?? '' );
			}
			update_option( self::opt( $year->id ), $data, false );
			return array( true, $back );
		}

		$class = SD_Data::get_class( absint( $post['class_id'] ?? 0 ) );
		if ( ! $class ) {
			return array( new WP_Error( 'sd', 'Το τμήμα δεν βρέθηκε.' ), SD_Frontend::url( 'ergastiria' ) );
		}
		$back = SD_Frontend::url( 'ergastiria', array( 'sxedio' => $class->id ) );
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) && ! SD_Workshops::is_class_teacher( $class->id ) ) {
			return array( new WP_Error( 'sd', 'Μόνο οι εκπαιδευτικοί του τμήματος συμπληρώνουν το σχέδιό του.' ), $back );
		}
		$cycles = array();
		foreach ( array_keys( SD_Workshops::CYCLES ) as $c ) {
			foreach ( array_keys( self::CLASS_FIELDS ) as $k ) {
				$cycles[ $c ][ $k ] = self::clean( $post['cy'][ $c ][ $k ] ?? '' );
			}
		}
		$data = array(
			'year_id'     => (int) $class->year_id,
			'class_id'    => (int) $class->id,
			'students'    => min( 99, absint( $post['students'] ?? 0 ) ),
			'orientation' => self::clean( $post['orientation'] ?? '' ),
			'cycles'      => wp_json_encode( $cycles, JSON_UNESCAPED_UNICODE ),
			'updated_by'  => get_current_user_id(),
			'updated_at'  => current_time( 'mysql' ),
		);
		if ( self::class_plan( $class->id ) ) {
			$wpdb->update( self::t(), $data, array( 'class_id' => (int) $class->id ) );
		} else {
			$wpdb->insert( self::t(), $data );
		}
		return array( true, $back );
	}

	/* =================== Οθόνες =================== */

	public static function render_class( $class_id ) {
		$class = SD_Data::get_class( $class_id );
		if ( ! $class || ! SD_Workshops::can_view( $class ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτό το τμήμα.</p>';
			return;
		}
		$plan = self::class_plan( $class->id );
		$vals = self::cycle_values( $class );
		$edit = current_user_can( SD_Roles::CAP_MANAGE ) || SD_Workshops::is_class_teacher( $class->id );
		$dis  = $edit ? '' : ' disabled';

		echo '<p><a href="' . esc_url( SD_Frontend::url( 'ergastiria' ) ) . '">← Εργαστήρια</a> · <a href="' . esc_url( SD_Exports::url( 'sxtm', $class->id ) ) . '">Λήψη σε Word</a> · <a href="' . esc_url( SD_Exports::url( 'prog', $class->id ) ) . '">Προγραμματισμός Εκπαιδευτικών (Word)</a></p>';
		echo '<h2>Σχέδιο Δράσης του Τμήματος ' . esc_html( SD_Data::class_label( $class ) ) . ' · Εργαστήρια Δεξιοτήτων</h2>';
		echo '<p class="sd-help">Υπόδειγμα της Πλατφόρμας 21+. Τα πεδία προσυμπληρώθηκαν από το Έντυπο 1Α και, στο τέλος της χρονιάς, από το Έντυπο 4. Το συμπληρώνετε όποτε θέλετε· δεν κλειδώνει και δεν επηρεάζει το 1Α.</p>';

		echo '<form method="post" class="sd-form">';
		wp_nonce_field( SD_Frontend::NONCE );
		echo '<input type="hidden" name="sd_do" value="ergplan_save"><input type="hidden" name="class_id" value="' . (int) $class->id . '">';
		echo '<section class="sd-card"><label class="sd-block">Αριθμός μαθητών <input type="number" min="0" max="99" name="students" value="' . esc_attr( $plan ? (string) $plan->students : '' ) . '"' . $dis . ' style="width:6em"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-block">Βασικός προσανατολισμός του ετήσιου Σχεδίου Δράσης<textarea name="orientation" rows="5"' . $dis . '>' . esc_textarea( $plan && '' !== $plan->orientation ? $plan->orientation : self::ORIENTATION ) . '</textarea></label></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( SD_Workshops::CYCLES as $c => $label ) {
			echo '<section class="sd-card"><h3>Θεματικός κύκλος: ' . esc_html( $label ) . '</h3><p class="sd-meta">' . esc_html( SD_Workshops::subtheme( $class->grade, $c ) ) . '</p>';
			foreach ( self::CLASS_FIELDS as $k => $l ) {
				$rows = in_array( $k, array( 'iep_title', 'our_title' ), true ) ? 1 : 3;
				echo '<label class="sd-block">' . esc_html( $l ) . '<textarea rows="' . ( 'sequence' === $k ? 7 : $rows ) . '" name="cy[' . (int) $c . '][' . esc_attr( $k ) . ']"' . $dis . '>' . esc_textarea( $vals[ $c ][ $k ] ?? '' ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</section>';
		}
		if ( $edit ) {
			echo '<p><button class="sd-button" type="submit">Αποθήκευση</button></p>';
		}
		echo '</form>';
	}

	public static function render_school() {
		$year = SD_Data::active_year();
		if ( ! $year || ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση.</p>';
			return;
		}
		$v     = self::school_plan( $year->id );
		$st    = self::school_stats( $year );
		$edit  = current_user_can( SD_Roles::CAP_MANAGE );
		$dis   = $edit ? '' : ' disabled';
		echo '<p><a href="' . esc_url( SD_Frontend::url( 'ergastiria' ) ) . '">← Εργαστήρια</a> · <a href="' . esc_url( SD_Exports::url( 'sxsm', 0 ) ) . '">Λήψη σε Word</a></p>';
		echo '<h2>Σχέδιο Δράσης της Σχολικής Μονάδας · Εργαστήρια Δεξιοτήτων ' . esc_html( $year->label ) . '</h2>';
		echo '<p class="sd-help">Τμήματα και εκπαιδευτικοί Εργαστηρίων συμπληρώνονται αυτόματα από τα Τμήματα· οι υποθεματικές και οι τίτλοι από τα Έντυπα 1Α.</p>';
		echo '<section class="sd-card"><dl class="sd-dl"><dt>Αριθμός τμημάτων</dt><dd>' . (int) $st['classes'] . '</dd><dt>Εκπαιδευτικοί που συμμετέχουν στα Εργαστήρια</dt><dd>' . (int) $st['teachers'] . '</dd></dl>';
		echo '<h3>Υποθεματικές που υλοποιούνται</h3><ul>';
		foreach ( SD_Workshops::SUBTHEMES as $id => $l ) {
			echo '<li>' . ( isset( $st['used'][ $id ] ) ? '☒ ' : '☐ ' ) . esc_html( $id . ' ' . $l ) . ( isset( $st['used'][ $id ] ) ? ' <span class="sd-meta">(' . esc_html( implode( ', ', $st['used'][ $id ] ) ) . ')</span>' : '' ) . '</li>';
		}
		echo '</ul></section>';

		echo '<form method="post" class="sd-form">';
		wp_nonce_field( SD_Frontend::NONCE );
		echo '<input type="hidden" name="sd_do" value="school_ergplan_save"><section class="sd-card"><div class="sd-row">';
		echo '<label>Αριθμός μαθητών/μαθητριών <input type="number" min="0" name="students" value="' . esc_attr( (string) ( $v['students'] ?? '' ) ) . '"' . $dis . ' style="width:7em"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label>Αριθμός εκπαιδευτικών σχολικής μονάδας <input type="number" min="0" name="staff" value="' . esc_attr( (string) ( $v['staff'] ?? '' ) ) . '"' . $dis . ' style="width:7em"></label></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( array( 'orientation', 'vision', 'goals' ) as $k ) {
			echo '<label class="sd-block">' . esc_html( self::SCHOOL_TEXT[ $k ] ) . '<textarea rows="4" name="' . esc_attr( $k ) . '"' . $dis . '>' . esc_textarea( $v[ $k ] ?? '' ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</section><section class="sd-card"><h3>Ο προγραμματισμός των εργαστηρίων ανά θεματική ενότητα</h3><p class="sd-help">Περιγράφουμε αδρά τις αποφάσεις του Συλλόγου Διδασκόντων.</p>';
		foreach ( SD_Workshops::CYCLES as $c => $l ) {
			echo '<label class="sd-block">ως προς τη Θεματική Ενότητα «' . esc_html( $l ) . '»<textarea rows="3" name="decision[' . (int) $c . ']"' . $dis . '>' . esc_textarea( $v['decision'][ $c ] ?? '' ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ! empty( $st['titles'][ $c ] ) ) {
				echo '<p class="sd-help">Από τα 1Α: ' . esc_html( implode( ' · ', $st['titles'][ $c ] ) ) . '</p>';
			}
		}
		echo '</section><section class="sd-card"><h3>Οφέλη, συνεργασίες, προϊόντα</h3>';
		foreach ( array( 'climate', 'specific', 'community', 'local', 'adaptations', 'partners', 'products', 'material' ) as $k ) {
			echo '<label class="sd-block">' . esc_html( self::SCHOOL_TEXT[ $k ] ) . '<textarea rows="3" name="' . esc_attr( $k ) . '"' . $dis . '>' . esc_textarea( $v[ $k ] ?? '' ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</section><section class="sd-card"><h3>Αναστοχασμός – τελική αξιολόγηση (στο τέλος της χρονιάς)</h3><p class="sd-help">Κείμενο έως 100 λέξεις για κάθε Θεματική Ενότητα.</p>';
		foreach ( SD_Workshops::CYCLES as $c => $l ) {
			echo '<label class="sd-block">' . esc_html( $l ) . '<textarea rows="3" name="final[' . (int) $c . ']"' . $dis . '>' . esc_textarea( $v['final'][ $c ] ?? '' ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		foreach ( array( 'end_climate', 'end_school', 'end_local', 'difficulties', 'proposals' ) as $k ) {
			echo '<label class="sd-block">' . esc_html( self::SCHOOL_TEXT[ $k ] ) . '<textarea rows="3" name="' . esc_attr( $k ) . '"' . $dis . '>' . esc_textarea( $v[ $k ] ?? '' ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</section>';
		if ( $edit ) {
			echo '<p><button class="sd-button" type="submit">Αποθήκευση</button></p>';
		}
		echo '</form>';
	}

	/* =================== Word / Excel =================== */

	private static function teachers_of( $class ) {
		$names = array();
		foreach ( SD_Data::class_teachers( $class->id ) as $t ) {
			if ( 'ergastiria' === $t->class_role ) {
				$u = get_userdata( $t->user_id );
				if ( $u ) {
					$names[] = $u->display_name;
				}
			}
		}
		return implode( ', ', $names );
	}

	/** Περίοδος εφαρμογής από την ενότητα του 1Α (π.χ. «Οκτ.–Νοέ.»). */
	private static function period_of( $unit_no ) {
		$l = SD_Workshops::UNITS[ (int) $unit_no ] ?? '';
		$p = strpos( $l, '· ' );
		return false === $p ? $l : trim( substr( $l, $p + strlen( '· ' ) ) );
	}

	/** Γραμμές «Προγραμματισμού Εκπαιδευτικών» για ένα τμήμα (με σειρά ενοτήτων). */
	public static function programming_rows( $class ) {
		$rows    = array();
		$teacher = self::teachers_of( $class );
		foreach ( SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) ) as $n => $u ) {
			$rows[] = array(
				'teacher' => $teacher,
				'cycle'   => $u->cycle ? SD_Workshops::CYCLES[ $u->cycle ] : '',
				'title'   => $u->title,
				'period'  => self::period_of( $n ),
			);
		}
		return $rows;
	}

	public static function programming_blocks( $class ) {
		$b = array(
			array( 'p', SD_Frontend::school_name(), true ),
			array( 'title', 'Προγραμματισμός Εκπαιδευτικών · Εργαστήρια Δεξιοτήτων' ),
			array( 'kv', array( array( 'Τάξη:', SD_Data::class_label( $class ) ) ) ),
		);
		foreach ( self::programming_rows( $class ) as $r ) {
			$b[] = array(
				'kv',
				array(
					array( 'Εκπαιδευτικός:', $r['teacher'] ),
					array( 'Θεματική ενότητα:', $r['cycle'] ),
					array( 'Τίτλος:', $r['title'] ? '«' . $r['title'] . '»' : '' ),
					array( 'Περίοδος Εφαρμογής:', $r['period'] ),
				),
			);
		}
		return $b;
	}

	public static function programming_sheet( $year ) {
		$rows = array( array( 'Τάξη', 'Εκπαιδευτικός', 'Θεματική ενότητα', 'Τίτλος', 'Περίοδος εφαρμογής' ) );
		foreach ( SD_Data::classes( $year->id ) as $c ) {
			foreach ( self::programming_rows( $c ) as $r ) {
				$rows[] = array( SD_Data::class_label( $c ), $r['teacher'], $r['cycle'], $r['title'], $r['period'] );
			}
		}
		return $rows;
	}

	public static function class_blocks( $class ) {
		$plan = self::class_plan( $class->id );
		$vals = self::cycle_values( $class );
		$year = null;
		foreach ( SD_Data::years() as $y ) {
			if ( (int) $y->id === (int) $class->year_id ) {
				$year = $y;
			}
		}
		$teach = array();
		foreach ( SD_Data::class_teachers( $class->id ) as $t ) {
			$u = get_userdata( $t->user_id );
			if ( $u ) {
				$teach[ $u->ID ] = $u->display_name . ' (' . ( SD_Data::CLASS_ROLES[ $t->class_role ] ?? '' ) . ')';
			}
		}
		$b = array(
			array( 'p', 'ΠΛΑΤΦΟΡΜΑ 21+: ΕΡΓΑΣΤΗΡΙΑ ΔΕΞΙΟΤΗΤΩΝ', true ),
			array( 'title', 'Σχέδιο Δράσης του Τμήματος · Σχολικό έτος ' . ( $year ? $year->label : '' ) ),
			array(
				'kv',
				array(
					array( 'Σχολείο:', SD_Frontend::school_name() ),
					array( 'Τάξη:', SD_Data::GRADES[ (int) $class->grade ] ?? '' ),
					array( 'Τμήμα:', SD_Data::class_label( $class ) ),
					array( 'Αριθμός μαθητών:', $plan && $plan->students ? (string) $plan->students : '' ),
					array( 'Συνολικός αριθμός εκπαιδευτικών που συνεργάζονται:', (string) count( $teach ) ),
					array( 'Στοιχεία εκπαιδευτικών (ονοματεπώνυμο, ρόλος):', implode( "\n", $teach ) ),
					array( 'Βασικός προσανατολισμός του ετήσιου Σχεδίου Δράσης', $plan && '' !== $plan->orientation ? $plan->orientation : self::ORIENTATION ),
				),
			),
		);
		foreach ( SD_Workshops::CYCLES as $c => $label ) {
			$v    = $vals[ $c ];
			$seq  = array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', (string) $v['sequence'] ) ) ) );
			$rows = array(
				array( 'Θεματικός Κύκλος:', $label ),
				array( 'Επιμέρους Θεματική Ενότητα:', SD_Workshops::subtheme( $class->grade, $c ) ),
				array( 'Τίτλος/τίτλοι προγραμμάτων:', $v['iep_title'] ),
				array( 'Ο δικός μας τίτλος:', $v['our_title'] ),
				array( 'Στόχοι Σχεδίου Δράσης', $v['goals'] ),
			);
			for ( $i = 0; $i < 7; $i++ ) {
				$rows[] = array( 'Εργαστήριο ' . ( $i + 1 ), $seq[ $i ] ?? '' );
			}
			if ( count( $seq ) > 7 ) {
				$rows[6][1] .= "\n" . implode( "\n", array_slice( $seq, 7 ) );
			}
			foreach ( array( 'adaptations', 'partners', 'products', 'material', 'assessment', 'diffusion' ) as $k ) {
				$rows[] = array( self::CLASS_FIELDS[ $k ], $v[ $k ] );
			}
			$b[] = array( 'h', 'Θεματικός Κύκλος «' . $label . '»' );
			$b[] = array( 'kv', $rows );
		}
		return $b;
	}

	public static function school_blocks( $year ) {
		$v  = self::school_plan( $year->id );
		$st = self::school_stats( $year );
		$sub = array();
		foreach ( SD_Workshops::CYCLES as $c => $l ) {
			$items = array();
			foreach ( SD_Workshops::SUBTHEMES as $id => $sl ) {
				if ( 0 === strpos( $id, $c . '.' ) ) {
					$items[] = ( isset( $st['used'][ $id ] ) ? '☒ ' : '☐ ' ) . $id . ' ' . $sl . ( isset( $st['used'][ $id ] ) ? ' (' . implode( ', ', $st['used'][ $id ] ) . ')' : '' );
				}
			}
			$sub[] = array( $l, implode( "\n", $items ) );
		}
		$b = array(
			array( 'p', 'ΕΡΓΑΣΤΗΡΙΑ ΔΕΞΙΟΤΗΤΩΝ 21+', true ),
			array( 'title', 'Σχέδιο Δράσης Σχολικής Μονάδας · Σχολικό έτος ' . $year->label ),
			array(
				'kv',
				array(
					array( 'Σχολική μονάδα', SD_Frontend::school_name() ),
					array( 'Αριθμός τμημάτων', (string) $st['classes'] ),
					array( 'Αριθμός μαθητών/μαθητριών σχολικής μονάδας', (string) ( $v['students'] ?? '' ) ),
					array( 'Αριθμός εκπαιδευτικών σχολικής μονάδας', (string) ( $v['staff'] ?? '' ) ),
					array( 'Αριθμός εκπαιδευτικών που συμμετέχουν στα Εργαστήρια δεξιοτήτων', (string) $st['teachers'] ),
				),
			),
			array( 'h', 'Θεματικές ενότητες και υποθεματικές' ),
			array( 'kv', $sub ),
			array(
				'kv',
				array(
					array( self::SCHOOL_TEXT['orientation'], $v['orientation'] ?? '' ),
					array( self::SCHOOL_TEXT['vision'], $v['vision'] ?? '' ),
					array( self::SCHOOL_TEXT['goals'], $v['goals'] ?? '' ),
				),
			),
			array( 'h', 'Ο προγραμματισμός των εργαστηρίων ανά θεματική ενότητα' ),
		);
		$rows = array();
		foreach ( SD_Workshops::CYCLES as $c => $l ) {
			$rows[] = array( 'ως προς τη Θεματική Ενότητα «' . $l . '»', $v['decision'][ $c ] ?? '' );
		}
		foreach ( array( 'climate', 'specific', 'community', 'local', 'adaptations', 'partners', 'products', 'material' ) as $k ) {
			$rows[] = array( self::SCHOOL_TEXT[ $k ], $v[ $k ] ?? '' );
		}
		$b[] = array( 'kv', $rows );
		$b[] = array( 'h', 'Αναστοχασμός – τελική αξιολόγηση της εφαρμογής' );
		$rows = array();
		foreach ( SD_Workshops::CYCLES as $c => $l ) {
			$rows[] = array( '1. Τελική αξιολόγηση: ' . $l, $v['final'][ $c ] ?? '' );
		}
		foreach ( array( 'end_climate', 'end_school', 'end_local', 'difficulties', 'proposals' ) as $k ) {
			$rows[] = array( self::SCHOOL_TEXT[ $k ], $v[ $k ] ?? '' );
		}
		$b[] = array( 'kv', $rows );
		return $b;
	}
}
