# AI Internal Links SEO

Plugin WordPress para link building interno automatizado usando Inteligencia Artificial (Gemini API).

## Descricao

O AI Internal Links SEO analisa o conteudo dos seus posts e sugere links internos relevantes usando IA. O plugin identifica oportunidades de link building interno, sugere textos ancora naturais e permite aplicar os links com um clique.

## Requisitos

- WordPress 5.8 ou superior
- PHP 7.4 ou superior
- Chave de API do Google Gemini

## Instalacao

1. Faca upload da pasta `ai-internal-links-seo` para o diretorio `/wp-content/plugins/`
2. Ative o plugin atraves do menu 'Plugins' no WordPress
3. Acesse 'AI Internal Links' > 'Configuracoes' no menu admin
4. Insira sua API Key do Gemini
5. Configure os tipos de post que deseja analisar

## Obtendo a API Key

1. Acesse [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Faca login com sua conta Google
3. Clique em "Create API Key"
4. Copie a chave gerada
5. Cole no campo "API Key" nas configuracoes do plugin

## Como Usar

### 1. Configurar o Plugin

- Acesse **AI Internal Links > Configuracoes**
- Insira sua API Key do Gemini
- Escolha o modelo Gemini desejado (padrao recomendado: Gemini 2.5 Flash)
- Selecione quais tipos de post podem ser analisados (ex: Posts, Paginas)
- Defina o numero maximo de links por post (recomendado: 2-3)
- Ajuste o score minimo de confianca (recomendado: 70%)

Modelos disponiveis nas configuracoes:

- Gemini 2.5 Flash (`gemini-2.5-flash`) - padrao recomendado
- Gemini 3.1 Flash-Lite (`gemini-3.1-flash-lite`) - mais rapido e economico
- Gemini 3 Flash Preview (`gemini-3-flash-preview`) - mais novo, sujeito a mudancas da API

### 2. Analisar Posts

- Acesse **AI Internal Links > Analisar Posts**
- Use os filtros para encontrar posts especificos
- Clique em "Analisar" no post desejado
- Aguarde a IA processar o conteudo
- Veja as sugestoes encontradas

### 3. Revisar Sugestoes

- Acesse **AI Internal Links > Sugestoes**
- Revise as sugestoes agrupadas por post de origem
- Veja o contexto (paragrafo onde o link sera inserido)
- Confira a justificativa da IA
- Veja o score de confianca

### 4. Aplicar Links

- Para cada sugestao, voce pode:
  - **Aplicar**: Insere o link automaticamente no post
  - **Rejeitar**: Descarta a sugestao
  - **Desfazer**: Reverte um link aplicado

## Funcionalidades

### Dashboard
- Estatisticas de sugestoes (pendentes, aplicadas, rejeitadas)
- Acoes rapidas
- Status da conexao com API

### Analise de Posts
- Lista de posts com filtros por tipo e categoria
- Analise individual via AJAX
- Visualizacao de sugestoes existentes

### Gerenciamento de Sugestoes
- Filtro por status (pendentes, aplicadas, rejeitadas)
- Sugestoes exibidas em grupos por post de origem
- Preview do paragrafo com texto ancora destacado
- Justificativa da IA para cada sugestao
- Score de confianca
- Acoes de aplicar/rejeitar/desfazer

### Configuracoes
- API Key segura (campo password)
- Selecao do modelo Gemini usado nas analises
- Selecao de post types
- Limites personalizaveis
- Teste de conexao com API

## Boas Praticas de SEO

O plugin segue as melhores praticas de SEO para links internos:

- Limite de 2-3 links internos por post
- Textos ancora variados e naturais
- Links apenas quando ha relevancia semantica real
- Evita links no primeiro paragrafo
- Prioriza links que agregam valor ao leitor

## Estrutura do Banco de Dados

O plugin cria duas tabelas:

### wp_ailseo_suggestions
Armazena as sugestoes de links:
- ID da sugestao
- ID do post de origem
- Texto ancora sugerido
- ID do post de destino
- Contexto do paragrafo
- Score de confianca
- Status (pending, rejected, applied)
- Data de criacao e aplicacao

Ao reanalisar um post, sugestoes pendentes antigas do mesmo post sao removidas antes de salvar as novas. Sugestoes aplicadas e rejeitadas sao preservadas; sugestoes iguais ja rejeitadas para o mesmo post, destino e texto ancora nao sao recriadas.

### wp_ailseo_log
Registra acoes para auditoria:
- ID do post
- ID da sugestao
- Acao realizada
- Detalhes
- Usuario
- Data

## Seguranca

- API Keys armazenadas de forma segura com wp_options
- Verificacao de nonces em todos os formularios
- Verificacao de capabilities (manage_options)
- Sanitizacao de todos os inputs
- Escape de todos os outputs
- Prepared statements para queries SQL

## Debug

Em ambiente de desenvolvimento, ative o WP_DEBUG para ver logs:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Os logs sao salvos em `wp-content/debug.log` com prefixo `[AI Internal Links SEO]`.

## Changelog

### 1.0.0
- Versao inicial
- Integracao com Gemini API
- Analise individual de posts
- Gerenciamento de sugestoes
- Sistema de aplicacao/rollback de links

## Suporte

Para reportar bugs ou solicitar funcionalidades, entre em contato com a equipe PIU Digital.

## Licenca

GPL v2 ou posterior - https://www.gnu.org/licenses/gpl-2.0.html
