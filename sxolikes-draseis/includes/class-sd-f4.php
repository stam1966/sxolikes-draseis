<?php
/**
 * Έντυπο 4 – Αποτίμηση (Εργαστήρια Δεξιοτήτων και Δράση Ενεργού Πολίτη), ένα ανά τμήμα.
 *
 * Προσυμπληρώνεται από το Έντυπο 1Α (εβδομάδες, παραδοτέα) και το Έντυπο 2
 * (ώρες, στάδια, διάχυση, θεματικά πεδία, σύνδεση με Σχέδια Δράσης).
 * Από εδώ βγαίνουν: η αποτίμηση κάθε ΣΔ για την πλατφόρμα και η λίστα για το myschool.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_F4 {

	const OPT_DEADLINE = 'sd_deadline_f4';

	const STAGES_SHORT = array(
		'dierevnisi'   => 'Διερεύνηση',
		'proetoimasia' => 'Σχεδιασμός',
		'ylopoiisi'    => 'Υλοποίηση',
		'diaxysi'      => 'Διάχυση',
		'anastoxasmos' => 'Αναστοχασμός',
	);

	public static function t() {
		return SD_Data::table( 'f4' );
	}

	public static function deadline() {
		return (string) get_option( self::OPT_DEADLINE, '' );
	}

	public static function form_of_class( $class_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE class_id = %d', $class_id ) );
		return $row ? self::decode( $row ) : null;
	}

	private static function decode( $row ) {
		foreach ( array( 'cycles', 'deps', 'plans' ) as $f ) {
			$v                    = json_decode( (string) $row->$f, true );
			$row->{$f . '_arr'} = is_array( $v ) ? $v : array();
		}
		return $row;
	}

	/** Σχέδια Δράσης με τα οποία συνδέθηκε η τάξη (από 1Α και Έντυπο 2). */
	private static function linked_plans( $class ) {
		$action_plan = array();
		foreach ( SD_Plans::year_actions( $class->year_id ) as $row ) {
			$action_plan[ (int) $row[2]->id ] = (int) $row[0]->id;
		}
		$ids = array();
		foreach ( SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) ) as $u ) {
			if ( $u->action_id && isset( $action_plan[ (int) $u->action_id ] ) ) {
				$ids[ $action_plan[ (int) $u->action_id ] ] = true;
			}
		}
		foreach ( SD_Citizen::of_class( $class->id ) as $d ) {
			foreach ( array( $d->action_id, $d->action2_id ) as $a ) {
				if ( $a && isset( $action_plan[ (int) $a ] ) ) {
					$ids[ $action_plan[ (int) $a ] ] = true;
				}
			}
		}
		return $ids;
	}

	private static function csv_labels( $csv, array $labels ) {
		$out = array();
		foreach ( '' === (string) $csv ? array() : explode( ',', $csv ) as $k ) {
			if ( isset( $labels[ $k ] ) ) {
				$out[] = $labels[ $k ];
			}
		}
		return $out;
	}

	/** Προσυμπλήρωση από 1Α και Έντυπο 2 (μόνο για πεδία που είναι ακόμη κενά). */
	private static function prefill( $class, $f ) {
		$cycles = $f ? $f->cycles_arr : array();
		foreach ( SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) ) as $u ) {
			$c = (int) $u->cycle;
			if ( $c && empty( $cycles[ $c ] ) ) {
				$cycles[ $c ] = array(
					'weeks'        => (int) $u->weeks,
					'deliverables' => (string) $u->deliverable,
					'difficulties' => '',
				);
			}
		}
		$deps = $f ? $f->deps_arr : array();
		foreach ( SD_Citizen::of_class( $class->id ) as $d ) {
			if ( isset( $deps[ $d->id ] ) ) {
				continue;
			}
			$stages = array();
			foreach ( $d->stage_arr as $k => $s ) {
				if ( (int) ( $s['hours'] ?? 0 ) > 0 ) {
					$stages[] = $k;
				}
			}
			$deps[ $d->id ] = array(
				'field'     => implode( ', ', self::csv_labels( $d->sdgs, SD_Citizen::SDGS ) ),
				'hours'     => SD_Citizen::hours_total( $d ),
				'active'    => 0,
				'total'     => 0,
				'stages'    => $stages,
				'impact'    => '',
				'said'      => '',
				'diffusion' => implode( ', ', array_filter( array_merge( self::csv_labels( $d->diffusion, SD_Citizen::DIFFUSION ), array( $d->diffusion_other ) ) ) ),
				'proposals' => '',
			);
		}
		$plans = $f ? $f->plans_arr : array();
		foreach ( array_keys( self::linked_plans( $class ) ) as $pid ) {
			if ( ! isset( $plans[ $pid ] ) ) {
				$plans[ $pid ] = array(
					'on'           => 1,
					'contribution' => '',
					'evidence'     => '',
				);
			}
		}
		return array( $cycles, $deps, $plans );
	}

	public static function can_view( $class ) {
		return SD_Workshops::can_view( $class );
	}

	public static function can_edit( $class, $f ) {
		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return true;
		}
		$status = $f ? $f->status : 'proxeiro';
		return SD_Workshops::is_class_teacher( $class->id ) && in_array( $status, array( 'proxeiro', 'epistrafike' ), true );
	}

	/** Τι λείπει για υποβολή. */
	public static function missing( $class, $f ) {
		$m = array();
		if ( ! $f ) {
			return array( 'όλα τα πεδία' );
		}
		foreach ( SD_Citizen::of_class( $class->id ) as $d ) {
			$x = $f->deps_arr[ $d->id ] ?? array();
			if ( empty( $x['total'] ) ) {
				$m[] = 'μαθητές με ενεργό συμμετοχή («' . $d->title . '»)';
			}
			if ( '' === trim( (string) ( $x['impact'] ?? '' ) ) ) {
				$m[] = 'αντίκτυπος («' . $d->title . '»)';
			}
		}
		return $m;
	}

	/* =================== Ενέργειες =================== */

	private static function clean( $v, $limit = 2000 ) {
		return mb_substr( sanitize_textarea_field( (string) $v ), 0, $limit );
	}

	public static function handle( $do ) {
		global $wpdb;
		if ( ! in_array( $do, array( 'f4_save', 'f4_submit', 'f4_review' ), true ) ) {
			return null;
		}
		$class = SD_Data::get_class( isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0 );
		$list  = SD_Frontend::url( 'apotimisi' );
		if ( ! $class ) {
			return array( new WP_Error( 'sd', 'Το τμήμα δεν βρέθηκε.' ), $list );
		}
		$back = SD_Frontend::url( 'apotimisi', array( 'class' => $class->id ) );
		$f    = self::form_of_class( $class->id );

		if ( 'f4_save' === $do ) {
			if ( ! self::can_edit( $class, $f ) ) {
				return array( new WP_Error( 'sd', 'Δεν μπορείτε να αλλάξετε αυτό το έντυπο.' ), $back );
			}
			$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security
			$cyc  = array();
			foreach ( array_keys( SD_Workshops::CYCLES ) as $c ) {
				$in        = $post['cy'][ $c ] ?? array();
				$cyc[ $c ] = array(
					'weeks'        => min( 40, absint( $in['weeks'] ?? 0 ) ),
					'deliverables' => self::clean( $in['deliverables'] ?? '', 600 ),
					'difficulties' => self::clean( $in['difficulties'] ?? '', 600 ),
				);
			}
			$deps = array();
			foreach ( SD_Citizen::of_class( $class->id ) as $d ) {
				$in     = $post['dep'][ $d->id ] ?? array();
				$total  = min( 99, absint( $in['total'] ?? 0 ) );
				$stages = isset( $in['stages'] ) && is_array( $in['stages'] ) ? array_values( array_intersect( array_keys( self::STAGES_SHORT ), array_map( 'sanitize_key', $in['stages'] ) ) ) : array();
				$deps[ $d->id ] = array(
					'field'     => self::clean( $in['field'] ?? '', 300 ),
					'hours'     => min( 99, absint( $in['hours'] ?? 0 ) ),
					'active'    => min( $total, absint( $in['active'] ?? 0 ) ),
					'total'     => $total,
					'stages'    => $stages,
					'impact'    => self::clean( $in['impact'] ?? '' ),
					'said'      => self::clean( $in['said'] ?? '' ),
					'diffusion' => self::clean( $in['diffusion'] ?? '', 600 ),
					'proposals' => self::clean( $in['proposals'] ?? '' ),
				);
			}
			$plans = array();
			foreach ( SD_Plans::plans( $class->year_id ) as $p ) {
				$in              = $post['pl'][ $p->id ] ?? array();
				$plans[ $p->id ] = array(
					'on'           => empty( $in['on'] ) ? 0 : 1,
					'contribution' => self::clean( $in['contribution'] ?? '' ),
					'evidence'     => self::clean( $in['evidence'] ?? '', 600 ),
				);
			}
			$data = array(
				'cycles'     => wp_json_encode( $cyc, JSON_UNESCAPED_UNICODE ),
				'deps'       => wp_json_encode( $deps, JSON_UNESCAPED_UNICODE ),
				'plans'      => wp_json_encode( $plans, JSON_UNESCAPED_UNICODE ),
				'updated_by' => get_current_user_id(),
				'updated_at' => current_time( 'mysql' ),
			);
			if ( $f ) {
				$wpdb->update( self::t(), $data, array( 'id' => $f->id ) );
			} else {
				$wpdb->insert(
					self::t(),
					$data + array(
						'year_id'     => (int) $class->year_id,
						'class_id'    => (int) $class->id,
						'status'      => 'proxeiro',
						'review_note' => '',
					)
				);
			}
			return array( true, $back );
		}

		if ( 'f4_submit' === $do ) {
			if ( ! $f || ! self::can_edit( $class, $f ) ) {
				return array( new WP_Error( 'sd', 'Αποθηκεύστε πρώτα το έντυπο.' ), $back );
			}
			$missing = self::missing( $class, $f );
			if ( $missing ) {
				return array( new WP_Error( 'sd', 'Συμπληρώστε πρώτα: ' . implode( ', ', $missing ) . '.' ), $back );
			}
			$wpdb->update( self::t(), array( 'status' => 'ypovlithike', 'submitted_by' => get_current_user_id(), 'submitted_at' => current_time( 'mysql' ) ), array( 'id' => $f->id ) );
			SD_Notify::submitted( 'Έντυπο 4 (αποτίμηση) · τμήμα ' . SD_Data::class_label( $class ), $class->id, $back );
			return array( true, $back );
		}

		// f4_review
		if ( ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
			return array( new WP_Error( 'sd', 'Μόνο η Επιτροπή και η Διεύθυνση ελέγχουν τα έντυπα.' ), $back );
		}
		if ( ! $f || ! in_array( $f->status, array( 'ypovlithike', 'elegxthike' ), true ) ) {
			return array( new WP_Error( 'sd', 'Το έντυπο δεν έχει υποβληθεί.' ), $back );
		}
		if ( ! SD_Notify::may_review( $f->submitted_by, $class->id ) ) {
			return array( new WP_Error( 'sd', SD_Notify::why_not( $f->submitted_by, $class->id ) ), $back );
		}
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$note     = self::clean( isset( $_POST['review_note'] ) ? wp_unslash( $_POST['review_note'] ) : '', 1000 ); // phpcs:ignore WordPress.Security
		if ( 'return' === $decision && '' === trim( $note ) ) {
			return array( new WP_Error( 'sd', 'Γράψτε τι χρειάζεται διόρθωση.' ), $back );
		}
		$wpdb->update( self::t(), array( 'status' => 'return' === $decision ? 'epistrafike' : 'elegxthike', 'review_note' => $note, 'reviewed_by' => get_current_user_id(), 'reviewed_at' => current_time( 'mysql' ) ), array( 'id' => $f->id ) );
		SD_Notify::decided( 'Έντυπο 4 (αποτίμηση) · τμήμα ' . SD_Data::class_label( $class ), $class->id, $f->submitted_by, 'return' === $decision, $note, $back );
		return array( true, $back );
	}

	/* =================== Εμφάνιση =================== */

	public static function badge( $f ) {
		$s   = $f ? $f->status : '';
		$cls = array(
			'ypovlithike' => 'sd-badge-info',
			'epistrafike' => 'sd-badge-warn',
			'elegxthike'  => 'sd-badge-ok',
		);
		return '<span class="sd-badge ' . esc_attr( $cls[ $s ] ?? '' ) . '">' . esc_html( $s ? SD_Workshops::STATUS[ $s ] : 'Δεν έχει ξεκινήσει' ) . '</span>';
	}

	public static function render() {
		if ( isset( $_GET['print'] ) ) {
			self::render_print( absint( $_GET['print'] ) );
		} elseif ( isset( $_GET['class'] ) ) {
			self::render_form( absint( $_GET['class'] ) );
		} elseif ( isset( $_GET['sxedio'] ) ) {
			self::render_plan( absint( $_GET['sxedio'] ) );
		} elseif ( isset( $_GET['myschool'] ) ) {
			self::render_myschool();
		} else {
			self::render_list();
		}
	}

	public static function can_view_plan( $plan ) {
		return current_user_can( SD_Roles::CAP_REVIEW ) || ( (int) $plan->coordinator_id && (int) $plan->coordinator_id === get_current_user_id() );
	}

	private static function render_list() {
		$year = SD_Data::active_year();
		echo '<h2>Αποτίμηση · Έντυπο 4</h2>';
		echo '<p class="sd-help">Ένα ανά τμήμα, στο τέλος της χρονιάς. Προσυμπληρώνεται από το Έντυπο 1Α και το Έντυπο 2· συμπληρώνετε τι έγινε στην πράξη.' . ( self::deadline() ? ' Προθεσμία: <strong>' . esc_html( mysql2date( 'd/m/Y', self::deadline() ) ) . '</strong>.' : '' ) . '</p>';
		if ( ! $year ) {
			return;
		}
		$review  = current_user_can( SD_Roles::CAP_REVIEW );
		$classes = $review ? SD_Data::classes( $year->id ) : SD_Data::classes_of_user( get_current_user_id(), $year->id );
		echo '<table class="sd-table"><thead><tr><th>Τμήμα</th><th>Κατάσταση</th><th></th></tr></thead><tbody>';
		foreach ( $classes as $c ) {
			$f = self::form_of_class( $c->id );
			echo '<tr><td><a href="' . esc_url( SD_Frontend::url( 'apotimisi', array( 'class' => $c->id ) ) ) . '">' . esc_html( SD_Data::class_label( $c ) ) . '</a></td><td>' . self::badge( $f ) . '</td><td>' . ( $f ? '<a href="' . esc_url( SD_Exports::url( 'f4', $c->id ) ) . '">Word</a>' : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( ! $classes ) {
			echo '<tr><td colspan="3">Δεν σας έχει αντιστοιχιστεί τμήμα.</td></tr>';
		}
		echo '</tbody></table>';

		$plans = array_filter( SD_Plans::plans( $year->id ), array( __CLASS__, 'can_view_plan' ) );
		if ( $plans || $review ) {
			echo '<section class="sd-card"><h3>Συγκεντρωτικά</h3><ul>';
			foreach ( $plans as $p ) {
				echo '<li><a href="' . esc_url( SD_Frontend::url( 'apotimisi', array( 'sxedio' => $p->id ) ) ) . '">Αποτίμηση ' . esc_html( trim( $p->code . ' ' . $p->title ) ) . '</a> (για την πλατφόρμα)</li>';
			}
			if ( $review ) {
				echo '<li><a href="' . esc_url( SD_Frontend::url( 'apotimisi', array( 'myschool' => 1 ) ) ) . '">Λίστα για το myschool</a> (Δράσεις Ενεργού Πολίτη, ώρες, συμμετοχή)</li>';
			}
			echo '</ul></section>';
		}
	}

	private static function render_form( $class_id ) {
		$class = SD_Data::get_class( $class_id );
		if ( ! $class || ! self::can_view( $class ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτό το τμήμα.</p>';
			return;
		}
		$f                          = self::form_of_class( $class->id );
		list( $cycles, $deps, $pl ) = self::prefill( $class, $f );
		$edit                       = self::can_edit( $class, $f );
		if ( $edit ) {
			SD_AiField::mark( 'f4', $class->id );
		}
		$dis                        = $edit ? '' : ' disabled';

		echo '<p><a href="' . esc_url( SD_Frontend::url( 'apotimisi' ) ) . '">← Όλα τα τμήματα</a>';
		if ( $f ) {
			echo ' · <a href="' . esc_url( SD_Frontend::url( 'apotimisi', array( 'print' => $class->id ) ) ) . '">Εκτυπώσιμη μορφή</a> · <a href="' . esc_url( SD_Exports::url( 'f4', $class->id ) ) . '">Λήψη σε Word</a>';
		}
		echo '</p><h2>Έντυπο 4 · Αποτίμηση · Τμήμα ' . esc_html( SD_Data::class_label( $class ) ) . '</h2><p>' . self::badge( $f ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $f && $f->review_note && in_array( $f->status, array( 'epistrafike', 'elegxthike' ), true ) ) {
			echo '<p class="sd-notice ' . ( 'epistrafike' === $f->status ? 'sd-error' : 'sd-ok' ) . '"><strong>Σχόλια ελέγχου:</strong> ' . esc_html( $f->review_note ) . '</p>';
		}
		if ( ! $f ) {
			echo '<p class="sd-notice sd-ok">Τα πεδία προσυμπληρώθηκαν από το Έντυπο 1Α και το Έντυπο 2. Ελέγξτε τα και πατήστε «Αποθήκευση».</p>';
		}

		echo '<form method="post" class="sd-form">';
		wp_nonce_field( SD_Frontend::NONCE );
		echo '<input type="hidden" name="sd_do" value="f4_save"><input type="hidden" name="class_id" value="' . (int) $class->id . '">';

		echo '<section class="sd-card"><h3>Ι. Εργαστήρια Δεξιοτήτων</h3><table class="sd-table"><thead><tr><th>Θεματική ενότητα</th><th>Εβδομάδες που υλοποιήθηκαν</th><th>Βασικά παραδοτέα</th><th>Δυσκολίες / προτάσεις</th></tr></thead><tbody>';
		foreach ( SD_Workshops::CYCLES as $c => $label ) {
			$x = $cycles[ $c ] ?? array();
			echo '<tr><th>' . esc_html( $label ) . '</th><td><input type="number" min="0" max="40" name="cy[' . (int) $c . '][weeks]" value="' . esc_attr( (string) ( $x['weeks'] ?? '' ) ) . '"' . $dis . ' style="width:5em"></td>';
			echo '<td><textarea rows="2" name="cy[' . (int) $c . '][deliverables]"' . $dis . '>' . esc_textarea( $x['deliverables'] ?? '' ) . '</textarea></td><td><textarea rows="2" name="cy[' . (int) $c . '][difficulties]"' . $dis . '>' . esc_textarea( $x['difficulties'] ?? '' ) . '</textarea></td></tr>';
		}
		echo '</tbody></table></section>';

		$dlist = SD_Citizen::of_class( $class->id );
		echo '<section class="sd-card"><h3>ΙΙ. Δράση Ενεργού Πολίτη</h3>';
		if ( ! $dlist ) {
			echo '<p>Το τμήμα δεν έχει Δράση Ενεργού Πολίτη (Έντυπο 2).</p>';
		}
		foreach ( $dlist as $d ) {
			$x = $deps[ $d->id ] ?? array();
			$n = 'dep[' . (int) $d->id . ']';
			echo '<fieldset class="sd-card"><legend>' . esc_html( $d->title ) . ' · ' . esc_html( SD_Citizen::KINDS[ $d->kind ] ?? '' ) . '</legend>';
			echo '<label class="sd-block">Θεματικό πεδίο ΠΣ (για το myschool) <input name="' . $n . '[field]" value="' . esc_attr( $x['field'] ?? '' ) . '"' . $dis . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<div class="sd-row"><label>Διδακτικές ώρες που υλοποιήθηκαν <input type="number" min="0" max="99" name="' . $n . '[hours]" value="' . esc_attr( (string) ( $x['hours'] ?? '' ) ) . '"' . $dis . ' style="width:5em"> από ' . (int) SD_Citizen::HOURS . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<label>Μαθητές με ενεργό συμμετοχή <input type="number" min="0" max="99" name="' . $n . '[active]" value="' . esc_attr( (string) ( $x['active'] ?? '' ) ) . '"' . $dis . ' style="width:5em"> από <input type="number" min="0" max="99" name="' . $n . '[total]" value="' . esc_attr( (string) ( $x['total'] ?? '' ) ) . '"' . $dis . ' style="width:5em"></label></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<p class="sd-help">Η ονομαστική κατάσταση μαθητών για το myschool επισυνάπτεται εκτός εφαρμογής· η εφαρμογή δεν κρατά ονόματα μαθητών.</p>';
			echo '<fieldset><legend>Στάδια που ολοκληρώθηκαν (κριτήριο 6.3.1)</legend>';
			foreach ( self::STAGES_SHORT as $k => $l ) {
				echo '<label class="sd-inline"><input type="checkbox" name="' . $n . '[stages][]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, (array) ( $x['stages'] ?? array() ), true ), true, false ) . $dis . '> ' . esc_html( $l ) . '</label> '; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</fieldset>';
			foreach ( array( 'impact' => 'Αντίκτυπος στους μαθητές και στην κοινότητα (κριτήριο 6.3.2)', 'said' => 'Τι είπαν οι μαθητές στον αναστοχασμό', 'diffusion' => 'Πώς έγινε η διάχυση', 'proposals' => 'Προτάσεις για το επόμενο έτος (εκπαιδευτικών, μαθητών, γονέων)' ) as $k => $l ) {
				echo '<label class="sd-block">' . esc_html( $l ) . '<textarea rows="2" name="' . $n . '[' . $k . ']"' . $dis . '>' . esc_textarea( $x[ $k ] ?? '' ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</fieldset>';
		}
		echo '</section>';

		echo '<section class="sd-card"><h3>ΙΙΙ. Συμβολή στα Σχέδια Δράσης της σχολικής μονάδας</h3><p class="sd-help">Προεπιλεγμένα είναι τα σχέδια με τα οποία συνδέθηκαν το 1Α ή το Έντυπο 2 της τάξης.</p>';
		foreach ( SD_Plans::plans( $class->year_id ) as $p ) {
			$x = $pl[ $p->id ] ?? array();
			$n = 'pl[' . (int) $p->id . ']';
			echo '<fieldset class="sd-card"><legend><label class="sd-inline"><input type="checkbox" name="' . $n . '[on]" value="1"' . checked( ! empty( $x['on'] ), true, false ) . $dis . '> ' . esc_html( trim( $p->code . ' «' . $p->title . '»' ) ) . '</label></legend>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<label class="sd-block">Τι προσέφερε η τάξη στους στόχους του σχεδίου<textarea rows="2" name="' . $n . '[contribution]"' . $dis . '>' . esc_textarea( $x['contribution'] ?? '' ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<label class="sd-block">Τεκμήρια που διατίθενται στον/στη συντονιστή/τρια<input name="' . $n . '[evidence]" value="' . esc_attr( $x['evidence'] ?? '' ) . '"' . $dis . '></label></fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</section>';
		if ( $edit ) {
			echo '<p><button class="sd-button" type="submit">Αποθήκευση</button></p>';
		}
		echo '</form>';

		if ( $edit && $f ) {
			$missing = self::missing( $class, $f );
			if ( $missing ) {
				echo '<p class="sd-help">Για υποβολή λείπουν: ' . esc_html( implode( ', ', $missing ) ) . '.</p>';
			}
			SD_Frontend::form_open( 'f4_submit' );
			echo '<input type="hidden" name="class_id" value="' . (int) $class->id . '"><p><button class="sd-button" type="submit"' . ( $missing ? ' disabled' : '' ) . '>Υποβολή στη Διεύθυνση</button></p></form>';
		}
		if ( $f && current_user_can( SD_Roles::CAP_REVIEW ) && in_array( $f->status, array( 'ypovlithike', 'elegxthike' ), true ) && SD_Notify::review_gate( $f->submitted_by, $class->id, $f->status ) ) {
			echo '<section class="sd-card"><h3>Έλεγχος Επιτροπής / Διεύθυνσης</h3>';
			SD_Frontend::form_open( 'f4_review' );
			echo '<input type="hidden" name="class_id" value="' . (int) $class->id . '"><label class="sd-block">Σχόλια (υποχρεωτικά για επιστροφή)<textarea name="review_note" rows="2">' . esc_textarea( $f->review_note ) . '</textarea></label>';
			echo '<p><button class="sd-button" type="submit" name="decision" value="ok">Σημείωση ως ελεγμένο</button> <button class="sd-button sd-danger" type="submit" name="decision" value="return">Επιστροφή για διόρθωση</button></p></form></section>';
		}
	}

	/** Μπλοκ για Word και εκτύπωση. */
	public static function doc_blocks( $class ) {
		$f                          = self::form_of_class( $class->id );
		list( $cycles, $deps, $pl ) = self::prefill( $class, $f );
		$year                       = null;
		foreach ( SD_Data::years() as $y ) {
			if ( (int) $y->id === (int) $class->year_id ) {
				$year = $y;
			}
		}
		$teachers = array();
		foreach ( SD_Data::class_teachers( $class->id ) as $t ) {
			$u = get_userdata( $t->user_id );
			if ( $u ) {
				$teachers[ $u->ID ] = $u->display_name;
			}
		}
		$b    = array(
			array( 'p', SD_Frontend::school_name(), true ),
			array( 'title', 'ΕΝΤΥΠΟ 4 – Αποτίμηση' ),
			array( 'p', 'Εργαστήρια Δεξιοτήτων και Δράση Ενεργού Πολίτη · Σχολικό έτος ' . ( $year ? $year->label : '' ) ),
			array(
				'kv',
				array(
					array( 'Τάξη / Τμήμα', SD_Data::class_label( $class ) ),
					array( 'Εκπαιδευτικός / εκπαιδευτικοί', implode( ', ', $teachers ) ),
				),
			),
			array( 'h', 'Ι. Εργαστήρια Δεξιοτήτων' ),
		);
		$rows = array();
		foreach ( SD_Workshops::CYCLES as $c => $label ) {
			$x      = $cycles[ $c ] ?? array();
			$rows[] = array( $label, ! empty( $x['weeks'] ) ? (string) $x['weeks'] : '', $x['deliverables'] ?? '', $x['difficulties'] ?? '' );
		}
		$b[] = array( 'table', array( 'Θεματική ενότητα', 'Εβδομάδες που υλοποιήθηκαν', 'Βασικά παραδοτέα', 'Δυσκολίες / προτάσεις' ), $rows, array( 0.22, 0.18, 0.30, 0.30 ), true );
		$b[] = array( 'h', 'ΙΙ. Δράση Ενεργού Πολίτη' );
		foreach ( SD_Citizen::of_class( $class->id ) as $d ) {
			$x      = $deps[ $d->id ] ?? array();
			$stages = array();
			foreach ( self::STAGES_SHORT as $k => $l ) {
				$stages[] = ( in_array( $k, (array) ( $x['stages'] ?? array() ), true ) ? '☒ ' : '☐ ' ) . $l;
			}
			$b[] = array(
				'kv',
				array(
					array( 'Τίτλος δράσης', $d->title ),
					array( 'Θεματικό πεδίο ΠΣ (για το myschool)', $x['field'] ?? '' ),
					array( 'Είδος', SD_Citizen::KINDS[ $d->kind ] ?? '' ),
					array( 'Διδακτικές ώρες που υλοποιήθηκαν', ( $x['hours'] ?? '' ) . ' από ' . SD_Citizen::HOURS ),
					array( 'Μαθητές με ενεργό συμμετοχή', ( $x['active'] ?? '……' ) . ' από ' . ( $x['total'] ?? '……' ) . ' (επισυνάπτεται κατάσταση μαθητών για το myschool)' ),
					array( 'Στάδια που ολοκληρώθηκαν (κριτήριο 6.3.1)', implode( '   ', $stages ) ),
					array( 'Αντίκτυπος στους μαθητές και στην κοινότητα (κριτήριο 6.3.2)', $x['impact'] ?? '' ),
					array( 'Τι είπαν οι μαθητές στον αναστοχασμό', $x['said'] ?? '' ),
					array( 'Πώς έγινε η διάχυση', $x['diffusion'] ?? '' ),
					array( 'Προτάσεις για το επόμενο έτος (εκπαιδευτικών, μαθητών, γονέων)', $x['proposals'] ?? '' ),
				),
			);
		}
		$b[] = array( 'h', 'ΙΙΙ. Συμβολή στα Σχέδια Δράσης της σχολικής μονάδας' );
		foreach ( SD_Plans::plans( $class->year_id ) as $p ) {
			$x = $pl[ $p->id ] ?? array();
			if ( empty( $x['on'] ) ) {
				continue;
			}
			$b[] = array(
				'kv',
				array(
					array( 'Σχέδιο Δράσης', trim( $p->code . ' «' . $p->title . '»' ) ),
					array( 'Τι προσέφερε η τάξη στους στόχους του σχεδίου', $x['contribution'] ?? '' ),
					array( 'Τεκμήρια που διατίθενται στον/στη συντονιστή/τρια', $x['evidence'] ?? '' ),
				),
			);
		}
		$b[] = array( 'p', 'Ημερομηνία υποβολής: ' . ( $f && $f->submitted_at ? mysql2date( 'd/m/Y', $f->submitted_at ) : '…… / …… / 20……' ) );
		$b[] = array( 'p', 'Ο/Η εκπαιδευτικός (ονοματεπώνυμο – υπογραφή):' );
		return $b;
	}

	private static function render_print( $class_id ) {
		$class = SD_Data::get_class( $class_id );
		if ( ! $class || ! self::can_view( $class ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτό το τμήμα.</p>';
			return;
		}
		echo '<div class="sd-print"><p class="sd-noprint"><button class="sd-button" type="button" onclick="window.print()">Εκτύπωση / Αποθήκευση ως PDF</button> <a class="sd-button" href="' . esc_url( SD_Exports::url( 'f4', $class->id ) ) . '">Λήψη σε Word</a></p>';
		SD_Exports::print_blocks( self::doc_blocks( $class ) );
		echo '</div>';
	}

	/** Συμβολή όλων των τάξεων σε ένα ΣΔ (για την αποτίμηση στην πλατφόρμα). */
	public static function plan_rows( $plan ) {
		$rows = array();
		foreach ( SD_Data::classes( $plan->year_id ) as $c ) {
			$f = self::form_of_class( $c->id );
			$x = $f ? ( $f->plans_arr[ $plan->id ] ?? array() ) : array();
			if ( ! empty( $x['on'] ) ) {
				$rows[] = array( SD_Data::class_label( $c ), $x['contribution'] ?? '', $x['evidence'] ?? '', $f ? SD_Workshops::STATUS[ $f->status ] : '' );
			}
		}
		return $rows;
	}

	private static function render_plan( $plan_id ) {
		$plan = SD_Plans::get_plan( $plan_id );
		if ( ! $plan || ! self::can_view_plan( $plan ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση.</p>';
			return;
		}
		echo '<p class="sd-noprint"><a href="' . esc_url( SD_Frontend::url( 'apotimisi' ) ) . '">← Αποτίμηση</a> · <a href="' . esc_url( SD_Exports::url( 'sd', $plan->id ) ) . '">Λήψη σε Word</a></p>';
		echo '<h2>Αποτίμηση ' . esc_html( trim( $plan->code . ' «' . $plan->title . '»' ) ) . '</h2><p class="sd-help">Η συμβολή των τάξεων από τα Έντυπα 4. Βοηθά τον/τη συντονιστή/τρια να γράψει την αποτίμηση στην πλατφόρμα.</p>';
		$rows = self::plan_rows( $plan );
		if ( ! $rows ) {
			echo '<p>Καμία τάξη δεν έχει δηλώσει ακόμη συμβολή σε αυτό το σχέδιο.</p>';
			return;
		}
		echo '<table class="sd-table"><thead><tr><th>Τμήμα</th><th>Τι προσέφερε η τάξη</th><th>Τεκμήρια</th><th>Έντυπο 4</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r[0] ) . '</td><td>' . nl2br( esc_html( $r[1] ) ) . '</td><td>' . esc_html( $r[2] ) . '</td><td>' . esc_html( $r[3] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Δράσεις Ενεργού Πολίτη για το myschool. */
	public static function myschool_rows( $year_id ) {
		$rows = array( array( 'Τμήμα', 'Τίτλος δράσης', 'Είδος', 'Θεματικό πεδίο ΠΣ', 'Ώρες που υλοποιήθηκαν', 'Μαθητές με ενεργό συμμετοχή', 'Σύνολο μαθητών', 'Στάδια που ολοκληρώθηκαν', 'Κατάσταση Εντύπου 4' ) );
		foreach ( SD_Data::classes( $year_id ) as $c ) {
			$f = self::form_of_class( $c->id );
			foreach ( SD_Citizen::of_class( $c->id ) as $d ) {
				$x      = $f ? ( $f->deps_arr[ $d->id ] ?? array() ) : array();
				$st     = array();
				foreach ( (array) ( $x['stages'] ?? array() ) as $k ) {
					$st[] = self::STAGES_SHORT[ $k ] ?? $k;
				}
				$rows[] = array( SD_Data::class_label( $c ), $d->title, SD_Citizen::KINDS[ $d->kind ] ?? '', $x['field'] ?? '', (int) ( $x['hours'] ?? 0 ), (int) ( $x['active'] ?? 0 ), (int) ( $x['total'] ?? 0 ), implode( ', ', $st ), $f ? SD_Workshops::STATUS[ $f->status ] : 'Δεν έχει ξεκινήσει' );
			}
		}
		return $rows;
	}

	private static function render_myschool() {
		$year = SD_Data::active_year();
		if ( ! $year || ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση.</p>';
			return;
		}
		echo '<p class="sd-noprint"><a href="' . esc_url( SD_Frontend::url( 'apotimisi' ) ) . '">← Αποτίμηση</a> · <a href="' . esc_url( SD_Exports::url( 'xlsx', 0 ) ) . '">Όλοι οι πίνακες σε Excel</a></p>';
		echo '<h2>Για το myschool · ' . esc_html( $year->label ) . '</h2><p class="sd-help">Συμμετοχή στις Δράσεις Ενεργού Πολίτη (παρ. 4.2 της εγκυκλίου). Οι ονομαστικές καταστάσεις μαθητών μένουν στο σχολείο, εκτός εφαρμογής.</p>';
		$rows = self::myschool_rows( $year->id );
		$head = array_shift( $rows );
		echo '<table class="sd-table"><thead><tr>';
		foreach ( $head as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			foreach ( $r as $v ) {
				echo '<td>' . esc_html( (string) $v ) . '</td>';
			}
			echo '</tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="' . count( $head ) . '">Δεν υπάρχουν ακόμη Δράσεις Ενεργού Πολίτη.</td></tr>';
		}
		echo '</tbody></table>';
	}
}
