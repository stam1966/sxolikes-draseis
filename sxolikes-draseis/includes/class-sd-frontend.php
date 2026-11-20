<?php
/**
 * Η εφαρμογή στο frontend: shortcode [sxolikes_draseis] με τις οθόνες της.
 *
 * Κάθε ενέργεια (POST) ελέγχει nonce και δικαίωμα στον server, και μετά
 * επιστρέφει με ανακατεύθυνση (για να μην ξαναϋποβάλλεται με ανανέωση).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Frontend {

	const NONCE = 'sd_action';

	/** Οθόνες: κλειδί => [τίτλος, δικαίωμα]. */
	private static function screens() {
		return array(
			'arxiki' => array( 'Αρχική', SD_Roles::CAP_ACCESS ),
			'sxedia' => array( 'Σχέδια Δράσης', SD_Roles::CAP_ACCESS ),
			'ergastiria' => array( 'Εργαστήρια (1Α)', SD_Roles::CAP_ACCESS ),
			'politis'    => array( 'Ενεργός Πολίτης (2)', SD_Roles::CAP_ACCESS ),
			'taxeis' => array( 'Τμήματα', SD_Roles::CAP_MANAGE ),
			'etos'   => array( 'Σχολικό έτος', SD_Roles::CAP_MANAGE ),
			'rythmiseis' => array( 'Ρυθμίσεις', SD_Roles::CAP_MANAGE ),
		);
	}

	public static function init() {
		add_shortcode( 'sxolikes_draseis', array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		self::fix_blogname();
	}

	private static function is_app_page() {
		$page_id = (int) get_option( SD_Install::OPT_PAGE );
		return $page_id && ( is_page( $page_id ) || ( is_front_page() && (int) get_option( 'page_on_front' ) === $page_id ) );
	}

	public static function assets() {
		if ( self::is_app_page() ) {
			wp_enqueue_style( 'sd-app', SD_URL . 'assets/sd.css', array(), SD_VERSION );
			wp_enqueue_script( 'sd-app', SD_URL . 'assets/sd.js', array(), SD_VERSION, true );
		}
	}

	/** Η σελίδα της εφαρμογής δεν χρησιμοποιεί το θέμα: έχει δική της καθαρή μορφή. */
	public static function template( $template ) {
		return self::is_app_page() ? SD_DIR . 'templates/app.php' : $template;
	}

	/** Όνομα σχολείου για την κεφαλίδα (ρύθμιση· προεπιλογή το σχολείο μας). */
	public static function school_name() {
		return (string) get_option( 'sd_school_name', '22ο Δημοτικό Σχολείο Λαμίας – Ροδίτσας' );
	}

	/** Το όνομα του ιστότοπου, αν λείπει ή αλλοιώθηκε. */
	private static function fix_blogname() {
		$name = (string) get_option( 'blogname' );
		if ( ! preg_match( '/\p{L}{3,}/u', $name ) ) {
			update_option( 'blogname', 'Σχολικές Δράσεις' );
		}
	}

	public static function url( $screen = 'arxiki', $args = array() ) {
		return SD_Install::app_url( array_merge( array( 'sd' => $screen ), $args ) );
	}

	/* ================= Ενέργειες (POST) ================= */

	public static function handle_post() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || empty( $_POST['sd_do'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() || ! check_admin_referer( self::NONCE ) ) {
			wp_die( 'Η ενέργεια δεν επιτρέπεται.', 403 );
		}
		$do = sanitize_key( wp_unslash( $_POST['sd_do'] ) );

		$manage_actions = array( 'add_year', 'activate_year', 'add_class', 'add_all_classes', 'save_class', 'delete_class' );
		if ( in_array( $do, $manage_actions, true ) && ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			wp_die( 'Δεν έχετε δικαίωμα για αυτή την ενέργεια.', 403 );
		}

		$back   = self::url( 'arxiki' );
		$result = true;

		$plans = SD_Plans::handle( $do );
		foreach ( array( 'SD_Workshops', 'SD_Citizen', 'SD_Settings' ) as $module ) {
			if ( null === $plans ) {
				$plans = $module::handle( $do );
			}
		}
		if ( null !== $plans ) {
			list( $result, $back ) = $plans;
			$do                    = '';
		}

		switch ( $do ) {
			case '':
				break;

			case 'add_year':
				$result = SD_Data::add_year( isset( $_POST['start_year'] ) ? absint( $_POST['start_year'] ) : 0 );
				$back   = self::url( 'etos' );
				break;

			case 'activate_year':
				$result = SD_Data::set_active_year( isset( $_POST['year_id'] ) ? absint( $_POST['year_id'] ) : 0 );
				$back   = self::url( 'etos' );
				break;

			case 'add_class':
				$year   = SD_Data::active_year();
				$result = $year ? SD_Data::add_class(
					$year->id,
					isset( $_POST['grade'] ) ? absint( $_POST['grade'] ) : 0,
					isset( $_POST['section'] ) ? sanitize_text_field( wp_unslash( $_POST['section'] ) ) : ''
				) : new WP_Error( 'sd', 'Δεν υπάρχει τρέχον σχολικό έτος.' );
				$back = self::url( 'taxeis' );
				break;

			case 'add_all_classes':
				$year = SD_Data::active_year();
				if ( $year ) {
					foreach ( array_keys( SD_Data::GRADES ) as $g ) {
						SD_Data::add_class( $year->id, $g, '' ); // Υπάρχοντα τμήματα παραλείπονται.
					}
				}
				$back = self::url( 'taxeis' );
				break;

			case 'save_class':
				$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
				$class    = SD_Data::get_class( $class_id );
				if ( ! $class ) {
					$result = new WP_Error( 'sd', 'Το τμήμα δεν βρέθηκε.' );
					break;
				}
				$posted = isset( $_POST['teachers'] ) && is_array( $_POST['teachers'] ) ? wp_unslash( $_POST['teachers'] ) : array();
				foreach ( array_keys( SD_Data::CLASS_ROLES ) as $role ) {
					$ids = isset( $posted[ $role ] ) ? array_map( 'absint', (array) $posted[ $role ] ) : array();
					SD_Data::set_class_teachers( $class_id, $role, $ids );
				}
				$back = self::url( 'taxeis' );
				break;

			case 'delete_class':
				SD_Data::delete_class( isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0 );
				$back = self::url( 'taxeis' );
				break;

			default:
				$result = new WP_Error( 'sd', 'Άγνωστη ενέργεια.' );
		}

		if ( is_wp_error( $result ) ) {
			set_transient( 'sd_msg_' . get_current_user_id(), array( 'error', $result->get_error_message() ), 60 );
		} else {
			set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', 'Αποθηκεύτηκε.' ), 60 );
		}
		wp_safe_redirect( $back );
		exit;
	}

	/* ================= Εμφάνιση ================= */

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return self::login_box();
		}
		if ( ! current_user_can( SD_Roles::CAP_ACCESS ) ) {
			return '<div class="sd-app"><p class="sd-notice sd-error">Ο λογαριασμός σας δεν έχει ακόμη πρόσβαση στην εφαρμογή. Επικοινωνήστε με τη Διεύθυνση.</p></div>';
		}

		$screens = self::screens();
		$screen  = isset( $_GET['sd'] ) ? sanitize_key( wp_unslash( $_GET['sd'] ) ) : 'arxiki';
		if ( ! isset( $screens[ $screen ] ) || ! current_user_can( $screens[ $screen ][1] ) ) {
			$screen = 'arxiki';
		}

		ob_start();
		echo '<div class="sd-app">';
		self::nav( $screen );
		self::flash();
		switch ( $screen ) {
			case 'taxeis':
				if ( isset( $_GET['id'] ) ) {
					self::screen_class( absint( $_GET['id'] ) );
				} else {
					self::screen_classes();
				}
				break;
			case 'etos':
				self::screen_years();
				break;
			case 'sxedia':
				SD_Plans::render();
				break;
			case 'ergastiria':
				SD_Workshops::render();
				break;
			case 'politis':
				SD_Citizen::render();
				break;
			case 'rythmiseis':
				SD_Settings::render();
				break;
			default:
				self::screen_home();
		}
		echo '</div>';
		return ob_get_clean();
	}

	private static function login_box() {
		$html  = '<div class="sd-app sd-login"><h2>Σύνδεση</h2>';
		$html .= '<p>Η εφαρμογή είναι μόνο για τους εκπαιδευτικούς του σχολείου.</p>';
		$html .= wp_login_form(
			array(
				'echo'           => false,
				'redirect'       => SD_Install::app_url(),
				'label_username' => 'Όνομα χρήστη ή email',
				'label_password' => 'Κωδικός',
				'label_remember' => 'Να με θυμάσαι',
				'label_log_in'   => 'Σύνδεση',
			)
		);
		$html .= '<p><a href="' . esc_url( wp_lostpassword_url( SD_Install::app_url() ) ) . '">Ξεχάσατε τον κωδικό σας;</a></p></div>';
		return $html;
	}

	private static function nav( $current ) {
		$user = wp_get_current_user();
		$year = SD_Data::active_year();
		echo '<nav class="sd-nav"><ul>';
		foreach ( self::screens() as $key => $def ) {
			if ( ! current_user_can( $def[1] ) ) {
				continue;
			}
			printf(
				'<li><a href="%s"%s>%s</a></li>',
				esc_url( self::url( $key ) ),
				$key === $current ? ' aria-current="page"' : '',
				esc_html( $def[0] )
			);
		}
		echo '</ul><div class="sd-who">';
		echo esc_html( $user->display_name );
		$label = SD_Roles::role_label( $user );
		if ( $label ) {
			echo ' · ' . esc_html( $label );
		}
		if ( $year ) {
			echo ' · <strong>' . esc_html( $year->label ) . '</strong>';
		}
		echo ' · <a href="' . esc_url( wp_logout_url( SD_Install::app_url() ) ) . '">Αποσύνδεση</a>';
		echo '</div></nav>';
	}

	private static function flash() {
		$key = 'sd_msg_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( $msg ) {
			delete_transient( $key );
			printf(
				'<p class="sd-notice %s" role="status">%s</p>',
				'error' === $msg[0] ? 'sd-error' : 'sd-ok',
				esc_html( $msg[1] )
			);
		}
	}

	public static function form_open( $action ) {
		echo '<form method="post" class="sd-form">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="sd_do" value="' . esc_attr( $action ) . '">';
	}

	/* ---------- Αρχική ---------- */

	private static function screen_home() {
		$user = wp_get_current_user();
		$year = SD_Data::active_year();

		echo '<h2>Καλώς ήρθατε, ' . esc_html( $user->first_name ? $user->first_name : $user->display_name ) . '</h2>';

		if ( ! $year ) {
			echo '<p class="sd-notice sd-error">Δεν έχει οριστεί τρέχον σχολικό έτος.</p>';
			return;
		}

		$mine = SD_Workshops::dashboard_rows( $year, false );
		echo '<section class="sd-card"><h3>Τα τμήματά μου · Έντυπο 1Α</h3>';
		if ( $mine ) {
			echo '<p class="sd-meta">Προθεσμία υποβολής 1Α: ' . esc_html( mysql2date( 'd/m/Y', SD_Workshops::deadline() ) ) . '</p><ul class="sd-list">';
			foreach ( $mine as $row ) {
				echo '<li><a href="' . esc_url( self::url( 'ergastiria', array( 'class' => $row[0]->id ) ) ) . '">Τμήμα ' . esc_html( SD_Data::class_label( $row[0] ) ) . '</a> ' . SD_Workshops::badge( $row[1] ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</ul>';
		} else {
			echo '<p>Δεν σας έχει αντιστοιχιστεί ακόμη τμήμα. Η Διεύθυνση ορίζει τα τμήματα και τους υπεύθυνους.</p>';
		}
		echo '</section>';

		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			$classes   = SD_Data::classes( $year->id );
			$no_lead   = 0;
			foreach ( $classes as $c ) {
				$has = false;
				foreach ( SD_Data::class_teachers( $c->id ) as $t ) {
					if ( 'ergastiria' === $t->class_role ) {
						$has = true;
					}
				}
				if ( ! $has ) {
					$no_lead++;
				}
			}
			$counts = array();
			foreach ( SD_Workshops::dashboard_rows( $year, true ) as $row ) {
				$s            = $row[1] ? $row[1]->status : 'none';
				$counts[ $s ] = ( $counts[ $s ] ?? 0 ) + 1;
			}
			echo '<section class="sd-card"><h3>Για τη Διεύθυνση</h3><ul>';
			echo '<li>Τμήματα στο ' . esc_html( $year->label ) . ': <strong>' . count( $classes ) . '</strong></li>';
			echo '<li>Έντυπα 1Α: υποβλήθηκαν <strong>' . (int) ( ( $counts['ypovlithike'] ?? 0 ) + ( $counts['elegxthike'] ?? 0 ) ) . '</strong>, ελέγχθηκαν <strong>' . (int) ( $counts['elegxthike'] ?? 0 ) . '</strong>, σε εξέλιξη ή επιστροφή <strong>' . (int) ( ( $counts['proxeiro'] ?? 0 ) + ( $counts['epistrafike'] ?? 0 ) + ( $counts['none'] ?? 0 ) ) . '</strong> · <a href="' . esc_url( self::url( 'ergastiria' ) ) . '">Όλα τα έντυπα</a></li>';
			if ( $classes ) {
				echo '<li>Τμήματα χωρίς υπεύθυνο Εργαστηρίων: <strong>' . (int) $no_lead . '</strong></li>';
			}
			echo '</ul><p><a class="sd-button" href="' . esc_url( self::url( 'taxeis' ) ) . '">Διαχείριση τμημάτων</a></p></section>';
		}

		$plans = SD_Plans::plans( $year->id );
		echo '<section class="sd-card"><h3>Σχέδια Δράσης του σχολείου</h3>';
		if ( $plans ) {
			echo '<ul>';
			foreach ( $plans as $p ) {
				$mine = (int) $p->coordinator_id === (int) $user->ID ? ' <span class="sd-badge sd-badge-ok">Συντονίζετε</span>' : ( in_array( (int) $user->ID, SD_Plans::members( $p->id ), true ) ? ' <span class="sd-badge">Μέλος</span>' : '' );
				echo '<li><a href="' . esc_url( self::url( 'sxedia', array( 'id' => $p->id ) ) ) . '">' . esc_html( trim( $p->code . ' ' . $p->title ) ) . '</a>' . $mine . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</ul>';
		} else {
			echo '<p>Δεν έχουν καταχωριστεί ακόμη Σχέδια Δράσης.</p>';
		}
		echo '</section>';

		$deps = SD_Citizen::dashboard_rows( $year );
		if ( $deps ) {
			echo '<section class="sd-card"><h3>Δράσεις Ενεργού Πολίτη των τμημάτων μου</h3><ul class="sd-list">';
			foreach ( $deps as $row ) {
				echo '<li><strong>' . esc_html( SD_Data::class_label( $row[0] ) ) . '</strong>: ';
				if ( ! $row[1] ) {
					echo '<a href="' . esc_url( self::url( 'politis' ) ) . '">καμία ακόμη, ξεκινήστε μία</a>';
				}
				foreach ( $row[1] as $d ) {
					echo '<a href="' . esc_url( self::url( 'politis', array( 'id' => $d->id ) ) ) . '">' . esc_html( $d->title ) . '</a> ' . SD_Citizen::status_badge( $d->status ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</li>';
			}
			echo '</ul></section>';
		}
	}

	/* ---------- Τμήματα ---------- */

	private static function screen_classes() {
		$year = SD_Data::active_year();
		if ( ! $year ) {
			echo '<p class="sd-notice sd-error">Ορίστε πρώτα σχολικό έτος.</p>';
			return;
		}
		$classes = SD_Data::classes( $year->id );
		$names   = array();
		foreach ( SD_Roles::app_users() as $u ) {
			$names[ $u->ID ] = $u->display_name;
		}

		echo '<h2>Τμήματα ' . esc_html( $year->label ) . '</h2>';

		if ( $classes ) {
			echo '<table class="sd-table"><thead><tr><th>Τμήμα</th><th>Ώρες Εργαστηρίων / εβδ.</th>';
			foreach ( SD_Data::CLASS_ROLES as $label ) {
				echo '<th>' . esc_html( $label ) . '</th>';
			}
			echo '<th></th></tr></thead><tbody>';
			foreach ( $classes as $c ) {
				$by_role = array();
				foreach ( SD_Data::class_teachers( $c->id ) as $t ) {
					$by_role[ $t->class_role ][] = isset( $names[ $t->user_id ] ) ? $names[ $t->user_id ] : '#' . $t->user_id;
				}
				echo '<tr><td><strong>' . esc_html( SD_Data::class_label( $c ) ) . '</strong></td><td>' . (int) $c->workshop_hours . '</td>';
				foreach ( array_keys( SD_Data::CLASS_ROLES ) as $role ) {
					$list = isset( $by_role[ $role ] ) ? implode( ', ', $by_role[ $role ] ) : '';
					echo '<td>' . ( $list ? esc_html( $list ) : '<span class="sd-missing">—</span>' ) . '</td>';
				}
				echo '<td><a href="' . esc_url( self::url( 'taxeis', array( 'id' => $c->id ) ) ) . '">Επεξεργασία</a></td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>Δεν υπάρχουν ακόμη τμήματα.</p>';
			self::form_open( 'add_all_classes' );
			echo '<p><button class="sd-button" type="submit">Δημιουργία Α΄ έως ΣΤ΄ (ένα τμήμα ανά τάξη)</button></p></form>';
		}

		echo '<section class="sd-card"><h3>Νέο τμήμα</h3>';
		self::form_open( 'add_class' );
		echo '<div class="sd-row"><label>Τάξη <select name="grade" required><option value="">—</option>';
		foreach ( SD_Data::GRADES as $n => $g ) {
			echo '<option value="' . (int) $n . '">' . esc_html( $g ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>Τμήμα <input name="section" inputmode="numeric" pattern="[0-9]{0,2}" size="3" placeholder="π.χ. 1"></label>';
		echo '<button class="sd-button" type="submit">Προσθήκη</button></div>';
		echo '<p class="sd-help">Αφήστε το «Τμήμα» κενό αν η τάξη έχει ένα μόνο τμήμα. Οι ώρες Εργαστηρίων συμπληρώνονται από την τάξη (3 / 2 / 1 ανά εβδομάδα).</p>';
		echo '</form></section>';
	}

	private static function screen_class( $class_id ) {
		$class = SD_Data::get_class( $class_id );
		if ( ! $class ) {
			echo '<p class="sd-notice sd-error">Το τμήμα δεν βρέθηκε.</p>';
			return;
		}
		$current = array();
		foreach ( SD_Data::class_teachers( $class->id ) as $t ) {
			$current[ $t->class_role ][] = (int) $t->user_id;
		}
		$users = SD_Roles::app_users();

		echo '<p><a href="' . esc_url( self::url( 'taxeis' ) ) . '">← Όλα τα τμήματα</a></p>';
		echo '<h2>Τμήμα ' . esc_html( SD_Data::class_label( $class ) ) . '</h2>';
		echo '<p>Εβδομαδιαίες ώρες Εργαστηρίων: <strong>' . (int) $class->workshop_hours . '</strong></p>';

		if ( ! $users ) {
			echo '<p class="sd-notice sd-error">Δεν υπάρχουν ακόμη χρήστες με ρόλο της εφαρμογής.</p>';
			return;
		}

		self::form_open( 'save_class' );
		echo '<input type="hidden" name="class_id" value="' . (int) $class->id . '">';
		foreach ( SD_Data::CLASS_ROLES as $role => $label ) {
			echo '<fieldset class="sd-card"><legend>' . esc_html( $label ) . '</legend><div class="sd-checks">';
			foreach ( $users as $u ) {
				$checked = isset( $current[ $role ] ) && in_array( (int) $u->ID, $current[ $role ], true );
				printf(
					'<label><input type="checkbox" name="teachers[%s][]" value="%d"%s> %s</label>',
					esc_attr( $role ),
					(int) $u->ID,
					$checked ? ' checked' : '',
					esc_html( $u->display_name )
				);
			}
			echo '</div></fieldset>';
		}
		echo '<p><button class="sd-button" type="submit">Αποθήκευση</button></p></form>';

		self::form_open( 'delete_class' );
		echo '<input type="hidden" name="class_id" value="' . (int) $class->id . '">';
		echo '<p><button class="sd-button sd-danger" type="submit" onclick="return confirm(\'Διαγραφή του τμήματος; Οι αντιστοιχίσεις εκπαιδευτικών θα χαθούν.\');">Διαγραφή τμήματος</button></p></form>';
	}

	/* ---------- Σχολικό έτος ---------- */

	private static function screen_years() {
		echo '<h2>Σχολικό έτος</h2>';
		echo '<table class="sd-table"><thead><tr><th>Έτος</th><th>Κατάσταση</th><th></th></tr></thead><tbody>';
		foreach ( SD_Data::years() as $y ) {
			echo '<tr><td><strong>' . esc_html( $y->label ) . '</strong></td><td>' . ( $y->is_active ? 'Τρέχον' : '' ) . '</td><td>';
			if ( ! $y->is_active ) {
				self::form_open( 'activate_year' );
				echo '<input type="hidden" name="year_id" value="' . (int) $y->id . '"><button class="sd-button sd-small" type="submit">Ορισμός ως τρέχον</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		$next = (int) current_time( 'Y' );
		echo '<section class="sd-card"><h3>Νέο σχολικό έτος</h3>';
		self::form_open( 'add_year' );
		echo '<div class="sd-row"><label>Ξεκινά τον Σεπτέμβριο του <input type="number" name="start_year" min="2020" max="2100" value="' . (int) $next . '" required></label>';
		echo '<button class="sd-button" type="submit">Δημιουργία</button></div>';
		echo '<p class="sd-help">Τα δεδομένα κάθε έτους μένουν χωριστά. Το νέο έτος γίνεται τρέχον μόνο όταν το ορίσετε.</p></form></section>';
	}
}
