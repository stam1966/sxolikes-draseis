<?php
/**
 * Σχέδιο Δράσης με τη μορφή της πλατφόρμας του ΙΕΠ, για να μη χρειάζεται
 * ο/η συνάδελφος να μάθει δύο διαφορετικές οθόνες:
 * «Επιλογή Σχεδίου» · «Επεξεργασία φόρμας» (στόχοι σε καρτέλες, ενέργειες με
 * μήνα έναρξης/λήξης, εργαλεία/τρόπος εργασίας) · «Επισκόπηση και Υποβολή»
 * (σύνοψη στόχων, χρονοδιάγραμμα, έλεγχος πριν από την καταχώριση).
 * Δεξιά, στήλη βοήθειας όπως ο «Έξυπνος Βοηθός» της πλατφόρμας.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_PlanUI {

	const OPT_DEADLINE = 'sd_deadline_plans';
	const MAX_ACTIONS  = 3;

	const MONTHS = array( 1 => 'Ιανουάριος', 'Φεβρουάριος', 'Μάρτιος', 'Απρίλιος', 'Μάιος', 'Ιούνιος', 'Ιούλιος', 'Αύγουστος', 'Σεπτέμβριος', 'Οκτώβριος', 'Νοέμβριος', 'Δεκέμβριος' );
	const SHORT  = array( 1 => 'Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μάι', 'Ιούν', 'Ιούλ', 'Αύγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ' );

	public static function deadline() {
		return (string) get_option( self::OPT_DEADLINE, '' );
	}

	private static function t( $n ) {
		return SD_Data::table( $n );
	}

	/** Πρώτο έτος της σχολικής χρονιάς του σχεδίου (π.χ. 2026 για 2026-2027). */
	private static function start_year( $plan ) {
		foreach ( SD_Data::years() as $y ) {
			if ( (int) $y->id === (int) $plan->year_id && preg_match( '/(\d{4})/', $y->label, $m ) ) {
				return (int) $m[1];
			}
		}
		return (int) current_time( 'Y' ) - ( (int) current_time( 'n' ) < 9 ? 1 : 0 );
	}

	/** Οι μήνες της χρονιάς: ['2026-09' => 'Σεπτέμβριος 2026', …, '2027-06' => …]. */
	private static function months( $plan ) {
		$y   = self::start_year( $plan );
		$out = array();
		foreach ( array( 9, 10, 11, 12, 1, 2, 3, 4, 5, 6 ) as $m ) {
			$yy                                  = $m >= 9 ? $y : $y + 1;
			$out[ sprintf( '%04d-%02d', $yy, $m ) ] = self::MONTHS[ $m ] . ' ' . $yy;
		}
		return $out;
	}

	private static function ym( $date ) {
		return $date ? substr( (string) $date, 0, 7 ) : '';
	}

	/** Ετικέτες ενεργειών: «Ενέργεια Υλοποίησης 1», «Ενέργεια Αποτίμησης». */
	private static function action_labels( $actions ) {
		$n   = 0;
		$out = array();
		foreach ( $actions as $i => $a ) {
			if ( 'apotimisi' === $a->kind ) {
				$out[ $i ] = 'Ενέργεια Αποτίμησης';
			} else {
				$n++;
				$out[ $i ] = 'Ενέργεια Υλοποίησης ' . $n;
			}
		}
		return $out;
	}

	private static function team( $plan ) {
		$names = array();
		foreach ( SD_Plans::members( $plan->id ) as $m ) {
			$u = get_userdata( $m );
			if ( $u && (int) $m !== (int) $plan->coordinator_id ) {
				$names[] = $u->display_name;
			}
		}
		$names = array_merge( $names, SD_Staff::plan_extra( $plan->id ) );
		$c     = $plan->coordinator_id ? get_userdata( $plan->coordinator_id ) : null;
		return array( $c ? $c->display_name : '', $names );
	}

	/* =================== Αποθήκευση στόχου (όλα μαζί, όπως στην πλατφόρμα) =================== */

	private static function text( $v, $limit, $label, $multi = true ) {
		$v = $multi ? sanitize_textarea_field( (string) $v ) : sanitize_text_field( (string) $v );
		if ( SD_Eval::length( $v ) > $limit ) {
			return new WP_Error( 'sd', $label . ': έως ' . $limit . ' χαρακτήρες (τώρα ' . SD_Eval::length( $v ) . ').' );
		}
		return $v;
	}

	private static function keys( $arr, array $allowed ) {
		$arr = is_array( $arr ) ? array_map( 'sanitize_key', $arr ) : array();
		return implode( ',', array_values( array_intersect( array_keys( $allowed ), $arr ) ) );
	}

	public static function handle( $do ) {
		global $wpdb;
		if ( 'save_goal_full' !== $do ) {
			return null;
		}
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security
		$goal = ! empty( $post['goal_id'] ) ? SD_Plans::get_goal( absint( $post['goal_id'] ) ) : null;
		$plan = SD_Plans::get_plan( $goal ? $goal->plan_id : absint( $post['plan_id'] ?? 0 ) );
		$list = SD_Frontend::url( 'sxedia' );
		if ( ! $plan ) {
			return array( new WP_Error( 'sd', 'Το σχέδιο δεν βρέθηκε.' ), $list );
		}
		$back = SD_Frontend::url( 'sxedia', array( 'id' => $plan->id, 'tab' => 'forma', 'stoxos' => $goal ? $goal->id : 'neos' ) );
		if ( ! SD_Plans::can_edit( $plan ) ) {
			return array( new WP_Error( 'sd', 'Μόνο ο/η Συντονιστής/τρια και η Διεύθυνση επεξεργάζονται το σχέδιο.' ), $back );
		}
		if ( ! $goal && count( SD_Plans::goals( $plan->id ) ) >= SD_Eval::MAX_GOALS ) {
			return array( new WP_Error( 'sd', 'Η φόρμα δέχεται έως ' . SD_Eval::MAX_GOALS . ' στόχους.' ), $back );
		}

		$desc  = self::text( $post['description'] ?? '', SD_Eval::LIMIT, 'Περιγραφή Στόχου' );
		$title = self::text( $post['title'] ?? '', 255, 'Σύντομος τίτλος', false );
		$t_oth = self::text( $post['tools_other'] ?? '', SD_Eval::OTHER_LIMIT, 'Άλλο εργαλείο', false );
		$w_oth = self::text( $post['ways_other'] ?? '', SD_Eval::OTHER_LIMIT, 'Άλλος τρόπος εργασίας', false );
		foreach ( array( $desc, $title, $t_oth, $w_oth ) as $v ) {
			if ( is_wp_error( $v ) ) {
				return array( $v, $back );
			}
		}
		if ( '' === trim( $desc ) ) {
			return array( new WP_Error( 'sd', 'Γράψτε την Περιγραφή Στόχου.' ), $back );
		}

		// Ενέργειες: έλεγχος πριν γραφτεί οτιδήποτε.
		$months = self::months( $plan );
		$rows   = array();
		$own    = $goal ? wp_list_pluck( SD_Plans::actions( $goal->id ), 'id' ) : array();
		$own    = array_map( 'intval', $own );
		$keep   = 0;
		foreach ( (array) ( $post['act'] ?? array() ) as $i => $a ) {
			if ( ! is_array( $a ) ) {
				continue;
			}
			$id   = absint( $a['id'] ?? 0 );
			$id   = in_array( $id, $own, true ) ? $id : 0;
			$del  = ! empty( $a['del'] );
			$text = self::text( $a['desc'] ?? '', SD_Eval::LIMIT, 'Περιγραφή Ενέργειας' );
			$crit = self::text( $a['crit'] ?? '', SD_Eval::LIMIT, 'Κριτήριο Επιτυχίας' );
			if ( is_wp_error( $text ) || is_wp_error( $crit ) ) {
				return array( is_wp_error( $text ) ? $text : $crit, $back );
			}
			if ( ! $id && '' === trim( $text ) ) {
				continue; // κενή θέση
			}
			$from = isset( $months[ $a['from'] ?? '' ] ) ? $a['from'] : '';
			$to   = isset( $months[ $a['to'] ?? '' ] ) ? $a['to'] : '';
			if ( $from && $to && $to < $from ) {
				return array( new WP_Error( 'sd', 'Ο Μήνας Λήξης είναι πριν από τον Μήνα Έναρξης.' ), $back );
			}
			$kind   = isset( SD_Eval::ACTION_KINDS[ $a['kind'] ?? '' ] ) ? $a['kind'] : 'ylopoiisi';
			$grades = isset( $a['grades'] ) && is_array( $a['grades'] ) ? array_values( array_intersect( array_keys( SD_Data::GRADES ), array_map( 'absint', $a['grades'] ) ) ) : array();
			$keep  += $del ? 0 : 1;
			$rows[] = array(
				'id'   => $id,
				'del'  => $del,
				'data' => array(
					'kind'        => $kind,
					'description' => $text,
					'start_date'  => $from ? $from . '-01' : null,
					'end_date'    => $to ? gmdate( 'Y-m-t', strtotime( $to . '-01' ) ) : null,
					'criterion'   => 'apotimisi' === $kind ? $crit : '',
					'grades'      => implode( ',', $grades ),
				),
			);
		}
		if ( $keep > self::MAX_ACTIONS ) {
			return array( new WP_Error( 'sd', 'Κάθε στόχος έχει έως ' . self::MAX_ACTIONS . ' ενέργειες.' ), $back );
		}

		$gdata = array(
			'title'       => $title,
			'description' => $desc,
			'tools'       => self::keys( $post['tools'] ?? array(), SD_Eval::TOOLS ),
			'tools_other' => $t_oth,
			'ways'        => self::keys( $post['ways'] ?? array(), SD_Eval::WAYS ),
			'ways_other'  => $w_oth,
		);
		if ( $goal ) {
			$wpdb->update( self::t( 'goals' ), $gdata, array( 'id' => $goal->id ) );
			$gid = (int) $goal->id;
		} else {
			$gdata['plan_id'] = $plan->id;
			$gdata['ord']     = count( SD_Plans::goals( $plan->id ) ) + 1;
			$wpdb->insert( self::t( 'goals' ), $gdata );
			$gid = (int) $wpdb->insert_id;
		}
		$ord = 0;
		foreach ( $rows as $r ) {
			if ( $r['del'] ) {
				if ( $r['id'] ) {
					$wpdb->delete( self::t( 'goal_actions' ), array( 'id' => $r['id'] ) );
				}
				continue;
			}
			$ord++;
			$d        = $r['data'];
			$d['ord'] = $ord;
			if ( $r['id'] ) {
				$wpdb->update( self::t( 'goal_actions' ), $d, array( 'id' => $r['id'] ) );
			} else {
				$d['goal_id'] = $gid;
				$wpdb->insert( self::t( 'goal_actions' ), $d );
			}
		}
		$wpdb->update( self::t( 'plans' ), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $plan->id ) );
		set_transient( 'sd_msg_' . get_current_user_id(), array( 'ok', 'Ο στόχος αποθηκεύτηκε. Η αποθήκευση δεν ισοδυναμεί με καταχώριση στην πλατφόρμα του ΙΕΠ.' ), 60 );
		return array( true, SD_Frontend::url( 'sxedia', array( 'id' => $plan->id, 'tab' => 'forma', 'stoxos' => $gid ) ) );
	}

	/* =================== Οθόνη =================== */

	public static function render( $plan_id ) {
		$plan = SD_Plans::get_plan( $plan_id );
		if ( ! $plan ) {
			echo '<p class="sd-notice sd-error">Το σχέδιο δεν βρέθηκε.</p>';
			return;
		}
		$edit = SD_Plans::can_edit( $plan );
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$tab  = in_array( $tab, array( 'forma', 'episkopisi' ), true ) ? $tab : ( $edit ? 'forma' : 'episkopisi' );
		if ( $edit && 'forma' === $tab ) {
			SD_AiField::mark( 'plan', $plan->id );
		}

		$url = function ( $args ) use ( $plan ) {
			return SD_Frontend::url( 'sxedia', array_merge( array( 'id' => $plan->id ), $args ) );
		};
		echo '<nav class="sd-pt-tabs" aria-label="Σχέδιο Δράσης"><a href="' . esc_url( SD_Frontend::url( 'sxedia' ) ) . '">Επιλογή Σχεδίου</a>';
		echo '<a href="' . esc_url( $url( array( 'tab' => 'forma' ) ) ) . '"' . ( 'forma' === $tab ? ' aria-current="page"' : '' ) . '>' . ( $edit ? 'Επεξεργασία φόρμας' : 'Φόρμα' ) . '</a>';
		echo '<a href="' . esc_url( $url( array( 'tab' => 'episkopisi' ) ) ) . '"' . ( 'episkopisi' === $tab ? ' aria-current="page"' : '' ) . '>Επισκόπηση και Υποβολή</a></nav>';

		echo '<div class="sd-pt">';
		echo '<div class="sd-pt-main">';
		if ( 'episkopisi' === $tab ) {
			self::overview( $plan );
		} else {
			self::form( $plan, $edit );
		}
		echo '</div><aside class="sd-pt-side" aria-label="Βοηθός">';
		self::side( $plan, $tab, $edit );
		echo '</aside></div>';
	}

	/** Η γκρι κάρτα με τα στοιχεία του σχεδίου, όπως στην πλατφόρμα. */
	private static function head( $plan, $full ) {
		list( $coord, $members ) = self::team( $plan );
		$team                    = ( $coord ? '1 συντονιστής' : 'χωρίς συντονιστή' ) . ' + ' . count( $members ) . ( 1 === count( $members ) ? ' μέλος' : ' μέλη' );
		$issues                  = SD_Plans::issues( $plan );
		$errors                  = count( array_filter( $issues, function ( $i ) {
			return 'error' === $i[0];
		} ) );
		echo '<section class="sd-pt-head"><div class="sd-pt-head-top"><span class="sd-pt-kicker">Σχέδιο Δράσης</span><h2>' . esc_html( trim( $plan->code . ' ' . $plan->title ) ) . '</h2>';
		echo '<span class="sd-pt-status' . ( $errors ? '' : ' is-ok' ) . '">' . ( $errors ? 'Σε επεξεργασία' : 'Έτοιμο για την πλατφόρμα' ) . '</span></div>';
		echo '<dl class="sd-pt-grid"><div><dt>Λειτουργία</dt><dd>' . esc_html( SD_Eval::function_label( $plan->axis ) ) . '</dd></div><div><dt>Άξονας</dt><dd>' . esc_html( SD_Eval::axis_label( $plan->axis ) ) . '</dd></div><div><dt>Συντονιστής / Μέλη Ομάδας</dt><dd title="' . esc_attr( trim( $coord . ( $members ? ' · ' . implode( ', ', $members ) : '' ), ' ·' ) ) . '">' . esc_html( $team ) . '</dd></div></dl>';
		if ( $full ) {
			echo '<dl class="sd-pt-long"><dt>Σκοπός / Βασική κατεύθυνση</dt><dd>' . nl2br( esc_html( $plan->purpose ? $plan->purpose : '—' ) ) . '</dd><dt>Αρχική Ανάγκη</dt><dd>' . nl2br( esc_html( $plan->need ? $plan->need : '—' ) ) . '</dd></dl>';
		}
		echo '</section>';
	}

	private static function month_select( $name, $value, $months, $dis ) {
		$h = '<select name="' . esc_attr( $name ) . '"' . $dis . '><option value="">—</option>';
		foreach ( $months as $k => $l ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $h . '</select>';
	}

	/** Επιλογές σε δύο στήλες, αλφαβητικά, όπως στην πλατφόρμα. */
	private static function check_grid( $name, array $options, $csv, $dis ) {
		$sel = '' === (string) $csv ? array() : explode( ',', $csv );
		uasort(
			$options,
			function ( $a, $b ) {
				return strcmp( remove_accents( mb_strtolower( $a ) ), remove_accents( mb_strtolower( $b ) ) );
			}
		);
		$h = '<div class="sd-pt-checks">';
		foreach ( $options as $k => $l ) {
			$h .= '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $k ) . '"' . ( in_array( (string) $k, $sel, true ) ? ' checked' : '' ) . $dis . '> ' . esc_html( $l ) . '</label>';
		}
		return $h . '</div>';
	}

	private static function action_card( $i, $a, $label, $months, $dis, $hidden = false, $kind = 'ylopoiisi' ) {
		$k    = $a ? $a->kind : $kind;
		$sel  = $a && '' !== $a->grades ? explode( ',', $a->grades ) : array();
		$base = 'act[' . (int) $i . ']';
		$h    = '<div class="sd-pt-act sd-pt-act-' . esc_attr( $k ) . '" data-act' . ( $hidden ? ' hidden data-empty' : '' ) . '>';
		$h   .= '<div class="sd-pt-act-top"><span class="sd-pt-kind" data-kind-label>' . esc_html( $label ) . '</span>';
		$h   .= '<label class="sd-pt-kind-pick">Είδος <select name="' . esc_attr( $base ) . '[kind]" data-kind' . $dis . '>';
		foreach ( SD_Eval::ACTION_KINDS as $kk => $kl ) {
			$h .= '<option value="' . esc_attr( $kk ) . '"' . selected( $k, $kk, false ) . '>' . esc_html( $kl ) . '</option>';
		}
		$h .= '</select></label>';
		if ( ! $dis ) {
			$h .= '<label class="sd-pt-del" title="Διαγραφή ενέργειας"><input type="checkbox" name="' . esc_attr( $base ) . '[del]" value="1" data-del><span>' . SD_Docs::icon( 'trash' ) . 'Διαγραφή</span></label>';
		}
		$h .= '</div>';
		$h .= '<input type="hidden" name="' . esc_attr( $base ) . '[id]" value="' . (int) ( $a ? $a->id : 0 ) . '">';
		$h .= '<label class="sd-pt-field" data-help="Τι ακριβώς θα γίνει, από ποιους/ποιες και πού. Μία ενέργεια = μία συγκεκριμένη δράση.">Περιγραφή Ενέργειας <small>(έως ' . SD_Eval::LIMIT . ' χαρ.)</small><textarea name="' . esc_attr( $base ) . '[desc]" rows="3" data-limit="' . SD_Eval::LIMIT . '"' . $dis . '>' . esc_textarea( $a ? $a->description : '' ) . '</textarea></label>';
		$h .= '<div class="sd-pt-months"><label data-help="Ο μήνας που ξεκινά η ενέργεια.">Μήνας Έναρξης ' . self::month_select( $base . '[from]', $a ? self::ym( $a->start_date ) : '', $months, $dis ) . '</label>';
		$h .= '<label data-help="Ο μήνας που ολοκληρώνεται η ενέργεια.">Μήνας Λήξης ' . self::month_select( $base . '[to]', $a ? self::ym( $a->end_date ) : '', $months, $dis ) . '</label></div>';
		$h .= '<label class="sd-pt-field sd-pt-crit" data-help="Από ποια στοιχεία, ενδείξεις ή αλλαγές θα διαπιστώσετε ότι ο στόχος επιτεύχθηκε; Τι θα παρατηρήσετε ή θα μετρήσετε;">Κριτήριο Επιτυχίας <small>Από ποια στοιχεία θα διαπιστώσετε ότι ο στόχος επιτεύχθηκε; (έως ' . SD_Eval::LIMIT . ' χαρ.)</small><textarea name="' . esc_attr( $base ) . '[crit]" rows="3" data-limit="' . SD_Eval::LIMIT . '"' . $dis . '>' . esc_textarea( $a ? $a->criterion : '' ) . '</textarea></label>';
		$h .= '<details class="sd-pt-grades"><summary>Ποιες τάξεις αφορά <small>(για την εφαρμογή· κενό = όλες)</small></summary><div class="sd-chips">';
		foreach ( SD_Data::GRADES as $n => $l ) {
			$h .= '<label class="sd-chip"><input type="checkbox" name="' . esc_attr( $base ) . '[grades][]" value="' . (int) $n . '"' . ( in_array( (string) $n, $sel, true ) ? ' checked' : '' ) . $dis . '><span>' . esc_html( $l ) . '</span></label>';
		}
		return $h . '</div></details></div>';
	}

	private static function form( $plan, $edit ) {
		$dis   = $edit ? '' : ' disabled';
		$goals = SD_Plans::goals( $plan->id );
		self::head( $plan, true );

		// Στοιχεία σχεδίου και ομάδα: ανοίγουν μόνο όταν χρειαστεί.
		if ( $edit || current_user_can( SD_Roles::CAP_MANAGE ) ) {
			echo '<details class="sd-pt-more"><summary>Στοιχεία σχεδίου (τίτλος, άξονας, σκοπός, αρχική ανάγκη, πηγές)</summary>';
			if ( $edit ) {
				SD_Plans::render_info_form( $plan );
			}
			echo '</details>';
			echo '<details class="sd-pt-more" id="omada"><summary>Ομάδα Δράσης</summary>';
			SD_Plans::render_team( $plan );
			echo '</details>';
		}

		$cur = isset( $_GET['stoxos'] ) ? sanitize_key( wp_unslash( $_GET['stoxos'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$sel = null;
		foreach ( $goals as $g ) {
			if ( (string) $g->id === $cur ) {
				$sel = $g;
			}
		}
		if ( ! $sel && 'neos' !== $cur && $goals ) {
			$sel = $goals[0];
		}
		$new = ! $sel;
		if ( $new && ! $edit ) {
			echo '<p>Δεν έχουν καταχωριστεί στόχοι.</p>';
			return;
		}

		echo '<p class="sd-pt-goals-h">' . SD_Docs::icon( 'eye' ) . 'Στόχοι (' . count( $goals ) . '/' . SD_Eval::MAX_GOALS . ')</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<nav class="sd-pt-gtabs">';
		foreach ( $goals as $i => $g ) {
			echo '<a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'id' => $plan->id, 'tab' => 'forma', 'stoxos' => $g->id ) ) ) . '"' . ( $sel && (int) $sel->id === (int) $g->id ? ' aria-current="page"' : '' ) . '>Στόχος ' . (int) ( $i + 1 ) . '</a>';
		}
		if ( $edit && count( $goals ) < SD_Eval::MAX_GOALS ) {
			echo '<a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'id' => $plan->id, 'tab' => 'forma', 'stoxos' => 'neos' ) ) ) . '"' . ( $new ? ' aria-current="page"' : '' ) . '>+ Νέος στόχος</a>';
		}
		echo '</nav>';

		$n       = $new ? count( $goals ) + 1 : array_search( $sel, $goals, true ) + 1;
		$actions = $new ? array() : SD_Plans::actions( $sel->id );
		$labels  = self::action_labels( $actions );
		$months  = self::months( $plan );

		echo '<section class="sd-pt-goal">';
		if ( $edit && ! $new ) {
			echo '<div class="sd-pt-goal-top"><strong>Στόχος ' . (int) $n . ' / ' . (int) max( count( $goals ), $n ) . '</strong>';
			SD_Frontend::form_open( 'delete_goal', ' class="sd-pt-inline"' );
			echo '<input type="hidden" name="goal_id" value="' . (int) $sel->id . '"><button class="sd-pt-danger" type="submit" onclick="return confirm(\'Διαγραφή του στόχου και των ενεργειών του;\');">' . SD_Docs::icon( 'trash' ) . 'Διαγραφή</button></form></div>';
		} else {
			echo '<div class="sd-pt-goal-top"><strong>' . ( $new ? 'Νέος στόχος (' . (int) $n . ' / ' . SD_Eval::MAX_GOALS . ')' : 'Στόχος ' . (int) $n ) . '</strong></div>';
		}

		SD_Frontend::form_open( 'save_goal_full', ' data-pt-goal' );
		echo $new ? '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '">' : '<input type="hidden" name="goal_id" value="' . (int) $sel->id . '">';
		echo '<label class="sd-pt-field" data-help="Τι θέλετε να αλλάξει. Ένας στόχος, διατυπωμένος ώστε να φαίνεται στο τέλος αν πέτυχε.">Περιγραφή Στόχου <small>(έως ' . SD_Eval::LIMIT . ' χαρ.)</small><textarea name="description" rows="4" data-limit="' . SD_Eval::LIMIT . '"' . $dis . '>' . esc_textarea( $new ? '' : $sel->description ) . '</textarea></label>';

		echo '<div class="sd-pt-itabs" role="tablist"><button type="button" role="tab" aria-selected="true" data-itab="energeies">Ενέργειες</button><button type="button" role="tab" aria-selected="false" data-itab="ergaleia">Εργαλεία / Τρόπος Εργασίας</button></div>';

		// Ενέργειες
		echo '<div class="sd-pt-ipanel" data-ipanel="energeies">';
		echo '<div class="sd-pt-acts-top">' . ( $edit ? '<button type="button" class="sd-button sd-small" data-add-act>+ Προσθήκη Ενέργειας</button>' : '<span></span>' ) . '<span class="sd-meta"><span data-act-count>' . count( $actions ) . '</span>/' . self::MAX_ACTIONS . ' ενέργειες</span></div>';
		$i = 0;
		foreach ( $actions as $idx => $a ) {
			echo self::action_card( $i++, $a, $labels[ $idx ], $months, $dis ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( ! $actions && ! $edit ) {
			echo '<p class="sd-meta">— Καμία ενέργεια —</p>';
		}
		if ( $edit ) {
			$has_eval = false;
			$ylop     = 0;
			foreach ( $actions as $a ) {
				$has_eval = $has_eval || 'apotimisi' === $a->kind;
				$ylop    += 'ylopoiisi' === $a->kind ? 1 : 0;
			}
			for ( $s = count( $actions ); $s < self::MAX_ACTIONS; $s++ ) {
				// Προτεινόμενο είδος: πρώτα υλοποίηση, η τελευταία θέση αποτίμηση.
				$kind = ( ! $has_eval && ( $ylop >= 1 || self::MAX_ACTIONS - 1 === $s ) ) ? 'apotimisi' : 'ylopoiisi';
				if ( 'apotimisi' === $kind ) {
					$has_eval = true;
				} else {
					$ylop++;
				}
				echo self::action_card( $i++, null, 'apotimisi' === $kind ? 'Ενέργεια Αποτίμησης' : 'Ενέργεια Υλοποίησης ' . $ylop, $months, $dis, true, $kind ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo '</div>';

		// Εργαλεία / Τρόπος Εργασίας
		echo '<div class="sd-pt-ipanel" data-ipanel="ergaleia">';
		echo '<fieldset class="sd-pt-box" data-help="Τα μέσα που θα χρησιμοποιήσετε για να υλοποιήσετε τις ενέργειες."><legend><strong>Εργαλεία</strong> <small>(με τι θα δουλέψετε) Επιλέξτε τα μέσα που θα αξιοποιήσετε για να υλοποιήσετε τις ενέργειες.</small></legend>' . self::check_grid( 'tools', SD_Eval::TOOLS, $new ? '' : $sel->tools, $dis ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-pt-field">Άλλο <small>(έως ' . SD_Eval::OTHER_LIMIT . ' χαρ.)</small><input name="tools_other" maxlength="' . SD_Eval::OTHER_LIMIT . '" placeholder="Προσθέστε άλλο εργαλείο…" value="' . esc_attr( $new ? '' : $sel->tools_other ) . '"' . $dis . '></label></fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<fieldset class="sd-pt-box" data-help="Ποιοι θα εμπλακούν και πώς θα δουλέψουν."><legend><strong>Τρόπος Εργασίας</strong> <small>(ποιοι και πώς θα δουλέψετε) Επιλέξτε ποιοι θα εμπλακούν και τι είδους υποστήριξη θα αξιοποιηθεί.</small></legend>' . self::check_grid( 'ways', SD_Eval::WAYS, $new ? '' : $sel->ways, $dis ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-pt-field">Άλλο <small>(έως ' . SD_Eval::OTHER_LIMIT . ' χαρ.)</small><input name="ways_other" maxlength="' . SD_Eval::OTHER_LIMIT . '" placeholder="Προσθέστε άλλον τρόπο…" value="' . esc_attr( $new ? '' : $sel->ways_other ) . '"' . $dis . '></label></fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="sd-pt-field sd-pt-short">Σύντομος τίτλος στόχου για την εφαρμογή <small>(προαιρετικό· δεν υπάρχει στην πλατφόρμα)</small><input name="title" value="' . esc_attr( $new ? '' : $sel->title ) . '"' . $dis . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		if ( $edit ) {
			echo '<div class="sd-pt-save"><button class="sd-button" type="submit">' . SD_Docs::icon( 'word' ) . 'Αποθήκευση Στόχου</button> <a class="sd-pt-cancel" href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'id' => $plan->id, 'tab' => 'forma', 'stoxos' => $new ? 'neos' : $sel->id ) ) ) . '">Ακύρωση</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<p class="sd-help">* Η αποθήκευση δεν ισοδυναμεί με καταχώριση. Τα κείμενα περνούν στην πλατφόρμα του ΙΕΠ από την καρτέλα «Επισκόπηση και Υποβολή».</p>';
		}
		echo '</form></section>';

		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			SD_Frontend::form_open( 'delete_plan' );
			echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '"><p class="sd-pt-planedel"><button class="sd-button sd-danger sd-small" type="submit" onclick="return confirm(\'Διαγραφή ολόκληρου του σχεδίου με τους στόχους και τις ενέργειές του;\');">Διαγραφή σχεδίου</button></p></form>';
		}
	}

	/* =================== Επισκόπηση =================== */

	private static function overview( $plan ) {
		$goals = SD_Plans::goals( $plan->id );
		echo '<p class="sd-pt-state">Κατάσταση σχεδίου: ';
		$errors = array_filter( SD_Plans::issues( $plan ), function ( $i ) {
			return 'error' === $i[0];
		} );
		echo '<span class="sd-pt-status' . ( $errors ? '' : ' is-ok' ) . '">' . ( $errors ? 'Σε επεξεργασία' : 'Έτοιμο για την πλατφόρμα' ) . '</span></p>';

		echo '<div class="sd-pt-sum">';
		self::head( $plan, false );
		foreach ( $goals as $gi => $g ) {
			$acts = SD_Plans::actions( $g->id );
			$y    = 0;
			$e    = 0;
			foreach ( $acts as $a ) {
				$y += 'ylopoiisi' === $a->kind ? 1 : 0;
				$e += 'apotimisi' === $a->kind ? 1 : 0;
			}
			$labels = self::action_labels( $acts );
			echo '<details class="sd-pt-grow"><summary><span class="sd-pt-pill">Στόχος ' . (int) ( $gi + 1 ) . '</span><span class="sd-pt-gtext">' . esc_html( ( $g->title ? $g->title . ' · ' : '' ) . $g->description ) . '</span><span class="sd-pt-chip">' . count( $acts ) . ' ενέργειες</span><span class="sd-pt-chip">' . (int) $y . ' Υλοποίηση</span><span class="sd-pt-chip is-eval">' . (int) $e . ' Αποτίμηση</span></summary>';
			echo '<div class="sd-pt-gbody"><p>' . nl2br( esc_html( $g->description ) ) . '</p><ol>';
			foreach ( $acts as $ai => $a ) {
				$from = self::ym( $a->start_date );
				$to   = self::ym( $a->end_date );
				echo '<li><strong>' . esc_html( $labels[ $ai ] ) . '</strong> <span class="sd-meta">' . esc_html( ( $from ? self::month_name( $from ) : '—' ) . ' – ' . ( $to ? self::month_name( $to ) : '—' ) ) . '</span><br>' . nl2br( esc_html( $a->description ) ) . ( '' !== trim( $a->criterion ) ? '<br><em>Κριτήριο Επιτυχίας:</em> ' . nl2br( esc_html( $a->criterion ) ) : '' ) . '</li>';
			}
			echo '</ol></div></details>';
		}
		if ( ! $goals ) {
			echo '<p class="sd-meta" style="padding:1rem">Δεν έχουν καταχωριστεί στόχοι.</p>';
		}
		echo '</div>';
		self::gantt( $plan, $goals );
	}

	private static function month_name( $ym ) {
		$m = (int) substr( $ym, 5, 2 );
		return ( self::MONTHS[ $m ] ?? '' ) . ' ' . substr( $ym, 0, 4 );
	}

	/** Χρονοδιάγραμμα: γραμμή ανά ενέργεια, στήλη ανά μήνα (Οκτ–Μάι, ή ό,τι καλύπτουν οι ενέργειες). */
	private static function gantt( $plan, $goals ) {
		$all  = array_keys( self::months( $plan ) );
		$rows = array();
		$used = array();
		foreach ( $goals as $gi => $g ) {
			foreach ( SD_Plans::actions( $g->id ) as $ai => $a ) {
				$f      = self::ym( $a->start_date );
				$t      = self::ym( $a->end_date );
				$rows[] = array( 'Σ' . ( $gi + 1 ) . '.' . ( $ai + 1 ), $a, $f, $t ? $t : $f );
				if ( $f ) {
					$used[] = $f;
					$used[] = $t ? $t : $f;
				}
			}
		}
		$first = array_search( '10', array_map( function ( $k ) {
			return substr( $k, 5, 2 );
		}, $all ), true );
		$cols  = array_slice( $all, 1, 8 ); // Οκτ–Μάι
		if ( $used ) {
			$lo   = min( $used );
			$hi   = max( $used );
			$cols = array_values( array_filter( $all, function ( $k ) use ( $lo, $hi, $cols ) {
				return in_array( $k, $cols, true ) || ( $k >= $lo && $k <= $hi );
			} ) );
		}
		unset( $first );
		echo '<section class="sd-pt-gantt"><h3>' . SD_Docs::icon( 'eye' ) . 'Χρονοδιάγραμμα Σχεδίου (Gantt)</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $rows ) {
			echo '<p class="sd-meta"><em>— Δεν υπάρχουν ενέργειες προς εμφάνιση —</em></p></section>';
			return;
		}
		echo '<div class="sd-table-wrap"><div class="sd-gantt" style="grid-template-columns:minmax(9rem,12rem) repeat(' . count( $cols ) . ',minmax(2.6rem,1fr))"><span></span>';
		foreach ( $cols as $c ) {
			echo '<span class="sd-gantt-m">' . esc_html( self::SHORT[ (int) substr( $c, 5, 2 ) ] ) . '</span>';
		}
		foreach ( $rows as $r ) {
			list( $code, $a, $f, $t ) = $r;
			echo '<span class="sd-gantt-l" title="' . esc_attr( $a->description ) . '">' . esc_html( $code . ' ' . wp_html_excerpt( $a->description, 22, '…' ) ) . '</span>';
			foreach ( $cols as $c ) {
				$on = $f && $c >= $f && $c <= $t;
				echo '<span class="sd-gantt-c' . ( $on ? ' is-' . esc_attr( $a->kind ) : '' ) . '"></span>';
			}
		}
		echo '</div></div><p class="sd-gantt-legend"><span class="is-ylopoiisi"></span>Υλοποίηση <span class="is-apotimisi"></span>Αποτίμηση</p></section>';
	}

	/* =================== Στήλη βοήθειας =================== */

	private static function side( $plan, $tab, $edit ) {
		$dl = self::deadline();
		if ( $dl ) {
			$days = (int) floor( ( strtotime( $dl ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );
			echo '<div class="sd-pt-warn"><strong>Προθεσμία υποβολής στην πλατφόρμα</strong><p>Τα Σχέδια Δράσης καταχωρίζονται στην πλατφόρμα του ΙΕΠ έως τις <strong>' . esc_html( date_i18n( 'j F Y', strtotime( $dl ) ) ) . '</strong>' . ( $days >= 0 ? ' (σε ' . (int) $days . ( 1 === $days ? ' ημέρα' : ' ημέρες' ) . ')' : '' ) . '.</p></div>';
		}
		if ( 'episkopisi' === $tab ) {
			$issues = SD_Plans::issues( $plan );
			$errors = array_values( array_filter( $issues, function ( $i ) {
				return 'error' === $i[0];
			} ) );
			echo '<div class="sd-pt-card"><h4>Επιβεβαίωση πριν από την καταχώριση</h4><ol class="sd-pt-confirm" data-confirm="' . esc_attr( 'sd-plan-' . $plan->id . '-' . $plan->updated_at ) . '">';
			$q = array(
				array( 'Σαφήνεια σχεδιασμού', 'Είναι σαφείς οι στόχοι και η σύνδεσή τους με τις ενέργειες, τα εργαλεία και τα κριτήρια επιτυχίας;' ),
				array( 'Ποιότητα αποτίμησης', 'Είναι σαφές από ποια στοιχεία θα διαπιστωθεί η επίτευξη των στόχων;' ),
				array( 'Κατανομή στον χρόνο', 'Υπάρχει επαρκής χρόνος για την ολοκλήρωση των ενεργειών;' ),
				array( 'Φόρτος σχεδίου', 'Είναι ο αριθμός και η έκταση των ενεργειών διαχειρίσιμα από την Ομάδα Δράσης;' ),
			);
			foreach ( $q as $i => $item ) {
				echo '<li><label><span><strong>' . esc_html( $item[0] ) . '</strong><br>' . esc_html( $item[1] ) . '</span><input type="checkbox" data-confirm-box></label></li>';
			}
			echo '</ol></div>';
			if ( $errors ) {
				echo '<div class="sd-pt-card sd-pt-errors"><h4>Λείπουν ακόμη (' . count( $errors ) . ')</h4><ul>';
				foreach ( array_slice( $errors, 0, 8 ) as $e ) {
					echo '<li>' . esc_html( $e[1] ) . '</li>';
				}
				echo '</ul></div>';
			}
			foreach ( $issues as $w ) {
				if ( 'warning' === $w[0] ) {
					echo '<p class="sd-help">ⓘ ' . esc_html( $w[1] ) . '</p>';
				}
			}
			echo '<div class="sd-pt-card sd-pt-exp"><span>' . SD_Docs::icon( 'word' ) . 'Εξαγωγή Σχεδίου</span>' . SD_Docs::actions( 'plan', $plan->id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<a class="sd-pt-go' . ( $errors ? ' is-off' : '' ) . '" data-confirm-go href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'copy' => $plan->id ) ) ) . '">Μεταφορά στην πλατφόρμα του ΙΕΠ</a>';
			echo '<p class="sd-help">Ανοίγουν τα κείμενα έτοιμα για αντιγραφή, πεδίο προς πεδίο, με τη σειρά της πλατφόρμας.' . ( $errors ? ' Πρώτα συμπληρώστε όσα λείπουν.' : '' ) . '</p>';
			return;
		}
		echo '<div class="sd-pt-card sd-pt-guide"><p>Σχεδιάστε τους <strong>στόχους</strong>, τις <strong>ενέργειες</strong>, τα <strong>εργαλεία</strong> και τον <strong>τρόπο εργασίας</strong> του Σχεδίου Δράσης. Για κάθε στόχο σκεφτείτε:</p><ul><li>τι θέλετε να αλλάξει,</li><li>τι θα κάνετε για να το πετύχετε,</li><li>πώς και με ποια εργαλεία θα εργαστείτε,</li><li>πώς θα διαπιστώσετε αν πετύχατε τον στόχο,</li><li>πότε θα γίνει κάθε ενέργεια.</li></ul><p>Έως <strong>' . (int) SD_Eval::MAX_GOALS . ' στόχους</strong> και έως <strong>' . self::MAX_ACTIONS . ' ενέργειες ανά στόχο</strong>, με τουλάχιστον μία ενέργεια υλοποίησης και μία αποτίμησης. Δεν χρειάζεται να εξαντλήσετε το όριο.</p></div>';
		echo '<div class="sd-pt-card sd-pt-help"><h4>Βοήθεια πεδίου</h4><p data-field-help><em>Πατήστε σε ένα πεδίο για να δείτε τη σχετική βοήθεια.</em></p></div>';
		echo '<div class="sd-pt-links">';
		echo '<a class="sd-pt-go" href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'copy' => $plan->id ) ) ) . '">Κείμενα για την πλατφόρμα</a>';
		if ( $edit ) {
			echo '<a class="sd-pt-link" href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'eisagogi' => $plan->id ) ) ) . '">Εισαγωγή από την πλατφόρμα</a>';
		}
		echo '<span class="sd-pt-link">' . SD_Docs::actions( 'plan', $plan->id ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
