<?php
/**
 * Página de importação (admin).
 *
 * @package WCSPI
 *
 * @var array<string, mixed>|false     $flash
 * @var array<string, mixed>|null      $last_log
 * @var string                         $active_tab
 * @var array{padrao:string,variacoes:string} $model_urls
 */

defined( 'ABSPATH' ) || exit;

$import_url  = admin_url( 'admin.php?page=wcspi-import' );
$help_url    = admin_url( 'admin.php?page=wcspi-import&tab=help' );
$export_url  = admin_url( 'admin.php?page=wcspi-import&tab=export' );
?>
<div class="wrap wcspi-wrap">
	<header class="wcspi-page-header">
		<h1 class="wcspi-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p class="wcspi-page-subtitle">
			<?php esc_html_e( 'Importação em massa WooCommerce via CSV ou Excel — produtos simples, variáveis e variações, com validação e relatório por linha.', 'wc-spreadsheet-product-importer' ); ?>
		</p>
	</header>

	<div class="wcspi-admin-container">
		<div class="wcspi-admin-content">
			<div class="wcspi-panel-card">
				<nav class="wcspi-tabs nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Secções do importador', 'wc-spreadsheet-product-importer' ); ?>">
					<a href="<?php echo esc_url( $import_url ); ?>" class="nav-tab <?php echo 'import' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Importar', 'wc-spreadsheet-product-importer' ); ?>
					</a>
					<a href="<?php echo esc_url( $help_url ); ?>" class="nav-tab <?php echo 'help' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Ajuda', 'wc-spreadsheet-product-importer' ); ?>
					</a>
					<a href="<?php echo esc_url( $export_url ); ?>" class="nav-tab <?php echo 'export' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Exportar', 'wc-spreadsheet-product-importer' ); ?>
					</a>
				</nav>

				<div class="wcspi-panel-body">
					<?php if ( 'help' === $active_tab ) : ?>
						<div class="wcspi-tab-panel wcspi-tab-help">
							<p class="wcspi-lead">
								<?php esc_html_e( 'Descarregue os modelos em CSV (UTF-8). Pode editar no Excel e guardar como «CSV UTF-8» ou usar .xlsx — o plugin aceita ambos os formatos.', 'wc-spreadsheet-product-importer' ); ?>
							</p>

							<div class="wcspi-help-section">
								<h2><?php esc_html_e( 'Produtos que já existem (atualização por SKU)', 'wc-spreadsheet-product-importer' ); ?></h2>
								<p>
									<?php esc_html_e( 'O importador usa o SKU como identificador. Se já existir um produto na loja com o mesmo SKU, essa linha não cria duplicado: o produto é atualizado com os dados da planilha e o relatório conta como «Atualizados».', 'wc-spreadsheet-product-importer' ); ?>
								</p>
								<ul class="wcspi-hints">
									<li><?php esc_html_e( 'Produto simples: o SKU tem de corresponder a um produto simples existente; caso contrário, ocorre erro (por exemplo, se o SKU for de um produto variável).', 'wc-spreadsheet-product-importer' ); ?></li>
									<li><?php esc_html_e( 'Produto variável: idem — o tipo na loja tem de ser «variável» e o SKU tem de coincidir.', 'wc-spreadsheet-product-importer' ); ?></li>
									<li><?php esc_html_e( 'Variação: o SKU da variação identifica a linha; tem de pertencer ao produto pai indicado em «SKU Pai». Se a variação existir com outro pai, a importação regista erro.', 'wc-spreadsheet-product-importer' ); ?></li>
									<li><?php esc_html_e( 'Campos preenchidos na planilha substituem nome, descrições, estado, medidas, preço normal, imagens (quando indicar URL) e categorias (quando a coluna tiver valores — as categorias são definidas conforme a lista separada por vírgulas).', 'wc-spreadsheet-product-importer' ); ?></li>
									<li><?php esc_html_e( 'Células vazias: stock, preço normal, imagem, galeria e categorias podem ficar inalterados se não preencher essas colunas. Se a coluna «Preço promocional» estiver vazia, o preço em promoção é removido do produto.', 'wc-spreadsheet-product-importer' ); ?></li>
								</ul>
							</div>

							<div class="wcspi-download-cards">
								<div class="wcspi-card">
									<h3><?php esc_html_e( 'Modelo padrão (produtos simples)', 'wc-spreadsheet-product-importer' ); ?></h3>
									<p>
										<?php esc_html_e( 'Colunas de catálogo simples: nome, descrição, preços, SKU, stock, categoria, imagens e medidas. Sem coluna «Tipo» — cada linha é um produto simples.', 'wc-spreadsheet-product-importer' ); ?>
									</p>
									<p>
										<a class="button button-primary wcspi-btn-primary" href="<?php echo esc_url( $model_urls['padrao'] ); ?>" download>
											<?php esc_html_e( 'Descarregar modelo-padrao.csv', 'wc-spreadsheet-product-importer' ); ?>
										</a>
									</p>
								</div>
								<div class="wcspi-card">
									<h3><?php esc_html_e( 'Modelo com variações', 'wc-spreadsheet-product-importer' ); ?></h3>
									<p>
										<?php esc_html_e( 'Inclui «Tipo», «SKU Pai», «Atributos globais» e «Atributos variação». Use variavel para o pai e variacao para cada combinação (preço e stock por linha).', 'wc-spreadsheet-product-importer' ); ?>
									</p>
									<ul class="wcspi-hints">
										<li><?php esc_html_e( 'Atributos globais: Tamanho|Cor', 'wc-spreadsheet-product-importer' ); ?></li>
										<li><?php esc_html_e( 'Atributos variação: Tamanho:M|Cor:Azul', 'wc-spreadsheet-product-importer' ); ?></li>
										<li><?php esc_html_e( 'O SKU da variação deve referenciar o SKU correto do produto variável.', 'wc-spreadsheet-product-importer' ); ?></li>
									</ul>
									<p>
										<a class="button button-primary wcspi-btn-primary" href="<?php echo esc_url( $model_urls['variacoes'] ); ?>" download>
											<?php esc_html_e( 'Descarregar modelo-variacoes.csv', 'wc-spreadsheet-product-importer' ); ?>
										</a>
									</p>
								</div>
							</div>

							<p class="wcspi-muted">
								<?php esc_html_e( 'Modelo alternativo com cabeçalhos em inglês:', 'wc-spreadsheet-product-importer' ); ?>
								<a href="<?php echo esc_url( WCSPI_URL . 'modelo-exemplo.csv' ); ?>" download><?php esc_html_e( 'modelo-exemplo.csv', 'wc-spreadsheet-product-importer' ); ?></a>
							</p>

							<p class="wcspi-muted">
								<?php esc_html_e( 'Para obter uma planilha com todos os produtos da loja excepto os que estão no lixo (mesmo formato de colunas que o modelo com variações), use a aba Exportar.', 'wc-spreadsheet-product-importer' ); ?>
							</p>
						</div>
					<?php elseif ( 'export' === $active_tab ) : ?>
						<div class="wcspi-tab-panel wcspi-tab-export">
							<p class="wcspi-lead">
								<?php esc_html_e( 'Gere um ficheiro CSV (UTF-8) com o catálogo actual: todos os tipos de produto WooCommerce e variações, com os mesmos cabeçalhos que «modelo-variacoes.csv», pronto para editar e voltar a importar.', 'wc-spreadsheet-product-importer' ); ?>
							</p>
							<ul class="wcspi-hints wcspi-hints--compact">
								<li><?php esc_html_e( 'Ordem das linhas: primeiro todos os produtos simples e pais variáveis; a seguir todas as variações (agrupadas por pai).', 'wc-spreadsheet-product-importer' ); ?></li>
								<li><?php esc_html_e( 'Inclui todos os estados de publicação excepto o lixo (por exemplo publicado, rascunho, pendente, privado).', 'wc-spreadsheet-product-importer' ); ?></li>
								<li><?php esc_html_e( 'Inclui artigos sem stock e em espera (encomenda), mesmo que a loja oculte fora de stock no catálogo.', 'wc-spreadsheet-product-importer' ); ?></li>
								<li><?php esc_html_e( 'Imagens: URLs directos dos ficheiros na biblioteca de media (como na importação).', 'wc-spreadsheet-product-importer' ); ?></li>
								<li><?php esc_html_e( 'Produtos agrupados ou externos aparecem como linhas sem tipo (como os simples); campos que não existam para esse tipo ficam vazios.', 'wc-spreadsheet-product-importer' ); ?></li>
								<li><?php esc_html_e( 'Catálogos muito grandes podem demorar — o limite de tempo de PHP é alargado durante a exportação.', 'wc-spreadsheet-product-importer' ); ?></li>
							</ul>
							<form class="wcspi-form wcspi-form--export" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'wcspi_export_products', 'wcspi_export_nonce' ); ?>
								<input type="hidden" name="action" value="wcspi_export_products" />
								<p>
									<?php submit_button( __( 'Descarregar CSV da loja', 'wc-spreadsheet-product-importer' ), 'primary wcspi-btn-primary', 'submit', false ); ?>
								</p>
							</form>
						</div>
					<?php else : ?>
						<div class="wcspi-tab-panel wcspi-tab-import">
							<p class="wcspi-lead">
								<?php esc_html_e( 'Envie uma planilha .csv ou .xlsx. Produtos simples e variáveis usam o SKU; cada variação tem SKU próprio e SKU do produto pai.', 'wc-spreadsheet-product-importer' ); ?>
							</p>

							<ul class="wcspi-hints wcspi-hints--compact">
								<li><?php esc_html_e( 'Mínimo: colunas Nome e SKU (ver Ajuda).', 'wc-spreadsheet-product-importer' ); ?></li>
								<li><?php esc_html_e( 'Status: publish, draft ou private.', 'wc-spreadsheet-product-importer' ); ?></li>
								<li><?php esc_html_e( 'Várias categorias: separar por vírgula.', 'wc-spreadsheet-product-importer' ); ?></li>
								<li><?php esc_html_e( 'Imagens por URL: falhas são registadas sem cancelar a importação.', 'wc-spreadsheet-product-importer' ); ?></li>
							</ul>

							<p class="wcspi-inline-actions">
								<a class="button" href="<?php echo esc_url( $help_url ); ?>">
									<?php esc_html_e( 'Modelos de planilha', 'wc-spreadsheet-product-importer' ); ?>
								</a>
							</p>

							<?php if ( is_array( $flash ) ) : ?>
								<?php if ( 'error' === ( $flash['type'] ?? '' ) ) : ?>
									<div class="wcspi-alert wcspi-alert--error notice notice-error"><p><?php echo esc_html( (string) ( $flash['message'] ?? '' ) ); ?></p></div>
								<?php elseif ( 'success' === ( $flash['type'] ?? '' ) && isset( $flash['data'] ) && is_array( $flash['data'] ) ) : ?>
									<?php
									$d = $flash['data'];
									?>
									<div class="wcspi-alert wcspi-alert--success notice notice-success wcspi-notice-success">
										<p>
											<strong><?php esc_html_e( 'Importação concluída.', 'wc-spreadsheet-product-importer' ); ?></strong>
											<?php
											printf(
												/* translators: 1: created, 2: updated, 3: errors */
												esc_html__( 'Criados: %1$d · Atualizados: %2$d · Erros: %3$d', 'wc-spreadsheet-product-importer' ),
												(int) ( $d['created'] ?? 0 ),
												(int) ( $d['updated'] ?? 0 ),
												(int) ( $d['error_count'] ?? 0 )
											);
											?>
										</p>
										<?php if ( ! empty( $d['errors'] ) && is_array( $d['errors'] ) ) : ?>
											<details class="wcspi-errors">
												<summary><?php esc_html_e( 'Ver erros por linha', 'wc-spreadsheet-product-importer' ); ?></summary>
												<ul>
													<?php foreach ( array_slice( $d['errors'], 0, 100 ) as $err ) : ?>
														<?php if ( is_array( $err ) && isset( $err['message'] ) ) : ?>
															<li><?php echo esc_html( (string) $err['message'] ); ?></li>
														<?php endif; ?>
													<?php endforeach; ?>
												</ul>
											</details>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							<?php endif; ?>

							<?php if ( is_array( $last_log ) && empty( $flash ) ) : ?>
								<section class="wcspi-last-log" aria-label="<?php esc_attr_e( 'Último relatório', 'wc-spreadsheet-product-importer' ); ?>">
									<h2><?php esc_html_e( 'Último relatório guardado', 'wc-spreadsheet-product-importer' ); ?></h2>
									<p>
										<?php
										printf(
											/* translators: 1: created, 2: updated, 3: errors */
											esc_html__( 'Criados: %1$d · Atualizados: %2$d · Erros: %3$d · Concluído: %4$s', 'wc-spreadsheet-product-importer' ),
											(int) ( $last_log['created'] ?? 0 ),
											(int) ( $last_log['updated'] ?? 0 ),
											(int) ( $last_log['error_count'] ?? 0 ),
											esc_html( (string) ( $last_log['finished_at'] ?? '' ) )
										);
										?>
									</p>
								</section>
							<?php endif; ?>

							<form class="wcspi-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
								<?php wp_nonce_field( 'wcspi_run_import', 'wcspi_nonce' ); ?>
								<input type="hidden" name="action" value="wcspi_run_import" />
								<table class="form-table wcspi-form-table" role="presentation">
									<tr>
										<th scope="row"><label for="wcspi_file"><?php esc_html_e( 'Ficheiro', 'wc-spreadsheet-product-importer' ); ?></label></th>
										<td>
											<input type="file" name="wcspi_file" id="wcspi_file" class="wcspi-file-input" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required />
											<p class="description">
												<?php
												echo esc_html(
													sprintf(
														/* translators: %s: max size */
														__( 'Tamanho máximo recomendado: %s.', 'wc-spreadsheet-product-importer' ),
														size_format( \WCSPI\Config\Plugin_Config::max_upload_bytes() )
													)
												);
												?>
											</p>
										</td>
									</tr>
								</table>
								<?php submit_button( __( 'Iniciar importação', 'wc-spreadsheet-product-importer' ), 'primary', 'submit', true, array( 'class' => 'wcspi-submit-button' ) ); ?>
							</form>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<aside class="wcspi-admin-sidebar" role="complementary" aria-label="<?php esc_attr_e( 'Recursos e ligações', 'wc-spreadsheet-product-importer' ); ?>">
			<div class="wcspi-side-box wcspi-side-box--highlight">
				<h3 class="wcspi-side-box__title"><?php esc_html_e( 'Dicas de importação', 'wc-spreadsheet-product-importer' ); ?></h3>
				<p class="wcspi-side-box__text">
					<?php esc_html_e( 'O SKU decide se o produto é criado ou atualizado: mesmo SKU que já existe na loja = atualização. Use SKUs novos só quando quiser artigos novos. Planilhas grandes podem exigir mais tempo de PHP — o plugin aumenta o limite durante o pedido.', 'wc-spreadsheet-product-importer' ); ?>
				</p>
				<ul class="wcspi-side-list">
					<li><?php esc_html_e( 'Bulk import WooCommerce com CSV ou XLSX', 'wc-spreadsheet-product-importer' ); ?></li>
					<li><?php esc_html_e( 'Exportar a loja para CSV no formato do importador', 'wc-spreadsheet-product-importer' ); ?></li>
					<li><?php esc_html_e( 'Sincronização de stock e preços por SKU', 'wc-spreadsheet-product-importer' ); ?></li>
					<li><?php esc_html_e( 'Catálogo com produtos variáveis e atributos', 'wc-spreadsheet-product-importer' ); ?></li>
				</ul>
				<a class="wcspi-side-cta wcspi-side-cta--dark" href="<?php echo esc_url( $help_url ); ?>">
					<?php esc_html_e( 'Ver modelos e formato', 'wc-spreadsheet-product-importer' ); ?>
				</a>
			</div>

			<div class="wcspi-side-box wcspi-side-box--info">
				<h3 class="wcspi-side-box__title"><?php esc_html_e( 'Cyfer Development', 'wc-spreadsheet-product-importer' ); ?></h3>
				<p class="wcspi-side-box__text">
					<?php esc_html_e( 'Este importador foi pensado para lojas que trabalham com planilhas e precisam de um fluxo viável entre Excel e a loja online.', 'wc-spreadsheet-product-importer' ); ?>
				</p>
				<a class="button button-secondary wcspi-side-link" href="<?php echo esc_url( apply_filters( 'wcspi_docs_url', 'https://www.cyfer.com.br' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Conhecer mais', 'wc-spreadsheet-product-importer' ); ?>
				</a>
				<p class="wcspi-side-foot">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: plugin version */
							__( 'Versão %s', 'wc-spreadsheet-product-importer' ),
							WCSPI_VERSION
						)
					);
					?>
				</p>
			</div>
		</aside>
	</div>
</div>
