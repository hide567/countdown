<?php
/**
 * Plugin Name: 資格試験カウントダウンマネージャー
 * Plugin URI: https://example.com
 * Description: 複数の資格試験のカウントダウンと学習進捗を管理できるプラグインです。行政書士、宅建、FPなど様々な資格試験に対応。
 * Version: 1.0.0
 * Author: 行政書士の道
 * Text Domain: exam-countdown-manager
 * Domain Path: /languages
 *
 * @package ExamCountdownManager
 */

// 直接アクセスを禁止
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの定数定義
define('ECM_PLUGIN_VERSION', '1.0.0');
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
        $this->load_dependencies();
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('ExamCountdownManager', 'uninstall'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
    }
    
    /**
     * 依存ファイルを読み込み
     */
    private function load_dependencies() {
        require_once ECM_PLUGIN_PATH . 'includes/class-exam-settings.php';
        require_once ECM_PLUGIN_PATH . 'includes/class-exam-shortcodes.php';
        require_once ECM_PLUGIN_PATH . 'includes/class-exam-widgets.php';
        require_once ECM_PLUGIN_PATH . 'includes/exam-functions.php';
    }
    
    /**
     * 初期化
     */
    public function init() {
        // 多言語化の準備
        load_plugin_textdomain('exam-countdown-manager', false, dirname(ECM_PLUGIN_BASENAME) . '/languages');
        
        // 各クラスを初期化
        ECM_Exam_Settings::get_instance();
        ECM_Exam_Shortcodes::get_instance();
        ECM_Exam_Widgets::get_instance();
    }
    
    /**
     * 管理画面用スタイル・スクリプト読み込み
     */
    public function admin_enqueue_scripts($hook) {
        // プラグインの管理画面でのみ読み込み
        if (strpos($hook, 'exam-countdown') !== false) {
            wp_enqueue_style(
                'ecm-admin-style',
                ECM_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                ECM_PLUGIN_VERSION
            );
            
            wp_enqueue_script(
                'ecm-admin-script',
                ECM_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                ECM_PLUGIN_VERSION,
                true
            );
            
            // 日本語化されたデータを渡す
            wp_localize_script('ecm-admin-script', 'ecm_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ecm_admin_nonce'),
                'messages' => array(
                    'delete_confirm' => __('この資格試験を削除してもよろしいですか？', 'exam-countdown-manager'),
                    'save_success' => __('設定を保存しました。', 'exam-countdown-manager'),
                )
            ));
        }
    }
    
    /**
     * フロントエンド用スタイル・スクリプト読み込み
     */
    public function frontend_enqueue_scripts() {
        wp_enqueue_style(
            'ecm-frontend-style',
            ECM_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ECM_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'ecm-frontend-script',
            ECM_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            ECM_PLUGIN_VERSION,
            true
        );
        
        // フロントエンド用データ
        wp_localize_script('ecm-frontend-script', 'ecm_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecm_frontend_nonce'),
        ));
    }
    
    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // デフォルトの資格試験データを設定
        $default_exams = array(
            'gyouseishoshi' => array(
                'name' => '行政書士試験',
                'date' => date('Y') . '-11-09', // 毎年11月第2日曜日（概算）
                'description' => '総務省が実施する国家資格試験',
                'display_countdown' => true,
                'primary' => true,
                'category' => 'law',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );
        
        // 既存データがない場合のみデフォルトデータを設定
        if (!get_option('ecm_exam_settings_data')) {
            update_option('ecm_exam_settings_data', $default_exams);
        }
        
        // プラグインのバージョンを保存
        update_option('ecm_plugin_version', ECM_PLUGIN_VERSION);
        
        // 必要なデータベーステーブルを作成（将来の拡張用）
        $this->create_database_tables();
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
    }
    
    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        // リライトルールをフラッシュ
        flush_rewrite_rules();
        
        // 一時的なデータをクリーンアップ
        wp_clear_scheduled_hook('ecm_daily_cleanup');
    }
    
    /**
     * プラグイン削除時の処理
     */
    public static function uninstall() {
        // ユーザーがデータ削除を許可している場合のみ実行
        $delete_data = get_option('ecm_delete_data_on_uninstall', false);
        
        if ($delete_data) {
            // オプションデータを削除
            delete_option('ecm_exam_settings_data');
            delete_option('ecm_plugin_version');
            delete_option('ecm_delete_data_on_uninstall');
            
            // カスタムテーブルを削除（将来の拡張用）
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ecm_study_logs");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ecm_user_progress");
        }
    }
    
    /**
     * データベーステーブル作成（将来の拡張用）
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 学習ログテーブル
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
        
        // ユーザー進捗テーブル
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
add_action('plugins_loaded', 'ecm_get_instance');

/**
 * デバッグ用: プラグインの状態確認
 */
function ecm_debug_info() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $exams = get_option('ecm_exam_settings_data', array());
    $version = get_option('ecm_plugin_version');
    
    echo '<div style="background: #f1f1f1; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">';
    echo '<h4>ECM Debug Info</h4>';
    echo '<p><strong>Plugin Version:</strong> ' . esc_html($version) . '</p>';
    echo '<p><strong>Registered Exams:</strong> ' . count($exams) . '</p>';
    echo '<p><strong>Primary Exam:</strong> ';
    
    $primary = ecm_get_primary_exam();
    if ($primary) {
        echo esc_html($primary['name']) . ' (' . esc_html($primary['key']) . ')';
    } else {
        echo 'None';
    }
    echo '</p>';
    echo '</div>';
}

// 管理画面でのデバッグ情報表示（開発時のみ）
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_notices', 'ecm_debug_info');
}