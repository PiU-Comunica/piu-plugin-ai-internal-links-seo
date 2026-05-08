<?php
/**
 * Rotina de desinstalação do plugin.
 *
 * @package AIInternalLinksSEO
 */

// Impedir acesso direto fora do fluxo de uninstall do WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$delete_data = (bool) get_option( 'ailseo_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
    return;
}

global $wpdb;

$suggestions_table = $wpdb->prefix . 'ailseo_suggestions';
$log_table         = $wpdb->prefix . 'ailseo_log';

// Remover tabelas próprias do plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$suggestions_table}, {$log_table}" );

$options = array(
    'ailseo_api_key',
    'ailseo_gemini_model',
    'ailseo_post_types',
    'ailseo_max_links_per_post',
    'ailseo_min_confidence_score',
    'ailseo_db_version',
    'ailseo_delete_data_on_uninstall',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remover transients do cache do plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options}
        WHERE option_name LIKE %s
        OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_ailseo_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_ailseo_' ) . '%'
    )
);

wp_clear_scheduled_hook( 'ailseo_scheduled_analysis' );
