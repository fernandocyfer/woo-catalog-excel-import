<?php
/**
 * Persistência do último relatório de importação.
 *
 * @package WCSPI
 */

namespace WCSPI\Services;

use WCSPI\DTO\Import_Result;

defined( 'ABSPATH' ) || exit;

final class Import_Log_Service {

	public const OPTION_KEY = 'wcspi_last_import_log';

	public function save( Import_Result $result ): void {
		update_option( self::OPTION_KEY, $result->to_array(), false );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_last(): ?array {
		$data = get_option( self::OPTION_KEY, null );
		return is_array( $data ) ? $data : null;
	}
}
