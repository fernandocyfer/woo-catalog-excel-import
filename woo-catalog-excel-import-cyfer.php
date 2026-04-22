<?php
/**
 * Plugin Name: WooCommerce Spreadsheet Product Importer
 * Plugin URI: https://www.cyfer.com.br
 * Description: Importação em massa para WooCommerce: produtos simples e variáveis a partir de Excel (.xlsx) ou CSV. Atualização por SKU, categorias, stock, preços e imagens por URL. Ideal para catálogo, migração e sincronização de planilhas.
 * Version: 1.0.0
 * Contributors: cyferweb
 * Author: Cyfer Development
 * Author URI: https://www.cyfer.com.br
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-spreadsheet-product-importer
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 *
 * @package WCSPI
 */

defined( 'ABSPATH' ) || exit;

define( 'WCSPI_VERSION', '1.0.0' );
define( 'WCSPI_FILE', __FILE__ );
define( 'WCSPI_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCSPI_URL', plugin_dir_url( __FILE__ ) );
define( 'WCSPI_SLUG', 'wc-spreadsheet-product-importer' );
define( 'WCSPI_PREFIX', 'wcspi_' );

if ( ! is_readable( WCSPI_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'WooCommerce Spreadsheet Product Importer: the library folder is missing (vendor/). Reinstall the plugin from the distribution ZIP, which includes PhpSpreadsheet. If you are developing from a Git clone, run: composer install --no-dev --optimize-autoloader',
				'wc-spreadsheet-product-importer'
			);
			echo '</p></div>';
		}
	);
	return;
}

require_once WCSPI_DIR . 'vendor/autoload.php';
require_once WCSPI_DIR . 'includes/class-plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		WCSPI\Includes\Plugin::instance();
	},
	11
);
