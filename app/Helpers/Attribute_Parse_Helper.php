<?php
/**
 * Parse de atributos no formato Nome|Nome ou Nome:Valor|Nome:Valor.
 *
 * @package WCSPI
 */

namespace WCSPI\Helpers;

defined( 'ABSPATH' ) || exit;

final class Attribute_Parse_Helper {

	/**
	 * @return list<string>
	 */
	public static function parse_global_names( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array();
		}
		$out = array();
		foreach ( explode( '|', $raw ) as $part ) {
			$part = trim( $part );
			if ( '' !== $part ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, string> nome visível => valor da opção
	 */
	public static function parse_variation_pairs( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array();
		}
		$out = array();
		foreach ( explode( '|', $raw ) as $segment ) {
			$segment = trim( $segment );
			if ( '' === $segment ) {
				continue;
			}
			$pos = strpos( $segment, ':' );
			if ( false === $pos ) {
				continue;
			}
			$name  = trim( substr( $segment, 0, $pos ) );
			$value = trim( substr( $segment, $pos + 1 ) );
			if ( '' !== $name && '' !== $value ) {
				$out[ $name ] = $value;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, array<string, bool>> $acc
	 * @param array<string, string>              $pairs
	 * @return array<string, array<string, bool>>
	 */
	public static function merge_option_values( array $acc, array $pairs ): array {
		foreach ( $pairs as $name => $value ) {
			if ( ! isset( $acc[ $name ] ) ) {
				$acc[ $name ] = array();
			}
			$acc[ $name ][ $value ] = true;
		}
		return $acc;
	}
}
