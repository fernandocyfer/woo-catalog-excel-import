<?php
/**
 * Normalização de cabeçalhos da planilha.
 *
 * @package WCSPI
 */

namespace WCSPI\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Mapeia rótulos em inglês ou português para chaves internas.
 */
final class Column_Map_Helper {

	/**
	 * alias (lowercase trim) => chave interna
	 *
	 * @var array<string, string>
	 */
	private const ALIASES = array(
		'sku'                 => 'sku',
		'name'                => 'name',
		'nome'                => 'name',
		'description'         => 'description',
		'descricao'           => 'description',
		'descrição'           => 'description',
		'short_description'   => 'short_description',
		'descricao curta'     => 'short_description',
		'descrição curta'     => 'short_description',
		'price'               => 'price',
		'preco'               => 'price',
		'preço'               => 'price',
		'sale_price'          => 'sale_price',
		'preco promocional'   => 'sale_price',
		'preço promocional'   => 'sale_price',
		'stock'               => 'stock',
		'estoque'             => 'stock',
		'category'            => 'category',
		'categoria'           => 'category',
		'image'               => 'image',
		'imagem principal'    => 'image',
		'status'              => 'status',
		'gallery'             => 'gallery',
		'imagens galeria'     => 'gallery',
		'weight'              => 'weight',
		'peso (kg)'           => 'weight',
		'peso'                => 'weight',
		'length'              => 'length',
		'comprimento (cm)'    => 'length',
		'comprimento'         => 'length',
		'width'               => 'width',
		'largura (cm)'        => 'width',
		'largura'             => 'width',
		'height'              => 'height',
		'altura (cm)'         => 'height',
		'altura'              => 'height',
		'tipo'                => 'product_type',
		'tipo produto'        => 'product_type',
		'product type'        => 'product_type',
		'type'                => 'product_type',
		'sku pai'             => 'parent_sku',
		'sku_pai'             => 'parent_sku',
		'parent sku'          => 'parent_sku',
		'atributos globais'   => 'attributes_global',
		'atributos global'    => 'attributes_global',
		'global attributes'   => 'attributes_global',
		'atributos variacao'  => 'attributes_variation',
		'atributos variação'  => 'attributes_variation',
		'atributos da variacao' => 'attributes_variation',
		'atributos da variação' => 'attributes_variation',
		'variation attributes' => 'attributes_variation',
	);

	/**
	 * @param array<int, mixed> $header_row
	 * @return array<string, int> chave interna => índice da coluna
	 */
	public static function map_header_row( array $header_row ): array {
		$map = array();
		foreach ( $header_row as $index => $label ) {
			$key = self::normalize_label( (string) $label );
			if ( '' === $key ) {
				continue;
			}
			if ( isset( self::ALIASES[ $key ] ) ) {
				$internal = self::ALIASES[ $key ];
				if ( ! isset( $map[ $internal ] ) ) {
					$map[ $internal ] = (int) $index;
				}
			}
		}
		return $map;
	}

	private static function normalize_label( string $label ): string {
		$label = trim( $label );
		$label = preg_replace( '/^\xEF\xBB\xBF/', '', $label );
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $label, 'UTF-8' );
		}
		return strtolower( $label );
	}

	/**
	 * @param array<int, mixed>      $row
	 * @param array<string, int> $column_map
	 * @return array<string, string>
	 */
	public static function row_to_assoc( array $row, array $column_map ): array {
		$out = array();
		foreach ( $column_map as $key => $idx ) {
			$out[ $key ] = isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';
		}
		return $out;
	}
}
