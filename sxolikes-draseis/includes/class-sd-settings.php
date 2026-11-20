<?php
/**
 * Ρυθμίσεις της Διεύθυνσης: όνομα σχολείου, κάρτα τόπου για τον βοηθό AI,
 * προθεσμία του Εντύπου 1Α.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Settings {

	public static function handle( $do ) {
		if ( ! in_array( $do, array( 'save_settings', 'update_check' ), true ) ) {
			return null;
		}
		$back = SD_Frontend::url( 'rythmiseis' );
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση αλλάζει τις ρυθμίσεις.' ), $back );
		}
		if ( 'update_check' === $do ) {
			$info = SD_Updater::check_now();
			return array( $info ? true : new WP_Error( 'sd', 'Ο έλεγχος για νέα έκδοση απέτυχε. Δείτε την αιτία παρακάτω.' ), $back . '#enimeroseis' );
		}
		$name = isset( $_POST['school_name'] ) ? sanitize_text_field( wp_unslash( $_POST['school_name'] ) ) : '';
		$card = isset( $_POST['place_card'] ) ? sanitize_textarea_field( wp_unslash( $_POST['place_card'] ) ) : '';
		$dl   = isset( $_POST['deadline_f1a'] ) ? sanitize_text_field( wp_unslash( $_POST['deadline_f1a'] ) ) : '';
		if ( '' !== $name ) {
			update_option( 'sd_school_name', $name );
		}
		update_option( SD_Ai::OPT_PLACE, $card );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dl ) ) {
			update_option( SD_Workshops::OPT_DEADLINE, $dl );
		}
		return array( true, $back );
	}

	public static function render() {
		echo '<h2>Ρυθμίσεις</h2>';
		SD_Frontend::form_open( 'save_settings' );
		echo '<section class="sd-card"><label class="sd-block">Όνομα σχολείου (κεφαλίδα και εκτυπώσεις) <input name="school_name" value="' . esc_attr( SD_Frontend::school_name() ) . '"></label>';
		echo '<label class="sd-block">Προθεσμία υποβολής Εντύπου 1Α <input type="date" name="deadline_f1a" value="' . esc_attr( SD_Workshops::deadline() ) . '"></label></section>';
		echo '<section class="sd-card"><h3>Κάρτα τόπου</h3><p class="sd-help">Μνημεία, φυσικοί χώροι, φορείς, ΚΕΠΕΑ και προγράμματα της περιοχής. Μπαίνει σε κάθε prompt του βοηθού AI, ώστε οι προτάσεις να είναι ρεαλιστικές για τον τόπο μας. Χωρίς προσωπικά στοιχεία.</p>';
		echo '<textarea name="place_card" rows="12" placeholder="π.χ.&#10;Μνημεία: Κάστρο Λαμίας, ...&#10;Φύση: Σπερχειός, Μαλιακός κόλπος, Οίτη, ...&#10;Φορείς: Δήμος Λαμιέων, ΚΕΠΕΑ ..., Αρχαιολογικό Μουσείο Λαμίας, ...">' . esc_textarea( SD_Ai::place_card() ) . '</textarea></section>';
		echo '<p><button class="sd-button" type="submit">Αποθήκευση ρυθμίσεων</button></p></form>';

		echo '<section class="sd-card" id="enimeroseis"><h3>Ενημερώσεις της εφαρμογής</h3><p>' . SD_Updater::status_html() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="sd-help">Οι νέες εκδόσεις έρχονται από το GitHub και εμφανίζονται στα «Πρόσθετα» της διαχείρισης του WordPress. Εκεί μπορείτε να ενεργοποιήσετε και τις αυτόματες ενημερώσεις.</p>';
		SD_Frontend::form_open( 'update_check' );
		echo '<button class="sd-button sd-small" type="submit">Έλεγχος τώρα</button></form></section>';
	}
}
