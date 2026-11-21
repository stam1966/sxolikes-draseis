<?php
/**
 * Έντυπο 2 – Σχεδιασμός Δράσης Ενεργού Πολίτη.
 *
 * Πηγές: εγκ. 128360/ΓΔ4/28-09-2026 (παρ. 1.6–1.9 ώρες και μαθήματα, 1.7 Τοπική
 * Ιστορία/Γεωγραφία, 2.1 πεδίο, 5.2 πέντε στάδια, 5.6 το θέμα το διαμορφώνουν
 * οι μαθητές, Παράρτημα: πρότυπο), ΦΕΚ Β΄ 6048/2024 (17 θεματικά πεδία).
 *
 * Ροή όπως στο 1Α: Πρόχειρο → Υποβλήθηκε → (Επιστράφηκε) → Ελέγχθηκε.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Citizen {

	const HOURS = 10;

	const SDGS = array(
		1  => 'Μηδενική Φτώχεια',
		2  => 'Μηδενική Πείνα',
		3  => 'Καλή Υγεία και Ευημερία',
		4  => 'Ποιοτική Εκπαίδευση',
		5  => 'Ισότητα των Φύλων',
		6  => 'Καθαρό Νερό και Αποχέτευση',
		7  => 'Φτηνή και Καθαρή Ενέργεια',
		8  => 'Αξιοπρεπής Εργασία και Οικονομική Ανάπτυξη',
		9  => 'Βιομηχανία, Καινοτομία και Υποδομές',
		10 => 'Λιγότερες Ανισότητες',
		11 => 'Βιώσιμες Πόλεις και Κοινότητες',
		12 => 'Υπεύθυνη Κατανάλωση και Παραγωγή',
		13 => 'Δράση για το Κλίμα',
		14 => 'Ζωή στο Νερό',
		15 => 'Ζωή στη Στεριά',
		16 => 'Ειρήνη, Δικαιοσύνη, Κοινωνική Ισότητα και Ισχυροί Θεσμοί',
		17 => 'Συνεργασία για τους Στόχους',
	);

	const KINDS = array(
		'istoria'   => 'Δράση Τοπικής Ιστορίας',
		'geografia' => 'Δράση Τοπικής Γεωγραφίας',
		'alli'      => 'Άλλη Δράση Ενεργού Πολίτη',
	);

	/** Τα 6 ζητήματα ενεργού πολιτειότητας του Προτύπου Ανάπτυξης Δράσεων. */
	const ZITIMATA = array(
		'allileggyi' => 'Κοινωνική αλληλεγγύη – υποστήριξη ευπαθών ομάδων',
		'synyparxi'  => 'Συνύπαρξη στην κοινότητα',
		'diktyosi'   => 'Δικτύωση μέσα από συμμετοχικές δράσεις και πρωτοβουλίες παροχής βοήθειας σε δύσκολες καταστάσεις',
		'poiotita'   => 'Προαγωγή ποιότητας ζωής του γενικού πληθυσμού (υγεία, εκπαίδευση, δικαιώματα)',
		'perivallon' => 'Προστασία και ανάδειξη του φυσικού και πολιτιστικού περιβάλλοντος',
		'filozoia'   => 'Φιλοζωία',
	);

	private static $programs = null;

	/** Έτοιμα προγράμματα (ΚΕΠΕΑ και προτάσεις ΔΠΕ) από το data/programmata.json. */
	public static function programs() {
		if ( null === self::$programs ) {
			$raw            = file_get_contents( SD_DIR . 'data/programmata.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$j              = $raw ? json_decode( $raw, true ) : array();
			self::$programs = array();
			foreach ( $j['kepea'] ?? array() as $p ) {
				$tags = array();
				if ( ! empty( $p['istoria'] ) ) {
					$tags[] = 'Τοπ. Ιστορία';
				}
				if ( ! empty( $p['geografia'] ) ) {
					$tags[] = 'Τοπ. Γεωγραφία';
				}
				$p['label']                    = 'ΚΕΠΕΑ: ' . $p['title'] . ( $tags ? ' (' . implode( ', ', $tags ) . ')' : '' );
				$p['group']                    = 'kepea';
				self::$programs[ $p['id'] ] = $p;
			}
			foreach ( $j['dpe'] ?? array() as $p ) {
				$p['label']                    = 'ΔΠΕ Φθιώτιδας – ' . $p['title'];
				$p['group']                    = 'dpe';
				self::$programs[ $p['id'] ] = $p;
			}
		}
		return self::$programs;
	}

	/** Αριθμημένοι ΣΒΑ, όπως στο myschool: «3 Καλή Υγεία και Ευημερία, 4 Ποιοτική Εκπαίδευση». */
	public static function sdg_text( $csv ) {
		$out = array();
		foreach ( '' === (string) $csv ? array() : explode( ',', $csv ) as $k ) {
			if ( isset( self::SDGS[ (int) $k ] ) ) {
				$out[ (int) $k ] = (int) $k . ' ' . self::SDGS[ (int) $k ];
			}
		}
		ksort( $out );
		return implode( ', ', $out );
	}

	/** Περίοδος υλοποίησης: το πεδίο, αλλιώς από το «Πότε» των φάσεων. */
	public static function period_text( $dep ) {
		if ( '' !== trim( (string) $dep->period ) ) {
			return $dep->period;
		}
		$w = array();
		foreach ( $dep->stage_arr as $s ) {
			if ( '' !== trim( (string) ( $s['when'] ?? '' ) ) ) {
				$w[] = trim( $s['when'] );
			}
		}
		return implode( ' · ', array_unique( $w ) );
	}

	const SUBJECTS = array(
		'ergastiria' => 'Εργαστήρια Δεξιοτήτων',
		'mep'        => 'Μελέτη Περιβάλλοντος',
		'istoria'    => 'Ιστορία',
		'kpa'        => 'Κοινωνική και Πολιτική Αγωγή',
		'fysika'     => 'Φυσικά',
		'geografia'  => 'Γεωγραφία',
		'allo'       => 'Άλλο μάθημα / πλαίσιο (με αιτιολόγηση)',
	);

	/** Μαθήματα της εγκυκλίου ανά τάξη (παρ. 1.9.1–1.9.3). */
	const GRADE_SUBJECTS = array(
		1 => array( 'ergastiria', 'mep' ),
		2 => array( 'ergastiria', 'mep' ),
		3 => array( 'ergastiria', 'mep', 'istoria' ),
		4 => array( 'ergastiria', 'mep', 'istoria' ),
		5 => array( 'ergastiria', 'kpa', 'fysika', 'geografia', 'istoria' ),
		6 => array( 'ergastiria', 'kpa', 'fysika', 'geografia', 'istoria' ),
	);

	const STAGES = array(
		'dierevnisi'   => '1. Διερεύνηση (επιλογή θέματος με τους μαθητές)',
		'proetoimasia' => '2. Προετοιμασία / Σχεδιασμός (βήματα, επαφές με φορείς)',
		'ylopoiisi'    => '3. Υλοποίηση στο πεδίο',
		'diaxysi'      => '4. Επικοινωνία – Διάχυση',
		'anastoxasmos' => '5. Αναστοχασμός',
	);

	const PLACES = array(
		'avli'       => 'Αυλή ή άλλοι χώροι του σχολείου',
		'perpatima'  => 'Προορισμοί σε απόσταση περπατήματος',
		'metakinisi' => 'Προορισμοί με μετακίνηση (συγκοινωνιακό μέσο)',
		'psifiakos'  => 'Ψηφιακός χώρος',
	);

	const AUDIENCE = array(
		'sxoliki'   => 'Σχολική κοινότητα',
		'koinotita' => 'Κοινότητα – γειτονιά',
		'xora'      => 'Χώρα / διεθνής χώρος',
		'psifiakos' => 'Ψηφιακός κόσμος',
	);

	const DIFFUSION = array(
		'ekdilosi'   => 'Σχολική εκδήλωση',
		'istoselida' => 'Ιστοσελίδα σχολείου',
		'afisa'      => 'Αφίσα / έκθεση',
		'video'      => 'Βίντεο / ψηφιακό υλικό',
	);

	const EVIDENCE = array(
		'eportfolio'  => 'e-portfolio τμήματος',
		'fakelos'     => 'Φάκελος εργασιών',
		'fotografies' => 'Φωτογραφίες χωρίς πρόσωπα μαθητών',
	);

	/** Ηλικιακή ομάδα του ΠΣ (παρ. 1.3): Α΄–Γ΄ με το Νηπιαγωγείο, Δ΄–ΣΤ΄ μαζί. */
	public static function age_group( $grade ) {
		return (int) $grade <= 3 ? 'Νηπιαγωγείο – Α΄, Β΄, Γ΄ Δημοτικού' : 'Δ΄, Ε΄, ΣΤ΄ Δημοτικού';
	}

	/* =================== Δεδομένα =================== */

	private static function t() {
		return SD_Data::table( 'citizen' );
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ) );
		return $row ? self::decode( $row ) : null;
	}

	public static function of_class( $class_id ) {
		global $wpdb;
		return array_map( array( __CLASS__, 'decode' ), $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE class_id = %d ORDER BY id', $class_id ) ) );
	}

	public static function of_year( $year_id ) {
		global $wpdb;
		return array_map( array( __CLASS__, 'decode' ), $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE year_id = %d ORDER BY class_id, id', $year_id ) ) );
	}

	private static function decode( $row ) {
		$rows           = json_decode( (string) $row->hours_rows, true );
		$stages         = json_decode( (string) $row->stages, true );
		$row->hours_arr = is_array( $rows ) ? $rows : array();
		$row->stage_arr = is_array( $stages ) ? $stages : array();
		return $row;
	}

	private static function csv( $csv ) {
		return '' === (string) $csv ? array() : explode( ',', $csv );
	}

	public static function hours_total( $dep ) {
		$sum = 0;
		foreach ( $dep->hours_arr as $r ) {
			$sum += (int) ( $r['hours'] ?? 0 );
		}
		return $sum;
	}

	public static function stages_total( $dep ) {
		$sum = 0;
		foreach ( $dep->stage_arr as $s ) {
			$sum += (int) ( $s['hours'] ?? 0 );
		}
		return $sum;
	}

	private static function has_subject( $dep, $subject ) {
		foreach ( $dep->hours_arr as $r ) {
			if ( ( $r['subject'] ?? '' ) === $subject && (int) ( $r['hours'] ?? 0 ) > 0 ) {
				return true;
			}
		}
		return false;
	}

	/** Μετρά για την υποχρεωτική Τοπική Ιστορία / Γεωγραφία του σχολείου (παρ. 1.7); */
	public static function counts_as_local( $dep, $grade ) {
		if ( 'istoria' === $dep->kind ) {
			return $grade >= 3 && self::has_subject( $dep, 'istoria' );
		}
		if ( 'geografia' === $dep->kind ) {
			return ( in_array( (int) $grade, array( 3, 4 ), true ) && self::has_subject( $dep, 'mep' ) )
				|| ( (int) $grade >= 5 && self::has_subject( $dep, 'geografia' ) );
		}
		return false;
	}

	public static function issues( $dep, $class ) {
		$out = array();
		if ( '' === trim( $dep->title ) ) {
			$out[] = array( 'error', 'Λείπει ο τίτλος (μπορεί να είναι προσωρινός).' );
		}
		if ( '' === $dep->sdgs ) {
			$out[] = array( 'error', 'Διαλέξτε τουλάχιστον ένα θεματικό πεδίο (ΣΒΑ).' );
		}
		if ( '' === trim( $dep->description ) ) {
			$out[] = array( 'error', 'Λείπει η σύντομη περιγραφή.' );
		}
		$h = self::hours_total( $dep );
		if ( self::HOURS !== $h ) {
			$out[] = array( 'error', 'Κατανομή ωρών ανά μάθημα: σύνολο ' . $h . ' αντί για ' . self::HOURS . '.' );
		}
		$s = self::stages_total( $dep );
		if ( self::HOURS !== $s ) {
			$out[] = array( 'error', 'Ώρες ανά στάδιο: σύνολο ' . $s . ' αντί για ' . self::HOURS . '.' );
		}
		foreach ( array_keys( self::STAGES ) as $k ) {
			if ( empty( $dep->stage_arr[ $k ]['hours'] ) ) {
				$out[] = array( 'warning', 'Το στάδιο «' . preg_replace( '/^\d\.\s*|\s*\(.*$/u', '', self::STAGES[ $k ] ) . '» δεν έχει ώρες. Η δράση περνά και από τα 5 στάδια.' );
			}
		}
		if ( '' === trim( $dep->outcomes ) ) {
			$out[] = array( 'warning', 'Συμπληρώστε τα προσδοκώμενα μαθησιακά αποτελέσματα.' );
		}
		$allowed = self::GRADE_SUBJECTS[ (int) $class->grade ] ?? array();
		foreach ( $dep->hours_arr as $r ) {
			$sub = $r['subject'] ?? '';
			if ( 'allo' === $sub && '' === trim( $r['unit'] ?? '' ) ) {
				$out[] = array( 'warning', '«Άλλο μάθημα»: γράψτε στη στήλη «Ενότητα» ποιο μάθημα και γιατί.' );
			} elseif ( $sub && 'allo' !== $sub && ! in_array( $sub, $allowed, true ) ) {
				$out[] = array( 'warning', 'Το μάθημα «' . self::SUBJECTS[ $sub ] . '» δεν είναι στα μαθήματα της εγκυκλίου για αυτή την τάξη.' );
			}
		}
		if ( 'istoria' === $dep->kind && ! self::counts_as_local( $dep, $class->grade ) ) {
			$out[] = array( 'warning', 'Η Τοπική Ιστορία γίνεται στην Ιστορία (Γ΄–ΣΤ΄). Βάλτε ώρες Ιστορίας για να μετρήσει στην υποχρέωση του σχολείου.' );
		}
		if ( 'geografia' === $dep->kind && ! self::counts_as_local( $dep, $class->grade ) ) {
			$out[] = array( 'warning', 'Η Τοπική Γεωγραφία γίνεται στη Μελέτη Περιβάλλοντος (Γ΄–Δ΄) ή στη Γεωγραφία (Ε΄–ΣΤ΄). Βάλτε ώρες του μαθήματος για να μετρήσει.' );
		}
		if ( '' !== trim( $dep->visit ) || '' !== trim( $dep->visitor ) ) {
			$out[] = array( 'info', 'Για επίσκεψη ή φορέα στο σχολείο υποβάλλεται και Έντυπο 3, τουλάχιστον 10 εργάσιμες πριν.' );
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

	public static function can_view( $class ) {
		return current_user_can( SD_Roles::CAP_REVIEW ) || SD_Workshops::is_class_teacher( $class->id );
	}

	public static function can_edit( $class, $dep ) {
		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return true;
		}
		$status = $dep ? $dep->status : 'proxeiro';
		return SD_Workshops::is_class_teacher( $class->id ) && in_array( $status, array( 'proxeiro', 'epistrafike' ), true );
	}

	/* =================== Ενέργειες (POST) =================== */

	private static function keys( $field, array $allowed ) {
		$raw = isset( $_POST[ $field ] ) && is_array( $_POST[ $field ] ) ? array_map( 'sanitize_key', wp_unslash( $_POST[ $field ] ) ) : array(); // phpcs:ignore WordPress.Security
		return implode( ',', array_values( array_intersect( array_map( 'strval', array_keys( $allowed ) ), $raw ) ) );
	}

	private static function text( $field, $multiline = true ) {
		if ( ! isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security
			return '';
		}
		$v = wp_unslash( $_POST[ $field ] ); // phpcs:ignore WordPress.Security
		return $multiline ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
	}

	private static function valid_action( $id, $class ) {
		$id = absint( $id );
		if ( ! $id ) {
			return 0;
		}
		foreach ( SD_Plans::year_actions( $class->year_id, $class->grade ) as $row ) {
			if ( (int) $row[2]->id === $id ) {
				return $id;
			}
		}
		return 0;
	}

	public static function handle( $do ) {
		global $wpdb;
		if ( ! in_array( $do, array( 'dep_create', 'dep_save', 'dep_submit', 'dep_review', 'dep_delete' ), true ) ) {
			return null;
		}
		$list = SD_Frontend::url( 'politis' );

		if ( 'dep_create' === $do ) {
			$class = SD_Data::get_class( isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0 );
			if ( ! $class || ! self::can_edit( $class, null ) ) {
				return array( new WP_Error( 'sd', 'Δεν μπορείτε να δημιουργήσετε δράση σε αυτό το τμήμα.' ), $list );
			}
			$title = self::text( 'title', false );
			if ( '' === $title ) {
				return array( new WP_Error( 'sd', 'Γράψτε έναν (προσωρινό) τίτλο.' ), $list );
			}
			$now = current_time( 'mysql' );
			$wpdb->insert(
				self::t(),
				array(
					'year_id'     => (int) $class->year_id,
					'class_id'    => (int) $class->id,
					'owner_id'    => get_current_user_id(),
					'title'       => $title,
					'partners'    => '',
					'description' => '',
					'outcomes'    => '',
					'hours_rows'  => '[]',
					'stages'      => '{}',
					'visit'       => '',
					'visitor'     => '',
					'ai_notes'    => '',
					'review_note' => '',
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);
			return array( true, SD_Frontend::url( 'politis', array( 'id' => (int) $wpdb->insert_id ) ) );
		}

		$dep = self::get( isset( $_POST['dep_id'] ) ? absint( $_POST['dep_id'] ) : 0 );
		if ( ! $dep ) {
			return array( new WP_Error( 'sd', 'Η δράση δεν βρέθηκε.' ), $list );
		}
		$class = SD_Data::get_class( $dep->class_id );
		$back  = SD_Frontend::url( 'politis', array( 'id' => $dep->id ) );

		if ( 'dep_review' === $do ) {
			if ( ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
				return array( new WP_Error( 'sd', 'Μόνο η Επιτροπή και η Διεύθυνση ελέγχουν τα έντυπα.' ), $back );
			}
			if ( ! in_array( $dep->status, array( 'ypovlithike', 'elegxthike' ), true ) ) {
				return array( new WP_Error( 'sd', 'Το έντυπο δεν έχει υποβληθεί.' ), $back );
			}
			$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
			$note     = self::text( 'review_note' );
			if ( 'return' === $decision && '' === trim( $note ) ) {
				return array( new WP_Error( 'sd', 'Γράψτε τι χρειάζεται διόρθωση.' ), $back );
			}
			$wpdb->update(
				self::t(),
				array(
					'status'      => 'return' === $decision ? 'epistrafike' : 'elegxthike',
					'review_note' => $note,
					'reviewed_by' => get_current_user_id(),
					'reviewed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $dep->id )
			);
			return array( true, $back );
		}

		if ( ! $class || ! self::can_edit( $class, $dep ) ) {
			return array( new WP_Error( 'sd', 'Το έντυπο δεν μπορεί να αλλάξει τώρα (επεξεργάζεται όσο είναι πρόχειρο ή έχει επιστραφεί).' ), $back );
		}

		if ( 'dep_delete' === $do ) {
			if ( 'proxeiro' !== $dep->status && ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
				return array( new WP_Error( 'sd', 'Διαγράφεται μόνο πρόχειρη δράση.' ), $back );
			}
			$wpdb->delete( self::t(), array( 'id' => $dep->id ) );
			return array( true, $list );
		}

		if ( 'dep_submit' === $do ) {
			if ( self::has_errors( self::issues( $dep, $class ) ) ) {
				return array( new WP_Error( 'sd', 'Υπάρχουν ελλείψεις που πρέπει να διορθωθούν πριν την υποβολή.' ), $back );
			}
			$wpdb->update(
				self::t(),
				array(
					'status'       => 'ypovlithike',
					'submitted_by' => get_current_user_id(),
					'submitted_at' => current_time( 'mysql' ),
				),
				array( 'id' => $dep->id )
			);
			return array( true, $back );
		}

		// dep_save: όλο το έντυπο.
		$rows    = array();
		$posted  = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array(); // phpcs:ignore WordPress.Security
		foreach ( $posted as $r ) {
			$sub   = isset( $r['subject'] ) ? sanitize_key( $r['subject'] ) : '';
			$hours = isset( $r['hours'] ) ? min( 10, absint( $r['hours'] ) ) : 0;
			$unit  = isset( $r['unit'] ) ? sanitize_text_field( $r['unit'] ) : '';
			$per   = isset( $r['period'] ) ? sanitize_text_field( $r['period'] ) : '';
			if ( ! isset( self::SUBJECTS[ $sub ] ) || ( ! $hours && '' === $unit ) ) {
				continue;
			}
			$rows[] = array(
				'subject' => $sub,
				'unit'    => $unit,
				'hours'   => $hours,
				'period'  => $per,
			);
		}
		$stages = array();
		$st_in  = isset( $_POST['stages'] ) && is_array( $_POST['stages'] ) ? wp_unslash( $_POST['stages'] ) : array(); // phpcs:ignore WordPress.Security
		foreach ( array_keys( self::STAGES ) as $k ) {
			$s            = isset( $st_in[ $k ] ) && is_array( $st_in[ $k ] ) ? $st_in[ $k ] : array();
			$stages[ $k ] = array(
				'activities' => isset( $s['activities'] ) ? sanitize_textarea_field( $s['activities'] ) : '',
				'hours'      => isset( $s['hours'] ) ? min( 10, absint( $s['hours'] ) ) : 0,
				'when'       => isset( $s['when'] ) ? sanitize_text_field( $s['when'] ) : '',
			);
		}
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'alli';
		$prog = isset( $_POST['program'] ) ? sanitize_key( wp_unslash( $_POST['program'] ) ) : '';
		$prog = self::programs()[ $prog ] ?? null;
		if ( $prog && 'kepea' === $prog['group'] ) {
			// Πρόγραμμα ΚΕΠΕΑ: σημειώνεται αυτόματα η συνεργασία.
			$_POST['kepea'] = 1;
			if ( '' === trim( (string) ( $_POST['kepea_note'] ?? '' ) ) ) {
				$_POST['kepea_note'] = $prog['title'];
			}
		}
		$unit = isset( $_POST['f1a_unit'] ) ? absint( $_POST['f1a_unit'] ) : 0;
		$wpdb->update(
			self::t(),
			array(
				'title'             => self::text( 'title', false ),
				'title_provisional' => empty( $_POST['title_provisional'] ) ? 0 : 1,
				'kind'              => isset( self::KINDS[ $kind ] ) ? $kind : 'alli',
				'kepea'             => empty( $_POST['kepea'] ) ? 0 : 1,
				'kepea_note'        => self::text( 'kepea_note', false ),
				'partners'          => self::text( 'partners' ),
				'sdgs'              => self::keys( 'sdgs', self::SDGS ),
				'action_id'         => self::valid_action( $_POST['action_id'] ?? 0, $class ),
				'action2_id'        => self::valid_action( $_POST['action2_id'] ?? 0, $class ),
				'f1a_unit'          => isset( SD_Workshops::UNITS[ $unit ] ) ? $unit : 0,
				'description'       => self::text( 'description' ),
				'outcomes'          => self::text( 'outcomes' ),
				'hours_rows'        => wp_json_encode( $rows ),
				'places'            => self::keys( 'places', self::PLACES ),
				'audience'          => self::keys( 'audience', self::AUDIENCE ),
				'stages'            => wp_json_encode( $stages ),
				'visit'             => self::text( 'visit' ),
				'visitor'           => self::text( 'visitor' ),
				'diffusion'         => self::keys( 'diffusion', self::DIFFUSION ),
				'diffusion_other'   => self::text( 'diffusion_other', false ),
				'evidence'          => self::keys( 'evidence', self::EVIDENCE ),
				'period'            => mb_substr( self::text( 'period', false ), 0, 100 ),
				'zitima'            => isset( $_POST['zitima'] ) && isset( self::ZITIMATA[ $_POST['zitima'] ] ) ? sanitize_key( $_POST['zitima'] ) : '',
				'program'           => isset( $_POST['program'] ) && isset( self::programs()[ $_POST['program'] ] ) ? sanitize_key( $_POST['program'] ) : '',
				'ai_notes'          => self::text( 'ai_notes' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => $dep->id )
		);
		return array( true, $back );
	}

	/* =================== Οθόνες =================== */

	private static function badge( $status ) {
		$cls = array(
			'proxeiro'    => '',
			'ypovlithike' => 'sd-badge-info',
			'epistrafike' => 'sd-badge-warn',
			'elegxthike'  => 'sd-badge-ok',
		);
		return '<span class="sd-badge ' . esc_attr( $cls[ $status ] ?? '' ) . '">' . esc_html( SD_Workshops::STATUS[ $status ] ?? $status ) . '</span>';
	}

	private static function checks( $name, array $options, $csv ) {
		$sel = self::csv( $csv );
		echo '<div class="sd-checks">';
		foreach ( $options as $k => $label ) {
			printf(
				'<label><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
				esc_attr( $name ),
				esc_attr( $k ),
				in_array( (string) $k, $sel, true ) ? ' checked' : '',
				esc_html( is_int( $k ) ? $k . '. ' . $label : $label )
			);
		}
		echo '</div>';
	}

	private static function labels( $csv, array $options ) {
		$out = array();
		foreach ( self::csv( $csv ) as $k ) {
			if ( isset( $options[ $k ] ) ) {
				$out[] = is_numeric( $k ) ? $k . '. ' . $options[ $k ] : $options[ $k ];
			}
		}
		return $out;
	}

	public static function render() {
		if ( isset( $_GET['print'] ) ) {
			self::render_print( absint( $_GET['print'] ) );
		} elseif ( isset( $_GET['id'] ) ) {
			self::render_form( absint( $_GET['id'] ) );
		} else {
			self::render_list();
		}
	}

	private static function render_list() {
		$year = SD_Data::active_year();
		if ( ! $year ) {
			echo '<p class="sd-notice sd-error">Δεν υπάρχει τρέχον σχολικό έτος.</p>';
			return;
		}
		echo '<h2>Δράσεις Ενεργού Πολίτη · Έντυπο 2</h2>';
		echo '<p class="sd-help">Κάθε τάξη κάνει 1 έως 3 δράσεις των 10 διδακτικών ωρών. Το θέμα το διαμορφώνουν οι μαθητές στη Διερεύνηση, γι’ αυτό ο τίτλος είναι αρχικά προσωρινός.</p>';

		$review  = current_user_can( SD_Roles::CAP_REVIEW );
		$classes = $review ? SD_Data::classes( $year->id ) : SD_Data::classes_of_user( get_current_user_id(), $year->id );

		if ( $review ) {
			$hist = array();
			$geo  = array();
			foreach ( self::of_year( $year->id ) as $d ) {
				$c = SD_Data::get_class( $d->class_id );
				if ( $c && self::counts_as_local( $d, $c->grade ) ) {
					$item = SD_Data::class_label( $c ) . ': ' . $d->title . ' (' . SD_Workshops::STATUS[ $d->status ] . ')';
					if ( 'istoria' === $d->kind ) {
						$hist[] = $item;
					} else {
						$geo[] = $item;
					}
				}
			}
			echo '<section class="sd-card"><h3>Υποχρέωση σχολείου: Τοπική Ιστορία και Τοπική Γεωγραφία</h3>';
			echo '<p class="sd-help">Μία δράση Τοπικής Ιστορίας και μία Τοπικής Γεωγραφίας ανά σχολείο και έτος (εγκ. 128360/2026, παρ. 1.7). Μετράει μόνο αν έχει ώρες του σωστού μαθήματος.</p><ul>';
			echo '<li>Τοπική Ιστορία: ' . ( $hist ? '<strong>' . esc_html( implode( ' · ', $hist ) ) . '</strong>' : '<span class="sd-missing">δεν έχει οριστεί</span>' ) . '</li>';
			echo '<li>Τοπική Γεωγραφία: ' . ( $geo ? '<strong>' . esc_html( implode( ' · ', $geo ) ) . '</strong>' : '<span class="sd-missing">δεν έχει οριστεί</span>' ) . '</li>';
			echo '</ul></section>';
		}

		if ( ! $classes ) {
			echo '<p>Δεν σας έχει αντιστοιχιστεί τμήμα.</p>';
			return;
		}
		echo '<table class="sd-table"><thead><tr><th>Τμήμα</th><th>Δράσεις</th><th></th></tr></thead><tbody>';
		foreach ( $classes as $c ) {
			$deps = self::of_class( $c->id );
			echo '<tr><td><strong>' . esc_html( SD_Data::class_label( $c ) ) . '</strong>';
			if ( ! $deps ) {
				echo '<br><span class="sd-missing">Χωρίς δράση (χρειάζεται τουλάχιστον 1)</span>';
			} elseif ( count( $deps ) > 3 ) {
				echo '<br><span class="sd-missing">Πάνω από 3 δράσεις</span>';
			}
			echo '</td><td>';
			foreach ( $deps as $d ) {
				echo '<div><a href="' . esc_url( SD_Frontend::url( 'politis', array( 'id' => $d->id ) ) ) . '">' . esc_html( $d->title ) . '</a> ' . self::badge( $d->status ) . ( 'alli' !== $d->kind ? ' <span class="sd-badge">' . esc_html( self::KINDS[ $d->kind ] ) . '</span>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</td><td>';
			if ( self::can_edit( $c, null ) && count( $deps ) < 3 ) {
				SD_Frontend::form_open( 'dep_create' );
				echo '<input type="hidden" name="class_id" value="' . (int) $c->id . '"><div class="sd-row"><input name="title" placeholder="Προσωρινός τίτλος" required> <button class="sd-button sd-small" type="submit">Νέα δράση</button></div></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_form( $id ) {
		$dep   = self::get( $id );
		$class = $dep ? SD_Data::get_class( $dep->class_id ) : null;
		if ( ! $dep || ! $class || ! self::can_view( $class ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτή τη δράση.</p>';
			return;
		}
		$edit   = self::can_edit( $class, $dep );
		$issues = self::issues( $dep, $class );
		$ro     = $edit ? '' : ' disabled';

		echo '<p><a href="' . esc_url( SD_Frontend::url( 'politis' ) ) . '">← Όλες οι δράσεις</a> · <a href="' . esc_url( SD_Frontend::url( 'politis', array( 'print' => $dep->id ) ) ) . '" target="_blank" rel="noopener">Εκτύπωση</a> · <a href="' . esc_url( SD_Exports::url( 'f2', $dep->id ) ) . '">Word (Έντυπο 2)</a> · <a href="' . esc_url( SD_Exports::url( 'f2p', $dep->id ) ) . '">Word (Πρότυπο Ανάπτυξης Δράσης)</a></p>';
		echo '<h2>Έντυπο 2 · ' . esc_html( SD_Data::class_label( $class ) ) . ': ' . esc_html( $dep->title ) . ' ' . self::badge( $dep->status ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="sd-meta">Ηλικιακό επίπεδο ΠΣ: ' . esc_html( self::age_group( $class->grade ) ) . '</p>';

		if ( 'epistrafike' === $dep->status && $dep->review_note ) {
			echo '<p class="sd-notice sd-error"><strong>Σχόλια ελέγχου:</strong> ' . nl2br( esc_html( $dep->review_note ) ) . '</p>';
		} elseif ( 'elegxthike' === $dep->status ) {
			echo '<p class="sd-notice sd-ok">Η δράση ελέγχθηκε' . ( $dep->review_note ? ': ' . esc_html( $dep->review_note ) : '.' ) . '</p>';
		}
		if ( $issues ) {
			echo '<section class="sd-card sd-issues"><h3>Έλεγχος</h3><ul>';
			foreach ( $issues as $i ) {
				echo '<li class="sd-issue-' . esc_attr( 'info' === $i[0] ? 'info' : $i[0] ) . '">' . esc_html( $i[1] ) . '</li>';
			}
			echo '</ul></section>';
		}

		SD_Ai::render_box( $dep, $class );
		if ( $edit ) {
			SD_AiField::mark( 'dep', $dep->id );
		}

		if ( $edit ) {
			SD_Frontend::form_open( 'dep_save' );
			echo '<input type="hidden" name="dep_id" value="' . (int) $dep->id . '">';
		} else {
			echo '<form class="sd-form" onsubmit="return false">';
		}

		/* Α. Στοιχεία */
		echo '<section class="sd-card"><h3>Α. Στοιχεία</h3>';
		echo '<label class="sd-block">Τίτλος δράσης (απλή περιγραφή) <input name="title" value="' . esc_attr( $dep->title ) . '"' . $ro . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label><input type="checkbox" name="title_provisional" value="1"' . checked( $dep->title_provisional, 1, false ) . $ro . '> Ο τίτλος είναι προσωρινός (μέχρι τη Διερεύνηση με τους μαθητές)</label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<fieldset class="sd-sub"><legend>Είδος δράσης</legend><div class="sd-checks">';
		foreach ( self::KINDS as $k => $l ) {
			echo '<label><input type="radio" name="kind" value="' . esc_attr( $k ) . '"' . checked( $dep->kind, $k, false ) . $ro . '> ' . esc_html( $l ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></fieldset>';
		echo '<div class="sd-row"><label><input type="checkbox" name="kepea" value="1"' . checked( $dep->kepea, 1, false ) . $ro . '> Συνεργασία με ΚΕΠΕΑ / εγκεκριμένο πρόγραμμα</label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-grow">Ποιο <input name="kepea_note" value="' . esc_attr( $dep->kepea_note ) . '"' . $ro . '></label></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-block">Έτοιμο πρόγραμμα (προαιρετικό): ΚΕΠΕΑ ή πρόταση της ΔΠΕ Φθιώτιδας <select name="program"' . $ro . '><option value="">—</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( self::programs() as $k => $p ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $dep->program, $k, false ) . '>' . esc_html( $p['label'] ) . '</option>';
		}
		echo '</select></label>';
		$sel = self::programs()[ $dep->program ] ?? null;
		if ( $sel && ! empty( $sel['url'] ) ) {
			echo '<p class="sd-help">Υλικό: <a href="' . esc_url( $sel['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $sel['title'] ) . '</a></p>';
		}
		echo '<p class="sd-help">Με πρόγραμμα ΚΕΠΕΑ σημειώνεται αυτόματα η συνεργασία. Τα προγράμματα με «Τοπ. Ιστορία / Τοπ. Γεωγραφία» μετρούν για την υποχρεωτική δράση, αν οι ώρες δοθούν στο αντίστοιχο μάθημα.</p>';
		echo '<label class="sd-block">Περίοδος υλοποίησης <input name="period" maxlength="100" value="' . esc_attr( $dep->period ) . '" placeholder="π.χ. Φεβρουάριος – Απρίλιος 2027"' . $ro . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-block">Συνεργαζόμενοι/ες εκπαιδευτικοί, τάξεις ή σχολεία<textarea name="partners" rows="2"' . $ro . '>' . esc_textarea( $dep->partners ) . '</textarea></label></section>'; // phpcs:ignore WordPress.Security.EscapeOutput

		/* Β. ΣΒΑ */
		echo '<section class="sd-card"><h3>Β. Θεματικό πεδίο του Προγράμματος Σπουδών (ΣΒΑ)</h3><p class="sd-help">Ένα ή περισσότερα. Καταχωρίζεται στο myschool στο τέλος της χρονιάς.</p>';
		echo $edit ? '' : '<fieldset disabled>';
		self::checks( 'sdgs', self::SDGS, $dep->sdgs );
		echo '<p><strong>Ζήτημα ενεργού πολιτειότητας</strong> (ένα, για το Πρότυπο Ανάπτυξης Δράσεων)</p><div class="sd-checks">';
		foreach ( self::ZITIMATA as $k => $l ) {
			echo '<label><input type="radio" name="zitima" value="' . esc_attr( $k ) . '"' . checked( $dep->zitima, $k, false ) . '> ' . esc_html( $l ) . '</label>';
		}
		echo '</div>';
		echo $edit ? '' : '</fieldset>';
		echo '</section>';

		/* Γ. Σύνδεση */
		$actions = SD_Plans::year_actions( $class->year_id, $class->grade );
		$units   = SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) );
		echo '<section class="sd-card"><h3>Γ. Σύνδεση με τον προγραμματισμό του σχολείου</h3>';
		foreach ( array( 'action_id' => 'Σχέδιο Δράσης – ενέργεια (κύρια)', 'action2_id' => 'Δεύτερη σύνδεση (προαιρετική)' ) as $field => $label ) {
			echo '<label class="sd-block">' . esc_html( $label ) . ' <select name="' . esc_attr( $field ) . '"' . $ro . '><option value="0">Δεν συνδέεται</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( $actions as $row ) {
				list( $p, $g, $a ) = $row;
				$text = trim( $p->code ) . ' › ' . ( $g->title ? $g->title : 'Στόχος ' . $g->ord ) . ' › ' . ( SD_Eval::ACTION_KINDS[ $a->kind ] ?? '' ) . ': ' . wp_html_excerpt( $a->description, 80, '…' );
				echo '<option value="' . (int) $a->id . '"' . selected( (int) $dep->$field, (int) $a->id, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<label class="sd-block">Ενότητα Εργαστηρίων της τάξης (από το Έντυπο 1Α) <select name="f1a_unit"' . $ro . '><option value="0">—</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $units as $n => $u ) {
			$t = SD_Workshops::UNITS[ $n ] . ( $u->cycle ? ' · ' . SD_Workshops::CYCLES[ $u->cycle ] : '' ) . ( $u->title ? ' · ' . $u->title : '' );
			echo '<option value="' . (int) $n . '"' . selected( (int) $dep->f1a_unit, $n, false ) . '>' . esc_html( $t ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="sd-block">Σύντομη περιγραφή (2–3 γραμμές)<textarea name="description" rows="3"' . $ro . '>' . esc_textarea( $dep->description ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-block">Προσδοκώμενα μαθησιακά αποτελέσματα<textarea name="outcomes" rows="4"' . $ro . '>' . esc_textarea( $dep->outcomes ) . '</textarea></label></section>'; // phpcs:ignore WordPress.Security.EscapeOutput

		/* Δ. Κατανομή 10 ωρών */
		$allowed = self::GRADE_SUBJECTS[ (int) $class->grade ] ?? array();
		echo '<section class="sd-card"><h3>Δ. Κατανομή των 10 διδακτικών ωρών</h3>';
		echo '<p class="sd-help">Μαθήματα της εγκυκλίου για την τάξη: ' . esc_html( implode( ', ', array_map( function ( $k ) {
			return SD_Citizen::SUBJECTS[ $k ];
		}, $allowed ) ) ) . '. Αν χρησιμοποιούνται ώρες Εργαστηρίων, χρειάζεται συνεννόηση με τον/την υπεύθυνο/η τους.</p>';
		echo '<table class="sd-table sd-hours" data-sum-target="sum-hours"><thead><tr><th>Μάθημα</th><th>Ενότητα / κεφάλαιο</th><th>Ώρες</th><th>Περίοδος</th></tr></thead><tbody>';
		$rows = $dep->hours_arr;
		$n    = max( 4, count( $rows ) + ( $edit ? 1 : 0 ) );
		for ( $i = 0; $i < $n; $i++ ) {
			$r = $rows[ $i ] ?? array( 'subject' => '', 'unit' => '', 'hours' => '', 'period' => '' );
			echo '<tr><td><select name="rows[' . $i . '][subject]"' . $ro . '><option value="">—</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( self::SUBJECTS as $k => $l ) {
				if ( in_array( $k, $allowed, true ) || 'allo' === $k || $r['subject'] === $k ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['subject'], $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
			}
			echo '</select></td>';
			echo '<td><input name="rows[' . $i . '][unit]" value="' . esc_attr( $r['unit'] ) . '"' . $ro . '></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td><input type="number" min="0" max="10" class="sd-num" data-sum="sum-hours" name="rows[' . $i . '][hours]" value="' . esc_attr( (string) $r['hours'] ) . '"' . $ro . '></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td><input name="rows[' . $i . '][period]" value="' . esc_attr( $r['period'] ) . '"' . $ro . '></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody><tfoot><tr><th colspan="2">Σύνολο</th><th><span id="sum-hours">' . (int) self::hours_total( $dep ) . '</span> / 10</th><th></th></tr></tfoot></table></section>';

		/* Ε. Πεδίο */
		echo '<section class="sd-card"><h3>Ε. Πεδίο υλοποίησης</h3>' . ( $edit ? '' : '<fieldset disabled>' );
		echo '<p><strong>Χώρος</strong></p>';
		self::checks( 'places', self::PLACES, $dep->places );
		echo '<p><strong>Αποδέκτης της δράσης</strong></p>';
		self::checks( 'audience', self::AUDIENCE, $dep->audience );
		echo ( $edit ? '' : '</fieldset>' ) . '</section>';

		/* ΣΤ. Φάσεις */
		echo '<section class="sd-card"><h3>ΣΤ. Φάσεις υλοποίησης</h3><table class="sd-table sd-stages"><thead><tr><th>Φάση</th><th>Ενδεικτικές δραστηριότητες / εργαλεία</th><th>Ώρες</th><th>Πότε</th></tr></thead><tbody>';
		foreach ( self::STAGES as $k => $l ) {
			$s = $dep->stage_arr[ $k ] ?? array( 'activities' => '', 'hours' => '', 'when' => '' );
			echo '<tr><th>' . esc_html( $l ) . '</th><td><textarea rows="2" name="stages[' . esc_attr( $k ) . '][activities]"' . $ro . '>' . esc_textarea( $s['activities'] ) . '</textarea></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td><input type="number" min="0" max="10" class="sd-num" data-sum="sum-stages" name="stages[' . esc_attr( $k ) . '][hours]" value="' . esc_attr( (string) $s['hours'] ) . '"' . $ro . '></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td><input name="stages[' . esc_attr( $k ) . '][when]" value="' . esc_attr( $s['when'] ) . '"' . $ro . '></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody><tfoot><tr><th colspan="2">Σύνολο</th><th><span id="sum-stages">' . (int) self::stages_total( $dep ) . '</span> / 10</th><th></th></tr></tfoot></table></section>';

		/* Ζ. Συνεργασίες */
		echo '<section class="sd-card"><h3>Ζ. Συνεργασίες, επισκέψεις και διάχυση</h3>';
		echo '<label class="sd-block">Εκπαιδευτική επίσκεψη εκτός σχολείου (κενό = δεν προβλέπεται· αλλιώς υποβάλλεται Έντυπο 3)<textarea name="visit" rows="2"' . $ro . '>' . esc_textarea( $dep->visit ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-block">Φορέας / ομιλητής στο σχολείο (κενό = δεν προβλέπεται· αλλιώς Έντυπο 3)<textarea name="visitor" rows="2"' . $ro . '>' . esc_textarea( $dep->visitor ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $edit ? '' : '<fieldset disabled>';
		echo '<p><strong>Τρόπος διάχυσης</strong></p>';
		self::checks( 'diffusion', self::DIFFUSION, $dep->diffusion );
		echo '<label class="sd-block">Άλλο <input name="diffusion_other" value="' . esc_attr( $dep->diffusion_other ) . '"></label>';
		echo '<p><strong>Τεκμήρια</strong></p>';
		self::checks( 'evidence', self::EVIDENCE, $dep->evidence );
		echo $edit ? '' : '</fieldset>';
		echo '</section>';

		echo '<section class="sd-card"><h3>Σημειώσεις από τον βοηθό AI</h3><p class="sd-help">Επικολλήστε εδώ την απάντηση του εργαλείου AI, για να την έχετε δίπλα όσο συμπληρώνετε το έντυπο. Δεν εμφανίζεται στην εκτύπωση.</p>';
		echo '<textarea name="ai_notes" rows="6"' . $ro . '>' . esc_textarea( $dep->ai_notes ) . '</textarea></section>'; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $edit ) {
			echo '<p><button class="sd-button" type="submit">Αποθήκευση εντύπου</button></p>';
		}
		echo '</form>';

		if ( $edit && in_array( $dep->status, array( 'proxeiro', 'epistrafike' ), true ) ) {
			echo '<section class="sd-card"><h3>Υποβολή</h3>';
			if ( self::has_errors( $issues ) ) {
				echo '<p>Η υποβολή γίνεται όταν διορθωθούν οι ελλείψεις του ελέγχου (αποθηκεύστε πρώτα).</p>';
			} else {
				SD_Frontend::form_open( 'dep_submit' );
				echo '<input type="hidden" name="dep_id" value="' . (int) $dep->id . '"><p>Μετά την υποβολή το έντυπο κλειδώνει μέχρι τον έλεγχο.</p><button class="sd-button" type="submit">Υποβολή στη Διεύθυνση</button></form>';
			}
			if ( 'proxeiro' === $dep->status || current_user_can( SD_Roles::CAP_MANAGE ) ) {
				SD_Frontend::form_open( 'dep_delete' );
				echo '<input type="hidden" name="dep_id" value="' . (int) $dep->id . '"><p><button class="sd-button sd-danger sd-small" type="submit" onclick="return confirm(\'Διαγραφή της δράσης;\');">Διαγραφή δράσης</button></p></form>';
			}
			echo '</section>';
		}

		if ( current_user_can( SD_Roles::CAP_REVIEW ) && in_array( $dep->status, array( 'ypovlithike', 'elegxthike' ), true ) ) {
			echo '<section class="sd-card"><h3>Έλεγχος Επιτροπής / Διεύθυνσης</h3>';
			SD_Frontend::form_open( 'dep_review' );
			echo '<input type="hidden" name="dep_id" value="' . (int) $dep->id . '">';
			echo '<label class="sd-block">Σχόλια (υποχρεωτικά για επιστροφή)<textarea name="review_note" rows="3">' . esc_textarea( $dep->review_note ) . '</textarea></label>';
			echo '<p><button class="sd-button" type="submit" name="decision" value="ok">Σημείωση ως ελεγμένο</button> <button class="sd-button sd-danger" type="submit" name="decision" value="return">Επιστροφή για διόρθωση</button></p></form></section>';
		}
	}

	private static function render_print( $id ) {
		$dep   = self::get( $id );
		$class = $dep ? SD_Data::get_class( $dep->class_id ) : null;
		if ( ! $dep || ! $class || ! self::can_view( $class ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτή τη δράση.</p>';
			return;
		}
		$year  = SD_Data::active_year();
		$owner = get_userdata( $dep->owner_id );
		$rows  = array(
			'Τάξη / Τμήμα'                 => SD_Data::class_label( $class ),
			'Υπεύθυνος/η εκπαιδευτικός'    => $owner ? $owner->display_name : '',
			'Συνεργαζόμενοι/ες'            => $dep->partners,
			'Ηλικιακό επίπεδο ΠΣ'          => self::age_group( $class->grade ),
			'Τίτλος δράσης'                => $dep->title . ( $dep->title_provisional ? ' (προσωρινός)' : '' ),
			'Είδος δράσης'                 => self::KINDS[ $dep->kind ] . ( $dep->kepea ? ' · Συνεργασία ΚΕΠΕΑ / πρόγραμμα: ' . $dep->kepea_note : '' ),
			'Θεματικά πεδία (ΣΒΑ)'         => implode( ', ', self::labels( $dep->sdgs, self::SDGS ) ),
			'Σύντομη περιγραφή'            => $dep->description,
			'Προσδοκώμενα αποτελέσματα'    => $dep->outcomes,
			'Χώρος'                        => implode( ', ', self::labels( $dep->places, self::PLACES ) ),
			'Αποδέκτης'                    => implode( ', ', self::labels( $dep->audience, self::AUDIENCE ) ),
			'Επίσκεψη εκτός σχολείου'      => $dep->visit ? $dep->visit : 'Δεν προβλέπεται',
			'Φορέας / ομιλητής στο σχολείο' => $dep->visitor ? $dep->visitor : 'Δεν προβλέπεται',
			'Διάχυση'                      => implode( ', ', array_filter( array_merge( self::labels( $dep->diffusion, self::DIFFUSION ), array( $dep->diffusion_other ) ) ) ),
			'Τεκμήρια'                     => implode( ', ', self::labels( $dep->evidence, self::EVIDENCE ) ),
		);
		echo '<div class="sd-print"><p class="sd-noprint"><button class="sd-button" type="button" onclick="window.print()">Εκτύπωση / Αποθήκευση ως PDF</button> <a class="sd-button" href="' . esc_url( SD_Exports::url( 'f2', $dep->id ) ) . '">Λήψη σε Word</a></p>';
		echo '<p><strong>' . esc_html( SD_Frontend::school_name() ) . '</strong></p><h2>ΕΝΤΥΠΟ 2 – Σχεδιασμός Δράσης Ενεργού Πολίτη</h2><p>Βάσει του Προτύπου της εγκυκλίου 128360/ΓΔ4/28-09-2026 · Σχολικό έτος ' . esc_html( $year ? $year->label : '' ) . '</p>';
		echo '<table class="sd-print-table"><tbody>';
		foreach ( $rows as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . nl2br( esc_html( (string) $v ) ) . '</td></tr>';
		}
		echo '</tbody></table><h3>Κατανομή των 10 διδακτικών ωρών</h3><table class="sd-print-table"><thead><tr><th>Μάθημα</th><th>Ενότητα / κεφάλαιο</th><th>Ώρες</th><th>Περίοδος</th></tr></thead><tbody>';
		foreach ( $dep->hours_arr as $r ) {
			echo '<tr><td>' . esc_html( self::SUBJECTS[ $r['subject'] ] ?? '' ) . '</td><td>' . esc_html( $r['unit'] ) . '</td><td>' . (int) $r['hours'] . '</td><td>' . esc_html( $r['period'] ) . '</td></tr>';
		}
		echo '<tr><th colspan="2">ΣΥΝΟΛΟ</th><th>' . (int) self::hours_total( $dep ) . '</th><td></td></tr></tbody></table>';
		echo '<h3>Φάσεις υλοποίησης</h3><table class="sd-print-table"><thead><tr><th>Φάση</th><th>Δραστηριότητες / εργαλεία</th><th>Ώρες</th><th>Πότε</th></tr></thead><tbody>';
		foreach ( self::STAGES as $k => $l ) {
			$s = $dep->stage_arr[ $k ] ?? array( 'activities' => '', 'hours' => 0, 'when' => '' );
			echo '<tr><th>' . esc_html( $l ) . '</th><td>' . nl2br( esc_html( $s['activities'] ) ) . '</td><td>' . (int) $s['hours'] . '</td><td>' . esc_html( $s['when'] ) . '</td></tr>';
		}
		echo '<tr><th colspan="2">ΣΥΝΟΛΟ</th><th>' . (int) self::stages_total( $dep ) . '</th><td></td></tr></tbody></table>';
		$sub = $dep->submitted_at ? mysql2date( 'd/m/Y', $dep->submitted_at ) : '…… / …… / 20……';
		echo '<p>Ημερομηνία υποβολής: ' . esc_html( $sub ) . '</p><p>Ο/Η εκπαιδευτικός (ονοματεπώνυμο – υπογραφή):</p></div>';
	}

	/** Μπλοκ για το Word: ίδια διάταξη με την εκτυπώσιμη μορφή. */
	public static function doc_blocks( $dep ) {
		$class = SD_Data::get_class( $dep->class_id );
		$year  = SD_Data::active_year();
		$owner = get_userdata( $dep->owner_id );
		$kv    = array(
			array( 'Τάξη / Τμήμα', $class ? SD_Data::class_label( $class ) : '' ),
			array( 'Υπεύθυνος/η εκπαιδευτικός', $owner ? $owner->display_name : '' ),
			array( 'Συνεργαζόμενοι/ες', $dep->partners ),
			array( 'Ηλικιακό επίπεδο ΠΣ', $class ? self::age_group( $class->grade ) : '' ),
			array( 'Τίτλος δράσης', $dep->title . ( $dep->title_provisional ? ' (προσωρινός)' : '' ) ),
			array( 'Είδος δράσης', ( self::KINDS[ $dep->kind ] ?? '' ) . ( $dep->kepea ? ' · Συνεργασία ΚΕΠΕΑ / πρόγραμμα: ' . $dep->kepea_note : '' ) ),
			array( 'Θεματικά πεδία (ΣΒΑ)', implode( ', ', self::labels( $dep->sdgs, self::SDGS ) ) ),
			array( 'Σύντομη περιγραφή', $dep->description ),
			array( 'Προσδοκώμενα αποτελέσματα', $dep->outcomes ),
			array( 'Χώρος', implode( ', ', self::labels( $dep->places, self::PLACES ) ) ),
			array( 'Αποδέκτης', implode( ', ', self::labels( $dep->audience, self::AUDIENCE ) ) ),
			array( 'Επίσκεψη εκτός σχολείου', $dep->visit ? $dep->visit : 'Δεν προβλέπεται' ),
			array( 'Φορέας / ομιλητής στο σχολείο', $dep->visitor ? $dep->visitor : 'Δεν προβλέπεται' ),
			array( 'Διάχυση', implode( ', ', array_filter( array_merge( self::labels( $dep->diffusion, self::DIFFUSION ), array( $dep->diffusion_other ) ) ) ) ),
			array( 'Τεκμήρια', implode( ', ', self::labels( $dep->evidence, self::EVIDENCE ) ) ),
		);
		$hours = array();
		foreach ( $dep->hours_arr as $r ) {
			$hours[] = array( self::SUBJECTS[ $r['subject'] ] ?? '', (string) ( $r['unit'] ?? '' ), (string) (int) ( $r['hours'] ?? 0 ), (string) ( $r['period'] ?? '' ) );
		}
		$hours[] = array( 'ΣΥΝΟΛΟ', '', (string) self::hours_total( $dep ), '' );
		$stages  = array();
		foreach ( self::STAGES as $k => $l ) {
			$st       = $dep->stage_arr[ $k ] ?? array( 'activities' => '', 'hours' => 0, 'when' => '' );
			$stages[] = array( $l, (string) ( $st['activities'] ?? '' ), (string) (int) ( $st['hours'] ?? 0 ), (string) ( $st['when'] ?? '' ) );
		}
		$stages[] = array( 'ΣΥΝΟΛΟ', '', (string) self::stages_total( $dep ), '' );
		return array(
			array( 'p', SD_Frontend::school_name(), true ),
			array( 'title', 'ΕΝΤΥΠΟ 2 – Σχεδιασμός Δράσης Ενεργού Πολίτη' ),
			array( 'p', 'Βάσει του Προτύπου της εγκυκλίου 128360/ΓΔ4/28-09-2026 · Σχολικό έτος ' . ( $year ? $year->label : '' ) ),
			array( 'kv', $kv ),
			array( 'h', 'Κατανομή των 10 διδακτικών ωρών' ),
			array( 'table', array( 'Μάθημα', 'Ενότητα / κεφάλαιο', 'Ώρες', 'Περίοδος' ), $hours, array( 0.3, 0.4, 0.1, 0.2 ) ),
			array( 'h', 'Φάσεις υλοποίησης' ),
			array( 'table', array( 'Φάση', 'Δραστηριότητες / εργαλεία', 'Ώρες', 'Πότε' ), $stages, array( 0.28, 0.47, 0.08, 0.17 ), true ),
			array( 'p', 'Ημερομηνία υποβολής: ' . ( $dep->submitted_at ? mysql2date( 'd/m/Y', $dep->submitted_at ) : '…… / …… / 20……' ) ),
			array( 'p', 'Ο/Η εκπαιδευτικός (ονοματεπώνυμο – υπογραφή):' ),
		);
	}

	/** Μπλοκ για Word στη μορφή του «Προτύπου Ανάπτυξης Δράσεων Ενίσχυσης της Ενεργού Πολιτειότητας». */
	public static function proto_blocks( $dep ) {
		$class  = SD_Data::get_class( $dep->class_id );
		$lessons = array();
		foreach ( $dep->hours_arr as $r ) {
			$lessons[] = ( self::SUBJECTS[ $r['subject'] ] ?? '' ) . ( '' !== (string) ( $r['unit'] ?? '' ) ? ' – ' . $r['unit'] : '' ) . ' (' . (int) ( $r['hours'] ?? 0 ) . ' ώρ.' . ( '' !== (string) ( $r['period'] ?? '' ) ? ', ' . $r['period'] : '' ) . ')';
		}
		if ( $dep->f1a_unit && $class ) {
			$u = SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) )[ (int) $dep->f1a_unit ] ?? null;
			if ( $u && $u->cycle ) {
				$lessons[] = 'Εργαστήρια Δεξιοτήτων: ' . SD_Workshops::CYCLES[ $u->cycle ] . ' – ' . SD_Workshops::subtheme( $class->grade, $u->cycle ) . ( $u->title ? ' («' . $u->title . '»)' : '' ) . ' · ' . SD_Workshops::UNITS[ (int) $dep->f1a_unit ];
			}
		}
		$b = array(
			array( 'p', SD_Frontend::school_name() . ' · Τάξη ' . ( $class ? SD_Data::class_label( $class ) : '' ), true ),
			array( 'title', 'Πρότυπο Ανάπτυξης Δράσεων Ενίσχυσης της Ενεργού Πολιτειότητας' ),
			array(
				'kv',
				array(
					array( '1. Τίτλος Δράσης', $dep->title ),
					array( '2. Επίπεδο', $class ? self::age_group( $class->grade ) : '' ),
					array( '3. Θεματικό Πεδίο', 'Θεματικά πεδία ΠΣ (ΣΒΑ): ' . self::sdg_text( $dep->sdgs ) . "\nΖήτημα ενεργού πολιτειότητας: " . ( self::ZITIMATA[ $dep->zitima ] ?? '' ) ),
					array( '4. Σύντομη περιγραφή της Δράσης', $dep->description ),
					array( '5. Μάθημα ή τομέας του Εργαστηρίου Δεξιοτήτων που εντάσσεται', implode( "\n", $lessons ) ),
					array( '6. Προσδοκώμενα Μαθησιακά Αποτελέσματα', $dep->outcomes ),
					array( '7. Πεδίο Υλοποίησης', implode( ', ', self::labels( $dep->places, self::PLACES ) ) ),
				),
			),
			array( 'h', '8. Φάσεις Υλοποίησης' ),
		);
		$n    = 0;
		$rows = array();
		foreach ( self::STAGES as $k => $l ) {
			$n++;
			$s      = $dep->stage_arr[ $k ] ?? array();
			$name   = preg_replace( '/^\d+\.\s*/u', '', $l );
			$rows[] = array( '8.' . $n . '. ' . $name . ' (' . (int) ( $s['hours'] ?? 0 ) . ' διδ. ώρες)', (string) ( $s['activities'] ?? '' ) . ( '' !== (string) ( $s['when'] ?? '' ) ? "\nΠότε: " . $s['when'] : '' ) );
		}
		$b[] = array( 'kv', $rows );
		return $b;
	}

	/** Για την αρχική: δράσεις των τμημάτων του χρήστη. */
	public static function dashboard_rows( $year ) {
		$out = array();
		foreach ( SD_Data::classes_of_user( get_current_user_id(), $year->id ) as $c ) {
			$out[] = array( $c, self::of_class( $c->id ) );
		}
		return $out;
	}

	public static function status_badge( $status ) {
		return self::badge( $status );
	}
}
