<?php
/**
 * Classe de administração do plugin
 *
 * @package AIInternalLinksSEO
 */

namespace AIInternalLinksSEO;

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe Admin
 *
 * Responsável por toda a interface administrativa do plugin
 */
class Admin {

    /**
     * Analisador de conteúdo
     *
     * @var Analyzer
     */
    private $analyzer;

    /**
     * Aplicador de links
     *
     * @var Link_Applier
     */
    private $link_applier;

    /**
     * Cliente de IA
     *
     * @var AI_Client
     */
    private $ai_client;

    /**
     * Construtor
     *
     * @param Analyzer     $analyzer     Instância do analisador.
     * @param Link_Applier $link_applier Instância do aplicador de links.
     * @param AI_Client    $ai_client    Instância do cliente de IA.
     */
    public function __construct( Analyzer $analyzer, Link_Applier $link_applier, AI_Client $ai_client ) {
        $this->analyzer     = $analyzer;
        $this->link_applier = $link_applier;
        $this->ai_client    = $ai_client;
    }

    /**
     * Inicializar admin
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Adicionar páginas ao menu
     */
    public function add_menu_pages() {
        // Menu principal
        add_menu_page(
            __( 'AI Internal Links', 'ai-internal-links-seo' ),
            __( 'AI Internal Links', 'ai-internal-links-seo' ),
            'manage_options',
            'ailseo-dashboard',
            array( $this, 'render_dashboard_page' ),
            'dashicons-admin-links',
            80
        );

        // Submenu: Dashboard
        add_submenu_page(
            'ailseo-dashboard',
            __( 'Dashboard', 'ai-internal-links-seo' ),
            __( 'Dashboard', 'ai-internal-links-seo' ),
            'manage_options',
            'ailseo-dashboard',
            array( $this, 'render_dashboard_page' )
        );

        // Submenu: Analisar Posts
        add_submenu_page(
            'ailseo-dashboard',
            __( 'Analisar Posts', 'ai-internal-links-seo' ),
            __( 'Analisar Posts', 'ai-internal-links-seo' ),
            'manage_options',
            'ailseo-analysis',
            array( $this, 'render_analysis_page' )
        );

        // Submenu: Sugestões
        add_submenu_page(
            'ailseo-dashboard',
            __( 'Sugestões', 'ai-internal-links-seo' ),
            __( 'Sugestões', 'ai-internal-links-seo' ),
            'manage_options',
            'ailseo-suggestions',
            array( $this, 'render_suggestions_page' )
        );

        // Submenu: Configurações
        add_submenu_page(
            'ailseo-dashboard',
            __( 'Configurações', 'ai-internal-links-seo' ),
            __( 'Configurações', 'ai-internal-links-seo' ),
            'manage_options',
            'ailseo-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Registrar configurações
     */
    public function register_settings() {
        // API Key
        register_setting(
            'ailseo_settings',
            'ailseo_api_key',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        // Post Types
        register_setting(
            'ailseo_settings',
            'ailseo_post_types',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_post_types' ),
                'default'           => array( 'post' ),
            )
        );

        // Máximo de links por post
        register_setting(
            'ailseo_settings',
            'ailseo_max_links_per_post',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 3,
            )
        );

        // Score mínimo de confiança
        register_setting(
            'ailseo_settings',
            'ailseo_min_confidence_score',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_confidence_score' ),
                'default'           => 70,
            )
        );
    }

    /**
     * Sanitizar post types
     *
     * @param mixed $input Input a ser sanitizado.
     * @return array Post types sanitizados.
     */
    public function sanitize_post_types( $input ) {
        if ( ! is_array( $input ) ) {
            return array( 'post' );
        }

        $valid_post_types = get_post_types( array( 'public' => true ), 'names' );

        return array_filter( $input, function ( $type ) use ( $valid_post_types ) {
            return in_array( $type, $valid_post_types, true );
        } );
    }

    /**
     * Sanitizar score de confiança
     *
     * @param mixed $input Input a ser sanitizado.
     * @return int Score sanitizado (entre 0 e 100).
     */
    public function sanitize_confidence_score( $input ) {
        $score = absint( $input );
        return min( 100, max( 0, $score ) );
    }

    /**
     * Enfileirar assets
     *
     * @param string $hook Hook da página atual.
     */
    public function enqueue_assets( $hook ) {
        // Verificar se estamos em uma página do plugin
        if ( strpos( $hook, 'ailseo' ) === false ) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'ailseo-admin',
            AILSEO_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            AILSEO_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'ailseo-admin',
            AILSEO_PLUGIN_URL . 'admin/assets/js/admin.js',
            array( 'jquery' ),
            AILSEO_VERSION,
            true
        );

        // Localizar script
        wp_localize_script( 'ailseo-admin', 'ailseo', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ailseo_nonce' ),
            'i18n'     => array(
                'confirm_apply'    => __( 'Aplicar este link no post?', 'ai-internal-links-seo' ),
                'confirm_reject'   => __( 'Rejeitar esta sugestão?', 'ai-internal-links-seo' ),
                'confirm_undo'     => __( 'Desfazer este link e restaurar o texto original?', 'ai-internal-links-seo' ),
                'analyzing'        => __( 'Analisando...', 'ai-internal-links-seo' ),
                'applying'         => __( 'Aplicando...', 'ai-internal-links-seo' ),
                'error'            => __( 'Ocorreu um erro. Tente novamente.', 'ai-internal-links-seo' ),
                'success'          => __( 'Operação realizada com sucesso!', 'ai-internal-links-seo' ),
                'testing_api'      => __( 'Testando conexão...', 'ai-internal-links-seo' ),
            ),
        ) );
    }

    /**
     * Renderizar página Dashboard
     */
    public function render_dashboard_page() {
        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'ai-internal-links-seo' ) );
        }

        // Obter estatísticas
        $stats = $this->analyzer->get_stats();

        include AILSEO_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Renderizar página de Análise
     */
    public function render_analysis_page() {
        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'ai-internal-links-seo' ) );
        }

        // Obter configurações
        $post_types = get_option( 'ailseo_post_types', array( 'post' ) );

        // Paginação
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        // Filtros
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_category = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;

        // Query de posts
        $args = array(
            'post_type'      => ! empty( $filter_post_type ) ? $filter_post_type : $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( $filter_category ) {
            $args['cat'] = $filter_category;
        }

        $query = new \WP_Query( $args );

        include AILSEO_PLUGIN_DIR . 'admin/views/analysis.php';
    }

    /**
     * Renderizar página de Sugestões
     */
    public function render_suggestions_page() {
        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'ai-internal-links-seo' ) );
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'ailseo_suggestions';

        // Filtro de status
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

        // Paginação
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $paged    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $per_page = 20;
        $offset   = ( $paged - 1 ) * $per_page;

        // Construir query
        $where = '';
        if ( ! empty( $filter_status ) ) {
            $where = $wpdb->prepare( 'WHERE s.status = %s', $filter_status );
        }

        // Total de itens
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name s $where" );

        // Sugestões
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $suggestions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*,
                    p1.post_title as source_title,
                    p2.post_title as target_title
                FROM $table_name s
                LEFT JOIN {$wpdb->posts} p1 ON s.post_id = p1.ID
                LEFT JOIN {$wpdb->posts} p2 ON s.target_post_id = p2.ID
                $where
                ORDER BY s.created_at DESC
                LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        // Contadores por status
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
            OBJECT_K
        );

        include AILSEO_PLUGIN_DIR . 'admin/views/suggestions.php';
    }

    /**
     * Renderizar página de Configurações
     */
    public function render_settings_page() {
        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'ai-internal-links-seo' ) );
        }

        // Obter post types disponíveis
        $available_post_types = get_post_types( array( 'public' => true ), 'objects' );

        // Obter configurações atuais
        $api_key          = get_option( 'ailseo_api_key', '' );
        $selected_types   = get_option( 'ailseo_post_types', array( 'post' ) );
        $max_links        = get_option( 'ailseo_max_links_per_post', 3 );
        $min_score        = get_option( 'ailseo_min_confidence_score', 70 );

        include AILSEO_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
