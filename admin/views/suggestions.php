<?php
/**
 * Template da página de Sugestões
 *
 * @package AIInternalLinksSEO
 */

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Calcular total de páginas
$total_pages = ceil( $total / $per_page );
?>

<div class="wrap ailseo-wrap">
    <div class="ailseo-suggestions-header">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-format-aside"></span>
            <?php esc_html_e( 'Sugestões de Links', 'ai-internal-links-seo' ); ?>
        </h1>

        <!-- Filtros por Status -->
        <ul class="subsubsub">
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-suggestions' ) ); ?>"
                class="<?php echo empty( $filter_status ) ? 'current' : ''; ?>">
                    <?php esc_html_e( 'Todas', 'ai-internal-links-seo' ); ?>
                    <span class="count">(<?php echo esc_html( $total ); ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-suggestions&status=pending' ) ); ?>"
                class="<?php echo 'pending' === $filter_status ? 'current' : ''; ?>">
                    <?php esc_html_e( 'Pendentes', 'ai-internal-links-seo' ); ?>
                    <span class="count">(<?php echo esc_html( isset( $status_counts['pending'] ) ? $status_counts['pending']->count : 0 ); ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-suggestions&status=applied' ) ); ?>"
                class="<?php echo 'applied' === $filter_status ? 'current' : ''; ?>">
                    <?php esc_html_e( 'Aplicadas', 'ai-internal-links-seo' ); ?>
                    <span class="count">(<?php echo esc_html( isset( $status_counts['applied'] ) ? $status_counts['applied']->count : 0 ); ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-suggestions&status=rejected' ) ); ?>"
                class="<?php echo 'rejected' === $filter_status ? 'current' : ''; ?>">
                    <?php esc_html_e( 'Rejeitadas', 'ai-internal-links-seo' ); ?>
                    <span class="count">(<?php echo esc_html( isset( $status_counts['rejected'] ) ? $status_counts['rejected']->count : 0 ); ?>)</span>
                </a>
            </li>
        </ul>
    </div>

    <br class="clear">

    <!-- Lista de Sugestões -->
    <?php if ( ! empty( $suggestions ) ) : ?>
        <div class="ailseo-suggestions-list">
            <?php foreach ( $suggestions as $suggestion ) : ?>
                <div class="ailseo-suggestion-card status-<?php echo esc_attr( $suggestion['status'] ); ?>"
                     data-suggestion-id="<?php echo esc_attr( $suggestion['id'] ); ?>">

                    <!-- Cabeçalho -->
                    <div class="ailseo-suggestion-header">
                        <div class="ailseo-suggestion-meta">
                            <span class="ailseo-suggestion-status <?php echo esc_attr( $suggestion['status'] ); ?>">
                                <?php
                                $status_labels = array(
                                    'pending'  => __( 'Pendente', 'ai-internal-links-seo' ),
                                    'applied'  => __( 'Aplicada', 'ai-internal-links-seo' ),
                                    'rejected' => __( 'Rejeitada', 'ai-internal-links-seo' ),
                                );
                                echo esc_html( $status_labels[ $suggestion['status'] ] ?? $suggestion['status'] );
                                ?>
                            </span>
                            <span class="ailseo-suggestion-score" title="<?php esc_attr_e( 'Score de Confiança', 'ai-internal-links-seo' ); ?>">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <?php echo esc_html( $suggestion['confidence_score'] ); ?>%
                            </span>
                        </div>
                        <div class="ailseo-suggestion-date">
                            <?php
                            printf(
                                /* translators: %s: date */
                                esc_html__( 'Criada em %s', 'ai-internal-links-seo' ),
                                esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $suggestion['created_at'] ) ) )
                            );
                            ?>
                        </div>
                    </div>

                    <!-- Informações do Link -->
                    <div class="ailseo-suggestion-info">
                        <div class="ailseo-suggestion-posts">
                            <div class="ailseo-suggestion-source">
                                <strong><?php esc_html_e( 'Post de Origem:', 'ai-internal-links-seo' ); ?></strong>
                                <a href="<?php echo esc_url( get_edit_post_link( $suggestion['post_id'] ) ); ?>" target="_blank">
                                    <?php echo esc_html( $suggestion['source_title'] ); ?>
                                </a>
                            </div>
                            <div class="ailseo-suggestion-arrow">
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </div>
                            <div class="ailseo-suggestion-target">
                                <strong><?php esc_html_e( 'Link para:', 'ai-internal-links-seo' ); ?></strong>
                                <a href="<?php echo esc_url( get_permalink( $suggestion['target_post_id'] ) ); ?>" target="_blank">
                                    <?php echo esc_html( $suggestion['target_title'] ); ?>
                                </a>
                            </div>
                        </div>

                        <div class="ailseo-suggestion-anchor">
                            <strong><?php esc_html_e( 'Texto Âncora:', 'ai-internal-links-seo' ); ?></strong>
                            <code><?php echo esc_html( $suggestion['suggested_anchor'] ); ?></code>
                        </div>
                    </div>

                    <!-- Preview do Parágrafo -->
                    <div class="ailseo-suggestion-preview">
                        <strong><?php esc_html_e( 'Contexto:', 'ai-internal-links-seo' ); ?></strong>
                        <div class="ailseo-paragraph-preview">
                            <?php
                            // Destacar o anchor text no parágrafo
                            $paragraph   = esc_html( $suggestion['paragraph_context'] );
                            $anchor      = esc_html( $suggestion['suggested_anchor'] );
                            $highlighted = preg_replace(
                                '/(' . preg_quote( $anchor, '/' ) . ')/i',
                                '<mark>$1</mark>',
                                $paragraph,
                                1
                            );
                            echo wp_kses( $highlighted, array( 'mark' => array() ) );
                            ?>
                        </div>
                    </div>

                    <!-- Justificativa -->
                    <?php if ( ! empty( $suggestion['justification'] ) ) : ?>
                        <div class="ailseo-suggestion-justification">
                            <strong><?php esc_html_e( 'Justificativa da IA:', 'ai-internal-links-seo' ); ?></strong>
                            <p><?php echo esc_html( $suggestion['justification'] ); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Ações -->
                    <div class="ailseo-suggestion-actions">
                        <?php if ( 'pending' === $suggestion['status'] ) : ?>
                            <button type="button"
                                    class="button button-primary ailseo-apply-btn"
                                    data-suggestion-id="<?php echo esc_attr( $suggestion['id'] ); ?>">
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e( 'Aplicar Link', 'ai-internal-links-seo' ); ?>
                            </button>
                            <button type="button"
                                    class="button ailseo-reject-btn"
                                    data-suggestion-id="<?php echo esc_attr( $suggestion['id'] ); ?>">
                                <span class="dashicons dashicons-no"></span>
                                <?php esc_html_e( 'Rejeitar', 'ai-internal-links-seo' ); ?>
                            </button>
                        <?php elseif ( 'applied' === $suggestion['status'] ) : ?>
                            <button type="button"
                                    class="button ailseo-undo-btn"
                                    data-suggestion-id="<?php echo esc_attr( $suggestion['id'] ); ?>">
                                <span class="dashicons dashicons-undo"></span>
                                <?php esc_html_e( 'Desfazer', 'ai-internal-links-seo' ); ?>
                            </button>
                            <span class="ailseo-applied-info">
                                <?php
                                if ( $suggestion['applied_at'] ) {
                                    printf(
                                        /* translators: %s: date */
                                        esc_html__( 'Aplicado em %s', 'ai-internal-links-seo' ),
                                        esc_html( date_i18n( get_option( 'date_format' ), strtotime( $suggestion['applied_at'] ) ) )
                                    );
                                }
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="ailseo-pagination">
                <?php
                $current_url = remove_query_arg( 'paged' );
                echo wp_kses_post( paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%', $current_url ),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ) ) );
                ?>
            </div>
        <?php endif; ?>

    <?php else : ?>
        <div class="ailseo-no-suggestions">
            <p>
                <?php if ( ! empty( $filter_status ) ) : ?>
                    <?php esc_html_e( 'Nenhuma sugestão encontrada com este status.', 'ai-internal-links-seo' ); ?>
                <?php else : ?>
                    <?php esc_html_e( 'Nenhuma sugestão encontrada. Analise alguns posts para gerar sugestões.', 'ai-internal-links-seo' ); ?>
                    <br><br>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-analysis' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Ir para Análise', 'ai-internal-links-seo' ); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>
