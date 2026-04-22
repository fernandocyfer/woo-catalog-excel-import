<?php
/**
 * Persistência de produtos e termos.
 *
 * @package WCSPI
 */

namespace WCSPI\Repositories;

use WCSPI\Helpers\Attribute_Parse_Helper;
use WCSPI\Helpers\Image_Import_Helper;
use WCSPI\Helpers\Number_Helper;
use WCSPI\Helpers\Status_Helper;

defined( 'ABSPATH' ) || exit;

final class Product_Repository {

	/**
	 * @param array<string, string> $row
	 * @return array{action:string, product_id:int, image_error:?string}
	 */
	public function upsert_simple_product( array $row ): array {
		$sku = wc_clean( $row['sku'] ?? '' );
		$id  = $sku ? (int) wc_get_product_id_by_sku( $sku ) : 0;

		if ( $id > 0 ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_type( 'simple' ) ) {
				throw new \RuntimeException( __( 'SKU existente não corresponde a um produto simples.', 'wc-spreadsheet-product-importer' ) );
			}
			$action = 'updated';
		} else {
			$product = new \WC_Product_Simple();
			$action  = 'created';
		}

		$this->apply_core_fields( $product, $row );
		$this->apply_prices( $product, $row );
		$this->apply_stock( $product, $row );

		$product_id = $product->save();
		if ( ! $product_id ) {
			throw new \RuntimeException( __( 'Falha ao guardar produto.', 'wc-spreadsheet-product-importer' ) );
		}

		$this->assign_categories( (int) $product_id, $row['category'] ?? '' );
		$image_error = $this->apply_images( $product, $row, (int) $product_id );
		$product->save();

		return array(
			'action'      => $action,
			'product_id'  => (int) $product_id,
			'image_error' => $image_error,
		);
	}

	/**
	 * @param array<string, string>    $row
	 * @param array<string, list<string>> $attribute_options Nome visível => valores únicos.
	 * @return array{action:string, product_id:int, image_error:?string}
	 */
	public function upsert_variable_product( array $row, array $attribute_options ): array {
		$sku = wc_clean( $row['sku'] ?? '' );
		$id  = $sku ? (int) wc_get_product_id_by_sku( $sku ) : 0;

		if ( $id > 0 ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_type( 'variable' ) ) {
				throw new \RuntimeException( __( 'SKU existente não corresponde a um produto variável.', 'wc-spreadsheet-product-importer' ) );
			}
			$action = 'updated';
		} else {
			$product = new \WC_Product_Variable();
			$action  = 'created';
		}

		$this->apply_core_fields( $product, $row );
		$product->set_regular_price( '' );
		$product->set_sale_price( '' );
		$product->set_manage_stock( false );

		$global_names = Attribute_Parse_Helper::parse_global_names( $row['attributes_global'] ?? '' );
		$merged       = $attribute_options;
		foreach ( $global_names as $gname ) {
			if ( ! isset( $merged[ $gname ] ) ) {
				$merged[ $gname ] = array();
			}
		}

		$wc_attrs = array();
		foreach ( $merged as $display_name => $values ) {
			$values = array_values(
				array_filter(
					array_unique( array_map( 'strval', $values ) ),
					static function ( $v ) {
						return '' !== $v;
					}
				)
			);
			if ( empty( $values ) ) {
				continue;
			}
			$attr = new \WC_Product_Attribute();
			$attr->set_id( 0 );
			$attr->set_name( $display_name );
			$attr->set_options( $values );
			$attr->set_visible( true );
			$attr->set_variation( true );
			$wc_attrs[] = $attr;
		}

		if ( empty( $wc_attrs ) ) {
			throw new \RuntimeException( __( 'Produto variável sem atributos: defina «Atributos globais» e/ou linhas de variação com valores.', 'wc-spreadsheet-product-importer' ) );
		}

		$product->set_attributes( $wc_attrs );

		$product_id = $product->save();
		if ( ! $product_id ) {
			throw new \RuntimeException( __( 'Falha ao guardar produto variável.', 'wc-spreadsheet-product-importer' ) );
		}

		$this->assign_categories( (int) $product_id, $row['category'] ?? '' );
		$image_error = $this->apply_images( $product, $row, (int) $product_id );
		$product->save();

		\WC_Product_Variable::sync( $product, true );

		return array(
			'action'      => $action,
			'product_id'  => (int) $product_id,
			'image_error' => $image_error,
		);
	}

	/**
	 * @param array<string, string> $row
	 * @return array{action:string, product_id:int, image_error:?string}
	 */
	public function upsert_variation_product( array $row ): array {
		$sku        = wc_clean( $row['sku'] ?? '' );
		$parent_sku = wc_clean( $row['parent_sku'] ?? '' );
		$parent_id  = $parent_sku ? (int) wc_get_product_id_by_sku( $parent_sku ) : 0;
		if ( ! $parent_id ) {
			throw new \RuntimeException( __( 'SKU pai não encontrado.', 'wc-spreadsheet-product-importer' ) );
		}

		$parent = wc_get_product( $parent_id );
		if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
			throw new \RuntimeException( __( 'SKU pai não é um produto variável.', 'wc-spreadsheet-product-importer' ) );
		}

		$var_id = $sku ? (int) wc_get_product_id_by_sku( $sku ) : 0;
		if ( $var_id > 0 ) {
			$variation = wc_get_product( $var_id );
			if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
				throw new \RuntimeException( __( 'SKU de variação já usado noutro tipo de produto.', 'wc-spreadsheet-product-importer' ) );
			}
			if ( (int) $variation->get_parent_id() !== $parent_id ) {
				throw new \RuntimeException( __( 'Variação existe mas com outro produto pai.', 'wc-spreadsheet-product-importer' ) );
			}
			$action = 'updated';
		} else {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent_id );
			$action = 'created';
		}

		$variation->set_sku( $sku );
		$variation->set_description( $row['description'] ?? '' );

		$pairs      = Attribute_Parse_Helper::parse_variation_pairs( $row['attributes_variation'] ?? '' );
		$var_attrs  = array();
		foreach ( $pairs as $label => $value ) {
			$var_attrs[ sanitize_title( $label ) ] = $value;
		}
		$variation->set_attributes( $var_attrs );

		$regular = Number_Helper::parse_price( $row['price'] ?? '' );
		if ( null !== $regular ) {
			$variation->set_regular_price( $regular );
		}

		$sale = Number_Helper::parse_price( $row['sale_price'] ?? '' );
		if ( null !== $sale && '' !== $sale ) {
			$variation->set_sale_price( $sale );
		} else {
			$variation->set_sale_price( '' );
		}

		$stock_raw = $row['stock'] ?? '';
		if ( '' !== trim( $stock_raw ) ) {
			$qty = Number_Helper::parse_int_stock( $stock_raw );
			if ( null !== $qty ) {
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( $qty );
				$variation->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			}
		}

		$status = Status_Helper::to_post_status( $row['status'] ?? '' );
		if ( $status ) {
			$variation->set_status( $status );
		} else {
			$variation->set_status( 'publish' );
		}

		$w = Number_Helper::parse_dimension( $row['weight'] ?? '' );
		if ( null !== $w ) {
			$variation->set_weight( $w );
		}
		$l = Number_Helper::parse_dimension( $row['length'] ?? '' );
		if ( null !== $l ) {
			$variation->set_length( $l );
		}
		$wi = Number_Helper::parse_dimension( $row['width'] ?? '' );
		if ( null !== $wi ) {
			$variation->set_width( $wi );
		}
		$h = Number_Helper::parse_dimension( $row['height'] ?? '' );
		if ( null !== $h ) {
			$variation->set_height( $h );
		}

		$vid = $variation->save();
		if ( ! $vid ) {
			throw new \RuntimeException( __( 'Falha ao guardar variação.', 'wc-spreadsheet-product-importer' ) );
		}

		$image_error = $this->apply_images( $variation, $row, (int) $vid );
		$variation->save();

		return array(
			'action'      => $action,
			'product_id'  => (int) $vid,
			'image_error' => $image_error,
		);
	}

	private function apply_core_fields( \WC_Product $product, array $row ): void {
		$product->set_name( $row['name'] ?? '' );
		$product->set_description( $row['description'] ?? '' );
		$product->set_short_description( $row['short_description'] ?? '' );
		$product->set_sku( wc_clean( $row['sku'] ?? '' ) );

		$status = Status_Helper::to_post_status( $row['status'] ?? '' );
		$product->set_status( $status ? $status : 'publish' );
		$product->set_catalog_visibility( 'visible' );

		$w = Number_Helper::parse_dimension( $row['weight'] ?? '' );
		if ( null !== $w ) {
			$product->set_weight( $w );
		}
		$l = Number_Helper::parse_dimension( $row['length'] ?? '' );
		if ( null !== $l ) {
			$product->set_length( $l );
		}
		$wi = Number_Helper::parse_dimension( $row['width'] ?? '' );
		if ( null !== $wi ) {
			$product->set_width( $wi );
		}
		$h = Number_Helper::parse_dimension( $row['height'] ?? '' );
		if ( null !== $h ) {
			$product->set_height( $h );
		}
	}

	private function apply_prices( \WC_Product $product, array $row ): void {
		$regular = Number_Helper::parse_price( $row['price'] ?? '' );
		if ( null !== $regular ) {
			$product->set_regular_price( $regular );
		}

		$sale = Number_Helper::parse_price( $row['sale_price'] ?? '' );
		if ( null !== $sale && '' !== $sale ) {
			$product->set_sale_price( $sale );
		} else {
			$product->set_sale_price( '' );
		}
	}

	private function apply_stock( \WC_Product $product, array $row ): void {
		$stock_raw = $row['stock'] ?? '';
		if ( '' !== trim( $stock_raw ) ) {
			$qty = Number_Helper::parse_int_stock( $stock_raw );
			if ( null !== $qty ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $qty );
				$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			}
		}
	}

	/**
	 * @return string|null
	 */
	private function apply_images( \WC_Product $product, array $row, int $post_id ): ?string {
		$errors      = array();
		$main        = Image_Import_Helper::normalize_url( (string) ( $row['image'] ?? '' ) );
		if ( '' !== $main ) {
			$att = Image_Import_Helper::import_from_url( $main, $post_id );
			if ( is_wp_error( $att ) ) {
				$errors[] = sprintf(
					/* translators: %s: error message */
					__( 'Imagem principal: %s', 'wc-spreadsheet-product-importer' ),
					$att->get_error_message()
				);
			} else {
				$product->set_image_id( $att );
			}
		}

		$gallery_raw = (string) ( $row['gallery'] ?? '' );
		if ( '' !== trim( $gallery_raw ) ) {
			$ids = array();
			$n   = 0;
			foreach ( preg_split( '/\s*,\s*/', $gallery_raw ) as $chunk ) {
				$url = Image_Import_Helper::normalize_url( $chunk );
				if ( '' === $url ) {
					continue;
				}
				++$n;
				$gid = Image_Import_Helper::import_from_url( $url, $post_id );
				if ( is_wp_error( $gid ) ) {
					$errors[] = sprintf(
						/* translators: 1: gallery index, 2: error message */
						__( 'Galeria #%1$d: %2$s', 'wc-spreadsheet-product-importer' ),
						$n,
						$gid->get_error_message()
					);
					continue;
				}
				$ids[] = $gid;
			}
			if ( ! empty( $ids ) ) {
				$product->set_gallery_image_ids( $ids );
			}
		}

		return ! empty( $errors ) ? implode( ' ', $errors ) : null;
	}

	private function assign_categories( int $product_id, string $categories_csv ): void {
		$categories_csv = trim( $categories_csv );
		if ( '' === $categories_csv ) {
			return;
		}
		$names    = array_map( 'trim', explode( ',', $categories_csv ) );
		$term_ids = array();
		foreach ( $names as $name ) {
			if ( '' === $name ) {
				continue;
			}
			$term = term_exists( $name, 'product_cat' );
			if ( ! $term ) {
				$inserted = wp_insert_term( $name, 'product_cat' );
				if ( is_wp_error( $inserted ) ) {
					continue;
				}
				$term_ids[] = (int) $inserted['term_id'];
			} else {
				$term_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
		}
		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $product_id, $term_ids, 'product_cat' );
		}
	}

}
