<?php
/**
 * Αρχείο προηγούμενων ετών: Σχέδια Δράσης και αποτιμήσεις που εισάγονται από την
 * πλατφόρμα του ΙΕΠ (παλιά ή νέα), χωρίς να ενεργοποιείται το έτος· η Ετήσια Έκθεση·
 * «Συνέχεια σε νέο σχέδιο» και ενδεικτικές προτάσεις για τη νέα χρονιά.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Archive {

	/** Η εσωτερική αξιολόγηση με Σχέδια Δράσης ξεκίνησε το 2021-22. */
	const FIRST_YEAR = 2021;
	const OPT_REPORT = 'sd_year_report_';

	const TYPES = array(
		'plan'       => 'Σχέδιο Δράσης (νέα πλατφόρμα, PDF ή Word της εφαρμογής)',
		'legacy'     => 'Φόρμα Αποτύπωσης Σχεδίου Δράσης (παλιά πλατφόρμα): σχέδιο και αποτίμηση',
		'collective' => 'Συλλογικός Προγραμματισμός: όλα τα σχέδια της χρονιάς',
		'report'     => 'Ετήσια Έκθεση Εσωτερικής Αξιολόγησης',
	);

	public static function url( $args = array() ) {
		return SD_Frontend::url( 'sxedia', array_merge( array( 'arxeio' => 1 ), $args ) );
	}

	private static function t( $name ) {
		return SD_Data::table( $name );
	}

	/* =================== Δεδομένα =================== */

	/** Έτη πριν από το τρέχον, νεότερο πρώτο. */
	public static function past_years() {
		$act = SD_Data::active_year();
		if ( ! $act ) {
			return array();
		}
		return array_values(
			array_filter(
				SD_Data::years(),
				function ( $y ) use ( $act ) {
					return $y->starts_on < $act->starts_on;
				}
			)
		);
	}

	public static function is_archive( $plan ) {
		if ( ! $plan ) {
			return false;
		}
		foreach ( self::past_years() as $y ) {
			if ( (int) $y->id === (int) $plan->year_id ) {
				return true;
			}
		}
		return false;
	}

	private static function year_label( $year_id ) {
		foreach ( SD_Data::years() as $y ) {
			if ( (int) $y->id === (int) $year_id ) {
				return $y->label;
			}
		}
		return '';
	}

	/** Το έτος με αυτό το έτος έναρξης (δημιουργείται αν λείπει, χωρίς να γίνει τρέχον). */
	private static function year_id( $start, $create ) {
		$label = $start . '-' . ( $start + 1 );
		foreach ( SD_Data::years() as $y ) {
			if ( $y->label === $label ) {
				return (int) $y->id;
			}
		}
		return $create ? SD_Data::add_year( $start ) : 0;
	}

	/** Αποτίμηση σχεδίου: [degree, achieved, difficulties, next_steps, sections] ή null. */
	public static function get_eval( $plan_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'plan_evals' ) . ' WHERE plan_id = %d', (int) $plan_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$s               = json_decode( (string) $row['sections'], true );
		$row['sections'] = is_array( $s ) ? $s : array();
		$row['degree']   = (int) $row['degree'];
		return $row;
	}

	private static function save_eval( $plan_id, $ev, $raw = null ) {
		global $wpdb;
		$old = self::get_eval( $plan_id );
		$wpdb->replace(
			self::t( 'plan_evals' ),
			array(
				'plan_id'      => (int) $plan_id,
				'degree'       => isset( SD_ArchParse::DEGREES[ (int) ( $ev['degree'] ?? 0 ) ] ) ? (int) $ev['degree'] : 0,
				'achieved'     => sanitize_textarea_field( (string) ( $ev['achieved'] ?? '' ) ),
				'difficulties' => sanitize_textarea_field( (string) ( $ev['difficulties'] ?? '' ) ),
				'next_steps'   => sanitize_textarea_field( (string) ( $ev['next_steps'] ?? '' ) ),
				'sections'     => wp_json_encode( array_map( 'sanitize_textarea_field', array_intersect_key( (array) ( $ev['sections'] ?? array() ), SD_ArchParse::SECTIONS ) ) ),
				'raw'          => null === $raw ? (string) ( $old['raw'] ?? '' ) : sanitize_textarea_field( $raw ),
				'updated_by'   => get_current_user_id(),
				'updated_at'   => current_time( 'mysql' ),
			)
		);
	}

	public static function get_report( $year_id ) {
		$r = get_option( self::OPT_REPORT . (int) $year_id );
		return is_array( $r ) ? $r : null;
	}

	private static function clean( $v ) {
		return is_array( $v ) ? array_map( array( __CLASS__, 'clean' ), $v ) : ( is_int( $v ) ? $v : sanitize_textarea_field( (string) $v ) );
	}

	/* =================== Ενέργειες =================== */

	private static function tkey() {
		return 'sd_arx_imp_' . get_current_user_id();
	}

	private static function msg( $type, $text ) {
		set_transient( 'sd_msg_' . get_current_user_id(), array( $type, $text ), 60 );
	}

	public static function handle( $do ) {
		$ours = array( 'arx_preview', 'arx_apply', 'arx_cancel', 'arx_eval_save', 'arx_eval_parse', 'arx_continue', 'arx_ai', 'arx_delete', 'arx_report_delete' );
		if ( ! in_array( $do, $ours, true ) ) {
			return null;
		}
		$back = self::url();

		if ( in_array( $do, array( 'arx_eval_save', 'arx_eval_parse' ), true ) ) {
			$plan = SD_Plans::get_plan( absint( $_POST['plan_id'] ?? 0 ) );
			if ( ! $plan || ! SD_Plans::can_edit( $plan ) ) {
				return array( new WP_Error( 'sd', 'Δεν έχετε δικαίωμα να αλλάξετε αυτή την αποτίμηση.' ), $back );
			}
			$page = self::url( array( 'apotimisi' => $plan->id ) );
			$ev   = self::get_eval( $plan->id );
			$ev   = $ev ? $ev : array();
			if ( 'arx_eval_parse' === $do ) {
				$text = mb_substr( wp_strip_all_tags( (string) wp_unslash( $_POST['text'] ?? '' ) ), 0, SD_PlanImport::MAX_TEXT );
				if ( '' === trim( $text ) ) {
					return array( new WP_Error( 'sd', 'Επικολλήστε πρώτα το κείμενο της αποτίμησης.' ), $page );
				}
				$new = SD_ArchParse::legacy( $text )['eval'];
				if ( ! SD_ArchParse::has_eval( $new ) ) {
					self::msg( 'error', 'Δεν αναγνωρίστηκαν ενότητες αποτίμησης (π.χ. «Αποτελέσματα», «Δυσκολίες», «Βαθμός επίτευξης»). Συμπληρώστε τα πεδία με το χέρι.' );
					return array( true, $page );
				}
				foreach ( array( 'degree', 'achieved', 'difficulties', 'next_steps' ) as $f ) {
					if ( ! empty( $new[ $f ] ) ) {
						$ev[ $f ] = $new[ $f ];
					}
				}
				$ev['sections'] = array_merge( (array) ( $ev['sections'] ?? array() ), $new['sections'] );
				self::save_eval( $plan->id, $ev, $text );
				self::msg( 'ok', 'Τα πεδία συμπληρώθηκαν από το κείμενο. Ελέγξτε τα και διορθώστε ό,τι χρειάζεται.' );
				return array( true, $page );
			}
			foreach ( array( 'degree', 'achieved', 'difficulties', 'next_steps' ) as $f ) {
				$ev[ $f ] = wp_unslash( $_POST[ $f ] ?? '' );
			}
			self::save_eval( $plan->id, $ev );
			self::msg( 'ok', 'Η αποτίμηση αποθηκεύτηκε.' );
			return array( true, SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) ) );
		}

		if ( ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return array( new WP_Error( 'sd', 'Μόνο η διεύθυνση διαχειρίζεται το αρχείο.' ), $back );
		}

		switch ( $do ) {
			case 'arx_cancel':
				delete_transient( self::tkey() );
				return array( true, $back );

			case 'arx_preview':
				return self::preview_post( $back );

			case 'arx_apply':
				return self::apply_post( $back );

			case 'arx_delete':
				$plan = SD_Plans::get_plan( absint( $_POST['plan_id'] ?? 0 ) );
				if ( ! $plan || ! self::is_archive( $plan ) ) {
					return array( new WP_Error( 'sd', 'Το σχέδιο δεν βρέθηκε στο αρχείο.' ), $back );
				}
				global $wpdb;
				$wpdb->delete( self::t( 'plan_evals' ), array( 'plan_id' => (int) $plan->id ) );
				SD_Plans::delete_plan( $plan->id );
				self::msg( 'ok', 'Το σχέδιο διαγράφηκε από το αρχείο.' );
				return array( true, $back );

			case 'arx_report_delete':
				delete_option( self::OPT_REPORT . absint( $_POST['year_id'] ?? 0 ) );
				self::msg( 'ok', 'Η Ετήσια Έκθεση διαγράφηκε από το αρχείο.' );
				return array( true, $back );

			case 'arx_continue':
				$plan = SD_Plans::get_plan( absint( $_POST['plan_id'] ?? 0 ) );
				$year = SD_Data::active_year();
				if ( ! $plan || ! $year || ! self::is_archive( $plan ) ) {
					return array( new WP_Error( 'sd', 'Το σχέδιο δεν βρέθηκε στο αρχείο.' ), SD_Frontend::url( 'sxedia' ) );
				}
				return array( true, SD_Frontend::url( 'sxedia', array( 'id' => self::copy_plan( $plan, $year->id ) ) ) );
		}

		// arx_ai
		$prompt = self::ai_prompt();
		if ( '' === $prompt ) {
			return array( new WP_Error( 'sd', 'Το αρχείο είναι άδειο: εισαγάγετε πρώτα σχέδια ή εκθέσεις προηγούμενων ετών.' ), $back );
		}
		$text = SD_AiField::ask( $prompt, 2500 );
		set_transient(
			'sd_arx_ai_' . get_current_user_id(),
			array(
				'when'   => current_time( 'mysql' ),
				'text'   => is_wp_error( $text ) ? '' : sanitize_textarea_field( $text ),
				'prompt' => $prompt,
				'error'  => is_wp_error( $text ) ? $text->get_error_message() : '',
			),
			DAY_IN_SECONDS
		);
		$to = sanitize_key( wp_unslash( $_POST['to'] ?? '' ) );
		return array( true, ( 'list' === $to ? SD_Frontend::url( 'sxedia' ) : $back ) . '#protaseis' );
	}

	private static function max_start() {
		$act = SD_Data::active_year();
		return $act ? (int) substr( $act->starts_on, 0, 4 ) - 1 : (int) current_time( 'Y' ) - 1;
	}

	private static function preview_post( $back ) {
		$text = mb_substr( wp_strip_all_tags( (string) wp_unslash( $_POST['text'] ?? '' ) ), 0, SD_PlanImport::MAX_TEXT );
		if ( '' === trim( $text ) ) {
			return array( new WP_Error( 'sd', 'Επικολλήστε πρώτα το κείμενο από την πλατφόρμα.' ), $back );
		}
		$type   = SD_ArchParse::detect( $text );
		$found  = SD_ArchParse::year( $text );
		$chosen = absint( $_POST['start_year'] ?? 0 );
		$start  = $found ? $found : $chosen;
		if ( $start < self::FIRST_YEAR || $start > self::max_start() ) {
			$why = $found ? 'Το κείμενο είναι του ' . $found . '-' . ( $found + 1 ) . ', που δεν είναι προηγούμενο έτος.' : 'Διαλέξτε σχολικό έτος.';
			return array( new WP_Error( 'sd', $why ), $back );
		}
		switch ( $type ) {
			case 'report':
				$data = SD_ArchParse::report( $text );
				$ok   = $data['axes'] || $data['results'];
				break;
			case 'collective':
				$data = SD_ArchParse::collective( $text );
				$ok   = (bool) $data;
				break;
			case 'legacy':
				$data = SD_ArchParse::legacy( $text );
				$ok   = '' !== $data['title'] || '' !== $data['goal'];
				break;
			default:
				$data = SD_PlanImport::parse( $text );
				$ok   = (bool) $data['goals'];
		}
		set_transient(
			self::tkey(),
			array(
				'type'  => $type,
				'start' => $start,
				'found' => $found,
				'code'  => sanitize_text_field( mb_substr( (string) wp_unslash( $_POST['code'] ?? '' ), 0, 10 ) ),
				'data'  => $data,
				'raw'   => 'legacy' === $type ? $text : '',
			),
			HOUR_IN_SECONDS
		);
		self::msg( $ok ? 'ok' : 'error', $ok ? 'Αναγνωρίστηκε: ' . self::TYPES[ $type ] . '. Ελέγξτε την προεπισκόπηση.' : 'Δεν αναγνωρίστηκε περιεχόμενο στο κείμενο. Ελέγξτε ότι αντιγράψατε όλη τη σελίδα.' );
		return array( true, $back . '#proepiskopisi' );
	}

	/** Σχέδιο του έτους με παρόμοιο τίτλο (για συμπλήρωση αντί για διπλό). */
	private static function match_plan( $year_id, $title ) {
		$best = null;
		$sc   = 0.6;
		foreach ( $year_id ? SD_Plans::plans( $year_id ) : array() as $p ) {
			$s = SD_ArchParse::similar( $p->title, $title );
			if ( $s >= $sc ) {
				$best = $p;
				$sc   = $s;
			}
		}
		return $best;
	}

	/** Σχέδιο με έναν στόχο (παλιά πλατφόρμα): στοιχεία, στόχος, ενέργειες ως κείμενο. */
	private static function write_legacy_plan( $plan_id, $d, $keep_title = false ) {
		global $wpdb;
		$upd = array( 'updated_at' => current_time( 'mysql' ) );
		if ( ! $keep_title && '' !== ( $d['title'] ?? '' ) ) {
			$upd['title'] = sanitize_text_field( mb_substr( $d['title'], 0, 255 ) );
		}
		if ( ! empty( $d['axis'] ) ) {
			$upd['axis'] = (int) $d['axis'];
		}
		if ( '' !== ( $d['goal'] ?? '' ) ) {
			$upd['purpose'] = sanitize_textarea_field( $d['goal'] );
		}
		if ( '' !== ( $d['need'] ?? '' ) ) {
			$upd['need'] = sanitize_textarea_field( $d['need'] );
		}
		$wpdb->update( self::t( 'plans' ), $upd, array( 'id' => (int) $plan_id ) );
		if ( '' === ( $d['goal'] ?? '' ) ) {
			return;
		}
		foreach ( SD_Plans::goals( $plan_id ) as $g ) {
			SD_Plans::delete_goal( $g->id );
		}
		$wpdb->insert(
			self::t( 'goals' ),
			array(
				'plan_id'     => (int) $plan_id,
				'ord'         => 1,
				'title'       => '',
				'description' => sanitize_textarea_field( $d['goal'] ),
				'tools'       => '',
				'tools_other' => '',
				'ways'        => '',
				'ways_other'  => '',
			)
		);
		$gid  = (int) $wpdb->insert_id;
		$sec  = $d['eval']['sections'] ?? array();
		$acts = array(
			array( 'ylopoiisi', $sec['actions'] ?? '', '' ),
			array( 'apotimisi', $sec['evalproc'] ?? '', $sec['criteria'] ?? '' ),
		);
		foreach ( $acts as $i => $a ) {
			if ( '' === $a[1] && '' === $a[2] ) {
				continue;
			}
			$wpdb->insert(
				self::t( 'goal_actions' ),
				array(
					'goal_id'     => $gid,
					'kind'        => $a[0],
					'ord'         => $i + 1,
					'description' => sanitize_textarea_field( $a[1] ),
					'start_date'  => null,
					'end_date'    => null,
					'criterion'   => sanitize_textarea_field( $a[2] ),
					'grades'      => '',
				)
			);
		}
	}

	private static function apply_post( $back ) {
		$t = get_transient( self::tkey() );
		if ( ! is_array( $t ) ) {
			return array( new WP_Error( 'sd', 'Η προεπισκόπηση έληξε. Επικολλήστε ξανά το κείμενο.' ), $back );
		}
		$year_id = self::year_id( $t['start'], true );
		if ( is_wp_error( $year_id ) ) {
			return array( $year_id, $back );
		}
		$label = $t['start'] . '-' . ( $t['start'] + 1 );
		$d     = $t['data'];
		$to    = $back;
		switch ( $t['type'] ) {
			case 'report':
				update_option( self::OPT_REPORT . $year_id, self::clean( $d ), false );
				self::msg( 'ok', 'Η Ετήσια Έκθεση του ' . $label . ' μπήκε στο αρχείο.' );
				break;

			case 'collective':
				$new = 0;
				foreach ( $d as $p ) {
					if ( self::match_plan( $year_id, $p['title'] ) ) {
						continue;
					}
					$id = SD_Plans::add_plan( $year_id, '', sanitize_text_field( '' !== $p['title'] ? $p['title'] : 'Σχέδιο Δράσης ' . $label ), $p['axis'] );
					if ( ! is_wp_error( $id ) ) {
						self::write_legacy_plan( $id, array( 'goal' => $p['goal'] ) );
						++$new;
					}
				}
				self::msg( 'ok', 'Μπήκαν στο αρχείο του ' . $label . ' ' . $new . ' σχέδια. Μπορείτε τώρα να επικολλήσετε τη «Φόρμα Αποτύπωσης» κάθε σχεδίου για να προστεθούν αναλυτικά και η αποτίμησή του.' );
				break;

			case 'legacy':
				$target = absint( $_POST['target'] ?? 0 );
				$plan   = $target ? SD_Plans::get_plan( $target ) : null;
				if ( $plan && (int) $plan->year_id !== (int) $year_id ) {
					$plan = null;
				}
				$id = $plan ? (int) $plan->id : SD_Plans::add_plan( $year_id, $t['code'], sanitize_text_field( '' !== $d['title'] ? mb_substr( $d['title'], 0, 255 ) : 'Σχέδιο Δράσης ' . $label ), $d['axis'] ? $d['axis'] : 1 );
				if ( is_wp_error( $id ) ) {
					return array( $id, $back );
				}
				self::write_legacy_plan( $id, $d, (bool) $plan );
				if ( SD_ArchParse::has_eval( $d['eval'] ) ) {
					self::save_eval( $id, $d['eval'], $t['raw'] );
				}
				self::msg( 'ok', ( $plan ? 'Συμπληρώθηκε' : 'Μπήκε στο αρχείο' ) . ' το σχέδιο «' . $d['title'] . '» με την αποτίμησή του.' );
				$to = SD_Frontend::url( 'sxedia', array( 'id' => $id ) );
				break;

			default:
				if ( empty( $d['goals'] ) ) {
					return array( new WP_Error( 'sd', 'Δεν αναγνωρίστηκαν στόχοι.' ), $back );
				}
				$title = '' !== $d['title'] ? mb_substr( $d['title'], 0, 255 ) : 'Σχέδιο Δράσης ' . $label;
				$axis  = ! empty( $d['axis'] ) && isset( SD_Eval::AXES[ (int) $d['axis'] ] ) ? (int) $d['axis'] : 1;
				$id    = SD_Plans::add_plan( $year_id, $t['code'], sanitize_text_field( $title ), $axis );
				if ( is_wp_error( $id ) ) {
					return array( $id, $back );
				}
				SD_PlanImport::apply( $id, $d, array_fill_keys( array( 'title', 'axis', 'purpose', 'need', 'sources', 'praxi', 'continuation', 'collab' ), true ) );
				self::msg( 'ok', 'Το σχέδιο μπήκε στο αρχείο του ' . $label . '. Αν έχετε και την αποτίμησή του, επικολλήστε την εδώ.' );
				$to = self::url( array( 'apotimisi' => $id ) );
		}
		delete_transient( self::tkey() );
		return array( true, $to );
	}

	/** Αντιγραφή σχεδίου του αρχείου στο τρέχον έτος, ως συνέχεια. */
	private static function copy_plan( $plan, $year_id ) {
		global $wpdb;
		$id      = SD_Plans::add_plan( $year_id, '', $plan->title, (int) $plan->axis );
		$sources = array_filter( explode( ',', (string) $plan->sources ) );
		$sources = array_values( array_unique( array_merge( $sources, array( 'apotimisi' ) ) ) );
		$wpdb->update(
			self::t( 'plans' ),
			array(
				'purpose'      => mb_substr( $plan->purpose, 0, SD_Eval::PURPOSE_LIMIT ),
				'need'         => mb_substr( $plan->need, 0, SD_Eval::PURPOSE_LIMIT ),
				'sources'      => implode( ',', $sources ),
				'continuation' => 'nai',
				'collab'       => $plan->collab,
				'collab_note'  => $plan->collab_note,
			),
			array( 'id' => $id )
		);
		foreach ( SD_Plans::goals( $plan->id ) as $g ) {
			$wpdb->insert(
				self::t( 'goals' ),
				array(
					'plan_id'     => $id,
					'ord'         => $g->ord,
					'title'       => $g->title,
					'description' => mb_substr( $g->description, 0, SD_Eval::LIMIT ),
					'tools'       => $g->tools,
					'tools_other' => $g->tools_other,
					'ways'        => $g->ways,
					'ways_other'  => $g->ways_other,
				)
			);
			$gid = (int) $wpdb->insert_id;
			foreach ( SD_Plans::actions( $g->id ) as $a ) {
				$wpdb->insert(
					self::t( 'goal_actions' ),
					array(
						'goal_id'     => $gid,
						'kind'        => $a->kind,
						'ord'         => $a->ord,
						'description' => mb_substr( $a->description, 0, SD_Eval::LIMIT ),
						'start_date'  => null, // Οι μήνες ορίζονται ξανά για τη νέα χρονιά.
						'end_date'    => null,
						'criterion'   => mb_substr( $a->criterion, 0, SD_Eval::LIMIT ),
						'grades'      => '',
					)
				);
			}
		}
		self::msg( 'ok', 'Δημιουργήθηκε νέο σχέδιο ως συνέχεια του «' . $plan->title . '». Προσαρμόστε το στη νέα χρονιά: στόχοι, 2+1 ενέργειες, μήνες, ομάδα. Ο «Έλεγχος» δείχνει τι λείπει.' );
		return $id;
	}

	/* =================== Προτάσεις (AI) =================== */

	private static function ai_prompt() {
		$act  = SD_Data::active_year();
		$body = '';
		foreach ( self::past_years() as $y ) {
			$t   = '';
			$rep = self::get_report( $y->id );
			if ( $rep ) {
				$t .= "\n## " . $y->label . ' · Ετήσια Έκθεση' . "\n";
				foreach ( $rep['axes'] as $n => $a ) {
					$t .= '- ' . SD_Eval::axis_label( $n ) . ': ' . ( $a[0] ? $a[0] . '/4' : '—' ) . "\n";
				}
				foreach ( $rep['improve'] as $f => $txt ) {
					$t .= 'Σημεία προς βελτίωση (' . $f . '): ' . mb_substr( str_replace( "\n", '; ', $txt ), 0, 400 ) . "\n";
				}
				$t .= $rep['train_needs'] ? 'Προτάσεις για επιμορφώσεις: ' . mb_substr( implode( '; ', $rep['train_needs'] ), 0, 500 ) . "\n" : '';
			}
			foreach ( SD_Plans::plans( $y->id ) as $p ) {
				$t .= "\n## " . $y->label . ' · Σχέδιο: ' . $p->title . "\nΆξονας: " . SD_Eval::axis_label( $p->axis ) . "\n";
				$t .= $p->purpose ? 'Σκοπός/Στόχος: ' . mb_substr( $p->purpose, 0, 400 ) . "\n" : '';
				foreach ( SD_Plans::goals( $p->id ) as $g ) {
					if ( mb_substr( $g->description, 0, 60 ) !== mb_substr( $p->purpose, 0, 60 ) ) {
						$t .= '- Στόχος ' . (int) $g->ord . ': ' . mb_substr( $g->description, 0, 300 ) . "\n";
					}
				}
				$ev = self::get_eval( $p->id );
				if ( $ev ) {
					$t .= $ev['degree'] ? 'Επίτευξη: ' . SD_ArchParse::DEGREES[ $ev['degree'] ] . "\n" : '';
					$t .= $ev['achieved'] ? 'Αποτελέσματα: ' . mb_substr( $ev['achieved'], 0, 400 ) . "\n" : '';
					$t .= $ev['difficulties'] ? 'Δυσκολίες: ' . mb_substr( $ev['difficulties'], 0, 300 ) . "\n" : '';
					$t .= $ev['next_steps'] ? 'Προτάσεις για συνέχεια: ' . mb_substr( $ev['next_steps'], 0, 400 ) . "\n" : '';
				}
			}
			if ( mb_strlen( $body . $t ) > 12000 ) {
				break;
			}
			$body .= $t;
		}
		if ( '' === $body ) {
			return '';
		}
		$axes = array();
		foreach ( SD_Eval::AXES as $n => $a ) {
			$axes[] = $n . '. ' . $a[0];
		}
		return 'Ακολουθούν στοιχεία της εσωτερικής αξιολόγησης ενός Δημοτικού σχολείου από προηγούμενα έτη: Σχέδια Δράσης, η αποτίμησή τους και οι Ετήσιες Εκθέσεις (βαθμολογία αξόνων 1–4, σημεία προς βελτίωση).' . "\n" . $body . "\n"
			. 'Πρότεινε 3 ενδεικτικές ιδέες για Σχέδια Δράσης του σχολικού έτους ' . ( $act ? $act->label : '' ) . ', που αξιοποιούν τα παραπάνω: '
			. 'άξονες με χαμηλότερη βαθμολογία ή «σημεία προς βελτίωση», συνέχεια σχεδίων που δεν επιτεύχθηκαν πλήρως, και όχι επανάληψη όσων επιτεύχθηκαν πλήρως.' . "\n"
			. 'Για κάθε ιδέα γράψε: Τίτλος· Άξονας (ένας από: ' . implode( '· ', $axes ) . ')· Σκοπός (1–2 προτάσεις)· Αρχική ανάγκη (με αναφορά σε ό,τι έδειξαν τα προηγούμενα έτη)· 3 Στόχοι (μία πρόταση ο καθένας)· Γιατί προτείνεται (μία πρόταση). '
			. 'Ρεαλιστικά για δημόσιο Δημοτικό, απλό κείμενο χωρίς μορφοποίηση markdown.';
	}

	private static function ai_box( $to ) {
		$keep = get_transient( 'sd_arx_ai_' . get_current_user_id() );
		echo '<section class="sd-card" id="protaseis"><h3>Ενδεικτικές προτάσεις για νέα σχέδια</h3>';
		echo '<p class="sd-help">Η εφαρμογή διαβάζει τα σχέδια, τις αποτιμήσεις και τις Ετήσιες Εκθέσεις του αρχείου (χωρίς ονόματα) και προτείνει ιδέες. Είναι <strong>ενδεικτικές</strong>: τις συζητά και τις διαμορφώνει η ομάδα και ο Σύλλογος.</p>';
		SD_Frontend::form_open( 'arx_ai' );
		echo '<input type="hidden" name="to" value="' . esc_attr( $to ) . '">';
		echo '<p><button class="sd-button" type="submit">' . ( $keep ? 'Νέες προτάσεις' : 'Προτάσεις από το αρχείο' ) . '</button></p></form>';
		if ( is_array( $keep ) ) {
			if ( $keep['text'] ) {
				echo '<div class="sd-copy"><div class="sd-copy-head"><span class="sd-meta">' . esc_html( mysql2date( 'd/m/Y H:i', $keep['when'] ) ) . '</span> <button type="button" class="sd-button sd-small" data-copy>Αντιγραφή</button></div>';
				echo '<textarea readonly rows="16">' . esc_textarea( $keep['text'] ) . '</textarea></div>';
			} else {
				echo '<p class="sd-notice sd-error">' . esc_html( $keep['error'] ) . '</p>';
				echo '<p class="sd-help">Μπορείτε να αντιγράψετε την ερώτηση και να την επικολλήσετε σε μια δωρεάν υπηρεσία AI:</p>';
				echo '<div class="sd-copy"><div class="sd-copy-head"><strong>Ερώτηση</strong> <button type="button" class="sd-button sd-small" data-copy>Αντιγραφή</button></div><textarea readonly rows="8">' . esc_textarea( $keep['prompt'] ) . '</textarea></div>';
			}
		}
		echo '</section>';
	}

	/* =================== Οθόνες =================== */

	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['apotimisi'] ) ) {
			self::render_eval( absint( $_GET['apotimisi'] ) );
			return;
		}
		if ( isset( $_GET['ekthesi'] ) ) {
			self::render_report( absint( $_GET['ekthesi'] ) );
			return;
		}
		// phpcs:enable
		$manage = current_user_can( SD_Roles::CAP_MANAGE );
		echo '<p class="sd-noprint"><a href="' . esc_url( SD_Frontend::url( 'sxedia' ) ) . '">← Σχέδια Δράσης τρέχοντος έτους</a></p>';
		echo '<h2>Αρχείο προηγούμενων ετών</h2>';
		echo '<p class="sd-help">Τα Σχέδια Δράσης των προηγούμενων ετών, η αποτίμησή τους και οι Ετήσιες Εκθέσεις. Βοηθούν στον σχεδιασμό της νέας χρονιάς: συνέχεια ενός σχεδίου, τεκμηρίωση της αρχικής ανάγκης, ιδέες.</p>';

		if ( $manage ) {
			self::import_card();
		}

		$any = false;
		foreach ( self::past_years() as $y ) {
			$plans = SD_Plans::plans( $y->id );
			$rep   = self::get_report( $y->id );
			if ( ! $plans && ! $rep ) {
				continue;
			}
			$any = true;
			echo '<section class="sd-card sd-arx-year"><h3>' . esc_html( $y->label ) . '</h3>';
			if ( $rep ) {
				echo '<div class="sd-arx-plan"><div class="sd-grow"><strong><a href="' . esc_url( self::url( array( 'ekthesi' => $y->id ) ) ) . '">Ετήσια Έκθεση Εσωτερικής Αξιολόγησης</a></strong>';
				echo '<div class="sd-meta">' . ( $rep['degree'] ? 'Επίτευξη στόχων: ' . esc_html( SD_ArchParse::DEGREES[ $rep['degree'] ] ?? '' ) . ' · ' : '' ) . 'Άξονες με βαθμολογία: ' . count( $rep['axes'] ) . '</div></div>';
				if ( $manage ) {
					SD_Frontend::form_open( 'arx_report_delete' );
					echo '<input type="hidden" name="year_id" value="' . (int) $y->id . '"><button class="sd-button sd-small sd-quiet" type="submit" title="Διαγραφή της Έκθεσης" onclick="return confirm(\'Οριστική διαγραφή της Ετήσιας Έκθεσης από το αρχείο; Δεν αναιρείται.\');">' . SD_Docs::icon( 'trash' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</div>';
			}
			foreach ( $plans as $p ) {
				self::plan_row( $p, $manage );
			}
			echo '</section>';
		}
		if ( ! $any ) {
			echo '<p class="sd-notice">Το αρχείο είναι ακόμη άδειο.' . ( $manage ? ' Εισαγάγετε τα σχέδια και τις εκθέσεις των προηγούμενων ετών από την πλατφόρμα με τη φόρμα παραπάνω.' : '' ) . '</p>';
			return;
		}
		if ( $manage ) {
			self::ai_box( 'arxeio' );
		}
	}

	private static function plan_row( $p, $manage, $with_continue = false ) {
		$ev = self::get_eval( $p->id );
		echo '<div class="sd-arx-plan"><div class="sd-grow"><strong><a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'id' => $p->id ) ) ) . '">' . esc_html( trim( $p->code . ' ' . $p->title ) ) . '</a></strong>';
		echo '<div class="sd-meta">Άξονας ' . (int) $p->axis . ': ' . esc_html( SD_Eval::axis_label( $p->axis ) ) . '</div>';
		if ( ! $ev || ! SD_ArchParse::has_eval( $ev ) ) {
			echo '<div class="sd-meta">Χωρίς αποτίμηση</div>';
		} else {
			if ( $ev['degree'] ) {
				echo '<span class="sd-arx-deg is-d' . (int) $ev['degree'] . '">Επίτευξη: ' . esc_html( SD_ArchParse::DEGREES[ $ev['degree'] ] ) . '</span>';
			}
			if ( $with_continue && $ev['next_steps'] ) {
				echo '<div class="sd-meta">Προτάσεις για συνέχεια: ' . esc_html( wp_trim_words( $ev['next_steps'], 30 ) ) . '</div>';
			}
		}
		echo '</div>';
		if ( $manage && $with_continue ) {
			SD_Frontend::form_open( 'arx_continue' );
			echo '<input type="hidden" name="plan_id" value="' . (int) $p->id . '"><button class="sd-button sd-small" type="submit" onclick="return confirm(\'Να δημιουργηθεί νέο σχέδιο στο τρέχον έτος ως συνέχεια αυτού;\');">Συνέχεια σε νέο σχέδιο</button></form>';
		} elseif ( $manage ) {
			echo '<a class="sd-button sd-small sd-quiet" href="' . esc_url( self::url( array( 'apotimisi' => $p->id ) ) ) . '">Αποτίμηση</a>';
			SD_Frontend::form_open( 'arx_delete' );
			echo '<input type="hidden" name="plan_id" value="' . (int) $p->id . '"><button class="sd-button sd-small sd-quiet" type="submit" title="Διαγραφή από το αρχείο" onclick="return confirm(\'Οριστική διαγραφή του σχεδίου και της αποτίμησής του από το αρχείο; Δεν αναιρείται.\');">' . SD_Docs::icon( 'trash' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}

	private static function import_card() {
		$max = self::max_start();
		$t   = get_transient( self::tkey() );
		echo '<section class="sd-card"><h3>Εισαγωγή από την πλατφόρμα</h3>';
		echo '<p>Ανοίξτε στην πλατφόρμα του ΙΕΠ το έγγραφο που θέλετε (καλύτερα το <strong>PDF</strong> της εξαγωγής), πατήστε <kbd>Ctrl</kbd>+<kbd>A</kbd>, <kbd>Ctrl</kbd>+<kbd>C</kbd> και επικολλήστε εδώ. Η εφαρμογή αναγνωρίζει μόνη της τι είναι:</p><ul class="sd-steps">';
		echo '<li><strong>Φόρμα Αποτύπωσης Σχεδίου Δράσης</strong> (παλιά πλατφόρμα): ένα σχέδιο με σχεδιασμό, υλοποίηση, <strong>αποτίμηση</strong> και ανατροφοδότηση του Συμβούλου. Αν το σχέδιο υπάρχει ήδη, συμπληρώνεται. <strong>Αρκούν οι Φόρμες όλων των σχεδίων και η Ετήσια Έκθεση.</strong></li>';
		echo '<li><strong>Συλλογικός Προγραμματισμός</strong>: μόνο αν λείπουν Φόρμες, για τους τίτλους και τους στόχους. Το PDF του είναι πίνακας και η αντιγραφή συχνά ανακατεύει τις στήλες· ελέγξτε την προεπισκόπηση.</li>';
		echo '<li><strong>Ετήσια Έκθεση Εσωτερικής Αξιολόγησης</strong>: βαθμολογία αξόνων, θετικά σημεία, σημεία προς βελτίωση, προτάσεις επιμορφώσεων.</li>';
		echo '<li><strong>Σχέδιο Δράσης της νέας πλατφόρμας</strong> ή Word της εφαρμογής.</li></ul>';
		echo '<p class="sd-help">Το σχολικό έτος διαβάζεται από το κείμενο («Σχολικό Έτος: …»)· αν δεν υπάρχει, ισχύει αυτό που διαλέγετε. Το έτος <strong>δεν</strong> γίνεται τρέχον και δεν χρειάζεται τμήματα. Ονόματα συντονιστών δεν κρατούνται.</p>';
		SD_Frontend::form_open( 'arx_preview' );
		echo '<div class="sd-row"><label>Σχολικό έτος <select name="start_year">';
		for ( $y = $max; $y >= self::FIRST_YEAR; $y-- ) {
			$sel = is_array( $t ) ? (int) $t['start'] === $y : $y === $max;
			echo '<option value="' . (int) $y . '"' . selected( $sel, true, false ) . '>' . (int) $y . '-' . (int) ( $y + 1 ) . '</option>';
		}
		echo '</select></label><label>Κωδικός σχεδίου (προαιρετικά) <input name="code" size="5" placeholder="ΣΔ1" value="' . esc_attr( is_array( $t ) ? $t['code'] : '' ) . '"></label></div>';
		echo '<textarea name="text" rows="8" placeholder="Επικόλληση εδώ (Ctrl+V)"></textarea>';
		echo '<p><button class="sd-button" type="submit">Ανάλυση</button></p></form>';
		if ( is_array( $t ) ) {
			self::import_preview( $t );
		}
		echo '</section>';
	}

	private static function import_preview( $t ) {
		$label   = $t['start'] . '-' . ( $t['start'] + 1 );
		$year_id = self::year_id( $t['start'], false );
		$d       = $t['data'];
		echo '<div id="proepiskopisi" class="sd-arx-prev"><h3>Προεπισκόπηση</h3>';
		echo '<p><strong>' . esc_html( self::TYPES[ $t['type'] ] ) . '</strong> · σχολικό έτος <strong>' . esc_html( $label ) . '</strong>' . ( $t['found'] ? ' (από το κείμενο)' : '' ) . '</p>';
		$can = true;
		switch ( $t['type'] ) {
			case 'report':
				self::report_body( $d );
				if ( $year_id && self::get_report( $year_id ) ) {
					echo '<p class="sd-notice sd-error">Υπάρχει ήδη Έκθεση για το ' . esc_html( $label ) . ' και θα αντικατασταθεί.</p>';
				}
				$can = $d['axes'] || $d['results'];
				break;

			case 'collective':
				if ( ! $d ) {
					echo '<p class="sd-notice sd-error">Δεν βρέθηκαν σχέδια.</p>';
					$can = false;
				}
				echo '<ol>';
				foreach ( $d as $p ) {
					$dup = self::match_plan( $year_id, $p['title'] );
					echo '<li><strong>' . esc_html( '' !== $p['title'] ? $p['title'] : '(χωρίς τίτλο)' ) . '</strong> <span class="sd-meta">· ' . esc_html( SD_Eval::axis_label( $p['axis'] ) ) . ( $dup ? ' · υπάρχει ήδη, παραλείπεται' : '' ) . '</span><br><span class="sd-meta">' . esc_html( wp_trim_words( $p['goal'], 40 ) ) . '</span></li>';
				}
				echo '</ol>';
				break;

			case 'legacy':
				echo '<dl class="sd-dl"><dt>Τίτλος</dt><dd>' . esc_html( $d['title'] ? $d['title'] : '—' ) . '</dd><dt>Άξονας</dt><dd>' . esc_html( $d['axis'] ? SD_Eval::axis_label( $d['axis'] ) : '— (θα μπει ο 1ος· διορθώστε μετά)' ) . '</dd>';
				echo '<dt>Στόχος βελτίωσης</dt><dd>' . esc_html( wp_trim_words( $d['goal'], 60 ) ) . '</dd>';
				echo '<dt>Αναγκαιότητα</dt><dd>' . esc_html( wp_trim_words( $d['need'], 40 ) ) . '</dd></dl>';
				self::eval_body( $d['eval'], true );
				$match = self::match_plan( $year_id, $d['title'] );
				SD_Frontend::form_open( 'arx_apply' );
				echo '<label>Πού να μπει <select name="target"><option value="0">Νέο σχέδιο στο αρχείο</option>';
				foreach ( $year_id ? SD_Plans::plans( $year_id ) : array() as $p ) {
					echo '<option value="' . (int) $p->id . '"' . selected( $match && (int) $match->id === (int) $p->id, true, false ) . '>Συμπλήρωση του «' . esc_html( $p->title ) . '»</option>';
				}
				echo '</select></label>';
				echo '<p><button class="sd-button" type="submit">Αποθήκευση στο αρχείο</button></p></form>';
				$can = false; // η φόρμα δόθηκε ήδη
				break;

			default:
				if ( $d['warnings'] ) {
					echo '<div class="sd-issues"><ul>';
					foreach ( $d['warnings'] as $w ) {
						echo '<li class="sd-issue-warning">' . esc_html( $w ) . '</li>';
					}
					echo '</ul></div>';
				}
				SD_PlanImport::preview( $d );
				$can = (bool) $d['goals'];
		}
		if ( $can ) {
			SD_Frontend::form_open( 'arx_apply' );
			echo '<p><button class="sd-button" type="submit">Αποθήκευση στο αρχείο ' . esc_html( $label ) . '</button></p></form>';
		}
		SD_Frontend::form_open( 'arx_cancel' );
		echo '<button class="sd-button sd-small sd-quiet" type="submit">Ακύρωση</button></form></div>';
	}

	/** Σχέδιο του αρχείου: μόνο ανάγνωση, με την αποτίμησή του. */
	public static function render_plan( $plan ) {
		echo '<p class="sd-noprint"><a href="' . esc_url( self::url() ) . '">← Αρχείο προηγούμενων ετών</a></p>';
		echo '<div class="sd-arx-banner"><strong>Αρχείο σχολικού έτους ' . esc_html( self::year_label( $plan->year_id ) ) . '.</strong> Το σχέδιο εμφανίζεται μόνο για ανάγνωση.</div>';
		echo '<h2>' . esc_html( trim( $plan->code . ' ' . $plan->title ) ) . '</h2>';
		echo '<dl class="sd-dl"><dt>Άξονας</dt><dd>' . esc_html( SD_Eval::axis_label( $plan->axis ) ) . '</dd>';
		if ( '' !== trim( $plan->purpose ) ) {
			echo '<dt>Σκοπός / Στόχος βελτίωσης</dt><dd>' . nl2br( esc_html( $plan->purpose ) ) . '</dd>';
		}
		if ( '' !== trim( $plan->need ) ) {
			echo '<dt>Αρχική ανάγκη / Αναγκαιότητα</dt><dd>' . nl2br( esc_html( $plan->need ) ) . '</dd>';
		}
		echo '</dl>';
		$goals = SD_Plans::goals( $plan->id );
		$multi = count( $goals ) > 1 || ( $goals && mb_substr( $goals[0]->description, 0, 80 ) !== mb_substr( $plan->purpose, 0, 80 ) );
		foreach ( $goals as $g ) {
			echo '<section class="sd-card"><h3>Στόχος ' . (int) $g->ord . '</h3>';
			if ( $multi ) {
				echo '<p>' . nl2br( esc_html( $g->description ) ) . '</p>';
			}
			foreach ( SD_Plans::actions( $g->id ) as $a ) {
				echo '<h4>' . esc_html( SD_Eval::ACTION_KINDS[ $a->kind ] ?? $a->kind ) . '</h4><p>' . nl2br( esc_html( $a->description ) ) . '</p>';
				if ( '' !== trim( $a->criterion ) ) {
					echo '<p><strong>Κριτήριο επιτυχίας:</strong> ' . nl2br( esc_html( $a->criterion ) ) . '</p>';
				}
			}
			echo '</section>';
		}
		$ev = self::get_eval( $plan->id );
		echo '<section class="sd-card"><h3>Αποτίμηση</h3>';
		if ( $ev && SD_ArchParse::has_eval( $ev ) ) {
			self::eval_body( $ev, false );
		} else {
			echo '<p class="sd-meta">Δεν έχει καταχωριστεί αποτίμηση.</p>';
		}
		if ( SD_Plans::can_edit( $plan ) ) {
			echo '<p><a class="sd-button sd-small" href="' . esc_url( self::url( array( 'apotimisi' => $plan->id ) ) ) . '">' . ( $ev ? 'Επεξεργασία αποτίμησης' : 'Καταχώριση αποτίμησης' ) . '</a></p>';
		}
		echo '</section>';
	}

	private static function eval_body( $ev, $brief ) {
		echo '<dl class="sd-dl">';
		if ( ! empty( $ev['degree'] ) ) {
			echo '<dt>Βαθμός επίτευξης των στόχων</dt><dd><span class="sd-arx-deg is-d' . (int) $ev['degree'] . '">' . (int) $ev['degree'] . ' = ' . esc_html( SD_ArchParse::DEGREES[ $ev['degree'] ] ) . '</span></dd>';
		}
		foreach ( array( 'achieved' => 'Αποτελέσματα', 'difficulties' => 'Δυσκολίες', 'next_steps' => 'Προτάσεις για συνέχιση / νέες δράσεις' ) as $f => $l ) {
			if ( '' !== trim( (string) ( $ev[ $f ] ?? '' ) ) ) {
				echo '<dt>' . esc_html( $l ) . '</dt><dd>' . ( $brief ? esc_html( wp_trim_words( $ev[ $f ], 50 ) ) : nl2br( esc_html( $ev[ $f ] ) ) ) . '</dd>';
			}
		}
		foreach ( SD_ArchParse::SECTIONS as $k => $l ) {
			if ( '' !== trim( (string) ( $ev['sections'][ $k ] ?? '' ) ) ) {
				echo '<dt>' . esc_html( $l ) . '</dt><dd>' . ( $brief ? esc_html( wp_trim_words( $ev['sections'][ $k ], 30 ) ) : nl2br( esc_html( $ev['sections'][ $k ] ) ) ) . '</dd>';
			}
		}
		echo '</dl>';
	}

	private static function report_body( $r ) {
		if ( $r['axes'] ) {
			echo '<table class="sd-table"><thead><tr><th>Άξονας</th><th>Αξιολόγηση</th></tr></thead><tbody>';
			foreach ( $r['axes'] as $n => $a ) {
				echo '<tr><td>' . esc_html( SD_Eval::axis_label( $n ) ) . '</td><td><strong>' . ( $a[0] ? (int) $a[0] . '/4' : '—' ) . '</strong></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<dl class="sd-dl">';
		foreach ( array( 'strengths' => 'Θετικά σημεία', 'improve' => 'Σημεία προς βελτίωση' ) as $k => $l ) {
			foreach ( $r[ $k ] as $f => $txt ) {
				echo '<dt>' . esc_html( $l . ' · ' . $f ) . '</dt><dd>' . nl2br( esc_html( $txt ) ) . '</dd>';
			}
		}
		if ( $r['degree'] ) {
			echo '<dt>Βαθμός επίτευξης των στόχων</dt><dd>' . (int) $r['degree'] . ' = ' . esc_html( SD_ArchParse::DEGREES[ $r['degree'] ] ) . '</dd>';
		}
		foreach ( array( 'results' => 'Σημαντικότερα αποτελέσματα των Δράσεων', 'difficulties' => 'Δυσκολίες', 'practices' => 'Ανάδειξη πρακτικών' ) as $k => $l ) {
			if ( '' !== trim( $r[ $k ] ) ) {
				echo '<dt>' . esc_html( $l ) . '</dt><dd>' . nl2br( esc_html( $r[ $k ] ) ) . '</dd>';
			}
		}
		if ( $r['train_needs'] ) {
			echo '<dt>Προτάσεις για αναγκαίες επιμορφώσεις</dt><dd><ul>';
			foreach ( $r['train_needs'] as $x ) {
				echo '<li>' . esc_html( $x ) . '</li>';
			}
			echo '</ul></dd>';
		}
		echo '</dl>';
	}

	private static function render_report( $year_id ) {
		$r = self::get_report( $year_id );
		echo '<p class="sd-noprint"><a href="' . esc_url( self::url() ) . '">← Αρχείο προηγούμενων ετών</a></p>';
		if ( ! $r ) {
			echo '<p class="sd-notice sd-error">Δεν βρέθηκε Έκθεση.</p>';
			return;
		}
		echo '<h2>Ετήσια Έκθεση Εσωτερικής Αξιολόγησης ' . esc_html( self::year_label( $year_id ) ) . '</h2><section class="sd-card">';
		self::report_body( $r );
		echo '</section>';
	}

	private static function render_eval( $plan_id ) {
		$plan = SD_Plans::get_plan( $plan_id );
		if ( ! $plan || ! SD_Plans::can_edit( $plan ) ) {
			echo '<p class="sd-notice sd-error">Δεν έχετε δικαίωμα να αλλάξετε αυτή την αποτίμηση.</p>';
			return;
		}
		$ev = self::get_eval( $plan->id );
		echo '<p class="sd-noprint"><a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) ) ) . '">← ' . esc_html( trim( $plan->code . ' ' . $plan->title ) ) . '</a> · <a href="' . esc_url( self::url() ) . '">Αρχείο</a></p>';
		echo '<h2>Αποτίμηση: ' . esc_html( $plan->title ) . ' <span class="sd-meta">' . esc_html( self::year_label( $plan->year_id ) ) . '</span></h2>';

		echo '<section class="sd-card"><h3>Από την πλατφόρμα</h3><p class="sd-help">Ανοίξτε την αποτίμηση του σχεδίου στην πλατφόρμα (ή το PDF της), πατήστε <kbd>Ctrl</kbd>+<kbd>A</kbd>, <kbd>Ctrl</kbd>+<kbd>C</kbd> και επικολλήστε εδώ. Η εφαρμογή συμπληρώνει όσα αναγνωρίσει (βαθμός επίτευξης, αποτελέσματα, δυσκολίες, προτάσεις)· τα υπόλοιπα τα γράφετε παρακάτω.</p>';
		SD_Frontend::form_open( 'arx_eval_parse' );
		echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '"><textarea name="text" rows="6" placeholder="Επικόλληση εδώ (Ctrl+V)"></textarea>';
		echo '<p><button class="sd-button sd-small" type="submit">Συμπλήρωση από το κείμενο</button></p></form></section>';

		SD_Frontend::form_open( 'arx_eval_save' );
		echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '"><section class="sd-card">';
		echo '<label>Βαθμός επίτευξης των στόχων <select name="degree"><option value="0">—</option>';
		foreach ( SD_ArchParse::DEGREES as $k => $l ) {
			echo '<option value="' . (int) $k . '"' . selected( (int) ( $ev['degree'] ?? 0 ), $k, false ) . '>' . (int) $k . ' = ' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>Αποτελέσματα <textarea name="achieved" rows="5">' . esc_textarea( $ev['achieved'] ?? '' ) . '</textarea></label>';
		echo '<label>Δυσκολίες <textarea name="difficulties" rows="3">' . esc_textarea( $ev['difficulties'] ?? '' ) . '</textarea></label>';
		echo '<label>Προτάσεις για συνέχιση / νέες δράσεις <textarea name="next_steps" rows="4">' . esc_textarea( $ev['next_steps'] ?? '' ) . '</textarea></label>';
		echo '<p><button class="sd-button" type="submit">Αποθήκευση αποτίμησης</button></p></section></form>';
		if ( ! empty( $ev['sections'] ) ) {
			echo '<section class="sd-card"><h3>Άλλες ενότητες από την πλατφόρμα</h3>';
			self::eval_body( array( 'sections' => $ev['sections'] ), false );
			echo '</section>';
		}
	}

	/** Στη λίστα του τρέχοντος έτους: τα σχέδια της προηγούμενης χρονιάς, για συνέχεια. */
	public static function continue_card( $year ) {
		$prev = null;
		foreach ( self::past_years() as $y ) {
			if ( SD_Plans::plans( $y->id ) ) {
				$prev = $y;
				break;
			}
		}
		$manage = current_user_can( SD_Roles::CAP_MANAGE );
		if ( ! $prev ) {
			if ( $manage ) {
				echo '<section class="sd-card"><h3>Σχέδια προηγούμενων ετών</h3><p>Περάστε στο <a href="' . esc_url( self::url() ) . '">Αρχείο</a> τα σχέδια, τις αποτιμήσεις και τις Ετήσιες Εκθέσεις των προηγούμενων ετών από την πλατφόρμα. Μετά θα μπορείτε να συνεχίσετε ένα σχέδιο και να πάρετε ενδεικτικές προτάσεις.</p></section>';
			}
			return;
		}
		echo '<details class="sd-card sd-arx-cont"' . ( SD_Plans::plans( $year->id ) ? '' : ' open' ) . '><summary><strong>Από το αρχείο: ' . esc_html( $prev->label ) . '</strong> <span class="sd-meta">συνέχεια σχεδίου, προτάσεις</span></summary>';
		foreach ( SD_Plans::plans( $prev->id ) as $p ) {
			self::plan_row( $p, $manage, true );
		}
		echo '<p><a href="' . esc_url( self::url() ) . '">Όλο το αρχείο →</a></p>';
		if ( $manage ) {
			self::ai_box( 'list' );
		}
		echo '</details>';
	}
}
