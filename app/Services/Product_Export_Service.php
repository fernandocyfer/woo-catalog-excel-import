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
	 * Nível de aninhamento de WP_Query durante a exportação (parent + variações).
	 *
	 * @var int
	 */
	private static $export_wp_query_depth = 0;

	/**
	 * Se o filtro pre_get_posts já foi registado.
	 *
	 * @var bool
	 */
	private static $export_tax_guard_registered = false;

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

		$export_error = null;
		try {
			foreach ( $this->yield_rows() as $row ) {
				fputcsv( $out, $row, ',' );
			}
		} catch ( \Throwable $e ) {
			$export_error = $e;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WCSPI export: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine() );
			}
		} finally {
			if ( is_resource( $out ) ) {
				fclose( $out );
			}
		}

		if ( $export_error instanceof \Throwable ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: technical error message */
						__( 'A exportação falhou ao gerar o CSV. Ative WP_DEBUG e consulte o log do servidor para detalhes. Erro: %s', 'wc-spreadsheet-product-importer' ),
						$export_error->getMessage()
					)
				),
				esc_html__( 'Erro na exportação', 'wc-spreadsheet-product-importer' ),
				array( 'response' => 500 )
			);
		}
	}

	/**
	 * Lista IDs de produtos «pai» (todos os tipos: simples, variável, agrupado, externo, …) excepto na lixeira.
	 * Usa WP_Query directo para não herdar exclusões de stock/visibilidade em consultas filtradas.
	 *
	 * @return list<int>
	 */
	private function query_parent_product_ids(): array {
		self::register_export_tax_query_guard();

		$wc_args = apply_filters(
			'wcspi_export_parent_product_args',
			array(
				'status'  => 'any',
				'limit'   => -1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'type'    => array(),
			)
		);

		$wp_args = $this->wc_export_args_to_wp_query_parents( $wc_args );
		$wp_args['fields']        = 'ids';
		$wp_args['no_found_rows'] = true;
		$wp_args                  = apply_filters( 'wcspi_export_parent_wp_query_args', $wp_args );
		// Garante que temas/plugins não injectam tax_query de visibilidade/fora de stock.
		$wp_args['suppress_filters'] = true;

		++self::$export_wp_query_depth;
		try {
			$query = new \WP_Query( $wp_args );
			return array_map( 'absint', is_array( $query->posts ) ? $query->posts : array() );
		} finally {
			--self::$export_wp_query_depth;
		}
	}

	/**
	 * @return list<int>
	 */
	private function query_variation_product_ids(): array {
		self::register_export_tax_query_guard();

		$wc_args = apply_filters(
			'wcspi_export_variation_product_args',
			array(
				'status'  => 'any',
				'limit'   => -1,
				'orderby' => 'menu_order',
				'order'   => 'ASC',
				'type'    => array( 'variation' ),
			)
		);

		$wp_args = $this->wc_export_args_to_wp_query_variations( $wc_args );
		$wp_args['fields']        = 'ids';
		$wp_args['no_found_rows'] = true;
		$wp_args                  = apply_filters( 'wcspi_export_variation_wp_query_args', $wp_args );
		$wp_args['suppress_filters'] = true;

		$ids = array();
		++self::$export_wp_query_depth;
		try {
			$query = new \WP_Query( $wp_args );
			$ids   = array_map( 'absint', is_array( $query->posts ) ? $query->posts : array() );
		} finally {
			--self::$export_wp_query_depth;
		}

		usort(
			$ids,
			static function ( $a, $b ) {
				$pa = wp_get_post_parent_id( $a );
				$pb = wp_get_post_parent_id( $b );
				if ( $pa !== $pb ) {
					return $pa <=> $pb;
				}
				return $a <=> $b;
			}
		);

		return $ids;
	}

	/**
	 * O WordPress corre sempre pre_get_posts; suppress_filters não o desliga.
	 * Temas e extensões podem acrescentar tax_query em product_visibility (ex.: excluir outofstock).
	 */
	private static function register_export_tax_query_guard(): void {
		if ( self::$export_tax_guard_registered ) {
			return;
		}
		self::$export_tax_guard_registered = true;
		add_action( 'pre_get_posts', array( self::class, 'strip_product_visibility_tax_query_for_export' ), 999999 );
	}

	/**
	 * @param \WP_Query $query Query object.
	 */
	public static function strip_product_visibility_tax_query_for_export( \WP_Query $query ): void {
		if ( self::$export_wp_query_depth <= 0 ) {
			return;
		}
		$tax_query = $query->get( 'tax_query' );
		if ( empty( $tax_query ) || ! is_array( $tax_query ) ) {
			return;
		}
		$cleaned = self::tax_query_without_product_visibility( $tax_query );
		if ( $cleaned !== $tax_query ) {
			$query->set( 'tax_query', $cleaned );
		}
	}

	/**
	 * Remove cláusulas product_visibility (incl. sub-grupos) para exportar também fora de catálogo / sem stock.
	 *
	 * @param array<string|int, mixed> $tax_query
	 * @return array<string|int, mixed>
	 */
	private static function tax_query_without_product_visibility( array $tax_query ): array {
		$relation = isset( $tax_query['relation'] ) && is_string( $tax_query['relation'] ) ? $tax_query['relation'] : null;
		$clauses  = array();

		foreach ( $tax_query as $key => $clause ) {
			if ( 'relation' === $key ) {
				continue;
			}
			if ( ! is_array( $clause ) ) {
				continue;
			}
			if ( isset( $clause['taxonomy'] ) && 'product_visibility' === $clause['taxonomy'] ) {
				continue;
			}
			$is_nested = isset( $clause['relation'] ) || ( isset( $clause[0] ) && is_array( $clause[0] ) );
			if ( $is_nested && ! isset( $clause['taxonomy'] ) ) {
				$nested = self::tax_query_without_product_visibility( $clause );
				if ( array() !== $nested ) {
					$clauses[] = $nested;
				}
				continue;
			}
			$clauses[] = $clause;
		}

		if ( array() === $clauses ) {
			return array();
		}
		if ( 1 === count( $clauses ) ) {
			return $clauses[0];
		}
		$out = array( 'relation' => $relation ? $relation : 'AND' );
		foreach ( $clauses as $c ) {
			$out[] = $c;
		}
		return $out;
	}

	/**
	 * Converte o formato legado (filtro wcspi_export_*_product_args) para argumentos de WP_Query.
	 *
	 * @param array<string, mixed> $wc_args
	 * @return array<string, mixed>
	 */
	private function wc_export_args_to_wp_query_parents( array $wc_args ): array {
		unset( $wc_args['stock_status'], $wc_args['return'] );

		$wp = array(
			'post_type'      => 'product',
			'post_status'    => $this->wc_export_post_status_arg( $wc_args['status'] ?? 'any' ),
			'posts_per_page' => isset( $wc_args['limit'] ) ? (int) $wc_args['limit'] : -1,
			'orderby'        => $wc_args['orderby'] ?? 'ID',
			'order'          => $wc_args['order'] ?? 'ASC',
		);

		$types = $wc_args['type'] ?? array();
		$types = array_values( array_filter( array_map( 'strval', (array) $types ) ) );
		if ( ! empty( $types ) ) {
			$wp['tax_query'] = array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => $types,
				),
			);
		}

		if ( ! empty( $wc_args['include'] ) ) {
			$wp['post__in'] = array_map( 'absint', (array) $wc_args['include'] );
		}
		if ( ! empty( $wc_args['exclude'] ) ) {
			$wp['post__not_in'] = array_map( 'absint', (array) $wc_args['exclude'] );
		}

		return $wp;
	}

	/**
	 * @param array<string, mixed> $wc_args
	 * @return array<string, mixed>
	 */
	private function wc_export_args_to_wp_query_variations( array $wc_args ): array {
		unset( $wc_args['stock_status'], $wc_args['return'], $wc_args['type'] );

		$wp = array(
			'post_type'      => 'product_variation',
			'post_status'    => $this->wc_export_post_status_arg( $wc_args['status'] ?? 'any' ),
			'posts_per_page' => isset( $wc_args['limit'] ) ? (int) $wc_args['limit'] : -1,
			'orderby'        => $wc_args['orderby'] ?? 'menu_order',
			'order'          => $wc_args['order'] ?? 'ASC',
		);

		if ( ! empty( $wc_args['include'] ) ) {
			$wp['post__in'] = array_map( 'absint', (array) $wc_args['include'] );
		}
		if ( ! empty( $wc_args['exclude'] ) ) {
			$wp['post__not_in'] = array_map( 'absint', (array) $wc_args['exclude'] );
		}

		return $wp;
	}

	/**
	 * Estados para WP_Query: por omissão «any» (todos excepto estados internos, p.ex. lixo e auto-rascunho).
	 * Listas personalizadas nunca incluem «trash».
	 *
	 * @param mixed $status
	 * @return string|array<int|string, string>
	 */
	private function wc_export_post_status_arg( $status ) {
		if ( is_array( $status ) ) {
			$filtered = array_values(
				array_diff(
					array_map( 'strval', $status ),
					array( 'trash', 'auto-draft' )
				)
			);
			return ! empty( $filtered ) ? $filtered : 'any';
		}
		if ( is_string( $status ) && '' !== $status && 'any' !== $status && 'trash' !== $status ) {
			return $status;
		}
		return 'any';
	}

	/**
	 * @return \Generator<int, list<string>>
	 */
	private function yield_rows(): \Generator {
		foreach ( $this->query_parent_product_ids() as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product ) {
				yield $this->row_fallback_parent( $product_id );
				continue;
			}
			try {
				if ( $product->is_type( 'variable' ) && $product instanceof WC_Product_Variable ) {
					yield $this->row_variable( $product );
				} else {
					yield $this->row_simple( $product );
				}
			} catch ( \Throwable $e ) {
				$this->log_export_skip( 'parent', $product_id, $e );
				yield $this->row_fallback_parent( $product_id );
			}
		}

		foreach ( $this->query_variation_product_ids() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof WC_Product_Variation ) {
				yield $this->row_fallback_variation( $variation_id );
				continue;
			}
			try {
				yield $this->row_variation( $variation );
			} catch ( \Throwable $e ) {
				$this->log_export_skip( 'variation', $variation_id, $e );
				yield $this->row_fallback_variation( $variation_id );
			}
		}
	}

	/**
	 * Linha mínima a partir do post quando o objecto WC não está disponível ou ocorreu erro ao ler campos.
	 *
	 * @return list<string>
	 */
	private function row_fallback_parent( int $post_id ): array {
		$post = get_post( $post_id );
		$name = ( $post && is_string( $post->post_title ) ) ? $post->post_title : '';
		$desc = ( $post && is_string( $post->post_content ) && '' !== $post->post_content )
			? $this->description_plain( $post->post_content )
			: '';

		return $this->format_row(
			array(
				'tipo'                 => '',
				'name'                 => $this->cell_str( $name ),
				'description'          => $desc,
				'regular'              => '',
				'sale'                 => '',
				'sku'                  => '',
				'parent_sku'           => '',
				'stock'                => '',
				'category'             => $post_id > 0 ? $this->terms_csv( $post_id, 'product_cat' ) : '',
				'image'                => $post_id > 0 ? $this->attachment_url( (int) get_post_thumbnail_id( $post_id ) ) : '',
				'gallery'              => '',
				'weight'               => '',
				'length'               => '',
				'width'                => '',
				'height'               => '',
				'attributes_global'    => '',
				'attributes_variation' => '',
			)
		);
	}

	/**
	 * @return list<string>
	 */
	private function row_fallback_variation( int $post_id ): array {
		$post          = get_post( $post_id );
		$parent_id     = ( $post && isset( $post->post_parent ) ) ? (int) $post->post_parent : 0;
		$parent_sku    = '';
		$attr_global   = '';
		if ( $parent_id > 0 ) {
			$parent_prod = wc_get_product( $parent_id );
			if ( $parent_prod instanceof WC_Product_Variable ) {
				$parent_sku = $this->cell_str( $parent_prod->get_sku() );
				try {
					$attr_global = $this->variable_attribute_labels( $parent_prod );
				} catch ( \Throwable $e ) {
					$attr_global = '';
				}
			} elseif ( $parent_prod instanceof WC_Product ) {
				$parent_sku = $this->cell_str( $parent_prod->get_sku() );
			}
		}
		$desc = ( $post && is_string( $post->post_content ) && '' !== $post->post_content )
			? $this->description_plain( $post->post_content )
			: '';

		return $this->format_row(
			array(
				'tipo'                 => 'variacao',
				'name'                 => '',
				'description'          => $desc,
				'regular'              => '',
				'sale'                 => '',
				'sku'                  => '',
				'parent_sku'           => $parent_sku,
				'stock'                => '',
				'category'             => '',
				'image'                => $post_id > 0 ? $this->attachment_url( (int) get_post_thumbnail_id( $post_id ) ) : '',
				'gallery'              => '',
				'weight'               => '',
				'length'               => '',
				'width'                => '',
				'height'               => '',
				'attributes_global'    => $attr_global,
				'attributes_variation' => '',
			)
		);
	}

	private function log_export_skip( string $kind, int $product_id, \Throwable $e ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( 'error_log' ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'WCSPI export: skip %1$s id %2$d — %3$s',
				$kind,
				$product_id,
				$e->getMessage()
			)
		);
	}

	/**
	 * @return list<string>
	 */
	private function row_simple( WC_Product $product ): array {
		return $this->format_row(
			array(
				'tipo'                 => '',
				'name'                 => $this->cell_str( $product->get_name() ),
				'description'          => $this->description_plain( $product->get_description() ),
				'regular'              => $this->price_out( $product->get_regular_price() ),
				'sale'                 => $this->price_out( $product->get_sale_price() ),
				'sku'                  => $this->cell_str( $product->get_sku() ),
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
				'name'                 => $this->cell_str( $product->get_name() ),
				'description'          => $this->description_plain( $product->get_description() ),
				'regular'              => $this->price_out( $product->get_regular_price() ),
				'sale'                 => $this->price_out( $product->get_sale_price() ),
				'sku'                  => $this->cell_str( $product->get_sku() ),
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
			$parent_sku  = $this->cell_str( $parent->get_sku() );
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
				'description'          => $this->description_plain( $variation->get_description() ),
				'regular'              => $this->price_out( $variation->get_regular_price() ),
				'sale'                 => $this->price_out( $variation->get_sale_price() ),
				'sku'                  => $this->cell_str( $variation->get_sku() ),
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
		$keys = array(
			'tipo',
			'name',
			'description',
			'regular',
			'sale',
			'sku',
			'parent_sku',
			'stock',
			'category',
			'image',
			'gallery',
			'weight',
			'length',
			'width',
			'height',
			'attributes_global',
			'attributes_variation',
		);
		$row = array();
		foreach ( $keys as $key ) {
			$row[] = $this->cell_str( $data[ $key ] ?? '' );
		}
		return $row;
	}

	/**
	 * WooCommerce pode devolver null em preços/dimensões; PHP 8 rejeita null em parâmetros tipados como string.
	 *
	 * @param mixed $price
	 */
	private function price_out( $price ): string {
		$price = $this->cell_str( $price );
		$price = trim( $price );
		if ( '' === $price ) {
			return '';
		}
		return is_numeric( $price ) ? wc_format_decimal( $price ) : $price;
	}

	/**
	 * @param mixed $dim
	 */
	private function dim_out( $dim ): string {
		$dim = $this->cell_str( $dim );
		$dim = trim( $dim );
		if ( '' === $dim ) {
			return '';
		}
		return is_numeric( $dim ) ? wc_format_decimal( $dim ) : $dim;
	}

	/**
	 * @param mixed $value
	 */
	private function cell_str( $value ): string {
		if ( null === $value ) {
			return '';
		}
		if ( is_string( $value ) ) {
			return $value;
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		return '';
	}

	/**
	 * A descrição no WooCommerce é guardada em HTML; na exportação usamos texto simples para editar na planilha.
	 *
	 * @param mixed $value
	 */
	private function description_plain( $value ): string {
		$raw = $this->cell_str( $value );
		if ( '' === $raw ) {
			return '';
		}

		$raw = str_ireplace( array( '<br>', '<br/>', '<br />' ), ' ', $raw );
		$raw = preg_replace( '/<\s*\/\s*p\s*>/i', ' ', $raw );
		$raw = preg_replace( '/<\s*li\s*>/i', ' • ', $raw );

		$text = wp_strip_all_tags( $raw );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( $text );
	}

	private function stock_out( WC_Product $product ): string {
		if ( ! $product->managing_stock() ) {
			return '';
		}
		$q = $product->get_stock_quantity();
		if ( null === $q || '' === $q ) {
			return '0';
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
		$name_raw = $attr->get_name();
		$name     = is_string( $name_raw ) ? $name_raw : ( is_scalar( $name_raw ) ? (string) $name_raw : '' );
		if ( str_starts_with( $name, 'pa_' ) ) {
			return wc_attribute_label( $name );
		}
		return $name;
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
			$name_raw = $attr->get_name();
			$name     = is_string( $name_raw ) ? $name_raw : ( is_scalar( $name_raw ) ? (string) $name_raw : '' );
			if ( '' === $name ) {
				continue;
			}
			$label = $this->attribute_display_label( $attr );
			$val   = $variation->get_attribute( $name );
			$val   = $this->cell_str( $val );
			$val   = trim( $val );
			if ( '' === $label || '' === $val ) {
				continue;
			}
			$segments[] = $label . ':' . $val;
		}
		return implode( '|', $segments );
	}
}
