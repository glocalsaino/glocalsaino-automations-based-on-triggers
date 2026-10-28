<?php
/**
 * Reads CSV and Excel (.xlsx) files into rows of column => value, without external libraries.
 *
 * @package GlocalSainoAutomations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSAAT_File_Reader {

	/**
	 * Accepted extensions.
	 *
	 * @return string[]
	 */
	public static function extensions() {
		return array( 'csv', 'txt', 'xlsx' );
	}

	/**
	 * Read a file.
	 *
	 * @param string $path      File path.
	 * @param string $name      Original file name (for the extension).
	 * @param int    $max_rows  Maximum data rows (0 = no limit).
	 * @return array|WP_Error { columns: { key => label }, rows: array[], truncated: bool }
	 */
	public static function read( $path, $name, $max_rows = 0 ) {
		$extension = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::extensions(), true ) ) {
			return self::error( 'badtype', __( 'Only CSV and Excel (.xlsx) files are accepted. Save .xls files as .xlsx or .csv first.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		if ( ! is_readable( $path ) ) {
			return self::error( 'unreadable', __( 'The file could not be read.', 'glocalsaino-automations-based-on-triggers' ) );
		}

		$table = 'xlsx' === $extension ? self::read_xlsx( $path, $max_rows ) : self::read_csv( $path, $max_rows );
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		return self::with_header( $table['rows'], $table['truncated'] );
	}

	/**
	 * First row → column keys / labels; the others → rows keyed by column.
	 *
	 * @param array $matrix    Rows as lists of cells (first one is the header).
	 * @param bool  $truncated Whether rows were cut by the limit.
	 * @return array|WP_Error
	 */
	private static function with_header( array $matrix, $truncated ) {
		// Ignore empty lines before the header.
		while ( $matrix && ! array_filter( array_map( 'trim', $matrix[0] ), 'strlen' ) ) {
			array_shift( $matrix );
		}
		if ( ! $matrix ) {
			return self::error( 'empty', __( 'The file is empty.', 'glocalsaino-automations-based-on-triggers' ) );
		}

		$header  = array_shift( $matrix );
		$columns = self::column_keys( $header );
		$rows    = array();
		foreach ( $matrix as $cells ) {
			if ( ! array_filter( array_map( 'trim', array_map( 'strval', $cells ) ), 'strlen' ) ) {
				continue; // Skip blank lines.
			}
			$row = array();
			$i   = 0;
			foreach ( $columns as $key => $label ) {
				$row[ $key ] = isset( $cells[ $i ] ) ? trim( (string) $cells[ $i ] ) : '';
				++$i;
			}
			$rows[] = $row;
		}
		return array(
			'columns'   => $columns,
			'rows'      => $rows,
			'truncated' => $truncated,
		);
	}

	/**
	 * Turn header labels into unique field names: "Fecha de alta" → fecha_de_alta.
	 *
	 * @param array $header Header cells.
	 * @return array key => label
	 */
	public static function column_keys( array $header ) {
		$columns = array();
		foreach ( array_values( $header ) as $i => $label ) {
			$label = trim( (string) $label );
			$key   = self::key_from_label( $label );
			if ( '' === $key ) {
				$key = 'column_' . ( $i + 1 );
			}
			$base = $key;
			$n    = 2;
			while ( isset( $columns[ $key ] ) ) {
				$key = $base . '_' . $n++;
			}
			$columns[ $key ] = '' !== $label ? $label : $key;
		}
		return $columns;
	}

	public static function key_from_label( $label ) {
		$key = function_exists( 'remove_accents' ) ? remove_accents( (string) $label ) : (string) $label;
		$key = strtolower( preg_replace( '/[^A-Za-z0-9]+/', '_', $key ) );
		return substr( trim( $key, '_' ), 0, 64 );
	}

	/*
	 * ---------------------------------------------------------------------
	 * CSV
	 * ---------------------------------------------------------------------
	 */

	private static function read_csv( $path, $max_rows ) {
		$content = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
		if ( false !== strpos( $content, "\0" ) ) {
			return self::error( 'badtype', __( 'This does not look like a CSV file.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		$content = self::to_utf8( preg_replace( '/^\xEF\xBB\xBF/', '', $content ) );
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );

		$first     = strtok( $content, "\n" );
		$delimiter = self::detect_delimiter( false === $first ? '' : $first );

		$rows      = array();
		$truncated = false;
		$handle    = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		fwrite( $handle, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory stream.
		rewind( $handle );
		while ( false !== ( $cells = fgetcsv( $handle, 0, $delimiter, '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( $max_rows > 0 && count( $rows ) > $max_rows ) {
				$truncated = true;
				break;
			}
			$rows[] = null === $cells ? array() : $cells;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
		return array(
			'rows'      => $rows,
			'truncated' => $truncated,
		);
	}

	/**
	 * The most frequent of , ; tab | in the header line.
	 *
	 * @param string $line Header line.
	 * @return string
	 */
	public static function detect_delimiter( $line ) {
		$best  = ',';
		$count = 0;
		foreach ( array( ',', ';', "\t", '|' ) as $candidate ) {
			$n = substr_count( $line, $candidate );
			if ( $n > $count ) {
				$best  = $candidate;
				$count = $n;
			}
		}
		return $best;
	}

	/**
	 * Convert Windows-1252 / Latin-1 text (common in spreadsheets saved as CSV) to UTF-8.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function to_utf8( $text ) {
		if ( preg_match( '//u', $text ) ) {
			return $text;
		}
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
		}
		if ( function_exists( 'iconv' ) ) {
			$converted = iconv( 'Windows-1252', 'UTF-8//IGNORE', $text );
			if ( false !== $converted ) {
				return $converted;
			}
		}
		return wp_check_invalid_utf8( $text, true );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Excel (.xlsx = zip of XML files). Reads the first worksheet.
	 * ---------------------------------------------------------------------
	 */

	private static function read_xlsx( $path, $max_rows ) {
		if ( ! class_exists( 'ZipArchive' ) || ! function_exists( 'simplexml_load_string' ) ) {
			return self::error( 'noexcel', __( 'This server cannot read Excel files (the PHP zip / XML extensions are missing). Save the file as CSV and upload it again.', 'glocalsaino-automations-based-on-triggers' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return self::error( 'badtype', __( 'This does not look like an Excel (.xlsx) file.', 'glocalsaino-automations-based-on-triggers' ) );
		}

		$sheet_path = self::first_sheet_path( $zip );
		$sheet_xml  = $sheet_path ? $zip->getFromName( $sheet_path ) : false;
		if ( false === $sheet_xml ) {
			$zip->close();
			return self::error( 'unreadable', __( 'The Excel file has no worksheet.', 'glocalsaino-automations-based-on-triggers' ) );
		}

		$strings = array();
		$shared  = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false !== $shared ) {
			$xml = self::xml( $shared );
			if ( $xml ) {
				foreach ( $xml->si as $item ) {
					$strings[] = self::rich_text( $item );
				}
			}
		}
		$zip->close();

		$sheet = self::xml( $sheet_xml );
		if ( ! $sheet || ! isset( $sheet->sheetData ) ) {
			return self::error( 'unreadable', __( 'The Excel worksheet could not be read.', 'glocalsaino-automations-based-on-triggers' ) );
		}

		$rows      = array();
		$truncated = false;
		foreach ( $sheet->sheetData->row as $row ) {
			if ( $max_rows > 0 && count( $rows ) > $max_rows ) {
				$truncated = true;
				break;
			}
			$cells = array();
			$next  = 0;
			foreach ( $row->c as $cell ) {
				$index = isset( $cell['r'] ) ? self::column_index( (string) $cell['r'] ) : $next;
				$next  = $index + 1;

				$type  = (string) $cell['t'];
				$value = '';
				if ( 's' === $type ) {
					$position = (int) $cell->v;
					$value    = isset( $strings[ $position ] ) ? $strings[ $position ] : '';
				} elseif ( 'inlineStr' === $type ) {
					$value = self::rich_text( $cell->is );
				} elseif ( 'b' === $type ) {
					$value = '1' === (string) $cell->v ? 'TRUE' : 'FALSE';
				} else {
					$value = (string) $cell->v;
				}
				$cells[ $index ] = $value;
			}
			if ( $cells ) {
				$filled = array_fill( 0, max( array_keys( $cells ) ) + 1, '' );
				$rows[] = array_replace( $filled, $cells );
			} else {
				$rows[] = array();
			}
		}

		return array(
			'rows'      => $rows,
			'truncated' => $truncated,
		);
	}

	private static function first_sheet_path( ZipArchive $zip ) {
		$workbook = self::xml( (string) $zip->getFromName( 'xl/workbook.xml' ) );
		$rels     = self::xml( (string) $zip->getFromName( 'xl/_rels/workbook.xml.rels' ) );
		if ( $workbook && $rels && isset( $workbook->sheets->sheet[0] ) ) {
			$relationship = $workbook->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
			$id           = isset( $relationship['id'] ) ? (string) $relationship['id'] : '';
			foreach ( $rels->Relationship as $rel ) {
				if ( (string) $rel['Id'] === $id ) {
					$target = ltrim( (string) $rel['Target'], '/' );
					return 0 === strpos( $target, 'xl/' ) ? $target : 'xl/' . $target;
				}
			}
		}
		return false !== $zip->locateName( 'xl/worksheets/sheet1.xml' ) ? 'xl/worksheets/sheet1.xml' : '';
	}

	private static function xml( $content ) {
		if ( '' === $content ) {
			return false;
		}
		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( $content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $xml;
	}

	/**
	 * Text of a shared / inline string, joining rich-text runs.
	 *
	 * @param SimpleXMLElement|null $node <si> or <is> node.
	 * @return string
	 */
	private static function rich_text( $node ) {
		if ( ! $node ) {
			return '';
		}
		if ( isset( $node->t ) ) {
			return (string) $node->t;
		}
		$text = '';
		foreach ( $node->r as $run ) {
			$text .= (string) $run->t;
		}
		return $text;
	}

	/**
	 * "C7" → 2 (zero-based column).
	 *
	 * @param string $reference Cell reference.
	 * @return int
	 */
	public static function column_index( $reference ) {
		$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $reference ) );
		$index   = 0;
		for ( $i = 0, $n = strlen( $letters ); $i < $n; $i++ ) {
			$index = $index * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return max( 0, $index - 1 );
	}

	private static function error( $code, $message ) {
		return new WP_Error( 'glocalsaino_aat_file_' . $code, $message );
	}
}
