<?php
/**
 * Ο πίνακας του σχολείου για Διεύθυνση και Επιτροπή:
 * - «Περιμένουν τον έλεγχό σας»: λίστα με ένα κουμπί για το καθένα.
 * - Πίνακας τμημάτων × δουλειές της χρονιάς, με χρώμα ανά κατάσταση.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Board {

	/** Ετικέτα κατάστασης: [κλάση, κείμενο]. */
	private static function chip( $status ) {
		switch ( (string) $status ) {
			case 'elegxthike':
				return array( 'ok', 'Ελέγχθηκε' );
			case 'egkrithike':
				return array( 'ok', 'Εγκρίθηκε' );
			case 'ypovlithike':
				return array( 'wait', 'Για έλεγχο' );
			case 'epistrafike':
				return array( 'fix', 'Επιστράφηκε' );
			case 'proxeiro':
				return array( 'todo', 'Σε εξέλιξη' );
		}
		return array( 'none', 'Δεν ξεκίνησε' );
	}

	private static function cell( $cls, $text, $url = '', $sub = '' ) {
		$inner = '<span class="sd-cell sd-cell-' . esc_attr( $cls ) . '">' . esc_html( $text ) . '</span>' . ( $sub ? '<small>' . esc_html( $sub ) . '</small>' : '' );
		return '<td>' . ( $url ? '<a class="sd-cell-link" href="' . esc_url( $url ) . '">' . $inner . '</a>' : $inner ) . '</td>';
	}

	/** «Περιμένουν τον έλεγχό σας». */
	public static function inbox() {
		$items = SD_Notify::pending_for_user();
		echo '<section class="sd-inbox" aria-label="Περιμένουν τον έλεγχό σας"><div class="sd-inbox-head"><h2>Περιμένουν τον έλεγχό σας</h2><span class="sd-inbox-n">' . (int) count( $items ) . '</span></div>';
		if ( ! $items ) {
			echo '<p class="sd-allgood">Δεν περιμένει κάτι τον έλεγχό σας.</p></section>';
			return;
		}
		echo '<ul class="sd-inbox-list">';
		foreach ( $items as $it ) {
			$parts = explode( ' · ', $it[0], 2 );
			$url   = $it[1];
			if ( false === strpos( $url, 'aitimata' ) && false === strpos( $url, 'xartis' ) ) {
				$url .= '#apofasi';
			}
			echo '<li><span class="sd-inbox-ico" aria-hidden="true">' . SD_Docs::icon( 'eye' ) . '</span><span class="sd-inbox-txt"><strong>' . esc_html( $parts[0] ) . '</strong>' . ( isset( $parts[1] ) ? '<small>' . esc_html( $parts[1] ) . '</small>' : '' ) . '</span><a class="sd-button sd-small" href="' . esc_url( $url ) . '">Ελέγξτε το</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul></section>';
	}

	/** Πίνακας τμημάτων × δουλειές. */
	public static function matrix( $year ) {
		global $wpdb;
		$classes = SD_Data::classes( $year->id );
		echo '<section class="sd-board"><div class="sd-board-head"><h2>Ο πίνακας του σχολείου</h2><p>Κάθε γραμμή ένα τμήμα. Πατήστε ένα κελί για να ανοίξει.</p></div>';
		if ( ! $classes ) {
			echo '<p>Δεν έχουν οριστεί τμήματα. <a href="' . esc_url( SD_Frontend::url( 'taxeis' ) ) . '">Τμήματα</a></p></section>';
			return;
		}
		$reqs = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, class_id, status FROM ' . SD_Requests::t() . ' WHERE year_id = %d', $year->id ) ) as $r ) {
			$reqs[ (int) $r->class_id ][] = $r;
		}
		$tot = array( 'f1a' => 0, 'prog' => 0, 'f2' => 0, 'f4' => 0 );
		echo '<div class="sd-table-wrap"><table class="sd-table sd-board-table"><thead><tr><th>Τμήμα</th><th>Εργαστήρια<br><small>Έντυπο 1Α</small></th><th>Προγραμματισμός</th><th>Ενεργός Πολίτης<br><small>Έντυπο 2</small></th><th>Επισκέψεις<br><small>Έντυπο 3</small></th><th>Απολογισμός<br><small>Έντυπο 4</small></th></tr></thead><tbody>';
		foreach ( $classes as $c ) {
			$label = SD_Data::class_label( $c );
			$lead  = array();
			foreach ( SD_Data::class_teachers( $c->id ) as $t ) {
				$u = get_userdata( $t->user_id );
				if ( $u && 'ergastiria' === $t->class_role ) {
					$lead[] = $u->display_name;
				}
			}
			echo '<tr><th scope="row"><strong>' . esc_html( $label ) . '</strong>' . ( $lead ? '<small>' . esc_html( implode( ', ', $lead ) ) . '</small>' : '<small class="sd-warn-txt">χωρίς υπεύθυνο/η</small>' ) . '</th>';

			$f = SD_Workshops::form_of_class( $c->id );
			list( $cls, $txt ) = self::chip( $f ? $f->status : '' );
			$tot['f1a']       += in_array( $cls, array( 'ok', 'wait' ), true ) ? 1 : 0;
			echo self::cell( $cls, $txt, SD_Frontend::url( 'ergastiria', array( 'class' => $c->id ) ) . ( 'wait' === $cls ? '#apofasi' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput

			$rows  = SD_ErgPlan::programming_rows( $c );
			$title = 0;
			foreach ( $rows as $r ) {
				$title += '' !== $r['title'] ? 1 : 0;
			}
			$tot['prog'] += 4 === $title ? 1 : 0;
			echo self::cell( 4 === $title ? 'ok' : ( $title ? 'todo' : 'none' ), $title . ' / 4 τίτλοι', SD_Exports::preview_url( 'prog', $c->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput

			$deps = SD_Citizen::of_class( $c->id );
			if ( ! $deps ) {
				echo self::cell( 'none', 'Καμία δράση', SD_Frontend::url( 'politis' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				$order = array( 'fix' => 0, 'wait' => 1, 'todo' => 2, 'ok' => 3, 'none' => 4 );
				$worst = null;
				foreach ( $deps as $d ) {
					$ch = self::chip( $d->status );
					if ( ! $worst || $order[ $ch[0] ] < $order[ $worst[0] ] ) {
						$worst    = $ch;
						$worst[2] = $d;
					}
				}
				$tot['f2'] += 'ok' === $worst[0] || 'wait' === $worst[0] ? 1 : 0;
				echo self::cell( $worst[0], $worst[1], SD_Frontend::url( 'politis', array( 'id' => $worst[2]->id ) ) . ( 'wait' === $worst[0] ? '#apofasi' : '' ), count( $deps ) > 1 ? count( $deps ) . ' δράσεις' : wp_html_excerpt( $worst[2]->title, 28, '…' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}

			$list = $reqs[ (int) $c->id ] ?? array();
			if ( ! $list ) {
				echo self::cell( 'none', '—' ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				$pend = 0;
				$ok   = 0;
				foreach ( $list as $r ) {
					$pend += 'ypovlithike' === $r->status ? 1 : 0;
					$ok   += 'egkrithike' === $r->status ? 1 : 0;
				}
				echo self::cell( $pend ? 'wait' : ( $ok === count( $list ) ? 'ok' : 'todo' ), $pend ? $pend . ' για πράξη' : $ok . ' / ' . count( $list ) . ' εγκρίθηκαν', SD_Frontend::url( 'aitimata' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}

			$g = SD_F4::form_of_class( $c->id );
			list( $cls, $txt ) = self::chip( $g ? $g->status : '' );
			$tot['f4']        += in_array( $cls, array( 'ok', 'wait' ), true ) ? 1 : 0;
			echo self::cell( $g ? $cls : 'none', $g ? $txt : 'Τον Ιούνιο', SD_Frontend::url( 'apotimisi', array( 'class' => $c->id ) ) . ( 'wait' === $cls ? '#apofasi' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</tr>';
		}
		$n = count( $classes );
		echo '</tbody><tfoot><tr><th scope="row">Σύνολο</th><td>' . (int) $tot['f1a'] . ' / ' . $n . ' υποβλήθηκαν</td><td>' . (int) $tot['prog'] . ' / ' . $n . ' πλήρεις</td><td>' . (int) $tot['f2'] . ' / ' . $n . ' υποβλήθηκαν</td><td></td><td>' . (int) $tot['f4'] . ' / ' . $n . '</td></tr></tfoot></table></div>';
		echo '<p class="sd-legend-cells"><span class="sd-cell sd-cell-ok">Ολοκληρώθηκε</span> <span class="sd-cell sd-cell-wait">Για έλεγχο</span> <span class="sd-cell sd-cell-fix">Επιστράφηκε</span> <span class="sd-cell sd-cell-todo">Σε εξέλιξη</span> <span class="sd-cell sd-cell-none">Δεν ξεκίνησε</span></p>';
		echo '<p class="sd-board-acts"><span class="sd-meta">Για όλο το σχολείο:</span> ' . SD_Docs::actions( 'progall', 0, 'Προγραμματισμός (Word)' ) . ' <a class="sd-act" href="' . esc_url( SD_Exports::url( 'xlsx', 0 ) ) . '">' . SD_Docs::icon( 'word' ) . 'Excel της χρονιάς</a> <a class="sd-act" href="' . esc_url( SD_Frontend::url( 'exagoges' ) ) . '">Όλες οι εξαγωγές</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</section>';
	}
}
