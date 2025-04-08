<?php
class Persian_LMS_Lesson {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_lesson_meta_boxes'));
        add_action('save_post_lms_lesson', array($this, 'save_lesson_meta'));
        add_filter('template_include', array($this, 'load_lesson_template'));
    }

    public function add_lesson_meta_boxes() {
        add_meta_box(
            'lesson_details',
            'جزئیات درس',
            array($this, 'render_lesson_details'),
            'lms_lesson',
            'normal',
            'high'
        );

        add_meta_box(
            'lesson_content',
            'محتوای درس',
            array($this, 'render_lesson_content'),
            'lms_lesson',
            'normal',
            'high'
        );
    }

    public function render_lesson_details($post) {
        wp_nonce_field('save_lesson_details', 'lesson_details_nonce');

        $course_id = get_post_meta($post->ID, '_course_id', true);
        $duration = get_post_meta($post->ID, '_lesson_duration', true);
        $type = get_post_meta($post->ID, '_lesson_type', true);
        $free = get_post_meta($post->ID, '_lesson_free', true);
        
        // دریافت لیست دوره‌ها
        $courses = get_posts(array(
            'post_type' => 'lms_course',
            'posts_per_page' => -1
        ));
        ?>
        <table class="form-table">
            <tr>
                <th><label for="course_id">دوره مربوطه</label></th>
                <td>
                    <select name="course_id" id="course_id">
                        <option value="">انتخاب دوره</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo $course->ID; ?>" <?php selected($course_id, $course->ID); ?>>
                                <?php echo $course->post_title; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="lesson_duration">مدت زمان (دقیقه)</label></th>
                <td>
                    <input type="number" id="lesson_duration" name="lesson_duration" 
                           value="<?php echo esc_attr($duration); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="lesson_type">نوع درس</label></th>
                <td>
                    <select name="lesson_type" id="lesson_type">
                        <option value="video" <?php selected($type, 'video'); ?>>ویدیو</option>
                        <option value="text" <?php selected($type, 'text'); ?>>متن</option>
                        <option value="audio" <?php selected($type, 'audio'); ?>>صوت</option>
                        <option value="file" <?php selected($type, 'file'); ?>>فایل</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="lesson_free">درس رایگان</label></th>
                <td>
                    <input type="checkbox" id="lesson_free" name="lesson_free" 
                           value="yes" <?php checked($free, 'yes'); ?>>
                    <label for="lesson_free">این درس به صورت رایگان در دسترس است</label>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_lesson_content($post) {
        wp_nonce_field('save_lesson_content', 'lesson_content_nonce');

        $video_url = get_post_meta($post->ID, '_video_url', true);
        $audio_url = get_post_meta($post->ID, '_audio_url', true);
        $file_url = get_post_meta($post->ID, '_file_url', true);
        $type = get_post_meta($post->ID, '_lesson_type', true);
        ?>
        <div class="lesson-content-wrapper">
            <div class="lesson-type-content video-content" <?php echo $type !== 'video' ? 'style="display:none;"' : ''; ?>>
                <p>
                    <label for="video_url">آدرس ویدیو:</label><br>
                    <input type="text" id="video_url" name="video_url" 
                           value="<?php echo esc_url($video_url); ?>" class="large-text">
                    <button type="button" class="button upload-video">انتخاب ویدیو</button>
                </p>
            </div>

            <div class="lesson-type-content audio-content" <?php echo $type !== 'audio' ? 'style="display:none;"' : ''; ?>>
                <p>
                    <label for="audio_url">آدرس فایل صوتی:</label><br>
                    <input type="text" id="audio_url" name="audio_url" 
                           value="<?php echo esc_url($audio_url); ?>" class="large-text">
                    <button type="button" class="button upload-audio">انتخاب فایل صوتی</button>
                </p>
            </div>

            <div class="lesson-type-content file-content" <?php echo $type !== 'file' ? 'style="display:none;"' : ''; ?>>
                <p>
                    <label for="file_url">آدرس فایل:</label><br>
                    <input type="text" id="file_url" name="file_url" 
                           value="<?php echo esc_url($file_url); ?>" class="large-text">
                    <button type="button" class="button upload-file">انتخاب فایل</button>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // تغییر نمایش فرم‌ها بر اساس نوع درس
            $('#lesson_type').on('change', function() {
                var type = $(this).val();
                $('.lesson-type-content').hide();
                $('.' + type + '-content').show();
            });

            // آپلود مدیا
            $('.upload-video, .upload-audio, .upload-file').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var field = button.prev('input');
                
                var media = wp.media({
                    title: 'انتخاب فایل',
                    multiple: false
                }).on('select', function() {
                    var attachment = media.state().get('selection').first().toJSON();
                    field.val(attachment.url);
                }).open();
            });
        });
        </script>
        <?php
    }

    public function save_lesson_meta($post_id) {
        if (!isset($_POST['lesson_details_nonce']) || 
            !wp_verify_nonce($_POST['lesson_details_nonce'], 'save_lesson_details')) {
            return;
        }

        if (!isset($_POST['lesson_content_nonce']) || 
            !wp_verify_nonce($_POST['lesson_content_nonce'], 'save_lesson_content')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // ذخیره جزئیات درس
        $meta_fields = array(
            'course_id' => 'course_id',
            'lesson_duration' => '_lesson_duration',
            'lesson_type' => '_lesson_type',
            'lesson_free' => '_lesson_free'
        );

        foreach ($meta_fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta(
                    $post_id,
                    $meta_key,
                    sanitize_text_field($_POST[$field])
                );
            }
        }

        // ذخیره محتوای درس بر اساس نوع
        $type = $_POST['lesson_type'];
        
        switch ($type) {
            case 'video':
                if (isset($_POST['video_url'])) {
                    update_post_meta($post_id, '_video_url', esc_url_raw($_POST['video_url']));
                }
                break;
                
            case 'audio':
                if (isset($_POST['audio_url'])) {
                    update_post_meta($post_id, '_audio_url', esc_url_raw($_POST['audio_url']));
                }
                break;
                
            case 'file':
                if (isset($_POST['file_url'])) {
                    update_post_meta($post_id, '_file_url', esc_url_raw($_POST['file_url']));
                }
                break;
        }
    }

    public function load_lesson_template($template) {
        if (is_singular('lms_lesson')) {
            // بررسی دسترسی کاربر به درس
            if (!$this->can_view_lesson(get_the_ID())) {
                wp_redirect(home_url());
                exit;
            }

            $new_template = PERSIAN_LMS_PLUGIN_DIR . 'public/views/single-lesson.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    private function can_view_lesson($lesson_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $course_id = get_post_meta($lesson_id, '_course_id', true);
        $is_free = get_post_meta($lesson_id, '_lesson_free', true);

        if ($is_free === 'yes') {
            return true;
        }

        // بررسی اینکه آیا کاربر در دوره ثبت‌نام کرده است
        global $wpdb;
        $enrollment = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lms_enrollments
             WHERE user_id = %d AND course_id = %d AND status = 'active'",
            $user_id,
            $course_id
        ));

        return (bool) $enrollment;
    }
}

new Persian_LMS_Lesson();