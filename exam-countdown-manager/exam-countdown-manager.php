<?php
/**
 * Plugin Name: 資格試験カウントダウンマネージャー
 * Plugin URI: https://example.com
 * Description: 複数の資格試験のカウントダウンと学習進捗を管理できるプラグインです。行政書士、宅建、FPなど様々な資格試験に対応。
 * Version: 1.0.1
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
define('ECM_PLUGIN_VERSION', '1.0.1');
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
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
            )
        );
        
        wp_localize_script('ecm-frontend-script', 'ecmFrontend', $localize_data);
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
        
        // 必要なデータベーステーブルを作成
        $this->create_database_tables();
        
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
            'auto_cleanup_expired' => false
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
            
            // カスタムテーブルを削除
            global $wpdb;
            $tables_to_drop = array(
                $wpdb->prefix . 'ecm_study_logs',
                $wpdb->prefix . 'ecm_user_progress'
            );
            
            foreach ($tables_to_drop as $table) {
                $wpdb->query("DROP TABLE IF EXISTS $table");
            }
            
            // 定期タスクをクリア
            wp_clear_scheduled_hook('ecm_daily_cleanup');
        }
    }
    
    /**
     * データベーステーブル作成
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 学習ログテーブル（将来の拡張用）
        $table_study_logs = $wpdb->prefix . 'ecm_study_logs';
        $sql_study_logs = "CREATE TABLE $table_study_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            exam_key varchar(50) NOT NULL,
            subject varchar(100) NOT NULL,
            study_time int(11) DEFAULT 0,
            study_date date NOT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY exam_key (exam_key),
            KEY study_date (study_date)
        ) $charset_collate;";
        
        // ユーザー進捗テーブル（将来の拡張用）
        $table_user_progress = $wpdb->prefix . 'ecm_user_progress';
        $sql_user_progress = "CREATE TABLE $table_user_progress (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            exam_key varchar(50) NOT NULL,
            subject varchar(100) NOT NULL,
            progress_rate decimal(5,2) DEFAULT 0.00,
            last_study_date date,
            total_study_time int(11) DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_exam_subject (user_id, exam_key, subject),
            KEY user_id (user_id),
            KEY exam_key (exam_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_study_logs);
        dbDelta($sql_user_progress);
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
        
        // 将来のバージョンアップ時にここに処理を追加
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

// プラグインを初期化
add_action('plugins_loaded', 'ecm_get_instance', 10);

/**
 * デバッグ用: プラグインの状態確認（開発時のみ）
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    function ecm_debug_admin_notice() {
        if (!current_user_can('manage_options') || !isset($_GET['page']) || strpos($_GET['page'], 'exam-countdown') === false) {
            return;
        }
        
        $debug_info = ecm_get_debug_info();
        $health_check = ecm_get_instance()->health_check();
        
        echo '<div class="notice notice-info ecm-debug-info">';
        echo '<h4>ECM Debug Info</h4>';
        echo '<p><strong>Plugin Version:</strong> ' . esc_html($debug_info['plugin_version']) . '</p>';
        echo '<p><strong>Registered Exams:</strong> ' . esc_html($debug_info['total_exams']) . '</p>';
        echo '<p><strong>Primary Exam:</strong> ' . esc_html($debug_info['primary_exam']) . '</p>';
        
        if (!$health_check['healthy']) {
            echo '<p><strong style="color: #dc3232;">Health Issues:</strong></p>';
            echo '<ul style="margin-left: 20px;">';
            foreach ($health_check['issues'] as $issue) {
                echo '<li style="color: #dc3232;">' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color: #46b450;"><strong>Status:</strong> Healthy</p>';
        }
        
        echo '</div>';
    }
    
    add_action('admin_notices', 'ecm_debug_admin_notice');
}

/**
 * REST API エンドポイントの登録（将来の拡張用）
 */
function ecm_register_rest_routes() {
    register_rest_route('ecm/v1', '/exams', array(
        'methods' => 'GET',
        'callback' => 'ecm_rest_get_exams',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('ecm/v1', '/countdown/(?P<exam_key>[a-zA-Z0-9_-]+)', array(
        'methods' => 'GET',
        'callback' => 'ecm_rest_get_countdown',
        'permission_callback' => '__return_true'
    ));
}

add_action('rest_api_init', 'ecm_register_rest_routes');

/**
 * REST API: 試験一覧取得
 */
function ecm_rest_get_exams($request) {
    $upcoming_only = $request->get_param('upcoming') === 'true';
    $exams = ecm_get_all_exams($upcoming_only);
    
    $response_data = array();
    foreach ($exams as $key => $exam) {
        $response_data[] = array(
            'key' => $key,
            'name' => $exam['name'],
            'date' => $exam['date'],
            'days_left' => ecm_get_days_until_exam($exam['date']),
            'category' => $exam['category'] ?? 'other'
        );
    }
    
    return rest_ensure_response($response_data);
}

/**
 * REST API: カウントダウン情報取得
 */
function ecm_rest_get_countdown($request) {
    $exam_key = $request->get_param('exam_key');
    $exam = ecm_get_exam_by_key($exam_key);
    
    if (!$exam) {
        return new WP_Error('exam_not_found', 'Exam not found', array('status' => 404));
    }
    
    $time_data = ecm_get_time_until_exam($exam['date']);
    
    return rest_ensure_response(array(
        'exam_key' => $exam_key,
        'exam_name' => $exam['name'],
        'exam_date' => $exam['date'],
        'days_left' => $time_data['days'],
        'hours_left' => $time_data['hours'],
        'minutes_left' => $time_data['minutes'],
        'is_past' => $time_data['is_past']
    ));
}