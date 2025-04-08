<?php
// بررسی دسترسی کاربر
if (!is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

get_header();

$student_id = get_current_user_id();
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'courses';
?>

<div class="student-dashboard">
    <div class="container">
        <div class="dashboard-header">
            <div class="student-info">
                <?php echo get_avatar($student_id, 100); ?>
                <div class="info">
                    <h2><?php echo wp_get_current_user()->display_name; ?></h2>
                    <span class="role">دانشجو</span>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="dashboard-tabs">
                <a href="?tab=courses" class="<?php echo $current_tab === 'courses' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i> دوره‌های من
                </a>
                <a href="?tab=certificates" class="<?php echo $current_tab === 'certificates' ? 'active' : ''; ?>">
                    <i class="fas fa-certificate"></i> گواهینامه‌ها
                </a>
                <a href="?tab=profile" class="<?php echo $current_tab === 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user-edit"></i> ویرایش پروفایل
                </a>
            </div>

            <div class="dashboard-tab-content">
                <?php
                switch ($current_tab) {
                    case 'courses':
                        // نمایش دوره‌های دانشجو
                        global $wpdb;
                        $enrollments = $wpdb->get_results($wpdb->prepare(
                            "SELECT e.*, c.post_title 
                             FROM {$wpdb->prefix}lms_enrollments e
                             INNER JOIN {$wpdb->posts} c ON e.course_id = c.ID
                             WHERE e.user_id = %d
                             ORDER BY e.enrollment_date DESC",
                            $student_id
                        ));
                        ?>
                        <div class="student-courses">
                            <h3>دوره‌های من</h3>
                            <?php if ($enrollments) : ?>
                                <div class="courses-grid">
                                    <?php foreach ($enrollments as $enrollment) : 
                                        $progress = Persian_LMS_Course::get_course_progress($enrollment->course_id, $student_id);
                                        ?>
                                        <div class="course-card">
                                            <?php echo get_the_post_thumbnail($enrollment->course_id, 'thumbnail'); ?>
                                            <div class="course-info">
                                                <h4><?php echo get_the_title($enrollment->course_id); ?></h4>
                                                <div class="progress-bar">
                                                    <div class="progress" style="width: <?php echo $progress; ?>%"></div>
                                                    <span><?php echo round($progress); ?>% تکمیل شده</span>
                                                </div>
                                            </div>
                                            <div class="actions">
                                                <a href="<?php echo get_permalink($enrollment->course_id); ?>" class="button">
                                                    ادامه دوره
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="no-items">
                                    <p>شما هنوز در هیچ دوره‌ای ثبت‌نام نکرده‌اید.</p>
                                    <a href="<?php echo get_post_type_archive_link('lms_course'); ?>" class="button">
                                        مشاهده دوره‌ها
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;

                    case 'certificates':
                        // نمایش گواهینامه‌های دانشجو
                        global $wpdb;
                        $certificates = $wpdb->get_results($wpdb->prepare(
                            "SELECT c.*, co.post_title as course_title
                             FROM {$wpdb->prefix}lms_certificates c
                             INNER JOIN {$wpdb->posts} co ON c.course_id = co.ID
                             WHERE c.user_id = %d
                             ORDER BY c.issue_date DESC",
                            $student_id
                        ));
                        ?>
                        <div class="student-certificates">
                            <h3>گواهینامه‌های من</h3>
                            <?php if ($certificates) : ?>
                                <div class="certificates-grid">
                                    <?php foreach ($certificates as $certificate) : ?>
                                        <div class="certificate-card">
                                            <div class="certificate-preview">
                                                <i class="fas fa-certificate"></i>
                                            </div>
                                            <div class="certificate-info">
                                                <h4><?php echo $certificate->course_title; ?></h4>
                                                <div class="meta">
                                                    <span>
                                                        <i class="fas fa-calendar"></i>
                                                        <?php echo date_i18n('Y/m/d', strtotime($certificate->issue_date)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="actions">
                                                <a href="<?php echo $certificate->certificate_url; ?>" class="button" target="_blank">
                                                    <i class="fas fa-download"></i> دانلود گواهینامه
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="no-items">
                                    <p>شما هنوز گواهینامه‌ای دریافت نکرده‌اید.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;

                    case 'profile':
                        // فرم ویرایش پروفایل
                        $user = wp_get_current_user();
                        ?>
                        <div class="student-profile">
                            <h3>ویرایش پروفایل</h3>
                            <form method="post" class="profile-form" enctype="multipart/form-data">
                                <?php wp_nonce_field('update_student_profile', 'student_profile_nonce'); ?>
                                
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
                                    <label for="phone">شماره تماس</label>
                                    <input type="tel" id="phone" name="phone" 
                                           value="<?php echo esc_attr(get_user_meta($user->ID, 'phone', true)); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="bio">درباره من</label>
                                    <textarea id="bio" name="description" rows="5"><?php echo esc_textarea(get_user_meta($user->ID, 'description', true)); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="profile_image">تصویر پروفایل</label>
                                    <input type="file" id="profile_image" name="profile_image" accept="image/*">
                                </div>

                                <button type="submit" name="update_student_profile" class="button button-primary">
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