<?php
/**
 * 資格試験ショートコードクラス（日数強調機能付き完全版）
 *
 * @package ExamCountdownManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ECM_Exam_Shortcodes クラス
 */
class ECM_Exam_Shortcodes {
    
    /**
     * インスタンス
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
        add_action('init', array($this, 'register_shortcodes'));
    }
    
    /**
     * ショートコードを登録
     */
    public function register_shortcodes() {
        add_shortcode('exam_countdown', array($this, 'countdown_shortcode'));
        add_shortcode('exam_list', array($this, 'exam_list_shortcode'));
        add_shortcode('exam_info', array($this, 'exam_info_shortcode'));
        add_shortcode('exam_progress', array($this, 'exam_progress_shortcode'));
        add_shortcode('exam_calendar', array($this, 'exam_calendar_shortcode'));
    }
    
    /**
     * カウントダウンショートコード（日数強調機能付き）
     * 
     * 使用例:
     * [exam_countdown]
     * [exam_countdown exam="gyouseishoshi" style="simple"]
     * [exam_countdown style="detailed" show_time="true"]
     * [exam_countdown style="header"] - 一列表示（ヘッダー用）
     * [exam_countdown style="footer"] - 一列表示（フッター用）
     * [exam_countdown number_color="#ff0000" number_size="large"] - 日数強調
     */
    public function countdown_shortcode($atts) {
        // 属性のデフォルト値を設定
        $atts = shortcode_atts(array(
            'exam' => '',                    // 特定の資格試験キー
            'style' => 'default',           // default, simple, detailed, compact, header, footer
            'size' => 'medium',             // small, medium, large
            'show_time' => 'false',         // 時間・分も表示するか
            'hide_when_past' => 'false',    // 試験終了後に非表示にするか
            'custom_message' => '',         // カスタムメッセージ
            'show_exam_name' => 'true',     // 試験名を表示するか
            'animate' => 'true',            // アニメーション効果
            'color' => '',                  // カスタムカラー（全体の文字色）
            'background' => '',             // カスタム背景色
            'number_color' => '',           // 日数部分の色（NEW）
            'number_size' => ''             // 日数部分のサイズ（NEW）
        ), $atts, 'exam_countdown');
        
        // 資格試験データを取得
        if (!empty($atts['exam'])) {
            $exam = ecm_get_exam_by_key($atts['exam']);
        } else {
            $exam = ecm_get_primary_exam();
        }
        
        // 試験データが見つからない場合
        if (!$exam) {
            return $this->render_error_message(__('指定された資格試験が見つかりません。管理画面で資格試験を登録してください。', 'exam-countdown-manager'));
        }
        
        // 表示条件をチェック
        if (!$this->should_display_countdown($exam, $atts)) {
            return '';
        }
        
        // 残り時間を計算
        $time_data = ecm_get_time_until_exam($exam['date']);
        
        // 試験終了後の処理
        if ($time_data['is_past'] && $atts['hide_when_past'] === 'true') {
            return '';
        }
        
        // HTML出力を生成
        return $this->render_countdown_html($exam, $time_data, $atts);
    }
    
    /**
     * カウントダウンHTMLをレンダリング（日数強調機能付き）
     */
    private function render_countdown_html($exam, $time_data, $atts) {
        // カスタムスタイルを生成（日数強調含む）
        $custom_styles = $this->generate_custom_styles($atts);
        
        // クラス名を構築
        $classes = array(
            'ecm-countdown',
            'ecm-countdown-' . sanitize_html_class($atts['style']),
            'ecm-size-' . sanitize_html_class($atts['size'])
        );
        
        // 日数強調設定がある場合のクラス追加
        if (!empty($atts['number_color']) || !empty($atts['number_size'])) {
            $classes[] = 'ecm-number-enhanced';
        }
        
        // 一列表示の場合は専用クラスを追加
        if ($atts['style'] === 'header' || $atts['style'] === 'footer') {
            $classes[] = 'ecm-inline-display';
        }
        
        if ($atts['animate'] === 'true') {
            $classes[] = 'ecm-animated';
        }
        
        // 緊急度に応じたクラスを追加
        if (!$time_data['is_past']) {
            if ($time_data['days'] <= 3) {
                $classes[] = 'ecm-very-urgent';
            } elseif ($time_data['days'] <= 7) {
                $classes[] = 'ecm-urgent';
            }
        }
        
        if ($time_data['is_past']) {
            $classes[] = 'ecm-countdown-finished';
        }
        
        $class_string = implode(' ', $classes);
        
        // データ属性を設定
        $data_attrs = sprintf(
            'data-exam-date="%s" data-exam-key="%s" data-show-time="%s" data-style="%s"',
            esc_attr($exam['date']),
            esc_attr($exam['key']),
            esc_attr($atts['show_time']),
            esc_attr($atts['style'])
        );
        
        // HTML出力を開始
        ob_start();
        ?>
        <div class="<?php echo esc_attr($class_string); ?>" <?php echo $data_attrs; ?> <?php echo $custom_styles; ?>>
            <?php if ($atts['show_exam_name'] === 'true'): ?>
                <div class="ecm-exam-name"><?php echo esc_html($exam['name']); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($atts['custom_message'])): ?>
                <div class="ecm-custom-message"><?php echo esc_html($atts['custom_message']); ?></div>
            <?php endif; ?>
            
            <?php if ($time_data['is_past']): ?>
                <div class="ecm-finished-message">
                    <?php _e('試験終了', 'exam-countdown-manager'); ?>
                </div>
            <?php else: ?>
                <?php echo $this->render_countdown_content($atts['style'], $time_data, $atts['show_time'] === 'true', $atts); ?>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * カスタムスタイルを生成（日数強調対応）
     */
    private function generate_custom_styles($atts) {
        $styles = array();
        $css_vars = array();
        
        // 基本色設定
        if (!empty($atts['color']) && preg_match('/^#[a-fA-F0-9]{6}$/', $atts['color'])) {
            $css_vars[] = '--ecm-text-color: ' . esc_attr($atts['color']);
        }
        
        if (!empty($atts['background']) && preg_match('/^#[a-fA-F0-9]{6}$/', $atts['background'])) {
            $css_vars[] = '--ecm-primary-color: ' . esc_attr($atts['background']);
        }
        
        // 日数強調設定
        if (!empty($atts['number_color']) && preg_match('/^#[a-fA-F0-9]{6}$/', $atts['number_color'])) {
            $css_vars[] = '--ecm-number-color: ' . esc_attr($atts['number_color']);
        }
        
        if (!empty($atts['number_size'])) {
            $size_map = array(
                'small' => '1.2em',
                'medium' => '2em',
                'large' => '3em',
                'xlarge' => '4em'
            );
            
            if (isset($size_map[$atts['number_size']])) {
                $css_vars[] = '--ecm-number-size: ' . $size_map[$atts['number_size']];
            }
        }
        
        if (!empty($css_vars)) {
            $styles[] = implode('; ', $css_vars);
        }
        
        if (!empty($styles)) {
            return 'style="' . esc_attr(implode('; ', $styles)) . '"';
        }
        
        return '';
    }
    
    /**
     * カウントダウンコンテンツをレンダリング（日数強調対応）
     */
    private function render_countdown_content($style, $time_data, $show_time, $atts) {
        ob_start();
        
        switch ($style) {
            case 'header':
            case 'footer':
                $this->render_inline_countdown($time_data, $show_time, $atts);
                break;
                
            case 'detailed':
                $this->render_detailed_countdown($time_data, $show_time, $atts);
                break;
                
            case 'simple':
                $this->render_simple_countdown($time_data, $atts);
                break;
                
            case 'compact':
                $this->render_compact_countdown($time_data, $atts);
                break;
                
            default:
                $this->render_default_countdown($time_data, $atts);
                break;
        }
        
        return ob_get_clean();
    }
    
    /**
     * 一列表示カウントダウンをレンダリング（日数強調対応）
     */
    private function render_inline_countdown($time_data, $show_time, $atts) {
        ?>
        <div class="ecm-countdown-inline">
            <?php if ($show_time): ?>
                <span class="ecm-time-segment">
                    <span class="ecm-number ecm-enhanced-number"><?php echo esc_html($time_data['days']); ?></span>
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
                    <?php _e('あと', 'exam-countdown-manager'); ?><span class="ecm-enhanced-number"><?php echo esc_html($time_data['days']); ?></span><?php _e('日', 'exam-countdown-manager'); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * 詳細カウントダウンをレンダリング（日数強調対応）
     */
    private function render_detailed_countdown($time_data, $show_time, $atts) {
        ?>
        <div class="ecm-countdown-detailed">
            <div class="ecm-time-unit ecm-days-unit">
                <span class="ecm-number ecm-enhanced-number"><?php echo esc_html($time_data['days']); ?></span>
                <span class="ecm-label"><?php _e('日', 'exam-countdown-manager'); ?></span>
            </div>
            <?php if ($show_time): ?>
                <div class="ecm-time-unit">
                    <span class="ecm-number"><?php echo esc_html($time_data['hours']); ?></span>
                    <span class="ecm-label"><?php _e('時間', 'exam-countdown-manager'); ?></span>
                </div>
                <div class="ecm-time-unit">
                    <span class="ecm-number"><?php echo esc_html($time_data['minutes']); ?></span>
                    <span class="ecm-label"><?php _e('分', 'exam-countdown-manager'); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * シンプルカウントダウンをレンダリング（日数強調対応）
     */
    private function render_simple_countdown($time_data, $atts) {
        ?>
        <div class="ecm-countdown-simple">
            <?php _e('あと', 'exam-countdown-manager'); ?><span class="ecm-enhanced-number"><?php echo esc_html($time_data['days']); ?></span><?php _e('日', 'exam-countdown-manager'); ?>
        </div>
        <?php
    }
    
    /**
     * コンパクトカウントダウンをレンダリング（日数強調対応）
     */
    private function render_compact_countdown($time_data, $atts) {
        ?>
        <div class="ecm-countdown-compact">
            <span class="ecm-days-number ecm-enhanced-number"><?php echo esc_html($time_data['days']); ?></span>日
        </div>
        <?php
    }
    
    /**
     * デフォルトカウントダウンをレンダリング（日数強調対応）
     */
    private function render_default_countdown($time_data, $atts) {
        ?>
        <div class="ecm-countdown-default">
            あと <span class="ecm-days-number ecm-enhanced-number"><?php echo esc_html($time_data['days']); ?></span> 日
        </div>
        <?php
    }
    
    /**
     * 資格試験一覧ショートコード
     */
    public function exam_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'upcoming' => 'no',              // 今後の試験のみ表示
            'category' => '',               // 特定カテゴリーのみ
            'columns' => '1',               // カラム数 (1-4)
            'show_description' => 'no',     // 説明を表示
            'show_countdown' => 'yes',      // カウントダウンを表示
            'show_category' => 'yes',       // カテゴリーを表示
            'limit' => '0',                 // 表示件数制限 (0=無制限)
            'order' => 'date',              // ソート順 (date, name, category)
            'order_direction' => 'asc',     // ソート方向 (asc, desc)
            'exclude' => ''                 // 除外する試験キー (カンマ区切り)
        ), $atts, 'exam_list');
        
        // 資格試験データを取得
        if ($atts['upcoming'] === 'yes') {
            $exams = ecm_get_all_exams(true);
        } else {
            $exams = ecm_get_all_exams(false);
        }
        
        // カテゴリーフィルタ
        if (!empty($atts['category'])) {
            $exams = $this->filter_exams_by_category($exams, $atts['category']);
        }
        
        // 除外処理
        if (!empty($atts['exclude'])) {
            $exclude_keys = array_map('trim', explode(',', $atts['exclude']));
            foreach ($exclude_keys as $exclude_key) {
                unset($exams[$exclude_key]);
            }
        }
        
        if (empty($exams)) {
            return '<div class="ecm-no-exams">' . __('表示する資格試験がありません。', 'exam-countdown-manager') . '</div>';
        }
        
        // ソート処理
        $exams = $this->sort_exams($exams, $atts['order'], $atts['order_direction']);
        
        // 件数制限
        if (intval($atts['limit']) > 0) {
            $exams = array_slice($exams, 0, intval($atts['limit']), true);
        }
        
        return $this->render_exam_list_html($exams, $atts);
    }
    
    /**
     * 試験一覧HTMLをレンダリング
     */
    private function render_exam_list_html($exams, $atts) {
        // カラムクラスを決定
        $columns = max(1, min(4, intval($atts['columns'])));
        $column_class = ($columns > 1) ? 'ecm-columns-' . $columns : '';
        
        // カテゴリー情報を取得
        $categories = ecm_get_exam_categories();
        
        ob_start();
        ?>
        <div class="ecm-exam-list <?php echo esc_attr($column_class); ?>">
            <?php foreach ($exams as $key => $exam): 
                $days_left = ecm_get_days_until_exam($exam['date']);
                $category_name = isset($categories[$exam['category']]) ? $categories[$exam['category']] : __('未設定', 'exam-countdown-manager');
            ?>
                <div class="ecm-exam-item" data-exam-key="<?php echo esc_attr($key); ?>">
                    <div class="ecm-exam-header">
                        <h3 class="ecm-exam-title"><?php echo esc_html($exam['name']); ?></h3>
                        <?php if ($atts['show_category'] === 'yes'): ?>
                            <div class="ecm-exam-category">
                                <span class="ecm-category-badge"><?php echo esc_html($category_name); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ecm-exam-content">
                        <div class="ecm-exam-date">
                            <strong><?php _e('試験日:', 'exam-countdown-manager'); ?></strong>
                            <?php echo ecm_format_date($exam['date']); ?>
                        </div>
                        
                        <?php if ($atts['show_countdown'] === 'yes'): ?>
                            <div class="ecm-exam-countdown">
                                <?php if ($days_left < 0): ?>
                                    <span class="ecm-status-finished"><?php _e('終了済み', 'exam-countdown-manager'); ?></span>
                                <?php else: ?>
                                    <span class="ecm-days-left">
                                        <?php printf(__('あと%d日', 'exam-countdown-manager'), $days_left); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_description'] === 'yes' && !empty($exam['description'])): ?>
                            <div class="ecm-exam-description">
                                <?php echo wp_kses_post(wpautop($exam['description'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 資格試験情報ショートコード
     */
    public function exam_info_shortcode($atts) {
        $atts = shortcode_atts(array(
            'exam' => '',                   // 資格試験キー（必須）
            'format' => 'default',         // default, card, inline
            'show_countdown' => 'yes',      // カウントダウン表示
            'show_details' => 'yes',        // 詳細情報表示
            'show_progress' => 'no'         // 進捗情報表示（将来実装）
        ), $atts, 'exam_info');
        
        if (empty($atts['exam'])) {
            return $this->render_error_message(__('exam パラメータが必要です。使用例: [exam_info exam="gyouseishoshi"]', 'exam-countdown-manager'));
        }
        
        $exam = ecm_get_exam_by_key($atts['exam']);
        if (!$exam) {
            return $this->render_error_message(__('指定された資格試験が見つかりません。', 'exam-countdown-manager'));
        }
        
        return $this->render_exam_info_html($exam, $atts);
    }
    
    /**
     * 試験情報HTMLをレンダリング
     */
    private function render_exam_info_html($exam, $atts) {
        $exam_details = ecm_get_exam_details($exam['key']);
        $categories = ecm_get_exam_categories();
        $category_name = isset($categories[$exam['category']]) ? $categories[$exam['category']] : __('未設定', 'exam-countdown-manager');
        
        $classes = array('ecm-exam-info');
        if ($atts['format'] === 'card') {
            $classes[] = 'ecm-exam-info-card';
        } elseif ($atts['format'] === 'inline') {
            $classes[] = 'ecm-exam-info-inline';
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <div class="ecm-info-header">
                <h3 class="ecm-info-name"><?php echo esc_html($exam['name']); ?></h3>
                <span class="ecm-info-category"><?php echo esc_html($category_name); ?></span>
            </div>
            
            <div class="ecm-info-content">
                <div class="ecm-info-date">
                    <strong><?php _e('試験日:', 'exam-countdown-manager'); ?></strong>
                    <?php echo ecm_format_date($exam['date']); ?>
                </div>
                
                <?php if ($atts['show_countdown'] === 'yes' && $exam_details): ?>
                    <div class="ecm-info-countdown">
                        <?php if ($exam_details['days_left'] < 0): ?>
                            <?php _e('試験終了', 'exam-countdown-manager'); ?>
                        <?php else: ?>
                            <?php printf(__('あと%d日', 'exam-countdown-manager'), $exam_details['days_left']); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($exam['description'])): ?>
                    <div class="ecm-info-description">
                        <?php echo wp_kses_post(wpautop($exam['description'])); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_details'] === 'yes' && $exam_details): ?>
                    <div class="ecm-info-details">
                        <div class="ecm-detail-item">
                            <span class="ecm-detail-label"><?php _e('学習可能日数:', 'exam-countdown-manager'); ?></span>
                            <span class="ecm-detail-value"><?php echo esc_html($exam_details['study_days_left']); ?>日</span>
                        </div>
                        <div class="ecm-detail-item">
                            <span class="ecm-detail-label"><?php _e('平日のみ:', 'exam-countdown-manager'); ?></span>
                            <span class="ecm-detail-value"><?php echo esc_html($exam_details['study_days_left_weekdays']); ?>日</span>
                        </div>
                        <div class="ecm-detail-item">
                            <span class="ecm-detail-label"><?php _e('推奨学習期間:', 'exam-countdown-manager'); ?></span>
                            <span class="ecm-detail-value"><?php echo esc_html($exam_details['recommended_study_period']); ?>ヶ月</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 学習進捗ショートコード（将来実装用）
     */
    public function exam_progress_shortcode($atts) {
        $atts = shortcode_atts(array(
            'exam' => '',                   // 資格試験キー
            'user_id' => '',               // ユーザーID（指定しない場合は現在のユーザー）
            'format' => 'default',         // default, compact, detailed
            'show_chart' => 'yes'          // グラフ表示
        ), $atts, 'exam_progress');
        
        // 将来実装予定の機能
        return '<div class="ecm-progress-placeholder">' . 
               __('学習進捗機能は今後のアップデートで実装予定です。', 'exam-countdown-manager') . 
               '</div>';
    }
    
    /**
     * 試験カレンダーショートコード（将来実装用）
     */
    public function exam_calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'month' => '',                  // 表示月 (YYYY-MM形式)
            'category' => '',              // カテゴリーフィルタ
            'show_past' => 'no'            // 過去の試験も表示
        ), $atts, 'exam_calendar');
        
        // 将来実装予定の機能
        return '<div class="ecm-progress-placeholder">' . 
               __('試験カレンダー機能は今後のアップデートで実装予定です。', 'exam-countdown-manager') . 
               '</div>';
    }
    
    /**
     * 資格試験配列をソート
     */
    private function sort_exams($exams, $order, $direction) {
        switch ($order) {
            case 'name':
                uasort($exams, function($a, $b) use ($direction) {
                    $result = strcmp($a['name'], $b['name']);
                    return ($direction === 'desc') ? -$result : $result;
                });
                break;
                
            case 'category':
                uasort($exams, function($a, $b) use ($direction) {
                    $category_a = isset($a['category']) ? $a['category'] : '';
                    $category_b = isset($b['category']) ? $b['category'] : '';
                    $result = strcmp($category_a, $category_b);
                    return ($direction === 'desc') ? -$result : $result;
                });
                break;
                
            case 'date':
            default:
                uasort($exams, function($a, $b) use ($direction) {
                    $result = strtotime($a['date']) - strtotime($b['date']);
                    return ($direction === 'desc') ? -$result : $result;
                });
                break;
        }
        
        return $exams;
    }
    
    /**
     * カテゴリーで試験をフィルタリング
     */
    private function filter_exams_by_category($exams, $category) {
        $filtered = array();
        
        foreach ($exams as $key => $exam) {
            if (isset($exam['category']) && $exam['category'] === $category) {
                $filtered[$key] = $exam;
            }
        }
        
        return $filtered;
    }
    
    /**
     * カウントダウン表示の条件チェック
     */
    private function should_display_countdown($exam, $atts) {
        // 基本的な表示条件をチェック
        if (!isset($exam['display_countdown']) || !$exam['display_countdown']) {
            return false;
        }
        
        // 試験が終了している場合の設定をチェック
        $days_left = ecm_get_days_until_exam($exam['date']);
        if ($days_left < 0 && $atts['hide_when_past'] === 'true') {
            return false;
        }
        
        return true;
    }
    
    /**
     * エラーメッセージをレンダリング
     */
    private function render_error_message($message) {
        return sprintf(
            '<div class="ecm-error" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0;">%s</div>',
            esc_html($message)
        );
    }
}
