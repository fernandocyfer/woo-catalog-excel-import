<?php
/**
 * Tipo de linha de produto na planilha.
 *
 * @package WCSPI
 */

namespace WCSPI\Helpers;

defined( 'ABSPATH' ) || exit;

final class Product_Type_Helper {

	public const SIMPLE    = 'simple';
	public const VARIABLE  = 'variable';
	public const VARIATION = 'variation';

	/**
	 * @param array<string, string> $row
	 */
	public static function resolve( array $row ): string {
		$raw = trim( (string) ( $row['product_type'] ?? '' ) );
		if ( '' === $raw ) {
			return self::SIMPLE;
		}
		if ( function_exists( 'mb_strtolower' ) ) {
			$raw = mb_strtolower( $raw, 'UTF-8' );
		} else {
			$raw = strtolower( $raw );
		}
		$map = array(
			'simple'    => self::SIMPLE,
			'simples'   => self::SIMPLE,
			'simple product' => self::SIMPLE,
			'variable'  => self::VARIABLE,
			'variavel'  => self::VARIABLE,
			'variável'  => self::VARIABLE,
			'variation' => self::VARIATION,
			'variacao'  => self::VARIATION,
			'variação'  => self::VARIATION,
		);
		return $map[ $raw ] ?? self::SIMPLE;
	}
}
