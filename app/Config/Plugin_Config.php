<?php
/**
 * Configuração central do plugin.
 *
 * @package WCSPI
 */

namespace WCSPI\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Constantes e limites editáveis via filtro.
 */
final class Plugin_Config {

	public const VERSION = WCSPI_VERSION;

	/** @var int Tamanho máximo do upload em bytes. */
	public const DEFAULT_MAX_BYTES = 5242880;

	/** @var int Tempo máximo de execução por pedido de importação (segundos). */
	public const IMPORT_TIME_LIMIT = 300;

	/**
	 * Extensões permitidas (sem ponto).
	 *
	 * @return string[]
	 */
	public static function allowed_extensions(): array {
		return (array) apply_filters( 'wcspi_allowed_extensions', array( 'csv', 'xlsx' ) );
	}

	public static function max_upload_bytes(): int {
		return (int) apply_filters( 'wcspi_max_upload_bytes', self::DEFAULT_MAX_BYTES );
	}

	public static function import_time_limit(): int {
		return (int) apply_filters( 'wcspi_import_time_limit', self::IMPORT_TIME_LIMIT );
	}

	/**
	 * Colunas mínimas necessárias no cabeçalho (chaves normalizadas internas).
	 *
	 * @return string[]
	 */
	public static function required_header_keys(): array {
		return (array) apply_filters( 'wcspi_required_header_keys', array( 'sku', 'name' ) );
	}
}
