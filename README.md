# WooCommerce Spreadsheet Product Importer

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-8892BF.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759B.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-96588A.svg)](https://woocommerce.com/)

Importação e atualização em massa de produtos **WooCommerce** a partir de planilhas **CSV** ou **Excel (.xlsx)**, com validação, relatório por linha, modelos incluídos e exportação do catálogo no mesmo formato.

**Autor:** [Cyfer Development](https://www.cyfer.com.br) · **Licença:** GPL-2.0-or-later

---

## Funcionalidades

| Área | Descrição |
|------|-----------|
| **Produtos simples** | Nome, descrições, preços, stock, categorias, imagens por URL, peso e dimensões. |
| **Variáveis e variações** | Linhas `variavel` / `variacao`, SKU pai, atributos globais e por variação. |
| **SKU** | Identificador único: SKU existente **atualiza** o produto; SKU novo **cria** entrada. |
| **Categorias** | Criação automática quando não existem; várias categorias separadas por vírgula. |
| **Imagens** | Descarga a partir de URL para a biblioteca de media (erros registados sem bloquear o restante). |
| **Relatório** | Resumo de criados, atualizados e erros por linha; último relatório guardado no admin. |
| **Exportar** | Aba dedicada: CSV alinhado ao modelo com variações, para editar e reimportar. |
| **HPOS** | Declara compatibilidade com encomendas em tabelas personalizadas (WooCommerce). |

Cabeçalhos de coluna aceites em **português** ou **inglês** (mapeamento centralizado no plugin).

---

## Requisitos

- **WordPress** 6.2 ou superior  
- **WooCommerce** 8.0 ou superior (plugin marcado como dependência)  
- **PHP** 8.0 ou superior  
- **Composer** — necessário para instalar o PhpSpreadsheet (leitura de `.xlsx`)

---

## Instalação

### A partir do código (ex.: GitHub)

1. Clone ou copie o repositório para `wp-content/plugins/woo-catalog-excel-import-cyfer`.
2. Na raiz do plugin, instale as dependências de produção:

```bash
composer install --no-dev --optimize-autoloader
```

3. No WordPress, ative o plugin em **Plugins**.
4. Confirme que o **WooCommerce** está ativo.

### Onde usar no admin

**WooCommerce → Spreadsheet import**

- **Importar** — envio de `.csv` ou `.xlsx`.  
- **Ajuda** — modelos CSV (`modelo-padrao.csv`, `modelo-variacoes.csv`) e documentação de colunas.  
- **Exportar** — descarga do catálogo atual no formato compatível com a importação.

---

## Utilização resumida

1. Prepare a planilha com as colunas mínimas (**Nome** e **SKU**; no modelo com variações siga o ficheiro modelo).  
2. Envie o ficheiro e execute a importação.  
3. Consulte o relatório (criados / atualizados / erros).  
4. Para uma cópia editável do catálogo existente, use a aba **Exportar**.

---

## Comportamento da importação

1. Validação do ficheiro (tipo, tamanho, colunas obrigatórias).  
2. Validação linha a linha (nome, SKU, preços, stock, status, URLs de imagem quando preenchidas).  
3. Criação ou atualização de produtos com base no **SKU** (o tipo na loja deve corresponder: simples, variável ou variação).  
4. Opções e hooks usam o prefixo `wcspi_`; text domain: `wc-spreadsheet-product-importer`.

---

## Desenvolvimento

- **Namespace PHP:** `WCSPI\`  
- **Estrutura principal:** `app/` (serviços, repositórios, validadores, helpers), `views/`, `includes/`, `templates/`.

Para contribuir: fork, branch por funcionalidade ou correção, e pull request com descrição clara das alterações.

---

## Apoio e doações

Se este plugin for útil para o seu projeto, pode apoiar o desenvolvimento contínuo:

**[Buy Me a Coffee — Cyfer Development](https://buymeacoffee.com/cyfer)**

O apoio ajuda a manter compatibilidade com novas versões do WordPress e WooCommerce, documentação e melhorias de qualidade.

---

## Licença

Este projeto está licenciado sob a **GNU General Public License v2.0 or later**.  
Consulte o ficheiro [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) (referência oficial) ou a cópia incluída no repositório, se existir.

---

## Ligações

- [Cyfer Development](https://www.cyfer.com.br)  
- [Buy Me a Coffee](https://buymeacoffee.com/cyfer)
