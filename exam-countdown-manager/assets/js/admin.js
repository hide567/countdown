/**
 * 資格試験カウントダウンマネージャー - 管理画面JavaScript
 *
 * @package ExamCountdownManager
 */

(function($) {
    'use strict';

    // DOM読み込み完了時の処理
    $(document).ready(function() {
        initDeleteConfirmation();
        initFormValidation();
        initPreviewUpdates();
        initAjaxActions();
        initTabs();
        initTooltips();
    });

    /**
     * 削除確認ダイアログの初期化
     */
    function initDeleteConfirmation() {
        $('.ecm-delete-exam').on('click', function(e) {
            e.preventDefault();
            
            const examKey = $(this).data('exam-key');
            const examName = $(this).data('exam-name');
            
            if (!examKey || !examName) {
                alert('エラー: 資格試験の情報が取得できませんでした。');
                return;
            }
            
            const message = ecm_admin.messages.delete_confirm.replace('%s', examName);
            
            if (confirm(message)) {
                deleteExam(examKey);
            }
        });
    }

    /**
     * 資格試験削除処理
     */
    function deleteExam(examKey) {
        const $button = $('.ecm-delete-exam[data-exam-key="' + examKey + '"]');
        const $row = $button.closest('tr');
        
        // ローディング状態に設定
        $row.addClass('ecm-loading');
        $button.prop('disabled', true);
        
        $.ajax({
            url: ecm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ecm_delete_exam',
                exam_key: examKey,
                nonce: ecm_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // 成功メッセージを表示
                    showNotice(response.data.message, 'success');
                    
                    // 行をフェードアウトして削除
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // テーブルが空になった場合の処理
                        if ($('.wp-list-table tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    showNotice(response.data.message || 'エラーが発生しました。', 'error');
                    $row.removeClass('ecm-loading');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                showNotice('通信エラーが発生しました。', 'error');
                $row.removeClass('ecm-loading');
                $button.prop('disabled', false);
            }
        });
    }

    /**
     * フォームバリデーションの初期化
     */
    function initFormValidation() {
        $('.ecm-exam-form').on('submit', function(e) {
            const errors = validateExamForm($(this));
            
            if (errors.length > 0) {
                e.preventDefault();
                showValidationErrors(errors);
                return false;
            }
            
            // フォーム送信中の状態表示
            const $form = $(this);
            const $submitButton = $form.find('input[type="submit"]');
            
            $submitButton.val('処理中...').prop('disabled', true);
            $form.addClass('ecm-loading');
        });
        
        // リアルタイムバリデーション
        $('#exam_key').on('input', function() {
            validateExamKey($(this));
        });
        
        $('#exam_date').on('change', function() {
            validateExamDate($(this));
        });
        
        // プライマリ設定の排他制御
        $('input[name="primary_exam"]').on('change', function() {
            if ($(this).is(':checked')) {
                showNotice('他の資格試験のプライマリ設定は自動的に解除されます。', 'info');
            }
        });
    }

    /**
     * フォームバリデーション実行
     */
    function validateExamForm($form) {
        const errors = [];
        
        // 資格キーの検証
        const examKey = $form.find('#exam_key').val();
        if (examKey && !examKey.match(/^[a-zA-Z0-9_-]+$/)) {
            errors.push('資格キーは英数字、アンダースコア、ハイフンのみ使用可能です。');
        }
        
        // 必須フィールドの検証
        const requiredFields = [
            { selector: '#exam_name', label: '資格名称' },
            { selector: '#exam_date', label: '試験日' }
        ];
        
        requiredFields.forEach(function(field) {
            const $field = $form.find(field.selector);
            if (!$field.val().trim()) {
                errors.push(field.label + 'は必須です。');
            }
        });
        
        // 日付の検証
        const examDate = $form.find('#exam_date').val();
        if (examDate && !isValidDate(examDate)) {
            errors.push('試験日の形式が正しくありません。');
        }
        
        return errors;
    }

    /**
     * 資格キーのリアルタイムバリデーション
     */
    function validateExamKey($input) {
        const value = $input.val();
        const $feedback = $input.siblings('.validation-feedback');
        
        if (value && !value.match(/^[a-zA-Z0-9_-]+$/)) {
            showFieldError($input, '英数字、アンダースコア、ハイフンのみ使用可能です。');
        } else {
            clearFieldError($input);
        }
    }

    /**
     * 試験日のリアルタイムバリデーション
     */
    function validateExamDate($input) {
        const value = $input.val();
        
        if (value) {
            const examDate = new Date(value);
            const today = new Date();
            const daysDiff = Math.ceil((examDate - today) / (1000 * 60 * 60 * 24));
            
            if (daysDiff < 0) {
                showFieldWarning($input, '過去の日付が選択されています。');
            } else if (daysDiff > 730) { // 2年以上先
                showFieldWarning($input, '2年以上先の日付が選択されています。');
            } else {
                clearFieldError($input);
                if (daysDiff <= 30) {
                    showFieldInfo($input, `試験まであと${daysDiff}日です。`);
                }
            }
        }
    }

    /**
     * プレビュー更新の初期化
     */
    function initPreviewUpdates() {
        if ($('.ecm-preview-container').length === 0) return;
        
        // フォーム入力値の変更を監視
        $('.ecm-exam-form input, .ecm-exam-form select, .ecm-exam-form textarea').on('input change', function() {
            updatePreview();
        });
        
        // 初期プレビューを生成
        updatePreview();
    }

    /**
     * プレビュー更新処理
     */
    function updatePreview() {
        const examName = $('#exam_name').val() || '資格試験名';
        const examDate = $('#exam_date').val() || '2025-12-31';
        const displayCountdown = $('#display_countdown').is(':checked');
        
        if (!displayCountdown) {
            $('.ecm-preview-content').html('<p style="color: #666; font-style: italic;">カウントダウン表示が無効になっています。</p>');
            return;
        }
        
        const today = new Date();
        const targetDate = new Date(examDate);
        const timeDiff = targetDate - today;
        const daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
        
        let previewHTML = `
            <div class="ecm-countdown ecm-countdown-default">
                <div class="ecm-exam-name">${examName}</div>
                <div class="ecm-countdown-simple">
                    あと <span class="ecm-days-number">${daysDiff}</span> 日
                </div>
            </div>
        `;
        
        $('.ecm-preview-content').html(previewHTML);
    }

    /**
     * AJAX操作の初期化
     */
    function initAjaxActions() {
        // 設定保存のAJAX化（将来的な拡張用）
        $('.ecm-ajax-save').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $form = $button.closest('form');
            
            saveSettingsAjax($form, $button);
        });
    }

    /**
     * AJAX設定保存
     */
    function saveSettingsAjax($form, $button) {
        const originalText = $button.val();
        
        $button.val('保存中...').prop('disabled', true);
        
        $.ajax({
            url: ecm_admin.ajax_url,
            type: 'POST',
            data: $form.serialize() + '&action=ecm_save_settings&nonce=' + ecm_admin.nonce,
            success: function(response) {
                if (response.success) {
                    showNotice(ecm_admin.messages.save_success, 'success');
                    $button.val('保存完了').addClass('button-primary');
                    
                    setTimeout(function() {
                        $button.val(originalText).removeClass('button-primary').prop('disabled', false);
                    }, 2000);
                } else {
                    showNotice(response.data.message || '保存に失敗しました。', 'error');
                    $button.val(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showNotice('通信エラーが発生しました。', 'error');
                $button.val(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * タブ機能の初期化
     */
    function initTabs() {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            const href = $(this).attr('href');
            if (href && href !== '#') {
                window.location.href = href;
            }
        });
        
        // URLハッシュに基づいてタブを切り替え
        if (window.location.hash) {
            const targetTab = $(`.nav-tab[href*="${window.location.hash}"]`);
            if (targetTab.length) {
                $('.nav-tab').removeClass('nav-tab-active');
                targetTab.addClass('nav-tab-active');
            }
        }
    }

    /**
     * ツールチップの初期化
     */
    function initTooltips() {
        // WordPress標準のツールチップがない場合は独自実装
        $('[data-tooltip]').each(function() {
            const $element = $(this);
            const tooltipText = $element.data('tooltip');
            
            $element.on('mouseenter', function() {
                showTooltip($(this), tooltipText);
            }).on('mouseleave', function() {
                hideTooltip();
            });
        });
    }

    /**
     * 通知メッセージ表示
     */
    function showNotice(message, type) {
        type = type || 'info';
        
        const noticeClass = `notice notice-${type} is-dismissible`;
        const $notice = $(`<div class="${noticeClass}"><p>${message}</p></div>`);
        
        // 既存の通知を削除
        $('.notice.is-dismissible').remove();
        
        // 新しい通知を挿入
        if ($('.wrap h1').length) {
            $notice.insertAfter('.wrap h1');
        } else {
            $notice.prependTo('.wrap');
        }
        
        // 自動削除（エラー以外）
        if (type !== 'error') {
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        }
        
        // 閉じるボタンの処理
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }

    /**
     * バリデーションエラー表示
     */
    function showValidationErrors(errors) {
        const errorList = errors.map(error => `<li>${error}</li>`).join('');
        const errorHTML = `<ul style="margin: 0; padding-left: 20px;">${errorList}</ul>`;
        
        showNotice(errorHTML, 'error');
        
        // フォームの先頭にスクロール
        $('html, body').animate({
            scrollTop: $('.ecm-exam-form').offset().top - 50
        }, 300);
    }

    /**
     * フィールドエラー表示
     */
    function showFieldError($field, message) {
        clearFieldError($field);
        
        $field.addClass('error').after(
            `<div class="validation-feedback error">${message}</div>`
        );
    }

    /**
     * フィールド警告表示
     */
    function showFieldWarning($field, message) {
        clearFieldError($field);
        
        $field.addClass('warning').after(
            `<div class="validation-feedback warning">${message}</div>`
        );
    }

    /**
     * フィールド情報表示
     */
    function showFieldInfo($field, message) {
        clearFieldError($field);
        
        $field.after(
            `<div class="validation-feedback info">${message}</div>`
        );
    }

    /**
     * フィールドエラークリア
     */
    function clearFieldError($field) {
        $field.removeClass('error warning').siblings('.validation-feedback').remove();
    }

    /**
     * ツールチップ表示
     */
    function showTooltip($element, text) {
        const $tooltip = $('<div class="ecm-tooltip">' + text + '</div>');
        
        $('body').append($tooltip);
        
        const offset = $element.offset();
        const elementHeight = $element.outerHeight();
        
        $tooltip.css({
            position: 'absolute',
            top: offset.top + elementHeight + 5,
            left: offset.left,
            background: '#333',
            color: '#fff',
            padding: '8px 12px',
            borderRadius: '4px',
            fontSize: '12px',
            zIndex: 9999,
            whiteSpace: 'nowrap',
            boxShadow: '0 2px 8px rgba(0,0,0,0.3)'
        });
        
        // 画面外に出る場合の調整
        const tooltipWidth = $tooltip.outerWidth();
        const windowWidth = $(window).width();
        
        if (offset.left + tooltipWidth > windowWidth) {
            $tooltip.css('left', windowWidth - tooltipWidth - 10);
        }
    }

    /**
     * ツールチップ非表示
     */
    function hideTooltip() {
        $('.ecm-tooltip').remove();
    }

    /**
     * 日付妥当性チェック
     */
    function isValidDate(dateString) {
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date) && dateString === date.toISOString().split('T')[0];
    }

    /**
     * 統計情報の更新（将来の拡張用）
     */
    function updateStats() {
        if ($('.ecm-stats-grid').length === 0) return;
        
        $.ajax({
            url: ecm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ecm_get_stats',
                nonce: ecm_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                }
            }
        });
    }

    /**
     * 統計表示更新
     */
    function updateStatsDisplay(stats) {
        Object.keys(stats).forEach(function(key) {
            const $statBox = $(`.ecm-stat-box[data-stat="${key}"]`);
            if ($statBox.length) {
                $statBox.find('.ecm-stat-number').text(stats[key]);
            }
        });
    }

    /**
     * キーボードショートカット
     */
    $(document).on('keydown', function(e) {
        // Ctrl+S で設定保存
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            
            const $submitButton = $('.ecm-exam-form input[type="submit"]:visible');
            if ($submitButton.length) {
                $submitButton.click();
            }
        }
        
        // Escでモーダルを閉じる
        if (e.key === 'Escape') {
            $('.ecm-modal.show').removeClass('show');
        }
    });

    /**
     * ページ離脱前の確認（未保存の変更がある場合）
     */
    let formChanged = false;
    
    $('.ecm-exam-form input, .ecm-exam-form select, .ecm-exam-form textarea').on('change input', function() {
        formChanged = true;
    });
    
    $('.ecm-exam-form').on('submit', function() {
        formChanged = false;
    });
    
    $(window).on('beforeunload', function(e) {
        if (formChanged) {
            const message = '変更が保存されていません。ページを離れてもよろしいですか？';
            e.returnValue = message;
            return message;
        }
    });

    /**
     * 自動保存機能（将来的な拡張用）
     */
    function initAutoSave() {
        let autoSaveTimer;
        
        $('.ecm-exam-form input, .ecm-exam-form select, .ecm-exam-form textarea').on('input change', function() {
            clearTimeout(autoSaveTimer);
            
            autoSaveTimer = setTimeout(function() {
                if (formChanged) {
                    autoSaveForm();
                }
            }, 30000); // 30秒後に自動保存
        });
    }

    /**
     * フォーム自動保存
     */
    function autoSaveForm() {
        const $form = $('.ecm-exam-form');
        if ($form.length === 0) return;
        
        $.ajax({
            url: ecm_admin.ajax_url,
            type: 'POST',
            data: $form.serialize() + '&action=ecm_auto_save&nonce=' + ecm_admin.nonce,
            success: function(response) {
                if (response.success) {
                    showNotice('自動保存しました', 'info');
                    formChanged = false;
                }
            }
        });
    }

    // 初期化完了後の処理
    $(window).on('load', function() {
        // 統計情報の初期読み込み
        updateStats();
        
        // 自動保存機能の初期化（必要に応じて有効化）
        // initAutoSave();
    });

})(jQuery);