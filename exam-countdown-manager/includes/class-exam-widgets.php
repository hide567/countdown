<?php
/**
 * 資格試験ウィジェットクラス
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
     * ヘッダーカウントダウン表示
     */
    public function add_header_countdown() {
        $options = get_option('ecm_countdown_display_options', array());
        
        if (isset($options['show_in_header']) && $options['show_in_header']) {
            $primary_exam = ecm_get_primary_exam();
            
            if ($primary_exam && isset($primary_exam['display_countdown']) && $primary_exam['display_countdown']) {
                echo '<div id="ecm-header-countdown">';
                echo do_shortcode('[exam_countdown style="header"]');
                echo '</div>';
            }
        }
    }
    
    /**
     * フッターカウントダウン表示
     */
    public function add_footer_countdown() {
        $options = get_option('ecm_countdown_display_options', array());
        
        if (isset($options['show_in_footer']) && $options['show_in_footer']) {
            $primary_exam = ecm_get_primary_exam();
            
            if ($primary_exam && isset($primary_exam['display_countdown']) && $primary_exam['display_countdown']) {
                echo '<div id="ecm-footer-countdown">';
                echo do_shortcode('[exam_countdown style="footer"]');
                echo '</div>';
            }
        }
    }
}

/**
 * カウントダウンウィジェット
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
        $show_time = !empty($instance['show_time']) ? 'true' : 'false';
        $hide_when_past = !empty($instance['hide_when_past']) ? 'true' : 'false';
        
        echo $args['before_widget'];
        
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        
        // ショートコードを構築
        $shortcode_atts = array(
            'style="' . esc_attr($style) . '"',
            'show_time="' . esc_attr($show_time) . '"',
            'hide_when_past="' . esc_attr($hide_when_past) . '"'
        );
        
        if (!empty($exam_key)) {
            $shortcode_atts[] = 'exam="' . esc_attr($exam_key) . '"';
        }
        
        $shortcode = '[exam_countdown ' . implode(' ', $shortcode_atts) . ']';
        echo do_shortcode($shortcode);
        
        echo $args['after_widget'];
    }
    
    /**
     * ウィジェット設定フォーム
     */
    public function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $exam_key = isset($instance['exam_key']) ? esc_attr($instance['exam_key']) : '';
        $style = isset($instance['style']) ? esc_attr($instance['style']) : 'default';
        $show_time = isset($instance['show_time']) ? (bool) $instance['show_time'] : false;
        $hide_when_past = isset($instance['hide_when_past']) ? (bool) $instance['hide_when_past'] : false;
        
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
        $instance['show_time'] = !empty($new_instance['show_time']) ? 1 : 0;
        $instance['hide_when_past'] = !empty($new_instance['hide_when_past']) ? 1 : 0;
        
        return $instance;
    }
}

/**
 * 資格試験一覧ウィジェット
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
        $limit = !empty($instance['limit']) ? intval($instance['limit']) : 0;
        
        echo $args['before_widget'];
        
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        
        // ショートコードを構築
        $shortcode_atts = array(
            'upcoming="' . esc_attr($upcoming) . '"',
            'show_description="' . esc_attr($show_description) . '"',
            'show_countdown="' . esc_attr($show_countdown) . '"'
        );
        
        if (!empty($category)) {
            $shortcode_atts[] = 'category="' . esc_attr($category) . '"';
        }
        
        $shortcode = '[exam_list ' . implode(' ', $shortcode_atts) . ']';
        $output = do_shortcode($shortcode);
        
        // 件数制限がある場合は調整
        if ($limit > 0) {
            $output = $this->limit_exam_items($output, $limit);
        }
        
        echo $output;
        
        echo $args['after_widget'];
    }
    
    /**
     * 表示件数を制限
     */
    private function limit_exam_items($html, $limit) {
        // 簡易的な制限実装
        $pattern = '/<div class="ecm-exam-item">(.*?)<\/div>/s';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        
        if (count($matches) > $limit) {
            $limited_matches = array_slice($matches, 0, $limit);
            $limited_html = '';
            foreach ($limited_matches as $match) {
                $limited_html .= $match[0];
            }
            
            return str_replace($html, '<div class="ecm-exam-list ecm-columns-1">' . $limited_html . '</div>', $html);
        }
        
        return $html;
    }
    
    /**
     * ウィジェット設定フォーム
     */
    public function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $upcoming = isset($instance['upcoming']) ? (bool) $instance['upcoming'] : true;
        $category = isset($instance['category']) ? esc_attr($instance['category']) : '';
        $show_description = isset($instance['show_description']) ? (bool) $instance['show_description'] : false;
        $show_countdown = isset($instance['show_countdown']) ? (bool) $instance['show_countdown'] : true;
        $limit = isset($instance['limit']) ? intval($instance['limit']) : 5;
        
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
        $instance['limit'] = intval($new_instance['limit']);
        
        return $instance;
    }
}