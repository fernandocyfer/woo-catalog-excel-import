<?php
/**
 * UI e pedidos de importação no admin.
 *
 * @package WCSPI
 */

namespace WCSPI\Controllers\Admin;

use WCSPI\Config\Plugin_Config;
use WCSPI\Services\Import_Log_Service;
use WCSPI\Services\Import_Service;
use WCSPI\Services\Product_Export_Service;

defined( 'ABSPATH' ) || exit;

final class Import_Controller {

	private const ACTION        = 'wcspi_run_import';
	private const EXPORT_ACTION = 'wcspi_export_products';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		/*
		 * Registado sempre: admin-post.php verifica has_action antes de despachar.
		 * Se o hook não existir, o núcleo responde com wp_die('', 400) — corpo vazio e ERR_INVALID_RESPONSE no browser.
		 */
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_post' ), 0 );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ), 0 );
	}

	/**
	 * Menu e assets só quando o WooCommerce está disponível.
	 */
	public function register_woocommerce_dependent_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Spreadsheet product import', 'wc-spreadsheet-product-importer' ),
			__( 'Spreadsheet import', 'wc-spreadsheet-product-importer' ),
			'manage_woocommerce',
			'wcspi-import',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_wcspi-import' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'wcspi-admin',
			WCSPI_URL . 'assets/admin.css',
			array(),
			WCSPI_VERSION
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'wc-spreadsheet-product-importer' ) );
		}

		$flash = get_transient( $this->flash_key() );
		if ( false !== $flash ) {
			delete_transient( $this->flash_key() );
		}

		$last_log = ( new Import_Log_Service() )->get_last();

		$active_tab = 'import';
		if ( ! empty( $_GET['tab'] ) ) {
			$t = sanitize_key( wp_unslash( $_GET['tab'] ) );
			if ( in_array( $t, array( 'help', 'export' ), true ) ) {
				$active_tab = $t;
			}
		}

		$model_urls = array(
			'padrao'    => WCSPI_URL . 'templates/modelo-padrao.csv',
			'variacoes' => WCSPI_URL . 'templates/modelo-variacoes.csv',
		);

		include WCSPI_DIR . 'views/admin-import.php';
	}

	public function handle_export(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'wc-spreadsheet-product-importer' ) );
		}

		check_admin_referer( self::EXPORT_ACTION, 'wcspi_export_nonce' );

		if ( ! class_exists( \WooCommerce::class ) ) {
			wp_die(
				esc_html__( 'O WooCommerce tem de estar ativo para exportar produtos.', 'wc-spreadsheet-product-importer' ),
				esc_html__( 'WooCommerce inativo', 'wc-spreadsheet-product-importer' ),
				array( 'response' => 503 )
			);
		}

		$this->prepare_streaming_response();

		( new Product_Export_Service() )->send_csv_download();

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		exit;
	}

	public function handle_post(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'wc-spreadsheet-product-importer' ) );
		}

		check_admin_referer( self::ACTION, 'wcspi_nonce' );

		if ( ! class_exists( \WooCommerce::class ) ) {
			$this->redirect_with_flash(
				array(
					'type'    => 'error',
					'message' => __( 'O WooCommerce tem de estar ativo para importar.', 'wc-spreadsheet-product-importer' ),
				)
			);
		}

		$file = isset( $_FILES['wcspi_file'] ) ? $_FILES['wcspi_file'] : null;
		if ( ! is_array( $file ) || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->redirect_with_flash(
				array(
					'type'    => 'error',
					'message' => __( 'Envio de ficheiro inválido.', 'wc-spreadsheet-product-importer' ),
				)
			);
		}

		if ( (int) $file['size'] > Plugin_Config::max_upload_bytes() ) {
			$this->redirect_with_flash(
				array(
					'type'    => 'error',
					'message' => __( 'Ficheiro excede o tamanho máximo permitido.', 'wc-spreadsheet-product-importer' ),
				)
			);
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, Plugin_Config::allowed_extensions(), true ) ) {
			$this->redirect_with_flash(
				array(
					'type'    => 'error',
					'message' => __( 'Tipo de ficheiro não permitido. Use .csv ou .xlsx.', 'wc-spreadsheet-product-importer' ),
				)
			);
		}

		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( empty( $check['ext'] ) || ! in_array( strtolower( (string) $check['ext'] ), Plugin_Config::allowed_extensions(), true ) ) {
			$this->redirect_with_flash(
				array(
					'type'    => 'error',
					'message' => __( 'Não foi possível validar o tipo do ficheiro.', 'wc-spreadsheet-product-importer' ),
				)
			);
		}

		$service = new Import_Service();
		$result  = $service->run( $file['tmp_name'] );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_flash(
				array(
					'type'    => 'error',
					'message' => $result->get_error_message(),
				)
			);
		}

		( new Import_Log_Service() )->save( $result );

		$this->redirect_with_flash(
			array(
				'type' => 'success',
				'data' => $result->to_array(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function redirect_with_flash( array $payload ): void {
		set_transient( $this->flash_key(), $payload, 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=wcspi-import' ) );
		exit;
	}

	private function flash_key(): string {
		return WCSPI_PREFIX . 'flash_' . get_current_user_id();
	}

	/**
	 * Evita cabeçalhos inválidos ou corpo corrompido (avisos HTML, buffers, compressão zlib).
	 */
	private function prepare_streaming_response(): void {
		if ( function_exists( 'ini_get' ) && ini_get( 'zlib.output_compression' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@ini_set( 'zlib.output_compression', 'Off' );
		}

		while ( ob_get_level() > 0 ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@ob_end_clean();
		}
	}
}
