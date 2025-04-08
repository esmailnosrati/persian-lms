<?php
// بررسی دسترسی کاربر
if (!is_user_logged_in() || !current_user_can('lms_instructor')) {
    wp_redirect(home_url());
    exit;
}

get_header();

$instructor_id = get_current_user_id();
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'courses';
?>

<div class="instructor-dashboard">
    <div class="container">
        <div class="dashboard-header">
            <div class="instructor-info">
                <?php echo get_avatar($instructor_id, 100); ?>
                <div class="info">
                    <h2><?php echo wp_get_current_user()->display_name; ?></h2>
                    <span class="role">مدرس</span>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="dashboard-tabs">
                <a href="?tab=courses" class="<?php echo $current_tab === 'courses' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i> دوره‌های من
                </a>
                <a href="?tab=students" class="<?php echo $current_tab === 'students' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> دانشجویان
                </a>
                <a href="?tab=earnings" class="<?php echo $current_tab === 'earnings' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill"></i> درآمد
                </a>
                <a href="?tab=profile" class="<?php echo $current_tab === 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user-edit"></i> ویرایش پروفایل
                </a>
            </div>

            <div class="dashboard-tab-content">
                <?php
                switch ($current_tab) {
                    case 'courses':
                        // نمایش دوره‌های مدرس
                        $courses = get_posts(array(
                            'post_type' => 'lms_course',
                            'author' => $instructor_id,
                            'posts_per_page' => -1
                        ));
                        ?>
                        <div class="instructor-courses">
                            <div class="header-actions">
                                <h3>دوره‌های من</h3>
                                <a href="<?php echo admin_url('post-new.php?post_type=lms_course'); ?>" class="button">
                                    <i class="fas fa-plus"></i> افزودن دوره جدید
                                </a>
                            </div>

                            <?php if ($courses) : ?>
                                <div class="courses-grid">
                                    <?php foreach ($courses as $course) : 
                                        $students_count = count(Persian_LMS_Course::get_enrolled_students($course->ID));
                                        $total_earnings = 0; // محاسبه درآمد کل دوره
                                        ?>
                                        <div class="course-card">
                                            <?php echo get_the_post_thumbnail($course->ID, 'thumbnail'); ?>
                                            <div class="course-info">
                                                <h4><?php echo $course->post_title; ?></h4>
                                                <div class="meta">
                                                    <span><i class="fas fa-users"></i> <?php echo $students_count; ?> دانشجو</span>
                                                    <span><i class="fas fa-money-bill"></i> <?php echo number_format($total_earnings); ?> تومان</span>
                                                </div>
                                            </div>
                                            <div class="actions">
                                                <a href="<?php echo get_edit_post_link($course->ID); ?>" class="button">
                                                    <i class="fas fa-edit"></i> ویرایش
                                                </a>
                                                <a href="<?php echo get_permalink($course->ID); ?>" class="button">
                                                    <i class="fas fa-eye"></i> مشاهده
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="no-items">
                                    <p>شما هنوز دوره‌ای ایجاد نکرده‌اید.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;

                    case 'students':
                        // نمایش لیست دانشجویان مدرس
                        global $wpdb;
                        $students = $wpdb->get_results($wpdb->prepare(
                            "SELECT DISTINCT u.*, e.enrollment_date 
                             FROM {$wpdb->users} u
                             INNER JOIN {$wpdb->prefix}lms_enrollments e ON u.ID = e.user_id
                             INNER JOIN {$wpdb->posts} p ON e.course_id = p.ID
                             WHERE p.post_author = %d
                             ORDER BY e.enrollment_date DESC",
                            $instructor_id
                        ));
                        ?>
                        <div class="instructor-students">
                            <h3>دانشجویان من</h3>
                            <?php if ($students) : ?>
                                <table class="students-table">
                                    <thead>
                                        <tr>
                                            <th>نام دانشجو</th>
                                            <th>دوره</th>
                                            <th>تاریخ ثبت‌نام</th>
                                            <th>پیشرفت</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student) :
                                            $course_id = $wpdb->get_var($wpdb->prepare(
                                                "SELECT course_id FROM {$wpdb->prefix}lms_enrollments 
                                                 WHERE user_id = %d ORDER BY enrollment_date DESC LIMIT 1",
                                                $student->ID
                                            ));
                                            $progress = Persian_LMS_Course::get_course_progress($course_id, $student->ID);
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php echo get_avatar($student->ID, 30); ?>
                                                    <?php echo $student->display_name; ?>
                                                </td>
                                                <td><?php echo get_the_title($course_id); ?></td>
                                                <td><?php echo date_i18n('Y/m/d', strtotime($student->enrollment_date)); ?></td>
                                                <td>
                                                    <div class="progress-bar">
                                                        <div class="progress" style="width: <?php echo $progress; ?>%"></div>
                                                        <span><?php echo round($progress); ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <div class="no-items">
                                    <p>هنوز دانشجویی در دوره‌های شما ثبت‌نام نکرده است.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;

                    case 'earnings':
                        // نمایش گزارش درآمد
                        global $wpdb;
                        $earnings = $wpdb->get_results($wpdb->prepare(
                            "SELECT p.*, c.post_title as course_title
                             FROM {$wpdb->prefix}lms_payments p
                             INNER JOIN {$wpdb->posts} c ON p.course_id = c.ID
                             WHERE c.post_author = %d AND p.status = 'completed'
                             ORDER BY p.payment_date DESC",
                            $instructor_id
                        ));
                        ?>
                        <div class="instructor-earnings">
                            <h3>گزارش درآمد</h3>
                            <?php if ($earnings) : ?>
                                <div class="earnings-summary">
                                    <div class="summary-card">
                                        <h4>درآمد کل</h4>
                                        <span class="amount">
                                            <?php
                                            $total = array_sum(array_column($earnings, 'amount'));
                                            echo number_format($total);
                                            ?> تومان
                                        </span>
                                    </div>
                                    <div class="summary-card">
                                        <h4>درآمد ماه جاری</h4>
                                        <span class="amount">
                                            <?php
                                            $current_month = array_filter($earnings, function($e) {
                                                return date('Y-m', strtotime($e->payment_date)) === date('Y-m');
                                            });
                                            echo number_format(array_sum(array_column($current_month, 'amount')));
                                            ?> تومان
                                        </span>
                                    </div>
                                </div>

                                <table class="earnings-table">
                                    <thead>
                                        <tr>
                                            <th>دوره</th>
                                            <th>دانشجو</th>
                                            <th>مبلغ</th>
                                            <th>تاریخ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($earnings as $earning) : 
                                            $student = get_userdata($earning->user_id);
                                            ?>
                                            <tr>
                                                <td><?php echo $earning->course_title; ?></td>
                                                <td><?php echo $student->display_name; ?></td>
                                                <td><?php echo number_format($earning->amount); ?> تومان</td>
                                                <td><?php echo date_i18n('Y/m/d', strtotime($earning->payment_date)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <div class="no-items">
                                    <p>هنوز درآمدی ثبت نشده است.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;

                    case 'profile':
                        // فرم ویرایش پروفایل
                        $user = wp_get_current_user();
                        ?>
                        <div class="instructor-profile">
                            <h3>ویرایش پروفایل</h3>
                            <form method="post" class="profile-form" enctype="multipart/form-data">
                                <?php wp_nonce_field('update_instructor_profile', 'instructor_profile_nonce'); ?>
                                
                                <div class="form-group">
                                    <label for="display_name">نام نمایشی</label>
                                    <input type="text" id="display_name" name="display_name" 
                                           value="<?php echo esc_attr($user->display_name); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="email">ایمیل</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo esc_attr($user->user_email); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="bio">بیوگرافی</label>
                                    <textarea id="bio" name="description" rows="5"><?php echo esc_textarea(get_user_meta($user->ID, 'description', true)); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="expertise">تخصص‌ها</label>
                                    <input type="text" id="expertise" name="expertise" 
                                           value="<?php echo esc_attr(get_user_meta($user->ID, 'expertise', true)); ?>">
                                    <small>تخصص‌ها را با کاما از هم جدا کنید</small>
                                </div>

                                <div class="form-group">
                                    <label for="social_links">شبکه‌های اجتماعی</label>
                                    <input type="url" name="social_links[linkedin]" placeholder="لینکدین"
                                           value="<?php echo esc_url(get_user_meta($user->ID, 'linkedin', true)); ?>">
                                    <input type="url" name="social_links[twitter]" placeholder="توییتر"
                                           value="<?php echo esc_url(get_user_meta($user->ID, 'twitter', true)); ?>">
                                    <input type="url" name="social_links[website]" placeholder="وب‌سایت"
                                           value="<?php echo esc_url(get_user_meta($user->ID, 'website', true)); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="profile_image">تصویر پروفایل</label>
                                    <input type="file" id="profile_image" name="profile_image" accept="image/*">
                                </div>

                                <button type="submit" name="update_instructor_profile" class="button button-primary">
                                    بروزرسانی پروفایل
                                </button>
                            </form>
                        </div>
                        <?php
                        break;
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();