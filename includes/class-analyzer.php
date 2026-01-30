<?php
/**
 * Classe de análise de conteúdo
 *
 * @package AIInternalLinksSEO
 */

namespace AIInternalLinksSEO;

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe Analyzer
 *
 * Responsável por analisar posts e coordenar a geração de sugestões de links
 */
class Analyzer {

    /**
     * Cliente de IA
     *
     * @var AI_Client
     */
    private $ai_client;

    /**
     * Sistema de cache
     *
     * @var Cache
     */
    private $cache;

    /**
     * Construtor
     *
     * @param AI_Client $ai_client Cliente de IA.
     * @param Cache     $cache     Sistema de cache.
     */
    public function __construct( AI_Client $ai_client, Cache $cache ) {
        $this->ai_client = $ai_client;
        $this->cache     = $cache;
    }

    /**
     * Analisar um post e gerar sugestões de links
     *
     * @param int $post_id ID do post a ser analisado.
     * @return array Resultado da análise.
     */
    public function analyze_post( $post_id ) {
        // Verificar se post existe
        $post = get_post( $post_id );

        if ( ! $post || 'publish' !== $post->post_status ) {
            return array(
                'success' => false,
                'message' => __( 'Post não encontrado ou não publicado.', 'ai-internal-links-seo' ),
            );
        }

        $this->log( 'Iniciando análise do post: ' . $post_id );

        // Preparar dados do post atual
        $current_post = $this->prepare_post_data( $post );

        // Obter posts disponíveis para linking
        $available_posts = $this->get_available_posts( $post_id );

        if ( empty( $available_posts ) ) {
            return array(
                'success' => false,
                'message' => __( 'Não há posts disponíveis para criar links internos.', 'ai-internal-links-seo' ),
            );
        }

        // Enviar para análise da IA
        $suggestions = $this->ai_client->analyze_for_links( $current_post, $available_posts );

        if ( is_wp_error( $suggestions ) ) {
            return array(
                'success' => false,
                'message' => $suggestions->get_error_message(),
            );
        }

        // Salvar sugestões no banco de dados
        $saved_count = $this->save_suggestions( $post_id, $suggestions );

        // Marcar post como analisado no cache
        $this->cache->mark_post_analyzed( $post_id );

        // Registrar log
        $this->log_action( $post_id, 'analyzed', array(
            'suggestions_count' => count( $suggestions ),
            'saved_count'       => $saved_count,
        ) );

        $this->log( 'Análise concluída. Sugestões salvas: ' . $saved_count );

        return array(
            'success'     => true,
            'message'     => sprintf(
                /* translators: %d: Number of suggestions */
                _n(
                    '%d sugestão de link encontrada.',
                    '%d sugestões de links encontradas.',
                    $saved_count,
                    'ai-internal-links-seo'
                ),
                $saved_count
            ),
            'suggestions' => $suggestions,
        );
    }

    /**
     * Preparar dados do post para envio à IA
     *
     * @param \WP_Post $post Objeto do post.
     * @return array Dados preparados.
     */
    private function prepare_post_data( $post ) {
        // Remover shortcodes e limpar HTML
        $content = $post->post_content;
        $content = strip_shortcodes( $content );
        $content = wp_strip_all_tags( $content, true );
        $content = preg_replace( '/\s+/', ' ', $content );
        $content = trim( $content );

        return array(
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $content,
            'url'     => get_permalink( $post->ID ),
        );
    }

    /**
     * Obter posts disponíveis para linking
     *
     * @param int $exclude_post_id ID do post a ser excluído (post atual).
     * @return array Posts disponíveis.
     */
    private function get_available_posts( $exclude_post_id ) {
        // Obter configurações
        $post_types = get_option( 'ailseo_post_types', array( 'post' ) );

        if ( empty( $post_types ) ) {
            $post_types = array( 'post' );
        }

        // Verificar cache
        $cache_key = $this->cache->get_posts_list_key( array(
            'post_types' => $post_types,
            'exclude'    => $exclude_post_id,
        ) );

        $cached = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // Buscar posts
        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 50, // Limitar para não sobrecarregar a API
            'post__not_in'   => array( $exclude_post_id ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $query = new \WP_Query( $args );
        $posts = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();

                $posts[] = array(
                    'id'      => get_the_ID(),
                    'title'   => get_the_title(),
                    'excerpt' => get_the_excerpt(),
                    'url'     => get_permalink(),
                );
            }
        }

        wp_reset_postdata();

        // Salvar no cache
        $this->cache->set( $cache_key, $posts, HOUR_IN_SECONDS );

        return $posts;
    }

    /**
     * Salvar sugestões no banco de dados
     *
     * @param int   $post_id     ID do post.
     * @param array $suggestions Sugestões a serem salvas.
     * @return int Número de sugestões salvas.
     */
    private function save_suggestions( $post_id, $suggestions ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ailseo_suggestions';
        $saved      = 0;

        // Remover sugestões antigas pendentes do mesmo post
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $table_name,
            array(
                'post_id' => $post_id,
                'status'  => 'pending',
            ),
            array( '%d', '%s' )
        );

        foreach ( $suggestions as $suggestion ) {
            // Verificar se já existe uma sugestão similar aplicada
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name
                    WHERE post_id = %d
                    AND target_post_id = %d
                    AND status = 'applied'",
                    $post_id,
                    $suggestion['target_post_id']
                )
            );

            if ( $existing ) {
                continue; // Já existe link aplicado para este destino
            }

            // Inserir nova sugestão
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->insert(
                $table_name,
                array(
                    'post_id'           => $post_id,
                    'suggested_anchor'  => $suggestion['anchor_text'],
                    'target_post_id'    => $suggestion['target_post_id'],
                    'paragraph_context' => $suggestion['paragraph'],
                    'confidence_score'  => $suggestion['confidence_score'],
                    'justification'     => $suggestion['justification'],
                    'status'            => 'pending',
                    'created_at'        => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
            );

            if ( $result ) {
                $saved++;
            }
        }

        return $saved;
    }

    /**
     * Obter sugestões pendentes de um post
     *
     * @param int $post_id ID do post.
     * @return array Sugestões pendentes.
     */
    public function get_pending_suggestions( $post_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ailseo_suggestions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $suggestions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, p.post_title as target_title, p.guid as target_url
                FROM $table_name s
                LEFT JOIN {$wpdb->posts} p ON s.target_post_id = p.ID
                WHERE s.post_id = %d AND s.status = 'pending'
                ORDER BY s.confidence_score DESC",
                $post_id
            ),
            ARRAY_A
        );

        return $suggestions;
    }

    /**
     * Obter todas as sugestões de um post
     *
     * @param int    $post_id ID do post.
     * @param string $status  Status opcional para filtrar.
     * @return array Sugestões.
     */
    public function get_all_suggestions( $post_id, $status = '' ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ailseo_suggestions';

        $sql = $wpdb->prepare(
            "SELECT s.*, p.post_title as target_title
            FROM $table_name s
            LEFT JOIN {$wpdb->posts} p ON s.target_post_id = p.ID
            WHERE s.post_id = %d",
            $post_id
        );

        if ( ! empty( $status ) ) {
            $sql .= $wpdb->prepare( ' AND s.status = %s', $status );
        }

        $sql .= ' ORDER BY s.confidence_score DESC';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Obter estatísticas de análise
     *
     * @return array Estatísticas.
     */
    public function get_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ailseo_suggestions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied,
                COUNT(DISTINCT post_id) as posts_analyzed
            FROM $table_name",
            ARRAY_A
        );

        return $stats;
    }

    /**
     * Registrar ação no log
     *
     * @param int    $post_id       ID do post.
     * @param string $action        Ação realizada.
     * @param array  $details       Detalhes adicionais.
     * @param int    $suggestion_id ID da sugestão (opcional).
     */
    private function log_action( $post_id, $action, $details = array(), $suggestion_id = null ) {
        global $wpdb;

        $log_table = $wpdb->prefix . 'ailseo_log';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $log_table,
            array(
                'post_id'       => $post_id,
                'suggestion_id' => $suggestion_id,
                'action'        => $action,
                'details'       => wp_json_encode( $details ),
                'user_id'       => get_current_user_id(),
                'created_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%s' )
        );
    }

    /**
     * Log de operações (apenas em modo debug)
     *
     * @param string $message Mensagem de log.
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[AI Internal Links SEO - Analyzer] ' . $message );
        }
    }
}
