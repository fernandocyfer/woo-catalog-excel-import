=== WooCommerce Spreadsheet Product Importer ===
Contributors: cyfer
Tags: woocommerce, import, bulk import, csv, xlsx, excel, products, catalog, sku, stock, variable products
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Importação e atualização em massa de produtos WooCommerce a partir de ficheiros CSV ou Excel (XLSX), com validação, relatório de erros e modelos incluídos.

== Description ==

O **WooCommerce Spreadsheet Product Importer** permite importar ou atualizar o catálogo da sua loja a partir de planilhas **CSV** ou **Excel (.xlsx)** — útil para migrações, sincronização com fornecedores e gestão de SKU em volume.

* **Produtos simples** — nome, descrição, preços, stock, categorias, imagens (URL), peso e dimensões.
* **Produtos variáveis e variações** — tipo de linha, SKU pai, atributos globais e atributos por variação.
* **Identificação por SKU** — se o SKU existir, o produto é atualizado; caso contrário, é criado.
* **Categorias** — criadas automaticamente quando não existem; várias categorias separadas por vírgula.
* **Imagens** — transferência para a biblioteca de media a partir de URL (falhas registadas sem bloquear o restante).
* **Registos** — resumo de criados, atualizados e erros por linha; último relatório guardado nas opções.

Compatível com **HPOS** (tabelas de encomendas personalizadas do WooCommerce).

== Installation ==

1. Envie a pasta do plugin para `wp-content/plugins/` ou instale o ZIP no WordPress.
2. Na pasta do plugin, execute `composer install --no-dev --optimize-autoloader` (necessário para ler .xlsx).
3. Ative o plugin em **Plugins**.
4. Confirme que o **WooCommerce** está ativo.
5. Vá a **WooCommerce → Spreadsheet import** para importar ou à aba **Ajuda** para descarregar modelos.

== Frequently Asked Questions ==

= Que colunas são obrigatórias? =

No modelo simples: **Nome** e **SKU**. No modelo com variações, siga as colunas do ficheiro modelo-variacoes.csv na aba Ajuda.

= Aceita ficheiros Excel? =

Sim, **.xlsx** e **.csv**, através da biblioteca PhpSpreadsheet (instalada via Composer).

= O que acontece se uma imagem falhar? =

O produto é guardado na mesma; o erro aparece no relatório da importação.

== Screenshots ==

1. Ecrã de importação com separador Ajuda e modelos CSV.
2. Relatório após importação (criados, atualizados, erros).

== Changelog ==

= 1.1.0 =
* Melhorias de interface (layout em duas colunas, estilo atualizado).
* SEO: readme.txt, meta na lista de plugins, descrição alargada.
* Suporte a cabeçalho Requires Plugins (WooCommerce).

= 1.0.0 =
* Lançamento inicial: importação CSV/XLSX, simples e variáveis, logs.
