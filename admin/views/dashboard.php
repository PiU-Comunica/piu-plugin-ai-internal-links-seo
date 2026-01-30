<?php
/**
 * Template da página Dashboard
 *
 * @package AIInternalLinksSEO
 */

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap ailseo-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-links"></span>
        <?php esc_html_e( 'AI Internal Links SEO', 'ai-internal-links-seo' ); ?>
    </h1>

    <div class="ailseo-dashboard">
        <!-- Cards de Estatísticas -->
        <div class="ailseo-stats-grid">
            <div class="ailseo-stat-card">
                <div class="ailseo-stat-icon pending">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="ailseo-stat-content">
                    <span class="ailseo-stat-number"><?php echo esc_html( $stats['pending'] ?? 0 ); ?></span>
                    <span class="ailseo-stat-label"><?php esc_html_e( 'Pendentes', 'ai-internal-links-seo' ); ?></span>
                </div>
            </div>

            <div class="ailseo-stat-card">
                <div class="ailseo-stat-icon applied">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="ailseo-stat-content">
                    <span class="ailseo-stat-number"><?php echo esc_html( $stats['applied'] ?? 0 ); ?></span>
                    <span class="ailseo-stat-label"><?php esc_html_e( 'Aplicados', 'ai-internal-links-seo' ); ?></span>
                </div>
            </div>

            <div class="ailseo-stat-card">
                <div class="ailseo-stat-icon rejected">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="ailseo-stat-content">
                    <span class="ailseo-stat-number"><?php echo esc_html( $stats['rejected'] ?? 0 ); ?></span>
                    <span class="ailseo-stat-label"><?php esc_html_e( 'Rejeitados', 'ai-internal-links-seo' ); ?></span>
                </div>
            </div>

            <div class="ailseo-stat-card">
                <div class="ailseo-stat-icon analyzed">
                    <span class="dashicons dashicons-search"></span>
                </div>
                <div class="ailseo-stat-content">
                    <span class="ailseo-stat-number"><?php echo esc_html( $stats['posts_analyzed'] ?? 0 ); ?></span>
                    <span class="ailseo-stat-label"><?php esc_html_e( 'Posts Analisados', 'ai-internal-links-seo' ); ?></span>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="ailseo-quick-actions">
            <h2><?php esc_html_e( 'Ações Rápidas', 'ai-internal-links-seo' ); ?></h2>

            <div class="ailseo-action-buttons">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-analysis' ) ); ?>" class="button button-primary button-hero">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Analisar Posts', 'ai-internal-links-seo' ); ?>
                </a>

                <?php if ( ( $stats['pending'] ?? 0 ) > 0 ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-suggestions&status=pending' ) ); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-format-aside"></span>
                    <?php
                    printf(
                        /* translators: %d: number of pending suggestions */
                        esc_html__( 'Ver %d Sugestões Pendentes', 'ai-internal-links-seo' ),
                        esc_html( $stats['pending'] )
                    );
                    ?>
                </a>
                <?php endif; ?>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-settings' ) ); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Configurações', 'ai-internal-links-seo' ); ?>
                </a>
            </div>
        </div>

        <!-- Status da API -->
        <div class="ailseo-api-status">
            <h2><?php esc_html_e( 'Status da API', 'ai-internal-links-seo' ); ?></h2>

            <?php
            $api_key = get_option( 'ailseo_api_key', '' );
            if ( empty( $api_key ) ) :
            ?>
                <div class="notice notice-warning inline">
                    <p>
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'API Key não configurada.', 'ai-internal-links-seo' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-settings' ) ); ?>">
                            <?php esc_html_e( 'Configurar agora', 'ai-internal-links-seo' ); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <div class="ailseo-api-test">
                    <p>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php esc_html_e( 'API Key configurada.', 'ai-internal-links-seo' ); ?>
                    </p>
                    <button type="button" class="button" id="ailseo-test-api">
                        <?php esc_html_e( 'Testar Conexão', 'ai-internal-links-seo' ); ?>
                    </button>
                    <span id="ailseo-api-test-result"></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Como Usar -->
        <div class="ailseo-how-to">
            <h2><?php esc_html_e( 'Como Usar', 'ai-internal-links-seo' ); ?></h2>

            <ol class="ailseo-steps">
                <li>
                    <strong><?php esc_html_e( 'Configure a API Key', 'ai-internal-links-seo' ); ?></strong>
                    <p><?php esc_html_e( 'Acesse Configurações e insira sua chave da API Gemini.', 'ai-internal-links-seo' ); ?></p>
                </li>
                <li>
                    <strong><?php esc_html_e( 'Selecione um Post', 'ai-internal-links-seo' ); ?></strong>
                    <p><?php esc_html_e( 'Vá em "Analisar Posts" e escolha qual post deseja analisar.', 'ai-internal-links-seo' ); ?></p>
                </li>
                <li>
                    <strong><?php esc_html_e( 'Revise as Sugestões', 'ai-internal-links-seo' ); ?></strong>
                    <p><?php esc_html_e( 'A IA irá sugerir links internos relevantes. Revise cada sugestão.', 'ai-internal-links-seo' ); ?></p>
                </li>
                <li>
                    <strong><?php esc_html_e( 'Aplique os Links', 'ai-internal-links-seo' ); ?></strong>
                    <p><?php esc_html_e( 'Aprove as sugestões que fizerem sentido para inserir os links automaticamente.', 'ai-internal-links-seo' ); ?></p>
                </li>
            </ol>
        </div>
    </div>
</div>
