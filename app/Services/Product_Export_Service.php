<?php
/**
 * Exportação de produtos para CSV alinhado ao modelo de importação (modelo-variacoes).
 *
 * @package WCSPI
 */

namespace WCSPI\Services;

use WCSPI\Config\Plugin_Config;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Gera CSV com cabeçalhos iguais ao ficheiro «modelo-variacoes.csv» (produtos simples sem coluna Tipo).
 */
final class Product_Export_Service {

	/**
	 * Cabeçalhos na mesma ordem do modelo com variações.
	 *
	 * @var list<string>
	 */
	private const HEADERS = array(
		'Tipo',
		'Nome',
		'Descrição',
		'Preço',
		'Preço Promocional',
		'SKU',
		'SKU Pai',
		'Estoque',
		'Categoria',
		'Imagem Principal',
		'Imagens Galeria',
		'Peso (kg)',
		'Comprimento (cm)',
		'Largura (cm)',
		'Altura (cm)',
		'Atributos globais',
		'Atributos variação',
	);

	public function send_csv_download(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( Plugin_Config::import_time_limit() );
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		if ( function_exists( 'ini_get' ) && ini_get( 'zlib.output_compression' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@ini_set( 'zlib.output_compression', 'Off' );
		}

		while ( ob_get_level() > 0 ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@ob_end_clean();
		}

		$filename = apply_filters(
			'wcspi_export_csv_filename',
			'wcspi-produtos-loja-' . gmdate( 'Y-m-d-His' ) . '.csv'
		);
		$filename = sanitize_file_name( (string) $filename );
		if ( '' === $filename || ! str_ends_with( strtolower( $filename ), '.csv' ) ) {
			$filename = 'wcspi-produtos-loja-' . gmdate( 'Y-m-d-His' ) . '.csv';
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Accel-Buffering: no' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- stream de saída HTTP.
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Não foi possível iniciar a exportação.', 'wc-spreadsheet-product-importer' ) );
		}

		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, self::HEADERS, ',' );

		foreach ( $this->yield_rows() as $row ) {
			fputcsv( $out, $row, ',' );
		}

		fflush( $out );
		fclose( $out );
	}

	/**
	 * @return \Generator<int, list<string>>
	 */
	private function yield_rows(): \Generator {
		$parent_args = apply_filters(
			'wcspi_export_parent_product_args',
			array(
				'status'  => array( 'publish', 'draft', 'private' ),
				'limit'   => -1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'objects',
				'type'    => array( 'simple', 'variable' ),
			)
		);

		$parents = wc_get_products( $parent_args );
		if ( ! is_array( $parents ) ) {
			$parents = array();
		}

		foreach ( $parents as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( $product->is_type( 'simple' ) ) {
				yield $this->row_simple( $product );
			} elseif ( $product->is_type( 'variable' ) ) {
				/** @var WC_Product_Variable $product */
				yield $this->row_variable( $product );
			}
		}

		$var_args = apply_filters(
			'wcspi_export_variation_product_args',
			array(
				'status'  => array( 'publish', 'draft', 'private' ),
				'limit'   => -1,
				'orderby' => 'menu_order',
				'order'   => 'ASC',
				'return'  => 'objects',
				'type'    => array( 'variation' ),
			)
		);

		$variations = wc_get_products( $var_args );
		if ( ! is_array( $variations ) ) {
			$variations = array();
		}

		usort(
			$variations,
			static function ( $a, $b ) {
				$pa = $a instanceof WC_Product ? $a->get_parent_id() : 0;
				$pb = $b instanceof WC_Product ? $b->get_parent_id() : 0;
				if ( $pa !== $pb ) {
					return $pa <=> $pb;
				}
				$ia = $a instanceof WC_Product ? $a->get_id() : 0;
				$ib = $b instanceof WC_Product ? $b->get_id() : 0;
				return $ia <=> $ib;
			}
		);

		foreach ( $variations as $variation ) {
			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}
			yield $this->row_variation( $variation );
		}
	}

	/**
	 * @return list<string>
	 */
	private function row_simple( WC_Product $product ): array {
		return $this->format_row(
			array(
				'tipo'                 => '',
				'name'                 => $product->get_name(),
				'description'          => $product->get_description(),
				'regular'              => $this->price_out( $product->get_regular_price() ),
				'sale'                 => $this->price_out( $product->get_sale_price() ),
				'sku'                  => $product->get_sku(),
				'parent_sku'           => '',
				'stock'                => $this->stock_out( $product ),
				'category'             => $this->terms_csv( $product->get_id(), 'product_cat' ),
				'image'                => $this->attachment_url( $product->get_image_id() ),
				'gallery'              => $this->gallery_urls( $product->get_gallery_image_ids() ),
				'weight'               => $this->dim_out( $product->get_weight() ),
				'length'               => $this->dim_out( $product->get_length() ),
				'width'                => $this->dim_out( $product->get_width() ),
				'height'               => $this->dim_out( $product->get_height() ),
				'attributes_global'    => '',
				'attributes_variation' => '',
			)
		);
	}

	/**
	 * @return list<string>
	 */
	private function row_variable( WC_Product_Variable $product ): array {
		return $this->format_row(
			array(
				'tipo'                 => 'variavel',
				'name'                 => $product->get_name(),
				'description'          => $product->get_description(),
				'regular'              => $this->price_out( $product->get_regular_price() ),
				'sale'                 => $this->price_out( $product->get_sale_price() ),
				'sku'                  => $product->get_sku(),
				'parent_sku'           => '',
				'stock'                => $this->stock_out( $product ),
				'category'             => $this->terms_csv( $product->get_id(), 'product_cat' ),
				'image'                => $this->attachment_url( $product->get_image_id() ),
				'gallery'              => $this->gallery_urls( $product->get_gallery_image_ids() ),
				'weight'               => $this->dim_out( $product->get_weight() ),
				'length'               => $this->dim_out( $product->get_length() ),
				'width'                => $this->dim_out( $product->get_width() ),
				'height'               => $this->dim_out( $product->get_height() ),
				'attributes_global'    => $this->variable_attribute_labels( $product ),
				'attributes_variation' => '',
			)
		);
	}

	/**
	 * @return list<string>
	 */
	private function row_variation( WC_Product_Variation $variation ): array {
		$parent      = wc_get_product( $variation->get_parent_id() );
		$parent_sku  = '';
		$attr_global = '';
		if ( $parent instanceof WC_Product_Variable ) {
			$parent_sku  = $parent->get_sku();
			$attr_global = $this->variable_attribute_labels( $parent );
		}

		$pairs = '';
		if ( $parent instanceof WC_Product_Variable ) {
			$pairs = $this->variation_pairs_string( $variation, $parent );
		}

		return $this->format_row(
			array(
				'tipo'                 => 'variacao',
				'name'                 => '',
				'description'          => $variation->get_description(),
				'regular'              => $this->price_out( $variation->get_regular_price() ),
				'sale'                 => $this->price_out( $variation->get_sale_price() ),
				'sku'                  => $variation->get_sku(),
				'parent_sku'           => $parent_sku,
				'stock'                => $this->stock_out( $variation ),
				'category'             => '',
				'image'                => $this->attachment_url( $variation->get_image_id() ),
				'gallery'              => '',
				'weight'               => $this->dim_out( $variation->get_weight() ),
				'length'               => $this->dim_out( $variation->get_length() ),
				'width'                => $this->dim_out( $variation->get_width() ),
				'height'               => $this->dim_out( $variation->get_height() ),
				'attributes_global'    => $attr_global,
				'attributes_variation' => $pairs,
			)
		);
	}

	/**
	 * @param array<string, string> $data
	 * @return list<string>
	 */
	private function format_row( array $data ): array {
		return array(
			$data['tipo'],
			$data['name'],
			$data['description'],
			$data['regular'],
			$data['sale'],
			$data['sku'],
			$data['parent_sku'],
			$data['stock'],
			$data['category'],
			$data['image'],
			$data['gallery'],
			$data['weight'],
			$data['length'],
			$data['width'],
			$data['height'],
			$data['attributes_global'],
			$data['attributes_variation'],
		);
	}

	private function price_out( string $price ): string {
		$price = trim( $price );
		if ( '' === $price ) {
			return '';
		}
		return is_numeric( $price ) ? wc_format_decimal( $price ) : $price;
	}

	private function dim_out( string $dim ): string {
		$dim = trim( $dim );
		if ( '' === $dim ) {
			return '';
		}
		return is_numeric( $dim ) ? wc_format_decimal( $dim ) : $dim;
	}

	private function stock_out( WC_Product $product ): string {
		if ( ! $product->managing_stock() ) {
			return '';
		}
		$q = $product->get_stock_quantity();
		if ( null === $q || '' === $q ) {
			return '';
		}
		return (string) (int) $q;
	}

	private function attachment_url( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param int[] $ids
	 */
	private function gallery_urls( array $ids ): string {
		$urls = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$u = wp_get_attachment_url( $id );
			if ( is_string( $u ) && '' !== $u ) {
				$urls[] = $u;
			}
		}
		return implode( ',', $urls );
	}

	private function terms_csv( int $product_id, string $taxonomy ): string {
		$names = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
		if ( is_wp_error( $names ) || ! is_array( $names ) || empty( $names ) ) {
			return '';
		}
		return implode( ', ', array_map( 'strval', $names ) );
	}

	private function attribute_display_label( WC_Product_Attribute $attr ): string {
		$name = $attr->get_name();
		if ( is_string( $name ) && str_starts_with( $name, 'pa_' ) ) {
			return wc_attribute_label( $name );
		}
		return (string) $name;
	}

	private function variable_attribute_labels( WC_Product_Variable $product ): string {
		$labels = array();
		foreach ( $product->get_attributes() as $attr ) {
			if ( ! $attr instanceof WC_Product_Attribute || ! $attr->get_variation() ) {
				continue;
			}
			$labels[] = $this->attribute_display_label( $attr );
		}
		return implode( '|', $labels );
	}

	private function variation_pairs_string( WC_Product_Variation $variation, WC_Product_Variable $parent ): string {
		$segments = array();
		foreach ( $parent->get_attributes() as $attr ) {
			if ( ! $attr instanceof WC_Product_Attribute || ! $attr->get_variation() ) {
				continue;
			}
			$name  = $attr->get_name();
			$label = $this->attribute_display_label( $attr );
			$val   = $variation->get_attribute( $name );
			$val   = is_string( $val ) ? trim( $val ) : '';
			if ( '' === $label || '' === $val ) {
				continue;
			}
			$segments[] = $label . ':' . $val;
		}
		return implode( '|', $segments );
	}
}
