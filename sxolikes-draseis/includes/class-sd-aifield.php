<?php
/**
 * Κουμπί ✨ σε κάθε πεδίο κειμένου των εντύπων.
 *
 * Δύο τρόποι, που συνυπάρχουν:
 * - Χωρίς κλειδί API: το κουμπί φτιάχνει prompt για το συγκεκριμένο πεδίο,
 *   το αντιγράφει και ανοίγει το δωρεάν AI που προτιμά ο/η εκπαιδευτικός.
 * - Με κλειδί API (Google Gemini ή Mistral, ρύθμιση της Διεύθυνσης): η
 *   πρόταση εμφανίζεται κάτω από το πεδίο, με «Αντικατάσταση» ή «Προσθήκη».
 *
 * Στο prompt μπαίνουν τα στοιχεία του εντύπου και η κάρτα τόπου. Ονόματα
 * εκπαιδευτικών και μαθητών δεν μπαίνουν από την εφαρμογή· οι εκπαιδευτικοί
 * καλούνται να μη γράφουν ονόματα μαθητών στα πεδία.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_AiField {

	const OPT          = 'sd_ai_api';          // provider, key, model, models, per_user, per_day
	const OPT_USAGE    = 'sd_ai_usage';        // [ημερομηνία => [σύνολο, user_id => πλήθος]]
	const PROVIDERS    = array(
		''        => 'Καμία (μόνο αντιγραφή prompt)',
		'gemini'  => 'Google Gemini',
		'mistral' => 'Mistral',
	);
	const SITES        = array(
		'chatgpt' => array( 'ChatGPT', 'https://chatgpt.com/' ),
		'claude'  => array( 'Claude', 'https://claude.ai/new' ),
		'gemini'  => array( 'Gemini', 'https://gemini.google.com/app' ),
	);
	/** Πεδία που δεν παίρνουν κουμπί (ονόματα προσώπων, σχόλια ελέγχου). */
	const SKIP = array( 'review_note', 'visitor', 'ai_notes', 'escorts', 'b_person', 'b_role', 'b_when', 'b_topic', 'place_card' );

	private static $marked = false;

	public static function init() {
		add_action( 'wp_ajax_sd_ai_field', array( __CLASS__, 'ajax' ) );
	}

	/* =================== Ρυθμίσεις =================== */

	public static function cfg() {
		$v = get_option( self::OPT, array() );
		$v = is_array( $v ) ? $v : array();
		return array_merge(
			array(
				'provider' => '',
				'key'      => '',
				'model'    => '',
				'models'   => array(),
				'per_user' => 20,
				'per_day'  => 200,
			),
			$v
		);
	}

	/** Το κλειδί: σταθερά στο wp-config.php (προτιμότερο) ή ρύθμιση. */
	private static function key() {
		if ( defined( 'SD_AI_KEY' ) && SD_AI_KEY ) {
			return (string) SD_AI_KEY;
		}
		return (string) self::cfg()['key'];
	}

	public static function api_on() {
		$c = self::cfg();
		return '' !== $c['provider'] && '' !== self::key() && '' !== $c['model'];
	}

	public static function handle( $do ) {
		if ( ! in_array( $do, array( 'ai_save', 'ai_models' ), true ) ) {
			return null;
		}
		$back = SD_Frontend::url( 'rythmiseis' ) . '#ai';
		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return array( new WP_Error( 'sd', 'Μόνο η Διεύθυνση αλλάζει αυτή τη ρύθμιση.' ), $back );
		}
		$c = self::cfg();
		if ( 'ai_save' === $do ) {
			$prov = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
			if ( ! isset( self::PROVIDERS[ $prov ] ) ) {
				$prov = '';
			}
			if ( $prov !== $c['provider'] ) {
				$c['models'] = array();
				$c['model']  = '';
			}
			$c['provider'] = $prov;
			$key           = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
			$new_key       = false;
			if ( ! empty( $_POST['remove_key'] ) || '' === $prov ) {
				$c['key'] = '';
			} elseif ( '' !== $key && $key !== $c['key'] ) {
				$c['key'] = $key;
				$new_key  = true;
			}
			$model = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
			if ( in_array( $model, $c['models'], true ) ) {
				$c['model'] = $model;
			}
			$c['per_user'] = max( 1, min( 200, absint( $_POST['per_user'] ?? 20 ) ) );
			$c['per_day']  = max( 1, min( 2000, absint( $_POST['per_day'] ?? 200 ) ) );
			update_option( self::OPT, $c, false );
			// Κάθε νέο κλειδί ελέγχεται αμέσως· αν δεν γίνει δεκτό, το AI μένει ανενεργό.
			if ( $prov && self::key() && ( ! $c['models'] || $new_key ) ) {
				$res = self::fetch_models( $back );
				if ( is_wp_error( $res[0] ) ) {
					$c['model'] = '';
					update_option( self::OPT, $c, false );
				}
				return $res;
			}
			return array( true, $back );
		}
		return self::fetch_models( $back );
	}

	/** Ζητά από την υπηρεσία τα διαθέσιμα μοντέλα· έτσι δεν βασιζόμαστε σε ονόματα που αλλάζουν. */
	private static function fetch_models( $back ) {
		$c   = self::cfg();
		$key = self::key();
		if ( ! $c['provider'] || ! $key ) {
			return array( new WP_Error( 'sd', 'Διαλέξτε υπηρεσία και αποθηκεύστε κλειδί πρώτα.' ), $back );
		}
		if ( 'gemini' === $c['provider'] ) {
			$r = wp_remote_get( 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200', array( 'timeout' => 20, 'headers' => array( 'x-goog-api-key' => $key ) ) );
		} else {
			$r = wp_remote_get( 'https://api.mistral.ai/v1/models', array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $key ) ) );
		}
		$err = self::http_error( $r );
		if ( $err ) {
			return array( new WP_Error( 'sd', 'Η σύνδεση απέτυχε: ' . $err ), $back );
		}
		$body = json_decode( wp_remote_retrieve_body( $r ), true );
		$list = array();
		if ( 'gemini' === $c['provider'] ) {
			foreach ( (array) ( $body['models'] ?? array() ) as $m ) {
				$name = preg_replace( '#^models/#', '', (string) ( $m['name'] ?? '' ) );
				if ( $name && in_array( 'generateContent', (array) ( $m['supportedGenerationMethods'] ?? array() ), true ) && ! preg_match( '/embed|aqa|image|tts|audio|vision-only|live/i', $name ) ) {
					$list[] = $name;
				}
			}
		} else {
			foreach ( (array) ( $body['data'] ?? array() ) as $m ) {
				$id = (string) ( $m['id'] ?? '' );
				if ( $id && ! preg_match( '/embed|moderation|ocr|transcri|voxtral/i', $id ) && ( ! isset( $m['capabilities']['completion_chat'] ) || $m['capabilities']['completion_chat'] ) ) {
					$list[] = $id;
				}
			}
		}
		$list = array_values( array_unique( $list ) );
		sort( $list );
		if ( ! $list ) {
			return array( new WP_Error( 'sd', 'Η σύνδεση πέτυχε, αλλά δεν βρέθηκε κατάλληλο μοντέλο για αυτό το κλειδί.' ), $back );
		}
		$c['models'] = $list;
		if ( ! in_array( $c['model'], $list, true ) ) {
			$c['model'] = self::pick_default( $c['provider'], $list );
		}
		update_option( self::OPT, $c, false );
		set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', 'Η σύνδεση πέτυχε. Βρέθηκαν ' . count( $list ) . ' μοντέλα· επιλέχθηκε το «' . $c['model'] . '».' ), 60 );
		return array( true, $back );
	}

	/** Προεπιλογή: ένα γρήγορο, οικονομικό μοντέλο γενικής χρήσης. */
	private static function pick_default( $provider, $list ) {
		$prefs = 'gemini' === $provider ? array( '/flash-lite$/', '/flash$/', '/flash/' ) : array( '/^mistral-small-latest$/', '/^mistral-medium-latest$/', '/small/', '/latest$/' );
		foreach ( $prefs as $re ) {
			$hit = preg_grep( $re, $list );
			if ( $hit ) {
				return reset( $hit );
			}
		}
		return $list[0];
	}

	public static function render_settings() {
		$c = self::cfg();
		echo SD_Docs::fold_open( 'Βοηθός AI μέσα στις φόρμες (✨)', '<span>' . ( self::api_on() ? esc_html( self::PROVIDERS[ $c['provider'] ] ) : 'μόνο αντιγραφή prompt' ) . '</span>', false, 'id="ai"' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="sd-help">Σε κάθε πεδίο κειμένου των εντύπων υπάρχει κουμπί ✨. <strong>Χωρίς κλειδί</strong> φτιάχνει prompt για το πεδίο, το αντιγράφει και ανοίγει το δωρεάν ChatGPT, Claude ή Gemini του/της εκπαιδευτικού. <strong>Με κλειδί API</strong> η πρόταση εμφανίζεται κατευθείαν κάτω από το πεδίο.</p>';
		echo '<details><summary>Τι πρέπει να ξέρετε πριν βάλετε κλειδί</summary><ul class="sd-help">';
		echo '<li>Στέλνονται στην υπηρεσία τα στοιχεία του εντύπου και η κάρτα τόπου. Η εφαρμογή δεν στέλνει ονόματα εκπαιδευτικών· οι συνάδελφοι δεν πρέπει να γράφουν ονόματα μαθητών στα πεδία.</li>';
		echo '<li><strong>Google Gemini:</strong> κλειδί από το Google AI Studio (aistudio.google.com). Τα δωρεάν όρια και οι όροι για τα δεδομένα αλλάζουν· ελέγξτε τους πριν.</li>';
		echo '<li><strong>Mistral:</strong> κλειδί από το console.mistral.ai (δωρεάν πλάνο «Experiment»). Στο δωρεάν πλάνο τα δεδομένα μπορεί να χρησιμοποιηθούν για εκπαίδευση μοντέλων· απενεργοποιήστε το στις ρυθμίσεις απορρήτου του λογαριασμού.</li>';
		echo '<li>Το κλειδί αποθηκεύεται στη βάση του ιστότοπου. Πιο ασφαλές: να το προσθέσει ο διαχειριστής στο wp-config.php ως <code>define( \'SD_AI_KEY\', \'…\' );</code></li>';
		echo '</ul></details>';
		SD_Frontend::form_open( 'ai_save' );
		echo '<label class="sd-block">Υπηρεσία <select name="provider">';
		foreach ( self::PROVIDERS as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $c['provider'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		if ( defined( 'SD_AI_KEY' ) && SD_AI_KEY ) {
			echo '<p class="sd-meta">Το κλειδί ορίζεται στο wp-config.php.</p>';
		} else {
			$has = '' !== $c['key'];
			echo '<label class="sd-block">Κλειδί API ' . ( $has ? '<span class="sd-meta">(αποθηκευμένο, τελειώνει σε …' . esc_html( substr( $c['key'], -4 ) ) . '· αφήστε κενό για να μείνει)</span>' : '' ) . '<input type="password" name="api_key" autocomplete="off" value=""></label>';
			if ( $has ) {
				echo '<label class="sd-inline"><input type="checkbox" name="remove_key" value="1"> Διαγραφή του αποθηκευμένου κλειδιού</label>';
			}
		}
		if ( $c['models'] ) {
			echo '<label class="sd-block">Μοντέλο <select name="model">';
			foreach ( $c['models'] as $m ) {
				echo '<option value="' . esc_attr( $m ) . '"' . selected( $c['model'], $m, false ) . '>' . esc_html( $m ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<div class="sd-row"><label>Όριο ανά εκπαιδευτικό την ημέρα <input type="number" class="sd-num" min="1" max="200" name="per_user" value="' . (int) $c['per_user'] . '"></label>';
		echo '<label>Όριο για όλο το σχολείο την ημέρα <input type="number" class="sd-num" min="1" max="2000" name="per_day" value="' . (int) $c['per_day'] . '"></label></div>';
		$today = self::usage_today();
		echo '<p class="sd-meta">Σήμερα: ' . (int) $today['total'] . ' προτάσεις. Κατάσταση: <strong>' . ( self::api_on() ? 'ενεργό (' . esc_html( self::PROVIDERS[ $c['provider'] ] . ' · ' . $c['model'] ) . ')' : 'μόνο αντιγραφή prompt' ) . '</strong></p>';
		echo '<p><button class="sd-button" type="submit">Αποθήκευση</button></p></form>';
		if ( $c['provider'] && self::key() ) {
			SD_Frontend::form_open( 'ai_models' );
			echo '<button class="sd-button sd-small sd-quiet" type="submit">Έλεγχος σύνδεσης και ανανέωση μοντέλων</button></form>';
		}
		echo SD_Docs::fold_close(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* =================== Σήμανση σελίδας =================== */

	/** Η σελίδα-έντυπο δηλώνει ποιο έντυπο είναι· η JavaScript βάζει τα κουμπιά ✨. */
	public static function mark( $type, $id ) {
		if ( self::$marked ) {
			return;
		}
		self::$marked = true;
		$sites = array();
		foreach ( self::SITES as $k => $s ) {
			$sites[] = array( 'id' => $k, 'label' => $s[0], 'url' => $s[1] );
		}
		$cfg = array(
			'type'  => $type,
			'id'    => (int) $id,
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'sd_ai_field' ),
			'api'   => self::api_on(),
			'skip'  => self::SKIP,
			'sites' => $sites,
		);
		echo '<script type="application/json" id="sd-ai-cfg">' . wp_json_encode( $cfg ) . '</script>';
		echo '<p class="sd-help sd-noprint">✨ Δίπλα στα πεδία: πρόταση από AI για το συγκεκριμένο πεδίο' . ( self::api_on() ? '' : ' (αντιγράφεται και ανοίγει σε δωρεάν εργαλείο AI)' ) . '. Μη γράφετε ονόματα μαθητών στα πεδία.</p>';
	}

	/* =================== Πλαίσιο ανά έντυπο =================== */

	private static function school_line( $class = null ) {
		$s = 'Είμαι εκπαιδευτικός σε δημόσιο Δημοτικό Σχολείο στην Ελλάδα (' . SD_Frontend::school_name() . ').';
		if ( $class ) {
			$s .= ' Τάξη: ' . ( SD_Data::GRADES[ (int) $class->grade ] ?? '' ) . '.';
		}
		return $s;
	}

	private static function place( $max = 3500 ) {
		$p = trim( SD_Ai::place_card() );
		if ( '' === $p ) {
			return '';
		}
		return "\n\nΣτοιχεία για τον τόπο μας (μνημεία, φύση, φορείς, έτοιμα προγράμματα). Χρησιμοποίησε μόνο ό,τι ταιριάζει:\n" . mb_substr( $p, 0, $max );
	}

	private static function units_text( $class ) {
		$out = array();
		foreach ( SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) ) as $n => $u ) {
			if ( $u->cycle ) {
				$out[] = SD_Workshops::UNITS[ $n ] . ': θεματική «' . SD_Workshops::CYCLES[ $u->cycle ] . '», υποθεματική «' . SD_Workshops::class_subtheme( $class, $u->cycle ) . '»' . ( $u->title ? ', πρόγραμμα «' . $u->title . '»' : '' );
			}
		}
		return $out ? "\nΕργαστήρια Δεξιοτήτων της τάξης (Έντυπο 1Α):\n- " . implode( "\n- ", $out ) : '';
	}

	/**
	 * Οι ενέργειες των Σχεδίων Δράσης εσωτερικής αξιολόγησης που αφορούν την τάξη,
	 * ώστε οι προτάσεις για Εργαστήρια να τις υπηρετούν. Σημειώνονται όσες έχουν
	 * ήδη συνδεθεί με ενότητα του 1Α.
	 */
	private static function plans_text( $class ) {
		$linked = array();
		foreach ( SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) ) as $n => $u ) {
			if ( $u->action_id ) {
				$linked[ (int) $u->action_id ] = $n;
			}
		}
		$out = array();
		foreach ( SD_Plans::year_actions( $class->year_id, $class->grade ) as $row ) {
			list( $p, $g, $a ) = $row;
			$line  = '«' . $p->title . '» · στόχος: ' . mb_substr( $g->description, 0, 160 ) . ' · ενέργεια: ' . mb_substr( $a->description, 0, 220 );
			$line .= isset( $linked[ (int) $a->id ] ) ? ' [συνδέεται ήδη με την ' . $linked[ (int) $a->id ] . 'η ενότητα]' : '';
			$out[] = $line;
			if ( count( $out ) >= 10 ) {
				break;
			}
		}
		if ( ! $out ) {
			return '';
		}
		return "\nΣχέδια Δράσης εσωτερικής αξιολόγησης του σχολείου που αφορούν την τάξη (οι προτάσεις να τα υπηρετούν όπου ταιριάζει):\n- " . implode( "\n- ", $out );
	}

	/**
	 * Πλαίσιο και έλεγχος πρόσβασης. Επιστρέφει [κείμενο, οδηγία είδους] ή WP_Error.
	 */
	private static function context( $type, $id ) {
		switch ( $type ) {
			case 'dep':
				$dep   = SD_Citizen::get( $id );
				$class = $dep ? SD_Data::get_class( $dep->class_id ) : null;
				if ( ! $dep || ! $class || ! SD_Citizen::can_view( $class ) ) {
					break;
				}
				return array( SD_Ai::common_context( $dep, $class ), 'Το έντυπο είναι το «Έντυπο 2 · Δράση Ενεργού Πολίτη».' );
			case 'plan':
				$plan = SD_Plans::get_plan( $id );
				if ( ! $plan ) {
					break;
				}
				$t  = self::school_line() . "\nΣυντάσσουμε Σχέδιο Δράσης εσωτερικής αξιολόγησης της σχολικής μονάδας (πλατφόρμα του ΙΕΠ).";
				$t .= "\nΤίτλος: «" . $plan->title . '». ' . SD_Eval::function_label( $plan->axis ) . ', άξονας ' . (int) $plan->axis . ': ' . SD_Eval::axis_label( $plan->axis ) . '.';
				$t .= $plan->purpose ? "\nΣκοπός (αποθηκευμένος): " . $plan->purpose : '';
				$t .= $plan->need ? "\nΤεκμηρίωση αναγκαιότητας (αποθηκευμένη): " . $plan->need : '';
				foreach ( SD_Plans::goals( $plan->id ) as $i => $g ) {
					$t .= "\nΣτόχος " . ( $i + 1 ) . ': ' . $g->description;
				}
				return array( $t, 'Τα κείμενα μπαίνουν στη φόρμα του ΙΕΠ, που έχει αυστηρά όρια χαρακτήρων· σεβάσου το όριο του πεδίου.' );
			case 'f1a':
			case 'sxtm':
			case 'f4':
				$class = SD_Data::get_class( $id );
				if ( ! $class || ! SD_Workshops::can_view( $class ) ) {
					break;
				}
				$names = array(
					'f1a'  => 'Έντυπο 1Α · προγραμματισμός Εργαστηρίων Δεξιοτήτων της τάξης',
					'sxtm' => 'Σχέδιο Δράσης του Τμήματος για τα Εργαστήρια Δεξιοτήτων (υπόδειγμα Πλατφόρμας 21+)',
					'f4'   => 'Έντυπο 4 · αποτίμηση της χρονιάς (Εργαστήρια, Ενεργός Πολίτης, συμβολή στα Σχέδια Δράσης)',
				);
				$t  = self::school_line( $class ) . "\nΣυμπληρώνω: " . $names[ $type ] . '.';
				$t .= self::units_text( $class ) . self::plans_text( $class );
				foreach ( SD_Citizen::of_class( $class->id ) as $d ) {
					$t .= "\nΔράση Ενεργού Πολίτη της τάξης: «" . $d->title . '»';
				}
				return array( $t . self::place( 2500 ), 'Τα Εργαστήρια Δεξιοτήτων έχουν 4 θεματικούς κύκλους· σε κάθε κύκλο η τάξη δουλεύει την υποθεματική που φαίνεται παραπάνω.' );
			case 'f3':
				$r     = SD_Requests::get( $id );
				$class = $r ? SD_Data::get_class( $r->class_id ) : null;
				if ( ! $r || ! $class || ! SD_Requests::can_view( $class ) ) {
					break;
				}
				list( $head, $body ) = SD_Requests::rows( $r, $class );
				$t                   = self::school_line( $class ) . "\nΣυμπληρώνω το Έντυπο 3 · αίτημα " . ( 'A' === $r->part ? 'για εκπαιδευτική επίσκεψη εκτός σχολείου' : 'για φορέα ή ομιλητή στο σχολείο' ) . '.';
				foreach ( array_merge( array_slice( $head, 2 ), $body ) as $kv ) {
					if ( '' !== trim( (string) $kv[1] ) && false === strpos( $kv[0], 'σκοπός' ) && false === strpos( $kv[0], 'Συνοδοί' ) ) {
						$t .= "\n" . $kv[0] . ': ' . $kv[1];
					}
				}
				$t .= self::units_text( $class );
				return array( $t . self::place( 1500 ), 'Γράψε σύντομα (3–5 προτάσεις) τι θα μάθουν τα παιδιά από την επίσκεψη και πώς δένει με τη δράση στην οποία εντάσσεται (Εργαστήρια, Ενεργός Πολίτης, Σχέδιο Δράσης, μάθημα). Χωρίς ονόματα μαθητών.' );
			case 'sxsm':
				$year = SD_Data::active_year();
				if ( ! $year || ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
					break;
				}
				$t = self::school_line() . "\nΣυμπληρώνω το Σχέδιο Δράσης της Σχολικής Μονάδας για τα Εργαστήρια Δεξιοτήτων (" . $year->label . ', υπόδειγμα Πλατφόρμας 21+).';
				foreach ( SD_Data::classes( $year->id ) as $c ) {
					$u = self::units_text( $c );
					$t .= $u ? "\nΤμήμα " . SD_Data::class_label( $c ) . ':' . $u : '';
				}
				return array( $t, '' );
		}
		return new WP_Error( 'sd', 'Δεν έχετε πρόσβαση σε αυτό το έντυπο.' );
	}

	/** Το prompt για ένα πεδίο. $others: [ετικέτα => κείμενο] των άλλων πεδίων της φόρμας. */
	private static function build( $ctx, $label, $current, $limit, $others ) {
		list( $text, $kind ) = $ctx;
		$p  = $text;
		$p .= $kind ? "\n" . $kind : '';
		$o  = array();
		foreach ( $others as $l => $v ) {
			if ( '' !== trim( $v ) ) {
				$o[] = '- ' . $l . ': ' . mb_substr( $v, 0, 600 );
			}
		}
		if ( $o ) {
			$p .= "\n\nΌσα έχουν γραφτεί στη φόρμα μέχρι τώρα:\n" . implode( "\n", array_slice( $o, 0, 20 ) );
		}
		$p .= "\n\nΧρειάζομαι κείμενο για το πεδίο «" . $label . '».';
		if ( '' !== trim( $current ) ) {
			$p .= "\nΤι έχω γράψει ήδη σε αυτό (βελτίωσέ το, κράτα ό,τι είναι καλό):\n" . $current;
		}
		$p .= "\n\nΓράψε μόνο το κείμενο του πεδίου, έτοιμο να μπει στο έντυπο, στα ελληνικά, απλά και πρακτικά, κατάλληλο για την ηλικία των παιδιών.";
		$p .= $limit ? ' Έως ' . (int) $limit . ' χαρακτήρες.' : ' Σύντομα: λίγες γραμμές.';
		$p .= ' Χωρίς εισαγωγή, χωρίς σχόλια, χωρίς επικεφαλίδες ή μορφοποίηση markdown. Μην επινοείς ονόματα προσώπων. Αν δεν είσαι βέβαιος/η για κάποιο τοπικό στοιχείο, μην το αναφέρεις.';
		return $p;
	}

	/* =================== Όρια χρήσης =================== */

	private static function usage_today() {
		$u   = get_option( self::OPT_USAGE, array() );
		$day = current_time( 'Y-m-d' );
		$t   = is_array( $u ) && isset( $u[ $day ] ) ? $u[ $day ] : array();
		return array_merge( array( 'total' => 0 ), $t );
	}

	private static function count_use() {
		$day          = current_time( 'Y-m-d' );
		$t            = self::usage_today();
		$t['total']   = (int) $t['total'] + 1;
		$uid          = 'u' . get_current_user_id();
		$t[ $uid ]    = (int) ( $t[ $uid ] ?? 0 ) + 1;
		update_option( self::OPT_USAGE, array( $day => $t ), false ); // κρατάμε μόνο τη σημερινή
	}

	private static function over_limit() {
		$c = self::cfg();
		$t = self::usage_today();
		if ( (int) $t['total'] >= (int) $c['per_day'] ) {
			return 'Συμπληρώθηκε το σημερινό όριο του σχολείου (' . (int) $c['per_day'] . ' προτάσεις).';
		}
		if ( (int) ( $t[ 'u' . get_current_user_id() ] ?? 0 ) >= (int) $c['per_user'] ) {
			return 'Συμπληρώσατε το σημερινό σας όριο (' . (int) $c['per_user'] . ' προτάσεις).';
		}
		return '';
	}

	/* =================== Κλήση της υπηρεσίας =================== */

	private static function http_error( $r ) {
		if ( is_wp_error( $r ) ) {
			return 'δεν απάντησε η υπηρεσία (' . $r->get_error_message() . ').';
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		if ( $code >= 200 && $code < 300 ) {
			return '';
		}
		if ( 401 === $code || 403 === $code ) {
			return 'το κλειδί δεν έγινε δεκτό (κωδικός ' . $code . ').';
		}
		if ( 429 === $code ) {
			return 'η υπηρεσία λέει ότι εξαντλήθηκε το όριο χρήσης· δοκιμάστε αργότερα.';
		}
		$b   = json_decode( wp_remote_retrieve_body( $r ), true );
		$msg = is_array( $b ) ? ( $b['error']['message'] ?? ( $b['message'] ?? '' ) ) : '';
		return 'σφάλμα ' . $code . ( $msg ? ': ' . mb_substr( (string) $msg, 0, 200 ) : '' ) . '.';
	}

	private static function system_text() {
		return 'Είσαι βοηθός εκπαιδευτικών Δημοτικού στην Ελλάδα. Γράφεις κείμενα για σχολικά έντυπα σχεδιασμού και αποτίμησης: σύντομα, ακριβή, ρεαλιστικά για δημόσιο σχολείο, χωρίς υπερβολές. Απαντάς πάντα στα ελληνικά και μόνο με το κείμενο που ζητήθηκε.';
	}

	private static function call( $prompt, $limit ) {
		$c      = self::cfg();
		$key    = self::key();
		$tokens = $limit ? max( 400, min( 4000, (int) ( $limit * 1.2 ) ) ) : 1500;
		if ( 'gemini' === $c['provider'] ) {
			$r = wp_remote_post(
				'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $c['model'] ) . ':generateContent',
				array(
					'timeout' => 60,
					'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => $key ),
					'body'    => wp_json_encode(
						array(
							'systemInstruction' => array( 'parts' => array( array( 'text' => self::system_text() ) ) ),
							'contents'          => array( array( 'role' => 'user', 'parts' => array( array( 'text' => $prompt ) ) ) ),
							'generationConfig'  => array( 'maxOutputTokens' => $tokens + 2000, 'temperature' => 0.7 ),
						)
					),
				)
			);
			$err = self::http_error( $r );
			if ( $err ) {
				return new WP_Error( 'sd', 'Το AI δεν απάντησε: ' . $err );
			}
			$b    = json_decode( wp_remote_retrieve_body( $r ), true );
			$text = '';
			foreach ( (array) ( $b['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
				if ( empty( $part['thought'] ) ) {
					$text .= (string) ( $part['text'] ?? '' );
				}
			}
			if ( '' === trim( $text ) ) {
				$why = $b['promptFeedback']['blockReason'] ?? ( $b['candidates'][0]['finishReason'] ?? '' );
				return new WP_Error( 'sd', 'Το AI δεν έδωσε κείμενο' . ( $why ? ' (' . $why . ')' : '' ) . '. Δοκιμάστε ξανά ή αλλάξτε λίγο το πεδίο.' );
			}
			return trim( $text );
		}
		// Mistral
		$r   = wp_remote_post(
			'https://api.mistral.ai/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ),
				'body'    => wp_json_encode(
					array(
						'model'       => $c['model'],
						'messages'    => array(
							array( 'role' => 'system', 'content' => self::system_text() ),
							array( 'role' => 'user', 'content' => $prompt ),
						),
						'max_tokens'  => $tokens,
						'temperature' => 0.7,
					)
				),
			)
		);
		$err = self::http_error( $r );
		if ( $err ) {
			return new WP_Error( 'sd', 'Το AI δεν απάντησε: ' . $err );
		}
		$b    = json_decode( wp_remote_retrieve_body( $r ), true );
		$text = $b['choices'][0]['message']['content'] ?? '';
		if ( is_array( $text ) ) { // ορισμένα μοντέλα επιστρέφουν κομμάτια
			$parts = '';
			foreach ( $text as $chunk ) {
				$parts .= ( 'text' === ( $chunk['type'] ?? '' ) ) ? (string) ( $chunk['text'] ?? '' ) : '';
			}
			$text = $parts;
		}
		if ( '' === trim( (string) $text ) ) {
			return new WP_Error( 'sd', 'Το AI δεν έδωσε κείμενο. Δοκιμάστε ξανά.' );
		}
		return trim( (string) $text );
	}

	/** Ελεύθερη ερώτηση (π.χ. προτάσεις από το Αρχείο), με τα ίδια όρια χρήσης. */
	public static function ask( $prompt, $limit ) {
		if ( ! self::api_on() ) {
			return new WP_Error( 'sd', 'Δεν έχει ρυθμιστεί υπηρεσία AI.' );
		}
		$over = self::over_limit();
		if ( $over ) {
			return new WP_Error( 'sd', $over );
		}
		self::count_use();
		return self::call( $prompt, $limit );
	}

	/* =================== AJAX =================== */

	public static function ajax() {
		if ( ! check_ajax_referer( 'sd_ai_field', 'nonce', false ) || ! current_user_can( SD_Roles::CAP_ACCESS ) ) {
			wp_send_json_error( array( 'msg' => 'Η ενέργεια δεν επιτρέπεται. Ανανεώστε τη σελίδα.' ), 403 );
		}
		$type  = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		$id    = absint( $_POST['id'] ?? 0 );
		$field = sanitize_text_field( wp_unslash( $_POST['field'] ?? '' ) );
		$label = mb_substr( sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ), 0, 200 );
		$cur   = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['current'] ?? '' ) ), 0, 3000 );
		$limit = absint( $_POST['limit'] ?? 0 );
		$mode  = sanitize_key( wp_unslash( $_POST['mode'] ?? 'api' ) );
		if ( in_array( preg_replace( '/\[.*$/', '', $field ), self::SKIP, true ) ) {
			wp_send_json_error( array( 'msg' => 'Σε αυτό το πεδίο δεν χρησιμοποιείται AI.' ) );
		}
		$others = array();
		$raw    = json_decode( wp_unslash( $_POST['others'] ?? '[]' ), true );
		foreach ( is_array( $raw ) ? array_slice( $raw, 0, 30 ) : array() as $o ) {
			$l = mb_substr( sanitize_text_field( (string) ( $o['label'] ?? '' ) ), 0, 150 );
			$n = preg_replace( '/\[.*$/', '', (string) ( $o['name'] ?? '' ) );
			if ( $l && ! in_array( $n, self::SKIP, true ) ) {
				$others[ $l ] = mb_substr( sanitize_textarea_field( (string) ( $o['value'] ?? '' ) ), 0, 600 );
			}
		}
		$ctx = self::context( $type, $id );
		if ( is_wp_error( $ctx ) ) {
			wp_send_json_error( array( 'msg' => $ctx->get_error_message() ), 403 );
		}
		$prompt = self::build( $ctx, $label ? $label : $field, $cur, $limit, $others );
		if ( 'prompt' === $mode || ! self::api_on() ) {
			wp_send_json_success( array( 'prompt' => $prompt ) );
		}
		$over = self::over_limit();
		if ( $over ) {
			wp_send_json_error( array( 'msg' => $over, 'prompt' => $prompt ) );
		}
		self::count_use();
		$text = self::call( $prompt, $limit );
		if ( is_wp_error( $text ) ) {
			wp_send_json_error( array( 'msg' => $text->get_error_message(), 'prompt' => $prompt ) );
		}
		wp_send_json_success( array( 'text' => $text, 'prompt' => $prompt ) );
	}
}
