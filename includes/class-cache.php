<?php
/**
 * Classe de gerenciamento de cache
 *
 * @package AIInternalLinksSEO
 */

namespace AIInternalLinksSEO;

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe Cache
 *
 * Responsável por gerenciar o cache de análises e resultados
 */
class Cache {

    /**
     * Prefixo para chaves de cache
     *
     * @var string
     */
    const PREFIX = 'ailseo_';

    /**
     * Tempo padrão de expiração do cache (24 horas)
     *
     * @var int
     */
    const DEFAULT_EXPIRATION = DAY_IN_SECONDS;

    /**
     * Obter valor do cache
     *
     * @param string $key Chave do cache.
     * @return mixed|false Valor do cache ou false se não encontrado.
     */
    public function get( $key ) {
        $cached = get_transient( self::PREFIX . $key );

        if ( false !== $cached ) {
            $this->log( 'Cache hit: ' . $key );
            return $cached;
        }

        $this->log( 'Cache miss: ' . $key );
        return false;
    }

    /**
     * Salvar valor no cache
     *
     * @param string $key        Chave do cache.
     * @param mixed  $value      Valor a ser salvo.
     * @param int    $expiration Tempo de expiração em segundos.
     * @return bool Se o cache foi salvo com sucesso.
     */
    public function set( $key, $value, $expiration = null ) {
        if ( null === $expiration ) {
            $expiration = self::DEFAULT_EXPIRATION;
        }

        $result = set_transient( self::PREFIX . $key, $value, $expiration );

        if ( $result ) {
            $this->log( 'Cache set: ' . $key );
        }

        return $result;
    }

    /**
     * Deletar valor do cache
     *
     * @param string $key Chave do cache.
     * @return bool Se o cache foi deletado com sucesso.
     */
    public function delete( $key ) {
        $result = delete_transient( self::PREFIX . $key );

        if ( $result ) {
            $this->log( 'Cache delete: ' . $key );
        }

        return $result;
    }

    /**
     * Gerar chave de cache para um post
     *
     * @param int $post_id ID do post.
     * @return string Chave de cache.
     */
    public function get_post_key( $post_id ) {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return 'post_' . $post_id;
        }

        // Incluir data de modificação para invalidar cache quando post é editado
        return 'post_' . $post_id . '_' . strtotime( $post->post_modified );
    }

    /**
     * Gerar chave de cache para lista de posts disponíveis
     *
     * @param array $args Argumentos da query.
     * @return string Chave de cache.
     */
    public function get_posts_list_key( $args = array() ) {
        return 'posts_list_' . md5( wp_json_encode( $args ) );
    }

    /**
     * Limpar todo o cache do plugin
     *
     * @return int Número de itens removidos.
     */
    public function clear_all() {
        global $wpdb;

        // Buscar todas as transients do plugin
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                WHERE option_name LIKE %s
                OR option_name LIKE %s",
                '_transient_' . self::PREFIX . '%',
                '_transient_timeout_' . self::PREFIX . '%'
            )
        );

        $count = 0;

        foreach ( $transients as $transient ) {
            $key = str_replace( array( '_transient_', '_transient_timeout_' ), '', $transient );

            if ( strpos( $key, self::PREFIX ) === 0 ) {
                delete_transient( $key );
                $count++;
            }
        }

        $this->log( 'Cache cleared: ' . $count . ' items' );

        return $count;
    }

    /**
     * Verificar se um post foi analisado recentemente
     *
     * @param int $post_id ID do post.
     * @return bool Se o post foi analisado recentemente.
     */
    public function is_post_recently_analyzed( $post_id ) {
        return false !== $this->get( $this->get_post_key( $post_id ) . '_analyzed' );
    }

    /**
     * Marcar post como analisado
     *
     * @param int $post_id ID do post.
     * @return bool Se a marcação foi salva com sucesso.
     */
    public function mark_post_analyzed( $post_id ) {
        return $this->set(
            $this->get_post_key( $post_id ) . '_analyzed',
            time(),
            self::DEFAULT_EXPIRATION
        );
    }

    /**
     * Log de operações de cache (apenas em modo debug)
     *
     * @param string $message Mensagem de log.
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[AI Internal Links SEO - Cache] ' . $message );
        }
    }
}
