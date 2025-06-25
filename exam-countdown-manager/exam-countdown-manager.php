<?php
/**
 * Plugin Name: 資格試験カウントダウンマネージャー
 * Plugin URI: https://example.com
 * Description: 複数の資格試験のカウントダウンと学習進捗を管理できるプラグインです。行政書士、宅建、FPなど様々な資格試験に対応。
 * Version: 1.0.2
 * Author: 行政書士の道
 * Text Domain: exam-countdown-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 *
 * @package ExamCountdownManager
 */

// 直接アクセスを禁止
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの定数定義
define('ECM_PLUGIN_VERSION', '1.0.2');
define('ECM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ECM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * メインプラグインクラス
 */
class ExamCountdownManager {
    
    /**
     * プラグインインスタンス
     */
    private static $instance = null;
    
    /**
     * 初期化フラグ
     */
    private $initialized = false;
    
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
        $this->init_hooks();
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('ExamCountdownManager', 'uninstall'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'), 0);
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_action('wp_head', array($this, 'add_inline_styles'));
        add_action('wp_footer', array($this, 'debug_info'), 999);
    }
    
    /**
     * 多言語化ファイルの読み込み
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'exam-countdown-manager', 
            false, 
            dirname(ECM_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * 初期化
     */
    public function init() {
        if ($this->initialized) {
            return;
        }
        
        // 依存ファイルを読み込み
        $this->load_dependencies();
        
        // 各クラスを初期化
        if (class_exists('ECM_Exam_Settings')) {
            ECM_Exam_Settings::get_instance();
        }
        
        if (class_exists('ECM_Exam_Shortcodes')) {
            ECM_Exam_Shortcodes::get_instance();
        }
        
        if (class_exists('ECM_Exam_Widgets')) {
            ECM_Exam_Widgets::get_instance();
        }
        
        // アップグレード処理
        $this->maybe_upgrade();
        
        $this->initialized = true;
        
        // 初期化完了のアクションを発火
        do_action('ecm_initialized');
    }
    
    /**
     * 依存ファイルを読み込み
     */
    private function load_dependencies() {
        $files = array(
            'includes/exam-functions.php',
            'includes/class-exam-settings.php',
            'includes/class-exam-shortcodes.php',
            'includes/class-exam-widgets.php'
        );
        
        foreach ($files as $file) {
            $file_path = ECM_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // ファイルが見つからない場合のエラーログ
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ECM: Required file not found: ' . $file_path);
                }
            }
        }
    }
    
    /**
     * 管理画面用スタイル・スクリプト読み込み
     */
    public function admin_enqueue_scripts($hook) {
        // プラグインの管理画面でのみ読み込み
        if (strpos($hook, 'exam-countdown') === false) {
            return;
        }
        
        // CSSの読み込み
        wp_enqueue_style(
            'ecm-admin-style',
            ECM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ECM_PLUGIN_VERSION
        );
        
        // JavaScriptの読み込み
        wp_enqueue_script(
            'ecm-admin-script',
            ECM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ECM_PLUGIN_VERSION,
            true
        );
        
        // 管理画面用データをローカライズ
        wp_localize_script('ecm-admin-script', 'ecm_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecm_admin_nonce'),
            'plugin_url' => ECM_PLUGIN_URL,
            'messages' => array(
                'delete_confirm' => __('この資格試験を削除してもよろしいですか？', 'exam-countdown-manager'),
                'save_success' => __('設定を保存しました。', 'exam-countdown-manager'),
                'save_error' => __('設定の保存に失敗しました。', 'exam-countdown-manager'),
                'loading' => __('読み込み中...', 'exam-countdown-manager'),
                'error' => __('エラーが発生しました。', 'exam-countdown-manager')
            ),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }
    
    /**
     * フロントエンド用スタイル・スクリプト読み込み
     */
    public function frontend_enqueue_scripts() {
        // カウントダウン要素が存在するかチェック
        $has_countdown = $this->check_countdown_usage();
        
        // 必要な場合のみスクリプトを読み込み
        if ($has_countdown || is_admin()) {
            // CSSの読み込み
            wp_enqueue_style(
                'ecm-frontend-style',
                ECM_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                ECM_PLUGIN_VERSION
            );
            
            // JavaScriptの読み込み
            wp_enqueue_script(
                'ecm-frontend-script',
                ECM_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                ECM_PLUGIN_VERSION,
                true
            );
            
            // フロントエンド用データをローカライズ
            $this->localize_frontend_script();
        }
    }
    
    /**
     * カウントダウン使用状況をチェック
     */
    private function check_countdown_usage() {
        global $post;
        $has_countdown = false;
        
        // 投稿内容にショートコードがあるかチェック
        if ($post && (
            has_shortcode($post->post_content, 'exam_countdown') ||
            has_shortcode($post->post_content, 'exam_list') ||
            has_shortcode($post->post_content, 'exam_info')
        )) {
            $has_countdown = true;
        }
        
        // ウィジェットでカウントダウンが使用されているかチェック
        if (!$has_countdown) {
            $sidebars_widgets = get_option('sidebars_widgets', array());
            foreach ($sidebars_widgets as $sidebar => $widgets) {
                if (is_array($widgets)) {
                    foreach ($widgets as $widget) {
                        if (strpos($widget, 'ecm_countdown_widget') !== false || 
                            strpos($widget, 'ecm_exam_list_widget') !== false) {
                            $has_countdown = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        // ヘッダー・フッターでの表示設定をチェック
        if (!$has_countdown) {
            $options = get_option('ecm_countdown_display_options', array());
            if (!empty($options['show_in_header']) || !empty($options['show_in_footer'])) {
                $has_countdown = true;
            }
        }
        
        return $has_countdown;
    }
    
    /**
     * フロントエンドスクリプトのローカライズ
     */
    private function localize_frontend_script() {
        $primary_exam = ecm_get_primary_exam();
        
        $localize_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecm_frontend_nonce'),
            'plugin_url' => ECM_PLUGIN_URL,
            'primary_exam' => $primary_exam ? array(
                'key' => $primary_exam['key'],
                'name' => $primary_exam['name'],
                'date' => $primary_exam['date']
            ) : null,
            'messages' => array(
                'exam_finished' => __('試験終了', 'exam-countdown-manager'),
                'days_left' => __('あと%d日', 'exam-countdown-manager'),
                'loading_error' => __('読み込みエラーが発生しました', 'exam-countdown-manager'),
                'no_exam_data' => __('試験データが見つかりません', 'exam-countdown-manager')
            ),
            'settings' => array(
                'auto_update' => true,
                'animations_enabled' => !$this->is_reduced_motion(),
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
                'update_interval' => 60000 // 1分間隔で更新
            ),
            'display_options' => get_option('ecm_countdown_display_options', array())
        );
        
        wp_localize_script('ecm-frontend-script', 'ecmFrontend', $localize_data);
    }
    
    /**
     * インラインスタイル追加（一列表示対応）
     */
    public function add_inline_styles() {
        $options = get_option('ecm_countdown_display_options', array());
        
        $custom_css = '';
        
        // ヘッダー・フッター用の一列表示スタイル強化
        $custom_css .= '
        /* 一列表示の強制適用 */
        .ecm-countdown-header,
        .ecm-countdown-footer {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 15px !important;
            flex-wrap: nowrap !important;
            padding: 10px 20px !important;
            margin: 0 !important;
        }
        
        .ecm-countdown-header .ecm-exam-name,
        .ecm-countdown-footer .ecm-exam-name {
            margin: 0 !important;
            margin-right: 10px !important;
            white-space: nowrap !important;
            flex-shrink: 0 !important;
        }
        
        .ecm-countdown-header .ecm-countdown-detailed,
        .ecm-countdown-footer .ecm-countdown-detailed,
        .ecm-countdown-header .ecm-countdown-default,
        .ecm-countdown-footer .ecm-countdown-default,
        .ecm-countdown-header .ecm-countdown-simple,
        .ecm-countdown-footer .ecm-countdown-simple {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 8px !important;
            flex-wrap: nowrap !important;
        }
        
        .ecm-countdown-header .ecm-time-unit,
        .ecm-countdown-footer .ecm-time-unit {
            min-width: 50px !important;
            padding: 6px 8px !important;
            margin: 0 2px !important;
            display: inline-block !important;
            text-align: center !important;
        }
        
        .ecm-countdown-header .ecm-time-unit .ecm-number,
        .ecm-countdown-footer .ecm-time-unit .ecm-number {
            font-size: 1.2em !important;
            display: inline !important;
        }
        
        .ecm-countdown-header .ecm-time-unit .ecm-label,
        .ecm-countdown-footer .ecm-time-unit .ecm-label {
            font-size: 0.8em !important;
            margin-left: 2px !important;
            display: inline !important;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .ecm-countdown-header,
            .ecm-countdown-footer {
                flex-direction: column !important;
                gap: 8px !important;
                padding: 8px 15px !important;
            }
            
            .ecm-countdown-header .ecm-exam-name,
            .ecm-countdown-footer .ecm-exam-name {
                margin-right: 0 !important;
                margin-bottom: 5px !important;
            }
        }
        
        @media (max-width: 480px) {
            .ecm-countdown-header .ecm-time-unit,
            .ecm-countdown-footer .ecm-time-unit {
                min-width: 40px !important;
                padding: 4px 6px !important;
            }
            
            .ecm-countdown-header .ecm-time-unit .ecm-number,
            .ecm-countdown-footer .ecm-time-unit .ecm-number {
                font-size: 1em !important;
            }
        }
        ';
        
        // カスタムカラーの適用
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
                $custom_css .= 'background: ' . esc_attr($options['header_bg_color']) . ' !important; ';
            }
            if (!empty($options['header_text_color'])) {
                $custom_css .= 'color: ' . esc_attr($options['header_text_color']) . ' !important; ';
            }
            $custom_css .= '}';
            
            $custom_css .= '.ecm-countdown-header .ecm-time-unit { ';
            $custom_css .= 'background: rgba(255, 255, 255, 0.15) !important; ';
            $custom_css .= 'border: 1px solid rgba(255, 255, 255, 0.3) !important; ';
            $custom_css .= '}';
        }
        
        if (!empty($options['footer_bg_color']) || !empty($options['footer_text_color'])) {
            $custom_css .= '.ecm-countdown-footer { ';
            if (!empty($options['footer_bg_color'])) {
                $custom_css .= 'background: ' . esc_attr($options['footer_bg_color']) . ' !important; ';
            }
            if (!empty($options['footer_text_color'])) {
                $custom_css .= 'color: ' . esc_attr($options['footer_text_color']) . ' !important; ';
            }
            $custom_css .= '}';
            
            $custom_css .= '.ecm-countdown-footer .ecm-time-unit { ';
            $custom_css .= 'background: rgba(255, 255, 255, 0.15) !important; ';
            $custom_css .= 'border: 1px solid rgba(255, 255, 255, 0.3) !important; ';
            $custom_css .= '}';
        }
        
        if (!empty($custom_css)) {
            echo '<style type="text/css" id="ecm-inline-styles">' . $custom_css . '</style>';
        }
    }
    
    /**
     * アニメーション軽減設定をチェック
     */
    private function is_reduced_motion() {
        // サーバーサイドでは判定できないため、JavaScriptで処理
        return false;
    }
    
    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // 必要な権限をチェック
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // デフォルトの資格試験データを設定
        $this->setup_default_data();
        
        // プラグインのバージョンを保存
        update_option('ecm_plugin_version', ECM_PLUGIN_VERSION);
        
        // 定期タスクを設定
        $this->schedule_cron_jobs();
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
        
        // 有効化フックを発火
        do_action('ecm_plugin_activation');
    }
    
    /**
     * デフォルトデータのセットアップ
     */
    private function setup_default_data() {
        // 既存データがある場合は何もしない
        if (get_option('ecm_exam_settings_data')) {
            return;
        }
        
        $current_year = date('Y');
        $next_year = $current_year + 1;
        
        $default_exams = array(
            'gyouseishoshi' => array(
                'name' => '行政書士試験',
                'date' => $next_year . '-11-09', // 毎年11月第2日曜日（概算）
                'description' => '総務省が実施する国家資格試験。法令等、行政書士の業務に関し必要な法令等、一般知識等が出題されます。',
                'display_countdown' => true,
                'primary' => true,
                'category' => 'law',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );
        
        update_option('ecm_exam_settings_data', $default_exams);
        
        // デフォルトの表示設定
        $default_display_options = array(
            'show_in_header' => false,
            'show_in_footer' => false,
            'countdown_style' => 'default',
            'show_detailed_time' => false,
            'hide_past_exams' => false,
            'auto_cleanup_expired' => false,
            'header_bg_color' => '#2c3e50',
            'header_text_color' => '#ffffff',
            'footer_bg_color' => '#34495e',
            'footer_text_color' => '#ffffff'
        );
        
        update_option('ecm_countdown_display_options', $default_display_options);
    }
    
    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        // 定期タスクをクリア
        $this->clear_cron_jobs();
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
        
        // 一時的なデータをクリーンアップ
        delete_transient('ecm_stats_cache');
        
        // 無効化フックを発火
        do_action('ecm_plugin_deactivation');
    }
    
    /**
     * プラグイン削除時の処理
     */
    public static function uninstall() {
        // ユーザーがデータ削除を許可している場合のみ実行
        $delete_data = get_option('ecm_delete_data_on_uninstall', false);
        
        if ($delete_data) {
            // オプションデータを削除
            $options_to_delete = array(
                'ecm_exam_settings_data',
                'ecm_countdown_display_options',
                'ecm_plugin_version',
                'ecm_delete_data_on_uninstall',
                'ecm_auto_cleanup_expired'
            );
            
            foreach ($options_to_delete as $option) {
                delete_option($option);
            }
            
            // トランジェントを削除
            delete_transient('ecm_stats_cache');
            
            // 定期タスクをクリア
            wp_clear_scheduled_hook('ecm_daily_cleanup');
        }
    }
    
    /**
     * 定期タスクのスケジュール
     */
    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('ecm_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ecm_daily_cleanup');
        }
    }
    
    /**
     * 定期タスクのクリア
     */
    private function clear_cron_jobs() {
        wp_clear_scheduled_hook('ecm_daily_cleanup');
    }
    
    /**
     * アップグレード処理
     */
    private function maybe_upgrade() {
        $current_version = get_option('ecm_plugin_version', '1.0.0');
        
        if (version_compare($current_version, ECM_PLUGIN_VERSION, '<')) {
            $this->upgrade($current_version);
            update_option('ecm_plugin_version', ECM_PLUGIN_VERSION);
        }
    }
    
    /**
     * アップグレード実行
     */
    private function upgrade($from_version) {
        // 1.0.0 → 1.0.1 のアップグレード処理
        if (version_compare($from_version, '1.0.1', '<')) {
            // 新しいオプションのデフォルト値を設定
            $display_options = get_option('ecm_countdown_display_options', array());
            
            if (!isset($display_options['auto_cleanup_expired'])) {
                $display_options['auto_cleanup_expired'] = false;
                update_option('ecm_countdown_display_options', $display_options);
            }
        }
        
        // 1.0.1 → 1.0.2 のアップグレード処理（カラー設定追加）
        if (version_compare($from_version, '1.0.2', '<')) {
            $display_options = get_option('ecm_countdown_display_options', array());
            
            // カラー設定のデフォルト値を追加
            if (!isset($display_options['header_bg_color'])) {
                $display_options['header_bg_color'] = '#2c3e50';
            }
            if (!isset($display_options['header_text_color'])) {
                $display_options['header_text_color'] = '#ffffff';
            }
            if (!isset($display_options['footer_bg_color'])) {
                $display_options['footer_bg_color'] = '#34495e';
            }
            if (!isset($display_options['footer_text_color'])) {
                $display_options['footer_text_color'] = '#ffffff';
            }
            
            update_option('ecm_countdown_display_options', $display_options);
        }
    }
    
    /**
     * デバッグ情報の表示
     */
    public function debug_info() {
        if (!defined('WP_DEBUG') || !WP_DEBUG || !current_user_can('manage_options')) {
            return;
        }
        
        $debug_info = ecm_get_debug_info();
        ?>
        <script>
        console.group('ECM Debug Info');
        console.log('Plugin Version:', '<?php echo esc_js($debug_info['plugin_version']); ?>');
        console.log('Total Exams:', <?php echo intval($debug_info['total_exams']); ?>);
        console.log('Primary Exam:', '<?php echo esc_js($debug_info['primary_exam']); ?>');
        console.log('Active Exams:', <?php echo intval($debug_info['active_exams']); ?>);
        console.log('WordPress Version:', '<?php echo esc_js($debug_info['wp_version']); ?>');
        console.log('PHP Version:', '<?php echo esc_js($debug_info['php_version']); ?>');
        console.groupEnd();
        </script>
        <?php
    }
    
    /**
     * プラグインが初期化されているかチェック
     */
    public function is_initialized() {
        return $this->initialized;
    }
    
    /**
     * プラグインの健全性チェック
     */
    public function health_check() {
        $issues = array();
        
        // 必要なファイルの存在チェック
        $required_files = array(
            'assets/css/frontend.css',
            'assets/js/frontend.js',
            'includes/exam-functions.php'
        );
        
        foreach ($required_files as $file) {
            if (!file_exists(ECM_PLUGIN_PATH . $file)) {
                $issues[] = sprintf(__('必要なファイルが見つかりません: %s', 'exam-countdown-manager'), $file);
            }
        }
        
        // データベースの整合性チェック
        $exams = get_option('ecm_exam_settings_data', array());
        if (empty($exams)) {
            $issues[] = __('資格試験データが登録されていません。', 'exam-countdown-manager');
        }
        
        // プライマリ試験の存在チェック
        $has_primary = false;
        foreach ($exams as $exam) {
            if (isset($exam['primary']) && $exam['primary']) {
                $has_primary = true;
                break;
            }
        }
        
        if (!empty($exams) && !$has_primary) {
            $issues[] = __('プライマリ試験が設定されていません。', 'exam-countdown-manager');
        }
        
        return array(
            'healthy' => empty($issues),
            'issues' => $issues
        );
    }
}

// プラグインを初期化
add_action('plugins_loaded', function() {
    ExamCountdownManager::get_instance();
}, 10);

/**
 * プラグインの主要な機能関数
 */

/**
 * プラグインインスタンスを取得
 */
function ecm_get_instance() {
    return ExamCountdownManager::get_instance();
}

/**
 * プライマリに設定された資格試験を取得
 */
function ecm_get_primary_exam() {
    $exams = get_option('ecm_exam_settings_data', array());
    
    foreach ($exams as $key => $exam) {
        if (isset($exam['primary']) && $exam['primary']) {
            return array_merge(array('key' => $key), $exam);
        }
    }
    
    // プライマリがない場合、最初の要素を返す
    if (!empty($exams)) {
        $first_key = array_key_first($exams);
        return array_merge(array('key' => $first_key), $exams[$first_key]);
    }
    
    return null;
}

/**
 * 資格キーで資格試験データを取得
 */
function ecm_get_exam_by_key($key) {
    $exams = get_option('ecm_exam_settings_data', array());
    
    if (isset($exams[$key])) {
        return array_merge(array('key' => $key), $exams[$key]);
    }
    
    return null;
}

/**
 * すべての資格試験データを取得
 */
function ecm_get_all_exams($upcoming_only = false) {
    $exams = get_option('ecm_exam_settings_data', array());
    
    if ($upcoming_only) {
        $today = current_time('timestamp');
        $filtered_exams = array();
        
        foreach ($exams as $key => $exam) {
            $exam_date = strtotime($exam['date']);
            if ($exam_date >= $today) {
                $filtered_exams[$key] = $exam;
            }
        }
        
        return $filtered_exams;
    }
    
    return $exams;
}

/**
 * 試験日までの残り日数を計算
 */
function ecm_get_days_until_exam($date) {
    $exam_date = strtotime($date);
    $today = current_time('timestamp');
    $days_left = floor(($exam_date - $today) / (60 * 60 * 24));
    
    return $days_left;
}

/**
 * 残り時間を詳細形式で取得（日、時間、分）
 */
function ecm_get_time_until_exam($date) {
    $exam_date = strtotime($date);
    $today = current_time('timestamp');
    $diff = $exam_date - $today;
    
    if ($diff <= 0) {
        return array(
            'days' => 0,
            'hours' => 0,
            'minutes' => 0,
            'total_seconds' => 0,
            'is_past' => true
        );
    }
    
    $days = floor($diff / (60 * 60 * 24));
    $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
    $minutes = floor(($diff % (60 * 60)) / 60);
    
    return array(
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes,
        'total_seconds' => $diff,
        'is_past' => false
    );
}

/**
 * 資格試験のカテゴリーリストを取得
 */
function ecm_get_exam_categories() {
    return array(
        'law' => __('法律系', 'exam-countdown-manager'),
        'finance' => __('金融・財務系', 'exam-countdown-manager'),
        'it' => __('IT・情報系', 'exam-countdown-manager'),
        'business' => __('ビジネス・経営系', 'exam-countdown-manager'),
        'medical' => __('医療・福祉系', 'exam-countdown-manager'),
        'other' => __('その他', 'exam-countdown-manager')
    );
}