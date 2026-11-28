<?php
/**
 * Χάρτης Τοπικής Ιστορίας και Γεωγραφίας.
 *
 * Δύο πηγές σημείων:
 * - τα ελεγμένα δελτία του σχολείου (data/topoi.json, έρχονται με το πρόσθετο),
 * - τα σημεία που προσθέτουν οι τάξεις (πίνακας sd_places). Εμφανίζονται στον
 *   δημόσιο χάρτη μόνο αφού τα εγκρίνει η Επιτροπή ή η Διεύθυνση.
 *
 * Ο δημόσιος χάρτης (?sd_xartis=1) ανοίγει χωρίς σύνδεση και μπαίνει σε
 * αναρτήσεις της ιστοσελίδας με iframe. Δεν δείχνει ονόματα εκπαιδευτικών ή μαθητών.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Map {

	const STATUS = array(
		'proxeiro'     => 'Πρόχειρο',
		'ypovlithike'  => 'Υποβλήθηκε για έγκριση',
		'epistrafike'  => 'Επιστράφηκε για διόρθωση',
		'dimosieftike' => 'Δημοσιεύτηκε',
	);

	/** Όρια περιοχής (Φθιώτιδα και γύρω): σημεία εκτός αυτών απορρίπτονται. */
	const BOUNDS = array( 38.30, 21.60, 39.40, 23.60 );

	const LIMIT_SUMMARY  = 600;
	const LIMIT_ACTIVITY = 300;
	const PHOTO_MAX      = 4194304; // 4 MB

	/** Φωτογραφίες των ελεγμένων τόπων: [κλειδί τόπου => [[att, credit, caption], …]]. */
	const OPT_PHOTOS = 'sd_place_photos';
	const PHOTOS_MAX = 6;

	private static $official = null;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'public_page' ), 1 );
	}

	public static function t() {
		return SD_Data::table( 'places' );
	}

	/* =================== Δεδομένα =================== */

	/** Τα ελεγμένα δελτία του πρόσθετου. */
	public static function official() {
		if ( null === self::$official ) {
			$raw            = file_get_contents( SD_DIR . 'data/topoi.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			self::$official = $raw ? json_decode( $raw, true ) : array();
			if ( ! is_array( self::$official ) ) {
				self::$official = array();
			}
		}
		return self::$official;
	}

	public static function cats() {
		$o = self::official();
		return isset( $o['categories'] ) ? $o['categories'] : array();
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ) );
	}

	public static function of_class( $class_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE class_id = %d ORDER BY name', $class_id ) );
	}

	public static function of_year( $year_id, $status = '' ) {
		global $wpdb;
		if ( $status ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE year_id = %d AND status = %s ORDER BY class_id, name', $year_id, $status ) );
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE year_id = %d ORDER BY class_id, name', $year_id ) );
	}

	public static function pending_count( $year_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . " WHERE year_id = %d AND status = 'ypovlithike'", $year_id ) );
	}

	/**
	 * Σημεία τάξεων για τον χάρτη, σε μορφή ίδια με τα δελτία.
	 * Δημόσια: μόνο τα δημοσιευμένα. Στην εφαρμογή: και τα πρόχειρα των δικών
	 * μου τμημάτων· η Επιτροπή και η Διεύθυνση βλέπουν όλα.
	 */
	private static function class_points( $public ) {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::t() . ' ORDER BY id' );
		if ( $public || ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
			$mine = array();
			if ( ! $public && is_user_logged_in() ) {
				foreach ( SD_Data::years() as $y ) {
					foreach ( SD_Data::classes_of_user( get_current_user_id(), $y->id ) as $c ) {
						$mine[ (int) $c->id ] = true;
					}
				}
			}
			$rows = array_values(
				array_filter(
					$rows,
					function ( $r ) use ( $mine ) {
						return 'dimosieftike' === $r->status || isset( $mine[ (int) $r->class_id ] );
					}
				)
			);
		}
		$years = array();
		foreach ( SD_Data::years() as $y ) {
			$years[ (int) $y->id ] = $y->label;
		}
		$out = array();
		foreach ( $rows as $r ) {
			$class = SD_Data::get_class( $r->class_id );
			$photo = $r->photo_id ? wp_get_attachment_image_url( (int) $r->photo_id, 'medium_large' ) : '';
			$out[] = array(
				'id'        => 'taxi-' . (int) $r->id,
				'name'      => $r->name,
				'lat'       => (float) $r->lat,
				'lng'       => (float) $r->lng,
				'approx'    => (bool) $r->approx,
				'cat'       => $r->cat,
				'period'    => $r->period,
				'summary'   => $r->summary,
				'activity'  => $r->activity,
				'certainty' => '',
				'grades'    => $class ? array( (int) $class->grade ) : array(),
				'visit'     => '',
				'sources'   => self::source_list( $r->sources ),
				'byClass'   => $class ? SD_Data::class_label( $class ) : '',
				'year'      => isset( $years[ (int) $r->year_id ] ) ? $years[ (int) $r->year_id ] : '',
				'photo'     => $photo ? $photo : '',
				'credit'    => $r->photo_credit,
				'status'    => $r->status,
			);
		}
		return $out;
	}

	/** Γραμμές πηγών: μόνο http(s) σύνδεσμοι μένουν σύνδεσμοι· τα άλλα ως κείμενο. */
	private static function source_list( $text ) {
		$out = array();
		foreach ( preg_split( '/\R/u', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return array_slice( $out, 0, 8 );
	}

	/** Τα δεδομένα που παίρνει το sd-map.js. */
	public static function payload( $public ) {
		$o = self::official();
		return array(
			'school'  => isset( $o['school'] ) ? $o['school'] : null,
			'cats'    => self::cats(),
			'routes'  => isset( $o['routes'] ) ? $o['routes'] : array(),
			'borders' => isset( $o['borders'] ) ? $o['borders'] : array(),
			'admin'   => isset( $o['admin_history'] ) ? $o['admin_history'] : array(),
			'nomoi'   => isset( $o['nomoi'] ) ? $o['nomoi'] : array(),
			'coasts'  => isset( $o['coastlines'] ) ? $o['coastlines'] : array(),
			'places'  => array_merge( self::with_photos( isset( $o['places'] ) ? $o['places'] : array() ), self::class_points( $public ) ),
			'periods' => isset( $o['periods'] ) ? $o['periods'] : array(),
			'units'   => isset( $o['units'] ) ? $o['units'] : array(),
			'valley'  => isset( $o['valley'] ) ? $o['valley'] : null,
			'checked' => isset( $o['checked'] ) ? $o['checked'] : '',
			'public'  => (bool) $public,
			'schoolName' => SD_Frontend::school_name(),
		);
	}

	/** Οι φωτογραφίες που ανέβασε η Διεύθυνση για κάθε ελεγμένο τόπο. */
	public static function photos() {
		$all = get_option( self::OPT_PHOTOS, array() );
		return is_array( $all ) ? $all : array();
	}

	private static function with_photos( $places ) {
		$all = self::photos();
		foreach ( $places as &$p ) {
			$list = array();
			foreach ( $all[ $p['id'] ] ?? array() as $ph ) {
				$src = wp_get_attachment_image_url( (int) $ph['att'], 'large' );
				if ( $src ) {
					$list[] = array(
						'src'     => $src,
						'credit'  => (string) $ph['credit'],
						'caption' => (string) $ph['caption'],
					);
				}
			}
			if ( $list ) {
				$p['photos'] = $list;
			}
		}
		unset( $p );
		return $places;
	}

	/* =================== Δικαιώματα =================== */

	public static function can_edit( $place, $class ) {
		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return true;
		}
		$status = $place ? $place->status : 'proxeiro';
		return $class && SD_Workshops::is_class_teacher( $class->id ) && in_array( $status, array( 'proxeiro', 'epistrafike' ), true );
	}

	private static function editable_classes( $year ) {
		return current_user_can( SD_Roles::CAP_MANAGE ) ? SD_Data::classes( $year->id ) : SD_Data::classes_of_user( get_current_user_id(), $year->id );
	}

	/* =================== Ενέργειες (POST) =================== */

	private static function text( $field, $limit = 0, $multiline = true ) {
		$v = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security
		$v = $multiline ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
		return $limit ? mb_substr( $v, 0, $limit ) : $v;
	}

	public static function handle( $do ) {
		global $wpdb;
		if ( in_array( $do, array( 'official_photo_add', 'official_photo_delete' ), true ) ) {
			return self::handle_official_photo( $do );
		}
		if ( ! in_array( $do, array( 'place_save', 'place_submit', 'place_review', 'place_delete', 'place_unpublish' ), true ) ) {
			return null;
		}
		$list  = SD_Frontend::url( 'xartis' );
		$year  = SD_Data::active_year();
		$id    = isset( $_POST['place_id'] ) ? absint( $_POST['place_id'] ) : 0;
		$place = $id ? self::get( $id ) : null;
		if ( $id && ! $place ) {
			return array( new WP_Error( 'sd', 'Το σημείο δεν βρέθηκε.' ), $list );
		}

		if ( 'place_save' === $do ) {
			$class_id = $place ? (int) $place->class_id : ( isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0 );
			$class    = SD_Data::get_class( $class_id );
			if ( ! $class || ! $year || ( ! $place && (int) $class->year_id !== (int) $year->id ) || ! self::can_edit( $place, $class ) ) {
				return array( new WP_Error( 'sd', 'Δεν μπορείτε να αλλάξετε σημεία αυτού του τμήματος.' ), $list );
			}
			$back = $place ? SD_Frontend::url( 'xartis', array( 'edit' => $place->id ) ) : SD_Frontend::url( 'xartis', array( 'nea' => 1 ) );
			$name = self::text( 'name', 120, false );
			$lat  = isset( $_POST['lat'] ) ? (float) str_replace( ',', '.', wp_unslash( $_POST['lat'] ) ) : 0; // phpcs:ignore WordPress.Security
			$lng  = isset( $_POST['lng'] ) ? (float) str_replace( ',', '.', wp_unslash( $_POST['lng'] ) ) : 0; // phpcs:ignore WordPress.Security
			$cat  = isset( $_POST['cat'] ) ? sanitize_key( wp_unslash( $_POST['cat'] ) ) : '';
			if ( '' === $name ) {
				return array( new WP_Error( 'sd', 'Γράψτε όνομα για το σημείο.' ), $back );
			}
			if ( ! isset( self::cats()[ $cat ] ) ) {
				return array( new WP_Error( 'sd', 'Διαλέξτε κατηγορία.' ), $back );
			}
			list( $s, $w, $n, $e ) = self::BOUNDS;
			if ( $lat < $s || $lat > $n || $lng < $w || $lng > $e ) {
				return array( new WP_Error( 'sd', 'Διαλέξτε τη θέση πάνω στον χάρτη (μέσα στη Φθιώτιδα).' ), $back );
			}
			$citizen_id = isset( $_POST['citizen_id'] ) ? absint( $_POST['citizen_id'] ) : 0;
			if ( $citizen_id ) {
				$dep = SD_Citizen::get( $citizen_id );
				if ( ! $dep || (int) $dep->class_id !== (int) $class->id ) {
					$citizen_id = 0;
				}
			}
			$data = array(
				'name'         => $name,
				'lat'          => round( $lat, 6 ),
				'lng'          => round( $lng, 6 ),
				'approx'       => empty( $_POST['approx'] ) ? 0 : 1,
				'cat'          => $cat,
				'period'       => self::text( 'period', 120, false ),
				'summary'      => self::text( 'summary', self::LIMIT_SUMMARY ),
				'activity'     => self::text( 'activity', self::LIMIT_ACTIVITY ),
				'sources'      => self::text( 'sources', 1500 ),
				'photo_credit' => self::text( 'photo_credit', 200, false ),
				'citizen_id'   => $citizen_id,
				'updated_at'   => current_time( 'mysql' ),
			);
			if ( $place ) {
				$wpdb->update( self::t(), $data, array( 'id' => $place->id ) );
				$pid = (int) $place->id;
			} else {
				$data += array(
					'year_id'     => (int) $year->id,
					'class_id'    => (int) $class->id,
					'owner_id'    => get_current_user_id(),
					'status'      => 'proxeiro',
					'review_note' => '',
					'created_at'  => current_time( 'mysql' ),
				);
				$wpdb->insert( self::t(), $data );
				$pid = (int) $wpdb->insert_id;
			}
			$back = SD_Frontend::url( 'xartis', array( 'edit' => $pid ) );

			if ( ! empty( $_POST['photo_remove'] ) ) {
				$wpdb->update( self::t(), array( 'photo_id' => 0 ), array( 'id' => $pid ) );
			}
			if ( ! empty( $_FILES['photo']['name'] ) ) {
				$photo = self::upload_photo( $pid );
				if ( is_wp_error( $photo ) ) {
					return array( $photo, $back );
				}
			}
			return array( true, $back );
		}

		$class = $place ? SD_Data::get_class( $place->class_id ) : null;
		$back  = $place ? SD_Frontend::url( 'xartis', array( 'edit' => $place->id ) ) : $list;

		if ( 'place_submit' === $do ) {
			if ( ! $place || ! self::can_edit( $place, $class ) ) {
				return array( new WP_Error( 'sd', 'Δεν μπορείτε να υποβάλετε αυτό το σημείο.' ), $list );
			}
			if ( '' === trim( $place->summary ) ) {
				return array( new WP_Error( 'sd', 'Συμπληρώστε το «Τι έγινε εδώ» πριν την υποβολή.' ), $back );
			}
			$wpdb->update(
				self::t(),
				array(
					'status'       => 'ypovlithike',
					'submitted_at' => current_time( 'mysql' ),
				),
				array( 'id' => $place->id )
			);
			SD_Notify::submitted( 'Σημείο χάρτη «' . $place->name . '»', (int) $place->class_id, $back );
			return array( true, $back );
		}

		if ( 'place_review' === $do ) {
			if ( ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
				return array( new WP_Error( 'sd', 'Μόνο η Επιτροπή και η Διεύθυνση εγκρίνουν σημεία.' ), $back );
			}
			if ( ! $place || 'ypovlithike' !== $place->status ) {
				return array( new WP_Error( 'sd', 'Το σημείο δεν έχει υποβληθεί.' ), $back );
			}
			if ( ! SD_Notify::may_review( 0, (int) $place->class_id ) ) {
				return array( new WP_Error( 'sd', SD_Notify::why_not( 0, (int) $place->class_id ) ), $back );
			}
			$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
			$note     = self::text( 'review_note', 1000 );
			if ( 'return' === $decision && '' === trim( $note ) ) {
				return array( new WP_Error( 'sd', 'Γράψτε τι χρειάζεται διόρθωση.' ), $back );
			}
			$wpdb->update(
				self::t(),
				array(
					'status'      => 'return' === $decision ? 'epistrafike' : 'dimosieftike',
					'review_note' => $note,
					'reviewed_by' => get_current_user_id(),
					'reviewed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $place->id )
			);
			SD_Notify::decided( 'Σημείο χάρτη «' . $place->name . '»' . ( 'return' === $decision ? '' : ' (δημοσιεύτηκε)' ), (int) $place->class_id, 0, 'return' === $decision, $note, $back );
			return array( true, $back );
		}

		if ( 'place_unpublish' === $do ) {
			if ( ! $place || ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
				return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση αποσύρει δημοσιευμένα σημεία.' ), $back );
			}
			$wpdb->update( self::t(), array( 'status' => 'epistrafike' ), array( 'id' => $place->id ) );
			return array( true, $back );
		}

		// place_delete
		if ( ! $place || ! self::can_edit( $place, $class ) ) {
			return array( new WP_Error( 'sd', 'Δεν μπορείτε να διαγράψετε αυτό το σημείο.' ), $list );
		}
		if ( $place->photo_id ) {
			wp_delete_attachment( (int) $place->photo_id, true );
		}
		$wpdb->delete( self::t(), array( 'id' => $place->id ) );
		return array( true, $list );
	}

	/** Φωτογραφίες ελεγμένων τόπων: μόνο η Διεύθυνση, με δήλωση δικαιωμάτων και δημιουργό. */
	private static function handle_official_photo( $do ) {
		$back = SD_Frontend::url( 'xartis', array( 'fotografies' => 1 ) );
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση προσθέτει φωτογραφίες στους ελεγμένους τόπους.' ), $back );
		}
		$key = isset( $_POST['place_key'] ) ? sanitize_key( wp_unslash( $_POST['place_key'] ) ) : '';
		$ids = wp_list_pluck( self::official()['places'] ?? array(), 'id' );
		if ( ! in_array( $key, $ids, true ) ) {
			return array( new WP_Error( 'sd', 'Διαλέξτε τόπο.' ), $back );
		}
		$all  = self::photos();
		$list = $all[ $key ] ?? array();
		if ( 'official_photo_delete' === $do ) {
			$i = isset( $_POST['idx'] ) ? absint( $_POST['idx'] ) : -1;
			if ( isset( $list[ $i ] ) ) {
				wp_delete_attachment( (int) $list[ $i ]['att'], true );
				array_splice( $list, $i, 1 );
			}
			$all[ $key ] = $list;
			update_option( self::OPT_PHOTOS, array_filter( $all ), false );
			return array( true, $back . '#t-' . $key );
		}
		if ( count( $list ) >= self::PHOTOS_MAX ) {
			return array( new WP_Error( 'sd', 'Έως ' . self::PHOTOS_MAX . ' φωτογραφίες ανά τόπο.' ), $back );
		}
		$credit = isset( $_POST['credit'] ) ? sanitize_text_field( wp_unslash( $_POST['credit'] ) ) : '';
		if ( '' === $credit ) {
			return array( new WP_Error( 'sd', 'Γράψτε ποιος τράβηξε τη φωτογραφία ή από πού είναι (π.χ. «Αρχείο σχολείου» ή «Wikimedia Commons, CC BY-SA»).' ), $back );
		}
		$att = self::store_upload();
		if ( is_wp_error( $att ) ) {
			return array( $att, $back );
		}
		$list[]      = array(
			'att'     => (int) $att,
			'credit'  => mb_substr( $credit, 0, 160 ),
			'caption' => mb_substr( isset( $_POST['caption'] ) ? sanitize_text_field( wp_unslash( $_POST['caption'] ) ) : '', 0, 200 ),
		);
		$all[ $key ] = $list;
		update_option( self::OPT_PHOTOS, $all, false );
		return array( true, $back . '#t-' . $key );
	}

	/** Ανέβασμα εικόνας (JPG/PNG/WEBP, έως 4 MB) με δήλωση· επιστρέφει το attachment. */
	private static function store_upload() {
		if ( empty( $_POST['photo_ok'] ) ) {
			return new WP_Error( 'sd', 'Για να ανέβει φωτογραφία, επιβεβαιώστε ότι έχετε δικαίωμα χρήσης και ότι δεν δείχνει πρόσωπα μαθητών.' );
		}
		$size = isset( $_FILES['photo']['size'] ) ? (int) $_FILES['photo']['size'] : 0;
		if ( $size <= 0 || $size > self::PHOTO_MAX ) {
			return new WP_Error( 'sd', 'Η φωτογραφία πρέπει να είναι έως 4 MB.' );
		}
		$mimes = array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
		);
		$name  = isset( $_FILES['photo']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['photo']['name'] ) ) : '';
		$check = wp_check_filetype( $name, $mimes );
		if ( ! $check['type'] ) {
			return new WP_Error( 'sd', 'Επιτρέπονται μόνο φωτογραφίες JPG, PNG ή WEBP.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_handle_upload( 'photo', 0, array(), array( 'test_form' => false, 'mimes' => $mimes ) );
		if ( is_wp_error( $att ) ) {
			return new WP_Error( 'sd', 'Η φωτογραφία δεν ανέβηκε: ' . $att->get_error_message() );
		}
		return $att;
	}

	/** Οθόνη: φωτογραφίες των ελεγμένων τόπων (Διεύθυνση). */
	private static function render_photos() {
		echo '<p class="sd-noprint"><a href="' . esc_url( SD_Frontend::url( 'xartis' ) ) . '">← Χάρτης</a></p>';
		echo '<h2>Φωτογραφίες των ελεγμένων τόπων</h2>';
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			echo '<p class="sd-notice sd-error">Μόνο η Διεύθυνση προσθέτει φωτογραφίες στους ελεγμένους τόπους.</p>';
			return;
		}
		echo '<p class="sd-help">Οι φωτογραφίες εμφανίζονται στο πλαίσιο του τόπου, στον χάρτη της εφαρμογής και στον δημόσιο χάρτη. Μόνο δικές σας φωτογραφίες ή με ελεύθερη άδεια (π.χ. Wikimedia Commons), πάντα με τον δημιουργό. Όχι πρόσωπα μαθητών. Έως ' . (int) self::PHOTOS_MAX . ' ανά τόπο, έως 4 MB η καθεμία.</p>';
		$places = self::official()['places'] ?? array();
		usort(
			$places,
			function ( $a, $b ) {
				return strcmp( remove_accents( $a['name'] ), remove_accents( $b['name'] ) );
			}
		);
		$all = self::photos();
		echo '<section class="sd-card"><h3>Νέα φωτογραφία</h3>';
		echo '<form method="post" enctype="multipart/form-data" class="sd-form">';
		wp_nonce_field( SD_Frontend::NONCE );
		echo '<input type="hidden" name="sd_do" value="official_photo_add">';
		echo '<label class="sd-block">Τόπος <select name="place_key" required><option value="">Διαλέξτε…</option>';
		foreach ( $places as $p ) {
			echo '<option value="' . esc_attr( $p['id'] ) . '">' . esc_html( $p['name'] ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="sd-block">Αρχείο JPG, PNG ή WEBP έως 4 MB <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>';
		echo '<label class="sd-block">Δημιουργός / πηγή και άδεια <input name="credit" maxlength="160" required placeholder="π.χ. Αρχείο σχολείου ή Wikimedia Commons, CC BY-SA"></label>';
		echo '<label class="sd-block">Λεζάντα (προαιρετικά) <input name="caption" maxlength="200" placeholder="π.χ. Η γέφυρα της Αλαμάνας από τα νότια"></label>';
		echo '<label class="sd-inline"><input type="checkbox" name="photo_ok" value="1" required> Έχουμε δικαίωμα να τη δημοσιεύσουμε και δεν δείχνει πρόσωπα μαθητών.</label>';
		echo '<p><button class="sd-button" type="submit">Ανέβασμα</button></p></form></section>';
		$any = false;
		foreach ( $places as $p ) {
			$list = $all[ $p['id'] ] ?? array();
			if ( ! $list ) {
				continue;
			}
			$any = true;
			echo '<section class="sd-card" id="t-' . esc_attr( $p['id'] ) . '"><h3>' . esc_html( $p['name'] ) . '</h3><div class="sd-photo-grid">';
			foreach ( $list as $i => $ph ) {
				$src = wp_get_attachment_image_url( (int) $ph['att'], 'medium' );
				echo '<figure>' . ( $src ? '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $ph['caption'] ? $ph['caption'] : $p['name'] ) . '" loading="lazy">' : '' ) . '<figcaption>' . esc_html( $ph['caption'] ) . ' <span class="sd-meta">' . esc_html( $ph['credit'] ) . '</span></figcaption>';
				SD_Frontend::form_open( 'official_photo_delete' );
				echo '<input type="hidden" name="place_key" value="' . esc_attr( $p['id'] ) . '"><input type="hidden" name="idx" value="' . (int) $i . '"><button class="sd-button sd-small sd-quiet" type="submit" onclick="return confirm(\'Οριστική διαγραφή της φωτογραφίας; Δεν αναιρείται.\');">Διαγραφή</button></form></figure>';
			}
			echo '</div></section>';
		}
		if ( ! $any ) {
			echo '<p class="sd-notice">Δεν έχει ανέβει ακόμη φωτογραφία σε ελεγμένο τόπο.</p>';
		}
	}

	/** Φωτογραφία σημείου: μόνο εικόνα, έως 4 MB, με δήλωση ότι είναι δική μας. */
	private static function upload_photo( $place_id ) {
		global $wpdb;
		if ( empty( $_POST['photo_ok'] ) ) {
			return new WP_Error( 'sd', 'Για να ανέβει φωτογραφία, επιβεβαιώστε ότι είναι δική σας και δεν δείχνει πρόσωπα μαθητών.' );
		}
		$size = isset( $_FILES['photo']['size'] ) ? (int) $_FILES['photo']['size'] : 0;
		if ( $size <= 0 || $size > self::PHOTO_MAX ) {
			return new WP_Error( 'sd', 'Η φωτογραφία πρέπει να είναι έως 4 MB.' );
		}
		$name  = isset( $_FILES['photo']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['photo']['name'] ) ) : '';
		$check = wp_check_filetype( $name, array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
		) );
		if ( ! $check['type'] ) {
			return new WP_Error( 'sd', 'Επιτρέπονται μόνο φωτογραφίες JPG, PNG ή WEBP.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_handle_upload(
			'photo',
			0,
			array(),
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'webp'     => 'image/webp',
				),
			)
		);
		if ( is_wp_error( $att ) ) {
			return new WP_Error( 'sd', 'Η φωτογραφία δεν ανέβηκε: ' . $att->get_error_message() );
		}
		$old = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT photo_id FROM ' . self::t() . ' WHERE id = %d', $place_id ) );
		if ( $old ) {
			wp_delete_attachment( $old, true );
		}
		$wpdb->update( self::t(), array( 'photo_id' => (int) $att ), array( 'id' => $place_id ) );
		return true;
	}

	/* =================== Οθόνες στην εφαρμογή =================== */

	public static function render() {
		$year = SD_Data::active_year();
		if ( isset( $_GET['nea'] ) || isset( $_GET['edit'] ) ) {
			self::render_form( isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0 );
			return;
		}
		if ( isset( $_GET['paradotea'] ) ) {
			self::render_deliverables();
			return;
		}
		if ( isset( $_GET['fotografies'] ) ) {
			self::render_photos();
			return;
		}

		echo '<h2>Χάρτης Τοπικής Ιστορίας και Γεωγραφίας</h2>';
		echo '<p class="sd-help">Ελεγμένα δελτία τόπων (έλεγχος ' . esc_html( mysql2date( 'd/m/Y', self::official()['checked'] ?? '' ) ) . ') και τα σημεία που προσθέτουν οι τάξεις. Στην τάξη: «Προβολή» για μεγάλα γράμματα στον προβολέα, «Διαδρομές» για ξενάγηση βήμα-βήμα.</p>';
		self::map_box( false, 'sd-map-app' );
		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			echo '<p><a class="sd-button sd-small sd-quiet" href="' . esc_url( SD_Frontend::url( 'xartis', array( 'fotografies' => 1 ) ) ) . '">Φωτογραφίες των ελεγμένων τόπων</a></p>';
		}

		if ( ! $year ) {
			return;
		}
		$classes = self::editable_classes( $year );
		$review  = current_user_can( SD_Roles::CAP_REVIEW );

		if ( $review ) {
			$pending = self::of_year( $year->id, 'ypovlithike' );
			echo '<section class="sd-card"><h3>Σημεία για έγκριση (' . count( $pending ) . ')</h3>';
			if ( $pending ) {
				echo '<ul class="sd-list">';
				foreach ( $pending as $p ) {
					$c = SD_Data::get_class( $p->class_id );
					echo '<li><a href="' . esc_url( SD_Frontend::url( 'xartis', array( 'edit' => $p->id ) ) ) . '">' . esc_html( $p->name ) . '</a> · ' . esc_html( $c ? SD_Data::class_label( $c ) : '' ) . '</li>';
				}
				echo '</ul>';
			} else {
				echo '<p>Δεν υπάρχουν σημεία που περιμένουν έγκριση.</p>';
			}
			echo '</section>';
		}

		echo '<section class="sd-card"><h3>Τα σημεία των τμημάτων μου</h3>';
		if ( $classes ) {
			echo '<p><a class="sd-button" href="' . esc_url( SD_Frontend::url( 'xartis', array( 'nea' => 1 ) ) ) . '">Νέο σημείο στον χάρτη</a> <a class="sd-button sd-small" href="' . esc_url( SD_Frontend::url( 'xartis', array( 'paradotea' => 1 ) ) ) . '">Παραδοτέα τάξεων (για την αποτίμηση)</a></p>';
			echo '<table class="sd-table"><thead><tr><th>Τμήμα</th><th>Σημείο</th><th>Κατάσταση</th></tr></thead><tbody>';
			$any = false;
			foreach ( $classes as $c ) {
				foreach ( self::of_class( $c->id ) as $p ) {
					$any = true;
					echo '<tr><td>' . esc_html( SD_Data::class_label( $c ) ) . '</td><td><a href="' . esc_url( SD_Frontend::url( 'xartis', array( 'edit' => $p->id ) ) ) . '">' . esc_html( $p->name ) . '</a></td><td>' . self::badge( $p->status ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
			}
			if ( ! $any ) {
				echo '<tr><td colspan="3">Καμία τάξη δεν έχει προσθέσει ακόμη σημείο.</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>Δεν σας έχει αντιστοιχιστεί τμήμα. Μπορείτε να χρησιμοποιείτε τον χάρτη στην τάξη.</p>';
		}
		echo '</section>';

		$public = add_query_arg( 'sd_xartis', '1', home_url( '/' ) );
		$embed  = add_query_arg( array( 'sd_xartis' => '1', 'embed' => '1' ), home_url( '/' ) );
		echo '<section class="sd-card"><h3>Δημόσιος χάρτης και ενσωμάτωση στην ιστοσελίδα</h3>';
		echo '<p>Ο δημόσιος χάρτης ανοίγει χωρίς σύνδεση και δείχνει μόνο τα ελεγμένα δελτία και τα εγκεκριμένα σημεία των τάξεων, χωρίς ονόματα: <a href="' . esc_url( $public ) . '" target="_blank" rel="noopener">' . esc_html( $public ) . '</a></p>';
		$iframe = '<iframe src="' . esc_url( $embed ) . '" title="Χάρτης Τοπικής Ιστορίας" width="100%" height="560" style="border:0" loading="lazy"></iframe>';
		echo '<label class="sd-block">Κώδικας για ανάρτηση στην ιστοσελίδα (μπλοκ «Προσαρμοσμένη HTML»)<textarea rows="3" readonly onclick="this.select()">' . esc_textarea( $iframe ) . '</textarea></label>';
		echo '<p class="sd-help">Για μία μόνο διαδρομή προσθέστε στη διεύθυνση <code>&amp;diadromi=</code> και το κλειδί της (π.χ. <code>1821</code>, <code>irakles</code>, <code>perasmata</code>, <code>antistasi</code>, <code>nera</code>, <code>synoro</code>). Για μία τάξη: <code>&amp;taxi=5</code>. Για συγκεκριμένο σημείο: <code>#thermopyles</code> στο τέλος.</p></section>';
	}

	public static function badge( $status ) {
		$cls = 'dimosieftike' === $status ? ' sd-badge-ok' : ( 'epistrafike' === $status ? ' sd-badge-warn' : '' );
		return '<span class="sd-badge' . $cls . '">' . esc_html( self::STATUS[ $status ] ?? $status ) . '</span>';
	}

	/** Το κουτί του χάρτη (στην εφαρμογή ή στη δημόσια σελίδα). */
	private static function map_box( $public, $id, $extra = array() ) {
		$payload = array_merge( self::payload( $public ), $extra );
		echo '<div class="sd-map-wrap" data-sd-map="' . esc_attr( $id ) . '"></div>';
		echo '<script>window.SD_MAPS=window.SD_MAPS||{};window.SD_MAPS[' . wp_json_encode( $id ) . ']=' . wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function render_form( $id ) {
		$year  = SD_Data::active_year();
		$place = $id ? self::get( $id ) : null;
		if ( $id && ! $place ) {
			echo '<p class="sd-notice sd-error">Το σημείο δεν βρέθηκε.</p>';
			return;
		}
		$class   = $place ? SD_Data::get_class( $place->class_id ) : null;
		$review  = current_user_can( SD_Roles::CAP_REVIEW );
		$mine    = $class ? SD_Workshops::is_class_teacher( $class->id ) : false;
		if ( $place && ! $review && ! $mine && ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε πρόσβαση σε αυτό το σημείο.</p>';
			return;
		}
		$editable = $place ? self::can_edit( $place, $class ) : ( $year && self::editable_classes( $year ) );

		echo '<p><a href="' . esc_url( SD_Frontend::url( 'xartis' ) ) . '">← Χάρτης</a></p>';
		echo '<h2>' . ( $place ? esc_html( $place->name ) : 'Νέο σημείο στον χάρτη' ) . '</h2>';
		if ( $place ) {
			echo '<p>' . self::badge( $place->status ) . ' · Τμήμα ' . esc_html( $class ? SD_Data::class_label( $class ) : '' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $place->review_note && in_array( $place->status, array( 'epistrafike', 'dimosieftike' ), true ) ) {
				echo '<p class="sd-notice ' . ( 'epistrafike' === $place->status ? 'sd-error' : 'sd-ok' ) . '"><strong>Σχόλια ελέγχου:</strong> ' . esc_html( $place->review_note ) . '</p>';
			}
		}
		if ( ! $editable ) {
			if ( ! $place ) {
				echo '<p class="sd-notice sd-error">Δεν σας έχει αντιστοιχιστεί τμήμα για να προσθέσετε σημείο.</p>';
				return;
			}
			self::render_readonly( $place );
		} else {
			self::render_edit( $place, $year );
		}

		if ( $place && $review && 'ypovlithike' === $place->status && SD_Notify::review_gate( 0, (int) $place->class_id, $place->status ) ) {
			echo '<section class="sd-card"><h3>Έγκριση από Επιτροπή / Διεύθυνση</h3><p class="sd-help">Ελέγξτε ότι η πληροφορία έχει πηγή, ότι μύθος και ιστορία είναι χωριστά και ότι η φωτογραφία δεν δείχνει πρόσωπα μαθητών.</p>';
			SD_Frontend::form_open( 'place_review' );
			echo '<input type="hidden" name="place_id" value="' . (int) $place->id . '">';
			echo '<label class="sd-block">Σχόλια (υποχρεωτικά για επιστροφή)<textarea name="review_note" rows="3">' . esc_textarea( $place->review_note ) . '</textarea></label>';
			echo '<p><button class="sd-button" type="submit" name="decision" value="ok">Έγκριση και δημοσίευση</button> <button class="sd-button sd-danger" type="submit" name="decision" value="return">Επιστροφή για διόρθωση</button></p></form></section>';
		}
		if ( $place && 'dimosieftike' === $place->status && current_user_can( SD_Roles::CAP_MANAGE ) ) {
			SD_Frontend::form_open( 'place_unpublish' );
			echo '<input type="hidden" name="place_id" value="' . (int) $place->id . '"><p><button class="sd-button sd-small" type="submit">Απόσυρση από τον δημόσιο χάρτη</button></p></form>';
		}
	}

	private static function render_readonly( $p ) {
		$cats = self::cats();
		echo '<section class="sd-card"><dl class="sd-dl">';
		echo '<dt>Κατηγορία</dt><dd>' . esc_html( $cats[ $p->cat ]['label'] ?? $p->cat ) . '</dd>';
		echo '<dt>Εποχή</dt><dd>' . esc_html( $p->period ) . '</dd>';
		echo '<dt>Τι έγινε εδώ</dt><dd>' . nl2br( esc_html( $p->summary ) ) . '</dd>';
		echo '<dt>Ιδέα δραστηριότητας</dt><dd>' . nl2br( esc_html( $p->activity ) ) . '</dd>';
		echo '<dt>Πηγές</dt><dd>' . nl2br( esc_html( $p->sources ) ) . '</dd>';
		echo '<dt>Θέση</dt><dd>' . esc_html( $p->lat . ', ' . $p->lng ) . ( $p->approx ? ' (ενδεικτική)' : '' ) . '</dd>';
		if ( $p->photo_id ) {
			echo '<dt>Φωτογραφία</dt><dd>' . wp_get_attachment_image( (int) $p->photo_id, 'medium' ) . '<br>' . esc_html( $p->photo_credit ) . '</dd>';
		}
		echo '</dl></section>';
	}

	private static function render_edit( $p, $year ) {
		$cats    = self::cats();
		$classes = $p ? array( SD_Data::get_class( $p->class_id ) ) : self::editable_classes( $year );
		$v       = function ( $f, $d = '' ) use ( $p ) {
			return $p ? $p->$f : $d;
		};

		echo '<form method="post" class="sd-form" enctype="multipart/form-data">';
		wp_nonce_field( SD_Frontend::NONCE );
		echo '<input type="hidden" name="sd_do" value="place_save">';
		if ( $p ) {
			echo '<input type="hidden" name="place_id" value="' . (int) $p->id . '">';
		}
		echo '<section class="sd-card">';
		if ( ! $p ) {
			echo '<label class="sd-block">Τμήμα <select name="class_id" required>';
			foreach ( $classes as $c ) {
				echo '<option value="' . (int) $c->id . '">' . esc_html( SD_Data::class_label( $c ) ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<label class="sd-block">Όνομα σημείου <input name="name" maxlength="120" required value="' . esc_attr( $v( 'name' ) ) . '" placeholder="π.χ. Η βρύση του χωριού μας"></label>';
		echo '<div class="sd-row"><label>Κατηγορία <select name="cat" required><option value="">—</option>';
		foreach ( $cats as $k => $c ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $v( 'cat' ), $k, false ) . '>' . esc_html( $c['label'] ) . '</option>';
		}
		echo '</select></label><label>Εποχή / χρονολογία <input name="period" maxlength="120" value="' . esc_attr( $v( 'period' ) ) . '" placeholder="π.χ. 1821 ή Σήμερα"></label></div>';
		echo '<label class="sd-block">Τι έγινε εδώ / τι βρήκαμε <textarea name="summary" rows="4" data-limit="' . (int) self::LIMIT_SUMMARY . '" placeholder="Λίγες προτάσεις. Χωρίστε τον μύθο από την ιστορία.">' . esc_textarea( $v( 'summary' ) ) . '</textarea></label>';
		echo '<label class="sd-block">Ιδέα δραστηριότητας για άλλες τάξεις <textarea name="activity" rows="2" data-limit="' . (int) self::LIMIT_ACTIVITY . '">' . esc_textarea( $v( 'activity' ) ) . '</textarea></label>';
		echo '<label class="sd-block">Πηγές (μία ανά γραμμή: σύνδεσμος, βιβλίο, μαρτυρία χωρίς όνομα) <textarea name="sources" rows="3">' . esc_textarea( $v( 'sources' ) ) . '</textarea></label>';

		$deps = array();
		foreach ( $classes as $c ) {
			foreach ( SD_Citizen::of_year( $year ? $year->id : 0 ) as $d ) {
				if ( (int) $d->class_id === (int) $c->id ) {
					$deps[] = $d;
				}
			}
		}
		if ( $deps ) {
			echo '<label class="sd-block">Σύνδεση με Δράση Ενεργού Πολίτη (προαιρετικό) <select name="citizen_id"><option value="0">—</option>';
			foreach ( $deps as $d ) {
				echo '<option value="' . (int) $d->id . '"' . selected( (int) $v( 'citizen_id', 0 ), (int) $d->id, false ) . '>' . esc_html( $d->title ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '</section>';

		echo '<section class="sd-card"><h3>Θέση στον χάρτη</h3><p class="sd-help">Κάντε κλικ πάνω στον χάρτη για να βάλετε το σημείο. Μπορείτε να το διορθώσετε με νέο κλικ.</p>';
		echo '<div class="sd-pick" data-sd-pick="1"></div>';
		echo '<div class="sd-row"><label>Πλάτος <input name="lat" inputmode="decimal" required value="' . esc_attr( $v( 'lat' ) ) . '" data-sd-lat></label><label>Μήκος <input name="lng" inputmode="decimal" required value="' . esc_attr( $v( 'lng' ) ) . '" data-sd-lng></label>';
		echo '<label class="sd-inline"><input type="checkbox" name="approx" value="1"' . checked( (int) $v( 'approx', 0 ), 1, false ) . '> Η θέση είναι ενδεικτική</label></div></section>';

		echo '<section class="sd-card"><h3>Φωτογραφία (προαιρετικό)</h3>';
		if ( $p && $p->photo_id ) {
			echo '<p>' . wp_get_attachment_image( (int) $p->photo_id, 'medium' ) . '</p><label class="sd-inline"><input type="checkbox" name="photo_remove" value="1"> Αφαίρεση φωτογραφίας</label>';
		}
		echo '<label class="sd-block">Αρχείο JPG, PNG ή WEBP έως 4 MB <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>';
		echo '<label class="sd-block">Ποιος τράβηξε τη φωτογραφία <input name="photo_credit" maxlength="200" value="' . esc_attr( $v( 'photo_credit' ) ) . '" placeholder="π.χ. Ε΄ τάξη, 2026"></label>';
		echo '<label class="sd-inline"><input type="checkbox" name="photo_ok" value="1"> Η φωτογραφία είναι δική μας και δεν δείχνει πρόσωπα μαθητών.</label></section>';

		echo '<p><button class="sd-button" type="submit">Αποθήκευση</button></p></form>';

		if ( $p ) {
			echo '<div class="sd-row">';
			SD_Frontend::form_open( 'place_submit' );
			echo '<input type="hidden" name="place_id" value="' . (int) $p->id . '"><button class="sd-button" type="submit">Υποβολή για έγκριση</button></form>';
			SD_Frontend::form_open( 'place_delete' );
			echo '<input type="hidden" name="place_id" value="' . (int) $p->id . '"><button class="sd-button sd-danger" type="submit" onclick="return confirm(\'Διαγραφή του σημείου;\');">Διαγραφή</button></form>';
			echo '</div>';
		}
	}

	/** Παραδοτέα τάξεων: τι πρόσθεσε κάθε τμήμα (για την αποτίμηση του ΣΔ και το Έντυπο 4). */
	private static function render_deliverables() {
		$year = SD_Data::active_year();
		echo '<p class="sd-noprint"><a href="' . esc_url( SD_Frontend::url( 'xartis' ) ) . '">← Χάρτης</a> · <a href="#" onclick="window.print();return false;">Εκτύπωση</a></p>';
		if ( ! $year ) {
			return;
		}
		echo '<h2>Χάρτης Τοπικής Ιστορίας · Παραδοτέα τάξεων ' . esc_html( $year->label ) . '</h2>';
		$classes = current_user_can( SD_Roles::CAP_REVIEW ) ? SD_Data::classes( $year->id ) : SD_Data::classes_of_user( get_current_user_id(), $year->id );
		$cats    = self::cats();
		$total   = 0;
		$pub     = 0;
		echo '<table class="sd-table"><thead><tr><th>Τμήμα</th><th>Σημείο</th><th>Κατηγορία</th><th>Κατάσταση</th><th>Δημοσίευση</th></tr></thead><tbody>';
		foreach ( $classes as $c ) {
			$rows = self::of_class( $c->id );
			if ( ! $rows ) {
				echo '<tr><td>' . esc_html( SD_Data::class_label( $c ) ) . '</td><td colspan="4"><span class="sd-missing">Κανένα σημείο</span></td></tr>';
				continue;
			}
			foreach ( $rows as $p ) {
				$total++;
				if ( 'dimosieftike' === $p->status ) {
					$pub++;
				}
				echo '<tr><td>' . esc_html( SD_Data::class_label( $c ) ) . '</td><td>' . esc_html( $p->name ) . '</td><td>' . esc_html( $cats[ $p->cat ]['label'] ?? '' ) . '</td><td>' . esc_html( self::STATUS[ $p->status ] ?? '' ) . '</td><td>' . esc_html( $p->reviewed_at && 'dimosieftike' === $p->status ? mysql2date( 'd/m/Y', $p->reviewed_at ) : '' ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '<p>Σύνολο σημείων τάξεων: <strong>' . (int) $total . '</strong>, δημοσιευμένα: <strong>' . (int) $pub . '</strong>. Ελεγμένα δελτία σχολείου: <strong>' . count( self::official()['places'] ?? array() ) . '</strong>.</p>';
	}

	/* =================== Δημόσια σελίδα =================== */

	public static function public_page() {
		if ( empty( $_GET['sd_xartis'] ) ) {
			return;
		}
		$embed = ! empty( $_GET['embed'] );
		$extra = array(
			'route'   => isset( $_GET['diadromi'] ) ? sanitize_key( wp_unslash( $_GET['diadromi'] ) ) : '',
			'grade'   => isset( $_GET['taxi'] ) ? absint( $_GET['taxi'] ) : 0,
			'period'  => isset( $_GET['periodos'] ) ? sanitize_key( wp_unslash( $_GET['periodos'] ) ) : '',
			'valleyOn' => ! empty( $_GET['koilada'] ),
			'embed'   => $embed,
			'fullUrl' => add_query_arg( 'sd_xartis', '1', home_url( '/' ) ),
		);
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		$title = 'Χάρτης Τοπικής Ιστορίας – ' . SD_Frontend::school_name();
		echo '<!doctype html><html lang="el"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">';
		echo '<title>' . esc_html( $title ) . '</title>';
		echo '<link rel="stylesheet" href="' . esc_url( SD_URL . 'assets/vendor/leaflet/leaflet.css?ver=1.9.4' ) . '">';
		echo '<link rel="stylesheet" href="' . esc_url( SD_URL . 'assets/sd-map.css?ver=' . SD_VERSION ) . '">';
		echo '</head><body class="sd-map-public' . ( $embed ? ' sd-map-embed' : '' ) . '">';
		if ( ! $embed ) {
			echo '<header class="sd-map-head"><strong>Χάρτης Τοπικής Ιστορίας και Γεωγραφίας</strong> <span>' . esc_html( SD_Frontend::school_name() ) . '</span></header>';
		}
		self::map_box( true, 'sd-map-public', $extra );
		echo '<script src="' . esc_url( SD_URL . 'assets/vendor/leaflet/leaflet.js?ver=1.9.4' ) . '"></script>';
		echo '<script src="' . esc_url( SD_URL . 'assets/sd-map.js?ver=' . SD_VERSION ) . '"></script>';
		echo '</body></html>';
		exit;
	}
}
