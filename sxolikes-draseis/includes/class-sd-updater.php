<?php
/**
 * Ενημερώσεις από το GitHub (ίδιος μηχανισμός με το School e-Forms).
 *
 * Το WordPress ελέγχει για ενημερώσεις πρόσθετων μερικές φορές την ημέρα. Τότε το πρόσθετο διαβάζει
 * το update.json του repository stam1966/sxolikes-draseis και, αν η έκδοση είναι νεότερη, η ενημέρωση
 * εμφανίζεται στα «Πρόσθετα» του WordPress (ένα κλικ ή αυτόματα, αν ενεργοποιηθούν οι αυτόματες ενημερώσεις).
 *
 * Η σύνδεση γίνεται από τον server του σχολείου προς το GitHub και δεν στέλνει κανένα στοιχείο του σχολείου.
 * Το πρόσθετο βρίσκεται στον υποφάκελο «sxolikes-draseis» του repository· μετά τη λήψη, ο υποφάκελος
 * μπαίνει στη θέση του πρόσθετου.
 *
 * Μορφή update.json: {"version":"0.4.1","download":"https://github.com/…/archive/<commit>.zip",
 * "requires":"6.2","requires_php":"7.4","tested":"7.1","date":"…","changelog":"…"}
 */

defined( 'ABSPATH' ) || exit;

class SD_Updater {

	const SLUG      = 'sxolikes-draseis';
	const REPO      = 'https://github.com/stam1966/sxolikes-draseis';
	const INFO_URL  = 'https://raw.githubusercontent.com/stam1966/sxolikes-draseis/main/update.json';
	const CACHE     = 'sd_update_info';
	const CACHE_TTL = 10800; // 3 ώρες

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear' ), 10, 0 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_action( 'admin_post_sd_update_check', array( __CLASS__, 'admin_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	public static function basename() {
		return plugin_basename( SD_FILE );
	}

	private static function info_url() {
		return (string) apply_filters( 'sd_update_info_url', self::INFO_URL );
	}

	/** Στοιχεία της τελευταίας έκδοσης (προσωρινή μνήμη 3 ωρών· αποτυχία → 1 ώρα). */
	public static function info( $force = false ) {
		$c = get_site_transient( self::CACHE );
		if ( ! $force && is_array( $c ) ) {
			return empty( $c['version'] ) ? null : $c;
		}
		$r     = wp_remote_get(
			self::info_url(),
			array(
				'timeout'    => 8,
				'user-agent' => 'Sxolikes-Draseis-Updater/' . SD_VERSION,
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		$data  = null;
		$error = '';
		if ( is_wp_error( $r ) ) {
			$error = 'δεν έγινε σύνδεση με το GitHub (' . mb_substr( $r->get_error_message(), 0, 140 ) . ')';
		} elseif ( 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			$error = 'το GitHub απάντησε με κωδικό ' . (int) wp_remote_retrieve_response_code( $r );
		} else {
			$data = self::parse( wp_remote_retrieve_body( $r ) );
			if ( ! $data ) {
				$error = 'μη έγκυρο αρχείο ενημέρωσης';
			}
		}
		$store               = $data ? $data : array( 'version' => '', 'error' => $error );
		$store['checked_at'] = time();
		set_site_transient( self::CACHE, $store, $data ? self::CACHE_TTL : HOUR_IN_SECONDS );
		return $data;
	}

	/** Έλεγχος του update.json· δεκτές μόνο λήψεις από το GitHub. */
	public static function parse( $body ) {
		$j = json_decode( (string) $body, true );
		if ( ! is_array( $j ) || empty( $j['version'] ) || empty( $j['download'] ) ) {
			return null;
		}
		$v = (string) $j['version'];
		if ( ! preg_match( '/^\d+\.\d+(\.\d+)?$/', $v ) ) {
			return null;
		}
		$dl   = esc_url_raw( (string) $j['download'] );
		$host = wp_parse_url( $dl, PHP_URL_HOST );
		if ( 'https' !== wp_parse_url( $dl, PHP_URL_SCHEME ) || ! in_array( $host, array( 'github.com', 'codeload.github.com', 'objects.githubusercontent.com', 'raw.githubusercontent.com' ), true ) ) {
			return null;
		}
		return array(
			'version'      => $v,
			'download'     => $dl,
			'requires'     => isset( $j['requires'] ) ? sanitize_text_field( $j['requires'] ) : '6.2',
			'requires_php' => isset( $j['requires_php'] ) ? sanitize_text_field( $j['requires_php'] ) : '7.4',
			'tested'       => isset( $j['tested'] ) ? sanitize_text_field( $j['tested'] ) : '',
			'date'         => isset( $j['date'] ) ? sanitize_text_field( $j['date'] ) : '',
			'changelog'    => isset( $j['changelog'] ) ? mb_substr( (string) $j['changelog'], 0, 20000 ) : '',
		);
	}

	/** Η έκδοση στον δίσκο (μετά από αναβάθμιση το SD_VERSION μπορεί να είναι ακόμη το παλιό). */
	public static function installed_version( $transient = null ) {
		$base = self::basename();
		if ( is_object( $transient ) && ! empty( $transient->checked[ $base ] ) ) {
			return (string) $transient->checked[ $base ];
		}
		if ( function_exists( 'get_file_data' ) ) {
			$h = get_file_data( SD_FILE, array( 'Version' => 'Version' ) );
			if ( ! empty( $h['Version'] ) ) {
				return (string) $h['Version'];
			}
		}
		return SD_VERSION;
	}

	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$force = is_admin() && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification -- μόνο ανάγνωση
		$info  = self::info( $force );
		$base  = self::basename();
		$have  = self::installed_version( $transient );
		$item  = (object) array(
			'id'           => self::REPO,
			'slug'         => self::SLUG,
			'plugin'       => $base,
			'new_version'  => $info ? $info['version'] : $have,
			'url'          => self::REPO,
			'package'      => $info ? $info['download'] : '',
			'requires'     => $info ? $info['requires'] : '',
			'requires_php' => $info ? $info['requires_php'] : '',
			'tested'       => $info ? $info['tested'] : '',
			'icons'        => array(),
			'banners'      => array(),
		);
		if ( $info && version_compare( $info['version'], $have, '>' ) ) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ $base ] = $item;
		} else {
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$item->new_version             = $have;
			$transient->no_update[ $base ] = $item;
			if ( isset( $transient->response[ $base ] ) ) {
				unset( $transient->response[ $base ] );
			}
		}
		return $transient;
	}

	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$info = self::info();
		return (object) array(
			'name'          => 'Σχολικές Δράσεις',
			'slug'          => self::SLUG,
			'version'       => $info ? $info['version'] : SD_VERSION,
			'author'        => '22ο Δημοτικό Σχολείο Λαμίας – Ροδίτσας',
			'homepage'      => self::REPO,
			'requires'      => $info ? $info['requires'] : '6.2',
			'requires_php'  => $info ? $info['requires_php'] : '7.4',
			'tested'        => $info ? $info['tested'] : '',
			'last_updated'  => $info ? $info['date'] : '',
			'download_link' => $info ? $info['download'] : '',
			'sections'      => array(
				'description' => '<p>Σχεδιασμός Εργαστηρίων Δεξιοτήτων και Δράσεων Ενεργού Πολίτη, συνδεδεμένων με τα Σχέδια Δράσης της εσωτερικής αξιολόγησης.</p>',
				'changelog'   => self::changelog_html( $info ? $info['changelog'] : '' ),
			),
		);
	}

	public static function changelog_html( $text ) {
		if ( '' === trim( (string) $text ) ) {
			return '<p>Δείτε το <a href="' . esc_url( self::REPO ) . '">repository</a>.</p>';
		}
		$html = '';
		$list = false;
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^[-•*]\s+(.*)$/u', $line, $m ) ) {
				$html .= ( $list ? '' : '<ul>' ) . '<li>' . esc_html( $m[1] ) . '</li>';
				$list  = true;
				continue;
			}
			if ( $list ) {
				$html .= '</ul>';
				$list  = false;
			}
			if ( '' === $line ) {
				continue;
			}
			$html .= preg_match( '/^#+\s*(.*)$/u', $line, $m ) ? '<h4>' . esc_html( $m[1] ) . '</h4>' : '<p>' . esc_html( $line ) . '</p>';
		}
		return $html . ( $list ? '</ul>' : '' );
	}

	/**
	 * Το zip του GitHub αποσυμπιέζεται σε «sxolikes-draseis-<κωδικός>/» και το πρόσθετο είναι στον
	 * υποφάκελο «sxolikes-draseis/». Αυτός ο υποφάκελος μπαίνει στη θέση του πρόσθετου.
	 */
	public static function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( empty( $hook_extra['plugin'] ) || self::basename() !== $hook_extra['plugin'] || is_wp_error( $source ) ) {
			return $source;
		}
		$want  = trailingslashit( $remote_source ) . self::SLUG . '/';
		$inner = trailingslashit( $source ) . self::SLUG . '/';
		if ( $wp_filesystem && $wp_filesystem->exists( $inner . 'sxolikes-draseis.php' ) ) {
			$tmp = trailingslashit( $remote_source ) . 'sd-plugin-tmp/';
			if ( $wp_filesystem->move( untrailingslashit( $inner ), untrailingslashit( $tmp ), true )
				&& ( untrailingslashit( $source ) === untrailingslashit( $want ) ? $wp_filesystem->delete( untrailingslashit( $source ), true ) : true )
				&& $wp_filesystem->move( untrailingslashit( $tmp ), untrailingslashit( $want ), true ) ) {
				return $want;
			}
			return new WP_Error( 'sd_update_folder', 'Δεν ήταν δυνατή η τοποθέτηση του φακέλου της ενημέρωσης.' );
		}
		if ( untrailingslashit( $source ) === untrailingslashit( $want ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $want ), true ) ) {
			return $want;
		}
		return new WP_Error( 'sd_update_folder', 'Δεν ήταν δυνατή η μετονομασία του φακέλου της ενημέρωσης.' );
	}

	public static function clear() {
		delete_site_transient( self::CACHE );
	}

	/** Για τις Ρυθμίσεις της εφαρμογής: «Έλεγχος τώρα». */
	public static function check_now() {
		$info = self::info( true );
		delete_site_transient( 'update_plugins' );
		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-includes/update.php';
		}
		wp_update_plugins();
		return $info;
	}

	public static function status_html() {
		$c    = get_site_transient( self::CACHE );
		$have = self::installed_version();
		$out  = 'Εγκατεστημένη έκδοση: <b>' . esc_html( $have ) . '</b>. ';
		if ( is_array( $c ) && ! empty( $c['version'] ) ) {
			$out .= 'Τελευταία στο GitHub: <b>' . esc_html( $c['version'] ) . '</b>';
			$out .= version_compare( $c['version'], $have, '>' ) ? ' — η ενημέρωση εμφανίζεται στα «Πρόσθετα» της διαχείρισης.' : ' (είστε ενημερωμένοι).';
		} elseif ( is_array( $c ) && ! empty( $c['error'] ) ) {
			$out .= '<span class="sd-missing">Ο τελευταίος έλεγχος απέτυχε: ' . esc_html( $c['error'] ) . '.</span>';
		} else {
			$out .= 'Δεν έχει γίνει ακόμη έλεγχος.';
		}
		if ( is_array( $c ) && ! empty( $c['checked_at'] ) ) {
			$out .= ' Τελευταίος έλεγχος: ' . esc_html( wp_date( 'j/n/Y H:i', (int) $c['checked_at'] ) ) . '.';
		}
		return $out;
	}

	public static function row_meta( $links, $file ) {
		if ( self::basename() === $file ) {
			if ( current_user_can( 'update_plugins' ) ) {
				$url     = wp_nonce_url( admin_url( 'admin-post.php?action=sd_update_check' ), 'sd_update_check' );
				$links[] = '<a href="' . esc_url( $url ) . '"><strong>Έλεγχος για ενημέρωση</strong></a>';
			}
			$links[] = '<a href="' . esc_url( self::REPO ) . '" target="_blank" rel="noopener noreferrer">GitHub</a>';
		}
		return $links;
	}

	/** «Έλεγχος για ενημέρωση» από τη σελίδα «Πρόσθετα»: ρωτά το GitHub τώρα και επιστρέφει εκεί. */
	public static function admin_check() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( 'Δεν έχετε δικαίωμα.', 403 );
		}
		check_admin_referer( 'sd_update_check' );
		$info = self::check_now();
		$have = self::installed_version();
		if ( ! $info ) {
			$msg = 'fail';
		} elseif ( version_compare( $info['version'], $have, '>' ) ) {
			$msg = 'new';
		} else {
			$msg = 'same';
		}
		wp_safe_redirect( admin_url( 'plugins.php?sd_check=' . $msg ) );
		exit;
	}

	public static function admin_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id || empty( $_GET['sd_check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- μόνο εμφάνιση μηνύματος
			return;
		}
		$c    = get_site_transient( self::CACHE );
		$new  = is_array( $c ) && ! empty( $c['version'] ) ? $c['version'] : '';
		$code = sanitize_key( wp_unslash( $_GET['sd_check'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'new' === $code ) {
			$text  = 'Σχολικές Δράσεις: βρέθηκε νέα έκδοση ' . $new . '. Πατήστε «Ενημέρωση τώρα» κάτω από το πρόσθετο.';
			$class = 'notice-warning';
		} elseif ( 'same' === $code ) {
			$text  = 'Σχολικές Δράσεις: έχετε την τελευταία έκδοση (' . self::installed_version() . ').';
			$class = 'notice-success';
		} else {
			$text  = 'Σχολικές Δράσεις: ο έλεγχος στο GitHub απέτυχε. Δοκιμάστε ξανά σε λίγο.';
			$class = 'notice-error';
		}
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}
}
