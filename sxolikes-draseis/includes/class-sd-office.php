<?php
/**
 * Μικρός συντάκτης αρχείων Word (.docx) και Excel (.xlsx), χωρίς εξωτερικές βιβλιοθήκες.
 *
 * Έγγραφο Word = λίστα από «μπλοκ»:
 *   array( 'title', 'κείμενο' )            μεγάλος τίτλος
 *   array( 'h', 'κείμενο' )                επικεφαλίδα ενότητας
 *   array( 'p', 'κείμενο' [, 'bold'] )     παράγραφος (οι αλλαγές γραμμής κρατιούνται)
 *   array( 'kv', array( array( k, v ), … ) )   πίνακας δύο στηλών (ετικέτα – τιμή)
 *   array( 'table', array( κεφαλίδες ), array( array( … ), … ) )
 * Excel = array( 'Όνομα φύλλου' => array( array( κεφαλίδες ), array( … ), … ), … ).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SD_Office {

	/* =================== Κοινά =================== */

	private static function x( $s ) {
		// Αφαιρεί χαρακτήρες ελέγχου που δεν επιτρέπονται σε XML.
		$s = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $s );
		return htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/** Φτιάχνει zip από [διαδρομή => περιεχόμενο]. ZipArchive αν υπάρχει, αλλιώς PclZip του WordPress. */
	private static function zip( array $files ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( 'sd-office' );
		if ( class_exists( 'ZipArchive' ) ) {
			$z = new ZipArchive();
			if ( true !== $z->open( $tmp, ZipArchive::OVERWRITE ) ) {
				return new WP_Error( 'sd', 'Δεν δημιουργήθηκε το αρχείο.' );
			}
			foreach ( $files as $path => $content ) {
				$z->addFromString( $path, $content );
			}
			$z->close();
		} else {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
			$dir = trailingslashit( get_temp_dir() ) . 'sd-office-' . wp_generate_password( 8, false );
			wp_mkdir_p( $dir );
			$list = array();
			foreach ( $files as $path => $content ) {
				$full = $dir . '/' . $path;
				wp_mkdir_p( dirname( $full ) );
				file_put_contents( $full, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$list[] = $full;
			}
			$z = new PclZip( $tmp );
			$z->create( $list, PCLZIP_OPT_REMOVE_PATH, $dir );
		}
		$data = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return $data;
	}

	/** Στέλνει το αρχείο στον browser και τερματίζει. */
	public static function send( $filename, $mime, $data ) {
		if ( is_wp_error( $data ) ) {
			wp_die( esc_html( $data->get_error_message() ) );
		}
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . strlen( $data ) );
		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput -- δυαδικό αρχείο
		exit;
	}

	/* =================== Word =================== */

	private static function run( $text, $bold = false, $size = 0 ) {
		$props = ( $bold ? '<w:b/>' : '' ) . ( $size ? '<w:sz w:val="' . (int) $size . '"/><w:szCs w:val="' . (int) $size . '"/>' : '' );
		$out   = '';
		$lines = preg_split( '/\R/u', (string) $text );
		foreach ( $lines as $i => $line ) {
			if ( $i ) {
				$out .= '<w:r><w:br/></w:r>';
			}
			$out .= '<w:r>' . ( $props ? '<w:rPr>' . $props . '</w:rPr>' : '' ) . '<w:t xml:space="preserve">' . self::x( $line ) . '</w:t></w:r>';
		}
		return $out;
	}

	private static function para( $text, $style = '', $bold = false ) {
		return '<w:p>' . ( $style ? '<w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr>' : '' ) . self::run( $text, $bold ) . '</w:p>';
	}

	private static function cell( $text, $width, $header = false ) {
		return '<w:tc><w:tcPr><w:tcW w:w="' . (int) $width . '" w:type="dxa"/>' . ( $header ? '<w:shd w:val="clear" w:color="auto" w:fill="E8EEF5"/>' : '' ) . '</w:tcPr>'
			. '<w:p><w:pPr><w:spacing w:before="40" w:after="40"/></w:pPr>' . self::run( $text, $header ) . '</w:p></w:tc>';
	}

	private static function table( array $rows, array $widths, $first_row_header, $first_col_header = false ) {
		$grid = '';
		foreach ( $widths as $w ) {
			$grid .= '<w:gridCol w:w="' . (int) $w . '"/>';
		}
		$x = '<w:tbl><w:tblPr><w:tblStyle w:val="SDTable"/><w:tblW w:w="0" w:type="auto"/><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>';
		foreach ( $rows as $ri => $row ) {
			$x .= '<w:tr><w:trPr><w:cantSplit/>' . ( 0 === $ri && $first_row_header ? '<w:tblHeader/>' : '' ) . '</w:trPr>';
			foreach ( array_values( $row ) as $ci => $val ) {
				$x .= self::cell( $val, $widths[ $ci ] ?? 2000, ( 0 === $ri && $first_row_header ) || ( 0 === $ci && $first_col_header ) );
			}
			$x .= '</w:tr>';
		}
		return $x . '</w:tbl>' . self::para( '' );
	}

	/**
	 * @param array $blocks     μπλοκ (βλ. πάνω).
	 * @param bool  $landscape  οριζόντια σελίδα (για πίνακες με πολλές στήλες).
	 */
	public static function docx( array $blocks, $landscape = false ) {
		$page_w = $landscape ? 16838 : 11906;
		$page_h = $landscape ? 11906 : 16838;
		$usable = $page_w - 2 * 1000;
		$body   = '';
		foreach ( $blocks as $b ) {
			switch ( $b[0] ) {
				case 'title':
					$body .= self::para( $b[1], 'Title' );
					break;
				case 'h':
					$body .= self::para( $b[1], 'Heading1' );
					break;
				case 'p':
					$body .= self::para( $b[1], '', ! empty( $b[2] ) );
					break;
				case 'kv':
					$rows = array();
					foreach ( $b[1] as $kv ) {
						$rows[] = array( (string) $kv[0], (string) $kv[1] );
					}
					if ( $rows ) {
						$body .= self::table( $rows, array( (int) ( $usable * 0.3 ), (int) ( $usable * 0.7 ) ), false, true );
					}
					break;
				case 'table':
					$head = $b[1];
					$n    = max( 1, count( $head ) );
					$ws   = isset( $b[3] ) && is_array( $b[3] ) ? array_map(
						function ( $f ) use ( $usable ) {
							return (int) ( $usable * $f );
						},
						$b[3]
					) : array_fill( 0, $n, (int) ( $usable / $n ) );
					$body .= self::table( array_merge( array( $head ), $b[2] ), $ws, true, ! empty( $b[4] ) );
					break;
			}
		}
		$sect = '<w:sectPr><w:pgSz w:w="' . $page_w . '" w:h="' . $page_h . '"' . ( $landscape ? ' w:orient="landscape"' : '' ) . '/><w:pgMar w:top="900" w:right="1000" w:bottom="900" w:left="1000" w:header="500" w:footer="500" w:gutter="0"/></w:sectPr>';
		$doc  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . $sect . '</w:body></w:document>';

		$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
			. '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri" w:eastAsia="Calibri"/><w:sz w:val="20"/><w:szCs w:val="20"/><w:lang w:val="el-GR"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="80" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
			. '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
			. '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="120"/></w:pPr><w:rPr><w:b/><w:sz w:val="30"/><w:szCs w:val="30"/><w:color w:val="1D5FA8"/></w:rPr></w:style>'
			. '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="200" w:after="80"/></w:pPr><w:rPr><w:b/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:style>'
			. '<w:style w:type="table" w:styleId="SDTable"><w:name w:val="SD Table"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="9AA5B1"/><w:left w:val="single" w:sz="4" w:color="9AA5B1"/><w:bottom w:val="single" w:sz="4" w:color="9AA5B1"/><w:right w:val="single" w:sz="4" w:color="9AA5B1"/><w:insideH w:val="single" w:sz="4" w:color="9AA5B1"/><w:insideV w:val="single" w:sz="4" w:color="9AA5B1"/></w:tblBorders><w:tblCellMar><w:left w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>'
			. '</w:styles>';

		return self::zip(
			array(
				'[Content_Types].xml'          => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
				'_rels/.rels'                  => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
				'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
				'word/document.xml'            => $doc,
				'word/styles.xml'              => $styles,
			)
		);
	}

	/* =================== Excel =================== */

	private static function col( $i ) {
		$s = '';
		for ( $i++; $i > 0; $i = intdiv( $i - 1, 26 ) ) {
			$s = chr( 65 + ( $i - 1 ) % 26 ) . $s;
		}
		return $s;
	}

	public static function xlsx( array $sheets ) {
		$files = array();
		$wb    = '';
		$rels  = '';
		$ct    = '';
		$n     = 0;
		$used  = array();
		foreach ( $sheets as $name => $rows ) {
			$n++;
			$clean = mb_substr( preg_replace( '/[\[\]\:\*\?\/\\\\]/u', ' ', (string) $name ), 0, 31 );
			while ( isset( $used[ $clean ] ) ) {
				$clean = mb_substr( $clean, 0, 28 ) . ' ' . $n;
			}
			$used[ $clean ] = true;

			$widths = array();
			$data   = '';
			foreach ( array_values( $rows ) as $ri => $row ) {
				$data .= '<row r="' . ( $ri + 1 ) . '">';
				foreach ( array_values( $row ) as $ci => $val ) {
					$ref  = self::col( $ci ) . ( $ri + 1 );
					$cell = 0 === $ri ? ' s="1"' : ' s="2"';
					if ( is_int( $val ) || is_float( $val ) ) {
						$data .= '<c r="' . $ref . '"' . $cell . '><v>' . $val . '</v></c>';
					} else {
						$data .= '<c r="' . $ref . '" t="inlineStr"' . $cell . '><is><t xml:space="preserve">' . self::x( $val ) . '</t></is></c>';
					}
					$len           = min( 60, max( 8, mb_strlen( (string) $val ) * 1.1 ) );
					$widths[ $ci ] = max( $widths[ $ci ] ?? 8, $len );
				}
				$data .= '</row>';
			}
			$cols = '';
			foreach ( $widths as $ci => $w ) {
				$cols .= '<col min="' . ( $ci + 1 ) . '" max="' . ( $ci + 1 ) . '" width="' . round( $w, 1 ) . '" customWidth="1"/>';
			}
			$files[ 'xl/worksheets/sheet' . $n . '.xml' ] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' . ( $cols ? '<cols>' . $cols . '</cols>' : '' ) . '<sheetData>' . $data . '</sheetData></worksheet>';
			$wb   .= '<sheet name="' . self::x( $clean ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
			$ct   .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		$rels .= '<Relationship Id="rId' . ( $n + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

		$files['[Content_Types].xml']        = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $ct . '</Types>';
		$files['_rels/.rels']                = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
		$files['xl/workbook.xml']            = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wb . '</sheets></workbook>';
		$files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
		$files['xl/styles.xml']              = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF5"/><bgColor indexed="64"/></patternFill></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
		return self::zip( $files );
	}

	public static function send_docx( $filename, array $blocks, $landscape = false ) {
		self::send( $filename . '.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', self::docx( $blocks, $landscape ) );
	}

	public static function send_xlsx( $filename, array $sheets ) {
		self::send( $filename . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', self::xlsx( $sheets ) );
	}
}
