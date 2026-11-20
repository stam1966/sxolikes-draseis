<?php
/**
 * Πίνακες της εφαρμογής και πρόσβαση στα δεδομένα.
 *
 * Όλα τα ετήσια δεδομένα ανήκουν σε σχολικό έτος. Η απεγκατάσταση δεν
 * διαγράφει τίποτα.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Data {

	/** Τάξεις Δημοτικού: αριθμός => ετικέτα. */
	const GRADES = array(
		1 => 'Α΄',
		2 => 'Β΄',
		3 => 'Γ΄',
		4 => 'Δ΄',
		5 => 'Ε΄',
		6 => 'ΣΤ΄',
	);

	/** Εβδομαδιαίες ώρες Εργαστηρίων σε 6/θέσια και άνω (ΦΕΚ Β΄ 3990/2026). */
	const WORKSHOP_HOURS = array(
		1 => 3,
		2 => 3,
		3 => 2,
		4 => 2,
		5 => 1,
		6 => 1,
	);

	/** Ρόλοι εκπαιδευτικών σε ένα τμήμα. */
	const CLASS_ROLES = array(
		'ergastiria' => 'Υπεύθυνος/η Εργαστηρίων',
		'pe70'       => 'Εκπαιδευτικός ΠΕ70 τμήματος',
		'allos'      => 'Άλλος/η εκπαιδευτικός',
	);

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'sd_' . $name;
	}

	public static function schema() {
		global $wpdb;
		$c       = $wpdb->get_charset_collate();
		$years   = self::table( 'years' );
		$classes = self::table( 'classes' );
		$ct      = self::table( 'class_teachers' );

		return array(
			"CREATE TABLE $years (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				label varchar(20) NOT NULL,
				starts_on date NOT NULL,
				ends_on date NOT NULL,
				is_active tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY label (label)
			) $c;",
			"CREATE TABLE $classes (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				year_id bigint(20) unsigned NOT NULL,
				grade tinyint(3) unsigned NOT NULL,
				section varchar(10) NOT NULL DEFAULT '',
				workshop_hours tinyint(3) unsigned NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY year_grade_section (year_id,grade,section),
				KEY year_id (year_id)
			) $c;",
			"CREATE TABLE $ct (
				class_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				class_role varchar(20) NOT NULL,
				PRIMARY KEY  (class_id,user_id,class_role),
				KEY user_id (user_id)
			) $c;",
			'CREATE TABLE ' . self::table( 'plans' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				year_id bigint(20) unsigned NOT NULL,
				code varchar(10) NOT NULL DEFAULT '',
				title varchar(255) NOT NULL DEFAULT '',
				axis tinyint(3) unsigned NOT NULL DEFAULT 1,
				indicators varchar(255) NOT NULL DEFAULT '',
				purpose text NOT NULL,
				need text NOT NULL,
				coordinator_id bigint(20) unsigned NOT NULL DEFAULT 0,
				team_note varchar(255) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY year_id (year_id)
			) $c;",
			'CREATE TABLE ' . self::table( 'plan_members' ) . " (
				plan_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				PRIMARY KEY  (plan_id,user_id),
				KEY user_id (user_id)
			) $c;",
			'CREATE TABLE ' . self::table( 'goals' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				plan_id bigint(20) unsigned NOT NULL,
				ord tinyint(3) unsigned NOT NULL DEFAULT 1,
				title varchar(255) NOT NULL DEFAULT '',
				description text NOT NULL,
				tools varchar(255) NOT NULL DEFAULT '',
				tools_other varchar(400) NOT NULL DEFAULT '',
				ways varchar(255) NOT NULL DEFAULT '',
				ways_other varchar(400) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY plan_id (plan_id)
			) $c;",
			'CREATE TABLE ' . self::table( 'f1a' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				year_id bigint(20) unsigned NOT NULL,
				class_id bigint(20) unsigned NOT NULL,
				status varchar(12) NOT NULL DEFAULT 'proxeiro',
				review_note text NOT NULL,
				submitted_by bigint(20) unsigned NOT NULL DEFAULT 0,
				submitted_at datetime DEFAULT NULL,
				reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
				reviewed_at datetime DEFAULT NULL,
				updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY class_id (class_id),
				KEY year_id (year_id)
			) $c;",
			'CREATE TABLE ' . self::table( 'f1a_units' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				f1a_id bigint(20) unsigned NOT NULL,
				unit_no tinyint(3) unsigned NOT NULL,
				cycle tinyint(3) unsigned NOT NULL DEFAULT 0,
				title varchar(255) NOT NULL DEFAULT '',
				weeks tinyint(3) unsigned NOT NULL DEFAULT 0,
				skills varchar(100) NOT NULL DEFAULT '',
				goals text NOT NULL,
				activities text NOT NULL,
				material varchar(500) NOT NULL DEFAULT '',
				action_id bigint(20) unsigned NOT NULL DEFAULT 0,
				citizen_hours tinyint(3) unsigned NOT NULL DEFAULT 0,
				visits text NOT NULL,
				deliverable varchar(500) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				UNIQUE KEY f1a_unit (f1a_id,unit_no)
			) $c;",
			'CREATE TABLE ' . self::table( 'citizen' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				year_id bigint(20) unsigned NOT NULL,
				class_id bigint(20) unsigned NOT NULL,
				owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(12) NOT NULL DEFAULT 'proxeiro',
				title varchar(255) NOT NULL DEFAULT '',
				title_provisional tinyint(1) NOT NULL DEFAULT 1,
				kind varchar(12) NOT NULL DEFAULT 'alli',
				kepea tinyint(1) NOT NULL DEFAULT 0,
				kepea_note varchar(255) NOT NULL DEFAULT '',
				partners text NOT NULL,
				sdgs varchar(60) NOT NULL DEFAULT '',
				action_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action2_id bigint(20) unsigned NOT NULL DEFAULT 0,
				f1a_unit tinyint(3) unsigned NOT NULL DEFAULT 0,
				description text NOT NULL,
				outcomes text NOT NULL,
				hours_rows longtext NOT NULL,
				places varchar(100) NOT NULL DEFAULT '',
				audience varchar(100) NOT NULL DEFAULT '',
				stages longtext NOT NULL,
				visit text NOT NULL,
				visitor text NOT NULL,
				diffusion varchar(100) NOT NULL DEFAULT '',
				diffusion_other varchar(255) NOT NULL DEFAULT '',
				evidence varchar(100) NOT NULL DEFAULT '',
				ai_notes longtext NOT NULL,
				review_note text NOT NULL,
				submitted_by bigint(20) unsigned NOT NULL DEFAULT 0,
				submitted_at datetime DEFAULT NULL,
				reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
				reviewed_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY year_id (year_id),
				KEY class_id (class_id)
			) $c;",
			'CREATE TABLE ' . self::table( 'goal_actions' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				goal_id bigint(20) unsigned NOT NULL,
				kind varchar(12) NOT NULL DEFAULT 'ylopoiisi',
				ord tinyint(3) unsigned NOT NULL DEFAULT 1,
				description text NOT NULL,
				start_date date DEFAULT NULL,
				end_date date DEFAULT NULL,
				criterion text NOT NULL,
				grades varchar(20) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY goal_id (goal_id)
			) $c;",
		);
	}

	/* ---------- Σχολικά έτη ---------- */

	public static function years() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table( 'years' ) . ' ORDER BY starts_on DESC' );
	}

	public static function active_year() {
		global $wpdb;
		return $wpdb->get_row( 'SELECT * FROM ' . self::table( 'years' ) . ' WHERE is_active = 1 LIMIT 1' );
	}

	/**
	 * Σχολικό έτος που ξεκινά την 1η Σεπτεμβρίου του $start_year.
	 */
	public static function add_year( $start_year ) {
		global $wpdb;
		$start_year = (int) $start_year;
		if ( $start_year < 2020 || $start_year > 2100 ) {
			return new WP_Error( 'sd_year', 'Μη έγκυρο έτος.' );
		}
		$label = $start_year . '-' . ( $start_year + 1 );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'years' ) . ' WHERE label = %s', $label ) );
		if ( $exists ) {
			return new WP_Error( 'sd_year', 'Το σχολικό έτος ' . $label . ' υπάρχει ήδη.' );
		}
		$wpdb->insert(
			self::table( 'years' ),
			array(
				'label'      => $label,
				'starts_on'  => $start_year . '-09-01',
				'ends_on'    => ( $start_year + 1 ) . '-08-31',
				'is_active'  => self::active_year() ? 0 : 1,
				'created_at' => current_time( 'mysql' ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function set_active_year( $year_id ) {
		global $wpdb;
		$year_id = (int) $year_id;
		$found   = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'years' ) . ' WHERE id = %d', $year_id ) );
		if ( ! $found ) {
			return new WP_Error( 'sd_year', 'Το σχολικό έτος δεν βρέθηκε.' );
		}
		$wpdb->query( 'UPDATE ' . self::table( 'years' ) . ' SET is_active = 0' );
		$wpdb->update( self::table( 'years' ), array( 'is_active' => 1 ), array( 'id' => $year_id ) );
		return true;
	}

	/* ---------- Τμήματα ---------- */

	public static function class_label( $class ) {
		$grade = isset( self::GRADES[ (int) $class->grade ] ) ? self::GRADES[ (int) $class->grade ] : '?';
		// Με τμήμα: «Α1», «ΣΤ2» (χωρίς το σημάδι ΄). Πολυβυτικό, άρα όχι rtrim().
		return '' === $class->section ? $grade : preg_replace( '/΄$/u', '', $grade ) . $class->section;
	}

	public static function classes( $year_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table( 'classes' ) . ' WHERE year_id = %d ORDER BY grade, section', $year_id )
		);
	}

	public static function get_class( $class_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'classes' ) . ' WHERE id = %d', $class_id ) );
	}

	public static function add_class( $year_id, $grade, $section ) {
		global $wpdb;
		$grade   = (int) $grade;
		$section = trim( (string) $section );
		if ( ! isset( self::GRADES[ $grade ] ) ) {
			return new WP_Error( 'sd_class', 'Διαλέξτε τάξη.' );
		}
		if ( ! preg_match( '/^[0-9]{0,2}$/', $section ) ) {
			return new WP_Error( 'sd_class', 'Το τμήμα είναι αριθμός (π.χ. 1, 2) ή κενό αν η τάξη έχει ένα τμήμα.' );
		}
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table( 'classes' ) . ' WHERE year_id = %d AND grade = %d AND section = %s',
				$year_id,
				$grade,
				$section
			)
		);
		if ( $exists ) {
			return new WP_Error( 'sd_class', 'Το τμήμα υπάρχει ήδη.' );
		}
		$wpdb->insert(
			self::table( 'classes' ),
			array(
				'year_id'        => (int) $year_id,
				'grade'          => $grade,
				'section'        => $section,
				'workshop_hours' => self::WORKSHOP_HOURS[ $grade ],
				'created_at'     => current_time( 'mysql' ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete_class( $class_id ) {
		global $wpdb;
		$class_id = (int) $class_id;
		$wpdb->delete( self::table( 'class_teachers' ), array( 'class_id' => $class_id ) );
		$wpdb->delete( self::table( 'classes' ), array( 'id' => $class_id ) );
	}

	/**
	 * Εκπαιδευτικοί τμήματος: [ ['user_id'=>, 'class_role'=>], ... ].
	 */
	public static function class_teachers( $class_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT user_id, class_role FROM ' . self::table( 'class_teachers' ) . ' WHERE class_id = %d', $class_id )
		);
	}

	/**
	 * Αντικαθιστά τους εκπαιδευτικούς ενός ρόλου στο τμήμα.
	 */
	public static function set_class_teachers( $class_id, $class_role, array $user_ids ) {
		global $wpdb;
		if ( ! isset( self::CLASS_ROLES[ $class_role ] ) ) {
			return;
		}
		$wpdb->delete(
			self::table( 'class_teachers' ),
			array(
				'class_id'   => (int) $class_id,
				'class_role' => $class_role,
			)
		);
		foreach ( array_unique( array_map( 'intval', $user_ids ) ) as $uid ) {
			if ( $uid > 0 && get_userdata( $uid ) ) {
				$wpdb->insert(
					self::table( 'class_teachers' ),
					array(
						'class_id'   => (int) $class_id,
						'user_id'    => $uid,
						'class_role' => $class_role,
					)
				);
			}
		}
	}

	/**
	 * Τα τμήματα ενός εκπαιδευτικού στο έτος.
	 */
	public static function classes_of_user( $user_id, $year_id ) {
		global $wpdb;
		$c  = self::table( 'classes' );
		$ct = self::table( 'class_teachers' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT c.* FROM $c c JOIN $ct t ON t.class_id = c.id WHERE t.user_id = %d AND c.year_id = %d ORDER BY c.grade, c.section",
				$user_id,
				$year_id
			)
		);
	}
}
