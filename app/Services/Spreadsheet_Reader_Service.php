<?php
/**
 * Leitura de CSV/XLSX via PhpSpreadsheet.
 *
 * @package WCSPI
 */

namespace WCSPI\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

defined( 'ABSPATH' ) || exit;

final class Spreadsheet_Reader_Service {

	/**
	 * @return array{0: array<int, mixed>, 1: list<array<int, mixed>>} [header_row, data_rows]
	 * @throws \Throwable
	 */
	public function read( string $absolute_path ): array {
		$spreadsheet = IOFactory::load( $absolute_path );
		$sheet       = $spreadsheet->getActiveSheet();
		$rows        = $sheet->toArray( null, true, true, false );
		if ( empty( $rows ) ) {
			return array( array(), array() );
		}
		$header = array_shift( $rows );
		$data   = array();
		foreach ( $rows as $r ) {
			if ( $this->is_row_empty( $r ) ) {
				continue;
			}
			$data[] = $r;
		}
		return array( is_array( $header ) ? $header : array(), $data );
	}

	/**
	 * @param array<int, mixed> $row
	 */
	private function is_row_empty( array $row ): bool {
		foreach ( $row as $cell ) {
			if ( null !== $cell && '' !== trim( (string) $cell ) ) {
				return false;
			}
		}
		return true;
	}
}
