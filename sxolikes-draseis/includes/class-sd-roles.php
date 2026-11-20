<?php
/**
 * Ρόλοι και δικαιώματα.
 *
 * Ρόλοι WordPress: Εκπαιδευτικός, Επιτροπή, Διεύθυνση. Ο Διαχειριστής του
 * WordPress έχει τεχνικές αρμοδιότητες και, για διευκόλυνση, και της Διεύθυνσης.
 * Ο ρόλος «Συντονιστής ΣΔ» δεν είναι ρόλος WordPress: ορίζεται ανά Σχέδιο Δράσης.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Roles {

	/** Πρόσβαση στην εφαρμογή. */
	const CAP_ACCESS = 'sd_access';
	/** Προβολή όλων των καταθέσεων, σχόλια, επιστροφή για διόρθωση. */
	const CAP_REVIEW = 'sd_review';
	/** Σχολικό έτος, τάξεις, Σχέδια Δράσης, πράξεις, ρυθμίσεις. */
	const CAP_MANAGE = 'sd_manage';

	public static function definitions() {
		return array(
			'sd_ekpaideftikos' => array(
				'label' => 'Εκπαιδευτικός',
				'caps'  => array( self::CAP_ACCESS ),
			),
			'sd_epitropi'      => array(
				'label' => 'Επιτροπή',
				'caps'  => array( self::CAP_ACCESS, self::CAP_REVIEW ),
			),
			'sd_diefthynsi'    => array(
				'label' => 'Διεύθυνση',
				'caps'  => array( self::CAP_ACCESS, self::CAP_REVIEW, self::CAP_MANAGE ),
			),
		);
	}

	public static function install() {
		foreach ( self::definitions() as $role => $def ) {
			remove_role( $role );
			$caps = array( 'read' => true );
			foreach ( $def['caps'] as $cap ) {
				$caps[ $cap ] = true;
			}
			add_role( $role, $def['label'], $caps );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array( self::CAP_ACCESS, self::CAP_REVIEW, self::CAP_MANAGE ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	public static function role_label( $user ) {
		$defs = self::definitions();
		foreach ( (array) $user->roles as $role ) {
			if ( isset( $defs[ $role ] ) ) {
				return $defs[ $role ]['label'];
			}
			if ( 'administrator' === $role ) {
				return 'Διαχειριστής';
			}
		}
		return '';
	}

	/**
	 * Χρήστες της εφαρμογής (όσοι έχουν ρόλο της), ταξινομημένοι κατά όνομα.
	 */
	public static function app_users() {
		return get_users(
			array(
				'role__in' => array_merge( array_keys( self::definitions() ), array( 'administrator' ) ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);
	}
}
