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
     * Analisar post e obter sugestões de links
     *
     * @param array $current_post    Dados do post atual.
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

        // Construir prompt
        $prompt = $this->build_analysis_prompt( $current_post, $available_posts );

        // Enviar requisição
        $response = $this->send_request( $prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Processar resposta
        $suggestions = $this->parse_suggestions_response( $response );

        return $suggestions;
    }

    /**
     * Construir prompt para análise de links
     *
     * @param array $current_post    Dados do post atual.
     * @param array $available_posts Posts disponíveis para linking.
     * @return string Prompt formatado.
     */
    private function build_analysis_prompt( $current_post, $available_posts ) {
        // Obter configurações
        $max_links    = get_option( 'ailseo_max_links_per_post', 3 );
        $min_score    = get_option( 'ailseo_min_confidence_score', 70 );

        // Formatar lista de posts disponíveis
        $available_posts_text = '';
        foreach ( $available_posts as $post ) {
            $available_posts_text .= sprintf(
                "- ID: %d | Título: %s | URL: %s | Resumo: %s\n",
                $post['id'],
                $post['title'],
                $post['url'],
                wp_trim_words( $post['excerpt'], 30, '...' )
            );
        }

        $prompt = <<<PROMPT
Você é um especialista em SEO e link building interno. Sua tarefa é analisar o conteúdo de um post de blog e identificar oportunidades relevantes para inserir links internos para outros posts do mesmo blog.

## POST ATUAL PARA ANÁLISE:

Título: {$current_post['title']}

Conteúdo:
{$current_post['content']}

## POSTS DISPONÍVEIS PARA LINKING:

{$available_posts_text}

## REGRAS IMPORTANTES:

1. Analise cuidadosamente o conteúdo do post atual
2. Identifique até {$max_links} oportunidades de links internos
3. Apenas sugira links quando houver relevância semântica clara
4. O anchor text deve ser natural e fluir com o texto existente
5. Prefira inserir links em palavras/frases que JÁ EXISTEM no texto
6. Evite o primeiro parágrafo para inserção de links
7. Priorize links que agregam valor real ao leitor
8. O score de confiança deve ser no mínimo {$min_score} para sugerir
9. NUNCA invente conteúdo - use apenas texto que já existe no post

## FORMATO DE RESPOSTA:

Responda APENAS com um JSON válido no seguinte formato (sem markdown, sem explicações adicionais):

{
    "suggestions": [
        {
            "paragraph": "texto completo do parágrafo onde inserir o link (copie exatamente como está no post)",
            "anchor_text": "texto âncora que receberá o link (deve existir no parágrafo)",
            "target_post_id": 123,
            "position": "inicio|meio|fim",
            "justification": "explicação breve de por que este link é relevante",
            "confidence_score": 85
        }
    ]
}

Se não houver oportunidades relevantes de links, responda com:
{
    "suggestions": []
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
                'temperature'     => 0.3,
                'topK'            => 40,
                'topP'            => 0.95,
                'maxOutputTokens' => 2048,
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

        $text = $data['candidates'][0]['content']['parts'][0]['text'];
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
        $data = json_decode( $response, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
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

        foreach ( $data['suggestions'] as $suggestion ) {
            if ( $this->validate_suggestion( $suggestion ) ) {
                $validated_suggestions[] = array(
                    'paragraph'        => sanitize_textarea_field( $suggestion['paragraph'] ),
                    'anchor_text'      => sanitize_text_field( $suggestion['anchor_text'] ),
                    'target_post_id'   => absint( $suggestion['target_post_id'] ),
                    'position'         => sanitize_text_field( $suggestion['position'] ),
                    'justification'    => sanitize_textarea_field( $suggestion['justification'] ),
                    'confidence_score' => min( 100, max( 0, absint( $suggestion['confidence_score'] ) ) ),
                );
            }
        }

        return $validated_suggestions;
    }

    /**
     * Validar uma sugestão individual
     *
     * @param array $suggestion Sugestão a ser validada.
     * @return bool Se a sugestão é válida.
     */
    private function validate_suggestion( $suggestion ) {
        // Verificar campos obrigatórios
        $required_fields = array(
            'paragraph',
            'anchor_text',
            'target_post_id',
            'confidence_score',
        );

        foreach ( $required_fields as $field ) {
            if ( ! isset( $suggestion[ $field ] ) || empty( $suggestion[ $field ] ) ) {
                return false;
            }
        }

        // Verificar se o anchor text está no parágrafo
        if ( stripos( $suggestion['paragraph'], $suggestion['anchor_text'] ) === false ) {
            $this->log( 'Anchor text não encontrado no parágrafo: ' . $suggestion['anchor_text'] );
            return false;
        }

        // Verificar se o post de destino existe
        $target_post = get_post( $suggestion['target_post_id'] );
        if ( ! $target_post || 'publish' !== $target_post->post_status ) {
            $this->log( 'Post de destino não encontrado: ' . $suggestion['target_post_id'] );
            return false;
        }

        // Verificar score mínimo
        $min_score = get_option( 'ailseo_min_confidence_score', 70 );
        if ( $suggestion['confidence_score'] < $min_score ) {
            $this->log( 'Score abaixo do mínimo: ' . $suggestion['confidence_score'] );
            return false;
        }

        return true;
    }

    /**
     * Log de operações (apenas em modo debug)
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
