# AGENTS.md

Guia para agentes que forem trabalhar neste plugin WordPress.

## Visão Geral

Este repositório contém o plugin **AI Internal Links SEO**, um plugin WordPress para sugerir e aplicar links internos usando a API Gemini. O plugin roda principalmente no painel administrativo do WordPress, cria tabelas próprias no banco e usa AJAX para testar a API, analisar posts, aplicar sugestões, rejeitar sugestões e desfazer links aplicados.

Requisitos declarados:

- WordPress 5.8 ou superior.
- PHP 7.4 ou superior.
- Chave da API Google Gemini configurada no painel do plugin.

## Estrutura do Projeto

- `ai-internal-links-seo.php`: arquivo principal do plugin, cabeçalho WordPress, constantes, autoloader, hooks de ativação/desativação e inicialização.
- `includes/class-plugin.php`: orquestra os componentes principais e registra os handlers AJAX.
- `includes/class-ai-client.php`: comunicação com a Gemini API, montagem do prompt e validação da resposta JSON.
- `includes/class-analyzer.php`: prepara conteúdo de posts, busca posts disponíveis, chama a IA e salva sugestões.
- `includes/class-link-applier.php`: aplica, rejeita e desfaz sugestões no conteúdo dos posts.
- `includes/class-cache.php`: cache via transients do WordPress.
- `admin/class-admin.php`: menus, settings, assets e renderização das páginas administrativas.
- `admin/views/*.php`: templates das telas de dashboard, análise, sugestões e configurações.
- `admin/assets/js/admin.js`: interações AJAX do painel.
- `admin/assets/css/admin.css`: estilos administrativos.
- `languages/ai-internal-links-seo.pot`: template de tradução.
- `README.md`: documentação funcional para usuários.

## Convenções de Código

- O namespace PHP do plugin é `AIInternalLinksSEO`.
- Classes seguem o padrão `Class_Name` e arquivos seguem `class-class-name.php`.
- Use as APIs nativas do WordPress sempre que possível.
- Mantenha verificações de acesso direto no início dos arquivos PHP:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

- Preserve o text domain `ai-internal-links-seo` em todas as strings traduzíveis.
- Prefira funções WordPress para sanitização, validação e escape:
  - Entrada: `sanitize_text_field`, `sanitize_textarea_field`, `absint`, `wp_unslash`.
  - Saída HTML: `esc_html`, `esc_attr`, `esc_url`, `wp_kses`.
  - Banco: `$wpdb->prepare`, formatos em `$wpdb->insert` e `$wpdb->update`.
- Não exponha API keys em logs, HTML, commits ou mensagens de erro.

## Fluxo Principal

1. O WordPress carrega `ai-internal-links-seo.php`.
2. `ailseo_init()` instancia `AIInternalLinksSEO\Plugin`.
3. `Plugin::run()` instancia cache, cliente de IA, analisador, aplicador de links e, no admin, a classe `Admin`.
4. `Admin` registra menus, opções e assets.
5. O JavaScript em `admin/assets/js/admin.js` chama endpoints AJAX registrados em `Plugin`.
6. `Analyzer` prepara o post e a lista de posts de destino.
7. `AI_Client` envia o prompt para o Gemini e espera JSON válido.
8. Sugestões válidas são gravadas em `wp_ailseo_suggestions`.
9. `Link_Applier` aplica, rejeita ou desfaz a sugestão, atualizando o post e registrando log.

## Banco de Dados

Na ativação, o plugin cria:

- `{prefix}_ailseo_suggestions`: sugestões de links, status, contexto original, parágrafo modificado e dados de aplicação.
- `{prefix}_ailseo_log`: auditoria de ações executadas.

Cuidados:

- Alterações de schema devem passar por `dbDelta`.
- Atualize `AILSEO_VERSION` e `ailseo_db_version` quando houver migração real.
- Ao mexer em status, considere os estados atualmente usados: `pending`, `applied` e `rejected`. O README também cita `approved`, mas o fluxo atual não usa esse estado.

## Segurança

Mantenha estes padrões em qualquer alteração:

- Todos os handlers AJAX devem validar nonce com `check_ajax_referer( 'ailseo_nonce', 'nonce' )`.
- Ações administrativas devem exigir `current_user_can( 'manage_options' )`.
- Dados vindos de `$_GET` e `$_POST` devem ser tratados com `wp_unslash` e sanitizados.
- Queries com valores dinâmicos devem usar `$wpdb->prepare`.
- Saídas em views devem ser escapadas no contexto correto.
- Links aplicados no conteúdo devem usar URL e título escapados.

## Integração com IA

`AI_Client::GEMINI_API_URL` aponta para `gemini-2.0-flash:generateContent`.

Ao alterar prompts ou parsing:

- Exija resposta JSON sem markdown sempre que possível.
- Continue removendo cercas de markdown antes de `json_decode`, pois modelos podem retorná-las mesmo quando instruídos a não fazer.
- Valide que o `anchor_text` existe no parágrafo.
- Valide existência e publicação do post de destino.
- Respeite o score mínimo configurado em `ailseo_min_confidence_score`.

## Aplicação e Rollback de Links

`Link_Applier` depende do parágrafo original salvo em banco. Mudanças manuais no post depois da análise podem impedir a aplicação ou o rollback.

Ao mexer nessa área:

- Teste conteúdo com HTML, acentos e espaços alterados.
- Evite substituir todas as ocorrências do texto âncora; o comportamento esperado é aplicar apenas a primeira ocorrência no parágrafo sugerido.
- Preserve `paragraph_context` e `paragraph_modified`, pois são necessários para desfazer alterações.

## Frontend/Admin

- O plugin usa jQuery e `wp_localize_script` para `ajax_url`, nonce e textos i18n.
- Os ícones são Dashicons, consistentes com o admin do WordPress.
- Evite dependências JS/CSS externas sem necessidade.
- Ao criar novos textos em JS, prefira passá-los via `wp_localize_script` para manter tradução.

## Internacionalização

- Use `__()`, `_e()`, `esc_html__()`, `esc_attr__()` e `_n()` conforme o contexto.
- Mantenha o text domain `ai-internal-links-seo`.
- Se novas strings forem adicionadas, atualize `languages/ai-internal-links-seo.pot`.
- Atenção: alguns arquivos parecem ter texto com mojibake, por exemplo `ConfiguraÃ§Ãµes`. Antes de alterar textos em massa, confirme a codificação dos arquivos e evite misturar correções de encoding com mudanças funcionais.

## Testes e Validação

Não há suíte automatizada ou arquivos de configuração de testes neste repositório.

Validação mínima recomendada após mudanças:

- Rodar lint PHP, se houver PHP disponível no ambiente:

```bash
php -l ai-internal-links-seo.php
php -l includes/class-plugin.php
php -l includes/class-ai-client.php
php -l includes/class-analyzer.php
php -l includes/class-link-applier.php
php -l includes/class-cache.php
php -l admin/class-admin.php
```

- Ativar o plugin em uma instalação WordPress local.
- Confirmar que as tabelas são criadas na ativação.
- Abrir as telas `AI Internal Links > Dashboard`, `Analisar Posts`, `Sugestões` e `Configurações`.
- Testar nonce/capability indiretamente pelas ações AJAX.
- Testar conexão com uma API key válida em ambiente seguro.
- Analisar um post, revisar sugestões, aplicar, rejeitar e desfazer.

## Git e Fluxo de Trabalho

- A branch principal esperada é `main`.
- Antes de editar, confira `git status --short --branch`.
- Não reverta mudanças de terceiros sem pedido explícito.
- Faça commits pequenos e descritivos.
- Não commite API keys, dumps de banco, logs, arquivos temporários ou conteúdo gerado pelo WordPress fora do escopo do plugin.

## Cuidados Antes de Alterar

- Este plugin altera conteúdo real de posts via `wp_update_post`; trate mudanças em `Link_Applier` como sensíveis.
- Mudanças em prompt ou parsing podem afetar custo, latência e qualidade das sugestões.
- Mudanças em queries administrativas devem considerar sites com muitos posts e muitas sugestões.
- Mudanças em schema precisam preservar instalações existentes.
- Mantenha compatibilidade com PHP 7.4, conforme cabeçalho do plugin.
