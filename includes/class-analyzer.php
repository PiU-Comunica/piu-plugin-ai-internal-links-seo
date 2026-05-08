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

        // Preparar dados do post atual (com parágrafos e taxonomias)
        $current_post = $this->prepare_post_data( $post );

        // Posts já vinculados (HTML do post + base de sugestões applied/rejected).
        $existing_target_ids = isset( $current_post['existing_target_ids'] ) ? (array) $current_post['existing_target_ids'] : array();
        $excluded_target_ids = array_values( array_unique( array_merge(
            $this->get_excluded_target_ids( $post_id ),
            $existing_target_ids
        ) ) );

        // Obter posts candidatos pré-filtrados por relevância
        $available_posts = $this->get_available_posts( $post_id, $current_post, $excluded_target_ids );

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
     * Limite de palavras do conteúdo enviado à IA.
     *
     * @var int
     */
    const MAX_CONTENT_WORDS = 4000;

    /**
     * Comprimento mínimo (caracteres) para um parágrafo ser enviado à IA.
     *
     * @var int
     */
    const MIN_PARAGRAPH_LENGTH = 40;

    /**
     * Quantidade máxima de candidatos enviados à IA após pré-filtragem.
     *
     * @var int
     */
    const MAX_CANDIDATES = 15;

    /**
     * Preparar dados do post para envio à IA
     *
     * @param \WP_Post $post Objeto do post.
     * @return array Dados preparados.
     */
    private function prepare_post_data( $post ) {
        $content = strip_shortcodes( $post->post_content );

        // Antes de remover HTML, extrair links já existentes no post.
        $existing_links = $this->extract_existing_links( $content );

        // Quebrar em parágrafos preservando estrutura antes de remover HTML.
        // Considera blocos comuns (<p>, <h*>, <li>, <blockquote>) como separadores.
        $blocks = preg_split(
            '/<\s*\/(?:p|h[1-6]|li|blockquote|div)\s*>|<br\s*\/?>|\n\s*\n/i',
            $content
        );

        $paragraphs = array();
        $word_count = 0;

        foreach ( (array) $blocks as $block ) {
            $text = wp_strip_all_tags( $block, true );
            $text = preg_replace( '/\s+/', ' ', $text );
            $text = trim( $text );

            if ( '' === $text || mb_strlen( $text ) < self::MIN_PARAGRAPH_LENGTH ) {
                continue;
            }

            $words       = preg_split( '/\s+/', $text );
            $word_count += count( $words );

            // Cortar se exceder o limite global de palavras.
            if ( $word_count > self::MAX_CONTENT_WORDS ) {
                $this->log( 'Conteúdo truncado em ' . self::MAX_CONTENT_WORDS . ' palavras.' );
                break;
            }

            $paragraphs[] = $text;
        }

        // Fallback: se a quebra não rendeu nada, usa o conteúdo inteiro como 1 parágrafo.
        if ( empty( $paragraphs ) ) {
            $fallback = preg_replace( '/\s+/', ' ', wp_strip_all_tags( $content, true ) );
            $fallback = trim( $fallback );

            if ( '' !== $fallback ) {
                $paragraphs[] = $fallback;
            }
        }

        return array(
            'id'                 => $post->ID,
            'title'              => $post->post_title,
            'paragraphs'         => $paragraphs,
            'taxonomies'         => $this->get_post_terms_summary( $post ),
            'url'                => get_permalink( $post->ID ),
            'existing_target_ids' => $existing_links['target_ids'],
            'existing_anchors'   => $existing_links['anchors'],
        );
    }

    /**
     * Extrair links existentes do conteúdo HTML do post.
     *
     * Resolve URLs de links que apontam para posts internos do site para os IDs
     * correspondentes, e coleta os textos âncora normalizados em minúsculas.
     *
     * @param string $html_content Conteúdo HTML bruto do post.
     * @return array{ target_ids: int[], anchors: string[] }
     */
    private function extract_existing_links( $html_content ) {
        $target_ids = array();
        $anchors    = array();

        if ( '' === trim( (string) $html_content ) ) {
            return array( 'target_ids' => $target_ids, 'anchors' => $anchors );
        }

        if ( preg_match_all( '/<a\s+[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html_content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $url    = trim( html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
                $anchor = trim( wp_strip_all_tags( $match[2] ) );

                if ( '' !== $anchor ) {
                    $anchors[] = mb_strtolower( $anchor );
                }

                if ( '' === $url || '#' === $url[0] ) {
                    continue;
                }

                $resolved_id = url_to_postid( $url );

                if ( $resolved_id > 0 ) {
                    $target_ids[] = (int) $resolved_id;
                }
            }
        }

        return array(
            'target_ids' => array_values( array_unique( $target_ids ) ),
            'anchors'    => array_values( array_unique( $anchors ) ),
        );
    }

    /**
     * Resumir termos taxonômicos relevantes de um post como string curta.
     *
     * @param \WP_Post $post Objeto do post.
     * @return string Lista separada por vírgulas ou string vazia.
     */
    private function get_post_terms_summary( $post ) {
        $taxonomies = get_object_taxonomies( $post->post_type );
        $names      = array();

        foreach ( $taxonomies as $taxonomy ) {
            $tax_obj = get_taxonomy( $taxonomy );

            if ( ! $tax_obj || empty( $tax_obj->public ) ) {
                continue;
            }

            $terms = get_the_terms( $post->ID, $taxonomy );

            if ( empty( $terms ) || is_wp_error( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $names[] = $term->name;
            }
        }

        $names = array_slice( array_unique( $names ), 0, 10 );

        return implode( ', ', $names );
    }

    /**
     * Obter IDs de term taxonômicos do post (para cálculo de overlap).
     *
     * @param int    $post_id ID do post.
     * @param string $post_type Tipo do post.
     * @return array IDs de termos.
     */
    private function get_post_term_ids( $post_id, $post_type ) {
        $taxonomies = get_object_taxonomies( $post_type );
        $term_ids   = array();

        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );

            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $term_ids = array_merge( $term_ids, $terms );
            }
        }

        return array_unique( array_map( 'intval', $term_ids ) );
    }

    /**
     * Tokens significativos do título para overlap simples.
     *
     * @param string $text Texto.
     * @return array Tokens em minúsculas com >=4 caracteres.
     */
    private function tokenize( $text ) {
        $text   = mb_strtolower( wp_strip_all_tags( (string) $text ) );
        $text   = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
        $tokens = preg_split( '/\s+/', trim( (string) $text ) );

        if ( empty( $tokens ) ) {
            return array();
        }

        $stopwords = array(
            'para','como','sobre','dos','das','dum','duma','das','com','por','que',
            'mais','sem','seu','sua','meu','minha','este','esta','isso','aqui','quando',
            'onde','tudo','muito','muita','também','foi','são','está','estão','pode',
            'podem','será','tem','têm','uma','uns','umas','seus','suas','pelo','pela',
        );

        $filtered = array();

        foreach ( $tokens as $tok ) {
            if ( mb_strlen( $tok ) < 4 ) {
                continue;
            }
            if ( in_array( $tok, $stopwords, true ) ) {
                continue;
            }
            $filtered[ $tok ] = true;
        }

        return array_keys( $filtered );
    }

    /**
     * Obter posts candidatos a linking, ranqueados por relevância.
     *
     * @param int   $exclude_post_id     ID do post atual (a ser excluído).
     * @param array $current_post        Dados preparados do post atual.
     * @param array $excluded_target_ids IDs adicionais a excluir (já aplicados/rejeitados).
     * @return array Posts disponíveis (top N por score).
     */
    private function get_available_posts( $exclude_post_id, $current_post, $excluded_target_ids = array() ) {
        $post_types = get_option( 'ailseo_post_types', array( 'post' ) );

        if ( empty( $post_types ) ) {
            $post_types = array( 'post' );
        }

        $exclude_ids = array_unique( array_merge( array( $exclude_post_id ), array_map( 'intval', $excluded_target_ids ) ) );

        // Cache por post (varia conforme exclusões e taxonomias do post atual).
        $cache_key = $this->cache->get_posts_list_key( array(
            'post_types' => $post_types,
            'exclude'    => $exclude_ids,
            'for_post'   => $exclude_post_id,
        ) );

        $cached = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $current_post_obj  = get_post( $exclude_post_id );
        $current_post_type = $current_post_obj ? $current_post_obj->post_type : 'post';
        $current_term_ids  = $this->get_post_term_ids( $exclude_post_id, $current_post_type );
        $title_tokens      = $this->tokenize( $current_post['title'] );

        $candidate_ids = array();

        // 1) Buscar primeiro posts que compartilham taxonomia com o atual.
        if ( ! empty( $current_term_ids ) ) {
            $tax_query = array( 'relation' => 'OR' );

            $taxonomies = get_object_taxonomies( $current_post_type );
            foreach ( $taxonomies as $taxonomy ) {
                $term_ids = wp_get_post_terms( $exclude_post_id, $taxonomy, array( 'fields' => 'ids' ) );
                if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
                    continue;
                }
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term_ids,
                );
            }

            if ( count( $tax_query ) > 1 ) {
                $tax_query_args = array(
                    'post_type'      => $post_types,
                    'post_status'    => 'publish',
                    'posts_per_page' => 60,
                    'post__not_in'   => $exclude_ids,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                );

                $tax_query_obj = new \WP_Query( $tax_query_args );

                if ( ! empty( $tax_query_obj->posts ) ) {
                    $candidate_ids = array_map( 'intval', $tax_query_obj->posts );
                }
            }
        }

        // 2) Completar com posts recentes para garantir um pool mínimo.
        if ( count( $candidate_ids ) < self::MAX_CANDIDATES * 2 ) {
            $extra_excludes = array_unique( array_merge( $exclude_ids, $candidate_ids ) );

            $recent_query = new \WP_Query( array(
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => self::MAX_CANDIDATES * 2,
                'post__not_in'   => $extra_excludes,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ) );

            if ( ! empty( $recent_query->posts ) ) {
                $candidate_ids = array_merge( $candidate_ids, array_map( 'intval', $recent_query->posts ) );
            }
        }

        $candidate_ids = array_values( array_unique( $candidate_ids ) );

        // 3) Pontuar candidatos.
        $scored = array();

        foreach ( $candidate_ids as $cid ) {
            $candidate = get_post( $cid );
            if ( ! $candidate ) {
                continue;
            }

            $cand_term_ids = $this->get_post_term_ids( $cid, $candidate->post_type );
            $tax_overlap   = count( array_intersect( $current_term_ids, $cand_term_ids ) );

            $cand_title_tokens = $this->tokenize( $candidate->post_title );
            $title_overlap     = count( array_intersect( $title_tokens, $cand_title_tokens ) );

            // Score: taxonomia pesa mais que título.
            $score = ( $tax_overlap * 3 ) + ( $title_overlap * 2 );

            $scored[] = array(
                'id'         => $cid,
                'post'       => $candidate,
                'score'      => $score,
                'tax_terms'  => $cand_term_ids,
            );
        }

        usort( $scored, function ( $a, $b ) {
            if ( $a['score'] === $b['score'] ) {
                return strtotime( $b['post']->post_date ) <=> strtotime( $a['post']->post_date );
            }
            return $b['score'] <=> $a['score'];
        } );

        $top = array_slice( $scored, 0, self::MAX_CANDIDATES );

        $posts = array();

        foreach ( $top as $entry ) {
            $cand = $entry['post'];

            $excerpt = has_excerpt( $cand ) ? $cand->post_excerpt : wp_trim_words( wp_strip_all_tags( $cand->post_content ), 25, '...' );

            $posts[] = array(
                'id'         => $cand->ID,
                'title'      => $cand->post_title,
                'excerpt'    => $excerpt,
                'taxonomies' => $this->get_post_terms_summary( $cand ),
                'url'        => get_permalink( $cand->ID ),
            );
        }

        $this->log( 'Candidatos selecionados: ' . count( $posts ) . ' (pool: ' . count( $candidate_ids ) . ')' );

        $this->cache->set( $cache_key, $posts, 30 * MINUTE_IN_SECONDS );

        return $posts;
    }

    /**
     * Obter IDs de posts já aplicados ou rejeitados a partir do post atual.
     *
     * @param int $post_id ID do post atual.
     * @return array IDs de posts de destino a serem excluídos.
     */
    private function get_excluded_target_ids( $post_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ailseo_suggestions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT target_post_id FROM $table_name
                WHERE post_id = %d AND status IN ('applied','rejected')",
                $post_id
            )
        );

        return array_map( 'intval', (array) $rows );
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
            // Verificar se já existe uma sugestão aplicada para este destino.
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

            // Evitar recriar sugestões iguais que já foram rejeitadas.
            // Mantém o histórico de rejeição sem poluir a lista em novas análises.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rejected = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name
                    WHERE post_id = %d
                    AND target_post_id = %d
                    AND suggested_anchor = %s
                    AND status = 'rejected'",
                    $post_id,
                    $suggestion['target_post_id'],
                    $suggestion['anchor_text']
                )
            );

            if ( $rejected ) {
                continue;
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
