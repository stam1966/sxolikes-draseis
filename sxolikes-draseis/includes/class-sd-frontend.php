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
			'arxiki'     => array( 'Αρχική', SD_Roles::CAP_ACCESS, '' ),
			'sxedia'     => array( 'Σχέδια Δράσης', SD_Roles::CAP_ACCESS, '' ),
			'ergastiria' => array( 'Εργαστήρια', SD_Roles::CAP_ACCESS, '1Α' ),
			'politis'    => array( 'Ενεργός Πολίτης', SD_Roles::CAP_ACCESS, '2' ),
			'aitimata'   => array( 'Επισκέψεις', SD_Roles::CAP_ACCESS, '3' ),
			'apotimisi'  => array( 'Αποτίμηση', SD_Roles::CAP_ACCESS, '4' ),
			'xartis'     => array( 'Χάρτης τόπου', SD_Roles::CAP_ACCESS, '' ),
			'exagoges'   => array( 'Εξαγωγές', SD_Roles::CAP_REVIEW, '' ),
			'taxeis'     => array( 'Τμήματα', SD_Roles::CAP_MANAGE, 'admin' ),
			'xristes'    => array( 'Χρήστες', SD_Roles::CAP_MANAGE, 'admin' ),
			'etos'       => array( 'Σχολικό έτος', SD_Roles::CAP_MANAGE, 'admin' ),
			'rythmiseis' => array( 'Ρυθμίσεις', SD_Roles::CAP_MANAGE, 'admin' ),
			'voitheia'   => array( 'Βοήθεια', SD_Roles::CAP_ACCESS, 'hidden' ),
		);
	}

	public static function init() {
		add_shortcode( 'sxolikes_draseis', array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_assets' ) );
		add_filter( 'login_headerurl', array( 'SD_Install', 'app_url' ) );
		add_filter( 'login_headertext', array( __CLASS__, 'login_headertext' ) );
		add_filter( 'login_message', array( __CLASS__, 'login_title' ) );
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
			if ( isset( $_GET['sd'] ) && 'xartis' === $_GET['sd'] ) { // phpcs:ignore WordPress.Security.NonceVerification
				wp_enqueue_style( 'leaflet', SD_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
				wp_enqueue_style( 'sd-map', SD_URL . 'assets/sd-map.css', array( 'leaflet', 'sd-app' ), SD_VERSION );
				wp_enqueue_script( 'leaflet', SD_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
				wp_enqueue_script( 'sd-map', SD_URL . 'assets/sd-map.js', array( 'leaflet' ), SD_VERSION, true );
			}
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

	/** Λογότυπο: ρύθμιση της Διεύθυνσης ή το εικονίδιο του ιστότοπου. */
	public static function logo_url() {
		$u = (string) get_option( 'sd_logo_url', '' );
		if ( '' === $u && function_exists( 'get_site_icon_url' ) ) {
			$u = (string) get_site_icon_url( 128 );
		}
		return $u;
	}

	public static function logo_html() {
		$u = self::logo_url();
		if ( $u ) {
			return '<span class="sd-logo"><img src="' . esc_url( $u ) . '" alt=""></span>';
		}
		return '<span class="sd-logo"><span class="sd-logo-mono" aria-hidden="true">ΣΔ</span></span>';
	}

	/* Σελίδες σύνδεσης/κωδικού του WordPress στα χρώματα της εφαρμογής. */
	public static function login_assets() {
		wp_enqueue_style( 'sd-login', SD_URL . 'assets/sd-login.css', array(), SD_VERSION );
		$u = self::logo_url();
		$css = $u ? '.login h1 a{background-image:url("' . esc_url( $u ) . '")}' : '.login h1 a{background-image:none}';
		wp_add_inline_style( 'sd-login', $css );
	}

	public static function login_headertext() {
		return 'Σχολικές Δράσεις';
	}

	public static function login_title( $message ) {
		return '<p class="sd-login-title">Σχολικές Δράσεις<small>' . esc_html( self::school_name() ) . '</small></p>' . $message;
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
		foreach ( array( 'SD_Workshops', 'SD_Citizen', 'SD_Settings', 'SD_Map', 'SD_Requests', 'SD_F4', 'SD_Users', 'SD_ErgPlan', 'SD_AiField', 'SD_PlanImport', 'SD_Notify', 'SD_Wizard' ) as $module ) {
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
		} elseif ( ! get_transient( 'sd_msg_' . get_current_user_id() ) ) {
			// Μια ενότητα μπορεί να έχει ήδη γράψει πιο συγκεκριμένο μήνυμα.
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
			case 'xartis':
				SD_Map::render();
				break;
			case 'aitimata':
				SD_Requests::render();
				break;
			case 'apotimisi':
				SD_F4::render();
				break;
			case 'exagoges':
				SD_Exports::render();
				break;
			case 'xristes':
				SD_Users::render();
				break;
			case 'voitheia':
				SD_Guide::render();
				break;
			default:
				self::screen_home();
		}
		echo '</div>';
		return ob_get_clean();
	}

	/** Ο χρήστης στην κεφαλίδα: όνομα, ρόλος, χρονιά, Βοήθεια, Αποσύνδεση. */
	public static function header_user() {
		if ( ! is_user_logged_in() || ! current_user_can( SD_Roles::CAP_ACCESS ) ) {
			return '';
		}
		$user  = wp_get_current_user();
		$year  = SD_Data::active_year();
		$parts = preg_split( '/\s+/u', trim( $user->display_name ) );
		$init  = mb_strtoupper( mb_substr( $parts[0] ?? '', 0, 1 ) . ( count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '' ) );
		$role  = SD_Roles::role_label( $user );
		$cur   = isset( $_GET['sd'] ) ? sanitize_key( wp_unslash( $_GET['sd'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$h     = '<div class="sd-user">';
		$h    .= '<a class="sd-help-btn" href="' . esc_url( self::url( 'voitheia' ) ) . '"' . ( 'voitheia' === $cur ? ' aria-current="page"' : '' ) . '>Βοήθεια</a>';
		$h    .= '<span class="sd-user-txt"><strong>' . esc_html( $user->display_name ) . '</strong><small>' . esc_html( trim( $role . ( $year ? ' · ' . $year->label : '' ), ' ·' ) ) . '</small></span>';
		$h    .= '<span class="sd-avatar" aria-hidden="true">' . esc_html( $init ) . '</span>';
		$h    .= '<a class="sd-logout" href="' . esc_url( wp_logout_url( SD_Install::app_url() ) ) . '">Αποσύνδεση</a>';
		return $h . '</div>';
	}

	private static function login_box() {
		$html  = '<div class="sd-login-wrap"><div class="sd-app sd-login"><div class="sd-login-card">' . self::logo_html();
		$html .= '<h2>Καλώς ήρθατε</h2><p class="sd-login-sub">Σχολικές Δράσεις · ' . esc_html( self::school_name() ) . '</p>';
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
		$html .= '<p class="sd-login-links"><a href="' . esc_url( wp_lostpassword_url( SD_Install::app_url() ) ) . '">Ξεχάσατε τον κωδικό σας;</a></p>';
		$html .= '<p class="sd-login-note">Μόνο για τους εκπαιδευτικούς του σχολείου. Τον λογαριασμό τον δημιουργεί η Διεύθυνση.</p></div></div></div>';
		return $html;
	}

	private static function nav( $current ) {
		$user = wp_get_current_user();
		$year = SD_Data::active_year();
		$main  = '';
		$admin = '';
		$in_admin = false;
		foreach ( self::screens() as $key => $def ) {
			if ( ! current_user_can( $def[1] ) || 'hidden' === $def[2] ) {
				continue;
			}
			$tag  = ( '' !== $def[2] && 'admin' !== $def[2] ) ? ' <span class="sd-nav-no">' . esc_html( $def[2] ) . '</span>' : '';
			if ( 'arxiki' === $key && current_user_can( SD_Roles::CAP_REVIEW ) ) {
				$pending = count( SD_Notify::pending_for_user() );
				$tag    .= $pending ? ' <span class="sd-nav-count" title="Περιμένουν τον έλεγχό σας">' . (int) $pending . '</span>' : '';
			}
			$item = sprintf(
				'<li><a href="%s"%s>%s%s</a></li>',
				esc_url( self::url( $key ) ),
				$key === $current ? ' aria-current="page"' : '',
				esc_html( $def[0] ),
				$tag
			);
			if ( 'admin' === $def[2] ) {
				$admin   .= $item;
				$in_admin = $in_admin || $key === $current;
			} else {
				$main .= $item;
			}
		}
		$title = self::screens()[ $current ][0] ?? 'Μενού';
		echo '<nav class="sd-nav" aria-label="Μενού εφαρμογής"><details class="sd-nav-toggle"><summary><span class="sd-burger" aria-hidden="true"></span> Μενού · <strong>' . esc_html( $title ) . '</strong></summary></details>';
		echo '<ul class="sd-nav-main">' . $main; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $admin ) {
			echo '<li class="sd-nav-admin"><details><summary' . ( $in_admin ? ' aria-current="true"' : '' ) . '>Διαχείριση</summary><ul>' . $admin . '</ul></details></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul></nav>';

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

	private static function greeting() {
		return (int) current_time( 'G' ) < 13 ? 'Καλημέρα' : 'Καλησπέρα';
	}

	/** Η μεγάλη κάρτα «Το επόμενο βήμα σας». */
	private static function next_card( $s, $kicker ) {
		$dl = SD_Timeline::deadline_text( $s['deadline'], $s['state'] );
		echo '<section class="sd-next" aria-label="' . esc_attr( $kicker ) . '"><p class="sd-next-k">' . esc_html( $kicker ) . '</p>';
		echo '<h2>' . esc_html( $s['title'] ) . '</h2>';
		$txt = trim( ( ! empty( $s['group'] ) ? $s['group'] . '. ' : '' ) . $s['detail'] );
		if ( '' !== $txt ) {
			echo '<p>' . esc_html( $txt ) . '</p>';
		}
		if ( ! empty( $s['out'] ) ) {
			echo '<p class="sd-next-out">Μόλις το κάνετε, συμπληρώνεται μόνο του: ' . esc_html( $s['out'] ) . '.</p>';
		}
		echo '<a class="sd-big" href="' . esc_url( $s['url'] ) . '">' . esc_html( $s['cta'] ? $s['cta'] : 'Ξεκινήστε' ) . SD_Docs::icon( 'arrow' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $dl ) {
			echo '<p class="sd-next-when">' . esc_html( $dl ) . '</p>';
		}
		echo '</section>';
	}

	private static function screen_home() {
		$user = wp_get_current_user();
		$year = SD_Data::active_year();
		$name = $user->first_name ? $user->first_name : $user->display_name;

		echo '<div class="sd-hello"><h1>' . esc_html( self::greeting() . ', ' . $name ) . '</h1>';

		if ( ! $year ) {
			echo '</div><p class="sd-notice sd-error">Δεν έχει οριστεί τρέχον σχολικό έτος.' . ( current_user_can( SD_Roles::CAP_MANAGE ) ? ' <a href="' . esc_url( self::url( 'etos' ) ) . '">Ορισμός σχολικού έτους</a>' : '' ) . '</p>';
			return;
		}

		// Ενότητες: τα τμήματά μου, τα σχέδια που συντονίζω, το σχολείο.
		$classes = SD_Data::classes_of_user( $user->ID, $year->id );
		$groups  = array();
		foreach ( $classes as $c ) {
			$groups[] = array(
				'title' => 'Τμήμα ' . SD_Data::class_label( $c ),
				'steps' => SD_Timeline::class_steps( $c ),
				'class' => $c,
			);
		}
		$plans  = SD_Plans::plans( $year->id );
		$member = array();
		$coord  = array();
		foreach ( $plans as $p ) {
			if ( (int) $p->coordinator_id === (int) $user->ID ) {
				$coord[] = array(
					'title' => trim( $p->code . ' «' . $p->title . '»' ),
					'steps' => SD_Timeline::plan_steps( $p ),
				);
			} elseif ( in_array( (int) $user->ID, SD_Plans::members( $p->id ), true ) ) {
				$member[] = $p;
			}
		}
		$manage = current_user_can( SD_Roles::CAP_MANAGE );
		$school = current_user_can( SD_Roles::CAP_REVIEW ) ? SD_Timeline::school_steps( $year ) : array();

		$urgent  = SD_Timeline::urgent( array_merge( $groups, $coord ) );
		$pending = current_user_can( SD_Roles::CAP_REVIEW ) ? SD_Notify::pending_for_user() : array();
		$review  = array();
		foreach ( $school as $s ) {
			if ( 'review' === $s['state'] ) {
				$review[] = $s;
			}
		}

		$n = count( $urgent );
		if ( $n ) {
			$lead = 1 === $n ? 'Σας περιμένει ένα πράγμα.' : 'Σας περιμένουν ' . $n . ' πράγματα. Ξεκινήστε από το πρώτο.';
		} else {
			$lead = 'Δεν σας περιμένει κάτι αυτή τη στιγμή.';
		}
		if ( $pending ) {
			$lead .= ' ' . ( 1 === count( $pending ) ? 'Ένα έντυπο περιμένει τον έλεγχό σας.' : count( $pending ) . ' έντυπα περιμένουν τον έλεγχό σας.' );
		}
		echo '<p class="sd-lead">' . esc_html( $year->label . ' · ' . $lead ) . '</p></div>';

		if ( $urgent ) {
			self::next_card( $urgent[0], 'Το επόμενο βήμα σας' );
		} elseif ( $review ) {
			self::next_card( $review[0], 'Περιμένει τον έλεγχό σας' );
		} elseif ( $groups || $coord ) {
			echo '<section class="sd-next sd-next-calm"><p class="sd-next-k">Όλα εντάξει</p><h2>Δεν χρειάζεται να κάνετε κάτι τώρα</h2><p>Θα σας στείλουμε email 7 και 2 ημέρες πριν από κάθε προθεσμία.</p></section>';
		}

		if ( ! $groups && ! $school && ! $coord ) {
			echo '<section class="sd-card"><p>Δεν σας έχει αντιστοιχιστεί ακόμη τμήμα ή Σχέδιο Δράσης. Η Διεύθυνση ορίζει τα τμήματα και τους υπεύθυνους.</p></section>';
		}

		// Κάθε τμήμα: η κορδέλα της χρονιάς, οι δουλειές και τα έντυπα.
		foreach ( $groups as $g ) {
			$c     = $g['class'];
			$label = SD_Data::class_label( $c );
			echo '<section class="sd-year"><div class="sd-year-head"><h2>Η χρονιά του ' . esc_html( $label ) . '</h2>' . SD_Timeline::progress_html( $g['steps'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo SD_Docs::ribbon( $c ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<div class="sd-legend" aria-hidden="true">';
			foreach ( SD_Workshops::CYCLES as $k => $l ) {
				echo '<span><i class="sd-k' . (int) $k . '"></i>' . esc_html( $l ) . '</span>';
			}
			echo '</div>';
			echo '<div class="sd-year-tasks"><h3>Τι έχει το ' . esc_html( $label ) . ' φέτος</h3>';
			SD_Timeline::render_steps( $g['steps'] );
			echo '</div></section>';
			echo SD_Docs::grid( SD_Docs::of_class( $c ), 'Τα έντυπα του ' . $label, 'Δεν τα συμπληρώνετε χωριστά. Γεμίζουν από όσα κάνετε στις δουλειές παραπάνω.' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		foreach ( $coord as $g ) {
			echo '<section class="sd-card sd-tl-card"><div class="sd-tl-top"><h3>' . esc_html( $g['title'] ) . '</h3>' . SD_Timeline::progress_html( $g['steps'] ) . '</div><p class="sd-meta">Συντονίζετε αυτό το Σχέδιο Δράσης.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			SD_Timeline::render_steps( $g['steps'] );
			echo '</section>';
		}

		if ( $school ) {
			echo '<section class="sd-card sd-tl-card sd-tl-school"><div class="sd-tl-top"><h3>Όλο το σχολείο</h3>' . SD_Timeline::progress_html( $school ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			SD_Timeline::render_steps( $school );
			if ( $manage ) {
				$pending_places = SD_Map::pending_count( $year->id );
				if ( $pending_places ) {
					echo '<p class="sd-meta">Σημεία χάρτη για έγκριση: <strong>' . (int) $pending_places . '</strong> · <a href="' . esc_url( self::url( 'xartis' ) ) . '">Χάρτης τόπου</a></p>';
				}
			}
			echo '</section>';
		}

		// Τα Σχέδια Δράσης του σχολείου, σε σύντομη λίστα.
		echo '<section class="sd-card"><h3>Σχέδια Δράσης του σχολείου</h3>';
		if ( $plans ) {
			echo '<ul class="sd-list">';
			foreach ( $plans as $p ) {
				$tag = (int) $p->coordinator_id === (int) $user->ID ? ' <span class="sd-badge sd-badge-ok">Συντονίζετε</span>' : ( in_array( $p, $member, true ) ? ' <span class="sd-badge">Μέλος</span>' : '' );
				echo '<li><a href="' . esc_url( self::url( 'sxedia', array( 'id' => $p->id ) ) ) . '">' . esc_html( trim( $p->code . ' ' . $p->title ) ) . '</a>' . $tag . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</ul>';
		} else {
			echo '<p>Δεν έχουν καταχωριστεί ακόμη Σχέδια Δράσης.</p>';
		}
		echo '</section>';
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
