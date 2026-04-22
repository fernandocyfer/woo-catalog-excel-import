<?php
/**
 * Mapeamento de status de produto.
 *
 * @package WCSPI
 */

namespace WCSPI\Helpers;

defined( 'ABSPATH' ) || exit;

final class Status_Helper {

	/**
	 * @param string $raw Valor da célula (publish, draft, private, rascunho, etc.)
	 * @return string|null post_status válido ou null se vazio (usa default do fluxo).
	 */
	public static function to_post_status( string $raw ): ?string {
		$raw = strtolower( trim( $raw ) );
		if ( '' === $raw ) {
			return null;
		}
		$map = array(
			'publish'  => 'publish',
			'published'=> 'publish',
			'publicado'=> 'publish',
			'draft'    => 'draft',
			'rascunho' => 'draft',
			'private'  => 'private',
			'privado'  => 'private',
		);
		return $map[ $raw ] ?? null;
	}

	/**
	 * @return string[]
	 */
	public static function allowed_statuses(): array {
		return array( 'publish', 'draft', 'private' );
	}
}
