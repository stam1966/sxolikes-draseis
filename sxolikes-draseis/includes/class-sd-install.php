<?php
/**
 * Εγκατάσταση, αναβάθμιση βάσης, απενεργοποίηση.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Install {

	const OPT_DB   = 'sd_db_version';
	const OPT_PAGE = 'sd_app_page_id';

	public static function activate() {
		self::create_tables();
		SD_Roles::install();
		self::ensure_page();
		self::ensure_year();
		// Κλειστός ιστότοπος: να μη τον ευρετηριάζουν μηχανές αναζήτησης.
		update_option( 'blog_public', '0' );
		update_option( 'users_can_register', '0' );
	}

	public static function deactivate() {
		// Τίποτα δεν διαγράφεται. Τα δεδομένα μένουν για επανενεργοποίηση.
	}

	public static function maybe_upgrade() {
		if ( get_option( self::OPT_DB ) !== SD_DB_VERSION ) {
			self::create_tables();
			SD_Roles::install();
		}
	}

	private static function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( SD_Data::schema() as $sql ) {
			dbDelta( $sql );
		}
		update_option( self::OPT_DB, SD_DB_VERSION );
	}

	/**
	 * Η σελίδα της εφαρμογής, που γίνεται και αρχική σελίδα του ιστότοπου.
	 */
	private static function ensure_page() {
		$page_id = (int) get_option( self::OPT_PAGE );
		if ( $page_id && get_post( $page_id ) ) {
			return;
		}
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Σχολικές Δράσεις',
				'post_content' => '[sxolikes_draseis]',
			)
		);
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( self::OPT_PAGE, $page_id );
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $page_id );
		}
	}

	/**
	 * Αν δεν υπάρχει κανένα σχολικό έτος, δημιουργεί το τρέχον.
	 */
	private static function ensure_year() {
		if ( SD_Data::active_year() ) {
			return;
		}
		$y = (int) current_time( 'Y' );
		$m = (int) current_time( 'n' );
		SD_Data::add_year( $m >= 9 ? $y : $y - 1 );
	}

	public static function app_url( $args = array() ) {
		$page_id = (int) get_option( self::OPT_PAGE );
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );
		return $args ? add_query_arg( $args, $url ) : $url;
	}
}
