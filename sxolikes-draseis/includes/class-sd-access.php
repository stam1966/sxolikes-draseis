<?php
/**
 * Κλειστή πρόσβαση: ο ιστότοπος είναι μόνο για συνδεδεμένους χρήστες, και
 * όσοι δεν είναι διαχειριστές δεν μπαίνουν στη διαχείριση του WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Access {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'require_login' ) );
		add_action( 'admin_init', array( __CLASS__, 'block_wp_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'rest_require_login' ) );
		add_filter( 'xmlrpc_enabled', '__return_false' );
	}

	/** Διαχειριστές του WordPress (τεχνική διαχείριση). */
	public static function is_wp_admin_user() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Κάθε σελίδα εκτός από τη σελίδα της εφαρμογής ζητά σύνδεση.
	 * Η σελίδα της εφαρμογής δείχνει η ίδια τη φόρμα σύνδεσης.
	 */
	public static function require_login() {
		if ( is_user_logged_in() ) {
			return;
		}
		$page_id = (int) get_option( SD_Install::OPT_PAGE );
		if ( $page_id && ( is_page( $page_id ) || is_front_page() ) ) {
			return;
		}
		wp_safe_redirect( SD_Install::app_url() );
		exit;
	}

	public static function block_wp_admin() {
		if ( self::is_wp_admin_user() || wp_doing_ajax() ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( in_array( $script, array( 'admin-post.php', 'admin-ajax.php', 'profile.php' ), true ) ) {
			// Το profile.php επιτρέπεται για αλλαγή κωδικού.
			return;
		}
		wp_safe_redirect( SD_Install::app_url() );
		exit;
	}

	public static function admin_bar( $show ) {
		return self::is_wp_admin_user() ? $show : false;
	}

	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( $user instanceof WP_User && ! $user->has_cap( 'manage_options' ) ) {
			return SD_Install::app_url();
		}
		return $redirect_to;
	}

	/** Το REST API του WordPress μόνο για συνδεδεμένους χρήστες. */
	public static function rest_require_login( $result ) {
		if ( null === $result && ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', 'Απαιτείται σύνδεση.', array( 'status' => 401 ) );
		}
		return $result;
	}
}
