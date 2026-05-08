<?php
/**
 * Classe cliente para comunicação com APIs de IA
 *
 * @package AIInternalLinksSEO
 */

namespace AIInternalLinksSEO;

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe AI_Client
 *
 * Responsável pela comunicação com a Gemini API
 */
class AI_Client {

    /**
     * URL base da API Gemini.
     *
     * @var string
     */
    const GEMINI_API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * Modelo padrão da API Gemini.
     *
     * @var string
     */
    const DEFAULT_GEMINI_MODEL = 'gemini-2.5-flash';

    /**
     * Timeout para requisições em segundos
     *
     * @var int
     */
    const REQUEST_TIMEOUT = 60;

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * Modelo Gemini selecionado.
     *
     * @var string
     */
    private $model;

    /**
     * Construtor
     */
    public function __construct() {
        $this->api_key = get_option( 'ailseo_api_key', '' );
        $this->model   = self::sanitize_model( get_option( 'ailseo_gemini_model', self::DEFAULT_GEMINI_MODEL ) );
    }

    /**
     * Obter modelos Gemini suportados pelo plugin.
     *
     * @return array Modelos no formato model_id => label.
     */
    public static function get_available_models() {
        return array(
            'gemini-2.5-flash'       => __( 'Gemini 2.5 Flash (recomendado)', 'ai-internal-links-seo' ),
            'gemini-3.1-flash-lite'  => __( 'Gemini 3.1 Flash-Lite (mais rápido e econômico)', 'ai-internal-links-seo' ),
            'gemini-3-flash-preview' => __( 'Gemini 3 Flash Preview (mais novo, sujeito a mudanças)', 'ai-internal-links-seo' ),
        );
    }

    /**
     * Sanitizar modelo selecionado.
     *
     * @param string $model Modelo informado.
     * @return string Modelo válido.
     */
    public static function sanitize_model( $model ) {
        $model = sanitize_text_field( $model );

        if ( array_key_exists( $model, self::get_available_models() ) ) {
            return $model;
        }

        return self::DEFAULT_GEMINI_MODEL;
    }

    /**
     * Testar conexão com a API
     *
     * @param string $temp_api_key API Key temporária para teste (opcional).
     * @param string $temp_model   Modelo temporário para teste (opcional).
     * @return array Resultado do teste.
     */
    public function test_connection( $temp_api_key = '', $temp_model = '' ) {
        // Usar chave temporária se fornecida, senão usar a armazenada
        $api_key = ! empty( $temp_api_key ) ? $temp_api_key : $this->api_key;
        $model   = ! empty( $temp_model ) ? self::sanitize_model( $temp_model ) : $this->model;

        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'API Key não configurada.', 'ai-internal-links-seo' ),
            );
        }

        // Fazer requisição de teste simples com a chave fornecida
        $response = $this->send_request( 'Olá, responda apenas com "OK" se você recebeu esta mensagem.', $api_key, $model );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'Conexão com a API estabelecida com sucesso!', 'ai-internal-links-seo' ),
        );
    }

    /**
     * Parágrafos do post atual indexados (1-based) — preenchidos durante a análise
     * para que parse_suggestions_response possa resolver paragraph_index → texto.
     *
     * @var array
     */
    private $current_paragraphs = array();

    /**
     * IDs de posts que já estão linkados no post atual ou foram rejeitados.
     *
     * @var int[]
     */
    private $excluded_target_ids = array();

    /**
     * Âncoras (lowercase) que já são links no post atual e não devem ser usadas.
     *
     * @var string[]
     */
    private $excluded_anchors = array();

    /**
     * Analisar post e obter sugestões de links
     *
     * @param array $current_post    Dados do post atual (com 'paragraphs').
     * @param array $available_posts Posts disponíveis para linking.
     * @return array|WP_Error Sugestões de links ou erro.
     */
    public function analyze_for_links( $current_post, $available_posts ) {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error(
                'no_api_key',
                __( 'API Key não configurada.', 'ai-internal-links-seo' )
            );
        }

        $paragraphs = isset( $current_post['paragraphs'] ) && is_array( $current_post['paragraphs'] )
            ? array_values( $current_post['paragraphs'] )
            : array();

        if ( empty( $paragraphs ) && isset( $current_post['content'] ) ) {
            // Compatibilidade retroativa caso 'content' seja passado em vez de 'paragraphs'.
            $paragraphs = array( (string) $current_post['content'] );
        }

        $this->current_paragraphs = $paragraphs;

        $this->excluded_target_ids = isset( $current_post['existing_target_ids'] )
            ? array_map( 'intval', (array) $current_post['existing_target_ids'] )
            : array();

        $this->excluded_anchors = isset( $current_post['existing_anchors'] )
            ? array_map( 'mb_strtolower', array_map( 'strval', (array) $current_post['existing_anchors'] ) )
            : array();

        // Construir prompt
        $prompt = $this->build_analysis_prompt( $current_post, $available_posts, $paragraphs );

        // Enviar requisição
        $response = $this->send_request( $prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Processar resposta
        $suggestions = $this->parse_suggestions_response( $response );

        if ( is_wp_error( $suggestions ) ) {
            $this->log( 'Primeira tentativa retornou JSON inválido. Reenviando prompt com instruções de recuperação.' );

            $retry_response = $this->send_request( $this->build_recovery_prompt( $prompt ) );

            if ( ! is_wp_error( $retry_response ) ) {
                $retry_suggestions = $this->parse_suggestions_response( $retry_response );

                if ( ! is_wp_error( $retry_suggestions ) ) {
                    return $retry_suggestions;
                }
            }
        }

        return $suggestions;
    }

    /**
     * Construir prompt para análise de links
     *
     * @param array $current_post    Dados do post atual.
     * @param array $available_posts Posts disponíveis para linking.
     * @param array $paragraphs      Parágrafos numerados (1-based no prompt).
     * @return string Prompt formatado.
     */
    private function build_analysis_prompt( $current_post, $available_posts, $paragraphs ) {
        $max_links = absint( get_option( 'ailseo_max_links_per_post', 3 ) );
        $max_links = min( 10, max( 1, $max_links ) );
        $min_score = absint( get_option( 'ailseo_min_confidence_score', 70 ) );

        // Parágrafos numerados [P1], [P2], ...
        $numbered_content = '';
        foreach ( $paragraphs as $idx => $text ) {
            $numbered_content .= sprintf( "[P%d] %s\n\n", $idx + 1, $text );
        }
        $numbered_content = rtrim( $numbered_content );

        // Lista compacta de candidatos.
        $available_posts_text = '';
        foreach ( $available_posts as $post ) {
            $excerpt    = wp_trim_words( $post['excerpt'], 25, '...' );
            $taxonomies = isset( $post['taxonomies'] ) ? trim( $post['taxonomies'] ) : '';
            $tax_part   = '' !== $taxonomies ? sprintf( ' | Categorias/tags: %s', $taxonomies ) : '';

            $available_posts_text .= sprintf(
                "- ID:%d | %s%s | %s\n",
                $post['id'],
                $post['title'],
                $tax_part,
                $excerpt
            );
        }

        $current_taxonomies = isset( $current_post['taxonomies'] ) ? trim( $current_post['taxonomies'] ) : '';
        $current_tax_line   = '' !== $current_taxonomies
            ? "Categorias/tags do post atual: {$current_taxonomies}\n"
            : '';

        // Exclusões: posts já linkados/rejeitados e âncoras já usadas como link.
        $excluded_ids_line = '';
        if ( ! empty( $this->excluded_target_ids ) ) {
            $excluded_ids_line = 'IDs proibidos (já linkados ou rejeitados): '
                . implode( ', ', array_map( 'intval', $this->excluded_target_ids ) )
                . "\n";
        }

        $excluded_anchors_line = '';
        if ( ! empty( $this->excluded_anchors ) ) {
            $sample = array_slice( $this->excluded_anchors, 0, 30 );
            $excluded_anchors_line = 'Âncoras proibidas (já são links): "'
                . implode( '", "', $sample )
                . "\"\n";
        }

        $total_paragraphs = count( $paragraphs );

        $prompt = <<<PROMPT
Tarefa: identificar oportunidades de links internos relevantes em um post.

## POST ATUAL

Título: {$current_post['title']}
{$current_tax_line}
Conteúdo (parágrafos numerados de [P1] a [P{$total_paragraphs}]):

{$numbered_content}

## POSTS CANDIDATOS

{$available_posts_text}

## EXCLUSÕES

{$excluded_ids_line}{$excluded_anchors_line}
## REGRAS

1. Sugira no máximo {$max_links} links, priorizando alta relevância semântica.
2. Use APENAS texto âncora que já exista literalmente no parágrafo escolhido.
3. Não use [P1] (primeiro parágrafo) para inserir links.
4. Cada link deve ter target_post_id diferente. Não duplique destinos.
5. Não sugira nenhum target_post_id da lista de IDs proibidos acima.
6. Não use como anchor_text nenhum trecho da lista de âncoras proibidas (já são links).
7. Score mínimo aceitável: {$min_score}. Abaixo disso, descarte a sugestão.
8. Se não houver oportunidades fortes, retorne {"suggestions": []}.

## RESPOSTA (JSON estrito, sem markdown)

{
    "suggestions": [
        {
            "paragraph_index": 5,
            "anchor_text": "trecho exato existente no parágrafo",
            "target_post_id": 123,
            "confidence_score": 85,
            "justification": "máx. 15 palavras"
        }
    ]
}
PROMPT;

        return $prompt;
    }

    /**
     * Enviar requisição para a API
     *
     * @param string $prompt       Prompt a ser enviado.
     * @param string $temp_api_key API Key temporária (opcional).
     * @param string $temp_model   Modelo temporário (opcional).
     * @return string|WP_Error Resposta da API ou erro.
     */
    private function send_request( $prompt, $temp_api_key = '', $temp_model = '' ) {
        // Usar chave temporária se fornecida, senão usar a armazenada
        $api_key = ! empty( $temp_api_key ) ? $temp_api_key : $this->api_key;
        $model   = ! empty( $temp_model ) ? self::sanitize_model( $temp_model ) : $this->model;

        $url = self::GEMINI_API_BASE_URL . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt,
                        ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'temperature'      => 0.3,
                'topK'             => 40,
                'topP'             => 0.95,
                'maxOutputTokens'  => 4096,
                'responseMimeType' => 'application/json',
                'thinkingConfig'   => array(
                    'thinkingBudget' => 512,
                ),
            ),
        );

        $this->log( 'Enviando requisição para Gemini API' );

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Erro na requisição: ' . $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( 200 !== $status_code ) {
            $error_data = json_decode( $body, true );
            $error_msg  = isset( $error_data['error']['message'] )
                ? $error_data['error']['message']
                : __( 'Erro desconhecido na API.', 'ai-internal-links-seo' );

            $this->log( 'Erro na API: ' . $error_msg );

            return new \WP_Error(
                'api_error',
                sprintf(
                    /* translators: 1: HTTP status code, 2: Error message */
                    __( 'Erro na API (HTTP %1$d): %2$s', 'ai-internal-links-seo' ),
                    $status_code,
                    $error_msg
                )
            );
        }

        $data = json_decode( $body, true );

        if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $this->log( 'Resposta inesperada da API' );

            return new \WP_Error(
                'invalid_response',
                __( 'Resposta inesperada da API.', 'ai-internal-links-seo' )
            );
        }

        $finish_reason = isset( $data['candidates'][0]['finishReason'] ) ? $data['candidates'][0]['finishReason'] : 'unknown';
        $text          = $data['candidates'][0]['content']['parts'][0]['text'];

        $this->log( 'Finish reason da Gemini: ' . $finish_reason );
        $this->log( 'Resposta recebida com sucesso' );

        return $text;
    }

    /**
     * Processar resposta da API e extrair sugestões
     *
     * @param string $response Resposta da API.
     * @return array|WP_Error Sugestões processadas ou erro.
     */
    private function parse_suggestions_response( $response ) {
        // Limpar a resposta (remover possíveis marcadores de markdown)
        $response = trim( $response );
        $response = preg_replace( '/^```json\s*/i', '', $response );
        $response = preg_replace( '/\s*```$/i', '', $response );
        $response = trim( $response );

        // Tentar decodificar JSON
        $data = $this->decode_json_response( $response );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $partial_suggestions = $this->extract_partial_suggestions( $response );

            if ( ! empty( $partial_suggestions ) ) {
                $this->log( 'JSON truncado. Recuperando sugestoes válidas parciais: ' . count( $partial_suggestions ) );
                return $partial_suggestions;
            }

            $this->log( 'Erro ao decodificar JSON: ' . json_last_error_msg() );
            $this->log( 'Resposta raw: ' . $response );

            return new \WP_Error(
                'json_error',
                __( 'Erro ao processar resposta da IA. Por favor, tente novamente.', 'ai-internal-links-seo' )
            );
        }

        if ( ! isset( $data['suggestions'] ) || ! is_array( $data['suggestions'] ) ) {
            return new \WP_Error(
                'invalid_format',
                __( 'Formato de resposta inválido.', 'ai-internal-links-seo' )
            );
        }

        // Validar e limpar sugestões
        $validated_suggestions = array();
        $seen_targets          = array();

        foreach ( $data['suggestions'] as $suggestion ) {
            $resolved = $this->resolve_suggestion( $suggestion );

            if ( false === $resolved ) {
                continue;
            }

            // Evitar duplicatas de target no mesmo lote.
            if ( isset( $seen_targets[ $resolved['target_post_id'] ] ) ) {
                continue;
            }
            $seen_targets[ $resolved['target_post_id'] ] = true;

            $validated_suggestions[] = $resolved;
        }

        return $validated_suggestions;
    }

    /**
     * Resolver paragraph_index → texto do parágrafo e validar a sugestão.
     *
     * @param array $suggestion Sugestão crua da IA.
     * @return array|false Sugestão sanitizada ou false se inválida.
     */
    private function resolve_suggestion( $suggestion ) {
        if ( ! is_array( $suggestion ) ) {
            return false;
        }

        // Compatibilidade: aceitar tanto paragraph_index quanto paragraph (legado).
        $paragraph = '';

        if ( isset( $suggestion['paragraph_index'] ) ) {
            $idx = absint( $suggestion['paragraph_index'] ) - 1;

            if ( $idx < 0 || ! isset( $this->current_paragraphs[ $idx ] ) ) {
                $this->log( 'paragraph_index inválido: ' . $suggestion['paragraph_index'] );
                return false;
            }

            // Bloquear primeiro parágrafo conforme regra do prompt.
            if ( 0 === $idx ) {
                $this->log( 'Sugestão descartada: primeiro parágrafo não pode receber link.' );
                return false;
            }

            $paragraph = $this->current_paragraphs[ $idx ];
        } elseif ( isset( $suggestion['paragraph'] ) && is_string( $suggestion['paragraph'] ) ) {
            $paragraph = $suggestion['paragraph'];
        } else {
            return false;
        }

        $required = array( 'anchor_text', 'target_post_id', 'confidence_score' );
        foreach ( $required as $field ) {
            if ( ! isset( $suggestion[ $field ] ) || '' === $suggestion[ $field ] ) {
                return false;
            }
        }

        $anchor = (string) $suggestion['anchor_text'];

        if ( '' === $paragraph || stripos( $paragraph, $anchor ) === false ) {
            $this->log( 'Anchor text não encontrado no parágrafo: ' . $anchor );
            return false;
        }

        // Bloquear âncoras que já são links no post original.
        if ( ! empty( $this->excluded_anchors ) && in_array( mb_strtolower( $anchor ), $this->excluded_anchors, true ) ) {
            $this->log( 'Anchor descartado (já é link no post): ' . $anchor );
            return false;
        }

        $target_id = absint( $suggestion['target_post_id'] );

        // Bloquear destino que já está linkado ou foi rejeitado anteriormente.
        if ( ! empty( $this->excluded_target_ids ) && in_array( $target_id, $this->excluded_target_ids, true ) ) {
            $this->log( 'Target descartado (já linkado/rejeitado): ' . $target_id );
            return false;
        }

        $target_post = get_post( $target_id );
        if ( ! $target_post || 'publish' !== $target_post->post_status ) {
            $this->log( 'Post de destino não encontrado: ' . $suggestion['target_post_id'] );
            return false;
        }

        $score     = min( 100, max( 0, absint( $suggestion['confidence_score'] ) ) );
        $min_score = absint( get_option( 'ailseo_min_confidence_score', 70 ) );

        if ( $score < $min_score ) {
            $this->log( 'Score abaixo do mínimo: ' . $score );
            return false;
        }

        $justification = isset( $suggestion['justification'] ) ? (string) $suggestion['justification'] : '';

        return array(
            'paragraph'        => sanitize_textarea_field( $paragraph ),
            'anchor_text'      => sanitize_text_field( $anchor ),
            'target_post_id'   => absint( $suggestion['target_post_id'] ),
            'position'         => '',
            'justification'    => sanitize_textarea_field( $justification ),
            'confidence_score' => $score,
        );
    }

    /**
     * Log de operações (apenas em modo debug)
     *
     * @param string $message Mensagem de log.
     */
    /**
     * Decodificar JSON da IA com fallback para caracteres de controle inválidos.
     *
     * @param string $response Resposta JSON da IA.
     * @return array|null
     */
    /**
     * Construir prompt de recuperação para respostas truncadas ou inválidas.
     *
     * @param string $original_prompt Prompt original.
     * @return string
     */
    private function build_recovery_prompt( $original_prompt ) {
        return $original_prompt . "\n\nIMPORTANTE: Sua resposta anterior ficou inválida ou truncada." .
            "\nResponda novamente com JSON estrito e completo." .
            "\nSe houver qualquer dúvida, retorne {\"suggestions\":[]}." .
            "\nNão interrompa o JSON no meio de um campo." .
            "\nNão inclua markdown." .
            "\nNão inclua quebras de linha literais dentro dos valores de string.";
    }

    private function decode_json_response( $response ) {
        $data = json_decode( $response, true );

        if ( JSON_ERROR_NONE === json_last_error() ) {
            return $data;
        }

        $sanitized_response = $this->escape_control_characters_in_json_strings( $response );

        if ( $sanitized_response !== $response ) {
            $this->log( 'Tentando decodificar JSON após escapar caracteres de controle em strings.' );
            $data = json_decode( $sanitized_response, true );
        }

        return $data;
    }

    /**
     * Extrair sugestões completas de uma resposta JSON truncada.
     *
     * @param string $response Resposta bruta da IA.
     * @return array
     */
    private function extract_partial_suggestions( $response ) {
        $suggestions = array();
        $array_start = strpos( $response, '"suggestions"' );

        if ( false === $array_start ) {
            return $suggestions;
        }

        $array_start = strpos( $response, '[', $array_start );

        if ( false === $array_start ) {
            return $suggestions;
        }

        $in_string    = false;
        $is_escaped   = false;
        $object_depth = 0;
        $object_start = null;
        $length       = strlen( $response );

        for ( $index = $array_start + 1; $index < $length; $index++ ) {
            $char = $response[ $index ];

            if ( $in_string ) {
                if ( $is_escaped ) {
                    $is_escaped = false;
                    continue;
                }

                if ( '\\' === $char ) {
                    $is_escaped = true;
                    continue;
                }

                if ( '"' === $char ) {
                    $in_string = false;
                }

                continue;
            }

            if ( '"' === $char ) {
                $in_string = true;
                continue;
            }

            if ( '{' === $char ) {
                if ( 0 === $object_depth ) {
                    $object_start = $index;
                }

                $object_depth++;
                continue;
            }

            if ( '}' === $char && $object_depth > 0 ) {
                $object_depth--;

                if ( 0 === $object_depth && null !== $object_start ) {
                    $object_json = substr( $response, $object_start, $index - $object_start + 1 );
                    $object_data = $this->decode_json_response( $object_json );

                    if ( is_array( $object_data ) ) {
                        $resolved = $this->resolve_suggestion( $object_data );

                        if ( false !== $resolved ) {
                            $suggestions[] = $resolved;
                        }
                    }

                    $object_start = null;
                }
            }
        }

        return $suggestions;
    }

    /**
     * Escapar caracteres de controle inválidos dentro de strings JSON.
     *
     * Mantém a estrutura do JSON intacta e só altera controles crus dentro
     * de valores string, como novas linhas literais retornadas pelo modelo.
     *
     * @param string $json JSON bruto.
     * @return string
     */
    private function escape_control_characters_in_json_strings( $json ) {
        $result      = '';
        $in_string   = false;
        $is_escaped  = false;
        $json_length = strlen( $json );

        for ( $index = 0; $index < $json_length; $index++ ) {
            $char = $json[ $index ];
            $ord  = ord( $char );

            if ( $in_string ) {
                if ( $is_escaped ) {
                    $result    .= $char;
                    $is_escaped = false;
                    continue;
                }

                if ( '\\' === $char ) {
                    $result    .= $char;
                    $is_escaped = true;
                    continue;
                }

                if ( '"' === $char ) {
                    $result   .= $char;
                    $in_string = false;
                    continue;
                }

                if ( 10 === $ord ) {
                    $result .= '\n';
                    continue;
                }

                if ( 13 === $ord ) {
                    $result .= '\r';
                    continue;
                }

                if ( 9 === $ord ) {
                    $result .= '\t';
                    continue;
                }

                if ( $ord < 32 ) {
                    $result .= sprintf( '\u%04x', $ord );
                    continue;
                }
            } elseif ( '"' === $char ) {
                $in_string = true;
            }

            $result .= $char;
        }

        return $result;
    }

    /**
     * Log de operaÃ§Ãµes (apenas em modo debug)
     *
     * @param string $message Mensagem de log.
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[AI Internal Links SEO - AI Client] ' . $message );
        }
    }
}
