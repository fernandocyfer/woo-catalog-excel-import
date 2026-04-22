<?php
/**
 * SEO e ligações úteis na lista de plugins (WordPress.org / descoberta).
 *
 * @package WCSPI
 */

namespace WCSPI\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Meta links, atalhos de ação e texto otimizado para motores e utilizadores.
 */
final class Plugin_Seo {

	public static function register(): void {
		add_filter( 'plugin_row_meta', array( self::class, 'row_meta' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( WCSPI_FILE ), array( self::class, 'action_links' ) );
	}

	/**
	 * @param list<string> $links
	 * @return list<string>
	 */
	public static function row_meta( array $links, string $file ): array {
		if ( plugin_basename( WCSPI_FILE ) !== $file ) {
			return $links;
		}

		$doc_url = apply_filters(
			'wcspi_docs_url',
			'https://plugins.cyfer.com.br'
		);

		$links[] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $doc_url ),
			esc_html__( 'Documentação e Plugins Premium', 'wc-spreadsheet-product-importer' )
		);

		$links[] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=wcspi-import&tab=help' ) ),
			esc_html__( 'Modelos CSV e Excel (XLSX)', 'wc-spreadsheet-product-importer' )
		);

		return $links;
	}

	/**
	 * @param list<string> $links
	 * @return list<string>
	 */
	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=wcspi-import' ) ),
				esc_html__( 'Importar produtos', 'wc-spreadsheet-product-importer' )
			)
		);
		return $links;
	}
}
