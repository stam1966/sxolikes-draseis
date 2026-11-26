<?php
/**
 * Τα έντυπα ως «παράγωγα»: κάρτες-χαρτιά με κατάσταση, πρόοδο και κουμπιά
 * (Δείτε το · Word · Εκτύπωση). Ο/Η εκπαιδευτικός δεν «ανοίγει έντυπο»· κάνει
 * τη δουλειά της τάξης και τα έντυπα γεμίζουν μόνα τους.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Docs {

	const ICONS = array(
		'eye'   => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
		'word'  => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
		'print' => '<path d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2"/><path d="M6 14h12v7H6z"/>',
		'fix'   => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
		'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'trash' => '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/>',
		'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
	);

	public static function icon( $name ) {
		return '<svg class="sd-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . self::ICONS[ $name ] . '</svg>';
	}

	/** Τα τρία κουμπιά ενός εντύπου. */
	public static function actions( $type, $id, $word = 'Word' ) {
		return '<span class="sd-acts">'
			. '<a class="sd-act" href="' . esc_url( SD_Exports::preview_url( $type, $id ) ) . '">' . self::icon( 'eye' ) . 'Δείτε το</a>'
			. '<a class="sd-act" href="' . esc_url( SD_Exports::url( $type, $id ) ) . '">' . self::icon( 'word' ) . esc_html( $word ) . '</a>'
			. '<a class="sd-act" href="' . esc_url( add_query_arg( 'tiposi', 1, SD_Exports::preview_url( $type, $id ) ) ) . '">' . self::icon( 'print' ) . 'Εκτύπωση</a>'
			. '</span>';
	}

	/**
	 * Κάρτα-χαρτί. $d: type, id, title, sub, state (ready|miss|wait|todo), note,
	 * pct (0–100), fix (url για «Συμπληρώστε το»), fix_label.
	 */
	public static function card( $d ) {
		$d     = wp_parse_args( $d, array( 'sub' => '', 'state' => 'ready', 'note' => '', 'pct' => 100, 'fix' => '', 'fix_label' => 'Συμπληρώστε το' ) );
		$pct   = max( 0, min( 100, (int) $d['pct'] ) );
		$html  = '<article class="sd-doc sd-doc-' . esc_attr( $d['state'] ) . '">';
		$html .= '<div class="sd-doc-lines" aria-hidden="true"><i style="width:' . $pct . '%"></i></div>';
		$html .= '<h3>' . esc_html( $d['title'] ) . '</h3>';
		if ( $d['sub'] ) {
			$html .= '<p class="sd-doc-sub">' . esc_html( $d['sub'] ) . '</p>';
		}
		if ( $d['note'] ) {
			$html .= '<span class="sd-state sd-state-' . esc_attr( $d['state'] ) . '">' . esc_html( $d['note'] ) . '</span>';
		}
		$html .= '<div class="sd-doc-foot">';
		if ( $d['fix'] ) {
			$html .= '<a class="sd-act sd-act-primary" href="' . esc_url( $d['fix'] ) . '">' . self::icon( 'fix' ) . esc_html( $d['fix_label'] ) . '</a>';
		}
		$html .= self::actions( $d['type'], $d['id'] );
		$html .= '</div></article>';
		return $html;
	}

	public static function grid( array $cards, $title = '', $intro = '' ) {
		if ( ! $cards ) {
			return '';
		}
		$h = '<section class="sd-docs-wrap sd-noprint">';
		if ( $title ) {
			$h .= '<div class="sd-docs-head"><h3>' . esc_html( $title ) . '</h3>' . ( $intro ? '<p>' . esc_html( $intro ) . '</p>' : '' ) . '</div>';
		}
		return $h . '<div class="sd-docs">' . implode( '', $cards ) . '</div></section>';
	}

	/* =================== Κάρτες ανά έντυπο =================== */

	private static function status_card( $status, $fix_url ) {
		switch ( (string) $status ) {
			case 'elegxthike':
				return array( 'ready', 'Ελέγχθηκε', '' );
			case 'egkrithike':
				return array( 'ready', 'Εγκρίθηκε', '' );
			case 'ypovlithike':
				return array( 'wait', 'Περιμένει έλεγχο', '' );
			case 'epistrafike':
				return array( 'miss', 'Θέλει διόρθωση', $fix_url );
		}
		return array( 'todo', '', $fix_url );
	}

	public static function f1a( $class ) {
		$form   = SD_Workshops::form_of_class( $class->id );
		$units  = SD_Workshops::units( $form );
		$filled = 0;
		foreach ( $units as $u ) {
			$filled += ( $u->cycle && '' !== trim( $u->title ) ) ? 1 : 0;
		}
		list( $k, $v ) = SD_Wizard::first_step( $class );
		$url           = SD_Wizard::url( $class->id, $k, $v );
		list( $state, $note, $fix ) = self::status_card( $form ? $form->status : '', $url );
		if ( 'todo' === $state ) {
			$errors = 0;
			foreach ( SD_Workshops::issues( $units ) as $i ) {
				$errors += 'error' === $i[0] ? 1 : 0;
			}
			$state = $errors ? 'miss' : 'ready';
			$note  = $errors ? ( 1 === $errors ? 'Λείπει 1 στοιχείο' : 'Λείπουν ' . $errors . ' στοιχεία' ) : 'Έτοιμο για υποβολή';
			$fix   = $url;
		}
		return self::card( array( 'type' => 'f1a', 'id' => $class->id, 'title' => 'Έντυπο 1Α', 'sub' => 'Σχεδιασμός Εργαστηρίων', 'state' => $state, 'note' => $note, 'pct' => 25 * $filled, 'fix' => $fix, 'fix_label' => 'ready' === $state && 'Έτοιμο για υποβολή' === $note ? 'Υποβάλετέ το' : 'Συμπληρώστε το' ) );
	}

	public static function prog( $class ) {
		$rows  = SD_ErgPlan::programming_rows( $class );
		$no    = 0;
		foreach ( $rows as $r ) {
			$no += '' === $r['title'] ? 1 : 0;
		}
		$n = count( $rows );
		return self::card(
			array(
				'type'  => 'prog',
				'id'    => $class->id,
				'title' => 'Προγραμματισμός Εκπαιδευτικών',
				'sub'   => 'Υπόδειγμα Πλατφόρμας 21+',
				'state' => $no ? 'miss' : 'ready',
				'note'  => $no ? ( 1 === $no ? 'Λείπει ένας τίτλος' : 'Λείπουν ' . $no . ' τίτλοι' ) : 'Έτοιμο',
				'pct'   => $n ? (int) round( 100 * ( $n - $no ) / $n ) : 0,
				'fix'   => $no ? SD_Wizard::url( $class->id ) : '',
			)
		);
	}

	public static function sxtm( $class ) {
		$vals  = SD_ErgPlan::cycle_values( $class );
		$all   = 0;
		$full  = 0;
		foreach ( $vals as $v ) {
			foreach ( array( 'our_title', 'goals', 'sequence', 'products', 'assessment' ) as $k ) {
				$all++;
				$full += '' !== trim( (string) ( $v[ $k ] ?? '' ) ) ? 1 : 0;
			}
		}
		$pct = $all ? (int) round( 100 * $full / $all ) : 0;
		return self::card( array( 'type' => 'sxtm', 'id' => $class->id, 'title' => 'Σχέδιο Δράσης Τμήματος', 'sub' => 'Υπόδειγμα Πλατφόρμας 21+', 'state' => $pct >= 80 ? 'ready' : 'wait', 'note' => $pct >= 80 ? 'Σχεδόν πλήρες' : 'Συμπληρώνεται σιγά σιγά', 'pct' => $pct ) );
	}

	public static function f2( $dep ) {
		$url = SD_Frontend::url( 'politis', array( 'id' => $dep->id ) );
		list( $state, $note, $fix ) = self::status_card( $dep->status, $url );
		if ( 'todo' === $state ) {
			$state = 'wait';
			$note  = 'Σε εξέλιξη';
		}
		return self::card( array( 'type' => 'f2', 'id' => $dep->id, 'title' => 'Έντυπο 2', 'sub' => '«' . wp_html_excerpt( $dep->title, 60, '…' ) . '»', 'state' => $state, 'note' => $note, 'pct' => 'wait' === $state && 'Σε εξέλιξη' === $note ? 50 : 100, 'fix' => $fix ) );
	}

	public static function f3( $r ) {
		$url = SD_Frontend::url( 'aitimata', array( 'id' => $r->id ) );
		list( $state, $note, $fix ) = self::status_card( $r->status, $url );
		if ( 'todo' === $state ) {
			$miss  = count( SD_Requests::missing( $r ) );
			$state = $miss ? 'miss' : 'ready';
			$note  = $miss ? ( 1 === $miss ? 'Λείπει 1 στοιχείο' : 'Λείπουν ' . $miss . ' στοιχεία' ) : 'Έτοιμο για υποβολή';
		}
		$where = 'A' === $r->part ? $r->destination : $r->b_person;
		return self::card( array( 'type' => 'f3', 'id' => $r->id, 'title' => 'Έντυπο 3', 'sub' => trim( ( $r->event_date ? mysql2date( 'd/m', $r->event_date ) . ' · ' : '' ) . $where ), 'state' => $state, 'note' => $note, 'pct' => 'miss' === $state ? 60 : 100, 'fix' => $fix ) );
	}

	public static function f4( $class ) {
		$f = SD_F4::form_of_class( $class->id );
		if ( ! $f ) {
			return '';
		}
		$url = SD_Frontend::url( 'apotimisi', array( 'class' => $class->id ) );
		list( $state, $note, $fix ) = self::status_card( $f->status, $url );
		if ( 'todo' === $state ) {
			$state = 'wait';
			$note  = 'Σε εξέλιξη';
		}
		return self::card( array( 'type' => 'f4', 'id' => $class->id, 'title' => 'Έντυπο 4', 'sub' => 'Απολογισμός χρονιάς', 'state' => $state, 'note' => $note, 'pct' => 'Σε εξέλιξη' === $note ? 50 : 100, 'fix' => $fix ) );
	}

	/** Όλα τα έντυπα ενός τμήματος. */
	public static function of_class( $class ) {
		global $wpdb;
		$cards = array( self::f1a( $class ), self::prog( $class ), self::sxtm( $class ) );
		foreach ( SD_Citizen::of_class( $class->id ) as $d ) {
			$cards[] = self::f2( $d );
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . SD_Requests::t() . ' WHERE class_id = %d ORDER BY event_date, id', $class->id ) ) as $r ) {
			$cards[] = self::f3( $r );
		}
		$cards[] = self::f4( $class );
		return array_values( array_filter( $cards ) );
	}

	/* =================== Η κορδέλα της χρονιάς =================== */

	/** Θέση του «σήμερα» στη χρονιά (0–100) και ποιο τμήμα της κορδέλας. */
	private static function today_pos() {
		$m = (int) current_time( 'n' );
		$d = (int) current_time( 'j' );
		$f = min( 1, ( $d - 1 ) / 30 );
		// Στήλες: Σεπτ .55 | Οκτ–Νοέ 1 | Δεκ–Ιαν 1 | Φεβ–Μαρ 1 | Απρ–Μάι 1 | Ιούν .45  (σύνολο 5)
		$map = array( 9 => array( 0, .55, 0 ), 10 => array( .55, .5, 1 ), 11 => array( 1.05, .5, 1 ), 12 => array( 1.55, .5, 2 ), 1 => array( 2.05, .5, 2 ), 2 => array( 2.55, .5, 3 ), 3 => array( 3.05, .5, 3 ), 4 => array( 3.55, .5, 4 ), 5 => array( 4.05, .5, 4 ), 6 => array( 4.55, .45, 5 ) );
		if ( ! isset( $map[ $m ] ) ) {
			return array( null, -1 );
		}
		list( $start, $len, $seg ) = $map[ $m ];
		return array( round( 100 * ( $start + $len * $f ) / 5, 1 ), $seg );
	}

	public static function ribbon( $class ) {
		$units = SD_Workshops::units( SD_Workshops::form_of_class( $class->id ) );
		$vals  = SD_ErgPlan::cycle_values( $class );
		list( $pos, $here ) = self::today_pos();
		$h  = '<div class="sd-ribbon" role="list" aria-label="' . esc_attr( 'Η χρονιά του τμήματος ' . SD_Data::class_label( $class ) ) . '">';
		$h .= '<div class="sd-seg sd-seg-edge' . ( 0 === $here ? ' is-here' : '' ) . '" role="listitem"><span class="sd-seg-m">Σεπτ.</span><span class="sd-seg-t">Αρχή</span></div>';
		foreach ( $units as $n => $u ) {
			$months = trim( substr( SD_Workshops::UNITS[ $n ], strpos( SD_Workshops::UNITS[ $n ], '· ' ) + strlen( '· ' ) ) );
			$c      = (int) $u->cycle;
			if ( $c ) {
				$title = '' !== trim( $u->title ) ? $u->title : trim( (string) ( $vals[ $c ]['our_title'] ?? '' ) );
				$state = '' !== $title ? '«' . $title . '»' : 'θέλει τίτλο';
				$name  = SD_Workshops::CYCLES[ $c ];
			} else {
				$state = 'δεν ορίστηκε θεματική';
				$name  = ( $n ) . 'η ενότητα';
			}
			$h .= '<div class="sd-seg' . ( $c ? ' sd-k' . $c : ' sd-seg-empty' ) . ( $n === $here ? ' is-here' : '' ) . '" role="listitem"><span class="sd-seg-m">' . esc_html( $months ) . '</span><span class="sd-seg-t">' . esc_html( $name ) . '</span><span class="sd-seg-s">' . esc_html( $state ) . '</span></div>';
		}
		$h .= '<div class="sd-seg sd-seg-edge' . ( 5 === $here ? ' is-here' : '' ) . '" role="listitem"><span class="sd-seg-m">Ιούν.</span><span class="sd-seg-t">Τέλος</span></div>';
		if ( null !== $pos ) {
			$h .= '<span class="sd-now-mark" style="left:' . esc_attr( $pos ) . '%" aria-hidden="true"><span>Σήμερα</span></span>';
		}
		return $h . '</div>';
	}
}
