<?php
/**
 * 資格試験関連の補助関数（一列表示対応修正版）
 *
 * @package ExamCountdownManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 特定の資格試験の情報を配列で取得
 *
 * @param string $exam_key 資格試験のキー
 * @return array|null 試験データまたはnull
 */
function ecm_get_exam_info($exam_key) {
    return ecm_get_exam_by_key($exam_key);
}

/**
 * 現在登録されているすべての資格試験のキー一覧を取得
 *
 * @return array 試験キーの配列
 */
function ecm_get_exam_keys() {
    $exams = get_option('ecm_exam_settings_data', array());
    return array_keys($exams);
}

/**
 * カテゴリー別に資格試験を取得
 *
 * @param string $category カテゴリーキー
 * @return array カテゴリーに属する試験の配列
 */
function ecm_get_exams_by_category($category) {
    $exams = ecm_get_all_exams();
    $filtered = array();
    
    foreach ($exams as $key => $exam) {
        if (isset($exam['category']) && $exam['category'] === $category) {
            $filtered[$key] = $exam;
        }
    }
    
    return $filtered;
}

/**
 * 近日開催の資格試験を取得（指定日数以内）
 *
 * @param int $days 指定日数（デフォルト: 30日）
 * @return array 近日開催の試験配列
 */
function ecm_get_upcoming_exams($days = 30) {
    $exams = ecm_get_all_exams();
    $upcoming = array();
    $today = current_time('timestamp');
    $limit_date = $today + ($days * 24 * 60 * 60);
    
    foreach ($exams as $key => $exam) {
        $exam_date = strtotime($exam['date']);
        if ($exam_date >= $today && $exam_date <= $limit_date) {
            $upcoming[$key] = $exam;
        }
    }
    
    // 日付順でソート
    uasort($upcoming, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    return $upcoming;
}

/**
 * 資格試験の難易度情報を管理（将来の拡張用）
 *
 * @param string $exam_key 資格試験のキー
 * @return string 難易度レベル
 */
function ecm_get_exam_difficulty($exam_key) {
    $difficulty_map = array(
        'gyouseishoshi' => 'medium',
        'takken' => 'medium',
        'fp2' => 'easy',
        'fp1' => 'medium',
        'bengoshi' => 'hard',
        'zeirishi' => 'hard',
        'kihon_joho' => 'medium'
    );
    
    return isset($difficulty_map[$exam_key]) ? $difficulty_map[$exam_key] : 'medium';
}

/**
 * 試験の典型的な学習期間を取得（将来の拡張用）
 *
 * @param string $exam_key 資格試験のキー
 * @return int 推奨学習期間（月数）
 */
function ecm_get_recommended_study_period($exam_key) {
    $study_periods = array(
        'gyouseishoshi' => 8,  // 8ヶ月
        'takken' => 4,         // 4ヶ月
        'fp2' => 2,            // 2ヶ月
        'fp1' => 6,            // 6ヶ月
        'bengoshi' => 24,      // 2年
        'zeirishi' => 18,      // 1年半
        'kihon_joho' => 3      // 3ヶ月
    );
    
    return isset($study_periods[$exam_key]) ? $study_periods[$exam_key] : 6;
}

/**
 * 学習開始推奨日を算出
 *
 * @param string $exam_key 資格試験のキー
 * @return string 学習開始推奨日（Y-m-d形式）
 */
function ecm_get_recommended_start_date($exam_key) {
    $exam = ecm_get_exam_by_key($exam_key);
    if (!$exam) {
        return null;
    }
    
    $exam_date = strtotime($exam['date']);
    $study_months = ecm_get_recommended_study_period($exam_key);
    $start_date = strtotime("-{$study_months} months", $exam_date);
    
    return date('Y-m-d', $start_date);
}

/**
 * 試験日までの学習可能日数を計算（休日を除く）
 *
 * @param string $exam_key 資格試験のキー
 * @param bool $exclude_weekends 週末を除くかどうか
 * @return int 学習可能日数
 */
function ecm_get_study_days_left($exam_key, $exclude_weekends = false) {
    $exam = ecm_get_exam_by_key($exam_key);
    if (!$exam) {
        return 0;
    }
    
    $today = current_time('timestamp');
    $exam_date = strtotime($exam['date']);
    
    if ($exam_date <= $today) {
        return 0;
    }
    
    if (!$exclude_weekends) {
        return floor(($exam_date - $today) / (60 * 60 * 24));
    }
    
    // 週末を除いた計算
    $study_days = 0;
    $current_date = $today;
    
    while ($current_date < $exam_date) {
        $day_of_week = date('w', $current_date);
        if ($day_of_week != 0 && $day_of_week != 6) { // 日曜(0)と土曜(6)以外
            $study_days++;
        }
        $current_date += (60 * 60 * 24);
    }
    
    return $study_days;
}

/**
 * 資格試験のURLを生成（詳細ページ等への遷移用）
 *
 * @param string $exam_key 資格試験のキー
 * @param string $action アクション（detail, edit等）
 * @return string URL
 */
function ecm_get_exam_url($exam_key, $action = 'detail') {
    $base_url = admin_url('admin.php?page=exam-countdown-settings');
    
    switch ($action) {
        case 'edit':
            return $base_url . '&tab=edit&edit=' . urlencode($exam_key);
        case 'delete':
            return wp_nonce_url(
                $base_url . '&action=delete&exam=' . urlencode($exam_key),
                'delete_exam_' . $exam_key
            );
        default:
            return $base_url . '&tab=list#exam-' . urlencode($exam_key);
    }
}

/**
 * 試験の実施回数情報（年間）
 *
 * @param string $exam_key 資格試験のキー
 * @return int 年間実施回数
 */
function ecm_get_exam_frequency($exam_key) {
    $frequency_map = array(
        'gyouseishoshi' => 1,  // 年1回
        'takken' => 1,         // 年1回
        'fp2' => 3,            // 年3回
        'fp1' => 3,            // 年3回
        'bengoshi' => 1,       // 年1回
        'zeirishi' => 1,       // 年1回
        'kihon_joho' => 2      // 年2回
    );
    
    return isset($frequency_map[$exam_key]) ? $frequency_map[$exam_key] : 1;
}

/**
 * 合格率の情報（概算値）
 *
 * @param string $exam_key 資格試験のキー
 * @return float 合格率（0.0-1.0）
 */
function ecm_get_exam_pass_rate($exam_key) {
    $pass_rates = array(
        'gyouseishoshi' => 0.11,  // 約11%
        'fp2' => 0.40,            // 約40%
        'fp1' => 0.10,            // 約10%
        'bengoshi' => 0.03,       // 約3%
        'zeirishi' => 0.05,       // 約5%
        'kihon_joho' => 0.25      // 約25%
    );
    
    return isset($pass_rates[$exam_key]) ? $pass_rates[$exam_key] : 0.15;
}

/**
 * 資格試験の詳細情報を配列で取得
 *
 * @param string $exam_key 資格試験のキー
 * @return array 詳細情報の配列
 */
function ecm_get_exam_details($exam_key) {
    $exam = ecm_get_exam_by_key($exam_key);
    if (!$exam) {
        return null;
    }
    
    return array(
        'basic_info' => $exam,
        'days_left' => ecm_get_days_until_exam($exam['date']),
        'study_days_left' => ecm_get_study_days_left($exam_key),
        'study_days_left_weekdays' => ecm_get_study_days_left($exam_key, true),
        'difficulty' => ecm_get_exam_difficulty($exam_key),
        'recommended_study_period' => ecm_get_recommended_study_period($exam_key),
        'recommended_start_date' => ecm_get_recommended_start_date($exam_key),
        'frequency_per_year' => ecm_get_exam_frequency($exam_key),
        'estimated_pass_rate' => ecm_get_exam_pass_rate($exam_key),
        'time_until_exam' => ecm_get_time_until_exam($exam['date'])
    );
}

/**
 * 学習進捗の計算（将来の拡張用）
 *
 * @param int $user_id ユーザーID
 * @param string $exam_key 資格試験のキー
 * @return array 進捗データ
 */
function ecm_get_user_study_progress($user_id, $exam_key) {
    // 現在は仮データを返す（将来のデータベース拡張で実装）
    return array(
        'total_study_hours' => 0,
        'weekly_average' => 0,
        'subjects_completed' => 0,
        'overall_progress' => 0.0,
        'last_study_date' => null,
        'streak_days' => 0
    );
}

/**
 * 資格試験データの検証
 *
 * @param array $exam_data 試験データ
 * @return array エラーメッセージの配列（エラーがない場合は空配列）
 */
function ecm_validate_exam_data($exam_data) {
    $errors = array();
    
    // 必須フィールドのチェック
    $required_fields = array('name', 'date');
    foreach ($required_fields as $field) {
        if (empty($exam_data[$field])) {
            $errors[] = sprintf(__('%s は必須です。', 'exam-countdown-manager'), $field);
        }
    }
    
    // 日付形式のチェック
    if (!empty($exam_data['date'])) {
        $date = DateTime::createFromFormat('Y-m-d', $exam_data['date']);
        if (!$date || $date->format('Y-m-d') !== $exam_data['date']) {
            $errors[] = __('試験日の形式が正しくありません。', 'exam-countdown-manager');
        }
    }
    
    // カテゴリーのチェック
    if (!empty($exam_data['category'])) {
        $valid_categories = array_keys(ecm_get_exam_categories());
        if (!in_array($exam_data['category'], $valid_categories)) {
            $errors[] = __('無効なカテゴリーが指定されています。', 'exam-countdown-manager');
        }
    }
    
    return $errors;
}

/**
 * 資格試験データのサニタイズ
 *
 * @param array $exam_data 生の試験データ
 * @return array サニタイズされた試験データ
 */
function ecm_sanitize_exam_data($exam_data) {
    $sanitized = array();
    
    $sanitized['name'] = sanitize_text_field($exam_data['name'] ?? '');
    $sanitized['date'] = sanitize_text_field($exam_data['date'] ?? '');
    $sanitized['description'] = sanitize_textarea_field($exam_data['description'] ?? '');
    $sanitized['category'] = sanitize_text_field($exam_data['category'] ?? 'other');
    $sanitized['display_countdown'] = !empty($exam_data['display_countdown']);
    $sanitized['primary'] = !empty($exam_data['primary']);
    
    return $sanitized;
}

/**
 * カウントダウン表示の条件チェック
 *
 * @param string $exam_key 資格試験のキー
 * @return bool 表示するかどうか
 */
function ecm_should_display_countdown($exam_key) {
    $exam = ecm_get_exam_by_key($exam_key);
    
    if (!$exam) {
        return false;
    }
    
    // 表示設定がオフの場合
    if (!isset($exam['display_countdown']) || !$exam['display_countdown']) {
        return false;
    }
    
    // 試験が終了している場合の設定をチェック
    $days_left = ecm_get_days_until_exam($exam['date']);
    if ($days_left < 0) {
        $options = get_option('ecm_countdown_display_options', array());
        return !isset($options['hide_past_exams']) || !$options['hide_past_exams'];
    }
    
    return true;
}

/**
 * 管理画面用の通知メッセージを追加
 *
 * @param string $message メッセージ内容
 * @param string $type メッセージタイプ（success, error, warning, info）
 */
function ecm_add_admin_notice($message, $type = 'info') {
    add_action('admin_notices', function() use ($message, $type) {
        $class = 'notice notice-' . $type;
        if ($type === 'success') {
            $class .= ' is-dismissible';
        }
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    });
}

/**
 * 日付の形式変換（ローカライズ対応）
 *
 * @param string $date 日付文字列（Y-m-d形式）
 * @param string $format 出力形式
 * @return string フォーマットされた日付
 */
function ecm_format_date($date, $format = null) {
    if (empty($date)) {
        return '';
    }
    
    // 日付の妥当性をチェック
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    
    if ($format === null) {
        $format = get_option('date_format');
    }
    
    return date_i18n($format, $timestamp);
}

/**
 * プラグインのデバッグ情報を取得
 *
 * @return array デバッグ情報
 */
function ecm_get_debug_info() {
    $exams = get_option('ecm_exam_settings_data', array());
    $primary_exam = ecm_get_primary_exam();
    
    return array(
        'plugin_version' => ECM_PLUGIN_VERSION,
        'total_exams' => count($exams),
        'primary_exam' => $primary_exam ? $primary_exam['key'] : 'none',
        'active_exams' => count(ecm_get_all_exams(true)),
        'plugin_path' => ECM_PLUGIN_PATH,
        'plugin_url' => ECM_PLUGIN_URL,
        'wp_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'mysql_version' => $GLOBALS['wpdb']->db_version()
    );
}

/**
 * カウントダウン用のJavaScriptデータを生成
 *
 * @param string $exam_key 資格試験のキー
 * @return array JavaScriptに渡すデータ
 */
function ecm_get_countdown_js_data($exam_key = '') {
    if (empty($exam_key)) {
        $exam = ecm_get_primary_exam();
    } else {
        $exam = ecm_get_exam_by_key($exam_key);
    }
    
    if (!$exam) {
        return array();
    }
    
    $time_data = ecm_get_time_until_exam($exam['date']);
    
    return array(
        'examKey' => $exam['key'],
        'examName' => $exam['name'],
        'examDate' => $exam['date'],
        'daysLeft' => $time_data['days'],
        'hoursLeft' => $time_data['hours'],
        'minutesLeft' => $time_data['minutes'],
        'totalSeconds' => $time_data['total_seconds'],
        'isPast' => $time_data['is_past'],
        'displayCountdown' => isset($exam['display_countdown']) ? $exam['display_countdown'] : true
    );
}

/**
 * ショートコード用の属性をサニタイズ
 *
 * @param array $atts ショートコード属性
 * @return array サニタイズされた属性
 */
function ecm_sanitize_shortcode_atts($atts) {
    $sanitized = array();
    
    foreach ($atts as $key => $value) {
        switch ($key) {
            case 'exam':
                $sanitized[$key] = sanitize_key($value);
                break;
            case 'style':
            case 'format':
            case 'order':
            case 'order_direction':
            case 'category':
                $sanitized[$key] = sanitize_text_field($value);
                break;
            case 'columns':
            case 'limit':
                $sanitized[$key] = absint($value);
                break;
            case 'upcoming':
            case 'show_description':
            case 'show_countdown':
            case 'show_category':
            case 'show_time':
            case 'hide_when_past':
            case 'show_exam_name':
            case 'animate':
                $sanitized[$key] = $value === 'yes' || $value === 'true' || $value === '1' ? 'yes' : 'no';
                break;
            case 'exclude':
                $sanitized[$key] = sanitize_text_field($value);
                break;
            case 'custom_message':
                $sanitized[$key] = sanitize_text_field($value);
                break;
            case 'color':
            case 'background':
                // 16進数カラーコードの検証
                if (preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
                    $sanitized[$key] = $value;
                } else {
                    $sanitized[$key] = '';
                }
                break;
            default:
                $sanitized[$key] = sanitize_text_field($value);
                break;
        }
    }
    
    return $sanitized;
}

/**
 * CSSクラス名を安全に生成
 *
 * @param string $class_name クラス名
 * @return string サニタイズされたクラス名
 */
function ecm_sanitize_css_class($class_name) {
    // HTMLクラス名として使用可能な文字のみを許可
    $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $class_name);
    
    // 先頭が数字の場合はプレフィックスを追加
    if (preg_match('/^[0-9]/', $sanitized)) {
        $sanitized = 'ecm-' . $sanitized;
    }
    
    return $sanitized;
}

/**
 * HTML属性を安全に生成
 *
 * @param array $attributes 属性の連想配列
 * @return string HTML属性文字列
 */
function ecm_build_html_attributes($attributes) {
    $html_attributes = array();
    
    foreach ($attributes as $name => $value) {
        $safe_name = sanitize_key($name);
        $safe_value = esc_attr($value);
        $html_attributes[] = $safe_name . '="' . $safe_value . '"';
    }
    
    return implode(' ', $html_attributes);
}

/**
 * 一列表示用のHTMLクラスを生成
 *
 * @param string $style スタイル名
 * @param array $additional_classes 追加クラス
 * @return string HTMLクラス文字列
 */
function ecm_get_inline_display_classes($style, $additional_classes = array()) {
    $classes = array('ecm-countdown');
    
    // スタイル別クラス
    if ($style === 'header' || $style === 'footer') {
        $classes[] = 'ecm-countdown-' . $style;
        $classes[] = 'ecm-inline-display';
    } else {
        $classes[] = 'ecm-countdown-' . $style;
    }
    
    // 追加クラス
    if (!empty($additional_classes)) {
        $classes = array_merge($classes, $additional_classes);
    }
    
    return implode(' ', array_map('ecm_sanitize_css_class', $classes));
}

/**
 * 一列表示用のカウントダウンHTMLを生成
 *
 * @param array $exam 試験データ
 * @param array $time_data 時間データ
 * @param bool $show_time 時間表示フラグ
 * @return string HTML
 */
function ecm_render_inline_countdown_html($exam, $time_data, $show_time = false) {
    if ($time_data['is_past']) {
        return '<div class="ecm-finished-message">' . __('試験終了', 'exam-countdown-manager') . '</div>';
    }
    
    ob_start();
    ?>
    <div class="ecm-countdown-inline">
        <?php if ($show_time): ?>
            <span class="ecm-time-segment">
                <span class="ecm-number"><?php echo esc_html($time_data['days']); ?></span>
                <span class="ecm-label"><?php _e('日', 'exam-countdown-manager'); ?></span>
            </span>
            <span class="ecm-time-segment">
                <span class="ecm-number"><?php echo esc_html($time_data['hours']); ?></span>
                <span class="ecm-label"><?php _e('時間', 'exam-countdown-manager'); ?></span>
            </span>
            <span class="ecm-time-segment">
                <span class="ecm-number"><?php echo esc_html($time_data['minutes']); ?></span>
                <span class="ecm-label"><?php _e('分', 'exam-countdown-manager'); ?></span>
            </span>
        <?php else: ?>
            <span class="ecm-inline-text">
                <?php printf(__('あと%d日', 'exam-countdown-manager'), $time_data['days']); ?>
            </span>
        <?php endif; ?>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * 試験データのエクスポート（バックアップ用）
 *
 * @return array エクスポート用データ
 */
function ecm_export_exam_data() {
    $data = array(
        'version' => ECM_PLUGIN_VERSION,
        'export_date' => current_time('mysql'),
        'exams' => get_option('ecm_exam_settings_data', array()),
        'display_options' => get_option('ecm_countdown_display_options', array())
    );
    
    return $data;
}

/**
 * 試験データのインポート（バックアップからの復元用）
 *
 * @param array $data インポートするデータ
 * @return bool 成功/失敗
 */
function ecm_import_exam_data($data) {
    if (!is_array($data) || !isset($data['exams'])) {
        return false;
    }
    
    // データの妥当性をチェック
    foreach ($data['exams'] as $key => $exam) {
        $validation_errors = ecm_validate_exam_data($exam);
        if (!empty($validation_errors)) {
            return false;
        }
    }
    
    // データを保存
    update_option('ecm_exam_settings_data', $data['exams']);
    
    if (isset($data['display_options'])) {
        update_option('ecm_countdown_display_options', $data['display_options']);
    }
    
    return true;
}

/**
 * 試験終了後の自動クリーンアップ
 */
function ecm_cleanup_expired_exams() {
    $exams = get_option('ecm_exam_settings_data', array());
    $updated = false;
    $cleanup_threshold = 30; // 試験終了から30日後
    
    foreach ($exams as $key => $exam) {
        $days_since_exam = -ecm_get_days_until_exam($exam['date']);
        
        if ($days_since_exam > $cleanup_threshold) {
            // 自動削除オプションが有効な場合のみ削除
            $auto_cleanup = get_option('ecm_auto_cleanup_expired', false);
            if ($auto_cleanup) {
                unset($exams[$key]);
                $updated = true;
            }
        }
    }
    
    if ($updated) {
        update_option('ecm_exam_settings_data', $exams);
        
        // プライマリ試験が削除された場合の処理
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
    }
}

/**
 * フロントエンド用のJavaScript変数を出力
 */
function ecm_localize_frontend_scripts() {
    if (!wp_script_is('ecm-frontend-script', 'enqueued')) {
        return;
    }
    
    $primary_exam = ecm_get_primary_exam();
    $localize_data = array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ecm_frontend_nonce'),
        'primaryExam' => $primary_exam ? ecm_get_countdown_js_data($primary_exam['key']) : null,
        'messages' => array(
            'examFinished' => __('試験終了', 'exam-countdown-manager'),
            'daysLeft' => __('あと%d日', 'exam-countdown-manager'),
            'urgent' => __('緊急', 'exam-countdown-manager'),
            'loadingError' => __('読み込みエラーが発生しました', 'exam-countdown-manager')
        ),
        'settings' => array(
            'autoUpdate' => true,
            'animationsEnabled' => true,
            'debugMode' => defined('WP_DEBUG') && WP_DEBUG,
            'inlineDisplayEnabled' => true // 一列表示機能有効フラグ
        )
    );
    
    wp_localize_script('ecm-frontend-script', 'ecmFrontend', $localize_data);
}

// フロントエンドスクリプトのローカライズ
add_action('wp_enqueue_scripts', 'ecm_localize_frontend_scripts', 20);

/**
 * 初期化処理の完了チェック
 *
 * @return bool 初期化が完了しているか
 */
function ecm_is_initialized() {
    $plugin_version = get_option('ecm_plugin_version');
    $exam_data = get_option('ecm_exam_settings_data');
    
    return !empty($plugin_version) && is_array($exam_data);
}

/**
 * 緊急度レベルを取得
 *
 * @param int $days_left 残り日数
 * @return string 緊急度レベル（normal, urgent, very-urgent, expired）
 */
function ecm_get_urgency_level($days_left) {
    if ($days_left < 0) {
        return 'expired';
    } elseif ($days_left <= 3) {
        return 'very-urgent';
    } elseif ($days_left <= 7) {
        return 'urgent';
    } else {
        return 'normal';
    }
}

/**
 * 一列表示専用のヘッダー・フッターカウントダウンHTML生成
 *
 * @param string $position 位置（header, footer）
 * @return string HTML
 */
function ecm_generate_inline_countdown_html($position = 'header') {
    $primary_exam = ecm_get_primary_exam();
    
    if (!$primary_exam || !isset($primary_exam['display_countdown']) || !$primary_exam['display_countdown']) {
        return '';
    }
    
    $options = get_option('ecm_countdown_display_options', array());
    $show_time = isset($options['show_detailed_time']) && $options['show_detailed_time'];
    $time_data = ecm_get_time_until_exam($primary_exam['date']);
    
    // カスタムカラーの適用
    $style_attrs = array();
    $color_key = $position . '_bg_color';
    $text_color_key = $position . '_text_color';
    
    if (!empty($options[$color_key])) {
        $style_attrs[] = 'background-color: ' . esc_attr($options[$color_key]);
    }
    
    if (!empty($options[$text_color_key])) {
        $style_attrs[] = 'color: ' . esc_attr($options[$text_color_key]);
    }
    
    $style_attr = !empty($style_attrs) ? ' style="' . implode('; ', $style_attrs) . '"' : '';
    
    // クラス名を生成
    $classes = ecm_get_inline_display_classes($position, array('ecm-auto-generated'));
    
    // データ属性を設定
    $data_attrs = sprintf(
        'data-exam-date="%s" data-exam-key="%s" data-show-time="%s" data-style="%s"',
        esc_attr($primary_exam['date']),
        esc_attr($primary_exam['key']),
        $show_time ? 'true' : 'false',
        esc_attr($position)
    );
    
    ob_start();
    ?>
    <div class="<?php echo esc_attr($classes); ?>" <?php echo $data_attrs; ?><?php echo $style_attr; ?>>
        <div class="ecm-exam-name"><?php echo esc_html($primary_exam['name']); ?></div>
        <?php echo ecm_render_inline_countdown_html($primary_exam, $time_data, $show_time); ?>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * 管理画面での統計情報を取得
 *
 * @return array 統計データ
 */
function ecm_get_admin_stats() {
    $exams = get_option('ecm_exam_settings_data', array());
    $total_exams = count($exams);
    $active_exams = 0;
    $finished_exams = 0;
    $urgent_exams = 0;
    
    foreach ($exams as $exam) {
        $days_left = ecm_get_days_until_exam($exam['date']);
        
        if ($days_left < 0) {
            $finished_exams++;
        } else {
            $active_exams++;
            if ($days_left <= 7) {
                $urgent_exams++;
            }
        }
    }
    
    return array(
        'total_exams' => $total_exams,
        'active_exams' => $active_exams,
        'finished_exams' => $finished_exams,
        'urgent_exams' => $urgent_exams,
        'primary_exam' => ecm_get_primary_exam()
    );
}

// 定期タスクのフック
add_action('ecm_daily_cleanup', 'ecm_cleanup_expired_exams');

/**
 * プラグイン有効化時のフック設定
 */
function ecm_activation_hooks() {
    // 定期タスクをスケジュール
    if (!wp_next_scheduled('ecm_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'ecm_daily_cleanup');
    }
}

/**
 * プラグイン無効化時のフック解除
 */
function ecm_deactivation_hooks() {
    wp_clear_scheduled_hook('ecm_daily_cleanup');
}

// アクションフックの登録
add_action('ecm_plugin_activation', 'ecm_activation_hooks');
add_action('ecm_plugin_deactivation', 'ecm_deactivation_hooks');