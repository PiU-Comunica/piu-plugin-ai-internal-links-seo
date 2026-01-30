<?php
/**
 * Plugin Name: AI Internal Links SEO
 * Plugin URI: https://piu.digital
 * Description: Plugin que analisa posts e sugere links internos usando IA (Gemini API) para melhorar o SEO do seu blog.
 * Version: 1.0.0
 * Author: PIU Digital
 * Author URI: https://piu.digital
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-internal-links-seo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package AIInternalLinksSEO
 */

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constantes do plugin
define( 'AILSEO_VERSION', '1.0.0' );
define( 'AILSEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AILSEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AILSEO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'AILSEO_TEXT_DOMAIN', 'ai-internal-links-seo' );

/**
 * Autoloader para classes do plugin
 *
 * @param string $class_name Nome da classe a ser carregada.
 */
spl_autoload_register( function ( $class_name ) {
    // Namespace base do plugin
    $namespace = 'AIInternalLinksSEO\\';

    // Verificar se a classe pertence ao nosso namespace
    if ( strpos( $class_name, $namespace ) !== 0 ) {
        return;
    }

    // Remover namespace base e converter para caminho de arquivo
    $relative_class = str_replace( $namespace, '', $class_name );
    $relative_class = strtolower( str_replace( '_', '-', $relative_class ) );
    $relative_class = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class );

    // Construir caminho do arquivo
    $file = AILSEO_PLUGIN_DIR . 'includes/class-' . $relative_class . '.php';

    // Verificar também na pasta admin
    if ( ! file_exists( $file ) ) {
        $file = AILSEO_PLUGIN_DIR . 'admin/class-' . $relative_class . '.php';
    }

    // Carregar arquivo se existir
    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

/**
 * Função de ativação do plugin
 */
function ailseo_activate() {
    // Criar tabela customizada no banco de dados
    global $wpdb;

    $table_name      = $wpdb->prefix . 'ailseo_suggestions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        suggested_anchor varchar(255) NOT NULL,
        target_post_id bigint(20) unsigned NOT NULL,
        paragraph_context text NOT NULL,
        paragraph_modified text,
        confidence_score tinyint(3) unsigned DEFAULT 0,
        justification text,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        applied_at datetime DEFAULT NULL,
        applied_by bigint(20) unsigned DEFAULT NULL,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY target_post_id (target_post_id),
        KEY status (status)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Criar tabela de log
    $log_table = $wpdb->prefix . 'ailseo_log';

    $sql_log = "CREATE TABLE IF NOT EXISTS $log_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        suggestion_id bigint(20) unsigned DEFAULT NULL,
        action varchar(50) NOT NULL,
        details text,
        user_id bigint(20) unsigned NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY action (action)
    ) $charset_collate;";

    dbDelta( $sql_log );

    // Salvar versão do banco de dados
    add_option( 'ailseo_db_version', AILSEO_VERSION );

    // Limpar rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ailseo_activate' );

/**
 * Função de desativação do plugin
 */
function ailseo_deactivate() {
    // Limpar scheduled events
    wp_clear_scheduled_hook( 'ailseo_scheduled_analysis' );

    // Limpar rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ailseo_deactivate' );

/**
 * Inicializar o plugin
 */
function ailseo_init() {
    // Carregar traduções
    load_plugin_textdomain(
        AILSEO_TEXT_DOMAIN,
        false,
        dirname( AILSEO_PLUGIN_BASENAME ) . '/languages'
    );

    // Inicializar classe principal
    $plugin = new AIInternalLinksSEO\Plugin();
    $plugin->run();
}
add_action( 'plugins_loaded', 'ailseo_init' );

/**
 * Adicionar link de configurações na página de plugins
 *
 * @param array $links Links existentes.
 * @return array Links modificados.
 */
function ailseo_plugin_action_links( $links ) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url( 'admin.php?page=ailseo-settings' ),
        __( 'Configurações', 'ai-internal-links-seo' )
    );

    array_unshift( $links, $settings_link );

    return $links;
}
add_filter( 'plugin_action_links_' . AILSEO_PLUGIN_BASENAME, 'ailseo_plugin_action_links' );
