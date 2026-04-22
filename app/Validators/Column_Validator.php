<?php
/**
 * Validação do cabeçalho da planilha.
 *
 * @package WCSPI
 */

namespace WCSPI\Validators;

use WCSPI\Config\Plugin_Config;

defined( 'ABSPATH' ) || exit;

final class Column_Validator {

	/**
	 * @param array<string, int> $column_map
	 * @return true|\WP_Error
	 */
	public function validate( array $column_map ) {
		$required = Plugin_Config::required_header_keys();
		$missing  = array();
		foreach ( $required as $key ) {
			if ( ! isset( $column_map[ $key ] ) ) {
				$missing[] = $key;
			}
		}
		if ( ! empty( $missing ) ) {
			return new \WP_Error(
				'wcspi_missing_columns',
				sprintf(
					/* translators: %s: comma-separated column keys */
					__( 'Colunas obrigatórias em falta no ficheiro: %s', 'wc-spreadsheet-product-importer' ),
					implode( ', ', $missing )
				)
			);
		}
		return true;
	}
}
