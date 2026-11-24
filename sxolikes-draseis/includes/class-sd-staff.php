<?php
/**
 * Οι εκπαιδευτικοί του σχολείου: και όσοι/ες δεν έχουν λογαριασμό στην εφαρμογή
 * (συμμετέχουν συμπληρωματικά σε Εργαστήρια και Σχέδια Δράσης).
 * Καταχωρίζονται μία φορά από τη Διεύθυνση και επιλέγονται από λίστα.
 * Όποιος/α φεύγει από το σχολείο γίνεται «ανενεργός/ή» (δεν διαγράφεται)
 * και επανέρχεται με ένα κλικ, αν ξαναέρθει.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Staff {

	const OPT       = 'sd_staff';
	const OPT_EXTRA = 'sd_plan_extra';

	const SPECS = array( 'ΠΕ70', 'ΠΕ60', 'ΠΕ06', 'ΠΕ11', 'ΠΕ79', 'ΠΕ86', 'ΠΕ08', 'ΠΕ91', 'ΠΕ05', 'ΠΕ07', 'ΠΕ32', 'ΠΕ21', 'ΠΕ23', 'ΠΕ30', 'ΠΕ25', 'ΔΕ1' );

	/** [id => ['name','spec','active']]. */
	public static function all() {
		$v = get_option( self::OPT, array() );
		$v = is_array( $v ) ? $v : array();
		uasort(
			$v,
			function ( $a, $b ) {
				return strcmp( remove_accents( mb_strtolower( $a['name'] ) ), remove_accents( mb_strtolower( $b['name'] ) ) );
			}
		);
		return $v;
	}

	private static function save_all( $list ) {
		update_option( self::OPT, $list, false );
	}

	public static function line( $p ) {
		return trim( $p['name'] . ( $p['spec'] ? ', ' . $p['spec'] : '' ) );
	}

	private static function key( $name ) {
		return remove_accents( mb_strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $name ) ) ) );
	}

	/**
	 * Λίστα για επιλογή: οι ενεργοί/ές της λίστας και οι ενεργοί λογαριασμοί που
	 * δεν είναι ήδη στη λίστα. [κλειδί ονόματος => ['name','spec','line']].
	 */
	public static function choices( $include_inactive = array() ) {
		$out = array();
		foreach ( self::all() as $p ) {
			$k = self::key( $p['name'] );
			if ( $p['active'] || in_array( $k, $include_inactive, true ) ) {
				$out[ $k ] = array(
					'name'   => $p['name'],
					'spec'   => $p['spec'],
					'line'   => self::line( $p ),
					'active' => (bool) $p['active'],
				);
			}
		}
		foreach ( SD_Roles::app_users() as $u ) {
			// Μόνο λογαριασμοί με ρόλο της εφαρμογής (όχι ο τεχνικός διαχειριστής).
			if ( ! array_intersect( array_keys( SD_Roles::definitions() ), (array) $u->roles ) ) {
				continue;
			}
			$k = self::key( $u->display_name );
			if ( ! isset( $out[ $k ] ) && ! self::inactive_name( $k ) ) {
				$out[ $k ] = array(
					'name'   => $u->display_name,
					'spec'   => '',
					'line'   => $u->display_name,
					'active' => true,
				);
			}
		}
		uasort(
			$out,
			function ( $a, $b ) {
				return strcmp( remove_accents( mb_strtolower( $a['name'] ) ), remove_accents( mb_strtolower( $b['name'] ) ) );
			}
		);
		return $out;
	}

	private static function inactive_name( $k ) {
		foreach ( self::all() as $p ) {
			if ( ! $p['active'] && self::key( $p['name'] ) === $k ) {
				return true;
			}
		}
		return false;
	}

	/** Το όνομα μιας γραμμής «Ονοματεπώνυμο, ΠΕ86». */
	private static function name_of_line( $line ) {
		$parts = explode( ',', (string) $line, 2 );
		return self::key( $parts[0] );
	}

	/**
	 * Επιλογή εκπαιδευτικών με κουμπιά + «άλλος/η εκτός λίστας».
	 * $base: το όνομα πεδίου (π.χ. "teachers" ή "cy[2][teachers]"), $lines: οι αποθηκευμένες γραμμές.
	 * Στέλνει "{$base}_pick[]" και "{$base}_other"· τα ενώνει το combine().
	 */
	public static function picker( $base, $lines, $exclude_names = array(), $dis = '' ) {
		$saved    = SD_ErgPlan::extra_teachers( $lines );
		$keys     = array_map( array( __CLASS__, 'name_of_line' ), $saved );
		$choices  = self::choices( $keys );
		$excl     = array_map( array( __CLASS__, 'key' ), $exclude_names );
		$other    = array();
		$pick     = self::field_name( $base, 'pick' ) . '[]';
		foreach ( $saved as $i => $l ) {
			if ( ! isset( $choices[ $keys[ $i ] ] ) ) {
				$other[] = $l;
			}
		}
		$h = '<div class="sd-staff-pick"><div class="sd-chips">';
		$n = 0;
		foreach ( $choices as $k => $c ) {
			if ( in_array( $k, $excl, true ) ) {
				continue;
			}
			$on = in_array( $k, $keys, true );
			if ( ! $c['active'] && ! $on ) {
				continue;
			}
			$n++;
			$h .= '<label class="sd-chip"><input type="checkbox" name="' . esc_attr( $pick ) . '" value="' . esc_attr( $c['line'] ) . '"' . ( $on ? ' checked' : '' ) . $dis . '><span>' . esc_html( $c['name'] ) . ( $c['spec'] ? ' <small>' . esc_html( $c['spec'] ) . '</small>' : '' ) . ( $c['active'] ? '' : ' <small>(ανενεργός/ή)</small>' ) . '</span></label>';
		}
		$h .= '</div>';
		if ( ! $n ) {
			$h .= '<p class="sd-help">Η λίστα εκπαιδευτικών είναι κενή. Η Διεύθυνση τη συμπληρώνει από Διαχείριση → Εκπαιδευτικοί.</p>';
		}
		$h .= '<input type="hidden" name="' . esc_attr( self::field_name( $base, 'sent' ) ) . '" value="1">';
		$h .= '<label class="sd-staff-other">Άλλος/η, εκτός λίστας (ένας/μία ανά γραμμή, «Ονοματεπώνυμο, ειδικότητα»)<textarea rows="2" data-noai name="' . esc_attr( self::field_name( $base, 'other' ) ) . '"' . $dis . '>' . esc_textarea( implode( "\n", $other ) ) . '</textarea></label></div>';
		return $h;
	}

	/** "teachers" → "teachers_pick"· "cy[2][teachers]" → "cy[2][teachers_pick]". */
	public static function field_name( $base, $suffix ) {
		if ( substr( $base, -1 ) === ']' ) {
			return substr( $base, 0, -1 ) . '_' . $suffix . ']';
		}
		return $base . '_' . $suffix;
	}

	/** Ένωση επιλογών + «εκτός λίστας» σε γραμμές κειμένου. Null αν δεν στάλθηκε ο επιλογέας. */
	public static function combine( $arr, $key ) {
		if ( empty( $arr[ $key . '_sent' ] ) ) {
			return null;
		}
		$pick  = isset( $arr[ $key . '_pick' ] ) && is_array( $arr[ $key . '_pick' ] ) ? $arr[ $key . '_pick' ] : array();
		$other = preg_split( '/\R/u', (string) ( $arr[ $key . '_other' ] ?? '' ) );
		$lines = array();
		foreach ( array_merge( $pick, $other ) as $l ) {
			$l = trim( sanitize_text_field( (string) $l ) );
			if ( '' !== $l && ! in_array( self::name_of_line( $l ), array_map( array( __CLASS__, 'name_of_line' ), $lines ), true ) ) {
				$lines[] = $l;
			}
		}
		return implode( "\n", $lines );
	}

	/* =================== Σχέδια Δράσης: μέλη χωρίς λογαριασμό =================== */

	public static function plan_extra( $plan_id ) {
		$v = get_option( self::OPT_EXTRA, array() );
		return is_array( $v ) && isset( $v[ (int) $plan_id ] ) && is_array( $v[ (int) $plan_id ] ) ? $v[ (int) $plan_id ] : array();
	}

	public static function set_plan_extra( $plan_id, array $lines ) {
		$v = get_option( self::OPT_EXTRA, array() );
		$v = is_array( $v ) ? $v : array();
		$v[ (int) $plan_id ] = array_values( array_filter( array_map( 'sanitize_text_field', $lines ) ) );
		update_option( self::OPT_EXTRA, $v, false );
	}

	/** Επιλογέας μελών χωρίς λογαριασμό για την ομάδα ενός Σχεδίου Δράσης. */
	public static function plan_picker( $plan_id ) {
		$saved = self::plan_extra( $plan_id );
		$keys  = array_map( array( __CLASS__, 'name_of_line' ), $saved );
		$h     = '';
		$n     = 0;
		foreach ( self::all() as $p ) {
			$k  = self::key( $p['name'] );
			$on = in_array( $k, $keys, true );
			if ( ! $p['active'] && ! $on ) {
				continue;
			}
			// Όσοι/ες έχουν λογαριασμό επιλέγονται ήδη στα «Μέλη».
			$has_account = false;
			foreach ( SD_Roles::app_users() as $u ) {
				$has_account = $has_account || self::key( $u->display_name ) === $k;
			}
			if ( $has_account ) {
				continue;
			}
			$n++;
			$h .= '<label class="sd-chip"><input type="checkbox" name="extra[]" value="' . esc_attr( self::line( $p ) ) . '"' . ( $on ? ' checked' : '' ) . '><span>' . esc_html( $p['name'] ) . ( $p['spec'] ? ' <small>' . esc_html( $p['spec'] ) . '</small>' : '' ) . '</span></label>';
		}
		if ( ! $n ) {
			return '';
		}
		return '<fieldset class="sd-sub"><legend>Μέλη χωρίς λογαριασμό (από τη λίστα «Εκπαιδευτικοί»)</legend><input type="hidden" name="extra_sent" value="1"><div class="sd-chips">' . $h . '</div></fieldset>';
	}

	/* =================== Ενέργειες =================== */

	public static function handle( $do ) {
		if ( ! in_array( $do, array( 'staff_add', 'staff_toggle', 'staff_edit', 'staff_delete', 'staff_import' ), true ) ) {
			return null;
		}
		$back = SD_Frontend::url( 'ekpaideftikoi' );
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση διαχειρίζεται τη λίστα εκπαιδευτικών.' ), $back );
		}
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security
		$list = self::all();
		$id   = sanitize_key( (string) ( $post['staff_id'] ?? '' ) );
		foreach ( array_keys( $list ) as $k ) {
			if ( strtolower( (string) $k ) === $id ) {
				$id = $k; // παλιά κλειδιά με κεφαλαία
			}
		}
		$spec = sanitize_text_field( (string) ( $post['spec'] ?? '' ) );
		if ( 'allo' === $spec ) {
			$spec = sanitize_text_field( (string) ( $post['spec_other'] ?? '' ) );
		}
		$spec = mb_substr( $spec, 0, 40 );

		switch ( $do ) {
			case 'staff_add':
				$names = array_filter( array_map( 'trim', preg_split( '/\R/u', sanitize_textarea_field( (string) ( $post['names'] ?? '' ) ) ) ) );
				if ( ! $names ) {
					return array( new WP_Error( 'sd', 'Γράψτε τουλάχιστον ένα ονοματεπώνυμο.' ), $back );
				}
				$added = 0;
				foreach ( $names as $name ) {
					$name = mb_substr( preg_replace( '/\s+/u', ' ', $name ), 0, 120 );
					$dup  = false;
					foreach ( $list as $p ) {
						$dup = $dup || self::key( $p['name'] ) === self::key( $name );
					}
					if ( ! $dup ) {
						$list[ 's' . strtolower( wp_generate_password( 10, false, false ) ) ] = array( 'name' => $name, 'spec' => $spec, 'active' => true );
						$added++;
					}
				}
				self::save_all( $list );
				set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', $added ? 'Προστέθηκαν ' . $added . '.' : 'Υπήρχαν ήδη στη λίστα.' ), 60 );
				return array( true, $back );

			case 'staff_import':
				$added = 0;
				foreach ( SD_Users::listed() as $u ) {
					$dup = false;
					foreach ( $list as $p ) {
						$dup = $dup || self::key( $p['name'] ) === self::key( $u->display_name );
					}
					if ( ! $dup ) {
						$list[ 's' . strtolower( wp_generate_password( 10, false, false ) ) ] = array( 'name' => $u->display_name, 'spec' => '', 'active' => (bool) array_intersect( array_keys( SD_Roles::definitions() ), (array) $u->roles ) );
						$added++;
					}
				}
				self::save_all( $list );
				set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', 'Προστέθηκαν ' . $added . ' από τους λογαριασμούς. Συμπληρώστε τις ειδικότητες.' ), 60 );
				return array( true, $back );
		}

		if ( ! isset( $list[ $id ] ) ) {
			return array( new WP_Error( 'sd', 'Ο/Η εκπαιδευτικός δεν βρέθηκε.' ), $back );
		}
		if ( 'staff_toggle' === $do ) {
			$list[ $id ]['active'] = ! $list[ $id ]['active'];
			self::save_all( $list );
			set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', $list[ $id ]['name'] . ( $list[ $id ]['active'] ? ': ενεργός/ή ξανά.' : ': ανενεργός/ή. Μένει στη λίστα και ενεργοποιείται όποτε χρειαστεί.' ) ), 60 );
		} elseif ( 'staff_edit' === $do ) {
			$name = mb_substr( preg_replace( '/\s+/u', ' ', trim( sanitize_text_field( (string) ( $post['name'] ?? '' ) ) ) ), 0, 120 );
			if ( '' !== $name ) {
				$list[ $id ]['name'] = $name;
			}
			$list[ $id ]['spec'] = $spec;
			self::save_all( $list );
		} elseif ( 'staff_delete' === $do ) {
			unset( $list[ $id ] );
			self::save_all( $list );
		}
		return array( true, $back );
	}

	/* =================== Οθόνη =================== */

	private static function spec_select( $current = '' ) {
		$h     = '<select name="spec"><option value="">—</option>';
		$known = in_array( $current, self::SPECS, true ) || '' === $current;
		foreach ( self::SPECS as $s ) {
			$h .= '<option value="' . esc_attr( $s ) . '"' . selected( $current, $s, false ) . '>' . esc_html( $s ) . '</option>';
		}
		$h .= '<option value="allo"' . ( $known ? '' : ' selected' ) . '>Άλλη…</option></select>';
		$h .= ' <input name="spec_other" size="10" placeholder="π.χ. ΠΕ33" value="' . esc_attr( $known ? '' : $current ) . '">';
		return $h;
	}

	public static function render() {
		$list   = self::all();
		$active = array_filter(
			$list,
			function ( $p ) {
				return $p['active'];
			}
		);
		echo '<h2>Εκπαιδευτικοί του σχολείου</h2>';
		echo '<p class="sd-lead">Όλοι/ες οι εκπαιδευτικοί, και όσοι/ες δεν έχουν λογαριασμό. Από αυτή τη λίστα επιλέγονται όσοι/ες συμμετέχουν στα Εργαστήρια και στα Σχέδια Δράσης.</p>';
		echo '<p class="sd-help">Όποιος/α φεύγει από το σχολείο: «Απενεργοποίηση». Δεν χάνεται τίποτα· αν ξαναέρθει, «Ενεργοποίηση». Οι λογαριασμοί σύνδεσης ενεργοποιούνται και απενεργοποιούνται χωριστά, από <a href="' . esc_url( SD_Frontend::url( 'xristes' ) ) . '">Χρήστες</a>.</p>';

		echo '<section class="sd-card"><h3>Προσθήκη</h3>';
		SD_Frontend::form_open( 'staff_add' );
		echo '<label class="sd-block">Ονοματεπώνυμο (ένα ή περισσότερα, ένα σε κάθε γραμμή)<textarea name="names" rows="3" data-noai placeholder="π.χ. Παπαδοπούλου Ελένη"></textarea></label>';
		echo '<div class="sd-row"><label>Ειδικότητα ' . self::spec_select() . '</label><button class="sd-button" type="submit">Προσθήκη</button></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
		SD_Frontend::form_open( 'staff_import' );
		echo '<p class="sd-help" style="margin-top:1rem">Ή πάρτε με ένα κλικ όσους/ες έχουν ήδη λογαριασμό: <button class="sd-button sd-small sd-quiet" type="submit">Προσθήκη από τους λογαριασμούς</button></p></form></section>';

		echo '<section class="sd-card"><h3>Λίστα · ενεργοί/ές ' . count( $active ) . ' από ' . count( $list ) . '</h3>';
		if ( ! $list ) {
			echo '<p>Η λίστα είναι κενή.</p></section>';
			return;
		}
		echo '<div class="sd-table-wrap"><table class="sd-table"><thead><tr><th>Ονοματεπώνυμο</th><th>Ειδικότητα</th><th>Κατάσταση</th><th></th></tr></thead><tbody>';
		foreach ( $list as $id => $p ) {
			echo '<tr' . ( $p['active'] ? '' : ' class="sd-muted"' ) . '><td><strong>' . esc_html( $p['name'] ) . '</strong></td><td>' . esc_html( $p['spec'] ? $p['spec'] : '—' ) . '</td>';
			echo '<td>' . ( $p['active'] ? '<span class="sd-badge sd-badge-ok">Ενεργός/ή</span>' : '<span class="sd-badge sd-badge-warn">Ανενεργός/ή</span>' ) . '</td><td>';
			SD_Frontend::form_open( 'staff_toggle', ' style="display:inline"' );
			echo '<input type="hidden" name="staff_id" value="' . esc_attr( $id ) . '"><button class="sd-button sd-small' . ( $p['active'] ? ' sd-quiet' : '' ) . '" type="submit">' . ( $p['active'] ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ) . '</button></form> ';
			echo '<details class="sd-inline-edit"><summary>Αλλαγή</summary>';
			SD_Frontend::form_open( 'staff_edit' );
			echo '<input type="hidden" name="staff_id" value="' . esc_attr( $id ) . '"><div class="sd-row"><label>Ονοματεπώνυμο <input name="name" value="' . esc_attr( $p['name'] ) . '"></label><label>Ειδικότητα ' . self::spec_select( $p['spec'] ) . '</label><button class="sd-button sd-small" type="submit">Αποθήκευση</button></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
			SD_Frontend::form_open( 'staff_delete' );
			echo '<input type="hidden" name="staff_id" value="' . esc_attr( $id ) . '"><p class="sd-help">Διαγραφή μόνο για λάθος καταχώριση· για όποιον/α φεύγει, «Απενεργοποίηση». <button class="sd-button sd-small sd-danger" type="submit" onclick="return confirm(\'Οριστική διαγραφή από τη λίστα;\');">Διαγραφή</button></p></form></details>';
			echo '</td></tr>';
		}
		echo '</tbody></table></div></section>';
	}
}
