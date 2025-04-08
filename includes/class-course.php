            case 'instructor':
                $instructor_id = get_post_field('post_author', $post_id);
                $instructor = get_userdata($instructor_id);
                echo $instructor ? $instructor->display_name : '---';
                break;
            
            case 'featured':
                $featured = get_post_meta($post_id, '_course_featured', true);
                echo $featured === 'yes' ? '<span class="dashicons dashicons-star-filled"></span>' : '—';
                break;
        }
    }

    // متدهای کمکی برای مدیریت دوره

    public static function get_enrolled_students($course_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.* FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}lms_enrollments e ON u.ID = e.user_id
             WHERE e.course_id = %d AND e.status = 'active'
             ORDER BY e.enrollment_date DESC",
            $course_id
        ));
    }

    public static function get_course_progress($course_id, $user_id) {
        global $wpdb;
        
        // تعداد کل درس‌های دوره
        $curriculum = get_post_meta($course_id, '_course_curriculum', true);
        $total_lessons = 0;
        
        if (is_array($curriculum)) {
            foreach ($curriculum as $section) {
                if (isset($section['items']) && is_array($section['items'])) {
                    $total_lessons += count($section['items']);
                }
            }
        }

        // تعداد درس‌های تکمیل شده
        $completed_lessons = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lms_progress
             WHERE user_id = %d AND course_id = %d AND status = 'completed'",
            $user_id,
            $course_id
        ));

        return $total_lessons > 0 ? ($completed_lessons / $total_lessons) * 100 : 0;
    }

    public static function enroll_student($course_id, $user_id, $payment_id = null) {
        global $wpdb;
        
        // بررسی محدودیت تعداد دانشجو
        $max_students = get_post_meta($course_id, '_course_max_students', true);
        $current_students = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lms_enrollments
             WHERE course_id = %d AND status = 'active'",
            $course_id
        ));

        if ($max_students && $current_students >= $max_students) {
            return new WP_Error('enrollment_limit', 'ظرفیت دوره تکمیل شده است.');
        }

        // بررسی ثبت‌نام قبلی
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lms_enrollments
             WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));

        if ($existing) {
            return new WP_Error('already_enrolled', 'شما قبلاً در این دوره ثبت‌نام کرده‌اید.');
        }

        // ثبت‌نام دانشجو
        $result = $wpdb->insert(
            $wpdb->prefix . 'lms_enrollments',
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'payment_id' => $payment_id,
                'status' => $payment_id ? 'pending' : 'active',
                'enrollment_date' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );

        if ($result) {
            // ارسال ایمیل خوش‌آمدگویی
            self::send_welcome_email($course_id, $user_id);
            
            // افزودن نقش دانشجو به کاربر
            $user = new WP_User($user_id);
            $user->add_role('lms_student');

            do_action('lms_after_enrollment', $course_id, $user_id, $payment_id);
            
            return true;
        }

        return new WP_Error('enrollment_failed', 'خطا در ثبت‌نام. لطفاً مجدداً تلاش کنید.');
    }

    private static function send_welcome_email($course_id, $user_id) {
        $course = get_post($course_id);
        $user = get_userdata($user_id);
        $instructor = get_userdata($course->post_author);

        $subject = sprintf('خوش آمدید به دوره %s', $course->post_title);
        
        $message = sprintf(
            'سلام %s عزیز،

به دوره %s خوش آمدید!
مدرس دوره: %s

برای شروع یادگیری به پنل دانشجویی خود مراجعه کنید:
%s

با آرزوی موفقیت،
%s',
            $user->display_name,
            $course->post_title,
            $instructor->display_name,
            get_permalink(get_page_by_path('student-dashboard')),
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }

    public static function complete_lesson($lesson_id, $user_id) {
        global $wpdb;
        
        $course_id = get_post_meta($lesson_id, '_course_id', true);
        
        // ثبت تکمیل درس
        $result = $wpdb->insert(
            $wpdb->prefix . 'lms_progress',
            array(
                'user_id' => $user_id,
                'lesson_id' => $lesson_id,
                'course_id' => $course_id,
                'status' => 'completed',
                'completion_date' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );

        if ($result) {
            // بررسی تکمیل دوره
            $progress = self::get_course_progress($course_id, $user_id);
            
            if ($progress >= 100) {
                // اعطای گواهی در صورت وجود
                if (get_post_meta($course_id, '_course_certificate', true) === 'yes') {
                    do_action('lms_generate_certificate', $course_id, $user_id);
                }
                
                // بروزرسانی وضعیت دوره
                $wpdb->update(
                    $wpdb->prefix . 'lms_enrollments',
                    array(
                        'status' => 'completed',
                        'completed_at' => current_time('mysql')
                    ),
                    array(
                        'user_id' => $user_id,
                        'course_id' => $course_id
                    ),
                    array('%s', '%s'),
                    array('%d', '%d')
                );

                do_action('lms_course_completed', $course_id, $user_id);
            }

            return true;
        }

        return false;
    }

    public static function get_course_rating($course_id) {
        $args = array(
            'post_id' => $course_id,
            'status' => 'approve',
            'type' => 'review'
        );

        $reviews = get_comments($args);
        $total_rating = 0;
        $count = 0;

        foreach ($reviews as $review) {
            $rating = get_comment_meta($review->comment_ID, 'rating', true);
            if ($rating) {
                $total_rating += $rating;
                $count++;
            }
        }

        return $count > 0 ? round($total_rating / $count, 1) : 0;
    }
}

new Persian_LMS_Course();