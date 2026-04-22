<?php
/**
 * Bootstrap do plugin.
 *
 * @package WCSPI
 */

namespace WCSPI\Includes;

use WCSPI\Controllers\Admin\Import_Controller;

require_once __DIR__ . '/class-plugin-seo.php';

defined( 'ABSPATH' ) || exit;

/**
 * Ponto de entrada: WooCommerce, textdomain, admin.
 */
final class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compat' ) );

		$import_ui = Import_Controller::instance();

		if ( ! $this->is_woocommerce_loaded() ) {
			add_action( 'admin_notices', array( $this, 'notice_woocommerce_required' ) );
			return;
		}

		add_action( 'init', array( $this, 'load_textdomain' ) );
		Plugin_Seo::register();
		$import_ui->register_woocommerce_dependent_hooks();
	}

	public function declare_hpos_compat(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCSPI_FILE, true );
		}
	}

	private function is_woocommerce_loaded(): bool {
		return class_exists( \WooCommerce::class );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wc-spreadsheet-product-importer',
			false,
			dirname( plugin_basename( WCSPI_FILE ) ) . '/languages'
		);
	}

	public function notice_woocommerce_required(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'WooCommerce Spreadsheet Product Importer requer o WooCommerce ativo.', 'wc-spreadsheet-product-importer' );
		echo '</p></div>';
	}
}
