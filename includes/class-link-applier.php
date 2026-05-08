<?php
/**
 * Classe para aplicação de links no conteúdo
 *
 * @package AIInternalLinksSEO
 */

namespace AIInternalLinksSEO;

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe Link_Applier
 *
 * Responsável por aplicar, rejeitar e desfazer sugestões de links
 */
class Link_Applier {

    /**
     * Aplicar uma sugestão de link no post
     *
     * @param int $suggestion_id ID da sugestão.
     * @return array Resultado da operação.
     */
    public function apply_suggestion( $suggestion_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ailseo_suggestions';

        // Obter sugestão
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $suggestion = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $suggestion_id
            ),
            ARRAY_A
        );

        if ( ! $suggestion ) {
            return array(
                'success' => false,
                'message' => __( 'Sugestão não encontrada.', 'ai-internal-links-seo' ),
            );
        }

        if ( 'pending' !== $suggestion['status'] ) {
            return array(
                'success' => false,
                'message' => __( 'Esta sugestão já foi processada.', 'ai-internal-links-seo' ),
            );
        }

        // Obter post
        $post = get_post( $suggestion['post_id'] );

        if ( ! $post ) {
            return array(
                'success' => false,
                'message' => __( 'Post não encontrado.', 'ai-internal-links-seo' ),
            );
        }

        // Obter URL do post de destino
        $target_url = get_permalink( $suggestion['target_post_id'] );

        if ( ! $target_url ) {
            return array(
                'success' => false,
                'message' => __( 'Post de destino não encontrado.', 'ai-internal-links-seo' ),
            );
        }

        // Buscar o parágrafo no conteúdo
        $content          = $post->post_content;
        $paragraph        = $suggestion['paragraph_context'];
        $anchor_text      = $suggestion['suggested_anchor'];

        // Verificar se o parágrafo ainda existe no conteúdo
        if ( stripos( $content, $paragraph ) === false ) {
            // Tentar uma busca mais flexível (ignorar espaços extras)
            $normalized_content   = preg_replace( '/\s+/', ' ', $content );
            $normalized_paragraph = preg_replace( '/\s+/', ' ', $paragraph );

            if ( stripos( $normalized_content, $normalized_paragraph ) === false ) {
                return array(
                    'success' => false,
                    'message' => __( 'Parágrafo não encontrado no conteúdo atual. O post pode ter sido modificado.', 'ai-internal-links-seo' ),
                );
            }
        }

        // Verificar se o anchor text está no parágrafo
        if ( stripos( $paragraph, $anchor_text ) === false ) {
            return array(
                'success' => false,
                'message' => __( 'Texto âncora não encontrado no parágrafo.', 'ai-internal-links-seo' ),
            );
        }

        // Criar o link (abre em nova aba, com rel seguros).
        $link = sprintf(
            '<a href="%s" title="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( $target_url ),
            esc_attr( get_the_title( $suggestion['target_post_id'] ) ),
            esc_html( $anchor_text )
        );

        // Substituir o anchor text pelo link dentro do parágrafo
        $new_paragraph = $this->replace_first_occurrence(
            $paragraph,
            $anchor_text,
            $link
        );

        // Substituir o parágrafo no conteúdo
        $new_content = $this->replace_first_occurrence(
            $content,
            $paragraph,
            $new_paragraph
        );

        // Verificar se houve alteração
        if ( $new_content === $content ) {
            return array(
                'success' => false,
                'message' => __( 'Não foi possível aplicar o link. Verifique se o conteúdo não foi modificado.', 'ai-internal-links-seo' ),
            );
        }

        // Atualizar post
        $update_result = wp_update_post(
            array(
                'ID'           => $post->ID,
                'post_content' => $new_content,
            ),
            true
        );

        if ( is_wp_error( $update_result ) ) {
            return array(
                'success' => false,
                'message' => $update_result->get_error_message(),
            );
        }

        // Atualizar sugestão
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table_name,
            array(
                'status'             => 'applied',
                'paragraph_modified' => $new_paragraph,
                'applied_at'         => current_time( 'mysql' ),
                'applied_by'         => get_current_user_id(),
            ),
            array( 'id' => $suggestion_id ),
            array( '%s', '%s', '%s', '%d' ),
            array( '%d' )
        );

        // Registrar log
        $this->log_action( $suggestion['post_id'], 'link_applied', array(
            'target_post_id' => $suggestion['target_post_id'],
            'anchor_text'    => $anchor_text,
        ), $suggestion_id );

        $this->log( 'Link aplicado com sucesso: ' . $suggestion_id );

        return array(
            'success' => true,
            'message' => __( 'Link aplicado com sucesso!', 'ai-internal-links-seo' ),
        );
    }

    /**
     * Rejeitar uma sugestão de link
     *
     * @param int $suggestion_id ID da sugestão.
     * @return array Resultado da operação.
     */
    public function reject_suggestion( $suggestion_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ailseo_suggestions';

        // Obter sugestão
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $suggestion = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $suggestion_id
            ),
            ARRAY_A
        );

        if ( ! $suggestion ) {
            return array(
                'success' => false,
                'message' => __( 'Sugestão não encontrada.', 'ai-internal-links-seo' ),
            );
        }

        if ( 'pending' !== $suggestion['status'] ) {
            return array(
                'success' => false,
                'message' => __( 'Esta sugestão já foi processada.', 'ai-internal-links-seo' ),
            );
        }

        // Atualizar status
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table_name,
            array( 'status' => 'rejected' ),
            array( 'id' => $suggestion_id ),
            array( '%s' ),
            array( '%d' )
        );

        // Registrar log
        $this->log_action( $suggestion['post_id'], 'link_rejected', array(
            'target_post_id' => $suggestion['target_post_id'],
        ), $suggestion_id );

        $this->log( 'Sugestão rejeitada: ' . $suggestion_id );

        return array(
            'success' => true,
            'message' => __( 'Sugestão rejeitada.', 'ai-internal-links-seo' ),
        );
    }

    /**
     * Desfazer aplicação de um link
     *
     * @param int $suggestion_id ID da sugestão.
     * @return array Resultado da operação.
     */
    public function undo_suggestion( $suggestion_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ailseo_suggestions';

        // Obter sugestão
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $suggestion = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $suggestion_id
            ),
            ARRAY_A
        );

        if ( ! $suggestion ) {
            return array(
                'success' => false,
                'message' => __( 'Sugestão não encontrada.', 'ai-internal-links-seo' ),
            );
        }

        if ( 'applied' !== $suggestion['status'] ) {
            return array(
                'success' => false,
                'message' => __( 'Esta sugestão não foi aplicada.', 'ai-internal-links-seo' ),
            );
        }

        if ( empty( $suggestion['paragraph_modified'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Não é possível desfazer: dados de backup não encontrados.', 'ai-internal-links-seo' ),
            );
        }

        // Obter post
        $post = get_post( $suggestion['post_id'] );

        if ( ! $post ) {
            return array(
                'success' => false,
                'message' => __( 'Post não encontrado.', 'ai-internal-links-seo' ),
            );
        }

        $content           = $post->post_content;
        $modified_paragraph = $suggestion['paragraph_modified'];
        $original_paragraph = $suggestion['paragraph_context'];

        // Verificar se o parágrafo modificado existe no conteúdo
        if ( strpos( $content, $modified_paragraph ) === false ) {
            return array(
                'success' => false,
                'message' => __( 'Parágrafo modificado não encontrado. O post pode ter sido alterado manualmente.', 'ai-internal-links-seo' ),
            );
        }

        // Restaurar parágrafo original
        $new_content = str_replace( $modified_paragraph, $original_paragraph, $content );

        // Atualizar post
        $update_result = wp_update_post(
            array(
                'ID'           => $post->ID,
                'post_content' => $new_content,
            ),
            true
        );

        if ( is_wp_error( $update_result ) ) {
            return array(
                'success' => false,
                'message' => $update_result->get_error_message(),
            );
        }

        // Atualizar sugestão
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table_name,
            array(
                'status'             => 'pending',
                'paragraph_modified' => null,
                'applied_at'         => null,
                'applied_by'         => null,
            ),
            array( 'id' => $suggestion_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        // Registrar log
        $this->log_action( $suggestion['post_id'], 'link_undone', array(
            'target_post_id' => $suggestion['target_post_id'],
        ), $suggestion_id );

        $this->log( 'Link desfeito: ' . $suggestion_id );

        return array(
            'success' => true,
            'message' => __( 'Link removido com sucesso! A sugestão voltou para pendente.', 'ai-internal-links-seo' ),
        );
    }

    /**
     * Substituir primeira ocorrência de uma string
     *
     * @param string $haystack String original.
     * @param string $needle   String a ser encontrada.
     * @param string $replace  String de substituição.
     * @return string String com a substituição.
     */
    private function replace_first_occurrence( $haystack, $needle, $replace ) {
        $pos = strpos( $haystack, $needle );

        if ( $pos !== false ) {
            return substr_replace( $haystack, $replace, $pos, strlen( $needle ) );
        }

        return $haystack;
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
            error_log( '[AI Internal Links SEO - Link Applier] ' . $message );
        }
    }
}
