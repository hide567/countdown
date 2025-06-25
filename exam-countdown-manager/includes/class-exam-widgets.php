<?php
/**
 * 資格試験ウィジェットクラス（一列表示・カラー対応版・完全版）
 *
 * @package ExamCountdownManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ECM_Exam_Widgets クラス
 */
class ECM_Exam_Widgets {
    
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
        add_action('widgets_init', array($this, 'register_widgets'));
        add_action('wp_head', array($this, 'add_header_countdown'));
        add_action('wp_footer', array($this, 'add_footer_countdown'));
    }
    
    /**
     * ウィジェットを登録
     */
    public function register_widgets() {
        register_widget('ECM_Countdown_Widget');
        register_widget('ECM_Exam_List_Widget');
    }
    
    /**
     * ヘッダーカウントダウン表示（一列表示・カスタムカラー対応）
     */
    public function add_header_countdown() {
        $options = get_option('ecm_countdown_display_options', array());
        
        if (isset($options['show_in_header']) && $options['show_in_header']) {
            $primary_exam = ecm_get_primary_exam();
            
            if ($primary_exam && isset($primary_exam['display_countdown']) && $primary_exam['display_countdown']) {
                // カスタムカラーの設定
                $custom_attrs = array();
                
                if (!empty($options['header_bg_color'])) {
                    $custom_attrs[] = 'background="' . esc_attr($options['header_bg_color']) . '"';
                }
                
                if (!empty($options['header_text_color'])) {
                    $custom_attrs[] = 'color="' . esc_attr($options['header_text_color']) . '"';
                }
                
                // スタイル設定
                $style = isset($options['countdown_style']) ? $options['countdown_style'] : 'default';
                $show_time = isset($options['show_detailed_time']) && $options['show_detailed_time'] ? 'true' : 'false';
                
                // ショートコード構築
                $shortcode_attrs = array(
                    'style="header"',
                    'show_time="' . $show_time . '"',
                    'size="small"'
                );
                
                if (!empty($custom_attrs)) {
                    $shortcode_attrs = array_merge($shortcode_attrs, $custom_attrs);
                }
                
                $shortcode = '[exam_countdown ' . implode(' ', $shortcode_attrs) . ']';
                
                echo '<div id="ecm-header-countdown">';
                echo do_shortcode($shortcode);
                echo '</div>';
            }
        }
    }
    
    /**
     * フッターカウントダウン表示（一列表示・カスタムカラー対応）
     */
    public function add_footer_countdown() {
        $options = get_option('ecm_countdown_display_options', array());
        
        if (isset($options['show_in_footer']) && $options['show_in_footer']) {
            $primary_exam = ecm_get_primary_exam();
            
            if ($primary_exam && isset($primary_exam['display_countdown']) && $primary_exam['display_countdown']) {
                // カスタムカラーの設定
                $custom_attrs = array();
                
                if (!empty($options['footer_bg_color'])) {
                    $custom_attrs[] = 'background="' . esc_attr($options['footer_bg_color']) . '"';
                }
                
                if (!empty($options['footer_text_color'])) {
                    $custom_attrs[] = 'color="' . esc_attr($options['footer_text_color']) . '"';
                }
                
                // スタイル設定
                $style = isset($options['countdown_style']) ? $options['countdown_style'] : 'default';
                $show_time = isset($options['show_detailed_time']) && $options['show_detailed_time'] ? 'true' : 'false';
                
                // ショートコード構築
                $shortcode_attrs = array(
                    'style="footer"',
                    'show_time="' . $show_time . '"',
                    'size="small"'
                );
                
                if (!empty($custom_attrs)) {
                    $shortcode_attrs = array_merge($shortcode_attrs, $custom_attrs);
                }
                
                $shortcode = '[exam_countdown ' . implode(' ', $shortcode_attrs) . ']';
                
                echo '<div id="ecm-footer-countdown">';
                echo do_shortcode($shortcode);
                echo '</div>';
            }
        }
    }
}

/**
 * カウントダウンウィジェット（強化版）
 */
class ECM_Countdown_Widget extends WP_Widget {
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct(
            'ecm_countdown_widget',
            __('資格試験カウントダウン', 'exam-countdown-manager'),
            array(
                'description' => __('資格試験までのカウントダウンを表示します。', 'exam-countdown-manager'),
                'classname' => 'ecm-countdown-widget'
            )
        );
    }
    
    /**
     * ウィジェット表示
     */
    public function widget($args, $instance) {
        $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
        $exam_key = !empty($instance['exam_key']) ? $instance['exam_key'] : '';
        $style = !empty($instance['style']) ? $instance['style'] : 'default';
        $size = !empty($instance['size']) ? $instance['size'] : 'medium';
        $show_time = !empty($instance['show_time']) ? 'true' : 'false';
        $hide_when_past = !empty($instance['hide_when_past']) ? 'true' : 'false';
        $custom_bg_color = !empty($instance['custom_bg_color']) ? $instance['custom_bg_color'] : '';
        $custom_text_color = !empty($instance['custom_text_color']) ? $instance['custom_text_color'] : '';
        
        echo $args['before_widget'];
        
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        
        // ショートコードを構築
        $shortcode_atts = array(
            'style="' . esc_attr($style) . '"',
            'size="' . esc_attr($size) . '"',
            'show_time="' . esc_attr($show_time) . '"',
            'hide_when_past="' . esc_attr($hide_when_past) . '"'
        );
        
        if (!empty($exam_key)) {
            $shortcode_atts[] = 'exam="' . esc_attr($exam_key) . '"';
        }
        
        // カスタムカラーの追加
        if (!empty($custom_bg_color) && preg_match('/^#[a-fA-F0-9]{6}$/', $custom_bg_color)) {
            $shortcode_atts[] = 'background="' . esc_attr($custom_bg_color) . '"';
        }
        
        if (!empty($custom_text_color) && preg_match('/^#[a-fA-F0-9]{6}$/', $custom_text_color)) {
            $shortcode_atts[] = 'color="' . esc_attr($custom_text_color) . '"';
        }
        
        $shortcode = '[exam_countdown ' . implode(' ', $shortcode_atts) . ']';
        echo do_shortcode($shortcode);
        
        echo $args['after_widget'];
    }
    
    /**
     * ウィジェット設定フォーム（カラー設定追加）
     */
    public function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $exam_key = isset($instance['exam_key']) ? esc_attr($instance['exam_key']) : '';
        $style = isset($instance['style']) ? esc_attr($instance['style']) : 'default';
        $size = isset($instance['size']) ? esc_attr($instance['size']) : 'medium';
        $show_time = isset($instance['show_time']) ? (bool) $instance['show_time'] : false;
        $hide_when_past = isset($instance['hide_when_past']) ? (bool) $instance['hide_when_past'] : false;
        $custom_bg_color = isset($instance['custom_bg_color']) ? esc_attr($instance['custom_bg_color']) : '';
        $custom_text_color = isset($instance['custom_text_color']) ? esc_attr($instance['custom_text_color']) : '';
        
        $exams = ecm_get_all_exams();
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php _e('タイトル:', 'exam-countdown-manager'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('exam_key')); ?>">
                <?php _e('資格試験:', 'exam-countdown-manager'); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('exam_key')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('exam_key')); ?>">
                <option value=""><?php _e('プライマリ試験を使用', 'exam-countdown-manager'); ?></option>
                <?php foreach ($exams as $key => $exam): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($exam_key, $key); ?>>
                        <?php echo esc_html($exam['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('style')); ?>">
                <?php _e('スタイル:', 'exam-countdown-manager'); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('style')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('style')); ?>">
                <option value="default" <?php selected($style, 'default'); ?>><?php _e('デフォルト', 'exam-countdown-manager'); ?></option>
                <option value="simple" <?php selected($style, 'simple'); ?>><?php _e('シンプル', 'exam-countdown-manager'); ?></option>
                <option value="detailed" <?php selected($style, 'detailed'); ?>><?php _e('詳細', 'exam-countdown-manager'); ?></option>
                <option value="compact" <?php selected($style, 'compact'); ?>><?php _e('コンパクト', 'exam-countdown-manager'); ?></option>
            </select>
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('size')); ?>">
                <?php _e('サイズ:', 'exam-countdown-manager'); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('size')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('size')); ?>">
                <option value="small" <?php selected($size, 'small'); ?>><?php _e('小', 'exam-countdown-manager'); ?></option>
                <option value="medium" <?php selected($size, 'medium'); ?>><?php _e('中', 'exam-countdown-manager'); ?></option>
                <option value="large" <?php selected($size, 'large'); ?>><?php _e('大', 'exam-countdown-manager'); ?></option>
            </select>
        </p>
        
        <!-- カスタムカラー設定 -->
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('custom_bg_color')); ?>">
                <?php _e('背景色:', 'exam-countdown-manager'); ?>
            </label>
            <input type="color" class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('custom_bg_color')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('custom_bg_color')); ?>" 
                   value="<?php echo esc_attr($custom_bg_color); ?>"
                   style="height: 30px;">
            <small><?php _e('空にするとデフォルトカラーを使用', 'exam-countdown-manager'); ?></small>
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('custom_text_color')); ?>">
                <?php _e('文字色:', 'exam-countdown-manager'); ?>
            </label>
            <input type="color" class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('custom_text_color')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('custom_text_color')); ?>" 
                   value="<?php echo esc_attr($custom_text_color); ?>"
                   style="height: 30px;">
            <small><?php _e('空にするとデフォルトカラーを使用', 'exam-countdown-manager'); ?></small>
        </p>
        
        <p>
            <input class="checkbox" type="checkbox" 
                   id="<?php echo esc_attr($this->get_field_id('show_time')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_time')); ?>" 
                   <?php checked($show_time); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_time')); ?>">
                <?php _e('時間・分も表示する', 'exam-countdown-manager'); ?>
            </label>
        </p>
        
        <p>
            <input class="checkbox" type="checkbox" 
                   id="<?php echo esc_attr($this->get_field_id('hide_when_past')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('hide_when_past')); ?>" 
                   <?php checked($hide_when_past); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('hide_when_past')); ?>">
                <?php _e('試験終了後は非表示にする', 'exam-countdown-manager'); ?>
            </label>
        </p>
        <?php
    }
    
    /**
     * ウィジェット設定更新
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = sanitize_text_field($new_instance['title']);
        $instance['exam_key'] = sanitize_key($new_instance['exam_key']);
        $instance['style'] = sanitize_text_field($new_instance['style']);
        $instance['size'] = sanitize_text_field($new_instance['size']);
        $instance['show_time'] = !empty($new_instance['show_time']) ? 1 : 0;
        $instance['hide_when_past'] = !empty($new_instance['hide_when_past']) ? 1 : 0;
        
        // カスタムカラーのバリデーション
        $custom_bg_color = sanitize_text_field($new_instance['custom_bg_color']);
        $custom_text_color = sanitize_text_field($new_instance['custom_text_color']);
        
        if (empty($custom_bg_color) || preg_match('/^#[a-fA-F0-9]{6}$/', $custom_bg_color)) {
            $instance['custom_bg_color'] = $custom_bg_color;
        } else {
            $instance['custom_bg_color'] = '';
        }
        
        if (empty($custom_text_color) || preg_match('/^#[a-fA-F0-9]{6}$/', $custom_text_color)) {
            $instance['custom_text_color'] = $custom_text_color;
        } else {
            $instance['custom_text_color'] = '';
        }
        
        return $instance;
    }
}

/**
 * 資格試験一覧ウィジェット（改良版）
 */
class ECM_Exam_List_Widget extends WP_Widget {
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct(
            'ecm_exam_list_widget',
            __('資格試験一覧', 'exam-countdown-manager'),
            array(
                'description' => __('資格試験の一覧を表示します。', 'exam-countdown-manager'),
                'classname' => 'ecm-exam-list-widget'
            )
        );
    }
    
    /**
     * ウィジェット表示
     */
    public function widget($args, $instance) {
        $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
        $upcoming = !empty($instance['upcoming']) ? 'yes' : 'no';
        $category = !empty($instance['category']) ? $instance['category'] : '';
        $show_description = !empty($instance['show_description']) ? 'yes' : 'no';
        $show_countdown = !empty($instance['show_countdown']) ? 'yes' : 'no';
        $show_category = !empty($instance['show_category']) ? 'yes' : 'no';
        $limit = !empty($instance['limit']) ? intval($instance['limit']) : 0;
        $order = !empty($instance['order']) ? $instance['order'] : 'date';
        
        echo $args['before_widget'];
        
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        
        // ショートコードを構築
        $shortcode_atts = array(
            'upcoming="' . esc_attr($upcoming) . '"',
            'show_description="' . esc_attr($show_description) . '"',
            'show_countdown="' . esc_attr($show_countdown) . '"',
            'show_category="' . esc_attr($show_category) . '"',
            'order="' . esc_attr($order) . '"',
            'columns="1"' // ウィジェットでは常に1列
        );
        
        if (!empty($category)) {
            $shortcode_atts[] = 'category="' . esc_attr($category) . '"';
        }
        
        if ($limit > 0) {
            $shortcode_atts[] = 'limit="' . intval($limit) . '"';
        }
        
        $shortcode = '[exam_list ' . implode(' ', $shortcode_atts) . ']';
        echo do_shortcode($shortcode);
        
        echo $args['after_widget'];
    }
    
    /**
     * ウィジェット設定フォーム（拡張版）
     */
    public function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $upcoming = isset($instance['upcoming']) ? (bool) $instance['upcoming'] : true;
        $category = isset($instance['category']) ? esc_attr($instance['category']) : '';
        $show_description = isset($instance['show_description']) ? (bool) $instance['show_description'] : false;
        $show_countdown = isset($instance['show_countdown']) ? (bool) $instance['show_countdown'] : true;
        $show_category = isset($instance['show_category']) ? (bool) $instance['show_category'] : true;
        $limit = isset($instance['limit']) ? intval($instance['limit']) : 5;
        $order = isset($instance['order']) ? esc_attr($instance['order']) : 'date';
        
        $categories = ecm_get_exam_categories();
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php _e('タイトル:', 'exam-countdown-manager'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        
        <p>
            <input class="checkbox" type="checkbox" 
                   id="<?php echo esc_attr($this->get_field_id('upcoming')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('upcoming')); ?>" 
                   <?php checked($upcoming); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('upcoming')); ?>">
                <?php _e('今後の試験のみ表示', 'exam-countdown-manager'); ?>
            </label>
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('category')); ?>">
                <?php _e('カテゴリー:', 'exam-countdown-manager'); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('category')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('category')); ?>">
                <option value=""><?php _e('すべてのカテゴリー', 'exam-countdown-manager'); ?></option>
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($category, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('order')); ?>">
                <?php _e('並び順:', 'exam-countdown-manager'); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('order')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('order')); ?>">
                <option value="date" <?php selected($order, 'date'); ?>><?php _e('試験日順', 'exam-countdown-manager'); ?></option>
                <option value="name" <?php selected($order, 'name'); ?>><?php _e('名前順', 'exam-countdown-manager'); ?></option>
                <option value="category" <?php selected($order, 'category'); ?>><?php _e('カテゴリー順', 'exam-countdown-manager'); ?></option>
            </select>
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>">
                <?php _e('表示件数:', 'exam-countdown-manager'); ?>
            </label>
            <input class="small-text" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('limit')); ?>" 
                   type="number" value="<?php echo esc_attr($limit); ?>" min="0" max="20">
            <br><small><?php _e('0を指定すると全件表示', 'exam-countdown-manager'); ?></small>
        </p>
        
        <p>
            <input class="checkbox" type="checkbox" 
                   id="<?php echo esc_attr($this->get_field_id('show_countdown')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_countdown')); ?>" 
                   <?php checked($show_countdown); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_countdown')); ?>">
                <?php _e('カウントダウンを表示', 'exam-countdown-manager'); ?>
            </label>
        </p>
        
        <p>
            <input class="checkbox" type="checkbox" 
                   id="<?php echo esc_attr($this->get_field_id('show_category')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_category')); ?>" 
                   <?php checked($show_category); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_category')); ?>">
                <?php _e('カテゴリーを表示', 'exam-countdown-manager'); ?>
            </label>
        </p>
        
        <p>
            <input class="checkbox" type="checkbox" 
                   id="<?php echo esc_attr($this->get_field_id('show_description')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_description')); ?>" 
                   <?php checked($show_description); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_description')); ?>">
                <?php _e('説明を表示', 'exam-countdown-manager'); ?>
            </label>
        </p>
        <?php
    }
    
    /**
     * ウィジェット設定更新
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = sanitize_text_field($new_instance['title']);
        $instance['upcoming'] = !empty($new_instance['upcoming']) ? 1 : 0;
        $instance['category'] = sanitize_text_field($new_instance['category']);
        $instance['show_description'] = !empty($new_instance['show_description']) ? 1 : 0;
        $instance['show_countdown'] = !empty($new_instance['show_countdown']) ? 1 : 0;
        $instance['show_category'] = !empty($new_instance['show_category']) ? 1 : 0;
        $instance['limit'] = intval($new_instance['limit']);
        $instance['order'] = sanitize_text_field($new_instance['order']);
        
        return $instance;
    }
}