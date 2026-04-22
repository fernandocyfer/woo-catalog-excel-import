<?php
/**
 * Parse de números e preços.
 *
 * @package WCSPI
 */

namespace WCSPI\Helpers;

defined( 'ABSPATH' ) || exit;

final class Number_Helper {

	/**
	 * @return string|null Decimal formatado para WooCommerce ou null se inválido/vazio.
	 */
	public static function parse_price( string $raw ): ?string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		$raw = str_replace( ' ', '', $raw );
		$has_comma = str_contains( $raw, ',' );
		$has_dot   = str_contains( $raw, '.' );
		if ( $has_comma && $has_dot ) {
			$last_comma = strrpos( $raw, ',' );
			$last_dot   = strrpos( $raw, '.' );
			if ( $last_comma > $last_dot ) {
				$normalized = str_replace( '.', '', $raw );
				$normalized = str_replace( ',', '.', $normalized );
			} else {
				$normalized = str_replace( ',', '', $raw );
			}
		} elseif ( $has_comma ) {
			$normalized = preg_match( '/,\d{1,2}$/', $raw ) ? str_replace( ',', '.', $raw ) : str_replace( ',', '', $raw );
		} else {
			$normalized = $raw;
		}
		if ( ! is_numeric( $normalized ) ) {
			return null;
		}
		return wc_format_decimal( (string) $normalized );
	}

	public static function parse_int_stock( string $raw ): ?int {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		if ( ! is_numeric( $raw ) ) {
			return null;
		}
		return (int) round( (float) str_replace( ',', '.', $raw ) );
	}

	/**
	 * @return string|null
	 */
	public static function parse_dimension( string $raw ): ?string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		$normalized = str_replace( ',', '.', str_replace( ' ', '', $raw ) );
		if ( ! is_numeric( $normalized ) ) {
			return null;
		}
		return wc_format_decimal( (string) $normalized );
	}
}
