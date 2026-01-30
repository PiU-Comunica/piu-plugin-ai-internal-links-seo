<?php
/**
 * Classe principal do plugin
 *
 * @package AIInternalLinksSEO
 */

namespace AIInternalLinksSEO;

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe Plugin
 *
 * Responsável por inicializar todos os componentes do plugin
 */
class Plugin {

    /**
     * Instância da classe Admin
     *
     * @var Admin
     */
    private $admin;

    /**
     * Instância do cliente de IA
     *
     * @var AI_Client
     */
    private $ai_client;

    /**
     * Instância do analisador
     *
     * @var Analyzer
     */
    private $analyzer;

    /**
     * Instância do aplicador de links
     *
     * @var Link_Applier
     */
    private $link_applier;

    /**
     * Instância do sistema de cache
     *
     * @var Cache
     */
    private $cache;

    /**
     * Construtor
     */
    public function __construct() {
        $this->load_dependencies();
    }

    /**
     * Carregar dependências do plugin
     */
    private function load_dependencies() {
        // Carregar classes necessárias
        require_once AILSEO_PLUGIN_DIR . 'includes/class-cache.php';
        require_once AILSEO_PLUGIN_DIR . 'includes/class-ai-client.php';
        require_once AILSEO_PLUGIN_DIR . 'includes/class-analyzer.php';
        require_once AILSEO_PLUGIN_DIR . 'includes/class-link-applier.php';

        // Carregar admin se estiver no painel
        if ( is_admin() ) {
            require_once AILSEO_PLUGIN_DIR . 'admin/class-admin.php';
        }
    }

    /**
     * Executar o plugin
     */
    public function run() {
        // Inicializar componentes
        $this->cache        = new Cache();
        $this->ai_client    = new AI_Client();
        $this->analyzer     = new Analyzer( $this->ai_client, $this->cache );
        $this->link_applier = new Link_Applier();

        // Inicializar admin se estiver no painel
        if ( is_admin() ) {
            $this->admin = new Admin( $this->analyzer, $this->link_applier, $this->ai_client );
            $this->admin->init();
        }

        // Registrar AJAX handlers
        $this->register_ajax_handlers();
    }

    /**
     * Registrar handlers AJAX
     */
    private function register_ajax_handlers() {
        // Testar conexão com API
        add_action( 'wp_ajax_ailseo_test_api', array( $this, 'ajax_test_api' ) );

        // Analisar post único
        add_action( 'wp_ajax_ailseo_analyze_post', array( $this, 'ajax_analyze_post' ) );

        // Aplicar sugestão
        add_action( 'wp_ajax_ailseo_apply_suggestion', array( $this, 'ajax_apply_suggestion' ) );

        // Rejeitar sugestão
        add_action( 'wp_ajax_ailseo_reject_suggestion', array( $this, 'ajax_reject_suggestion' ) );

        // Obter sugestões de um post
        add_action( 'wp_ajax_ailseo_get_suggestions', array( $this, 'ajax_get_suggestions' ) );

        // Desfazer aplicação
        add_action( 'wp_ajax_ailseo_undo_suggestion', array( $this, 'ajax_undo_suggestion' ) );
    }

    /**
     * AJAX: Testar conexão com API
     */
    public function ajax_test_api() {
        // Verificar nonce
        check_ajax_referer( 'ailseo_nonce', 'nonce' );

        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permissão negada.', 'ai-internal-links-seo' ),
            ) );
        }

        // Obter API Key do POST (permite testar antes de salvar)
        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

        // Testar conexão (passa a chave temporariamente)
        $result = $this->ai_client->test_connection( $api_key );

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message' => $result['message'],
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result['message'],
            ) );
        }
    }

    /**
     * AJAX: Analisar post único
     */
    public function ajax_analyze_post() {
        // Verificar nonce
        check_ajax_referer( 'ailseo_nonce', 'nonce' );

        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permissão negada.', 'ai-internal-links-seo' ),
            ) );
        }

        // Obter ID do post
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array(
                'message' => __( 'ID do post inválido.', 'ai-internal-links-seo' ),
            ) );
        }

        // Analisar post
        $result = $this->analyzer->analyze_post( $post_id );

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message'     => $result['message'],
                'suggestions' => $result['suggestions'],
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result['message'],
            ) );
        }
    }

    /**
     * AJAX: Aplicar sugestão
     */
    public function ajax_apply_suggestion() {
        // Verificar nonce
        check_ajax_referer( 'ailseo_nonce', 'nonce' );

        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permissão negada.', 'ai-internal-links-seo' ),
            ) );
        }

        // Obter ID da sugestão
        $suggestion_id = isset( $_POST['suggestion_id'] ) ? absint( $_POST['suggestion_id'] ) : 0;

        if ( ! $suggestion_id ) {
            wp_send_json_error( array(
                'message' => __( 'ID da sugestão inválido.', 'ai-internal-links-seo' ),
            ) );
        }

        // Aplicar sugestão
        $result = $this->link_applier->apply_suggestion( $suggestion_id );

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message' => $result['message'],
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result['message'],
            ) );
        }
    }

    /**
     * AJAX: Rejeitar sugestão
     */
    public function ajax_reject_suggestion() {
        // Verificar nonce
        check_ajax_referer( 'ailseo_nonce', 'nonce' );

        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permissão negada.', 'ai-internal-links-seo' ),
            ) );
        }

        // Obter ID da sugestão
        $suggestion_id = isset( $_POST['suggestion_id'] ) ? absint( $_POST['suggestion_id'] ) : 0;

        if ( ! $suggestion_id ) {
            wp_send_json_error( array(
                'message' => __( 'ID da sugestão inválido.', 'ai-internal-links-seo' ),
            ) );
        }

        // Rejeitar sugestão
        $result = $this->link_applier->reject_suggestion( $suggestion_id );

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message' => $result['message'],
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result['message'],
            ) );
        }
    }

    /**
     * AJAX: Obter sugestões de um post
     */
    public function ajax_get_suggestions() {
        // Verificar nonce
        check_ajax_referer( 'ailseo_nonce', 'nonce' );

        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permissão negada.', 'ai-internal-links-seo' ),
            ) );
        }

        // Obter ID do post
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array(
                'message' => __( 'ID do post inválido.', 'ai-internal-links-seo' ),
            ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ailseo_suggestions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $suggestions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, p.post_title as target_title
                FROM $table_name s
                LEFT JOIN {$wpdb->posts} p ON s.target_post_id = p.ID
                WHERE s.post_id = %d
                ORDER BY s.confidence_score DESC",
                $post_id
            ),
            ARRAY_A
        );

        wp_send_json_success( array(
            'suggestions' => $suggestions,
        ) );
    }

    /**
     * AJAX: Desfazer aplicação de sugestão
     */
    public function ajax_undo_suggestion() {
        // Verificar nonce
        check_ajax_referer( 'ailseo_nonce', 'nonce' );

        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permissão negada.', 'ai-internal-links-seo' ),
            ) );
        }

        // Obter ID da sugestão
        $suggestion_id = isset( $_POST['suggestion_id'] ) ? absint( $_POST['suggestion_id'] ) : 0;

        if ( ! $suggestion_id ) {
            wp_send_json_error( array(
                'message' => __( 'ID da sugestão inválido.', 'ai-internal-links-seo' ),
            ) );
        }

        // Desfazer sugestão
        $result = $this->link_applier->undo_suggestion( $suggestion_id );

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message' => $result['message'],
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result['message'],
            ) );
        }
    }
}
