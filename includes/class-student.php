<?php
class Persian_LMS_Student {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('show_user_profile', array($this, 'add_student_fields'));
        add_action('edit_user_profile', array($this, 'add_student_fields'));
        add_action('personal_options_update', array($this, 'save_student_fields'));
        add_action('edit_user_profile_update', array($this, 'save_student_fields'));
    }

    public function init() {
        // اضافه کردن نقش دانشجو اگر وجود نداشته باشد
        if (!get_role('lms_student')) {
            add_role('lms_student', 'دانشجو', array(
                'read' => true,
                'take_courses' => true,
                'take_quizzes' => true
            ));
        }
    }

    public function add_student_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        ?>
        <h3>اطلاعات دانشجو</h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="phone">شماره تماس</label>
                </th>
                <td>
                    <input type="tel" name="phone" id="phone" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'phone', true)); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="education">تحصیلات</label>
                </th>
                <td>
                    <input type="text" name="education" id="education" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'education', true)); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="interests">علایق و زمینه‌های مورد علاقه</label>
                </th>
                <td>
                    <textarea name="interests" id="interests" rows="5" cols="30"><?php echo esc_textarea(get_user_meta($user->ID, 'interests', true)); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_student_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));
        update_user_meta($user_id, 'education', sanitize_text_field($_POST['education']));
        update_user_meta($user_id, 'interests', sanitize_textarea_field($_POST['interests']));
    }

    // متدهای کمکی برای مدیریت دانشجو

    public static function get_enrolled_courses($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, e.enrollment_date, e.status, e.completed_at 
             FROM {$wpdb->posts} c
             INNER JOIN {$wpdb->prefix}lms_enrollments e ON c.ID = e.course_id
             WHERE e.user_id = %d AND c.post_type = 'lms_course'
             ORDER BY e.enrollment_date DESC",
            $user_id
        ));
    }

    public static function get_course_certificates($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lms_certificates 
             WHERE user_id = %d 
             ORDER BY issue_date DESC",
            $user_id
        ));
    }

    public static function get_quiz_attempts($user_id, $quiz_id = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}lms_quiz_results WHERE user_id = %d";
        $params = array($user_id);
        
        if ($quiz_id) {
            $query .= " AND quiz_id = %d";
            $params[] = $quiz_id;
        }
        
        $query .= " ORDER BY started_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    public static function get_student_progress($user_id) {
        global $wpdb;
        
        // دریافت تمام دوره‌های دانشجو
        $courses = self::get_enrolled_courses($user_id);
        $progress = array();
        
        foreach ($courses as $course) {
            $progress[$course->ID] = array(
                'course' => $course,
                'progress' => Persian_LMS_Course::get_course_progress($course->ID, $user_id),
                'completed_lessons' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}lms_progress 
                     WHERE user_id = %d AND course_id = %d AND status = 'completed'",
                    $user_id,
                    $course->ID
                ))
            );
        }
        
        return $progress;
    }

    public static function get_student_achievements($user_id) {
        $achievements = array(
            'total_courses' => 0,
            'completed_courses' => 0,
            'total_quizzes' => 0,
            'passed_quizzes' => 0,
            'certificates' => 0,
            'total_time' => 0
        );
        
        global $wpdb;
        
        // تعداد کل و دوره‌های تکمیل شده
        $course_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_courses,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_courses
             FROM {$wpdb->prefix}lms_enrollments
             WHERE user_id = %d",
            $user_id
        ));
        
        $achievements['total_courses'] = $course_stats->total_courses;
        $achievements['completed_courses'] = $course_stats->completed_courses;
        
        // آمار آزمون‌ها
        $quiz_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_quizzes,
                SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed_quizzes
             FROM {$wpdb->prefix}lms_quiz_results
             WHERE user_id = %d",
            $user_id
        ));
        
        $achievements['total_quizzes'] = $quiz_stats->total_quizzes;
        $achievements['passed_quizzes'] = $quiz_stats->passed_quizzes;
        
        // تعداد گواهینامه‌ها
        $achievements['certificates'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lms_certificates WHERE user_id = %d",
            $user_id
        ));
        
        // محاسبه کل زمان صرف شده
        $total_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(TIME_TO_SEC(TIMEDIFF(completed_at, started_at))) as total_time
             FROM {$wpdb->prefix}lms_progress
             WHERE user_id = %d AND status = 'completed'",
            $user_id
        ));
        
        $achievements['total_time'] = $total_time ? round($total_time / 3600, 1) : 0; // تبدیل به ساعت
        
        return $achievements;
    }

    public static function update_student_settings($user_id, $settings) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        $allowed_settings = array(
            'email_notifications',
            'course_reminders',
            'quiz_reminders',
            'newsletter_subscription',
            'profile_visibility'
        );

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_settings)) {
                update_user_meta($user_id, '_student_' . $key, sanitize_text_field($value));
            }
        }

        return true;
    }
}

new Persian_LMS_Student();