<?php
/**
 * Σχέδια Δράσης εσωτερικής αξιολόγησης: δεδομένα, ενέργειες, οθόνες.
 *
 * Δικαιώματα: η Διεύθυνση δημιουργεί σχέδια και ορίζει ομάδα. Ο/Η Συντονιστής/τρια
 * επεξεργάζεται το περιεχόμενο του σχεδίου του/της (όπως στην πλατφόρμα του ΙΕΠ).
 * Όλοι οι χρήστες της εφαρμογής βλέπουν τα σχέδια, για να συνδέουν τις δράσεις τους.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Plans {

	/* =================== Δεδομένα =================== */

	private static function t( $name ) {
		return SD_Data::table( $name );
	}

	public static function plans( $year_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'plans' ) . ' WHERE year_id = %d ORDER BY code, id', $year_id ) );
	}

	public static function get_plan( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'plans' ) . ' WHERE id = %d', $id ) );
	}

	public static function members( $plan_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . self::t( 'plan_members' ) . ' WHERE plan_id = %d', $plan_id ) ) );
	}

	public static function goals( $plan_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'goals' ) . ' WHERE plan_id = %d ORDER BY ord, id', $plan_id ) );
	}

	public static function get_goal( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'goals' ) . ' WHERE id = %d', $id ) );
	}

	public static function actions( $goal_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::t( 'goal_actions' ) . " WHERE goal_id = %d ORDER BY FIELD(kind,'ylopoiisi','apotimisi'), ord, id", $goal_id )
		);
	}

	public static function get_action( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'goal_actions' ) . ' WHERE id = %d', $id ) );
	}

	/**
	 * Όλες οι ενέργειες του έτους, για επιλογή σε έντυπα: [plan, goal, action].
	 * Αν δοθεί τάξη, μόνο όσες την αφορούν (ή δεν έχουν περιορισμό τάξης).
	 */
	public static function year_actions( $year_id, $grade = 0 ) {
		$out = array();
		foreach ( self::plans( $year_id ) as $p ) {
			foreach ( self::goals( $p->id ) as $g ) {
				foreach ( self::actions( $g->id ) as $a ) {
					if ( $grade && '' !== $a->grades && ! in_array( (string) $grade, explode( ',', $a->grades ), true ) ) {
						continue;
					}
					$out[] = array( $p, $g, $a );
				}
			}
		}
		return $out;
	}

	public static function can_edit( $plan ) {
		if ( ! $plan ) {
			return false;
		}
		return current_user_can( SD_Roles::CAP_MANAGE ) || ( (int) $plan->coordinator_id && (int) $plan->coordinator_id === get_current_user_id() );
	}

	private static function touch( $plan_id ) {
		global $wpdb;
		$wpdb->update( self::t( 'plans' ), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $plan_id ) );
	}

	public static function add_plan( $year_id, $code, $title, $axis ) {
		global $wpdb;
		if ( '' === $title ) {
			return new WP_Error( 'sd', 'Γράψτε τίτλο.' );
		}
		if ( ! isset( SD_Eval::AXES[ $axis ] ) ) {
			return new WP_Error( 'sd', 'Διαλέξτε άξονα.' );
		}
		$now = current_time( 'mysql' );
		$wpdb->insert(
			self::t( 'plans' ),
			array(
				'year_id'    => (int) $year_id,
				'code'       => $code,
				'title'      => $title,
				'axis'       => (int) $axis,
				'purpose'    => '',
				'need'       => '',
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete_plan( $plan_id ) {
		global $wpdb;
		foreach ( self::goals( $plan_id ) as $g ) {
			self::delete_goal( $g->id );
		}
		$wpdb->delete( self::t( 'plan_members' ), array( 'plan_id' => (int) $plan_id ) );
		$wpdb->delete( self::t( 'plans' ), array( 'id' => (int) $plan_id ) );
	}

	public static function set_members( $plan_id, array $ids ) {
		global $wpdb;
		$wpdb->delete( self::t( 'plan_members' ), array( 'plan_id' => (int) $plan_id ) );
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $uid ) {
			if ( $uid > 0 && get_userdata( $uid ) ) {
				$wpdb->insert( self::t( 'plan_members' ), array( 'plan_id' => (int) $plan_id, 'user_id' => $uid ) );
			}
		}
	}

	public static function delete_goal( $goal_id ) {
		global $wpdb;
		$wpdb->delete( self::t( 'goal_actions' ), array( 'goal_id' => (int) $goal_id ) );
		$wpdb->delete( self::t( 'goals' ), array( 'id' => (int) $goal_id ) );
	}

	/**
	 * Έλεγχος πληρότητας με βάση τα όρια της επίσημης φόρμας.
	 * Επιστρέφει [ ['error'|'warning', κείμενο], ... ]. Τα προσχέδια αποθηκεύονται πάντα.
	 */
	public static function issues( $plan ) {
		$out   = array();
		$goals = self::goals( $plan->id );
		if ( ! $plan->coordinator_id ) {
			$out[] = array( 'warning', 'Δεν έχει οριστεί Συντονιστής/τρια.' );
		}
		$team = count( array_unique( array_merge( self::members( $plan->id ), $plan->coordinator_id ? array( (int) $plan->coordinator_id ) : array() ) ) );
		if ( $team && ( $team < 2 || $team > 5 ) ) {
			$out[] = array( 'warning', 'Η ομάδα έχει ' . $team . ' άτομα. Οι οδηγίες του ΙΕΠ αναφέρουν ομάδες 2–5 εκπαιδευτικών.' );
		}
		if ( '' === trim( $plan->purpose ) ) {
			$out[] = array( 'error', 'Λείπει ο σκοπός.' );
		}
		if ( '' === trim( $plan->need ) ) {
			$out[] = array( 'error', 'Λείπει η τεκμηρίωση της αναγκαιότητας.' );
		}
		if ( SD_Eval::length( $plan->purpose ) > SD_Eval::PURPOSE_LIMIT ) {
			$out[] = array( 'error', 'Ο σκοπός ξεπερνά τους ' . SD_Eval::PURPOSE_LIMIT . ' χαρακτήρες.' );
		}
		if ( SD_Eval::length( $plan->need ) > SD_Eval::PURPOSE_LIMIT ) {
			$out[] = array( 'error', 'Η τεκμηρίωση αναγκαιότητας ξεπερνά τους ' . SD_Eval::PURPOSE_LIMIT . ' χαρακτήρες.' );
		}
		if ( '' === (string) $plan->sources ) {
			$out[] = array( 'warning', 'Δεν έχουν σημειωθεί πηγές τεκμηρίωσης.' );
		}
		if ( ! $goals ) {
			$out[] = array( 'error', 'Χρειάζεται τουλάχιστον ένας στόχος.' );
		}
		if ( count( $goals ) > SD_Eval::MAX_GOALS ) {
			$out[] = array( 'error', 'Η φόρμα δέχεται έως ' . SD_Eval::MAX_GOALS . ' στόχους.' );
		}
		foreach ( $goals as $i => $g ) {
			$n = 'Στόχος ' . ( $i + 1 );
			if ( '' === trim( $g->description ) ) {
				$out[] = array( 'error', $n . ': λείπει η διατύπωση.' );
			} elseif ( SD_Eval::length( $g->description ) > SD_Eval::LIMIT ) {
				$out[] = array( 'error', $n . ': η διατύπωση ξεπερνά τους ' . SD_Eval::LIMIT . ' χαρακτήρες.' );
			}
			if ( '' === $g->tools && '' === trim( $g->tools_other ) ) {
				$out[] = array( 'error', $n . ': διαλέξτε τουλάχιστον ένα εργαλείο.' );
			}
			if ( '' === $g->ways && '' === trim( $g->ways_other ) ) {
				$out[] = array( 'error', $n . ': διαλέξτε τουλάχιστον έναν τρόπο εργασίας.' );
			}
			$counts = array(
				'ylopoiisi' => 0,
				'apotimisi' => 0,
			);
			foreach ( self::actions( $g->id ) as $a ) {
				$counts[ $a->kind ] = isset( $counts[ $a->kind ] ) ? $counts[ $a->kind ] + 1 : 1;
				$k = SD_Eval::ACTION_KINDS[ $a->kind ] ?? $a->kind;
				if ( '' === trim( $a->description ) ) {
					$out[] = array( 'error', $n . ', ενέργεια ' . $k . ': λείπει η περιγραφή.' );
				} elseif ( SD_Eval::length( $a->description ) > SD_Eval::LIMIT ) {
					$out[] = array( 'error', $n . ', ενέργεια ' . $k . ': η περιγραφή ξεπερνά τους ' . SD_Eval::LIMIT . ' χαρακτήρες.' );
				}
				if ( ! $a->start_date || ! $a->end_date ) {
					$out[] = array( 'error', $n . ', ενέργεια ' . $k . ': λείπουν οι ημερομηνίες.' );
				} elseif ( $a->end_date < $a->start_date ) {
					$out[] = array( 'error', $n . ', ενέργεια ' . $k . ': η λήξη είναι πριν από την έναρξη.' );
				}
				if ( 'apotimisi' === $a->kind && '' === trim( $a->criterion ) ) {
					$out[] = array( 'error', $n . ': η ενέργεια αποτίμησης χρειάζεται κριτήριο επιτυχίας.' );
				}
				if ( SD_Eval::length( $a->criterion ) > SD_Eval::LIMIT ) {
					$out[] = array( 'error', $n . ': το κριτήριο επιτυχίας ξεπερνά τους ' . SD_Eval::LIMIT . ' χαρακτήρες.' );
				}
			}
			if ( $counts['ylopoiisi'] < 1 || $counts['ylopoiisi'] > 2 ) {
				$out[] = array( 'error', $n . ': χρειάζονται 1 ή 2 ενέργειες υλοποίησης (τώρα ' . $counts['ylopoiisi'] . ').' );
			}
			if ( 1 !== $counts['apotimisi'] ) {
				$out[] = array( 'error', $n . ': χρειάζεται ακριβώς 1 ενέργεια αποτίμησης (τώρα ' . $counts['apotimisi'] . ').' );
			}
		}
		return $out;
	}

	/** Εκπαιδευτικοί που δεν ανήκουν σε καμία ομάδα σχεδίου του έτους. */
	public static function users_without_team( $year_id ) {
		$in = array();
		foreach ( self::plans( $year_id ) as $p ) {
			$in = array_merge( $in, self::members( $p->id ) );
			if ( $p->coordinator_id ) {
				$in[] = (int) $p->coordinator_id;
			}
		}
		$out = array();
		foreach ( get_users( array( 'role__in' => array_keys( SD_Roles::definitions() ), 'orderby' => 'display_name' ) ) as $u ) {
			if ( ! in_array( (int) $u->ID, $in, true ) ) {
				$out[] = $u;
			}
		}
		return $out;
	}

	/* =================== Ενέργειες (POST) =================== */

	private static function post_text( $key, $limit, $label, $multiline = true ) {
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security
		$val = $multiline ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		if ( $limit && SD_Eval::length( $val ) > $limit ) {
			return new WP_Error( 'sd', $label . ': έως ' . $limit . ' χαρακτήρες (γράψατε ' . SD_Eval::length( $val ) . ').' );
		}
		return $val;
	}

	private static function post_keys( $key, array $allowed ) {
		$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? array_map( 'sanitize_key', wp_unslash( $_POST[ $key ] ) ) : array(); // phpcs:ignore WordPress.Security
		return implode( ',', array_values( array_intersect( array_keys( $allowed ), $raw ) ) );
	}

	private static function post_date( $key ) {
		$v = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : null;
	}

	/** Πρώτο σφάλμα από λίστα τιμών, αλλιώς null. */
	private static function first_error( array $values ) {
		foreach ( $values as $v ) {
			if ( is_wp_error( $v ) ) {
				return $v;
			}
		}
		return null;
	}

	/**
	 * Χειρίζεται τις ενέργειες των σχεδίων. Επιστρέφει [αποτέλεσμα, URL επιστροφής]
	 * ή null αν η ενέργεια δεν αφορά τα σχέδια.
	 */
	public static function handle( $do ) {
		global $wpdb;
		$ours = array( 'add_plan', 'delete_plan', 'save_plan', 'save_team', 'add_goal', 'save_goal', 'delete_goal', 'add_action', 'save_action', 'delete_action', 'import_plans' );
		if ( ! in_array( $do, $ours, true ) ) {
			return null;
		}
		$list = SD_Frontend::url( 'sxedia' );

		// Ενέργειες μόνο για τη Διεύθυνση.
		if ( in_array( $do, array( 'add_plan', 'delete_plan', 'save_team', 'import_plans' ), true ) && ! current_user_can( SD_Roles::CAP_MANAGE ) ) {
			return array( new WP_Error( 'sd', 'Δεν έχετε δικαίωμα για αυτή την ενέργεια.' ), $list );
		}

		if ( 'add_plan' === $do ) {
			$year = SD_Data::active_year();
			if ( ! $year ) {
				return array( new WP_Error( 'sd', 'Δεν υπάρχει τρέχον σχολικό έτος.' ), $list );
			}
			$id = self::add_plan(
				$year->id,
				isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
				isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				isset( $_POST['axis'] ) ? absint( $_POST['axis'] ) : 0
			);
			return array( $id, is_wp_error( $id ) ? $list : SD_Frontend::url( 'sxedia', array( 'id' => $id ) ) );
		}

		if ( 'import_plans' === $do ) {
			return array( SD_Seed::import_plans(), $list );
		}

		// Από εδώ: ενέργειες σε συγκεκριμένο σχέδιο. Βρίσκουμε το σχέδιο από το
		// στοιχείο που αλλάζει, ώστε ο έλεγχος δικαιώματος να γίνεται στο σωστό σχέδιο.
		$plan = null;
		$goal = null;
		$act  = null;
		if ( isset( $_POST['action_id'] ) ) {
			$act  = self::get_action( absint( $_POST['action_id'] ) );
			$goal = $act ? self::get_goal( $act->goal_id ) : null;
		} elseif ( isset( $_POST['goal_id'] ) ) {
			$goal = self::get_goal( absint( $_POST['goal_id'] ) );
		}
		if ( $goal ) {
			$plan = self::get_plan( $goal->plan_id );
		} elseif ( isset( $_POST['plan_id'] ) ) {
			$plan = self::get_plan( absint( $_POST['plan_id'] ) );
		}
		if ( ! $plan ) {
			return array( new WP_Error( 'sd', 'Το σχέδιο δεν βρέθηκε.' ), $list );
		}
		if ( ! self::can_edit( $plan ) ) {
			return array( new WP_Error( 'sd', 'Μόνο ο/η Συντονιστής/τρια και η Διεύθυνση επεξεργάζονται το σχέδιο.' ), $list );
		}
		$back = SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) );

		switch ( $do ) {
			case 'delete_plan':
				self::delete_plan( $plan->id );
				return array( true, $list );

			case 'save_team':
				$coord = isset( $_POST['coordinator_id'] ) ? absint( $_POST['coordinator_id'] ) : 0;
				$wpdb->update(
					self::t( 'plans' ),
					array(
						'coordinator_id' => $coord && get_userdata( $coord ) ? $coord : 0,
						'team_note'      => isset( $_POST['team_note'] ) ? sanitize_text_field( wp_unslash( $_POST['team_note'] ) ) : '',
					),
					array( 'id' => $plan->id )
				);
				$ids = isset( $_POST['members'] ) && is_array( $_POST['members'] ) ? array_map( 'absint', wp_unslash( $_POST['members'] ) ) : array();
				self::set_members( $plan->id, array_diff( $ids, array( $coord ) ) );
				self::touch( $plan->id );
				return array( true, $back . '#omada' );

			case 'save_plan':
				$title   = self::post_text( 'title', 255, 'Τίτλος', false );
				$purpose = self::post_text( 'purpose', SD_Eval::PURPOSE_LIMIT, 'Σκοπός' );
				$need    = self::post_text( 'need', SD_Eval::PURPOSE_LIMIT, 'Τεκμηρίωση αναγκαιότητας' );
				$err     = self::first_error( array( $title, $purpose, $need ) );
				if ( $err ) {
					return array( $err, $back );
				}
				$axis = isset( $_POST['axis'] ) ? absint( $_POST['axis'] ) : (int) $plan->axis;
				$wpdb->update(
					self::t( 'plans' ),
					array(
						'code'       => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : $plan->code,
						'title'      => $title,
						'axis'       => isset( SD_Eval::AXES[ $axis ] ) ? $axis : (int) $plan->axis,
						'indicators' => self::post_keys( 'indicators', SD_Eval::indicators_of( $axis ) ),
						'purpose'      => $purpose,
						'need'         => $need,
						'sources'      => self::post_keys( 'sources', SD_Eval::SOURCES ),
						'praxi'        => isset( $_POST['praxi'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['praxi'] ) ), 0, 60 ) : '',
						'continuation' => isset( $_POST['continuation'] ) && isset( SD_Eval::YES_NO[ $_POST['continuation'] ] ) ? sanitize_key( $_POST['continuation'] ) : '',
						'collab'       => isset( $_POST['collab'] ) && isset( SD_Eval::YES_NO[ $_POST['collab'] ] ) ? sanitize_key( $_POST['collab'] ) : '',
						'collab_note'  => isset( $_POST['collab_note'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['collab_note'] ) ), 0, 255 ) : '',
						'updated_at'   => current_time( 'mysql' ),
					),
					array( 'id' => $plan->id )
				);
				return array( true, $back );

			case 'add_goal':
			case 'save_goal':
				if ( 'add_goal' === $do && count( self::goals( $plan->id ) ) >= SD_Eval::MAX_GOALS ) {
					return array( new WP_Error( 'sd', 'Η φόρμα δέχεται έως ' . SD_Eval::MAX_GOALS . ' στόχους.' ), $back );
				}
				$title = self::post_text( 'title', 255, 'Σύντομος τίτλος', false );
				$desc  = self::post_text( 'description', SD_Eval::LIMIT, 'Διατύπωση στόχου' );
				$t_oth = self::post_text( 'tools_other', SD_Eval::OTHER_LIMIT, 'Άλλο εργαλείο', false );
				$w_oth = self::post_text( 'ways_other', SD_Eval::OTHER_LIMIT, 'Άλλος τρόπος εργασίας', false );
				$err   = self::first_error( array( $title, $desc, $t_oth, $w_oth ) );
				if ( $err ) {
					return array( $err, $back );
				}
				$data = array(
					'title'       => $title,
					'description' => $desc,
					'tools'       => self::post_keys( 'tools', SD_Eval::TOOLS ),
					'tools_other' => $t_oth,
					'ways'        => self::post_keys( 'ways', SD_Eval::WAYS ),
					'ways_other'  => $w_oth,
				);
				if ( 'add_goal' === $do ) {
					$data['plan_id'] = $plan->id;
					$data['ord']     = count( self::goals( $plan->id ) ) + 1;
					$wpdb->insert( self::t( 'goals' ), $data );
					$anchor = '#stoxos-' . (int) $wpdb->insert_id;
				} else {
					$wpdb->update( self::t( 'goals' ), $data, array( 'id' => $goal->id ) );
					$anchor = '#stoxos-' . (int) $goal->id;
				}
				self::touch( $plan->id );
				return array( true, $back . $anchor );

			case 'delete_goal':
				self::delete_goal( $goal->id );
				self::touch( $plan->id );
				return array( true, $back );

			case 'add_action':
			case 'save_action':
				$desc = self::post_text( 'description', SD_Eval::LIMIT, 'Περιγραφή ενέργειας' );
				$crit = self::post_text( 'criterion', SD_Eval::LIMIT, 'Κριτήριο επιτυχίας' );
				$err  = self::first_error( array( $desc, $crit ) );
				if ( $err ) {
					return array( $err, $back . '#stoxos-' . (int) $goal->id );
				}
				$kind   = isset( $_POST['kind'] ) && isset( SD_Eval::ACTION_KINDS[ sanitize_key( wp_unslash( $_POST['kind'] ) ) ] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'ylopoiisi';
				$grades = isset( $_POST['grades'] ) && is_array( $_POST['grades'] ) ? array_map( 'absint', wp_unslash( $_POST['grades'] ) ) : array();
				$grades = array_values( array_intersect( array_keys( SD_Data::GRADES ), $grades ) );
				$data   = array(
					'kind'        => $kind,
					'description' => $desc,
					'start_date'  => self::post_date( 'start_date' ),
					'end_date'    => self::post_date( 'end_date' ),
					'criterion'   => $crit,
					'grades'      => implode( ',', $grades ),
				);
				if ( 'add_action' === $do ) {
					$data['goal_id'] = $goal->id;
					$data['ord']     = count( self::actions( $goal->id ) ) + 1;
					$wpdb->insert( self::t( 'goal_actions' ), $data );
				} else {
					$wpdb->update( self::t( 'goal_actions' ), $data, array( 'id' => $act->id ) );
				}
				self::touch( $plan->id );
				return array( true, $back . '#stoxos-' . (int) $goal->id );

			case 'delete_action':
				$wpdb->delete( self::t( 'goal_actions' ), array( 'id' => $act->id ) );
				self::touch( $plan->id );
				return array( true, $back . '#stoxos-' . (int) $goal->id );
		}
		return array( new WP_Error( 'sd', 'Άγνωστη ενέργεια.' ), $list );
	}

	/* =================== Οθόνες =================== */

	private static function user_name( $id ) {
		$u = $id ? get_userdata( $id ) : null;
		return $u ? $u->display_name : '';
	}

	private static function textarea( $name, $value, $limit = 0, $rows = 4, $required = false ) {
		printf(
			'<textarea name="%s" rows="%d"%s%s>%s</textarea>',
			esc_attr( $name ),
			(int) $rows,
			$limit ? ' data-limit="' . (int) $limit . '"' : '',
			$required ? ' required' : '',
			esc_textarea( $value )
		);
	}

	private static function checks( $name, array $options, $selected_csv ) {
		$sel = '' === $selected_csv ? array() : explode( ',', $selected_csv );
		echo '<div class="sd-checks">';
		foreach ( $options as $k => $label ) {
			printf(
				'<label><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
				esc_attr( $name ),
				esc_attr( $k ),
				in_array( (string) $k, $sel, true ) ? ' checked' : '',
				esc_html( $label )
			);
		}
		echo '</div>';
	}

	private static function labels( $csv, array $options, $other = '' ) {
		$out = array();
		foreach ( '' === $csv ? array() : explode( ',', $csv ) as $k ) {
			if ( isset( $options[ $k ] ) ) {
				$out[] = $options[ $k ];
			}
		}
		if ( '' !== trim( $other ) ) {
			$out[] = 'Άλλο: ' . $other;
		}
		return $out;
	}

	private static function fmt_date( $d ) {
		return $d ? mysql2date( 'd/m/Y', $d ) : '—';
	}

	private static function grades_text( $csv ) {
		if ( '' === $csv ) {
			return 'Όλες οι τάξεις';
		}
		$out = array();
		foreach ( explode( ',', $csv ) as $g ) {
			if ( isset( SD_Data::GRADES[ (int) $g ] ) ) {
				$out[] = SD_Data::GRADES[ (int) $g ];
			}
		}
		return implode( ', ', $out );
	}

	public static function render() {
		if ( isset( $_GET['eisagogi'] ) ) {
			SD_PlanImport::render( absint( $_GET['eisagogi'] ) );
		} elseif ( isset( $_GET['copy'] ) ) {
			self::render_copy( absint( $_GET['copy'] ) );
		} elseif ( isset( $_GET['id'] ) ) {
			self::render_plan( absint( $_GET['id'] ) );
		} else {
			self::render_list();
		}
	}

	private static function render_list() {
		$year = SD_Data::active_year();
		if ( ! $year ) {
			echo '<p class="sd-notice sd-error">Δεν υπάρχει τρέχον σχολικό έτος.</p>';
			return;
		}
		$plans = self::plans( $year->id );
		echo '<h2>Σχέδια Δράσης ' . esc_html( $year->label ) . '</h2>';
		echo '<p class="sd-help">Τα Σχέδια Δράσης της εσωτερικής αξιολόγησης είναι η «ομπρέλα» για τα Εργαστήρια και τις Δράσεις Ενεργού Πολίτη. Τα πεδία ακολουθούν την επίσημη φόρμα της πλατφόρμας του ΙΕΠ.</p>';

		if ( ! $plans && current_user_can( SD_Roles::CAP_MANAGE ) && SD_Seed::available_for( $year ) ) {
			echo '<section class="sd-card"><h3>Γρήγορη αρχή</h3><p>Μπορείτε να περάσετε έτοιμα τα τρία Σχέδια Δράσης του σχολείου για το ' . esc_html( $year->label ) . ', με τα στοιχεία που υπάρχουν ήδη στην πλατφόρμα (σκοπός, ανάγκη, στόχοι, ενέργειες όπου τα έχουμε). Μετά τα συμπληρώνετε ή τα διορθώνετε.</p>';
			SD_Frontend::form_open( 'import_plans' );
			echo '<button class="sd-button" type="submit">Εισαγωγή ΣΔ1–ΣΔ3</button></form></section>';
		}

		foreach ( $plans as $p ) {
			$issues = self::issues( $p );
			$errors = count(
				array_filter(
					$issues,
					function ( $i ) {
						return 'error' === $i[0];
					}
				)
			);
			$goals  = self::goals( $p->id );
			$nact   = 0;
			foreach ( $goals as $g ) {
				$nact += count( self::actions( $g->id ) );
			}
			echo '<section class="sd-card sd-plan-card">';
			echo '<h3><a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'id' => $p->id ) ) ) . '">' . esc_html( trim( $p->code . ' ' . $p->title ) ) . '</a></h3>';
			echo '<p class="sd-meta">Άξονας ' . (int) $p->axis . ': ' . esc_html( SD_Eval::axis_label( $p->axis ) ) . '</p>';
			$coord = self::user_name( $p->coordinator_id );
			echo '<p class="sd-meta">Συντονισμός: ' . esc_html( $coord ? $coord : ( $p->team_note ? $p->team_note : '—' ) ) . ' · Στόχοι: ' . count( $goals ) . ' · Ενέργειες: ' . (int) $nact . '</p>';
			echo $errors ? '<p class="sd-badge sd-badge-warn">Ελλείψεις για την πλατφόρμα: ' . (int) $errors . '</p>' : '<p class="sd-badge sd-badge-ok">Πλήρες για την πλατφόρμα</p>';
			echo '</section>';
		}

		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			$free = self::users_without_team( $year->id );
			if ( $plans ) {
				echo '<section class="sd-card"><h3>Συμμετοχή στις ομάδες</h3>';
				if ( $free ) {
					echo '<p>Κάθε εκπαιδευτικός συμμετέχει σε τουλάχιστον μία Ομάδα Δράσης (ΥΑ 108906/2021, άρθρο 4). Χωρίς ομάδα:</p><ul class="sd-chips">';
					foreach ( $free as $u ) {
						echo '<li>' . esc_html( $u->display_name ) . '</li>';
					}
					echo '</ul>';
				} else {
					echo '<p>Όλοι οι εκπαιδευτικοί της εφαρμογής ανήκουν σε ομάδα.</p>';
				}
				echo '</section>';
			}

			echo '<section class="sd-card"><h3>Νέο Σχέδιο Δράσης</h3>';
			SD_Frontend::form_open( 'add_plan' );
			echo '<div class="sd-row"><label>Κωδικός <input name="code" size="5" placeholder="ΣΔ4"></label>';
			echo '<label class="sd-grow">Τίτλος <input name="title" required></label>';
			echo '<label>Άξονας <select name="axis" required>';
			foreach ( SD_Eval::AXES as $n => $a ) {
				echo '<option value="' . (int) $n . '">' . (int) $n . '. ' . esc_html( $a[0] ) . '</option>';
			}
			echo '</select></label><button class="sd-button" type="submit">Δημιουργία</button></div></form></section>';
		}
	}

	private static function render_plan( $plan_id ) {
		$plan = self::get_plan( $plan_id );
		if ( ! $plan ) {
			echo '<p class="sd-notice sd-error">Το σχέδιο δεν βρέθηκε.</p>';
			return;
		}
		$edit  = self::can_edit( $plan );
		$goals = self::goals( $plan->id );
		if ( $edit ) {
			SD_AiField::mark( 'plan', $plan->id );
		}

		echo '<p><a href="' . esc_url( SD_Frontend::url( 'sxedia' ) ) . '">← Όλα τα σχέδια</a> · <a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'copy' => $plan->id ) ) ) . '">Κείμενα για την πλατφόρμα</a> · <a href="' . esc_url( SD_Exports::url( 'plan', $plan->id ) ) . '">Λήψη σε Word</a>' . ( $edit ? ' · <a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'eisagogi' => $plan->id ) ) ) . '">Εισαγωγή από την πλατφόρμα</a>' : '' ) . '</p>';
		echo '<h2>' . esc_html( trim( $plan->code . ' ' . $plan->title ) ) . '</h2>';
		echo '<p class="sd-meta">' . esc_html( SD_Eval::function_label( $plan->axis ) ) . ' · Άξονας ' . (int) $plan->axis . ': ' . esc_html( SD_Eval::axis_label( $plan->axis ) ) . '</p>';

		$issues = self::issues( $plan );
		if ( $issues ) {
			echo '<section class="sd-card sd-issues"><h3>Έλεγχος για την πλατφόρμα</h3><ul>';
			foreach ( $issues as $i ) {
				echo '<li class="sd-issue-' . esc_attr( $i[0] ) . '">' . ( 'error' === $i[0] ? 'Λείπει / λάθος: ' : 'Προσοχή: ' ) . esc_html( $i[1] ) . '</li>';
			}
			echo '</ul><p class="sd-help">Τα προσχέδια αποθηκεύονται κανονικά. Ο έλεγχος δείχνει τι χρειάζεται πριν την καταχώριση στην πλατφόρμα.</p></section>';
		} else {
			echo '<p class="sd-notice sd-ok">Το σχέδιο είναι πλήρες για την πλατφόρμα.</p>';
		}

		/* --- Στοιχεία σχεδίου --- */
		$ind = '' === $plan->indicators ? array() : explode( ',', $plan->indicators );
		if ( $edit ) {
			echo '<section class="sd-card"><h3>Στοιχεία σχεδίου</h3>';
			SD_Frontend::form_open( 'save_plan' );
			echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '">';
			echo '<div class="sd-row"><label>Κωδικός <input name="code" size="5" value="' . esc_attr( $plan->code ) . '"></label>';
			echo '<label class="sd-grow">Τίτλος <input name="title" required value="' . esc_attr( $plan->title ) . '"></label></div>';
			echo '<label class="sd-block">Άξονας <select name="axis">';
			foreach ( SD_Eval::AXES as $n => $a ) {
				echo '<option value="' . (int) $n . '"' . selected( (int) $plan->axis, $n, false ) . '>' . (int) $n . '. ' . esc_html( $a[0] ) . '</option>';
			}
			echo '</select></label>';
			echo '<fieldset class="sd-sub"><legend>Δείκτες του άξονα (ενδεικτικοί, προαιρετικοί)</legend>';
			self::checks( 'indicators', SD_Eval::indicators_of( $plan->axis ), $plan->indicators );
			echo '<p class="sd-help">Αν αλλάξετε άξονα, αποθηκεύστε και μετά διαλέξτε δείκτες.</p></fieldset>';
			echo '<label class="sd-block">Σκοπός';
			self::textarea( 'purpose', $plan->purpose, SD_Eval::PURPOSE_LIMIT, 5 );
			echo '</label><label class="sd-block">Τεκμηρίωση αναγκαιότητας';
			self::textarea( 'need', $plan->need, SD_Eval::PURPOSE_LIMIT, 5 );
			echo '</label><fieldset class="sd-sub"><legend>Πηγές τεκμηρίωσης</legend>';
			self::checks( 'sources', SD_Eval::SOURCES, $plan->sources );
			echo '</fieldset><div class="sd-row"><label>Πράξη Συλλόγου Διδασκόντων <input name="praxi" size="18" value="' . esc_attr( $plan->praxi ) . '" placeholder="π.χ. 12/20-10-2026"></label>';
			foreach ( array( 'continuation' => 'Συνέχεια προηγούμενου σχεδίου', 'collab' => 'Συνεργασία με άλλο σχολείο' ) as $f => $l ) {
				echo '<label>' . esc_html( $l ) . ' <select name="' . esc_attr( $f ) . '"><option value="">—</option>';
				foreach ( SD_Eval::YES_NO as $k => $v ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( $plan->$f, $k, false ) . '>' . esc_html( $v ) . '</option>';
				}
				echo '</select></label>';
			}
			echo '</div><label class="sd-block">Με ποιο σχολείο (αν υπάρχει συνεργασία) <input name="collab_note" value="' . esc_attr( $plan->collab_note ) . '"></label>';
			echo '<p><button class="sd-button" type="submit">Αποθήκευση στοιχείων</button></p></form></section>';
		} else {
			echo '<section class="sd-card"><h3>Σκοπός</h3><p>' . nl2br( esc_html( $plan->purpose ? $plan->purpose : '—' ) ) . '</p>';
			echo '<h3>Τεκμηρίωση αναγκαιότητας</h3><p>' . nl2br( esc_html( $plan->need ? $plan->need : '—' ) ) . '</p>';
			$src = self::labels( $plan->sources, SD_Eval::SOURCES, '' );
			echo '<p class="sd-meta">Πηγές τεκμηρίωσης: ' . esc_html( $src ? implode( ' · ', $src ) : '—' ) . '<br>Πράξη Συλλόγου: ' . esc_html( $plan->praxi ? $plan->praxi : '—' ) . ' · Συνέχεια προηγούμενου: ' . esc_html( SD_Eval::YES_NO[ $plan->continuation ] ?? '—' ) . ' · Συνεργασία με άλλο σχολείο: ' . esc_html( ( SD_Eval::YES_NO[ $plan->collab ] ?? '—' ) . ( $plan->collab_note ? ' (' . $plan->collab_note . ')' : '' ) ) . '</p>';
			if ( $ind ) {
				echo '<h3>Δείκτες</h3><ul>';
				foreach ( $ind as $i ) {
					echo '<li>' . esc_html( SD_Eval::INDICATORS[ $i ] ?? $i ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</section>';
		}

		/* --- Ομάδα --- */
		$members = self::members( $plan->id );
		echo '<section class="sd-card" id="omada"><h3>Ομάδα Δράσης</h3>';
		$names = array();
		foreach ( $members as $m ) {
			$names[] = self::user_name( $m );
		}
		echo '<p>Συντονιστής/τρια: <strong>' . esc_html( self::user_name( $plan->coordinator_id ) ? self::user_name( $plan->coordinator_id ) : '—' ) . '</strong><br>Μέλη: ' . esc_html( $names ? implode( ', ', $names ) : '—' ) . '</p>';
		if ( $plan->team_note ) {
			echo '<p class="sd-help">Σημείωση (όπως στην πλατφόρμα): ' . esc_html( $plan->team_note ) . '</p>';
		}
		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			$users = SD_Roles::app_users();
			echo '<details><summary>Ορισμός ομάδας</summary>';
			SD_Frontend::form_open( 'save_team' );
			echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '">';
			echo '<label class="sd-block">Συντονιστής/τρια <select name="coordinator_id"><option value="0">—</option>';
			foreach ( $users as $u ) {
				echo '<option value="' . (int) $u->ID . '"' . selected( (int) $plan->coordinator_id, (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
			}
			echo '</select></label><fieldset class="sd-sub"><legend>Μέλη</legend><div class="sd-checks">';
			foreach ( $users as $u ) {
				printf( '<label><input type="checkbox" name="members[]" value="%d"%s> %s</label>', (int) $u->ID, in_array( (int) $u->ID, $members, true ) ? ' checked' : '', esc_html( $u->display_name ) );
			}
			echo '</div></fieldset><label class="sd-block">Σημείωση ομάδας <input name="team_note" value="' . esc_attr( $plan->team_note ) . '"></label>';
			echo '<p><button class="sd-button" type="submit">Αποθήκευση ομάδας</button></p></form></details>';
		}
		echo '</section>';

		/* --- Στόχοι --- */
		foreach ( $goals as $i => $g ) {
			self::render_goal( $plan, $g, $i + 1, $edit );
		}
		if ( $edit && count( $goals ) < SD_Eval::MAX_GOALS ) {
			echo '<section class="sd-card"><details' . ( $goals ? '' : ' open' ) . '><summary><strong>Νέος στόχος</strong> (' . count( $goals ) . ' από ' . SD_Eval::MAX_GOALS . ')</summary>';
			self::goal_form( $plan, null );
			echo '</details></section>';
		}

		if ( current_user_can( SD_Roles::CAP_MANAGE ) ) {
			SD_Frontend::form_open( 'delete_plan' );
			echo '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '">';
			echo '<p><button class="sd-button sd-danger sd-small" type="submit" onclick="return confirm(\'Διαγραφή ολόκληρου του σχεδίου με τους στόχους και τις ενέργειές του;\');">Διαγραφή σχεδίου</button></p></form>';
		}
	}

	private static function goal_form( $plan, $g ) {
		SD_Frontend::form_open( $g ? 'save_goal' : 'add_goal' );
		echo $g ? '<input type="hidden" name="goal_id" value="' . (int) $g->id . '">' : '<input type="hidden" name="plan_id" value="' . (int) $plan->id . '">';
		echo '<label class="sd-block">Σύντομος τίτλος (για τις λίστες της εφαρμογής) <input name="title" value="' . esc_attr( $g ? $g->title : '' ) . '"></label>';
		echo '<label class="sd-block">Διατύπωση στόχου';
		self::textarea( 'description', $g ? $g->description : '', SD_Eval::LIMIT, 5, true );
		echo '</label><fieldset class="sd-sub"><legend>Εργαλεία</legend>';
		self::checks( 'tools', SD_Eval::TOOLS, $g ? $g->tools : '' );
		echo '<label class="sd-block">Άλλο <input name="tools_other" maxlength="' . (int) SD_Eval::OTHER_LIMIT . '" value="' . esc_attr( $g ? $g->tools_other : '' ) . '"></label></fieldset>';
		echo '<fieldset class="sd-sub"><legend>Τρόπος εργασίας</legend>';
		self::checks( 'ways', SD_Eval::WAYS, $g ? $g->ways : '' );
		echo '<label class="sd-block">Άλλο <input name="ways_other" maxlength="' . (int) SD_Eval::OTHER_LIMIT . '" value="' . esc_attr( $g ? $g->ways_other : '' ) . '"></label></fieldset>';
		echo '<p><button class="sd-button" type="submit">' . ( $g ? 'Αποθήκευση στόχου' : 'Προσθήκη στόχου' ) . '</button></p></form>';
	}

	private static function action_form( $g, $a, $kind = 'ylopoiisi' ) {
		SD_Frontend::form_open( $a ? 'save_action' : 'add_action' );
		echo $a ? '<input type="hidden" name="action_id" value="' . (int) $a->id . '">' : '<input type="hidden" name="goal_id" value="' . (int) $g->id . '">';
		$cur = $a ? $a->kind : $kind;
		echo '<div class="sd-row"><label>Είδος <select name="kind">';
		foreach ( SD_Eval::ACTION_KINDS as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>Έναρξη <input type="date" name="start_date" value="' . esc_attr( $a ? (string) $a->start_date : '' ) . '"></label>';
		echo '<label>Λήξη <input type="date" name="end_date" value="' . esc_attr( $a ? (string) $a->end_date : '' ) . '"></label></div>';
		echo '<label class="sd-block">Περιγραφή';
		self::textarea( 'description', $a ? $a->description : '', SD_Eval::LIMIT, 4, true );
		echo '</label><label class="sd-block">Κριτήριο επιτυχίας (υποχρεωτικό στην αποτίμηση)';
		self::textarea( 'criterion', $a ? $a->criterion : '', SD_Eval::LIMIT, 3 );
		echo '</label><fieldset class="sd-sub"><legend>Ποιες τάξεις αφορά (κενό = όλες)</legend><div class="sd-checks sd-checks-tight">';
		$sel = $a && '' !== $a->grades ? explode( ',', $a->grades ) : array();
		foreach ( SD_Data::GRADES as $n => $l ) {
			printf( '<label><input type="checkbox" name="grades[]" value="%d"%s> %s</label>', (int) $n, in_array( (string) $n, $sel, true ) ? ' checked' : '', esc_html( $l ) );
		}
		echo '</div><p class="sd-help">Έτσι, στα έντυπα κάθε τάξη βλέπει μόνο τις ενέργειες που την αφορούν.</p></fieldset>';
		echo '<p><button class="sd-button" type="submit">' . ( $a ? 'Αποθήκευση ενέργειας' : 'Προσθήκη ενέργειας' ) . '</button></p></form>';
	}

	private static function render_goal( $plan, $g, $n, $edit ) {
		$actions = self::actions( $g->id );
		echo '<section class="sd-card sd-goal" id="stoxos-' . (int) $g->id . '">';
		echo '<h3>Στόχος ' . (int) $n . ( $g->title ? ': ' . esc_html( $g->title ) : '' ) . '</h3>';
		echo '<p>' . nl2br( esc_html( $g->description ) ) . '</p>';
		$tools = self::labels( $g->tools, SD_Eval::TOOLS, $g->tools_other );
		$ways  = self::labels( $g->ways, SD_Eval::WAYS, $g->ways_other );
		echo '<p class="sd-meta">Εργαλεία: ' . esc_html( $tools ? implode( ' · ', $tools ) : '—' ) . '<br>Τρόπος εργασίας: ' . esc_html( $ways ? implode( ' · ', $ways ) : '—' ) . '</p>';

		echo '<ol class="sd-actions">';
		foreach ( $actions as $a ) {
			echo '<li><span class="sd-badge sd-kind-' . esc_attr( $a->kind ) . '">' . esc_html( SD_Eval::ACTION_KINDS[ $a->kind ] ?? $a->kind ) . '</span> ';
			echo '<span class="sd-meta">' . esc_html( self::fmt_date( $a->start_date ) . ' – ' . self::fmt_date( $a->end_date ) . ' · ' . self::grades_text( $a->grades ) ) . '</span>';
			echo '<p>' . nl2br( esc_html( $a->description ) ) . '</p>';
			if ( '' !== trim( $a->criterion ) ) {
				echo '<p class="sd-criterion"><strong>Κριτήριο επιτυχίας:</strong> ' . nl2br( esc_html( $a->criterion ) ) . '</p>';
			}
			if ( $edit ) {
				echo '<details><summary>Επεξεργασία ενέργειας</summary>';
				self::action_form( $g, $a );
				SD_Frontend::form_open( 'delete_action' );
				echo '<input type="hidden" name="action_id" value="' . (int) $a->id . '"><button class="sd-button sd-danger sd-small" type="submit" onclick="return confirm(\'Διαγραφή της ενέργειας;\');">Διαγραφή ενέργειας</button></form></details>';
			}
			echo '</li>';
		}
		echo '</ol>';

		if ( $edit ) {
			$has_eval = false;
			foreach ( $actions as $a ) {
				if ( 'apotimisi' === $a->kind ) {
					$has_eval = true;
				}
			}
			echo '<details><summary>Νέα ενέργεια</summary>';
			self::action_form( $g, null, $has_eval ? 'ylopoiisi' : ( count( $actions ) >= 1 ? 'apotimisi' : 'ylopoiisi' ) );
			echo '</details><details><summary>Επεξεργασία στόχου</summary>';
			self::goal_form( $plan, $g );
			SD_Frontend::form_open( 'delete_goal' );
			echo '<input type="hidden" name="goal_id" value="' . (int) $g->id . '"><button class="sd-button sd-danger sd-small" type="submit" onclick="return confirm(\'Διαγραφή του στόχου και των ενεργειών του;\');">Διαγραφή στόχου</button></form></details>';
		}
		echo '</section>';
	}

	private static function boxes( array $options, $csv, $other = '' ) {
		$sel = '' === (string) $csv ? array() : explode( ',', $csv );
		$out = array();
		foreach ( $options as $k => $l ) {
			$out[] = ( in_array( (string) $k, $sel, true ) ? '☒ ' : '☐ ' ) . $l;
		}
		$out[] = 'Άλλο: ' . $other;
		return implode( "\n", $out );
	}

	/** Μπλοκ για Word: «Δομικά στοιχεία» και «Στοχοθεσία», όπως στα πρότυπα του σχολείου. */
	public static function doc_blocks( $plan ) {
		$year  = null;
		foreach ( SD_Data::years() as $y ) {
			if ( (int) $y->id === (int) $plan->year_id ) {
				$year = $y;
			}
		}
		$names = array();
		foreach ( self::members( $plan->id ) as $m ) {
			$names[] = self::user_name( $m );
		}
		$src = array();
		$sel = '' === (string) $plan->sources ? array() : explode( ',', $plan->sources );
		foreach ( SD_Eval::SOURCES as $k => $l ) {
			$src[] = ( in_array( $k, $sel, true ) ? '☒ ' : '☐ ' ) . $l;
		}
		$b = array(
			array( 'p', SD_Frontend::school_name(), true ),
			array( 'title', 'Σχέδιο Δράσης ' . trim( $plan->code . ' «' . $plan->title . '»' ) ),
			array( 'p', 'Εσωτερική αξιολόγηση της σχολικής μονάδας · Σχολικό έτος ' . ( $year ? $year->label : '' ) ),
			array( 'h', 'Δομικά στοιχεία' ),
			array(
				'kv',
				array(
					array( 'Τίτλος σχεδίου', $plan->title ),
					array( 'Λειτουργία', SD_Eval::function_label( $plan->axis ) ),
					array( 'Άξονας', SD_Eval::axis_label( $plan->axis ) ),
					array( 'Σκοπός (500 χαρ.)', $plan->purpose ),
					array( 'Τεκμηρίωση αναγκαιότητας (500 χαρ.)', $plan->need ),
					array( 'Πηγές τεκμηρίωσης', implode( "\n", $src ) ),
					array( 'Πράξη Συλλόγου Διδασκόντων', $plan->praxi ),
					array( 'Συνέχεια προηγούμενου σχεδίου δράσης', SD_Eval::YES_NO[ $plan->continuation ] ?? '' ),
					array( 'Συνεργασία με άλλο σχολείο', ( SD_Eval::YES_NO[ $plan->collab ] ?? '' ) . ( $plan->collab_note ? ': ' . $plan->collab_note : '' ) ),
					array( 'Ομάδα Δράσης', 'Συντονιστής/τρια: ' . ( $plan->coordinator_id ? self::user_name( $plan->coordinator_id ) : '' ) . "\nΜέλη: " . implode( ', ', $names ) ),
				),
			),
			array( 'h', 'Στοχοθεσία' ),
			array( 'p', 'Κάθε σχέδιο δράσης περιλαμβάνει έως 3 στόχους. Κάθε στόχος περιλαμβάνει 1–2 ενέργειες υλοποίησης και 1 ενέργεια αποτίμησης.' ),
		);
		foreach ( self::goals( $plan->id ) as $i => $g ) {
			$rows     = array( array( 'Περιγραφή στόχου', $g->description ) );
			$impl     = array();
			$eval     = array();
			$criteria = array();
			foreach ( self::actions( $g->id ) as $a ) {
				if ( 'apotimisi' === $a->kind ) {
					$eval[] = $a;
					if ( '' !== trim( $a->criterion ) ) {
						$criteria[] = $a->criterion;
					}
				} else {
					$impl[] = $a;
				}
			}
			$rows[] = array( 'Ενέργειες υλοποίησης', '' );
			foreach ( $impl as $k => $a ) {
				$rows[] = array( 'Περιγραφή ενέργειας ' . ( $k + 1 ), $a->description );
				$rows[] = array( 'Ημερομηνία έναρξης – λήξης', self::fmt_date( $a->start_date ) . ' – ' . self::fmt_date( $a->end_date ) );
			}
			$rows[] = array( 'Ενέργειες αποτίμησης', '' );
			foreach ( $eval as $k => $a ) {
				$rows[] = array( 'Περιγραφή ενέργειας ' . ( $k + 1 ), $a->description );
				$rows[] = array( 'Ημερομηνία έναρξης – λήξης', self::fmt_date( $a->start_date ) . ' – ' . self::fmt_date( $a->end_date ) );
			}
			$rows[] = array( 'Κριτήρια επιτυχίας', implode( "\n", $criteria ) );
			$rows[] = array( 'Εργαλεία', self::boxes( SD_Eval::TOOLS, $g->tools, $g->tools_other ) );
			$rows[] = array( 'Τρόπος δουλειάς', self::boxes( SD_Eval::WAYS, $g->ways, $g->ways_other ) );
			$b[]    = array( 'h', 'Στόχος ' . ( $i + 1 ) . ( $g->title ? ': ' . $g->title : '' ) );
			$b[]    = array( 'kv', $rows );
		}
		return $b;
	}

	/** Τα κείμενα του σχεδίου, έτοιμα για αντιγραφή στην πλατφόρμα του ΙΕΠ. */
	private static function render_copy( $plan_id ) {
		$plan = self::get_plan( $plan_id );
		if ( ! $plan ) {
			echo '<p class="sd-notice sd-error">Το σχέδιο δεν βρέθηκε.</p>';
			return;
		}
		echo '<p><a href="' . esc_url( SD_Frontend::url( 'sxedia', array( 'id' => $plan->id ) ) ) . '">← Πίσω στο σχέδιο</a></p>';
		echo '<h2>Κείμενα για την πλατφόρμα: ' . esc_html( trim( $plan->code . ' ' . $plan->title ) ) . '</h2>';
		echo '<p class="sd-help">Πατήστε «Αντιγραφή» και επικολλήστε στο αντίστοιχο πεδίο της πλατφόρμας. Η εφαρμογή δεν υποβάλλει τίποτα μόνη της.</p>';
		$fields = array(
			'Τίτλος'          => $plan->title,
			'Σκοπός'                   => $plan->purpose,
			'Τεκμηρίωση αναγκαιότητας' => $plan->need,
		);
		foreach ( self::goals( $plan->id ) as $i => $g ) {
			$n = 'Στόχος ' . ( $i + 1 );
			$fields[ $n . ' – διατύπωση' ] = $g->description;
			$fields[ $n . ' – εργαλεία' ]  = implode( ', ', self::labels( $g->tools, SD_Eval::TOOLS, $g->tools_other ) );
			$fields[ $n . ' – τρόπος εργασίας' ] = implode( ', ', self::labels( $g->ways, SD_Eval::WAYS, $g->ways_other ) );
			foreach ( self::actions( $g->id ) as $j => $a ) {
				$k = $n . ' – ενέργεια ' . ( $j + 1 ) . ' (' . ( SD_Eval::ACTION_KINDS[ $a->kind ] ?? $a->kind ) . ', ' . self::fmt_date( $a->start_date ) . ' – ' . self::fmt_date( $a->end_date ) . ')';
				$fields[ $k ] = $a->description;
				if ( '' !== trim( $a->criterion ) ) {
					$fields[ $n . ' – κριτήριο επιτυχίας ενέργειας ' . ( $j + 1 ) ] = $a->criterion;
				}
			}
		}
		foreach ( $fields as $label => $text ) {
			$len = SD_Eval::length( $text );
			echo '<div class="sd-copy"><div class="sd-copy-head"><strong>' . esc_html( $label ) . '</strong> <span class="sd-meta">' . (int) $len . ' χαρ.</span>';
			echo ' <button type="button" class="sd-button sd-small" data-copy>Αντιγραφή</button></div>';
			echo '<textarea readonly rows="' . (int) min( 8, max( 2, ceil( $len / 90 ) ) ) . '">' . esc_textarea( $text ) . '</textarea></div>';
		}
	}
}
