<?php
/**
 * Orquestra validação e persistência.
 *
 * @package WCSPI
 */

namespace WCSPI\Services;

use WCSPI\Config\Plugin_Config;
use WCSPI\DTO\Import_Result;
use WCSPI\Helpers\Attribute_Parse_Helper;
use WCSPI\Helpers\Column_Map_Helper;
use WCSPI\Helpers\Product_Type_Helper;
use WCSPI\Repositories\Product_Repository;
use WCSPI\Validators\Column_Validator;
use WCSPI\Validators\Row_Validator;

defined( 'ABSPATH' ) || exit;

final class Import_Service {

	private Spreadsheet_Reader_Service $reader;
	private Column_Validator $column_validator;
	private Row_Validator $row_validator;
	private Product_Repository $repository;

	public function __construct(
		?Spreadsheet_Reader_Service $reader = null,
		?Column_Validator $column_validator = null,
		?Row_Validator $row_validator = null,
		?Product_Repository $repository = null
	) {
		$this->reader            = $reader ?? new Spreadsheet_Reader_Service();
		$this->column_validator  = $column_validator ?? new Column_Validator();
		$this->row_validator     = $row_validator ?? new Row_Validator();
		$this->repository        = $repository ?? new Product_Repository();
	}

	/**
	 * @throws \Throwable
	 * @return Import_Result|\WP_Error
	 */
	public function run( string $absolute_path ) {
		if ( ! is_readable( $absolute_path ) ) {
			return new \WP_Error( 'wcspi_file', __( 'Ficheiro ilegível.', 'wc-spreadsheet-product-importer' ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( Plugin_Config::import_time_limit() );
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		list( $header, $rows ) = $this->reader->read( $absolute_path );
		$map = Column_Map_Helper::map_header_row( $header );
		$ok  = $this->column_validator->validate( $map );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}

		$prepared = $this->prepare_rows( $rows, $map );
		$var_map  = $this->build_variation_value_map( $prepared );

		$result         = new Import_Result();
		$parents_sync   = array();

		foreach ( $prepared as $item ) {
			$line = $item['line'];
			$row  = $item['row'];
			$type = Product_Type_Helper::resolve( $row );

			if ( Product_Type_Helper::VARIATION === $type ) {
				continue;
			}

			$validated = $this->row_validator->validate( $row, $line );
			if ( is_wp_error( $validated ) ) {
				$result->add_error(
					array(
						'line'    => $line,
						'message' => $validated->get_error_message(),
					)
				);
				continue;
			}

			try {
				if ( Product_Type_Helper::VARIABLE === $type ) {
					$psk = wc_clean( $row['sku'] ?? '' );
					$opt = $this->options_list_for_parent( $var_map, $psk );
					$out = $this->repository->upsert_variable_product( $row, $opt );
				} else {
					$out = $this->repository->upsert_simple_product( $row );
				}
			} catch ( \Throwable $e ) {
				$result->add_error(
					array(
						'line'    => $line,
						'message' => sprintf(
							/* translators: 1: line number, 2: error message */
							__( 'Linha %1$d: %2$s', 'wc-spreadsheet-product-importer' ),
							$line,
							$e->getMessage()
						),
					)
				);
				continue;
			}

			$this->apply_result_counts( $result, $out, $row, $line );
		}

		foreach ( $prepared as $item ) {
			$line = $item['line'];
			$row  = $item['row'];
			$type = Product_Type_Helper::resolve( $row );

			if ( Product_Type_Helper::VARIATION !== $type ) {
				continue;
			}

			$validated = $this->row_validator->validate( $row, $line );
			if ( is_wp_error( $validated ) ) {
				$result->add_error(
					array(
						'line'    => $line,
						'message' => $validated->get_error_message(),
					)
				);
				continue;
			}

			try {
				$out = $this->repository->upsert_variation_product( $row );
			} catch ( \Throwable $e ) {
				$result->add_error(
					array(
						'line'    => $line,
						'message' => sprintf(
							/* translators: 1: line number, 2: error message */
							__( 'Linha %1$d: %2$s', 'wc-spreadsheet-product-importer' ),
							$line,
							$e->getMessage()
						),
					)
				);
				continue;
			}

			$this->apply_result_counts( $result, $out, $row, $line );
			$psk = wc_clean( $row['parent_sku'] ?? '' );
			if ( '' !== $psk ) {
				$parents_sync[ $psk ] = true;
			}
		}

		foreach ( array_keys( $parents_sync ) as $psk ) {
			$pid = wc_get_product_id_by_sku( $psk );
			if ( ! $pid ) {
				continue;
			}
			$p = wc_get_product( $pid );
			if ( $p && $p->is_type( 'variable' ) ) {
				\WC_Product_Variable::sync( $p, true );
			}
		}

		return $result;
	}

	/**
	 * @param list<array<int, mixed>> $rows
	 * @param array<string, int>      $map
	 * @return list<array{line:int, row:array<string, string>}>
	 */
	private function prepare_rows( array $rows, array $map ): array {
		$prepared = array();
		$line     = 1;
		foreach ( $rows as $row_cells ) {
			++$line;
			$prepared[] = array(
				'line' => $line,
				'row'  => Column_Map_Helper::row_to_assoc( $row_cells, $map ),
			);
		}
		return $prepared;
	}

	/**
	 * @param list<array{line:int, row:array<string, string>}> $prepared
	 * @return array<string, array<string, array<string, bool>>>
	 */
	private function build_variation_value_map( array $prepared ): array {
		$by_parent = array();
		foreach ( $prepared as $item ) {
			if ( Product_Type_Helper::VARIATION !== Product_Type_Helper::resolve( $item['row'] ) ) {
				continue;
			}
			$psk = wc_clean( $item['row']['parent_sku'] ?? '' );
			if ( '' === $psk ) {
				continue;
			}
			$pairs = Attribute_Parse_Helper::parse_variation_pairs( $item['row']['attributes_variation'] ?? '' );
			if ( ! isset( $by_parent[ $psk ] ) ) {
				$by_parent[ $psk ] = array();
			}
			$by_parent[ $psk ] = Attribute_Parse_Helper::merge_option_values( $by_parent[ $psk ], $pairs );
		}
		return $by_parent;
	}

	/**
	 * @param array<string, array<string, array<string, bool>>> $var_map
	 * @return array<string, list<string>>
	 */
	private function options_list_for_parent( array $var_map, string $parent_sku ): array {
		$out = array();
		foreach ( $var_map[ $parent_sku ] ?? array() as $display => $vals ) {
			$out[ $display ] = array_keys( $vals );
		}
		return $out;
	}

	/**
	 * @param array{action:string, product_id:int, image_error:?string} $out
	 * @param array<string, string>                                     $row
	 */
	private function apply_result_counts( Import_Result $result, array $out, array $row, int $line ): void {
		if ( 'created' === $out['action'] ) {
			$result->increment_created();
		} else {
			$result->increment_updated();
		}

		$sku = wc_clean( $row['sku'] ?? '' );
		$result->add_log(
			array(
				'line' => $line,
				'type' => $out['action'],
				'sku'  => $sku,
			)
		);

		if ( ! empty( $out['image_error'] ) ) {
			$result->add_error(
				array(
					'line'    => $line,
					'message' => sprintf(
						/* translators: 1: line number, 2: message */
						__( 'Linha %1$d: %2$s', 'wc-spreadsheet-product-importer' ),
						$line,
						(string) $out['image_error']
					),
				)
			);
		}
	}
}
