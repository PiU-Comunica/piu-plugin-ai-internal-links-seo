<?php
/**
 * Template da página de Análise
 *
 * @package AIInternalLinksSEO
 */

// Impedir acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Obter categorias para filtro
$categories = get_categories( array( 'hide_empty' => false ) );
?>

<div class="wrap ailseo-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-search"></span>
        <?php esc_html_e( 'Analisar Posts', 'ai-internal-links-seo' ); ?>
    </h1>

    <!-- Verificar API Key -->
    <?php
    $api_key = get_option( 'ailseo_api_key', '' );
    if ( empty( $api_key ) ) :
    ?>
        <div class="notice notice-error">
            <p>
                <?php esc_html_e( 'API Key não configurada. Configure antes de analisar posts.', 'ai-internal-links-seo' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-settings' ) ); ?>">
                    <?php esc_html_e( 'Ir para Configurações', 'ai-internal-links-seo' ); ?>
                </a>
            </p>
        </div>
    <?php else : ?>

    <!-- Filtros -->
    <div class="ailseo-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="ailseo-analysis">

            <select name="post_type">
                <option value=""><?php esc_html_e( 'Todos os tipos', 'ai-internal-links-seo' ); ?></option>
                <?php foreach ( $post_types as $type ) : ?>
                    <?php $type_obj = get_post_type_object( $type ); ?>
                    <?php if ( $type_obj ) : ?>
                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filter_post_type, $type ); ?>>
                            <?php echo esc_html( $type_obj->labels->singular_name ); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <select name="category">
                <option value=""><?php esc_html_e( 'Todas as categorias', 'ai-internal-links-seo' ); ?></option>
                <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $filter_category, $cat->term_id ); ?>>
                        <?php echo esc_html( $cat->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">

            <button type="submit" class="button">
                <?php esc_html_e( 'Filtrar', 'ai-internal-links-seo' ); ?>
            </button>
        </form>
    </div>

    <!-- Lista de Posts -->
    <div class="ailseo-posts-list">
        <?php if ( $query->have_posts() ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-title"><?php esc_html_e( 'Título', 'ai-internal-links-seo' ); ?></th>
                        <th class="column-type"><?php esc_html_e( 'Tipo', 'ai-internal-links-seo' ); ?></th>
                        <?php
                        $next_order      = ( 'ASC' === $order ) ? 'DESC' : 'ASC';
                        $sort_url        = add_query_arg(
                            array(
                                'order' => $next_order,
                                'paged' => 1,
                            )
                        );
                        $sort_indicator  = ( 'ASC' === $order ) ? '&uarr;' : '&darr;';
                        $sort_aria_label = ( 'ASC' === $order )
                            ? esc_attr__( 'Ordenado por data, do mais antigo para o mais novo. Clique para inverter.', 'ai-internal-links-seo' )
                            : esc_attr__( 'Ordenado por data, do mais novo para o mais antigo. Clique para inverter.', 'ai-internal-links-seo' );
                        ?>
                        <th class="column-date ailseo-sortable">
                            <a href="<?php echo esc_url( $sort_url ); ?>" aria-label="<?php echo esc_attr( $sort_aria_label ); ?>">
                                <span><?php esc_html_e( 'Data', 'ai-internal-links-seo' ); ?></span>
                                <span class="ailseo-sort-arrow" aria-hidden="true"><?php echo wp_kses( $sort_indicator, array() ); ?></span>
                            </a>
                        </th>
                        <th class="column-suggestions"><?php esc_html_e( 'Sugestões', 'ai-internal-links-seo' ); ?></th>
                        <th class="column-actions"><?php esc_html_e( 'Ações', 'ai-internal-links-seo' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ( $query->have_posts() ) :
                        $query->the_post();
                        $post_id   = get_the_ID();
                        $post_type = get_post_type_object( get_post_type() );

                        // Contar sugestões existentes
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'ailseo_suggestions';
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $suggestion_counts = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT
                                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                                    SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied
                                FROM $table_name
                                WHERE post_id = %d",
                                $post_id
                            ),
                            ARRAY_A
                        );
                    ?>
                        <tr data-post-id="<?php echo esc_attr( $post_id ); ?>">
                            <td class="column-title">
                                <strong>
                                    <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="<?php the_permalink(); ?>" target="_blank">
                                            <?php esc_html_e( 'Ver', 'ai-internal-links-seo' ); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-type">
                                <?php echo esc_html( $post_type->labels->singular_name ); ?>
                            </td>
                            <td class="column-date">
                                <?php echo esc_html( get_the_date() ); ?>
                            </td>
                            <td class="column-suggestions">
                                <?php if ( $suggestion_counts['pending'] > 0 || $suggestion_counts['applied'] > 0 ) : ?>
                                    <span class="ailseo-badge pending" title="<?php esc_attr_e( 'Pendentes', 'ai-internal-links-seo' ); ?>">
                                        <?php echo esc_html( $suggestion_counts['pending'] ?? 0 ); ?>
                                    </span>
                                    <span class="ailseo-badge applied" title="<?php esc_attr_e( 'Aplicados', 'ai-internal-links-seo' ); ?>">
                                        <?php echo esc_html( $suggestion_counts['applied'] ?? 0 ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="ailseo-badge none">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <button type="button"
                                        class="button button-primary ailseo-analyze-btn"
                                        data-post-id="<?php echo esc_attr( $post_id ); ?>">
                                    <span class="dashicons dashicons-search"></span>
                                    <?php esc_html_e( 'Analisar', 'ai-internal-links-seo' ); ?>
                                </button>

                                <?php if ( $suggestion_counts['pending'] > 0 ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ailseo-suggestions&post_id=' . $post_id ) ); ?>"
                                       class="button">
                                        <?php esc_html_e( 'Ver Sugestões', 'ai-internal-links-seo' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Paginação -->
            <?php
            $total_pages = $query->max_num_pages;
            if ( $total_pages > 1 ) :
                $current_url = remove_query_arg( 'paged' );
            ?>
                <div class="ailseo-pagination">
                    <?php
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
            <div class="ailseo-no-posts">
                <p><?php esc_html_e( 'Nenhum post encontrado com os filtros selecionados.', 'ai-internal-links-seo' ); ?></p>
            </div>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </div>

    <?php endif; ?>

    <!-- Modal de Resultado da Análise -->
    <div id="ailseo-analysis-modal" class="ailseo-modal" style="display: none;">
        <div class="ailseo-modal-content">
            <span class="ailseo-modal-close">&times;</span>
            <h2><?php esc_html_e( 'Resultado da Análise', 'ai-internal-links-seo' ); ?></h2>
            <div id="ailseo-analysis-result"></div>
        </div>
    </div>
</div>
