<?php
class Persian_LMS_Instructor {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('show_user_profile', array($this, 'add_instructor_fields'));
        add_action('edit_user_profile', array($this, 'add_instructor_fields'));
        add_action('personal_options_update', array($this, 'save_instructor_fields'));
        add_action('edit_user_profile_update', array($this, 'save_instructor_fields'));
        add_action('admin_menu', array($this, 'add_instructor_menu'));
        add_action('wp_ajax_instructor_application', array($this, 'handle_instructor_application'));
    }

    public function init() {
        // اضافه کردن نقش مدرس اگر وجود نداشته باشد
        if (!get_role('lms_instructor')) {
            add_role('lms_instructor', 'مدرس', array(
                'read' => true,
                'edit_posts' => true,
                'edit_published_posts' => true,
                'publish_posts' => true,
                'edit_courses' => true,
                'edit_lessons' => true,
                'manage_quizzes' => true
            ));
        }
    }

    public function add_instructor_fields($user) {
        if (!current_user_can('edit_user', $user->ID) && 
            !in_array('lms_instructor', $user->roles)) {
            return;
        }
        ?>
        <h3>اطلاعات مدرس</h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="expertise">تخصص‌ها</label>
                </th>
                <td>
                    <input type="text" name="expertise" id="expertise" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'expertise', true)); ?>" 
                           class="regular-text">
                    <p class="description">تخصص‌ها را با کاما از هم جدا کنید</p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="biography">بیوگرافی</label>
                </th>
                <td>
                    <?php
                    wp_editor(
                        get_user_meta($user->ID, 'biography', true),
                        'biography',
                        array(
                            'textarea_name' => 'biography',
                            'textarea_rows' => 10,
                            'media_buttons' => false
                        )
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="social_linkedin">LinkedIn</label>
                </th>
                <td>
                    <input type="url" name="social_linkedin" id="social_linkedin" 
                           value="<?php echo esc_url(get_user_meta($user->ID, 'social_linkedin', true)); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="social_website">وب‌سایت</label>
                </th>
                <td>
                    <input type="url" name="social_website" id="social_website" 
                           value="<?php echo esc_url(get_user_meta($user->ID, 'social_website', true)); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="payment_info">اطلاعات پرداخت</label>
                </th>
                <td>
                    <textarea name="payment_info" id="payment_info" rows="5" cols="30"><?php 
                        echo esc_textarea(get_user_meta($user->ID, 'payment_info', true)); 
                    ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_instructor_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        update_user_meta($user_id, 'expertise', sanitize_text_field($_POST['expertise']));
        update_user_meta($user_id, 'biography', wp_kses_post($_POST['biography']));
        update_user_meta($user_id, 'social_linkedin', esc_url_raw($_POST['social_linkedin']));
        update_user_meta($user_id, 'social_website', esc_url_raw($_POST['social_website']));
        update_user_meta($user_id, 'payment_info', sanitize_textarea_field($_POST['payment_info']));
    }

    public function add_instructor_menu() {
        add_submenu_page(
            'edit.php?post_type=lms_course',
            'درخواست‌های مدرسی',
            'درخواست‌های مدرسی',
            'manage_options',
            'instructor-applications',
            array($this, 'render_applications_page')
        );
    }

    public function render_applications_page() {
        global $wpdb;
        
        // مدیریت اکشن‌ها
        if (isset($_POST['action']) && check_admin_referer('instructor_application_action')) {
            $application_id = intval($_POST['application_id']);
            $action = $_POST['action'];
            
            switch ($action) {
                case 'approve':
                    $this->approve_application($application_id);
                    break;
                case 'reject':
                    $this->reject_application($application_id);
                    break;
            }
        }
        
        // دریافت لیست درخواست‌ها
        $applications = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}lms_instructor_applications 
             ORDER BY application_date DESC"
        );
        ?>
        <div class="wrap">
            <h1>درخواست‌های مدرسی</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>نام متقاضی</th>
                        <th>ایمیل</th>
                        <th>تخصص‌ها</th>
                        <th>تاریخ درخواست</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $application) : 
                        $user = get_user_by('id', $application->user_id);
                        if (!$user) continue;
                        ?>
                        <tr>
                            <td><?php echo $user->display_name; ?></td>
                            <td><?php echo $user->user_email; ?></td>
                            <td><?php echo esc_html($application->expertise); ?></td>
                            <td><?php echo date_i18n('Y/m/d H:i', strtotime($application->application_date)); ?></td>
                            <td><?php echo $this->get_status_label($application->status); ?></td>
                            <td>
                                <?php if ($application->status === 'pending') : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('instructor_application_action'); ?>
                                        <input type="hidden" name="application_id" value="<?php echo $application->id; ?>">
                                        <button type="submit" name="action" value="approve" class="button button-primary">
                                            تایید
                                        </button>
                                        <button type="submit" name="action" value="reject" class="button">
                                            رد
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function get_status_label($status) {
        $labels = array(
            'pending' => 'در انتظار بررسی',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده'
        );
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    public function handle_instructor_application() {
        check_ajax_referer('instructor_application', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('لطفاً ابتدا وارد سایت شوید.');
        }
        
        $user_id = get_current_user_id();
        
        // بررسی درخواست قبلی
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lms_instructor_applications 
             WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));
        
        if ($existing) {
            wp_send_json_error('شما قبلاً درخواست داده‌اید و درخواست شما در حال بررسی است.');
        }
        
        // ثبت درخواست جدید
        $result = $wpdb->insert(
            $wpdb->prefix . 'lms_instructor_applications',
            array(
                'user_id' => $user_id,
                'expertise' => sanitize_text_field($_POST['expertise']),
                'resume' => wp_kses_post($_POST['resume']),
                'application_date' => current_time('mysql'),
                'status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // ارسال ایمیل به مدیر
            $admin_email = get_option('admin_email');
            $subject = 'درخواست مدرسی جدید';
            $user = wp_get_current_user();
            $message = sprintf(
                'یک درخواست مدرسی جدید از طرف %s ثبت شده است.
                 برای بررسی درخواست به پنل مدیریت مراجعه کنید:
                 %s',
                $user->display_name,
                admin_url('edit.php?post_type=lms_course&page=instructor-applications')
            );
            wp_mail($admin_email, $subject, $message);
            
            wp_send_json_success('درخواست شما با موفقیت ثبت شد.');
        }
        
        wp_send_json_error('خطا در ثبت درخواست. لطفاً مجدداً تلاش کنید.');
    }

    private function approve_application($application_id) {
        global $wpdb;
        
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lms_instructor_applications WHERE id = %d",
            $application_id
        ));
        
        if (!$application) {
            return false;
        }
        
        // بروزرسانی وضعیت درخواست
        $wpdb->update(
            $wpdb->prefix . 'lms_instructor_applications',
            array('status' => 'approved'),
            array('id' => $application_id),
            array('%s'),
            array('%d')
        );
        
        // افزودن نقش مدرس به کاربر
        $user = new WP_User($application->user_id);
        $user->add_role('lms_instructor');
        
        // ذخیره اطلاعات مدرس
        update_user_meta($application->user_id, 'expertise', $application->expertise);
        
        // ارسال ایمیل به متقاضی
        $subject = 'تایید درخواست مدرسی';
        $message = sprintf(
            'درخواست مدرسی شما تایید شد.
             اکنون می‌توانید وارد پنل مدرسی خود شوید:
             %s',
            home_url('instructor-dashboard')
        );
        $user = get_userdata($application->user_id);
        wp_mail($user->user_email, $subject, $message);
        
        return true;
    }

    private function reject_application($application_id) {
        global $wpdb;
        
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lms_instructor_applications WHERE id = %d",
            $application_id
        ));
        
        if (!$application) {
            return false;
        }
        
        // بروزرسانی وضعیت درخواست
        $wpdb->update(
            $wpdb->prefix . 'lms_instructor_applications',
            array('status' => 'rejected'),
            array('id' => $application_id),
            array('%s'),
            array('%d')
        );
        
        // ارسال ایمیل به متقاضی
        $subject = 'نتیجه درخواست مدرسی';
        $message = 'متاسفانه درخواست مدرسی شما در حال حاضر مورد تایید قرار نگرفت.';
        $user = get_userdata($application->user_id);
        wp_mail($user->user_email, $subject, $message);
        
        return true;
    }

    // متدهای کمکی برای مدیریت مدرس

    public static function get_instructor_courses($instructor_id) {
        return get_posts(array(
            'post_type' => 'lms_course',
            'author' => $instructor_id,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending')
        ));
    }

    public static function get_instructor_students($instructor_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.* 
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}lms_enrollments e ON u.ID = e.user_id
             INNER JOIN {$wpdb->posts} p ON e.course_id = p.ID
             WHERE p.post_author = %d
             ORDER BY u.display_name",
            $instructor_id
        ));
    }

    public static function get_instructor_earnings($instructor_id, $period = 'all') {
        global $wpdb;
        
        $query = "SELECT SUM(amount) as total 
                 FROM {$wpdb->prefix}lms_payments p
                 INNER JOIN {$wpdb->posts} c ON p.course_id = c.ID
                 WHERE c.post_author = %d AND p.status = 'completed'";
        
        if ($period === 'month') {
            $query .= " AND MONTH(p.payment_date) = MONTH(CURRENT_DATE())
                       AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())";
        } elseif ($period === 'year') {
            $query .= " AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())";
        }
        
        return $wpdb->get_var($wpdb->prepare($query, $instructor_id)) ?: 0;
    }

    public static function get_instructor_reviews($instructor_id) {
        global $wpdb;
        
        $courses = self::get_instructor_courses($instructor_id);
        if (empty($courses)) {
            return array();
        }
        
        $course_ids = wp_list_pluck($courses, 'ID');
        $course_ids = implode(',', $course_ids);
        
        return $wpdb->get_results(
            "SELECT c.*, p.post_title as course_title
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
             WHERE c.comment_post_ID IN ($course_ids)
             AND c.comment_type = 'review'
             ORDER BY c.comment_date DESC"
        );
    }

    public static function get_instructor_rating($instructor_id) {
        $courses = self::get_instructor_courses($instructor_id);
        if (empty($courses)) {
            return 0;
        }
        
        $total_rating = 0;
        $count = 0;
        
        foreach ($courses as $course) {
            $rating = Persian_LMS_Course::get_course_rating($course->ID);
            if ($rating > 0) {
                $total_rating += $rating;
                $count++;
            }
        }
        
        return $count > 0 ? round($total_rating / $count, 1) : 0;
    }
}

new Persian_LMS_Instructor();