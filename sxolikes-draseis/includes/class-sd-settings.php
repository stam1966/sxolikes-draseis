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
		if ( ! in_array( $do, array( 'save_settings', 'update_check', 'load_place_card' ), true ) ) {
			return null;
		}
		$back = SD_Frontend::url( 'rythmiseis' );
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση αλλάζει τις ρυθμίσεις.' ), $back );
		}
		if ( 'load_place_card' === $do ) {
			$card = file_get_contents( SD_DIR . 'data/karta-topou.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( ! $card ) {
				return array( new WP_Error( 'sd', 'Η έτοιμη κάρτα τόπου δεν βρέθηκε.' ), $back );
			}
			update_option( SD_Ai::OPT_PLACE, sanitize_textarea_field( $card ) );
			return array( true, $back );
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
		$logo = isset( $_POST['logo_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['logo_url'] ) ), array( 'https', 'http' ) ) : '';
		update_option( 'sd_logo_url', $logo );
		update_option( SD_Ai::OPT_PLACE, $card );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dl ) ) {
			update_option( SD_Workshops::OPT_DEADLINE, $dl );
		}
		$dl4 = isset( $_POST['deadline_f4'] ) ? sanitize_text_field( wp_unslash( $_POST['deadline_f4'] ) ) : '';
		update_option( SD_F4::OPT_DEADLINE, preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dl4 ) ? $dl4 : '' );
		foreach ( array( 'deadline_f2' => SD_Timeline::OPT_DEADLINE_F2, 'deadline_sxtm' => SD_Timeline::OPT_DEADLINE_SXTM ) as $field => $opt ) {
			$v = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			update_option( $opt, preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '' );
		}
		return array( true, $back );
	}

	public static function render() {
		echo '<h2>Ρυθμίσεις</h2>';
		SD_Frontend::form_open( 'save_settings' );
		echo '<section class="sd-card"><label class="sd-block">Όνομα σχολείου (κεφαλίδα και εκτυπώσεις) <input name="school_name" value="' . esc_attr( SD_Frontend::school_name() ) . '"></label>';
		echo '<label class="sd-block">Λογότυπο σχολείου (διεύθυνση εικόνας, π.χ. το εικονίδιο της ιστοσελίδας του σχολείου· κενό = το εικονίδιο του ιστότοπου ή «ΣΔ») <input type="url" name="logo_url" value="' . esc_attr( (string) get_option( 'sd_logo_url', '' ) ) . '" placeholder="https://…/favicon-150x150.png"></label>';
		echo '<label class="sd-block">Προθεσμία υποβολής Εντύπου 1Α <input type="date" name="deadline_f1a" value="' . esc_attr( SD_Workshops::deadline() ) . '"></label>';
		echo '<label class="sd-block">Προθεσμία Εντύπου 2 · Ενεργός Πολίτης (προαιρετικό) <input type="date" name="deadline_f2" value="' . esc_attr( SD_Timeline::deadline_f2() ) . '"></label>';
		echo '<label class="sd-block">Προθεσμία Σχεδίου Δράσης Τμήματος · Πλατφόρμα 21+ (προαιρετικό) <input type="date" name="deadline_sxtm" value="' . esc_attr( SD_Timeline::deadline_sxtm() ) . '"></label>';
		echo '<label class="sd-block">Προθεσμία υποβολής Εντύπου 4 (αποτίμηση, προαιρετικό) <input type="date" name="deadline_f4" value="' . esc_attr( SD_F4::deadline() ) . '"></label>';
		echo '<p class="sd-help">Οι προθεσμίες εμφανίζονται στην αρχική οθόνη κάθε εκπαιδευτικού. Όσα βήματα τις ξεπεράσουν σημειώνονται «Εκπρόθεσμο».</p></section>';
		echo '<section class="sd-card"><h3>Κάρτα τόπου</h3><p class="sd-help">Μνημεία, φυσικοί χώροι, φορείς, ΚΕΠΕΑ και προγράμματα της περιοχής. Μπαίνει σε κάθε prompt του βοηθού AI, ώστε οι προτάσεις να είναι ρεαλιστικές για τον τόπο μας. Χωρίς προσωπικά στοιχεία.</p>';
		echo '<textarea name="place_card" rows="12" placeholder="π.χ.&#10;Μνημεία: Κάστρο Λαμίας, ...&#10;Φύση: Σπερχειός, Μαλιακός κόλπος, Οίτη, ...&#10;Φορείς: Δήμος Λαμιέων, ΚΕΠΕΑ ..., Αρχαιολογικό Μουσείο Λαμίας, ...">' . esc_textarea( SD_Ai::place_card() ) . '</textarea></section>';
		echo '<p><button class="sd-button" type="submit">Αποθήκευση ρυθμίσεων</button></p></form>';

		SD_Frontend::form_open( 'load_place_card' );
		echo '<p class="sd-help">Έτοιμη κάρτα τόπου για τη Ροδίτσα και τη Φθιώτιδα, ελεγμένη με επίσημες πηγές (ίδια βάση με τον Χάρτη τόπου). Αντικαθιστά ό,τι έχει γραφτεί παραπάνω.</p>';
		echo '<p><button class="sd-button sd-small" type="submit" onclick="return confirm(\'Να αντικατασταθεί η κάρτα τόπου με την έτοιμη;\');">Φόρτωση έτοιμης κάρτας τόπου</button></p></form>';

		SD_Notify::render_settings();
		SD_AiField::render_settings();

		echo '<section class="sd-card" id="enimeroseis"><h3>Ενημερώσεις της εφαρμογής</h3><p>' . SD_Updater::status_html() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="sd-help">Οι νέες εκδόσεις έρχονται από το GitHub και εμφανίζονται στα «Πρόσθετα» της διαχείρισης του WordPress. Εκεί μπορείτε να ενεργοποιήσετε και τις αυτόματες ενημερώσεις.</p>';
		SD_Frontend::form_open( 'update_check' );
		echo '<button class="sd-button sd-small" type="submit">Έλεγχος τώρα</button></form></section>';
	}
}
