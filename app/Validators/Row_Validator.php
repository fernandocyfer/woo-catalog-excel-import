<?php
/**
 * Validação linha a linha.
 *
 * @package WCSPI
 */

namespace WCSPI\Validators;

use WCSPI\Helpers\Attribute_Parse_Helper;
use WCSPI\Helpers\Image_Import_Helper;
use WCSPI\Helpers\Number_Helper;
use WCSPI\Helpers\Product_Type_Helper;
use WCSPI\Helpers\Status_Helper;

defined( 'ABSPATH' ) || exit;

final class Row_Validator {

	/**
	 * @param array<string, string> $row Chaves internas normalizadas.
	 * @param int                   $line Número da linha na planilha (1 = cabeçalho).
	 * @return true|\WP_Error
	 */
	public function validate( array $row, int $line ) {
		$type = Product_Type_Helper::resolve( $row );
		if ( Product_Type_Helper::VARIATION === $type ) {
			return $this->validate_variation( $row, $line );
		}
		if ( Product_Type_Helper::VARIABLE === $type ) {
			return $this->validate_variable( $row, $line );
		}
		return $this->validate_simple( $row, $line );
	}

	/**
	 * @param array<string, string> $row
	 */
	private function validate_simple( array $row, int $line ) {
		$name = $row['name'] ?? '';
		if ( '' === trim( $name ) ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'Nome obrigatório.', 'wc-spreadsheet-product-importer' ) ) );
		}

		$sku = trim( (string) ( $row['sku'] ?? '' ) );
		if ( '' === $sku ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'SKU obrigatório.', 'wc-spreadsheet-product-importer' ) ) );
		}

		return $this->validate_prices_stock_status_media( $row, $line );
	}

	/**
	 * @param array<string, string> $row
	 */
	private function validate_variable( array $row, int $line ) {
		$name = $row['name'] ?? '';
		if ( '' === trim( $name ) ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'Nome obrigatório (produto variável).', 'wc-spreadsheet-product-importer' ) ) );
		}

		$sku = trim( (string) ( $row['sku'] ?? '' ) );
		if ( '' === $sku ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'SKU obrigatório (produto variável).', 'wc-spreadsheet-product-importer' ) ) );
		}

		return $this->validate_prices_stock_status_media( $row, $line );
	}

	/**
	 * @param array<string, string> $row
	 */
	private function validate_variation( array $row, int $line ) {
		$sku = trim( (string) ( $row['sku'] ?? '' ) );
		if ( '' === $sku ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'SKU obrigatório (variação).', 'wc-spreadsheet-product-importer' ) ) );
		}

		$parent = trim( (string) ( $row['parent_sku'] ?? '' ) );
		if ( '' === $parent ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'SKU pai obrigatório (variação).', 'wc-spreadsheet-product-importer' ) ) );
		}

		$pairs = Attribute_Parse_Helper::parse_variation_pairs( $row['attributes_variation'] ?? '' );
		if ( empty( $pairs ) ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'Atributos da variação obrigatórios (ex.: Tamanho:M|Cor:Azul).', 'wc-spreadsheet-product-importer' ) ) );
		}

		return $this->validate_prices_stock_status_media( $row, $line );
	}

	/**
	 * @param array<string, string> $row
	 */
	private function validate_prices_stock_status_media( array $row, int $line ) {
		$price = $row['price'] ?? '';
		if ( '' !== trim( $price ) && null === Number_Helper::parse_price( $price ) ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'Preço inválido.', 'wc-spreadsheet-product-importer' ) ) );
		}

		$sale = $row['sale_price'] ?? '';
		if ( '' !== trim( $sale ) && null === Number_Helper::parse_price( $sale ) ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'Preço promocional inválido.', 'wc-spreadsheet-product-importer' ) ) );
		}

		$stock = $row['stock'] ?? '';
		if ( '' !== trim( $stock ) && null === Number_Helper::parse_int_stock( $stock ) ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'Estoque inválido.', 'wc-spreadsheet-product-importer' ) ) );
		}

		$status_raw = $row['status'] ?? '';
		if ( '' !== trim( $status_raw ) ) {
			$st = Status_Helper::to_post_status( $status_raw );
			if ( null === $st ) {
				return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'Status inválido (use publish, draft ou private).', 'wc-spreadsheet-product-importer' ) ) );
			}
		}

		$image = $row['image'] ?? '';
		if ( '' !== Image_Import_Helper::normalize_url( $image ) && ! $this->is_valid_image_url( $image ) ) {
			return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'URL da imagem inválida.', 'wc-spreadsheet-product-importer' ) ) );
		}

		$gallery = $row['gallery'] ?? '';
		if ( '' !== trim( $gallery ) ) {
			foreach ( preg_split( '/\s*,\s*/', $gallery ) as $u ) {
				$u = Image_Import_Helper::normalize_url( $u );
				if ( '' === $u ) {
					continue;
				}
				if ( ! $this->is_valid_image_url( $u ) ) {
					return new \WP_Error( 'wcspi_row', $this->format_line_error( $line, __( 'URL na galeria inválida.', 'wc-spreadsheet-product-importer' ) ) );
				}
			}
		}

		return true;
	}

	private function format_line_error( int $line, string $message ): string {
		return sprintf(
			/* translators: 1: line number, 2: error message */
			__( 'Linha %1$d: %2$s', 'wc-spreadsheet-product-importer' ),
			$line,
			$message
		);
	}

	/**
	 * Validação alinhada ao descarregamento: normaliza aspas do Excel e exige http(s) + host.
	 */
	private function is_valid_image_url( string $url ): bool {
		$url = esc_url_raw( Image_Import_Helper::normalize_url( $url ) );
		if ( '' === $url ) {
			return false;
		}
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) || empty( $p['scheme'] ) ) {
			return false;
		}
		return in_array( strtolower( (string) $p['scheme'] ), array( 'http', 'https' ), true );
	}
}
