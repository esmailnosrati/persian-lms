<?php
class Persian_LMS_Quiz {
    public function __construct() {
        add_action('init', array($this, 'register_quiz_post_type'));
        add_action('add_meta_boxes', array($this, 'add_quiz_meta_boxes'));
        add_action('save_post_lms_quiz', array($this, 'save_quiz_meta'));
    }

    public function register_quiz_post_type() {
        register_post_type('lms_quiz', array(
            'labels' => array(
                'name' => 'آزمون‌ها',
                'singular_name' => 'آزمون',
                'add_new' => 'افزودن آزمون جدید',
                'add_new_item' => 'افزودن آزمون جدید',
                'edit_item' => 'ویرایش آزمون',
                'view_item' => 'مشاهده آزمون',
                'search_items' => 'جستجوی آزمون‌ها',
                'not_found' => 'آزمونی یافت نشد',
                'menu_name' => 'آزمون‌ها'
            ),
            'public' => true,
            'show_in_menu' => 'edit.php?post_type=lms_course',
            'supports' => array('title', 'editor'),
            'menu_icon' => 'dashicons-welcome-learn-more'
        ));
    }

    public function add_quiz_meta_boxes() {
        add_meta_box(
            'quiz_settings',
            'تنظیمات آزمون',
            array($this, 'render_quiz_settings'),
            'lms_quiz',
            'normal',
            'high'
        );

        add_meta_box(
            'quiz_questions',
            'سوالات آزمون',
            array($this, 'render_quiz_questions'),
            'lms_quiz',
            'normal',
            'high'
        );
    }

    public function render_quiz_settings($post) {
        wp_nonce_field('save_quiz_settings', 'quiz_settings_nonce');

        $course_id = get_post_meta($post->ID, '_course_id', true);
        $time_limit = get_post_meta($post->ID, '_time_limit', true);
        $passing_grade = get_post_meta($post->ID, '_passing_grade', true);
        $attempts_allowed = get_post_meta($post->ID, '_attempts_allowed', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="course_id">دوره مربوطه</label></th>
                <td>
                    <select name="course_id" id="course_id">
                        <option value="">انتخاب دوره</option>
                        <?php
                        $courses = get_posts(array(
                            'post_type' => 'lms_course',
                            'posts_per_page' => -1
                        ));
                        foreach ($courses as $course) {
                            echo sprintf(
                                '<option value="%s" %s>%s</option>',
                                $course->ID,
                                selected($course_id, $course->ID, false),
                                $course->post_title
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="time_limit">محدودیت زمانی (دقیقه)</label></th>
                <td>
                    <input type="number" id="time_limit" name="time_limit" 
                           value="<?php echo esc_attr($time_limit); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="passing_grade">نمره قبولی (درصد)</label></th>
                <td>
                    <input type="number" id="passing_grade" name="passing_grade" 
                           value="<?php echo esc_attr($passing_grade); ?>" min="0" max="100">
                </td>
            </tr>
            <tr>
                <th><label for="attempts_allowed">تعداد دفعات مجاز</label></th>
                <td>
                    <input type="number" id="attempts_allowed" name="attempts_allowed" 
                           value="<?php echo esc_attr($attempts_allowed); ?>" min="1">
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_quiz_questions($post) {
        wp_nonce_field('save_quiz_questions', 'quiz_questions_nonce');

        $questions = get_post_meta($post->ID, '_questions', true);
        if (!is_array($questions)) {
            $questions = array();
        }
        ?>
        <div id="quiz-questions">
            <div class="questions-list">
                <?php foreach ($questions as $index => $question) : ?>
                    <div class="question-item" data-index="<?php echo $index; ?>">
                        <div class="question-header">
                            <span class="question-number"><?php echo $index + 1; ?></span>
                            <button type="button" class="remove-question button">حذف سوال</button>
                        </div>
                        <div class="question-content">
                            <p>
                                <label>متن سوال:</label>
                                <textarea name="questions[<?php echo $index; ?>][text]" rows="3" class="large-text"><?php echo esc_textarea($question['text']); ?></textarea>
                            </p>
                            <p>
                                <label>نوع سوال:</label>
                                <select name="questions[<?php echo $index; ?>][type]" class="question-type">
                                    <option value="multiple" <?php selected($question['type'], 'multiple'); ?>>چند گزینه‌ای</option>
                                    <option value="true_false" <?php selected($question['type'], 'true_false'); ?>>صحیح/غلط</option>
                                    <option value="descriptive" <?php selected($question['type'], 'descriptive'); ?>>تشریحی</option>
                                </select>
                            </p>
                            <div class="question-options" style="<?php echo $question['type'] === 'descriptive' ? 'display:none;' : ''; ?>">
                                <?php if ($question['type'] === 'multiple') : ?>
                                    <?php foreach ($question['options'] as $option_index => $option) : ?>
                                        <p>
                                            <label>گزینه <?php echo $option_index + 1; ?>:</label>
                                            <input type="text" name="questions[<?php echo $index; ?>][options][]" 
                                                   value="<?php echo esc_attr($option); ?>">
                                            <input type="radio" name="questions[<?php echo $index; ?>][correct]" 
                                                   value="<?php echo $option_index; ?>" 
                                                   <?php checked($question['correct'], $option_index); ?>>
                                            <label>پاسخ صحیح</label>
                                        </p>
                                    <?php endforeach; ?>
                                <?php elseif ($question['type'] === 'true_false') : ?>
                                    <p>
                                        <input type="radio" name="questions[<?php echo $index; ?>][correct]" 
                                               value="true" <?php checked($question['correct'], 'true'); ?>>
                                        <label>صحیح</label>
                                        <input type="radio" name="questions[<?php echo $index; ?>][correct]" 
                                               value="false" <?php checked($question['correct'], 'false'); ?>>
                                        <label>غلط</label>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <p>
                                <label>نمره سوال:</label>
                                <input type="number" name="questions[<?php echo $index; ?>][score]" 
                                       value="<?php echo esc_attr($question['score']); ?>" min="0">
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button-primary add-question">افزودن سوال جدید</button>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // اضافه کردن سوال جدید
            $('.add-question').on('click', function() {
                var index = $('.question-item').length;
                var template = `
                    <div class="question-item" data-index="${index}">
                        <div class="question-header">
                            <span class="question-number">${index + 1}</span>
                            <button type="button" class="remove-question button">حذف سوال</button>
                        </div>
                        <div class="question-content">
                            <p>
                                <label>متن سوال:</label>
                                <textarea name="questions[${index}][text]" rows="3" class="large-text"></textarea>
                            </p>
                            <p>
                                <label>نوع سوال:</label>
                                <select name="questions[${index}][type]" class="question-type">
                                    <option value="multiple">چند گزینه‌ای</option>
                                    <option value="true_false">صحیح/غلط</option>
                                    <option value="descriptive">تشریحی</option>
                                </select>
                            </p>
                            <div class="question-options">
                                <!-- گزینه‌ها بر اساس نوع سوال اضافه می‌شوند -->
                            </div>
                            <p>
                                <label>نمره سوال:</label>
                                <input type="number" name="questions[${index}][score]" value="1" min="0">
                            </p>
                        </div>
                    </div>
                `;
                $('.questions-list').append(template);
                updateQuestionNumbers();
            });

            // حذف سوال
            $(document).on('click', '.remove-question', function() {
                $(this).closest('.question-item').remove();
                updateQuestionNumbers();
            });

            // بروزرسانی شماره سوالات
            function updateQuestionNumbers() {
                $('.question-item').each(function(index) {
                    $(this).find('.question-number').text(index + 1);
                });
            }

            // تغییر نوع سوال
            $(document).on('change', '.question-type', function() {
                var $question = $(this).closest('.question-item');
                var type = $(this).val();
                var $options = $question.find('.question-options');
                
                $options.empty();

                if (type === 'multiple') {
                    var optionsHtml = '';
                    for (var i = 0; i < 4; i++) {
                        optionsHtml += `
                            <p>
                                <label>گزینه ${i + 1}:</label>
                                <input type="text" name="questions[${$question.data('index')}][options][]">
                                <input type="radio" name="questions[${$question.data('index')}][correct]" value="${i}">
                                <label>پاسخ صحیح</label>
                            </p>
                        `;
                    }
                    $options.html(optionsHtml).show();
                } else if (type === 'true_false') {
                    $options.html(`
                        <p>
                            <input type="radio" name="questions[${$question.data('index')}][correct]" value="true">
                            <label>صحیح</label>
                            <input type="radio" name="questions[${$question.data('index')}][correct]" value="false">
                            <label>غلط</label>
                        </p>
                    `).show();
                } else {
                    $options.hide();
                }
            });
        });
        </script>
        <?php
    }

    public function save_quiz_meta($post_id) {
        if (!isset($_POST['quiz_settings_nonce']) || 
            !wp_verify_nonce($_POST['quiz_settings_nonce'], 'save_quiz_settings')) {
            return;
        }

        if (!isset($_POST['quiz_questions_nonce']) || 
            !wp_verify_nonce($_POST['quiz_questions_nonce'], 'save_quiz_questions')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // ذخیره تنظیمات آزمون
        $settings = array(
            'course_id',
            'time_limit',
            'passing_grade',
            'attempts_allowed'
        );

        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_post_meta($post_id, '_' . $setting, sanitize_text_field($_POST[$setting]));
            }
        }

        // ذخیره سوالات
        if (isset($_POST['questions'])) {
            update_post_meta($post_id, '_questions', $_POST['questions']);
        }
    }

    // متدهای کمکی برای مدیریت آزمون
    public static function get_quiz_results($quiz_id, $user_id = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}lms_quiz_results WHERE quiz_id = %d";
        $params = array($quiz_id);
        
        if ($user_id) {
            $query .= " AND user_id = %d";
            $params[] = $user_id;
        }
        
        $query .= " ORDER BY completed_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    public static function submit_quiz($quiz_id, $user_id, $answers) {
        global $wpdb;
        
        $questions = get_post_meta($quiz_id, '_questions', true);
        $total_score = 0;
        $earned_score = 0;

        foreach ($questions as $question) {
            $total_score += $question['score'];
            
            if (isset($answers[$question['id']])) {
                if ($question['type'] === 'descriptive') {
                    // نمره سوالات تشریحی بعداً توسط مدرس تعیین می‌شود
                    continue;
                } elseif ($question['type'] === 'multiple' || $question['type'] === 'true_false') {
                    if ($answers[$question['id']] === $question['correct']) {
                        $earned_score += $question['score'];
                    }
                }
            }
        }

        $percentage = ($earned_score / $total_score) * 100;
        $passing_grade = get_post_meta($quiz_id, '_passing_grade', true);
        $status = $percentage >= $passing_grade ? 'passed' : 'failed';

        $result = $wpdb->insert(
            $wpdb->prefix . 'lms_quiz_results',
            array(
                'user_id' => $user_id,
                'quiz_id' => $quiz_id,
                'answers' => serialize($answers),
                'score' => $earned_score,
                'total_score' => $total_score,
                'percentage' => $percentage,
                'status' => $status,
                'completed_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s')
        );

        if ($result) {
            do_action('lms_quiz_completed', $quiz_id, $user_id, $status);
            return true;
        }

        return false;
    }
}

new Persian_LMS_Quiz();