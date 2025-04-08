<?php
class Persian_LMS_Notification {
    public function __construct() {
        add_action('lms_after_enrollment', array($this, 'notify_enrollment'), 10, 3);
        add_action('lms_course_completed', array($this, 'notify_course_completion'), 10, 2);
        add_action('lms_quiz_completed', array($this, 'notify_quiz_completion'), 10, 3);
        add_action('lms_generate_certificate', array($this, 'notify_certificate_generation'), 10, 2);
        add_action('comment_post', array($this, 'notify_new_course_review'), 10, 3);
        
        // ایجاد صف اعلان‌ها
        add_action('wp_ajax_lms_get_notifications', array($this, 'get_user_notifications'));
        add_action('wp_ajax_lms_mark_notification_read', array($this, 'mark_notification_read'));
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lms_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            reference_id bigint(20),
            reference_type varchar(50),
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * اضافه کردن اعلان جدید
     */
    public function add_notification($user_id, $type, $title, $message, $reference_id = null, $reference_type = null) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'lms_notifications',
            array(
                'user_id' => $user_id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'reference_id' => $reference_id,
                'reference_type' => $reference_type,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        if ($result) {
            $this->send_email_notification($user_id, $title, $message);
            do_action('lms_after_notification', $user_id, $type, $reference_id);
            return true;
        }

        return false;
    }

    /**
     * ارسال ایمیل اعلان
     */
    private function send_email_notification($user_id, $title, $message) {
        $user = get_userdata($user_id);
        if (!$user) return;

        // بررسی تنظیمات اعلان کاربر
        $email_notifications = get_user_meta($user_id, '_student_email_notifications', true);
        if ($email_notifications === 'disabled') return;

        $site_name = get_bloginfo('name');
        $email_message = sprintf(
            '%s

%s

---
%s
برای تغییر تنظیمات اعلان‌ها به پروفایل خود مراجعه کنید.
',
            $title,
            $message,
            $site_name
        );

        wp_mail($user->user_email, $title, $email_message);
    }

    /**
     * اعلان ثبت‌نام در دوره
     */
    public function notify_enrollment($course_id, $user_id, $payment_id) {
        $course = get_post($course_id);
        $instructor_id = $course->post_author;

        // اعلان به دانشجو
        $this->add_notification(
            $user_id,
            'enrollment',
            'ثبت‌نام موفق در دوره',
            sprintf('شما با موفقیت در دوره "%s" ثبت‌نام کردید.', $course->post_title),
            $course_id,
            'course'
        );

        // اعلان به مدرس
        $this->add_notification(
            $instructor_id,
            'new_student',
            'دانشجوی جدید',
            sprintf('یک دانشجوی جدید در دوره "%s" ثبت‌نام کرد.', $course->post_title),
            $course_id,
            'course'
        );
    }

    /**
     * اعلان تکمیل دوره
     */
    public function notify_course_completion($course_id, $user_id) {
        $course = get_post($course_id);
        $instructor_id = $course->post_author;

        // اعلان به دانشجو
        $this->add_notification(
            $user_id,
            'course_completed',
            'تبریک! دوره را تکمیل کردید',
            sprintf('شما با موفقیت دوره "%s" را به پایان رساندید.', $course->post_title),
            $course_id,
            'course'
        );

        // اعلان به مدرس
        $this->add_notification(
            $instructor_id,
            'student_completed',
            'تکمیل دوره توسط دانشجو',
            sprintf('یک دانشجو دوره "%s" را تکمیل کرد.', $course->post_title),
            $course_id,
            'course'
        );
    }

    /**
     * اعلان تکمیل آزمون
     */
    public function notify_quiz_completion($quiz_id, $user_id, $status) {
        $quiz = get_post($quiz_id);
        $course_id = get_post_meta($quiz_id, '_course_id', true);
        $course = get_post($course_id);
        $instructor_id = $course->post_author;

        // اعلان به دانشجو
        $title = $status === 'passed' ? 'تبریک! در آزمون قبول شدید' : 'نتیجه آزمون';
        $message = sprintf(
            'شما آزمون "%s" از دوره "%s" را %s.',
            $quiz->post_title,
            $course->post_title,
            $status === 'passed' ? 'با موفقیت گذراندید' : 'نگذراندید'
        );

        $this->add_notification(
            $user_id,
            'quiz_completed',
            $title,
            $message,
            $quiz_id,
            'quiz'
        );

        // اعلان به مدرس
        $this->add_notification(
            $instructor_id,
            'student_quiz_completed',
            'تکمیل آزمون توسط دانشجو',
            sprintf(
                'یک دانشجو آزمون "%s" از دوره "%s" را تکمیل کرد.',
                $quiz->post_title,
                $course->post_title
            ),
            $quiz_id,
            'quiz'
        );
    }

    /**
     * اعلان صدور گواهینامه
     */
    public function notify_certificate_generation($course_id, $user_id) {
        $course = get_post($course_id);
        
        $this->add_notification(
            $user_id,
            'certificate_generated',
            'گواهینامه دوره صادر شد',
            sprintf(
                'گواهینامه دوره "%s" برای شما صادر شد. برای دانلود به پنل دانشجویی مراجعه کنید.',
                $course->post_title
            ),
            $course_id,
            'course'
        );
    }

    /**
     * اعلان نظر جدید برای دوره
     */
    public function notify_new_course_review($comment_ID, $comment_approved, $commentdata) {
        if ($commentdata['comment_type'] !== 'review') return;

        $course_id = $commentdata['comment_post_ID'];
        $course = get_post($course_id);
        $instructor_id = $course->post_author;

        $this->add_notification(
            $instructor_id,
            'new_review',
            'نظر جدید برای دوره',
            sprintf('یک نظر جدید برای دوره "%s" ثبت شد.', $course->post_title),
            $course_id,
            'course'
        );
    }

    /**
     * دریافت اعلان‌های کاربر
     */
    public function get_user_notifications() {
        check_ajax_referer('lms_notifications', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('شما دسترسی ندارید.');
        }

        global $wpdb;
        
        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lms_notifications 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 20",
            $user_id
        ));

        $formatted_notifications = array();
        foreach ($notifications as $notification) {
            $formatted_notifications[] = array(
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'is_read' => (bool) $notification->is_read,
                'created_at' => human_time_diff(
                    strtotime($notification->created_at),
                    current_time('timestamp')
                ) . ' پیش',
                'link' => $this->get_notification_link($notification)
            );
        }

        wp_send_json_success($formatted_notifications);
    }

    /**
     * علامت‌گذاری اعلان به عنوان خوانده شده
     */
    public function mark_notification_read() {
        check_ajax_referer('lms_notifications', 'nonce');
        
        $user_id = get_current_user_id();
        $notification_id = intval($_POST['notification_id']);

        if (!$user_id) {
            wp_send_json_error('شما دسترسی ندارید.');
        }

        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'lms_notifications',
            array('is_read' => 1),
            array(
                'id' => $notification_id,
                'user_id' => $user_id
            ),
            array('%d'),
            array('%d', '%d')
        );

        if ($result) {
            wp_send_json_success('اعلان خوانده شد.');
        } else {
            wp_send_json_error('خطا در بروزرسانی وضعیت اعلان.');
        }
    }

    /**
     * دریافت لینک مربوط به اعلان
     */
    private function get_notification_link($notification) {
        $link = '';

        switch ($notification->type) {
            case 'enrollment':
            case 'course_completed':
                $link = get_permalink($notification->reference_id);
                break;

            case 'quiz_completed':
                $course_id = get_post_meta($notification->reference_id, '_course_id', true);
                $link = add_query_arg('quiz', $notification->reference_id, get_permalink($course_id));
                break;

            case 'certificate_generated':
                $link = home_url('student-dashboard/?tab=certificates');
                break;

            case 'new_student':
            case 'student_completed':
            case 'student_quiz_completed':
                $link = home_url('instructor-dashboard/?tab=students');
                break;

            case 'new_review':
                $link = get_permalink($notification->reference_id) . '#reviews';
                break;
        }

        return $link;
    }

    /**
     * دریافت تعداد اعلان‌های نخوانده
     */
    public static function get_unread_count($user_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lms_notifications 
             WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }
}

new Persian_LMS_Notification();