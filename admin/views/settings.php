<?php
/**
 * Template da página de Configurações
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
        <span class="dashicons dashicons-admin-generic"></span>
        <?php esc_html_e( 'Configurações', 'ai-internal-links-seo' ); ?>
    </h1>

    <form method="post" action="options.php" class="ailseo-settings-form">
        <?php settings_fields( 'ailseo_settings' ); ?>

        <!-- API Configuration -->
        <div class="ailseo-settings-section">
            <h2><?php esc_html_e( 'Configuração da API', 'ai-internal-links-seo' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ailseo_api_key">
                            <?php esc_html_e( 'API Key (Gemini)', 'ai-internal-links-seo' ); ?>
                        </label>
                    </th>
                    <td>
                        <div class="ailseo-api-key-field">
                            <input type="password"
                                   id="ailseo_api_key"
                                   name="ailseo_api_key"
                                   value="<?php echo esc_attr( $api_key ); ?>"
                                   class="regular-text"
                                   autocomplete="off">
                            <button type="button" class="button ailseo-toggle-password" title="<?php esc_attr_e( 'Mostrar/Ocultar', 'ai-internal-links-seo' ); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: link to Google AI Studio */
                                esc_html__( 'Obtenha sua API Key em %s', 'ai-internal-links-seo' ),
                                '<a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>'
                            );
                            ?>
                        </p>

                        <div class="ailseo-api-test-wrapper">
                            <button type="button" class="button" id="ailseo-test-api">
                                <?php esc_html_e( 'Testar Conexão', 'ai-internal-links-seo' ); ?>
                            </button>
                            <span id="ailseo-api-test-result"></span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ailseo_gemini_model">
                            <?php esc_html_e( 'Modelo Gemini', 'ai-internal-links-seo' ); ?>
                        </label>
                    </th>
                    <td>
                        <select id="ailseo_gemini_model" name="ailseo_gemini_model">
                            <?php foreach ( $gemini_models as $model_id => $model_label ) : ?>
                                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $gemini_model, $model_id ); ?>>
                                    <?php echo esc_html( $model_label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'O Gemini 2.5 Flash é o padrão recomendado. Modelos Preview podem mudar ou ser removidos pela API.', 'ai-internal-links-seo' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Post Types -->
        <div class="ailseo-settings-section">
            <h2><?php esc_html_e( 'Tipos de Conteúdo', 'ai-internal-links-seo' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Post Types para Análise', 'ai-internal-links-seo' ); ?>
                    </th>
                    <td>
                        <fieldset>
                            <?php foreach ( $available_post_types as $type ) : ?>
                                <?php if ( 'attachment' === $type->name ) continue; ?>
                                <label>
                                    <input type="checkbox"
                                           name="ailseo_post_types[]"
                                           value="<?php echo esc_attr( $type->name ); ?>"
                                           <?php checked( in_array( $type->name, $selected_types, true ) ); ?>>
                                    <?php echo esc_html( $type->labels->singular_name ); ?>
                                    <code>(<?php echo esc_html( $type->name ); ?>)</code>
                                </label>
                                <br>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">
                            <?php esc_html_e( 'Selecione quais tipos de conteúdo podem ser analisados e receber links.', 'ai-internal-links-seo' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Limits -->
        <div class="ailseo-settings-section">
            <h2><?php esc_html_e( 'Limites e Qualidade', 'ai-internal-links-seo' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ailseo_max_links_per_post">
                            <?php esc_html_e( 'Máximo de Links por Post', 'ai-internal-links-seo' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number"
                               id="ailseo_max_links_per_post"
                               name="ailseo_max_links_per_post"
                               value="<?php echo esc_attr( $max_links ); ?>"
                               min="1"
                               max="10"
                               class="small-text">
                        <p class="description">
                            <?php esc_html_e( 'Número máximo de links sugeridos por post (recomendado: 2-3).', 'ai-internal-links-seo' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ailseo_min_confidence_score">
                            <?php esc_html_e( 'Score Mínimo de Confiança', 'ai-internal-links-seo' ); ?>
                        </label>
                    </th>
                    <td>
                        <div class="ailseo-range-field">
                            <input type="range"
                                   id="ailseo_min_confidence_score"
                                   name="ailseo_min_confidence_score"
                                   value="<?php echo esc_attr( $min_score ); ?>"
                                   min="0"
                                   max="100"
                                   step="5">
                            <span class="ailseo-range-value"><?php echo esc_html( $min_score ); ?>%</span>
                        </div>
                        <p class="description">
                            <?php esc_html_e( 'Apenas sugestões com score acima deste valor serão salvas (recomendado: 70%).', 'ai-internal-links-seo' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Desinstalação -->
        <div class="ailseo-settings-section">
            <h2><?php esc_html_e( 'Desinstalação', 'ai-internal-links-seo' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Dados do plugin', 'ai-internal-links-seo' ); ?>
                    </th>
                    <td>
                        <input type="hidden" name="ailseo_delete_data_on_uninstall" value="0">
                        <label for="ailseo_delete_data_on_uninstall">
                            <input type="checkbox"
                                   id="ailseo_delete_data_on_uninstall"
                                   name="ailseo_delete_data_on_uninstall"
                                   value="1"
                                   <?php checked( $delete_on_uninstall ); ?>>
                            <?php esc_html_e( 'Excluir tabelas, configurações e cache ao deletar o plugin.', 'ai-internal-links-seo' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Esta ação será executada somente quando o plugin for excluído pelo painel do WordPress. Links já aplicados nos posts não serão removidos automaticamente.', 'ai-internal-links-seo' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Boas Práticas -->
        <div class="ailseo-settings-section ailseo-info-section">
            <h2><?php esc_html_e( 'Boas Práticas de SEO', 'ai-internal-links-seo' ); ?></h2>

            <div class="ailseo-tips">
                <ul>
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'Limite de 2-3 links internos por post é ideal para não parecer spam.', 'ai-internal-links-seo' ); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'Varie os textos âncora - não use sempre a mesma palavra-chave.', 'ai-internal-links-seo' ); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'Priorize links para posts antigos e relevantes.', 'ai-internal-links-seo' ); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'Links devem agregar valor real ao leitor.', 'ai-internal-links-seo' ); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'Evite links no primeiro parágrafo do post.', 'ai-internal-links-seo' ); ?>
                    </li>
                </ul>
            </div>
        </div>

        <?php submit_button( __( 'Salvar Configurações', 'ai-internal-links-seo' ) ); ?>
    </form>
</div>
