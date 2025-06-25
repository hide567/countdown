<?php
/**
 * 資格試験設定管理クラス（カラー設定対応版・完全版）
 *
 * @package ExamCountdownManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ECM_Exam_Settings クラス
 */
class ECM_Exam_Settings {
    
    /**
     * インスタンス
     */
    private static $instance = null;
    
    /**
     * 設定ページのスラッグ
     */
    private $page_slug = 'exam-countdown-settings';
    
    /**
     * シングルトンインスタンスを取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_ecm_save_exam', array($this, 'ajax_save_exam'));
        add_action('wp_ajax_ecm_delete_exam', array($this, 'ajax_delete_exam'));
        add_action('wp_head', array($this, 'output_custom_colors'));
    }
    
    /**
     * 管理メニューを追加
     */
    public function add_admin_menu() {
        add_menu_page(
            __('資格試験設定', 'exam-countdown-manager'),
            __('資格試験', 'exam-countdown-manager'),
            'manage_options',
            $this->page_slug,
            array($this, 'settings_page'),
            'dashicons-calendar-alt',
            29
        );
        
        // サブメニュー追加
        add_submenu_page(
            $this->page_slug,
            __('資格試験管理', 'exam-countdown-manager'),
            __('資格管理', 'exam-countdown-manager'),
            'manage_options',
            $this->page_slug,
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            $this->page_slug,
            __('カウントダウン設定', 'exam-countdown-manager'),
            __('カウントダウン', 'exam-countdown-manager'),
            'manage_options',
            $this->page_slug . '-countdown',
            array($this, 'countdown_settings_page')
        );
        
        add_submenu_page(
            $this->page_slug,
            __('使い方・ヘルプ', 'exam-countdown-manager'),
            __('使い方', 'exam-countdown-manager'),
            'manage_options',
            $this->page_slug . '-help',
            array($this, 'help_page')
        );
    }
    
    /**
     * 設定を初期化
     */
    public function init_settings() {
        register_setting('ecm_settings', 'ecm_exam_settings_data');
        register_setting('ecm_settings', 'ecm_countdown_display_options');
    }
    
    /**
     * カスタムカラーをCSSに出力
     */
    public function output_custom_colors() {
        $options = get_option('ecm_countdown_display_options', array());
        
        $custom_css = '';
        
        // カスタムCSS変数を定義
        if (!empty($options['header_bg_color']) || !empty($options['header_text_color']) || 
            !empty($options['footer_bg_color']) || !empty($options['footer_text_color'])) {
            $custom_css .= ':root {';
            
            if (!empty($options['header_bg_color'])) {
                $custom_css .= '--ecm-custom-header-bg: ' . esc_attr($options['header_bg_color']) . ';';
            }
            
            if (!empty($options['header_text_color'])) {
                $custom_css .= '--ecm-custom-header-text: ' . esc_attr($options['header_text_color']) . ';';
            }
            
            if (!empty($options['footer_bg_color'])) {
                $custom_css .= '--ecm-custom-footer-bg: ' . esc_attr($options['footer_bg_color']) . ';';
            }
            
            if (!empty($options['footer_text_color'])) {
                $custom_css .= '--ecm-custom-footer-text: ' . esc_attr($options['footer_text_color']) . ';';
            }
            
            $custom_css .= '}';
        }
        
        // カスタムカラーが設定されている場合のスタイル適用
        if (!empty($options['header_bg_color']) || !empty($options['header_text_color'])) {
            $custom_css .= '.ecm-countdown-header { ';
            if (!empty($options['header_bg_color'])) {
                $custom_css .= 'background: var(--ecm-custom-header-bg) !important; ';
            }
            if (!empty($options['header_text_color'])) {
                $custom_css .= 'color: var(--ecm-custom-header-text) !important; ';
            }
            $custom_css .= '}';
        }
        
        if (!empty($options['footer_bg_color']) || !empty($options['footer_text_color'])) {
            $custom_css .= '.ecm-countdown-footer { ';
            if (!empty($options['footer_bg_color'])) {
                $custom_css .= 'background: var(--ecm-custom-footer-bg) !important; ';
            }
            if (!empty($options['footer_text_color'])) {
                $custom_css .= 'color: var(--ecm-custom-footer-text) !important; ';
            }
            $custom_css .= '}';
        }
        
        if (!empty($custom_css)) {
            echo '<style type="text/css" id="ecm-custom-colors">' . $custom_css . '</style>';
        }
    }
    
    /**
     * メイン設定ページ
     */
    public function settings_page() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // フォーム処理
        $this->handle_form_submission();
        
        // 現在のタブを取得
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list';
        
        // 資格試験データを取得
        $exams = get_option('ecm_exam_settings_data', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- タブナビゲーション -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=list" 
                   class="nav-tab <?php echo $active_tab == 'list' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('資格一覧', 'exam-countdown-manager'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=add" 
                   class="nav-tab <?php echo $active_tab == 'add' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('新規追加', 'exam-countdown-manager'); ?>
                </a>
                <?php if (isset($_GET['edit'])): ?>
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=edit&edit=<?php echo esc_attr($_GET['edit']); ?>" 
                   class="nav-tab <?php echo $active_tab == 'edit' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('編集', 'exam-countdown-manager'); ?>
                </a>
                <?php endif; ?>
            </h2>
            
            <?php $this->display_tab_content($active_tab, $exams); ?>
        </div>
        <?php
    }
    
    /**
     * タブコンテンツを表示
     */
    private function display_tab_content($active_tab, $exams) {
        switch ($active_tab) {
            case 'list':
                $this->display_exam_list($exams);
                break;
            case 'add':
                $this->display_add_form();
                break;
            case 'edit':
                if (isset($_GET['edit']) && isset($exams[$_GET['edit']])) {
                    $this->display_edit_form($_GET['edit'], $exams[$_GET['edit']]);
                } else {
                    $this->display_exam_list($exams);
                }
                break;
            default:
                $this->display_exam_list($exams);
                break;
        }
    }
    
    /**
     * 資格試験一覧を表示
     */
    private function display_exam_list($exams) {
        ?>
        <div class="ecm-admin-section">
            <h3><?php _e('登録済み資格試験', 'exam-countdown-manager'); ?></h3>
            
            <?php if (empty($exams)): ?>
                <div class="notice notice-info">
                    <p><?php _e('資格試験が登録されていません。「新規追加」タブから登録してください。', 'exam-countdown-manager'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 15%;"><?php _e('資格キー', 'exam-countdown-manager'); ?></th>
                            <th style="width: 25%;"><?php _e('名称', 'exam-countdown-manager'); ?></th>
                            <th style="width: 15%;"><?php _e('試験日', 'exam-countdown-manager'); ?></th>
                            <th style="width: 10%;"><?php _e('カテゴリー', 'exam-countdown-manager'); ?></th>
                            <th style="width: 10%;"><?php _e('ステータス', 'exam-countdown-manager'); ?></th>
                            <th style="width: 10%;"><?php _e('残り日数', 'exam-countdown-manager'); ?></th>
                            <th style="width: 15%;"><?php _e('操作', 'exam-countdown-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $key => $exam): 
                            $days_left = ecm_get_days_until_exam($exam['date']);
                            $categories = ecm_get_exam_categories();
                            $category_name = isset($categories[$exam['category']]) ? $categories[$exam['category']] : __('未設定', 'exam-countdown-manager');
                        ?>
                            <tr>
                                <td><code><?php echo esc_html($key); ?></code></td>
                                <td>
                                    <strong><?php echo esc_html($exam['name']); ?></strong>
                                    <?php if (isset($exam['primary']) && $exam['primary']): ?>
                                        <span class="ecm-primary-badge"><?php _e('プライマリ', 'exam-countdown-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($exam['date']); ?></td>
                                <td><?php echo esc_html($category_name); ?></td>
                                <td>
                                    <?php if (isset($exam['display_countdown']) && $exam['display_countdown']): ?>
                                        <span class="ecm-status-active"><?php _e('表示中', 'exam-countdown-manager'); ?></span>
                                    <?php else: ?>
                                        <span class="ecm-status-inactive"><?php _e('非表示', 'exam-countdown-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($days_left < 0): ?>
                                        <span class="ecm-status-finished"><?php _e('終了', 'exam-countdown-manager'); ?></span>
                                    <?php else: ?>
                                        <span class="ecm-days-left"><?php echo esc_html($days_left) . __('日', 'exam-countdown-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=edit&edit=<?php echo esc_attr($key); ?>" 
                                       class="button button-small"><?php _e('編集', 'exam-countdown-manager'); ?></a>
                                    <button type="button" class="button button-small button-link-delete ecm-delete-exam" 
                                            data-exam-key="<?php echo esc_attr($key); ?>" 
                                            data-exam-name="<?php echo esc_attr($exam['name']); ?>">
                                        <?php _e('削除', 'exam-countdown-manager'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <p class="submit">
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=add" class="button button-primary">
                    <?php _e('新しい資格試験を追加', 'exam-countdown-manager'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * 新規追加フォームを表示
     */
    private function display_add_form() {
        $categories = ecm_get_exam_categories();
        ?>
        <div class="ecm-admin-section">
            <h3><?php _e('新しい資格試験を追加', 'exam-countdown-manager'); ?></h3>
            
            <form method="post" action="" class="ecm-exam-form">
                <?php wp_nonce_field('ecm_add_exam', 'ecm_add_exam_nonce'); ?>
                <input type="hidden" name="action" value="add_exam">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="exam_key"><?php _e('資格キー', 'exam-countdown-manager'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="exam_key" id="exam_key" class="regular-text" 
                                   pattern="[a-zA-Z0-9_-]+" required>
                            <p class="description">
                                <?php _e('システム内で使用される英数字のID（例：gyouseishoshi, takken など）', 'exam-countdown-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exam_name"><?php _e('資格名称', 'exam-countdown-manager'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="exam_name" id="exam_name" class="regular-text" required>
                            <p class="description">
                                <?php _e('表示される資格試験名（例：行政書士試験、宅建士試験 など）', 'exam-countdown-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exam_date"><?php _e('試験日', 'exam-countdown-manager'); ?> *</label>
                        </th>
                        <td>
                            <input type="date" name="exam_date" id="exam_date" class="regular-text" required>
                            <p class="description">
                                <?php _e('試験実施日を選択してください', 'exam-countdown-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exam_category"><?php _e('カテゴリー', 'exam-countdown-manager'); ?></label>
                        </th>
                        <td>
                            <select name="exam_category" id="exam_category" class="regular-text">
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exam_description"><?php _e('説明', 'exam-countdown-manager'); ?></label>
                        </th>
                        <td>
                            <textarea name="exam_description" id="exam_description" class="large-text" rows="3"></textarea>
                            <p class="description">
                                <?php _e('資格試験の簡単な説明（任意）', 'exam-countdown-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('表示設定', 'exam-countdown-manager'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="display_countdown" checked>
                                    <?php _e('カウントダウンを表示する', 'exam-countdown-manager'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="primary_exam">
                                    <?php _e('プライマリ資格試験として設定', 'exam-countdown-manager'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('プライマリに設定すると、サイト全体のメインカウントダウンとして表示されます', 'exam-countdown-manager'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('追加', 'exam-countdown-manager'); ?>">
                    <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=list" class="button">
                        <?php _e('キャンセル', 'exam-countdown-manager'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * 編集フォームを表示
     */
    private function display_edit_form($exam_key, $exam) {
        $categories = ecm_get_exam_categories();
        ?>
        <div class="ecm-admin-section">
            <h3><?php echo sprintf(__('%s の編集', 'exam-countdown-manager'), esc_html($exam['name'])); ?></h3>
            
            <form method="post" action="" class="ecm-exam-form">
                <?php wp_nonce_field('ecm_edit_exam', 'ecm_edit_exam_nonce'); ?>
                <input type="hidden" name="action" value="edit_exam">
                <input type="hidden" name="exam_key" value="<?php echo esc_attr($exam_key); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('資格キー', 'exam-countdown-manager'); ?></th>
                        <td>
                            <code><?php echo esc_html($exam_key); ?></code>
                            <p class="description"><?php _e('資格キーは編集できません', 'exam-countdown-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exam_name"><?php _e('資格名称', 'exam-countdown-manager'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="exam_name" id="exam_name" class="regular-text" 
                                   value="<?php echo esc_attr($exam['name']); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exam_date"><?php _e('試験日', 'exam-countdown-manager'); ?> *</label>
                        </th>
                        <td>
                            <input type="date" name="exam_date" id="exam_date" class="regular-text" 
                                   value="<?php echo esc_attr($exam['date']); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exam_category"><?php _e('カテゴリー', 'exam-countdown-manager'); ?></label>
                        </th>
                        <td>
                            <select name="exam_category" id="exam_category" class="regular-text">
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" 
                                            <?php selected(isset($exam['category']) ? $exam['category'] : 'other', $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exam_description"><?php _e('説明', 'exam-countdown-manager'); ?></label>
                        </th>
                        <td>
                            <textarea name="exam_description" id="exam_description" class="large-text" rows="3"><?php echo esc_textarea($exam['description']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('表示設定', 'exam-countdown-manager'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="display_countdown" 
                                           <?php checked(isset($exam['display_countdown']) && $exam['display_countdown']); ?>>
                                    <?php _e('カウントダウンを表示する', 'exam-countdown-manager'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="primary_exam" 
                                           <?php checked(isset($exam['primary']) && $exam['primary']); ?>>
                                    <?php _e('プライマリ資格試験として設定', 'exam-countdown-manager'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('更新', 'exam-countdown-manager'); ?>">
                    <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=list" class="button">
                        <?php _e('キャンセル', 'exam-countdown-manager'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * フォーム送信処理
     */
    private function handle_form_submission() {
        if (!isset($_POST['action'])) {
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'add_exam':
                $this->handle_add_exam();
                break;
            case 'edit_exam':
                $this->handle_edit_exam();
                break;
        }
    }
    
    /**
     * 資格試験追加処理
     */
    private function handle_add_exam() {
        // ノンス確認
        if (!wp_verify_nonce($_POST['ecm_add_exam_nonce'], 'ecm_add_exam')) {
            wp_die(__('セキュリティチェックに失敗しました。', 'exam-countdown-manager'));
        }
        
        $exam_key = sanitize_key($_POST['exam_key']);
        $exam_name = sanitize_text_field($_POST['exam_name']);
        $exam_date = sanitize_text_field($_POST['exam_date']);
        $exam_category = sanitize_text_field($_POST['exam_category']);
        $exam_description = sanitize_textarea_field($_POST['exam_description']);
        $display_countdown = isset($_POST['display_countdown']);
        $primary_exam = isset($_POST['primary_exam']);
        
        // バリデーション
        if (empty($exam_key) || empty($exam_name) || empty($exam_date)) {
            add_settings_error('ecm_messages', 'ecm_message', 
                __('資格キー、名称、試験日は必須です。', 'exam-countdown-manager'), 'error');
            return;
        }
        
        // 日付の形式チェック
        if (!$this->validate_date($exam_date)) {
            add_settings_error('ecm_messages', 'ecm_message', 
                __('試験日の形式が正しくありません。', 'exam-countdown-manager'), 'error');
            return;
        }
        
        $exams = get_option('ecm_exam_settings_data', array());
        
        // 重複チェック
        if (isset($exams[$exam_key])) {
            add_settings_error('ecm_messages', 'ecm_message', 
                __('このキーは既に使用されています。', 'exam-countdown-manager'), 'error');
            return;
        }
        
        // プライマリ設定の場合、他のプライマリフラグをオフに
        if ($primary_exam) {
            foreach ($exams as $key => $exam) {
                $exams[$key]['primary'] = false;
            }
        }
        
        // 新しい資格試験を追加
        $exams[$exam_key] = array(
            'name' => $exam_name,
            'date' => $exam_date,
            'category' => $exam_category,
            'description' => $exam_description,
            'display_countdown' => $display_countdown,
            'primary' => $primary_exam,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        update_option('ecm_exam_settings_data', $exams);
        
        add_settings_error('ecm_messages', 'ecm_message', 
            __('新しい資格試験を追加しました。', 'exam-countdown-manager'), 'success');
    }
    
    /**
     * 資格試験編集処理
     */
    private function handle_edit_exam() {
        // ノンス確認
        if (!wp_verify_nonce($_POST['ecm_edit_exam_nonce'], 'ecm_edit_exam')) {
            wp_die(__('セキュリティチェックに失敗しました。', 'exam-countdown-manager'));
        }
        
        $exam_key = sanitize_key($_POST['exam_key']);
        $exam_name = sanitize_text_field($_POST['exam_name']);
        $exam_date = sanitize_text_field($_POST['exam_date']);
        $exam_category = sanitize_text_field($_POST['exam_category']);
        $exam_description = sanitize_textarea_field($_POST['exam_description']);
        $display_countdown = isset($_POST['display_countdown']);
        $primary_exam = isset($_POST['primary_exam']);
        
        // バリデーション
        if (empty($exam_name) || empty($exam_date)) {
            add_settings_error('ecm_messages', 'ecm_message', 
                __('名称と試験日は必須です。', 'exam-countdown-manager'), 'error');
            return;
        }
        
        // 日付の形式チェック
        if (!$this->validate_date($exam_date)) {
            add_settings_error('ecm_messages', 'ecm_message', 
                __('試験日の形式が正しくありません。', 'exam-countdown-manager'), 'error');
            return;
        }
        
        $exams = get_option('ecm_exam_settings_data', array());
        
        if (!isset($exams[$exam_key])) {
            add_settings_error('ecm_messages', 'ecm_message', 
                __('指定された資格試験が見つかりません。', 'exam-countdown-manager'), 'error');
            return;
        }
        
        // プライマリ設定の場合、他のプライマリフラグをオフに
        if ($primary_exam) {
            foreach ($exams as $key => $exam) {
                if ($key !== $exam_key) {
                    $exams[$key]['primary'] = false;
                }
            }
        }
        
        // 資格試験データを更新
        $exams[$exam_key] = array_merge($exams[$exam_key], array(
            'name' => $exam_name,
            'date' => $exam_date,
            'category' => $exam_category,
            'description' => $exam_description,
            'display_countdown' => $display_countdown,
            'primary' => $primary_exam,
            'updated_at' => current_time('mysql')
        ));
        
        update_option('ecm_exam_settings_data', $exams);
        
        add_settings_error('ecm_messages', 'ecm_message', 
            __('資格試験情報を更新しました。', 'exam-countdown-manager'), 'success');
    }
    
    /**
     * AJAX: 資格試験削除
     */
    public function ajax_delete_exam() {
        // ノンス確認
        if (!wp_verify_nonce($_POST['nonce'], 'ecm_admin_nonce')) {
            wp_die(__('セキュリティチェックに失敗しました。', 'exam-countdown-manager'));
        }
        
        $exam_key = sanitize_key($_POST['exam_key']);
        $exams = get_option('ecm_exam_settings_data', array());
        
        if (isset($exams[$exam_key])) {
            unset($exams[$exam_key]);
            update_option('ecm_exam_settings_data', $exams);
            
            // プライマリが削除された場合、最初の要素をプライマリに設定
            $has_primary = false;
            foreach ($exams as $exam) {
                if (isset($exam['primary']) && $exam['primary']) {
                    $has_primary = true;
                    break;
                }
            }
            
            if (!$has_primary && !empty($exams)) {
                $first_key = array_key_first($exams);
                $exams[$first_key]['primary'] = true;
                update_option('ecm_exam_settings_data', $exams);
            }
            
            wp_send_json_success(array(
                'message' => __('資格試験を削除しました。', 'exam-countdown-manager')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('指定された資格試験が見つかりません。', 'exam-countdown-manager')
            ));
        }
    }
    
    /**
     * カウントダウン設定ページ（カラー設定追加版）
     */
    public function countdown_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 設定保存処理
        if (isset($_POST['submit'])) {
            $this->save_countdown_settings();
        }
        
        $options = get_option('ecm_countdown_display_options', array(
            'show_in_header' => false,
            'show_in_footer' => false,
            'countdown_style' => 'default',
            'show_detailed_time' => false,
            'header_bg_color' => '#2c3e50',
            'header_text_color' => '#ffffff',
            'footer_bg_color' => '#34495e',
            'footer_text_color' => '#ffffff'
        ));
        ?>
        <div class="wrap">
            <h1><?php _e('カウントダウン設定', 'exam-countdown-manager'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ecm_countdown_settings', 'ecm_countdown_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('表示場所', 'exam-countdown-manager'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="show_in_header" 
                                           <?php checked($options['show_in_header']); ?>>
                                    <?php _e('ヘッダーに表示', 'exam-countdown-manager'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="show_in_footer" 
                                           <?php checked($options['show_in_footer']); ?>>
                                    <?php _e('フッターに表示', 'exam-countdown-manager'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="countdown_style"><?php _e('表示スタイル', 'exam-countdown-manager'); ?></label>
                        </th>
                        <td>
                            <select name="countdown_style" id="countdown_style">
                                <option value="default" <?php selected($options['countdown_style'], 'default'); ?>>
                                    <?php _e('デフォルト', 'exam-countdown-manager'); ?>
                                </option>
                                <option value="simple" <?php selected($options['countdown_style'], 'simple'); ?>>
                                    <?php _e('シンプル', 'exam-countdown-manager'); ?>
                                </option>
                                <option value="detailed" <?php selected($options['countdown_style'], 'detailed'); ?>>
                                    <?php _e('詳細表示', 'exam-countdown-manager'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('詳細時間表示', 'exam-countdown-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_detailed_time" 
                                       <?php checked($options['show_detailed_time']); ?>>
                                <?php _e('日数だけでなく時間・分も表示する', 'exam-countdown-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <!-- ヘッダーカラー設定 -->
                    <tr>
                        <th scope="row"><?php _e('ヘッダー表示色設定', 'exam-countdown-manager'); ?></th>
                        <td>
                            <fieldset>
                                <label for="header_bg_color"><?php _e('背景色:', 'exam-countdown-manager'); ?></label>
                                <input type="color" name="header_bg_color" id="header_bg_color" 
                                       value="<?php echo esc_attr($options['header_bg_color']); ?>" class="color-field">
                                <br><br>
                                <label for="header_text_color"><?php _e('文字色:', 'exam-countdown-manager'); ?></label>
                                <input type="color" name="header_text_color" id="header_text_color" 
                                       value="<?php echo esc_attr($options['header_text_color']); ?>" class="color-field">
                                <p class="description">
                                    <?php _e('ヘッダーに表示されるカウントダウンの色を設定できます', 'exam-countdown-manager'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <!-- フッターカラー設定 -->
                    <tr>
                        <th scope="row"><?php _e('フッター表示色設定', 'exam-countdown-manager'); ?></th>
                        <td>
                            <fieldset>
                                <label for="footer_bg_color"><?php _e('背景色:', 'exam-countdown-manager'); ?></label>
                                <input type="color" name="footer_bg_color" id="footer_bg_color" 
                                       value="<?php echo esc_attr($options['footer_bg_color']); ?>" class="color-field">
                                <br><br>
                                <label for="footer_text_color"><?php _e('文字色:', 'exam-countdown-manager'); ?></label>
                                <input type="color" name="footer_text_color" id="footer_text_color" 
                                       value="<?php echo esc_attr($options['footer_text_color']); ?>" class="color-field">
                                <p class="description">
                                    <?php _e('フッターに表示されるカウントダウンの色を設定できます', 'exam-countdown-manager'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <!-- プレビュー表示 -->
                <div class="ecm-admin-section">
                    <h3><?php _e('プレビュー', 'exam-countdown-manager'); ?></h3>
                    <div class="ecm-preview-container">
                        <div class="ecm-preview-content">
                            <div id="header-preview" class="ecm-countdown ecm-countdown-header" style="margin-bottom: 10px;">
                                <div class="ecm-exam-name"><?php _e('サンプル試験', 'exam-countdown-manager'); ?></div>
                                <div class="ecm-countdown-default">
                                    <?php _e('あと', 'exam-countdown-manager'); ?> <span class="ecm-days-number">50</span> <?php _e('日', 'exam-countdown-manager'); ?>
                                </div>
                            </div>
                            <div id="footer-preview" class="ecm-countdown ecm-countdown-footer">
                                <div class="ecm-exam-name"><?php _e('サンプル試験', 'exam-countdown-manager'); ?></div>
                                <div class="ecm-countdown-default">
                                    <?php _e('あと', 'exam-countdown-manager'); ?> <span class="ecm-days-number">50</span> <?php _e('日', 'exam-countdown-manager'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="ecm-preview-note">
                            <p class="description">
                                <?php _e('色設定を変更すると、上記のプレビューに反映されます', 'exam-countdown-manager'); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" 
                           value="<?php _e('設定を保存', 'exam-countdown-manager'); ?>">
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // カラーピッカーの変更を監視してプレビューを更新
            $('input[type="color"]').on('change', function() {
                updatePreview();
            });
            
            function updatePreview() {
                var headerBg = $('#header_bg_color').val();
                var headerText = $('#header_text_color').val();
                var footerBg = $('#footer_bg_color').val();
                var footerText = $('#footer_text_color').val();
                
                $('#header-preview').css({
                    'background-color': headerBg,
                    'color': headerText
                });
                
                $('#footer-preview').css({
                    'background-color': footerBg,
                    'color': footerText
                });
            }
            
            // 初期プレビューを設定
            updatePreview();
        });
        </script>
        
        <style>
        .color-field {
            width: 60px;
            height: 30px;
            border: 1px solid #ddd;
            border-radius: 3px;
            cursor: pointer;
        }
        .ecm-preview-container {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .ecm-preview-content {
            margin-bottom: 15px;
        }
        .ecm-preview-note {
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        #header-preview, #footer-preview {
            margin: 10px 0;
            padding: 10px 15px;
            border-radius: 0;
            font-size: 14px;
            text-align: center;
        }
        </style>
        <?php
    }
    
    /**
     * カウントダウン設定保存（カラー設定追加版）
     */
    private function save_countdown_settings() {
        if (!wp_verify_nonce($_POST['ecm_countdown_nonce'], 'ecm_countdown_settings')) {
            wp_die(__('セキュリティチェックに失敗しました。', 'exam-countdown-manager'));
        }
        
        // カラー値のバリデーション
        $header_bg_color = sanitize_text_field($_POST['header_bg_color']);
        $header_text_color = sanitize_text_field($_POST['header_text_color']);
        $footer_bg_color = sanitize_text_field($_POST['footer_bg_color']);
        $footer_text_color = sanitize_text_field($_POST['footer_text_color']);
        
        // 16進数カラーコードの検証
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $header_bg_color)) {
            $header_bg_color = '#2c3e50';
        }
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $header_text_color)) {
            $header_text_color = '#ffffff';
        }
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $footer_bg_color)) {
            $footer_bg_color = '#34495e';
        }
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $footer_text_color)) {
            $footer_text_color = '#ffffff';
        }
        
        $options = array(
            'show_in_header' => isset($_POST['show_in_header']),
            'show_in_footer' => isset($_POST['show_in_footer']),
            'countdown_style' => sanitize_text_field($_POST['countdown_style']),
            'show_detailed_time' => isset($_POST['show_detailed_time']),
            'header_bg_color' => $header_bg_color,
            'header_text_color' => $header_text_color,
            'footer_bg_color' => $footer_bg_color,
            'footer_text_color' => $footer_text_color
        );
        
        update_option('ecm_countdown_display_options', $options);
        
        add_settings_error('ecm_messages', 'ecm_message', 
            __('設定を保存しました。', 'exam-countdown-manager'), 'success');
    }
    
    /**
     * ヘルプページ
     */
    public function help_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('使い方・ヘルプ', 'exam-countdown-manager'); ?></h1>
            
            <div class="ecm-help-content">
                <div class="card">
                    <h2><?php _e('ショートコード一覧', 'exam-countdown-manager'); ?></h2>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('ショートコード', 'exam-countdown-manager'); ?></th>
                                <th><?php _e('説明', 'exam-countdown-manager'); ?></th>
                                <th><?php _e('オプション', 'exam-countdown-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[exam_countdown]</code></td>
                                <td><?php _e('プライマリ試験のカウントダウンを表示', 'exam-countdown-manager'); ?></td>
                                <td>style, exam, color, background</td>
                            </tr>
                            <tr>
                                <td><code>[exam_list]</code></td>
                                <td><?php _e('資格試験の一覧を表示', 'exam-countdown-manager'); ?></td>
                                <td>upcoming, category, columns</td>
                            </tr>
                            <tr>
                                <td><code>[exam_info]</code></td>
                                <td><?php _e('特定の試験の詳細情報を表示', 'exam-countdown-manager'); ?></td>
                                <td>exam, format, show_countdown</td>
                            </tr>
                            <tr>
                                <td><code>[exam_progress]</code></td>
                                <td><?php _e('学習進捗を表示（将来実装予定）', 'exam-countdown-manager'); ?></td>
                                <td>user_id, exam</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="card">
                    <h2><?php _e('使用例', 'exam-countdown-manager'); ?></h2>
                    <h3><?php _e('基本的な使い方', 'exam-countdown-manager'); ?></h3>
                    <pre><code>[exam_countdown]</code></pre>
                    <p><?php _e('プライマリに設定された資格試験のカウントダウンを表示します。', 'exam-countdown-manager'); ?></p>
                    
                    <h3><?php _e('特定の試験を指定', 'exam-countdown-manager'); ?></h3>
                    <pre><code>[exam_countdown exam="gyouseishoshi"]</code></pre>
                    <p><?php _e('行政書士試験のカウントダウンを表示します。', 'exam-countdown-manager'); ?></p>
                    
                    <h3><?php _e('スタイルを変更', 'exam-countdown-manager'); ?></h3>
                    <pre><code>[exam_countdown style="simple"]</code></pre>
                    <p><?php _e('シンプルなスタイルでカウントダウンを表示します。', 'exam-countdown-manager'); ?></p>
                    
                    <h3><?php _e('カスタムカラー', 'exam-countdown-manager'); ?></h3>
                    <pre><code>[exam_countdown color="#ffffff" background="#e74c3c"]</code></pre>
                    <p><?php _e('白文字・赤背景でカウントダウンを表示します。', 'exam-countdown-manager'); ?></p>
                    
                    <h3><?php _e('ヘッダー・フッター表示', 'exam-countdown-manager'); ?></h3>
                    <p><?php _e('ヘッダーやフッターへの表示は、カウントダウン設定ページで設定できます。一列表示でコンパクトに表示され、カスタムカラーの設定も可能です。', 'exam-countdown-manager'); ?></p>
                </div>
                
                <div class="card">
                    <h2><?php _e('PHP関数', 'exam-countdown-manager'); ?></h2>
                    <p><?php _e('テーマファイルで直接使用できる関数：', 'exam-countdown-manager'); ?></p>
                    <pre><code>
// プライマリ試験を取得
$primary_exam = ecm_get_primary_exam();

// 特定の試験を取得
$exam = ecm_get_exam_by_key('gyouseishoshi');

// 残り日数を計算
$days_left = ecm_get_days_until_exam('2025-11-09');

// すべての試験を取得
$all_exams = ecm_get_all_exams();

// カスタムカラーでカウントダウン表示
echo do_shortcode('[exam_countdown style="header" color="#ffffff" background="#2c3e50"]');
</code></pre>
                </div>
                
                <div class="card">
                    <h2><?php _e('カラー設定について', 'exam-countdown-manager'); ?></h2>
                    <p><?php _e('ヘッダーやフッターに表示するカウントダウンの色は、カウントダウン設定ページで変更できます。', 'exam-countdown-manager'); ?></p>
                    <ul>
                        <li><?php _e('背景色：カウントダウン全体の背景色', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('文字色：試験名や日数の文字色', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('設定変更後は、プレビューで確認できます', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('ショートコードでも個別にカラー指定が可能です', 'exam-countdown-manager'); ?></li>
                    </ul>
                </div>
                
                <div class="card">
                    <h2><?php _e('一列表示について', 'exam-countdown-manager'); ?></h2>
                    <p><?php _e('ヘッダーやフッターでの表示は、画面幅を抑えるために一列表示に最適化されています：', 'exam-countdown-manager'); ?></p>
                    <ul>
                        <li><?php _e('試験名と日数が横並びで表示されます', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('詳細表示の場合も、日・時・分が横一列に配置されます', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('小さい画面では自動的にサイズ調整されます', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('通常のコンテンツ内では従来通りの表示が維持されます', 'exam-countdown-manager'); ?></li>
                    </ul>
                </div>
                
                <div class="card">
                    <h2><?php _e('トラブルシューティング', 'exam-countdown-manager'); ?></h2>
                    <h3><?php _e('カウントダウンが表示されない', 'exam-countdown-manager'); ?></h3>
                    <ul>
                        <li><?php _e('試験が登録されているか確認してください', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('「カウントダウンを表示する」がチェックされているか確認してください', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('ヘッダー・フッター表示の場合は、設定ページで表示設定を確認してください', 'exam-countdown-manager'); ?></li>
                    </ul>
                    
                    <h3><?php _e('色が反映されない', 'exam-countdown-manager'); ?></h3>
                    <ul>
                        <li><?php _e('ブラウザのキャッシュをクリアしてください', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('テーマのCSSが優先されている可能性があります', 'exam-countdown-manager'); ?></li>
                        <li><?php _e('カラーコードが正しい形式（#000000）か確認してください', 'exam-countdown-manager'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 日付バリデーション
     */
    private function validate_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

// 設定エラーメッセージの表示
add_action('admin_notices', function() {
    settings_errors('ecm_messages');
});