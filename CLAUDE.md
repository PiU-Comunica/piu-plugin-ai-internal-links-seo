# CLAUDE.md

Diretrizes para o Claude Code trabalhando neste plugin. Use este arquivo em conjunto com `AGENTS.md`, que tem o detalhamento técnico completo (estrutura, banco, segurança, fluxo de IA, etc.). **Sempre leia `AGENTS.md` antes de mudanças não triviais.**

## Stack rápida

- Plugin WordPress procedural-OO clássico, namespace `AIInternalLinksSEO`.
- PHP 7.4+, WordPress 5.8+, jQuery no admin, sem build step.
- Integração com Gemini API (`includes/class-ai-client.php`).
- Tabelas próprias: `{prefix}_ailseo_suggestions` e `{prefix}_ailseo_log`.

## Comandos úteis

- Lint PHP de um arquivo: `php -l caminho/do/arquivo.php`
- Não há suíte de testes automatizada — validação é manual no WordPress local.

## Regras de ouro

1. **Não altere conteúdo de post** fora de `Link_Applier`. Mudanças em `apply_suggestion` / `undo_suggestion` são sensíveis: preservam `paragraph_context` e `paragraph_modified` para permitir rollback.
2. **Status válidos**: `pending`, `applied`, `rejected`. Se introduzir um novo, atualize a view, o JS, o changelog e o AGENTS.md.
3. **AJAX**: todo handler precisa de `check_ajax_referer( 'ailseo_nonce', 'nonce' )` + `current_user_can( 'manage_options' )`.
4. **i18n**: text domain `ai-internal-links-seo`. Strings novas → adicione em `languages/ai-internal-links-seo.pot`.
5. **Strings JS**: passe via `wp_localize_script` em `admin/class-admin.php` (chave `i18n`), nunca hardcode em `admin.js`.
6. **Versão**: ao adicionar funcionalidade, suba `Version:` no header do plugin **e** `AILSEO_VERSION` em `ai-internal-links-seo.php`. SemVer: patch (`x.y.Z`) para bugfix, minor (`x.Y.0`) para feature nova compatível, major (`X.0.0`) para breaking change. Atualize também o changelog em `README.md` e o `Project-Id-Version` no `.pot`.
7. **Modelos Gemini**: não reintroduzir `gemini-2.0-flash` (depreciado). Lista atual em `AGENTS.md`.
8. **Uninstall**: ao adicionar nova `option`/tabela/transient, atualize `uninstall.php` para remover quando `ailseo_delete_data_on_uninstall` estiver ativo.

## Padrão para nova ação em sugestão (referência rápida)

Ao adicionar uma ação tipo aplicar/rejeitar/desfazer/restaurar:

1. Método público em `includes/class-link-applier.php` retornando `array( 'success' => bool, 'message' => string )` e chamando `$this->log_action(...)` com um identificador (`link_applied`, `link_rejected`, `link_undone`, `link_restored`, ...).
2. Hook AJAX em `includes/class-plugin.php`: `register_ajax_handlers()` + método `ajax_<acao>_suggestion()` com nonce e capability.
3. Botão em `admin/views/suggestions.php` dentro do bloco `ailseo-suggestion-actions`, com `data-suggestion-id`.
4. Handler JS em `admin/assets/js/admin.js`: bind em `init`, função que faz AJAX e atualiza classes do card (`status-pending` / `status-applied` / `status-rejected`) e refaz os botões.
5. String de confirmação no `i18n` de `admin/class-admin.php`.
6. Entradas correspondentes no `.pot`.

## Identidade

- Autor: **Agência PiU** — https://agenciapiu.com.br
- Não usar variações antigas como "PIU Digital" ou `piu.digital`.
