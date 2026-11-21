<?php
/**
 * Εισαγωγή Σχεδίου Δράσης από την πλατφόρμα αξιολόγησης του ΙΕΠ με αντιγραφή–επικόλληση.
 *
 * Ο/Η χρήστης ανοίγει την «Προβολή» του σχεδίου στην πλατφόρμα, πατά Ctrl+A, Ctrl+C
 * και επικολλά εδώ. Η εφαρμογή αναγνωρίζει στόχους, ενέργειες (υλοποίηση/αποτίμηση),
 * ημερομηνίες, κριτήρια επιτυχίας, εργαλεία και τρόπους εργασίας, δείχνει
 * προεπισκόπηση και μόνο μετά από επιβεβαίωση αντικαθιστά τη στοχοθεσία του σχεδίου.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_PlanImport {

	const MAX_TEXT = 60000;

	private static function key( $plan_id ) {
		return 'sd_imp_' . get_current_user_id() . '_' . (int) $plan_id;
	}

	/* =================== Ανάλυση κειμένου =================== */

	/** Κανονικοποίηση για συγκρίσεις: πεζά, χωρίς τόνους, μονά κενά. */
	public static function norm( $s ) {
		$s = mb_strtolower( (string) $s, 'UTF-8' );
		$s = strtr(
			$s,
			array(
				'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω',
				'ϊ' => 'ι', 'ΐ' => 'ι', 'ϋ' => 'υ', 'ΰ' => 'υ', 'ς' => 'σ',
			)
		);
		$s = preg_replace( '#\s*/\s*[-‐–]\s*#u', '/', $s ); // «μαθητών/-τριών» = «μαθητών/τριών»
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	private static function lines( $text ) {
		$text = str_replace( array( "\r\n", "\r", "\xC2\xA0", "\t" ), array( "\n", "\n", ' ', "\n" ), (string) $text );
		$out  = array();
		foreach ( explode( "\n", $text ) as $l ) {
			$l = trim( preg_replace( '/[ \x{2000}-\x{200B}]+/u', ' ', $l ) );
			if ( '' !== $l ) {
				$out[] = $l;
			}
		}
		return $out;
	}

	private static function date_ymd( $d ) {
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $d, $m ) && checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
			return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		}
		return '';
	}

	/** Ποιες ετικέτες μιας λίστας (εργαλεία/τρόποι) εμφανίζονται στο κείμενο· τι περισσεύει. */
	private static function match_list( $text, $list ) {
		$n     = ' ' . self::norm( $text ) . ' ';
		$found = array();
		foreach ( $list as $k => $label ) {
			$l = self::norm( $label );
			if ( false !== mb_strpos( $n, $l ) ) {
				$found[] = $k;
				$n       = str_replace( $l, ' ', $n );
			}
		}
		$rest = trim( preg_replace( '/\s+/u', ' ', $n ) );
		return array( $found, $rest );
	}

	private static function is_heading( $line, $words ) {
		$n = self::norm( rtrim( $line, ':' ) );
		foreach ( $words as $w ) {
			if ( $n === self::norm( $w ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ανάλυση. Επιστρέφει ['title'=>, 'purpose'=>, 'need'=>, 'goals'=>[...], 'warnings'=>[...]].
	 */
	public static function parse( $text ) {
		$lines = self::lines( $text );
		$res   = array(
			'title'    => '',
			'purpose'  => '',
			'need'     => '',
			'goals'    => array(),
			'warnings' => array(),
		);

		// Κεφαλίδα: «Τίτλος: …», «Σκοπός …», «Τεκμηρίωση …» (αν επικολληθούν και τα Δομικά στοιχεία).
		$stops = array( 'Στόχος', 'Σκοπός', 'Τεκμηρίωση', 'Άξονας', 'Ομάδα', 'Χρονοδιάγραμμα', 'Πηγές', 'Διάρκεια' );
		$n_l   = count( $lines );
		for ( $i = 0; $i < $n_l; $i++ ) {
			$l = $lines[ $i ];
			if ( '' === $res['title'] && preg_match( '/^Τίτλος\s*:\s*(.+)$/u', $l, $m ) ) {
				$res['title'] = trim( preg_replace( '/\s+Άξονας\s*:.*$/u', '', $m[1] ) );
			}
			foreach ( array( 'purpose' => '/^Σκοπός(?:\s+του\s+Σχεδίου)?(?:\s+Δράσης)?\s*:?\s*(.*)$/u', 'need' => '/^Τεκμηρίωση(?:\s+(?:της\s+)?αναγκαιότητας)?\s*:?\s*(.*)$/u' ) as $f => $re ) {
				if ( '' === $res[ $f ] && preg_match( $re, $l, $m ) ) {
					$buf = '' !== trim( $m[1] ) ? array( trim( $m[1] ) ) : array();
					for ( $j = $i + 1; $j < $n_l; $j++ ) {
						$stop = false;
						foreach ( $stops as $s ) {
							if ( 0 === mb_strpos( $lines[ $j ], $s ) ) {
								$stop = true;
							}
						}
						if ( $stop || count( $buf ) > 12 ) {
							break;
						}
						$buf[] = $lines[ $j ];
					}
					$res[ $f ] = trim( implode( ' ', $buf ) );
				}
			}
		}

		// Μπλοκ στόχων.
		$blocks = array();
		$cur    = null;
		foreach ( $lines as $l ) {
			if ( preg_match( '/^Στόχος\s*(\d)\b\s*[:.]?\s*(.*)$/u', $l, $m ) ) {
				if ( null !== $cur ) {
					$blocks[] = $cur;
				}
				$cur = array( 'no' => (int) $m[1], 'head' => trim( $m[2] ), 'lines' => array() );
				continue;
			}
			if ( null !== $cur && preg_match( '/^Χρονοδιάγραμμα/u', $l ) ) {
				$blocks[] = $cur;
				$cur      = null;
				continue;
			}
			if ( null !== $cur ) {
				$cur['lines'][] = $l;
			}
		}
		if ( null !== $cur ) {
			$blocks[] = $cur;
		}
		// Ο ίδιος στόχος μπορεί να εμφανίζεται δύο φορές (π.χ. στο χρονοδιάγραμμα)· κρατάμε το πληρέστερο.
		$by_no = array();
		foreach ( $blocks as $b ) {
			if ( ! isset( $by_no[ $b['no'] ] ) || count( $b['lines'] ) > count( $by_no[ $b['no'] ]['lines'] ) ) {
				$by_no[ $b['no'] ] = $b;
			}
		}
		ksort( $by_no );

		foreach ( $by_no as $b ) {
			$res['goals'][] = self::parse_goal( $b, $res['warnings'] );
		}
		if ( ! $res['goals'] ) {
			$res['warnings'][] = 'Δεν βρέθηκε κανένας «Στόχος». Βεβαιωθείτε ότι αντιγράψατε όλη τη σελίδα «Προβολή» του σχεδίου (Ctrl+A, Ctrl+C).';
		}
		if ( count( $res['goals'] ) > SD_Eval::MAX_GOALS ) {
			$res['warnings'][] = 'Βρέθηκαν ' . count( $res['goals'] ) . ' στόχοι· η φόρμα δέχεται έως ' . SD_Eval::MAX_GOALS . '. Θα εισαχθούν οι πρώτοι ' . SD_Eval::MAX_GOALS . '.';
			$res['goals']      = array_slice( $res['goals'], 0, SD_Eval::MAX_GOALS );
		}
		return $res;
	}

	private static function parse_goal( $b, &$warn ) {
		$sec  = 'head';
		$desc = array();
		$tool = array();
		$way  = array();
		$acts = array();
		$act  = null;
		$tit  = $b['head'];
		$push = function () use ( &$act, &$acts ) {
			if ( $act ) {
				$acts[] = $act;
			}
			$act = null;
		};
		foreach ( $b['lines'] as $l ) {
			if ( self::is_heading( $l, array( 'Διατύπωση Στόχου', 'Διατύπωση' ) ) ) {
				$sec = 'desc';
				continue;
			}
			if ( self::is_heading( $l, array( 'Ενέργειες' ) ) ) {
				$sec = 'acts';
				continue;
			}
			if ( self::is_heading( $l, array( 'Εργαλεία' ) ) ) {
				$push();
				$sec = 'tools';
				continue;
			}
			if ( self::is_heading( $l, array( 'Τρόπος Εργασίας', 'Τρόποι Εργασίας' ) ) ) {
				$push();
				$sec = 'ways';
				continue;
			}
			if ( self::is_heading( $l, array( 'Ανατροφοδότηση' ) ) ) {
				$push();
				$sec = 'skip';
				continue;
			}
			// «Εργαλεία … Τρόπος Εργασίας» μπορεί να έρθουν στην ίδια γραμμή.
			if ( preg_match( '/^Εργαλεία\s+(.+?)\s+Τρόπο[ςι]\s+Εργασίας\s*(.*)$/u', $l, $m ) ) {
				$push();
				$tool[] = $m[1];
				$way[]  = $m[2];
				$sec    = 'ways';
				continue;
			}
			switch ( $sec ) {
				case 'head':
					if ( ! preg_match( '/^\d+\s+(ενέργει|Υλοποίηση|Αποτίμηση)/u', $l ) && '' === $tit ) {
						$tit = $l;
					}
					break;
				case 'desc':
					$desc[] = $l;
					break;
				case 'acts':
					if ( preg_match( '/^(Υλοποίηση|Αποτίμηση)\s*(.*)$/u', $l, $m ) ) {
						$push();
						$act = array(
							'kind'  => 'Αποτίμηση' === $m[1] ? 'apotimisi' : 'ylopoiisi',
							'desc'  => array(),
							'crit'  => array(),
							'start' => '',
							'end'   => '',
							'inc'   => false,
						);
						$l = trim( $m[2] );
						if ( '' === $l ) {
							break;
						}
					}
					if ( ! $act ) {
						break;
					}
					if ( preg_match( '#(\d{1,2}/\d{1,2}/\d{4})\s*(?:έως|εως|-|–|—)\s*(\d{1,2}/\d{1,2}/\d{4})#u', $l, $m ) ) {
						$act['start'] = self::date_ymd( $m[1] );
						$act['end']   = self::date_ymd( $m[2] );
						$l            = trim( str_replace( $m[0], '', $l ) );
						if ( '' === $l ) {
							break;
						}
					}
					if ( preg_match( '/^Κριτήριο\s+Επιτυχίας\s*:?\s*(.*)$/u', $l, $m ) ) {
						$act['inc']    = true;
						$act['crit'][] = $m[1];
					} elseif ( $act['inc'] ) {
						$act['crit'][] = $l;
					} else {
						$act['desc'][] = $l;
					}
					break;
				case 'tools':
					$tool[] = $l;
					break;
				case 'ways':
					$way[] = $l;
					break;
			}
		}
		$push();

		list( $tools, $t_rest ) = self::match_list( implode( ' ', $tool ), SD_Eval::TOOLS );
		list( $ways, $w_rest )  = self::match_list( implode( ' ', $way ), SD_Eval::WAYS );
		$description            = trim( implode( ' ', $desc ) );
		$title                  = trim( $tit );
		if ( preg_match( '/(\.\.\.|…)$/u', $title ) ) { // κομμένος τίτλος: χωρίς τη μισή τελευταία λέξη
			$title = preg_replace( '/\s+\S*(\.\.\.|…)$/u', '', $title );
		}
		$title = rtrim( $title, " /-–,·:" );
		if ( '' === $title && '' !== $description ) {
			$title = mb_substr( $description, 0, 80 );
		}
		$out = array(
			'title'       => mb_substr( $title, 0, 255 ),
			'description' => $description,
			'tools'       => $tools,
			'tools_other' => mb_strlen( $t_rest ) > 2 ? mb_substr( $t_rest, 0, SD_Eval::OTHER_LIMIT ) : '',
			'ways'        => $ways,
			'ways_other'  => mb_strlen( $w_rest ) > 2 ? mb_substr( $w_rest, 0, SD_Eval::OTHER_LIMIT ) : '',
			'actions'     => array(),
		);
		foreach ( $acts as $a ) {
			$out['actions'][] = array(
				'kind'        => $a['kind'],
				'description' => trim( preg_replace( '/^\d+\.\s*/u', '', implode( ' ', $a['desc'] ) ) ),
				'start_date'  => $a['start'],
				'end_date'    => $a['end'],
				'criterion'   => trim( implode( ' ', $a['crit'] ) ),
			);
		}
		$n = 'Στόχος ' . (int) $b['no'];
		if ( '' === $description ) {
			$warn[] = $n . ': δεν βρέθηκε η «Διατύπωση Στόχου».';
		}
		if ( ! $out['actions'] ) {
			$warn[] = $n . ': δεν βρέθηκαν ενέργειες.';
		}
		if ( $out['tools_other'] ) {
			$warn[] = $n . ': στα εργαλεία υπάρχει κείμενο που δεν αναγνωρίστηκε· μπαίνει στο «Άλλο»: «' . $out['tools_other'] . '».';
		}
		if ( $out['ways_other'] ) {
			$warn[] = $n . ': στους τρόπους εργασίας υπάρχει κείμενο που δεν αναγνωρίστηκε· μπαίνει στο «Άλλο»: «' . $out['ways_other'] . '».';
		}
		foreach ( $out['actions'] as $i => $a ) {
			if ( ! $a['start_date'] || ! $a['end_date'] ) {
				$warn[] = $n . ', ενέργεια ' . ( $i + 1 ) . ': δεν βρέθηκαν ημερομηνίες.';
			}
		}
		return $out;
	}

	/* =================== Ενέργειες =================== */

	public static function handle( $do ) {
		if ( ! in_array( $do, array( 'plan_import_preview', 'plan_import_apply', 'plan_import_cancel' ), true ) ) {
			return null;
		}
		$plan = SD_Plans::get_plan( absint( $_POST['plan_id'] ?? 0 ) );
		$list = SD_Frontend::url( 'sxedia' );
		if ( ! $plan || ! SD_Plans::can_edit( $plan ) ) {
			return array( new WP_Error( 'sd', 'Δεν έχετε δικαίωμα να αλλάξετε αυτό το σχέδιο.' ), $list );
		}
		$page = SD_Frontend::url( 'sxedia', array( 'eisagogi' => $plan->id ) );
		if ( 'plan_import_cancel' === $do ) {
			delete_transient( self::key( $plan->id ) );
			return array( true, SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) ) );
		}
		if ( 'plan_import_preview' === $do ) {
			$text = isset( $_POST['text'] ) ? (string) wp_unslash( $_POST['text'] ) : '';
			$text = mb_substr( wp_strip_all_tags( $text ), 0, self::MAX_TEXT );
			if ( '' === trim( $text ) ) {
				return array( new WP_Error( 'sd', 'Επικολλήστε πρώτα το κείμενο από την πλατφόρμα.' ), $page );
			}
			$res = self::parse( $text );
			set_transient( self::key( $plan->id ), $res, HOUR_IN_SECONDS );
			set_transient( 'sd_msg_' . get_current_user_id(), array( $res['goals'] ? 'ok' : 'error', $res['goals'] ? 'Βρέθηκαν ' . count( $res['goals'] ) . ' στόχοι. Ελέγξτε την προεπισκόπηση και πατήστε «Εισαγωγή».' : 'Δεν αναγνωρίστηκε σχέδιο στο κείμενο.' ), 60 );
			return array( true, $page . '#proepiskopisi' );
		}
		// Εφαρμογή.
		$res = get_transient( self::key( $plan->id ) );
		if ( ! is_array( $res ) || empty( $res['goals'] ) ) {
			return array( new WP_Error( 'sd', 'Η προεπισκόπηση έληξε. Επικολλήστε ξανά το κείμενο.' ), $page );
		}
		global $wpdb;
		foreach ( SD_Plans::goals( $plan->id ) as $g ) {
			SD_Plans::delete_goal( $g->id );
		}
		foreach ( $res['goals'] as $gi => $g ) {
			$wpdb->insert(
				SD_Data::table( 'goals' ),
				array(
					'plan_id'     => $plan->id,
					'ord'         => $gi + 1,
					'title'       => sanitize_text_field( $g['title'] ),
					'description' => sanitize_textarea_field( $g['description'] ),
					'tools'       => implode( ',', $g['tools'] ),
					'tools_other' => sanitize_text_field( $g['tools_other'] ),
					'ways'        => implode( ',', $g['ways'] ),
					'ways_other'  => sanitize_text_field( $g['ways_other'] ),
				)
			);
			$goal_id = (int) $wpdb->insert_id;
			foreach ( $g['actions'] as $ai => $a ) {
				$wpdb->insert(
					SD_Data::table( 'goal_actions' ),
					array(
						'goal_id'     => $goal_id,
						'kind'        => isset( SD_Eval::ACTION_KINDS[ $a['kind'] ] ) ? $a['kind'] : 'ylopoiisi',
						'ord'         => $ai + 1,
						'description' => sanitize_textarea_field( $a['description'] ),
						'start_date'  => $a['start_date'] ? $a['start_date'] : null,
						'end_date'    => $a['end_date'] ? $a['end_date'] : null,
						'criterion'   => sanitize_textarea_field( $a['criterion'] ),
						'grades'      => '',
					)
				);
			}
		}
		$upd = array( 'updated_at' => current_time( 'mysql' ) );
		if ( ! empty( $_POST['use_title'] ) && '' !== $res['title'] ) {
			$upd['title'] = sanitize_text_field( mb_substr( $res['title'], 0, 255 ) );
		}
		foreach ( array( 'purpose', 'need' ) as $f ) {
			if ( ! empty( $_POST[ 'use_' . $f ] ) && '' !== $res[ $f ] ) {
				$upd[ $f ] = sanitize_textarea_field( $res[ $f ] );
			}
		}
		$wpdb->update( SD_Data::table( 'plans' ), $upd, array( 'id' => $plan->id ) );
		delete_transient( self::key( $plan->id ) );
		set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', 'Η στοχοθεσία εισήχθη από την πλατφόρμα. Ελέγξτε τον «Έλεγχο» του σχεδίου για ό,τι λείπει.' ), 60 );
		return array( true, SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) ) );
	}

	/* =================== Οθόνη =================== */

	private static function names( $keys, $list, $other ) {
		$out = array();
		foreach ( $keys as $k ) {
			$out[] = $list[ $k ] ?? $k;
		}
		if ( $other ) {
			$out[] = 'Άλλο: ' . $other;
		}
		return $out ? implode( ' · ', $out ) : '—';
	}

	public static function render( $plan_id ) {
		$plan = SD_Plans::get_plan( $plan_id );
		if ( ! $plan || ! SD_Plans::can_edit( $plan ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε δικαίωμα να αλλάξετε αυτό το σχέδιο.</p>';
			return;
		}
		echo '<p class="sd-noprint"><a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) ) ) . '">← ' . esc_html( trim( $plan->code . ' ' . $plan->title ) ) . '</a></p>';
		echo '<h2>Εισαγωγή από την πλατφόρμα αξιολόγησης</h2>';
		echo '<section class="sd-card"><h3>1. Αντιγραφή από την πλατφόρμα</h3><ol class="sd-steps">';
		echo '<li>Στην πλατφόρμα αξιολόγησης ανοίξτε το σχέδιο στην <strong>«Προβολή»</strong> και ανοίξτε όλους τους στόχους (βελάκι δεξιά), ώστε να φαίνονται οι ενέργειες.</li>';
		echo '<li>Πατήστε <kbd>Ctrl</kbd>+<kbd>A</kbd> (επιλογή όλων) και μετά <kbd>Ctrl</kbd>+<kbd>C</kbd> (αντιγραφή).</li>';
		echo '<li>Επικολλήστε παρακάτω με <kbd>Ctrl</kbd>+<kbd>V</kbd> και πατήστε «Ανάλυση». Δεν αλλάζει τίποτα ακόμη.</li>';
		echo '<li>Αν θέλετε και σκοπό/τεκμηρίωση, αντιγράψτε επίσης τη σελίδα με τα Δομικά στοιχεία και επικολλήστε την στο ίδιο κουτί, κάτω από την πρώτη.</li></ol>';
		SD_Frontend::form_open( 'plan_import_preview' );
		echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '">';
		echo '<textarea name="text" rows="10" placeholder="Επικόλληση εδώ (Ctrl+V)"></textarea>';
		echo '<p><button class="sd-button" type="submit">Ανάλυση</button></p></form></section>';

		$res = get_transient( self::key( $plan->id ) );
		if ( ! is_array( $res ) ) {
			return;
		}
		echo '<section class="sd-card" id="proepiskopisi"><h3>2. Προεπισκόπηση</h3>';
		if ( $res['warnings'] ) {
			echo '<div class="sd-issues"><ul>';
			foreach ( $res['warnings'] as $w ) {
				echo '<li class="sd-issue-warning">' . esc_html( $w ) . '</li>';
			}
			echo '</ul></div>';
		}
		if ( $res['title'] || $res['purpose'] || $res['need'] ) {
			echo '<dl class="sd-dl">';
			foreach ( array( 'title' => 'Τίτλος', 'purpose' => 'Σκοπός', 'need' => 'Τεκμηρίωση' ) as $f => $l ) {
				if ( $res[ $f ] ) {
					echo '<dt>' . esc_html( $l ) . '</dt><dd>' . esc_html( $res[ $f ] ) . '</dd>';
				}
			}
			echo '</dl>';
		}
		foreach ( $res['goals'] as $i => $g ) {
			echo '<div class="sd-imp-goal"><h4>Στόχος ' . (int) ( $i + 1 ) . ( $g['title'] ? ': ' . esc_html( $g['title'] ) : '' ) . '</h4>';
			echo '<p>' . esc_html( $g['description'] ) . ' <span class="sd-meta">(' . (int) SD_Eval::length( $g['description'] ) . '/' . (int) SD_Eval::LIMIT . ')</span></p>';
			echo '<p class="sd-meta"><strong>Εργαλεία:</strong> ' . esc_html( self::names( $g['tools'], SD_Eval::TOOLS, $g['tools_other'] ) ) . '<br><strong>Τρόποι εργασίας:</strong> ' . esc_html( self::names( $g['ways'], SD_Eval::WAYS, $g['ways_other'] ) ) . '</p><ol class="sd-imp-acts">';
			foreach ( $g['actions'] as $a ) {
				echo '<li><span class="sd-badge sd-kind-' . esc_attr( $a['kind'] ) . '">' . esc_html( SD_Eval::ACTION_KINDS[ $a['kind'] ] ) . '</span> ' . esc_html( $a['description'] );
				echo ' <span class="sd-meta">' . esc_html( ( $a['start_date'] ? mysql2date( 'd/m/Y', $a['start_date'] ) : '—' ) . ' έως ' . ( $a['end_date'] ? mysql2date( 'd/m/Y', $a['end_date'] ) : '—' ) ) . '</span>';
				if ( $a['criterion'] ) {
					echo '<div class="sd-criterion"><strong>Κριτήριο επιτυχίας:</strong> ' . esc_html( $a['criterion'] ) . '</div>';
				}
				echo '</li>';
			}
			echo '</ol></div>';
		}
		if ( $res['goals'] ) {
			SD_Frontend::form_open( 'plan_import_apply' );
			echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '">';
			if ( $res['title'] ) {
				echo '<label class="sd-inline"><input type="checkbox" name="use_title" value="1"> Να ενημερωθεί και ο τίτλος του σχεδίου</label><br>';
			}
			foreach ( array( 'purpose' => 'ο σκοπός', 'need' => 'η τεκμηρίωση αναγκαιότητας' ) as $f => $l ) {
				if ( $res[ $f ] ) {
					echo '<label class="sd-inline"><input type="checkbox" name="use_' . esc_attr( $f ) . '" value="1" checked> Να ενημερωθεί και ' . esc_html( $l ) . '</label><br>';
				}
			}
			$old = count( SD_Plans::goals( $plan->id ) );
			echo '<p class="sd-notice sd-error">Η εισαγωγή <strong>αντικαθιστά</strong> τη στοχοθεσία του σχεδίου' . ( $old ? ' (τώρα ' . (int) $old . ' στόχοι με τις ενέργειές τους)' : '' ) . '. Η ομάδα, ο συντονιστής και τα άλλα στοιχεία δεν αλλάζουν.</p>';
			echo '<p><button class="sd-button" type="submit" onclick="return confirm(\'Να αντικατασταθεί η στοχοθεσία του σχεδίου με την εισαγωγή;\');">Εισαγωγή</button></p></form>';
		}
		SD_Frontend::form_open( 'plan_import_cancel' );
		echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '"><button class="sd-button sd-small sd-quiet" type="submit">Ακύρωση</button></form>';
		echo '</section>';
	}
}
