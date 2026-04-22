<?php
/**
 * Importação de imagens remotas para a biblioteca de media.
 *
 * @package WCSPI
 */

namespace WCSPI\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Descarrega por URL, valida bytes reais de imagem e faz sideload compatível com o WooCommerce.
 *
 * O fluxo nativo `wc_rest_upload_image_from_url` falha com «Sem permissão para enviar esse tipo de arquivo»
 * quando o nome do ficheiro na URL não coincide com o conteúdo (ex.: .jpg mas resposta HTML ou redirecionamento).
 */
final class Image_Import_Helper {

	/**
	 * Remove lixo comum de Excel/CSV em torno do URL.
	 */
	public static function normalize_url( string $raw ): string {
		$u = trim( $raw );
		$u = preg_replace( '/^\xEF\xBB\xBF/', '', $u );
		$u = trim( $u, " \t\n\r\0\x0B\"'" );
		while ( ( str_starts_with( $u, '"' ) && str_ends_with( $u, '"' ) ) || ( str_starts_with( $u, "'" ) && str_ends_with( $u, "'" ) ) ) {
			$u = trim( substr( $u, 1, -1 ) );
		}
		return trim( $u );
	}

	/**
	 * Descarrega e regista a imagem como anexo do post indicado.
	 *
	 * @return int|\WP_Error ID do anexo ou erro (mensagem útil para logs).
	 */
	public static function import_from_url( string $raw_url, int $post_id ) {
		$clean = self::normalize_url( $raw_url );
		$url   = esc_url_raw( $clean );

		if ( '' === $url ) {
			return new \WP_Error( 'wcspi_image_empty', __( 'URL da imagem vazia após limpeza.', 'wc-spreadsheet-product-importer' ) );
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return new \WP_Error( 'wcspi_image_parse', __( 'URL da imagem inválida (falta protocolo ou host).', 'wc-spreadsheet-product-importer' ) );
		}

		$scheme = strtolower( (string) $parsed['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new \WP_Error( 'wcspi_image_scheme', __( 'Só são aceites URLs http ou https.', 'wc-spreadsheet-product-importer' ) );
		}

		$timeout_cb = static function () {
			return 30;
		};

		/**
		 * Em ambientes locais com certificado inválido, use: add_filter( 'wcspi_image_download_sslverify', '__return_false' );
		 * Atenção: desativa verificação SSL para todos os pedidos HTTP durante esta importação.
		 */
		$relax_ssl = ! apply_filters( 'wcspi_image_download_sslverify', true );

		add_filter( 'http_request_timeout', $timeout_cb, 999 );
		if ( $relax_ssl ) {
			add_filter( 'https_ssl_verify', '__return_false', 9999 );
		}

		try {
			$upload = self::download_and_sideload_verified( $url );
			if ( is_wp_error( $upload ) ) {
				return $upload;
			}
			if ( empty( $upload['file'] ) ) {
				return new \WP_Error( 'wcspi_image_upload', __( 'Resposta inválida ao processar a imagem.', 'wc-spreadsheet-product-importer' ) );
			}

			if ( function_exists( 'wc_rest_set_uploaded_image_as_attachment' ) ) {
				$attachment_id = wc_rest_set_uploaded_image_as_attachment( $upload, $post_id );
			} else {
				$attachment_id = self::insert_attachment_from_upload( $upload, $post_id );
			}

			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
			if ( ! $attachment_id ) {
				return new \WP_Error( 'wcspi_image_attachment', __( 'Falha ao criar o anexo da imagem.', 'wc-spreadsheet-product-importer' ) );
			}
			return (int) $attachment_id;
		} finally {
			remove_filter( 'http_request_timeout', $timeout_cb, 999 );
			if ( $relax_ssl ) {
				remove_filter( 'https_ssl_verify', '__return_false', 9999 );
			}
		}
	}

	/**
	 * MIME types permitidos (alinhados ao WooCommerce REST quando disponível).
	 *
	 * @return list<string>
	 */
	private static function get_import_image_mimes(): array {
		if ( function_exists( 'wc_rest_allowed_image_mime_types' ) ) {
			return array_values( wc_rest_allowed_image_mime_types() );
		}

		return array(
			'image/jpeg',
			'image/gif',
			'image/png',
			'image/bmp',
			'image/tiff',
			'image/x-icon',
			'image/webp',
		);
	}

	private static function mime_to_import_ext( string $mime ): ?string {
		$map = array(
			'image/jpeg'   => 'jpg',
			'image/gif'    => 'gif',
			'image/png'    => 'png',
			'image/bmp'    => 'bmp',
			'image/tiff'   => 'tif',
			'image/x-icon' => 'ico',
			'image/webp'   => 'webp',
		);

		return $map[ $mime ] ?? null;
	}

	/**
	 * Descarrega, valida imagem real, ajusta extensão e move para uploads (sideload).
	 *
	 * @return array|\WP_Error Estrutura com file, url, type em caso de sucesso.
	 */
	private static function download_and_sideload_verified( string $url ) {
		if ( ! function_exists( 'download_url' ) || ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$url_pathname       = wp_basename( current( explode( '?', $url ) ) );
		$file_array         = array(
			'name'     => '' !== $url_pathname ? $url_pathname : 'image.bin',
			'tmp_name' => download_url( $url ),
		);

		if ( is_wp_error( $file_array['tmp_name'] ) ) {
			$err = $file_array['tmp_name'];
			return new \WP_Error(
				'wcspi_image_download',
				sprintf(
					/* translators: %s: WordPress error message */
					__( 'Erro ao descarregar a imagem: %s', 'wc-spreadsheet-product-importer' ),
					$err->get_error_message()
				)
			);
		}

		$tmp = $file_array['tmp_name'];
		if ( ! is_string( $tmp ) || ! is_readable( $tmp ) || filesize( $tmp ) < 1 ) {
			wp_delete_file( $tmp );
			return new \WP_Error(
				'wcspi_image_empty_file',
				__( 'O descarregamento da imagem está vazio ou ilegível.', 'wc-spreadsheet-product-importer' )
			);
		}

		$detected = wp_get_image_mime( $tmp );
		if ( ! $detected ) {
			wp_delete_file( $tmp );
			return new \WP_Error(
				'wcspi_not_image',
				__(
					'O URL não devolveu um ficheiro de imagem válido (por exemplo HTML, página de erro ou ficheiro corrompido). Abra o link no navegador: tem de mostrar só a imagem.',
					'wc-spreadsheet-product-importer'
				)
			);
		}

		$allowed = self::get_import_image_mimes();
		if ( ! in_array( $detected, $allowed, true ) ) {
			wp_delete_file( $tmp );
			return new \WP_Error(
				'wcspi_image_mime_blocked',
				sprintf(
					/* translators: %s: MIME type */
					__(
						'Formato de imagem não permitido nesta importação (%s). Use JPEG, PNG, GIF, WebP, BMP, TIFF ou ICO.',
						'wc-spreadsheet-product-importer'
					),
					$detected
				)
			);
		}

		$ext = self::mime_to_import_ext( $detected );
		if ( null === $ext ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'wcspi_image_mime_map', __( 'Erro ao determinar a extensão da imagem.', 'wc-spreadsheet-product-importer' ) );
		}

		$stem = pathinfo( $file_array['name'], PATHINFO_FILENAME );
		if ( '' === $stem || '.' === $stem ) {
			$stem = 'image';
		}
		$file_array['name'] = $stem . '.' . $ext;

		$mimes_for_wc = function_exists( 'wc_rest_allowed_image_mime_types' ) ? wc_rest_allowed_image_mime_types() : null;

		/*
		 * A validação real já foi feita com wp_get_image_mime; test_type false evita falhas por desencontro URL/extensão.
		 */
		$file = wp_handle_sideload(
			$file_array,
			array(
				'test_form' => false,
				'test_type' => false,
				'mimes'     => $mimes_for_wc,
			),
			current_time( 'Y/m' )
		);

		if ( isset( $file['error'] ) ) {
			if ( is_string( $tmp ) && file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return new \WP_Error(
				'wcspi_image_sideload',
				sprintf(
					/* translators: %s: WordPress error message */
					__( 'Imagem inválida: %s', 'wc-spreadsheet-product-importer' ),
					$file['error']
				)
			);
		}

		return $file;
	}

	/**
	 * @return int|\WP_Error
	 */
	private static function insert_attachment_from_upload( array $upload, int $post_id ) {
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$info    = wp_check_filetype( $upload['file'] );
		$title   = '';
		$content = '';

		$image_meta = @wp_read_image_metadata( $upload['file'] );
		if ( $image_meta ) {
			if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
				$title = function_exists( 'wc_clean' ) ? wc_clean( $image_meta['title'] ) : sanitize_text_field( $image_meta['title'] );
			}
			if ( trim( $image_meta['caption'] ) ) {
				$content = function_exists( 'wc_clean' ) ? wc_clean( $image_meta['caption'] ) : sanitize_text_field( $image_meta['caption'] );
			}
		}

		$mime = $info['type'];
		if ( ! $mime ) {
			$mime = wp_get_image_mime( $upload['file'] ) ?: 'image/jpeg';
		}

		$attachment = array(
			'post_mime_type' => $mime,
			'guid'           => $upload['url'],
			'post_parent'    => $post_id,
			'post_title'     => $title ? $title : wp_basename( $upload['file'] ),
			'post_content'   => $content,
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

		return (int) $attachment_id;
	}
}
