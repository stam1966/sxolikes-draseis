<?php
/**
 * Βοηθός AI χωρίς API: η εφαρμογή φτιάχνει έτοιμα prompts από τα στοιχεία της
 * δράσης, της τάξης, του Σχεδίου Δράσης και της «κάρτας τόπου» του σχολείου.
 * Ο εκπαιδευτικός τα αντιγράφει σε όποιο εργαλείο AI προτιμά.
 *
 * Στα prompts δεν μπαίνουν ποτέ ονόματα μαθητών ή εκπαιδευτικών.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Ai {

	const OPT_PLACE = 'sd_place_card';

	public static function place_card() {
		return (string) get_option( self::OPT_PLACE, '' );
	}

	private static function action_context( $action_id, $year_id ) {
		if ( ! $action_id ) {
			return '';
		}
		foreach ( SD_Plans::year_actions( $year_id ) as $row ) {
			list( $p, $g, $a ) = $row;
			if ( (int) $a->id === (int) $action_id ) {
				$out  = 'Η δράση συνδέεται με το Σχέδιο Δράσης εσωτερικής αξιολόγησης του σχολείου «' . $p->title . '».';
				$out .= $p->purpose ? "\nΣκοπός του σχεδίου: " . $p->purpose : '';
				$out .= "\nΣτόχος του σχεδίου: " . $g->description;
				$out .= "\nΕνέργεια που υπηρετεί η δράση: " . $a->description;
				$out .= $a->criterion ? "\nΚριτήριο επιτυχίας: " . $a->criterion : '';
				return $out;
			}
		}
		return '';
	}

	private static function common( $dep, $class ) {
		$grade   = SD_Data::GRADES[ (int) $class->grade ] ?? '';
		$allowed = array();
		foreach ( SD_Citizen::GRADE_SUBJECTS[ (int) $class->grade ] ?? array() as $k ) {
			$allowed[] = SD_Citizen::SUBJECTS[ $k ];
		}
		$sdgs = array();
		foreach ( '' === $dep->sdgs ? array() : explode( ',', $dep->sdgs ) as $k ) {
			$sdgs[] = $k . '. ' . SD_Citizen::SDGS[ (int) $k ];
		}
		$lines   = array();
		$lines[] = 'Είμαι εκπαιδευτικός σε δημόσιο Δημοτικό Σχολείο στην Ελλάδα (Λαμία, συνοικία Ροδίτσα) και σχεδιάζω μια «Δράση Ενεργού Πολίτη» για την ' . $grade . ' τάξη.';
		$lines[] = 'Κανόνες (εγκύκλιος ΥΠΑΙΘΑ 128360/ΓΔ4/28-09-2026 για το Πρόγραμμα Σπουδών «Δράσεις Ενεργού Πολίτη»):';
		$lines[] = '- Η δράση διαρκεί 10 διδακτικές ώρες, από τα μαθήματα: ' . implode( ', ', $allowed ) . '.';
		$lines[] = '- Περνά από 5 στάδια: Διερεύνηση, Προετοιμασία/Σχεδιασμός, Υλοποίηση στο πεδίο, Επικοινωνία–Διάχυση, Αναστοχασμός.';
		$lines[] = '- Το συγκεκριμένο θέμα το διαμορφώνουν οι μαθητές στη Διερεύνηση· ο εκπαιδευτικός δίνει το πλαίσιο.';
		$lines[] = '- Ηλικιακή ομάδα του Προγράμματος Σπουδών: ' . SD_Citizen::age_group( $class->grade ) . '.';
		if ( 'alli' !== $dep->kind ) {
			$lines[] = '- Είδος: ' . SD_Citizen::KINDS[ $dep->kind ] . ( 'istoria' === $dep->kind ? ' (στο μάθημα της Ιστορίας).' : ' (στη Μελέτη Περιβάλλοντος Γ΄–Δ΄ ή στη Γεωγραφία Ε΄–ΣΤ΄).' );
		}
		if ( $sdgs ) {
			$lines[] = '- Θεματικά πεδία (Στόχοι Βιώσιμης Ανάπτυξης): ' . implode( ', ', $sdgs ) . '.';
		}
		if ( $dep->f1a_unit ) {
			$units = SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) );
			$u     = $units[ (int) $dep->f1a_unit ] ?? null;
			if ( $u && $u->cycle ) {
				$lines[] = '- Συνδέεται με το Εργαστήριο Δεξιοτήτων της τάξης: θεματική «' . SD_Workshops::CYCLES[ $u->cycle ] . '», υποθεματική «' . SD_Workshops::subtheme( $class->grade, $u->cycle ) . '»' . ( $u->title ? ', πρόγραμμα «' . $u->title . '»' : '' ) . '.';
			}
		}
		if ( $dep->kepea ) {
			$lines[] = '- Θέλουμε συνεργασία με Κέντρο Εκπαίδευσης για το Περιβάλλον και την Αειφορία (ΚΕΠΕΑ) ή εγκεκριμένο πρόγραμμα' . ( $dep->kepea_note ? ': ' . $dep->kepea_note : '' ) . '.';
		}
		$ctx = self::action_context( $dep->action_id, $class->year_id );
		if ( $ctx ) {
			$lines[] = '';
			$lines[] = $ctx;
		}
		$place = trim( self::place_card() );
		if ( $place ) {
			$lines[] = '';
			$lines[] = 'Στοιχεία για τον τόπο μας (μνημεία, φύση, φορείς), για να προτείνεις ρεαλιστικές δράσεις:';
			$lines[] = $place;
		}
		$lines[] = '';
		$lines[] = 'Τίτλος εργασίας μέχρι τώρα: «' . $dep->title . '».';
		if ( trim( $dep->description ) ) {
			$lines[] = 'Περιγραφή μέχρι τώρα: ' . $dep->description;
		}
		return implode( "\n", $lines );
	}

	/** Τα τρία prompts: [τίτλος, κείμενο]. */
	public static function prompts( $dep, $class ) {
		$base = self::common( $dep, $class );
		return array(
			array(
				'Δώσε μου ιδέες',
				$base . "\n\nΠρότεινε 3 διαφορετικές ιδέες για τη δράση, κατάλληλες για την ηλικία των παιδιών και υλοποιήσιμες σε 10 διδακτικές ώρες στον τόπο μας. Για καθεμία δώσε: τίτλο, 2–3 γραμμές περιγραφή, ποιο πραγματικό πρόβλημα της σχολικής ή τοπικής κοινότητας αγγίζει, ποιος είναι ο αποδέκτης (σχολική κοινότητα, γειτονιά, χώρα, ψηφιακός κόσμος), πιθανούς φορείς συνεργασίας και πώς θα γίνει η διάχυση. Γράψε στα ελληνικά, απλά και πρακτικά.",
			),
			array(
				'Σχεδίασε τις 10 ώρες',
				$base . "\n\nΣχεδίασε τη δράση σε 10 διδακτικές ώρες. Δώσε δύο πίνακες:\n1) Κατανομή ωρών ανά μάθημα (μάθημα, ενότητα/κεφάλαιο, ώρες, περίοδος) με σύνολο 10, μόνο από τα επιτρεπόμενα μαθήματα.\n2) Τα 5 στάδια (Διερεύνηση, Προετοιμασία/Σχεδιασμός, Υλοποίηση στο πεδίο, Επικοινωνία–Διάχυση, Αναστοχασμός) με ενδεικτικές δραστηριότητες και εργαλεία, ώρες και πότε, με σύνολο 10.\nΣτη Διερεύνηση πρότεινε τρόπους ώστε οι μαθητές να επιλέξουν οι ίδιοι το συγκεκριμένο θέμα. Αν προβλέπεται επίσκεψη, σημείωσε ότι χρειάζεται απόφαση του Συλλόγου Διδασκόντων.",
			),
			array(
				'Βελτίωσε στόχους και αποτελέσματα',
				$base . ( trim( $dep->outcomes ) ? "\n\nΠροσδοκώμενα μαθησιακά αποτελέσματα μέχρι τώρα:\n" . $dep->outcomes : '' ) . "\n\nΔιατύπωσε 4–6 προσδοκώμενα μαθησιακά αποτελέσματα για τους μαθητές (γνώσεις, δεξιότητες, στάσεις), σύντομα, μετρήσιμα και κατάλληλα για την ηλικία, με αρχή «Οι μαθητές/τριες να…». Πρόσθεσε 2–3 απλούς τρόπους να φανεί στο τέλος αν πετύχαμε (για τον Αναστοχασμό και την αποτίμηση).",
			),
		);
	}

	public static function render_box( $dep, $class ) {
		echo '<section class="sd-card sd-ai"><h3>Βοηθός AI</h3>';
		echo '<p class="sd-help">Πατήστε σε ένα από τα παρακάτω, αντιγράψτε το κείμενο και επικολλήστε το σε όποιο εργαλείο AI χρησιμοποιείτε (π.χ. ChatGPT, Claude, Gemini). Τα prompts φτιάχνονται από ό,τι έχετε <strong>αποθηκεύσει</strong>: συμπληρώστε και αποθηκεύστε πρώτα τάξη, είδος, ΣΒΑ, σύνδεση με ΣΔ και Εργαστήριο, για καλύτερες προτάσεις. Ελέγξτε πάντα την απάντηση πριν τη χρησιμοποιήσετε.</p>';
		if ( '' === trim( self::place_card() ) && current_user_can( SD_Roles::CAP_MANAGE ) ) {
			echo '<p class="sd-notice sd-error">Η «κάρτα τόπου» είναι κενή. Συμπληρώστε την στις <a href="' . esc_url( SD_Frontend::url( 'rythmiseis' ) ) . '">Ρυθμίσεις</a>, για να προτείνει το AI δράσεις με πραγματικά μέρη και φορείς της περιοχής.</p>';
		}
		foreach ( self::prompts( $dep, $class ) as $p ) {
			echo '<details class="sd-copy"><summary>' . esc_html( $p[0] ) . '</summary><div class="sd-copy-head"><button type="button" class="sd-button sd-small" data-copy>Αντιγραφή</button></div><textarea readonly rows="10">' . esc_textarea( $p[1] ) . '</textarea></details>';
		}
		echo '</section>';
	}
}
