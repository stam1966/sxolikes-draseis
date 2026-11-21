<?php
/**
 * Χρήστες της εφαρμογής: η Διεύθυνση δημιουργεί λογαριασμούς συναδέλφων,
 * αλλάζει ρόλο και απενεργοποιεί όσους φεύγουν, χωρίς τη διαχείριση του WordPress.
 *
 * Ο κωδικός δεν περνά ποτέ από τη Διεύθυνση: ο/η συνάδελφος λαμβάνει email
 * με σύνδεσμο και τον ορίζει μόνος/η του/της. Λογαριασμοί διαχειριστών του
 * WordPress δεν αλλάζουν από εδώ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Users {

	const META_FORMER = 'sd_former_role';

	public static function roles() {
		$out = array();
		foreach ( SD_Roles::definitions() as $k => $d ) {
			$out[ $k ] = $d['label'];
		}
		return $out;
	}

	private static function app_role( $user ) {
		foreach ( (array) $user->roles as $r ) {
			if ( isset( self::roles()[ $r ] ) ) {
				return $r;
			}
		}
		return '';
	}

	/** Λογαριασμοί που διαχειρίζεται η Διεύθυνση: όχι διαχειριστές, όχι ο εαυτός της. */
	private static function manageable( $user ) {
		return $user && ! user_can( $user, 'manage_options' ) && (int) $user->ID !== get_current_user_id();
	}

	/** Όλοι οι χρήστες με ρόλο της εφαρμογής ή απενεργοποιημένοι από αυτήν. */
	public static function listed() {
		$active   = get_users( array( 'role__in' => array_keys( self::roles() ) ) );
		$disabled = get_users( array( 'meta_key' => self::META_FORMER ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$all      = array();
		foreach ( array_merge( $active, $disabled ) as $u ) {
			$all[ $u->ID ] = $u;
		}
		uasort(
			$all,
			function ( $a, $b ) {
				return strcmp( remove_accents( $a->display_name ), remove_accents( $b->display_name ) );
			}
		);
		return $all;
	}

	/* =================== Ενέργειες =================== */

	public static function handle( $do ) {
		if ( ! in_array( $do, array( 'user_create', 'user_role', 'user_disable', 'user_enable', 'user_reset' ), true ) ) {
			return null;
		}
		$back = SD_Frontend::url( 'xristes' );
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση διαχειρίζεται λογαριασμούς.' ), $back );
		}

		if ( 'user_create' === $do ) {
			$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
			$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$role  = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
			if ( '' === $first || '' === $last ) {
				return array( new WP_Error( 'sd', 'Γράψτε όνομα και επώνυμο.' ), $back );
			}
			if ( ! is_email( $email ) ) {
				return array( new WP_Error( 'sd', 'Το email δεν είναι έγκυρο.' ), $back );
			}
			if ( email_exists( $email ) ) {
				return array( new WP_Error( 'sd', 'Υπάρχει ήδη λογαριασμός με αυτό το email.' ), $back );
			}
			if ( ! isset( self::roles()[ $role ] ) ) {
				return array( new WP_Error( 'sd', 'Διαλέξτε ρόλο.' ), $back );
			}
			$base  = sanitize_user( strtolower( strstr( $email, '@', true ) ), true );
			$base  = $base ? $base : 'xristis';
			$login = $base;
			for ( $i = 2; username_exists( $login ); $i++ ) {
				$login = $base . $i;
			}
			$id = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 24, true, true ),
					'first_name'   => $first,
					'last_name'    => $last,
					'display_name' => $first . ' ' . $last,
					'role'         => $role,
				)
			);
			if ( is_wp_error( $id ) ) {
				return array( new WP_Error( 'sd', 'Ο λογαριασμός δεν δημιουργήθηκε: ' . $id->get_error_message() ), $back );
			}
			// Email με σύνδεσμο για να ορίσει ο/η συνάδελφος τον κωδικό του/της.
			$sent = retrieve_password( $login );
			if ( is_wp_error( $sent ) ) {
				set_transient( 'sd_msg_' . get_current_user_id(), array( 'error', 'Δημιουργήθηκε ο λογαριασμός «' . $login . '». ' . self::link_fallback( get_userdata( $id ) ) ), 300 );
			} else {
				set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', 'Δημιουργήθηκε ο λογαριασμός «' . $login . '». Στάλθηκε email στο ' . $email . ' με σύνδεσμο για τον κωδικό.' ), 60 );
			}
			return array( true, $back );
		}

		$user = get_userdata( isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0 );
		if ( ! self::manageable( $user ) ) {
			return array( new WP_Error( 'sd', 'Αυτός ο λογαριασμός δεν αλλάζει από εδώ.' ), $back );
		}

		if ( 'user_role' === $do ) {
			$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
			if ( ! isset( self::roles()[ $role ] ) || ! self::app_role( $user ) ) {
				return array( new WP_Error( 'sd', 'Μη έγκυρος ρόλος.' ), $back );
			}
			$user->set_role( $role );
			return array( true, $back );
		}

		if ( 'user_disable' === $do ) {
			$role = self::app_role( $user );
			if ( ! $role ) {
				return array( new WP_Error( 'sd', 'Ο λογαριασμός είναι ήδη ανενεργός.' ), $back );
			}
			update_user_meta( $user->ID, self::META_FORMER, $role );
			$user->set_role( '' );
			WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
			return array( true, $back );
		}

		if ( 'user_enable' === $do ) {
			$role = (string) get_user_meta( $user->ID, self::META_FORMER, true );
			$user->set_role( isset( self::roles()[ $role ] ) ? $role : 'sd_ekpaideftikos' );
			delete_user_meta( $user->ID, self::META_FORMER );
			return array( true, $back );
		}

		// user_reset: νέος σύνδεσμος ορισμού κωδικού στο email του/της.
		$sent = retrieve_password( $user->user_login );
		if ( is_wp_error( $sent ) ) {
			return array( new WP_Error( 'sd', self::link_fallback( $user ) ), $back );
		}
		set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', 'Στάλθηκε σύνδεσμος ορισμού κωδικού στο ' . $user->user_email . '.' ), 60 );
		return array( true, $back );
	}

	/**
	 * Όταν ο server δεν στέλνει email: ο σύνδεσμος ορισμού κωδικού (ισχύει 24 ώρες)
	 * εμφανίζεται στη Διεύθυνση για να τον δώσει η ίδια. Τον κωδικό τον ορίζει ο/η συνάδελφος.
	 */
	private static function link_fallback( $user ) {
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return 'Το email δεν στάλθηκε και δεν δημιουργήθηκε σύνδεσμος. Ο/Η συνάδελφος μπορεί να πατήσει «Ξεχάσατε τον κωδικό σας;» στη σελίδα σύνδεσης.';
		}
		$url = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
		return 'Ο server δεν έστειλε το email. Δώστε στον/στη συνάδελφο αυτόν τον σύνδεσμο (ισχύει 24 ώρες) για να ορίσει μόνος/η τον κωδικό του/της: ' . $url;
	}

	/* =================== Εμφάνιση =================== */

	public static function render() {
		$year  = SD_Data::active_year();
		$roles = self::roles();
		echo '<h2>Χρήστες</h2><p class="sd-help">Λογαριασμοί των συναδέλφων. Ο κωδικός δεν περνά από τη Διεύθυνση: κάθε συνάδελφος λαμβάνει email με σύνδεσμο και τον ορίζει μόνος/η του/της. Αν δεν φτάσει το email, μπορεί να πατήσει «Ξεχάσατε τον κωδικό σας;» στη σελίδα σύνδεσης.</p>';

		$classes = array();
		if ( $year ) {
			foreach ( SD_Data::classes( $year->id ) as $c ) {
				foreach ( SD_Data::class_teachers( $c->id ) as $t ) {
					$classes[ (int) $t->user_id ][ $c->id ] = SD_Data::class_label( $c );
				}
			}
		}
		$plans = array();
		if ( $year ) {
			foreach ( SD_Plans::plans( $year->id ) as $p ) {
				foreach ( array_merge( SD_Plans::members( $p->id ), array( (int) $p->coordinator_id ) ) as $uid ) {
					if ( $uid ) {
						$plans[ (int) $uid ][ $p->id ] = trim( $p->code ) . ( (int) $p->coordinator_id === (int) $uid ? ' (συντ.)' : '' );
					}
				}
			}
		}

		echo '<table class="sd-table"><thead><tr><th>Ονοματεπώνυμο</th><th>Όνομα χρήστη / email</th><th>Ρόλος</th><th>Τμήματα</th><th>Σχέδια Δράσης</th><th></th></tr></thead><tbody>';
		foreach ( self::listed() as $u ) {
			$role     = self::app_role( $u );
			$disabled = ! $role;
			echo '<tr' . ( $disabled ? ' class="sd-muted"' : '' ) . '><td><strong>' . esc_html( $u->display_name ) . '</strong>' . ( $disabled ? ' <span class="sd-badge sd-badge-warn">Ανενεργός</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $u->user_login ) . '<br><span class="sd-meta">' . esc_html( $u->user_email ) . '</span></td><td>';
			if ( ! self::manageable( $u ) ) {
				echo esc_html( SD_Roles::role_label( $u ) );
			} elseif ( $disabled ) {
				echo '—';
			} else {
				SD_Frontend::form_open( 'user_role' );
				echo '<input type="hidden" name="user_id" value="' . (int) $u->ID . '"><select name="role" onchange="this.form.submit()" aria-label="Ρόλος">';
				foreach ( $roles as $k => $l ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( $role, $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select><noscript><button class="sd-button sd-small" type="submit">OK</button></noscript></form>';
			}
			echo '</td><td>' . esc_html( implode( ', ', $classes[ $u->ID ] ?? array() ) ) . '</td><td>' . esc_html( implode( ', ', $plans[ $u->ID ] ?? array() ) ) . '</td><td>';
			if ( self::manageable( $u ) ) {
				if ( $disabled ) {
					SD_Frontend::form_open( 'user_enable' );
					echo '<input type="hidden" name="user_id" value="' . (int) $u->ID . '"><button class="sd-button sd-small" type="submit">Επανενεργοποίηση</button></form>';
				} else {
					SD_Frontend::form_open( 'user_reset' );
					echo '<input type="hidden" name="user_id" value="' . (int) $u->ID . '"><button class="sd-button sd-small" type="submit">Νέος σύνδεσμος κωδικού</button></form> ';
					SD_Frontend::form_open( 'user_disable' );
					echo '<input type="hidden" name="user_id" value="' . (int) $u->ID . '"><button class="sd-button sd-small sd-danger" type="submit" onclick="return confirm(\'Απενεργοποίηση του λογαριασμού; Όσα έχει καταθέσει μένουν.\');">Απενεργοποίηση</button></form>';
				}
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<section class="sd-card"><h3>Νέος λογαριασμός</h3>';
		SD_Frontend::form_open( 'user_create' );
		echo '<div class="sd-row"><label>Όνομα <input name="first_name" required></label><label>Επώνυμο <input name="last_name" required></label></div>';
		echo '<div class="sd-row"><label class="sd-grow">Email (εκεί θα πάει ο σύνδεσμος για τον κωδικό) <input type="email" name="email" required></label>';
		echo '<label>Ρόλος <select name="role">';
		foreach ( $roles as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></label></div>';
		echo '<p class="sd-help">Εκπαιδευτικός: τα δικά του/της τμήματα και έντυπα. Επιτροπή: βλέπει και ελέγχει όλα τα έντυπα. Διεύθυνση: επιπλέον τμήματα, χρήστες, ρυθμίσεις.</p>';
		echo '<p><button class="sd-button" type="submit">Δημιουργία και αποστολή email</button></p></form></section>';
	}
}
