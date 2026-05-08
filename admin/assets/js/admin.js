/**
 * AI Internal Links SEO - Admin JavaScript
 *
 * @package AIInternalLinksSEO
 */

(function($) {
    'use strict';

    /**
     * Objeto principal do plugin
     */
    var AILSEO = {
        /**
         * Inicialização
         */
        init: function() {
            this.bindEvents();
            this.initRangeSlider();
        },

        /**
         * Vincular eventos
         */
        bindEvents: function() {
            // Testar API
            $(document).on('click', '#ailseo-test-api', this.testAPI);

            // Toggle senha
            $(document).on('click', '.ailseo-toggle-password', this.togglePassword);

            // Analisar post
            $(document).on('click', '.ailseo-analyze-btn', this.analyzePost);

            // Aplicar sugestão
            $(document).on('click', '.ailseo-apply-btn', this.applySuggestion);

            // Rejeitar sugestão
            $(document).on('click', '.ailseo-reject-btn', this.rejectSuggestion);

            // Desfazer sugestão
            $(document).on('click', '.ailseo-undo-btn', this.undoSuggestion);

            // Restaurar sugestão rejeitada
            $(document).on('click', '.ailseo-restore-btn', this.restoreSuggestion);

            // Fechar modal
            $(document).on('click', '.ailseo-modal-close, .ailseo-modal', this.closeModal);
            $(document).on('click', '.ailseo-modal-content', function(e) {
                e.stopPropagation();
            });

            // ESC para fechar modal
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') {
                    AILSEO.closeModal();
                }
            });
        },

        /**
         * Inicializar slider de range
         */
        initRangeSlider: function() {
            var $range = $('#ailseo_min_confidence_score');
            var $value = $('.ailseo-range-value');

            if ($range.length) {
                $range.on('input', function() {
                    $value.text($(this).val() + '%');
                });
            }
        },

        /**
         * Testar conexão com API
         */
        testAPI: function() {
            var $button = $(this);
            var $result = $('#ailseo-api-test-result');
            var apiKey = $('#ailseo_api_key').val().trim();
            var model = $('#ailseo_gemini_model').val();

            // Validar se a API Key foi preenchida
            if (!apiKey) {
                $result.addClass('error').text('Por favor, insira a API Key antes de testar.');
                return;
            }

            $button.prop('disabled', true).addClass('ailseo-loading');
            $result.removeClass('success error').text(ailseo.i18n.testing_api);

            $.ajax({
                url: ailseo.ajax_url,
                type: 'POST',
                data: {
                    action: 'ailseo_test_api',
                    nonce: ailseo.nonce,
                    api_key: apiKey,
                    model: model
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text(response.data.message);
                    } else {
                        $result.addClass('error').text(response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('error').text(ailseo.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ailseo-loading');
                }
            });
        },

        /**
         * Toggle visibilidade da senha
         */
        togglePassword: function() {
            var $input = $('#ailseo_api_key');
            var $icon = $(this).find('.dashicons');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        },

        /**
         * Analisar post
         */
        analyzePost: function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            var $row = $button.closest('tr');

            $button.prop('disabled', true).addClass('ailseo-loading');
            $button.find('.dashicons').css('visibility', 'hidden');

            $.ajax({
                url: ailseo.ajax_url,
                type: 'POST',
                data: {
                    action: 'ailseo_analyze_post',
                    nonce: ailseo.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        AILSEO.showModal(
                            '<div class="notice notice-success inline"><p>' +
                            response.data.message +
                            '</p></div>' +
                            AILSEO.renderSuggestionsPreview(response.data.suggestions)
                        );

                        // Atualizar badges na linha
                        if (response.data.suggestions && response.data.suggestions.length > 0) {
                            var $suggestionsCell = $row.find('.column-suggestions');
                            var pendingCount = response.data.suggestions.length;
                            var currentApplied = parseInt($suggestionsCell.find('.ailseo-badge.applied').text()) || 0;

                            $suggestionsCell.html(
                                '<span class="ailseo-badge pending" title="Pendentes">' + pendingCount + '</span> ' +
                                '<span class="ailseo-badge applied" title="Aplicados">' + currentApplied + '</span>'
                            );
                        }
                    } else {
                        AILSEO.showModal(
                            '<div class="notice notice-error inline"><p>' +
                            response.data.message +
                            '</p></div>'
                        );
                    }
                },
                error: function() {
                    AILSEO.showModal(
                        '<div class="notice notice-error inline"><p>' +
                        ailseo.i18n.error +
                        '</p></div>'
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ailseo-loading');
                    $button.find('.dashicons').css('visibility', 'visible');
                }
            });
        },

        /**
         * Renderizar preview das sugestões
         */
        renderSuggestionsPreview: function(suggestions) {
            if (!suggestions || suggestions.length === 0) {
                return '<p>Nenhuma sugestão encontrada para este post.</p>';
            }

            var html = '<div class="ailseo-suggestions-preview">';
            html += '<h3>Sugestões Encontradas:</h3>';

            suggestions.forEach(function(suggestion) {
                html += '<div class="ailseo-preview-item">';
                html += '<p><strong>Texto âncora:</strong> <code>' + AILSEO.escapeHtml(suggestion.anchor_text) + '</code></p>';
                html += '<p><strong>Score:</strong> ' + suggestion.confidence_score + '%</p>';
                if (suggestion.justification) {
                    html += '<p><strong>Justificativa:</strong> ' + AILSEO.escapeHtml(suggestion.justification) + '</p>';
                }
                html += '</div>';
            });

            html += '<p style="margin-top: 20px;"><a href="' + window.location.origin + '/wp-admin/admin.php?page=ailseo-suggestions&status=pending" class="button button-primary">Ver Todas as Sugestões</a></p>';
            html += '</div>';

            return html;
        },

        /**
         * Aplicar sugestão
         */
        applySuggestion: function() {
            var $button = $(this);
            var suggestionId = $button.data('suggestion-id');
            var $card = $button.closest('.ailseo-suggestion-card');

            if (!confirm(ailseo.i18n.confirm_apply)) {
                return;
            }

            $button.prop('disabled', true).addClass('ailseo-loading');

            $.ajax({
                url: ailseo.ajax_url,
                type: 'POST',
                data: {
                    action: 'ailseo_apply_suggestion',
                    nonce: ailseo.nonce,
                    suggestion_id: suggestionId
                },
                success: function(response) {
                    if (response.success) {
                        // Atualizar UI
                        $card.removeClass('status-pending').addClass('status-applied');
                        $card.find('.ailseo-suggestion-status')
                            .removeClass('pending')
                            .addClass('applied')
                            .text('Aplicada');

                        // Substituir botões
                        var $actions = $card.find('.ailseo-suggestion-actions');
                        $actions.html(
                            '<button type="button" class="button ailseo-undo-btn" data-suggestion-id="' + suggestionId + '">' +
                            '<span class="dashicons dashicons-undo"></span> Desfazer</button>' +
                            '<span class="ailseo-applied-info">Aplicado agora</span>'
                        );

                        AILSEO.showNotice('success', response.data.message);
                    } else {
                        AILSEO.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    AILSEO.showNotice('error', ailseo.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ailseo-loading');
                }
            });
        },

        /**
         * Rejeitar sugestão
         */
        rejectSuggestion: function() {
            var $button = $(this);
            var suggestionId = $button.data('suggestion-id');
            var $card = $button.closest('.ailseo-suggestion-card');

            if (!confirm(ailseo.i18n.confirm_reject)) {
                return;
            }

            $button.prop('disabled', true).addClass('ailseo-loading');

            $.ajax({
                url: ailseo.ajax_url,
                type: 'POST',
                data: {
                    action: 'ailseo_reject_suggestion',
                    nonce: ailseo.nonce,
                    suggestion_id: suggestionId
                },
                success: function(response) {
                    if (response.success) {
                        // Atualizar UI
                        $card.removeClass('status-pending').addClass('status-rejected');
                        $card.find('.ailseo-suggestion-status')
                            .removeClass('pending')
                            .addClass('rejected')
                            .text('Rejeitada');

                        // Remover botões
                        $card.find('.ailseo-suggestion-actions').html('');

                        AILSEO.showNotice('success', response.data.message);
                    } else {
                        AILSEO.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    AILSEO.showNotice('error', ailseo.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ailseo-loading');
                }
            });
        },

        /**
         * Desfazer sugestão
         */
        undoSuggestion: function() {
            var $button = $(this);
            var suggestionId = $button.data('suggestion-id');
            var $card = $button.closest('.ailseo-suggestion-card');

            if (!confirm(ailseo.i18n.confirm_undo)) {
                return;
            }

            $button.prop('disabled', true).addClass('ailseo-loading');

            $.ajax({
                url: ailseo.ajax_url,
                type: 'POST',
                data: {
                    action: 'ailseo_undo_suggestion',
                    nonce: ailseo.nonce,
                    suggestion_id: suggestionId
                },
                success: function(response) {
                    if (response.success) {
                        // Atualizar UI
                        $card.removeClass('status-applied').addClass('status-pending');
                        $card.find('.ailseo-suggestion-status')
                            .removeClass('applied')
                            .addClass('pending')
                            .text('Pendente');

                        // Restaurar botões
                        var $actions = $card.find('.ailseo-suggestion-actions');
                        $actions.html(
                            '<button type="button" class="button button-primary ailseo-apply-btn" data-suggestion-id="' + suggestionId + '">' +
                            '<span class="dashicons dashicons-yes"></span> Aplicar Link</button> ' +
                            '<button type="button" class="button ailseo-reject-btn" data-suggestion-id="' + suggestionId + '">' +
                            '<span class="dashicons dashicons-no"></span> Rejeitar</button>'
                        );

                        AILSEO.showNotice('success', response.data.message);
                    } else {
                        AILSEO.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    AILSEO.showNotice('error', ailseo.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ailseo-loading');
                }
            });
        },

        /**
         * Restaurar sugestão rejeitada
         */
        restoreSuggestion: function() {
            var $button = $(this);
            var suggestionId = $button.data('suggestion-id');
            var $card = $button.closest('.ailseo-suggestion-card');

            if (!confirm(ailseo.i18n.confirm_restore)) {
                return;
            }

            $button.prop('disabled', true).addClass('ailseo-loading');

            $.ajax({
                url: ailseo.ajax_url,
                type: 'POST',
                data: {
                    action: 'ailseo_restore_suggestion',
                    nonce: ailseo.nonce,
                    suggestion_id: suggestionId
                },
                success: function(response) {
                    if (response.success) {
                        $card.removeClass('status-rejected').addClass('status-pending');
                        $card.find('.ailseo-suggestion-status')
                            .removeClass('rejected')
                            .addClass('pending')
                            .text('Pendente');

                        var $actions = $card.find('.ailseo-suggestion-actions');
                        $actions.html(
                            '<button type="button" class="button button-primary ailseo-apply-btn" data-suggestion-id="' + suggestionId + '">' +
                            '<span class="dashicons dashicons-yes"></span> Aplicar Link</button> ' +
                            '<button type="button" class="button ailseo-reject-btn" data-suggestion-id="' + suggestionId + '">' +
                            '<span class="dashicons dashicons-no"></span> Rejeitar</button>'
                        );

                        AILSEO.showNotice('success', response.data.message);
                    } else {
                        AILSEO.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    AILSEO.showNotice('error', ailseo.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ailseo-loading');
                }
            });
        },

        /**
         * Mostrar modal
         */
        showModal: function(content) {
            var $modal = $('#ailseo-analysis-modal');
            var $result = $('#ailseo-analysis-result');

            if ($modal.length === 0) {
                $modal = $(
                    '<div id="ailseo-analysis-modal" class="ailseo-modal">' +
                    '<div class="ailseo-modal-content">' +
                    '<span class="ailseo-modal-close">&times;</span>' +
                    '<h2>Resultado da Análise</h2>' +
                    '<div id="ailseo-analysis-result"></div>' +
                    '</div>' +
                    '</div>'
                );
                $('body').append($modal);
                $result = $('#ailseo-analysis-result');
            }

            $result.html(content);
            $modal.fadeIn(200);
        },

        /**
         * Fechar modal
         */
        closeModal: function() {
            $('#ailseo-analysis-modal').fadeOut(200);
        },

        /**
         * Mostrar notificação
         */
        showNotice: function(type, message) {
            var $notice = $(
                '<div class="notice notice-' + type + ' is-dismissible ailseo-notice">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dispensar</span></button>' +
                '</div>'
            );

            $('.ailseo-wrap h1').after($notice);

            // Auto-remover após 5 segundos
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Remover ao clicar
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Escapar HTML
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Inicializar quando DOM estiver pronto
    $(document).ready(function() {
        AILSEO.init();
    });

})(jQuery);
