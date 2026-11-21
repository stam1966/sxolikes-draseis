<?php
/**
 * Εξαγωγές: κάθε έντυπο σε Word (.docx) και όλοι οι πίνακες της χρονιάς σε Excel (.xlsx).
 * Λήψη με σύνδεσμο ?sd_export=…&id=…&_wpnonce=… (έλεγχος σύνδεσης, δικαιώματος, nonce).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Exports {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'download' ), 3 );
	}

	public static function url( $type, $id ) {
		return wp_nonce_url( add_query_arg( array( 'sd_export' => $type, 'id' => (int) $id ), SD_Install::app_url() ), 'sd_export_' . $type . '_' . (int) $id );
	}

	private static function slug( $s ) {
		$s = preg_replace( '/[^\p{L}\p{N}]+/u', '-', (string) $s );
		return trim( mb_substr( $s, 0, 60 ), '-' );
	}

	public static function download() {
		if ( empty( $_GET['sd_export'] ) ) {
			return;
		}
		$type = sanitize_key( wp_unslash( $_GET['sd_export'] ) );
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! is_user_logged_in() || ! current_user_can( SD_Roles::CAP_ACCESS ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'sd_export_' . $type . '_' . $id ) ) {
			wp_die( 'Η λήψη δεν επιτρέπεται.', 403 );
		}
		$deny = function () {
			wp_die( 'Δεν έχετε πρόσβαση σε αυτό το έντυπο.', 403 );
		};
		$year = SD_Data::active_year();
		$y    = $year ? $year->label : '';

		switch ( $type ) {
			case 'f1a':
				$class = SD_Data::get_class( $id );
				if ( ! $class || ! SD_Workshops::can_view( $class ) ) {
					$deny();
				}
				SD_Office::send_docx( self::slug( 'Εντυπο-1Α-' . SD_Data::class_label( $class ) . '-' . $y ), SD_Workshops::doc_blocks( $class ), true );
				break;
			case 'f2':
				$dep   = SD_Citizen::get( $id );
				$class = $dep ? SD_Data::get_class( $dep->class_id ) : null;
				if ( ! $class || ! SD_Citizen::can_view( $class ) ) {
					$deny();
				}
				SD_Office::send_docx( self::slug( 'Εντυπο-2-' . SD_Data::class_label( $class ) . '-' . $dep->title ), SD_Citizen::doc_blocks( $dep ) );
				break;
			case 'f2p':
				$dep   = SD_Citizen::get( $id );
				$class = $dep ? SD_Data::get_class( $dep->class_id ) : null;
				if ( ! $class || ! SD_Citizen::can_view( $class ) ) {
					$deny();
				}
				SD_Office::send_docx( self::slug( 'Προτυπο-Δρασης-' . SD_Data::class_label( $class ) . '-' . $dep->title ), SD_Citizen::proto_blocks( $dep ) );
				break;
			case 'prog':
			case 'sxtm':
				$class = SD_Data::get_class( $id );
				if ( ! $class || ! SD_Workshops::can_view( $class ) ) {
					$deny();
				}
				if ( 'prog' === $type ) {
					SD_Office::send_docx( self::slug( 'Προγραμματισμος-Εκπαιδευτικων-' . SD_Data::class_label( $class ) . '-' . $y ), SD_ErgPlan::programming_blocks( $class ) );
				}
				SD_Office::send_docx( self::slug( 'Σχεδιο-Δρασης-Τμηματος-' . SD_Data::class_label( $class ) . '-' . $y ), SD_ErgPlan::class_blocks( $class ) );
				break;
			case 'progall':
				if ( ! $year || ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
					$deny();
				}
				$rows = SD_ErgPlan::programming_sheet( $year );
				$head = array_shift( $rows );
				SD_Office::send_docx(
					self::slug( 'Προγραμματισμος-Εκπαιδευτικων-' . $y ),
					array(
						array( 'p', SD_Frontend::school_name(), true ),
						array( 'title', 'Προγραμματισμός Εκπαιδευτικών · Εργαστήρια Δεξιοτήτων ' . $y ),
						array( 'table', $head, $rows, array( 0.08, 0.22, 0.24, 0.3, 0.16 ) ),
					),
					true
				);
				break;
			case 'sxsm':
				if ( ! $year || ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
					$deny();
				}
				SD_Office::send_docx( self::slug( 'Σχεδιο-Δρασης-Σχολικης-Μοναδας-Εργαστηρια-' . $y ), SD_ErgPlan::school_blocks( $year ) );
				break;
			case 'odigos':
				SD_Office::send_docx( self::slug( 'Οδηγος-Σχολικες-Δρασεις' ), SD_Guide::doc_blocks( 1 === $id ) );
				break;
			case 'symvoulos':
				if ( ! $year || ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
					$deny();
				}
				$rows = self::adviser_rows( $year );
				$head = array_shift( $rows );
				$ms   = self::myschool_rows( $year );
				$mh   = array_shift( $ms );
				SD_Office::send_docx(
					self::slug( 'Δρασεις-Ενεργου-Πολιτη-για-Συμβουλο-' . $y ),
					array(
						array( 'p', SD_Frontend::school_name(), true ),
						array( 'title', 'Δράσεις Ενεργού Πολίτη · ' . $y ),
						array( 'h', 'Συνολικός πίνακας' ),
						array( 'table', $head, $rows, array( 0.34, 0.36, 0.1, 0.2 ) ),
						array( 'h', 'Πίνακας για το myschool' ),
						array( 'table', $mh, $ms, array( 0.38, 0.5, 0.12 ) ),
					),
					true
				);
				break;
			case 'f3':
				$r     = SD_Requests::get( $id );
				$class = $r ? SD_Data::get_class( $r->class_id ) : null;
				if ( ! $class || ! SD_Requests::can_view( $class ) ) {
					$deny();
				}
				SD_Office::send_docx( self::slug( 'Εντυπο-3-' . SD_Data::class_label( $class ) . '-' . ( $r->event_date ? $r->event_date : 'αιτημα' ) ), SD_Requests::doc_blocks( $r ) );
				break;
			case 'f4':
				$class = SD_Data::get_class( $id );
				if ( ! $class || ! SD_F4::can_view( $class ) ) {
					$deny();
				}
				SD_Office::send_docx( self::slug( 'Εντυπο-4-' . SD_Data::class_label( $class ) . '-' . $y ), SD_F4::doc_blocks( $class ) );
				break;
			case 'sd':
				$plan = SD_Plans::get_plan( $id );
				if ( ! $plan || ! SD_F4::can_view_plan( $plan ) ) {
					$deny();
				}
				$rows = SD_F4::plan_rows( $plan );
				SD_Office::send_docx(
					self::slug( 'Αποτιμηση-' . $plan->code . '-' . $y ),
					array(
						array( 'p', SD_Frontend::school_name(), true ),
						array( 'title', 'Αποτίμηση ' . trim( $plan->code . ' «' . $plan->title . '»' ) ),
						array( 'p', 'Συμβολή των τάξεων από τα Έντυπα 4 · Σχολικό έτος ' . $y ),
						array( 'table', array( 'Τμήμα', 'Τι προσέφερε η τάξη', 'Τεκμήρια', 'Έντυπο 4' ), $rows, array( 0.1, 0.5, 0.25, 0.15 ), true ),
					),
					true
				);
				break;
			case 'plan':
				$plan = SD_Plans::get_plan( $id );
				if ( ! $plan ) {
					$deny();
				}
				SD_Office::send_docx( self::slug( 'Σχεδιο-Δρασης-' . $plan->code . '-' . $y ), SD_Plans::doc_blocks( $plan ) );
				break;
			case 'xlsx':
				if ( ! $year || ! current_user_can( SD_Roles::CAP_REVIEW ) ) {
					$deny();
				}
				SD_Office::send_xlsx( self::slug( 'Σχολικες-Δρασεις-' . $y ), self::workbook( $year ) );
				break;
			default:
				wp_die( 'Άγνωστη εξαγωγή.', 404 );
		}
	}

	/** Όλοι οι πίνακες της χρονιάς, ένα φύλλο ανά έντυπο. */
	public static function workbook( $year ) {
		$f1a = array( array( 'Τμήμα', 'Κατάσταση 1Α', 'Ενότητα', 'Θεματική', 'Υποθεματική', 'Τίτλος', 'Εβδομάδες', 'Ώρες Ενεργού Πολίτη', 'Παραδοτέο' ) );
		foreach ( SD_Data::classes( $year->id ) as $c ) {
			$form = SD_Workshops::form_of_class( $c->id );
			foreach ( SD_Workshops::units( $form ) as $n => $u ) {
				$f1a[] = array(
					SD_Data::class_label( $c ),
					$form ? SD_Workshops::STATUS[ $form->status ] : 'Δεν έχει ξεκινήσει',
					SD_Workshops::UNITS[ $n ] ?? (string) $n,
					$u->cycle ? SD_Workshops::CYCLES[ $u->cycle ] : '',
					$u->cycle ? SD_Workshops::subtheme( $c->grade, $u->cycle ) : '',
					$u->title,
					(int) $u->weeks,
					(int) $u->citizen_hours,
					$u->deliverable,
				);
			}
		}

		$f2 = array( array( 'Τμήμα', 'Τίτλος', 'Είδος', 'ΚΕΠΕΑ', 'Ώρες (σχεδιασμός)', 'Μετρά για Τοπική Ιστορία/Γεωγραφία', 'Κατάσταση' ) );
		foreach ( SD_Citizen::of_year( $year->id ) as $d ) {
			$c    = SD_Data::get_class( $d->class_id );
			$f2[] = array(
				$c ? SD_Data::class_label( $c ) : '',
				$d->title,
				SD_Citizen::KINDS[ $d->kind ] ?? '',
				$d->kepea ? 'Ναι' . ( $d->kepea_note ? ': ' . $d->kepea_note : '' ) : 'Όχι',
				SD_Citizen::hours_total( $d ),
				$c && SD_Citizen::counts_as_local( $d, $c->grade ) ? 'Ναι' : 'Όχι',
				SD_Workshops::STATUS[ $d->status ] ?? $d->status,
			);
		}

		$sd = array( array( 'Σχέδιο Δράσης', 'Τμήμα', 'Τι προσέφερε η τάξη', 'Τεκμήρια', 'Έντυπο 4' ) );
		foreach ( SD_Plans::plans( $year->id ) as $p ) {
			foreach ( SD_F4::plan_rows( $p ) as $r ) {
				$sd[] = array_merge( array( trim( $p->code . ' ' . $p->title ) ), $r );
			}
		}

		$map = array( array( 'Τμήμα', 'Σημείο', 'Κατηγορία', 'Εποχή', 'Κατάσταση' ) );
		$cats = SD_Map::cats();
		foreach ( SD_Map::of_year( $year->id ) as $p ) {
			$c     = SD_Data::get_class( $p->class_id );
			$map[] = array( $c ? SD_Data::class_label( $c ) : '', $p->name, $cats[ $p->cat ]['label'] ?? '', $p->period, SD_Map::STATUS[ $p->status ] ?? '' );
		}

		return array(
			'myschool'            => self::myschool_rows( $year ),
			'Για τον Σύμβουλο'    => self::adviser_rows( $year ),
			'Προγραμματισμός εκπ.' => SD_ErgPlan::programming_sheet( $year ),
			'Έντυπα 1Α'           => $f1a,
			'Ενεργός Πολίτης (2)' => $f2,
			'Επισκέψεις (3)'      => SD_Requests::sheet( $year->id ),
			'Συμμετοχή (4)'       => SD_F4::myschool_rows( $year->id ),
			'Συμβολή στα ΣΔ (4)'  => $sd,
			'Σημεία χάρτη'        => $map,
		);
	}

	private static function grade_letter( $class ) {
		return preg_replace( '/΄$/u', '', SD_Data::GRADES[ (int) $class->grade ] ?? '' );
	}

	/** Όπως ο πίνακας του myschool: Δράση · Στόχοι (ΣΒΑ) · Τάξη. */
	public static function myschool_rows( $year ) {
		$rows = array( array( 'Δράση', 'Στόχοι', 'Τάξη' ) );
		$list = SD_Citizen::of_year( $year->id );
		usort(
			$list,
			function ( $a, $b ) {
				$ca = SD_Data::get_class( $a->class_id );
				$cb = SD_Data::get_class( $b->class_id );
				return ( $ca ? $ca->grade * 100 : 0 ) - ( $cb ? $cb->grade * 100 : 0 );
			}
		);
		foreach ( $list as $d ) {
			$c      = SD_Data::get_class( $d->class_id );
			$rows[] = array( $d->title, SD_Citizen::sdg_text( $d->sdgs ), $c ? self::grade_letter( $c ) : '' );
		}
		return $rows;
	}

	/** Για τον Σύμβουλο: Θεματική (ΣΒΑ) · Τίτλος · Τάξη · Περίοδος. */
	public static function adviser_rows( $year ) {
		$rows = array( array( 'Θεματική (ΣΒΑ)', 'Τίτλος', 'Τάξη', 'Περίοδος' ) );
		foreach ( SD_Citizen::of_year( $year->id ) as $d ) {
			$c      = SD_Data::get_class( $d->class_id );
			$rows[] = array( SD_Citizen::sdg_text( $d->sdgs ), $d->title, $c ? SD_Data::class_label( $c ) : '', SD_Citizen::period_text( $d ) );
		}
		return $rows;
	}

	/** Τα ίδια μπλοκ του Word, ως HTML για εκτύπωση. */
	public static function print_blocks( array $blocks ) {
		foreach ( $blocks as $b ) {
			switch ( $b[0] ) {
				case 'title':
					echo '<h2>' . esc_html( $b[1] ) . '</h2>';
					break;
				case 'h':
					echo '<h3>' . esc_html( $b[1] ) . '</h3>';
					break;
				case 'p':
					echo '<p>' . ( ! empty( $b[2] ) ? '<strong>' : '' ) . nl2br( esc_html( $b[1] ) ) . ( ! empty( $b[2] ) ? '</strong>' : '' ) . '</p>';
					break;
				case 'kv':
					echo '<table class="sd-print-table"><tbody>';
					foreach ( $b[1] as $kv ) {
						echo '<tr><th>' . esc_html( $kv[0] ) . '</th><td>' . nl2br( esc_html( (string) $kv[1] ) ) . '</td></tr>';
					}
					echo '</tbody></table>';
					break;
				case 'table':
					echo '<table class="sd-print-table"><thead><tr>';
					foreach ( $b[1] as $h ) {
						echo '<th>' . esc_html( $h ) . '</th>';
					}
					echo '</tr></thead><tbody>';
					foreach ( $b[2] as $row ) {
						echo '<tr>';
						foreach ( array_values( $row ) as $i => $v ) {
							$tag = ( 0 === $i && ! empty( $b[4] ) ) ? 'th' : 'td';
							echo '<' . $tag . '>' . nl2br( esc_html( (string) $v ) ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
						}
						echo '</tr>';
					}
					echo '</tbody></table>';
					break;
			}
		}
	}

	/** Οθόνη «Εξαγωγές» για Επιτροπή και Διεύθυνση. */
	public static function render() {
		$year = SD_Data::active_year();
		echo '<h2>Εξαγωγές</h2>';
		if ( ! $year ) {
			return;
		}
		echo '<section class="sd-card"><h3>Όλοι οι πίνακες της χρονιάς σε Excel</h3><p>Ένα αρχείο με φύλλα για: Έντυπα 1Α, Δράσεις Ενεργού Πολίτη, Επισκέψεις (Έντυπο 3), myschool και Συμβολή στα ΣΔ (Έντυπο 4), Σημεία χάρτη.</p>';
		echo '<p><a class="sd-button" href="' . esc_url( self::url( 'xlsx', 0 ) ) . '">Λήψη Excel ' . esc_html( $year->label ) . '</a></p></section>';
		echo '<section class="sd-card"><h3>Δράσεις Ενεργού Πολίτη για τον Σύμβουλο και το myschool</h3><p>Συνολικός πίνακας (θεματική ΣΒΑ, τίτλος, τάξη, περίοδος) και ο πίνακας του myschool (δράση, στόχοι, τάξη), σε ένα Word. Οι ίδιοι πίνακες είναι και στα πρώτα φύλλα του Excel.</p>';
		echo '<p><a class="sd-button" href="' . esc_url( self::url( 'symvoulos', 0 ) ) . '">Λήψη Word για τον Σύμβουλο</a></p></section>';

		echo '<section class="sd-card"><h3>Έντυπα σε Word, ανά τμήμα</h3><table class="sd-table"><thead><tr><th>Τμήμα</th><th>Έντυπο 1Α</th><th>Έντυπα 2</th><th>Έντυπα 3</th><th>Έντυπο 4</th></tr></thead><tbody>';
		$reqs = array();
		foreach ( SD_Requests::of_year( $year->id ) as $r ) {
			$reqs[ (int) $r->class_id ][] = $r;
		}
		foreach ( SD_Data::classes( $year->id ) as $c ) {
			echo '<tr><td><strong>' . esc_html( SD_Data::class_label( $c ) ) . '</strong></td>';
			echo '<td>' . ( SD_Workshops::form_of_class( $c->id ) ? '<a href="' . esc_url( self::url( 'f1a', $c->id ) ) . '">Word</a>' : '—' ) . '</td><td>';
			foreach ( SD_Citizen::of_class( $c->id ) as $d ) {
				echo '<a href="' . esc_url( self::url( 'f2', $d->id ) ) . '">' . esc_html( wp_html_excerpt( $d->title, 30, '…' ) ) . '</a><br>';
			}
			echo '</td><td>';
			foreach ( $reqs[ (int) $c->id ] ?? array() as $r ) {
				echo '<a href="' . esc_url( self::url( 'f3', $r->id ) ) . '">' . esc_html( ( $r->event_date ? mysql2date( 'd/m', $r->event_date ) . ' ' : '' ) . wp_html_excerpt( 'A' === $r->part ? $r->destination : $r->b_person, 24, '…' ) ) . '</a><br>';
			}
			echo '</td><td>' . ( SD_F4::form_of_class( $c->id ) ? '<a href="' . esc_url( self::url( 'f4', $c->id ) ) . '">Word</a>' : '—' ) . '</td></tr>';
		}
		echo '</tbody></table></section>';

		echo '<section class="sd-card"><h3>Εργαστήρια Δεξιοτήτων (υποδείγματα Πλατφόρμας 21+)</h3><ul>';
		echo '<li><a href="' . esc_url( self::url( 'sxsm', 0 ) ) . '">Σχέδιο Δράσης Σχολικής Μονάδας</a> · <a href="' . esc_url( SD_Frontend::url( 'ergastiria', array( 'sxolio' => 1 ) ) ) . '">συμπλήρωση</a></li>';
		echo '<li><a href="' . esc_url( self::url( 'progall', 0 ) ) . '">Προγραμματισμός Εκπαιδευτικών, όλα τα τμήματα</a></li>';
		foreach ( SD_Data::classes( $year->id ) as $c ) {
			echo '<li>Τμήμα ' . esc_html( SD_Data::class_label( $c ) ) . ': <a href="' . esc_url( self::url( 'sxtm', $c->id ) ) . '">Σχέδιο Δράσης Τμήματος</a> · <a href="' . esc_url( self::url( 'prog', $c->id ) ) . '">Προγραμματισμός</a></li>';
		}
		echo '</ul></section>';
		echo '<section class="sd-card"><h3>Σχέδια Δράσης εσωτερικής αξιολόγησης (Δομικά στοιχεία και Στοχοθεσία)</h3><ul>';
		foreach ( SD_Plans::plans( $year->id ) as $p ) {
			echo '<li><a href="' . esc_url( self::url( 'plan', $p->id ) ) . '">' . esc_html( trim( $p->code . ' ' . $p->title ) ) . '</a></li>';
		}
		echo '</ul></section>';
		echo '<section class="sd-card"><h3>Αποτίμηση Σχεδίων Δράσης σε Word</h3><ul>';
		foreach ( SD_Plans::plans( $year->id ) as $p ) {
			echo '<li><a href="' . esc_url( self::url( 'sd', $p->id ) ) . '">' . esc_html( trim( $p->code . ' ' . $p->title ) ) . '</a></li>';
		}
		echo '</ul></section>';
	}
}
