# AI Internal Links SEO

Plugin WordPress para link building interno automatizado usando Inteligência Artificial (Gemini API).

## Descrição

O AI Internal Links SEO analisa o conteúdo dos seus posts e sugere links internos relevantes usando IA. O plugin identifica oportunidades de link building interno, sugere textos âncora naturais e permite aplicar os links com um clique.

## Requisitos

- WordPress 5.8 ou superior
- PHP 7.4 ou superior
- Chave de API do Google Gemini

## Instalação

1. Faça upload da pasta `ai-internal-links-seo` para o diretório `/wp-content/plugins/`
2. Ative o plugin através do menu `Plugins` no WordPress
3. Acesse `AI Internal Links > Configurações` no menu admin
4. Insira sua API Key do Gemini
5. Configure os tipos de post que deseja analisar

## Obtendo a API Key

1. Acesse [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Faça login com sua conta Google
3. Clique em `Create API Key`
4. Copie a chave gerada
5. Cole no campo `API Key` nas configurações do plugin

## Como Usar

### 1. Configurar o Plugin

- Acesse **AI Internal Links > Configurações**
- Insira sua API Key do Gemini
- Escolha o modelo Gemini desejado (padrão recomendado: Gemini 2.5 Flash)
- Selecione quais tipos de post podem ser analisados (ex: Posts, Páginas)
- Defina o número máximo de links por post (recomendado: 2-3)
- Ajuste o score mínimo de confiança (recomendado: 70%)
- Opcionalmente, marque a opção para excluir dados do plugin ao deletá-lo pelo painel do WordPress

Modelos disponíveis nas configurações:

- Gemini 2.5 Flash (`gemini-2.5-flash`) - padrão recomendado
- Gemini 3.1 Flash-Lite (`gemini-3.1-flash-lite`) - mais rápido e econômico
- Gemini 3 Flash Preview (`gemini-3-flash-preview`) - mais novo, sujeito a mudanças da API

### 2. Analisar Posts

- Acesse **AI Internal Links > Analisar Posts**
- Use os filtros para encontrar posts específicos
- Clique em `Analisar` no post desejado
- Aguarde a IA processar o conteúdo
- Veja as sugestões encontradas

### 3. Revisar Sugestões

- Acesse **AI Internal Links > Sugestões**
- Revise as sugestões agrupadas por post de origem
- Veja o contexto (parágrafo onde o link será inserido)
- Confira a justificativa da IA
- Veja o score de confiança

### 4. Aplicar Links

Para cada sugestão, você pode:

- **Aplicar**: insere o link automaticamente no post
- **Rejeitar**: descarta a sugestão
- **Desfazer**: reverte um link aplicado
- **Restaurar**: devolve uma sugestão rejeitada para o status pendente

## Funcionalidades

### Dashboard

- Estatísticas de sugestões (pendentes, aplicadas, rejeitadas)
- Ações rápidas
- Status da conexão com API

### Análise de Posts

- Lista de posts com filtros por tipo e categoria
- Análise individual via AJAX
- Visualização de sugestões existentes

### Gerenciamento de Sugestões

- Filtro por status (pendentes, aplicadas, rejeitadas)
- Sugestões exibidas em grupos por post de origem
- Preview do parágrafo com texto âncora destacado
- Justificativa da IA para cada sugestão
- Score de confiança
- Ações de aplicar, rejeitar, desfazer e restaurar

### Configurações

- API Key segura (campo password)
- Seleção do modelo Gemini usado nas análises
- Seleção de post types
- Limites personalizáveis
- Teste de conexão com API
- Opção para excluir tabelas, configurações e cache ao deletar o plugin

## Desinstalação e Remoção de Dados

Por padrão, ao deletar o plugin pelo painel do WordPress, os dados do banco são preservados.

Para remover também os dados do plugin:

1. Acesse **AI Internal Links > Configurações**
2. Marque **Excluir tabelas, configurações e cache ao deletar o plugin**
3. Salve as configurações
4. Desative e delete o plugin pelo painel do WordPress

Quando essa opção estiver marcada, o `uninstall.php` remove:

- As tabelas `{prefix}_ailseo_suggestions` e `{prefix}_ailseo_log`
- As opções `ailseo_*` registradas pelo plugin
- Os transients de cache com prefixo `ailseo_`
- O hook agendado `ailseo_scheduled_analysis`

Links já aplicados no conteúdo dos posts não são removidos automaticamente.

## Boas Práticas de SEO

O plugin segue as melhores práticas de SEO para links internos:

- Limite de 2-3 links internos por post
- Textos âncora variados e naturais
- Links apenas quando há relevância semântica real
- Evita links no primeiro parágrafo
- Prioriza links que agregam valor ao leitor

## Estrutura do Banco de Dados

O plugin cria duas tabelas:

### wp_ailseo_suggestions

Armazena as sugestões de links:

- ID da sugestão
- ID do post de origem
- Texto âncora sugerido
- ID do post de destino
- Contexto do parágrafo
- Score de confiança
- Status (pending, rejected, applied)
- Data de criação e aplicação

Ao reanalisar um post, sugestões pendentes antigas do mesmo post são removidas antes de salvar as novas. Sugestões aplicadas e rejeitadas são preservadas; sugestões iguais já rejeitadas para o mesmo post, destino e texto âncora não são recriadas.

### wp_ailseo_log

Registra ações para auditoria:

- ID do post
- ID da sugestão
- Ação realizada
- Detalhes
- Usuário
- Data

## Segurança

- API Keys armazenadas de forma segura com `wp_options`
- Verificação de nonces em todos os formulários
- Verificação de capabilities (`manage_options` para Configurações; `edit_others_posts` para gestão de sugestões)
- Sanitização de todos os inputs
- Escape de todos os outputs
- Prepared statements para queries SQL

## Debug

Em ambiente de desenvolvimento, ative o `WP_DEBUG` para ver logs:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Os logs são salvos em `wp-content/debug.log` com prefixo `[AI Internal Links SEO]`.

## Changelog

### 1.3.0

- Coluna **Data** da página *Analisar Posts* agora é clicável e permite ordenar do mais novo para o mais antigo ou do mais antigo para o mais novo

### 1.2.0

- Editores agora podem acessar Dashboard, Analisar Posts e Sugestões (capability `edit_others_posts`)
- Página de Configurações e teste de conexão com a API continuam restritos a administradores (`manage_options`), preservando o sigilo da API Key

### 1.1.3

- Teste atualização

### 1.1.2

- Teste atualização

### 1.1.1

- Teste atualização

### 1.1.0

- Botão **Restaurar** em sugestões rejeitadas para devolvê-las ao status pendente
- Ação registrada no log de auditoria como `link_restored`

### 1.0.0

- Versão inicial
- Integração com Gemini API
- Análise individual de posts
- Gerenciamento de sugestões
- Sistema de aplicação/rollback de links
- Opção para excluir dados do plugin ao deletá-lo

## Suporte

Para reportar bugs ou solicitar funcionalidades, entre em contato com a equipe da Agência PiU (https://agenciapiu.com.br).

## Licença

GPL v2 ou posterior - https://www.gnu.org/licenses/gpl-2.0.html
