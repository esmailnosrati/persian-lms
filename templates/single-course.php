<?php
get_header();

while (have_posts()) : the_post();
    $course_id = get_the_ID();
    $price = get_post_meta($course_id, '_course_price', true);
    $duration = get_post_meta($course_id, '_course_duration', true);
    $instructor_id = get_post_field('post_author', $course_id);
    $instructor = get_userdata($instructor_id);
    $curriculum = get_post_meta($course_id, '_course_curriculum', true);
    $students = Persian_LMS_Course::get_enrolled_students($course_id);
    $rating = Persian_LMS_Course::get_course_rating($course_id);
    ?>

    <div class="single-course-wrapper">
        <div class="course-header">
            <div class="container">
                <div class="course-info">
                    <h1 class="course-title"><?php the_title(); ?></h1>
                    
                    <div class="course-meta">
                        <span class="instructor">
                            <i class="fas fa-chalkboard-teacher"></i>
                            مدرس: <?php echo $instructor->display_name; ?>
                        </span>
                        <span class="duration">
                            <i class="fas fa-clock"></i>
                            مدت دوره: <?php echo $duration; ?> ساعت
                        </span>
                        <span class="students">
                            <i class="fas fa-users"></i>
                            <?php echo count($students); ?> دانشجو
                        </span>
                        <?php if ($rating) : ?>
                            <span class="rating">
                                <i class="fas fa-star"></i>
                                <?php echo $rating; ?> از 5
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="course-thumbnail">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            </div>
        </div>

        <div class="course-content">
            <div class="container">
                <div class="course-main">
                    <div class="course-description">
                        <h3>توضیحات دوره</h3>
                        <?php the_content(); ?>
                    </div>

                    <?php if ($curriculum && is_array($curriculum)) : ?>
                        <div class="course-curriculum">
                            <h3>سرفصل‌های دوره</h3>
                            <div class="curriculum-sections">
                                <?php foreach ($curriculum as $section) : ?>
                                    <div class="curriculum-section">
                                        <h4 class="section-title"><?php echo $section['title']; ?></h4>
                                        <?php if (isset($section['items']) && is_array($section['items'])) : ?>
                                            <div class="section-lessons">
                                                <?php foreach ($section['items'] as $item) : ?>
                                                    <div class="lesson-item">
                                                        <span class="lesson-title">
                                                            <i class="fas fa-<?php echo $item['type'] === 'quiz' ? 'question-circle' : 'play-circle'; ?>"></i>
                                                            <?php echo $item['title']; ?>
                                                        </span>
                                                        <span class="lesson-meta">
                                                            <?php echo $item['duration']; ?> دقیقه
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="course-requirements">
                        <h3>پیش‌نیازها</h3>
                        <?php
                        $prerequisites = get_post_meta($course_id, '_course_prerequisites', true);
                        if ($prerequisites) {
                            echo '<ul>';
                            $prerequisites = explode("\n", $prerequisites);
                            foreach ($prerequisites as $prerequisite) {
                                echo '<li>' . esc_html($prerequisite) . '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p>این دوره پیش‌نیاز خاصی ندارد.</p>';
                        }
                        ?>
                    </div>

                    <?php if (comments_open()) : ?>
                        <div class="course-reviews">
                            <h3>نظرات دانشجویان</h3>
                            <?php comments_template(); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="course-sidebar">
                    <div class="course-card">
                        <div class="price-box">
                            <?php if ($price > 0) : ?>
                                <div class="course-price">
                                    <?php echo number_format($price); ?> تومان
                                </div>
                            <?php else : ?>
                                <div class="course-price free">
                                    رایگان!
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php
                        // بررسی وضعیت ثبت‌نام کاربر
                        $user_id = get_current_user_id();
                        global $wpdb;
                        $enrollment = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}lms_enrollments 
                             WHERE user_id = %d AND course_id = %d",
                            $user_id,
                            $course_id
                        ));

                        if ($enrollment) {
                            if ($enrollment->status === 'active') {
                                $progress = Persian_LMS_Course::get_course_progress($course_id, $user_id);
                                ?>
                                <div class="enrolled">
                                    <a href="<?php echo home_url('student-dashboard/?course=' . $course_id); ?>" class="button button-primary">
                                        ادامه دوره (<?php echo round($progress); ?>٪)
                                    </a>
                                </div>
                                <?php
                            } else {
                                ?>
                                <div class="pending">
                                    <p>ثبت‌نام شما در حال بررسی است.</p>
                                </div>
                                <?php
                            }
                        } else {
                            ?>
                            <form method="post" class="enroll-form">
                                <?php wp_nonce_field('enroll_course', 'enroll_nonce'); ?>
                                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <button type="submit" name="enroll_course" class="button button-primary">
                                    ثبت‌نام در دوره
                                </button>
                            </form>
                            <?php
                        }
                        ?>

                        <div class="course-features">
                            <ul>
                                <li>
                                    <i class="fas fa-clock"></i>
                                    مدت دوره: <?php echo $duration; ?> ساعت
                                </li>
                                <li>
                                    <i class="fas fa-users"></i>
                                    تعداد دانشجویان: <?php echo count($students); ?> نفر
                                </li>
                                <?php
                                $certificate = get_post_meta($course_id, '_course_certificate', true);
                                if ($certificate === 'yes') :
                                    ?>
                                    <li>
                                        <i class="fas fa-certificate"></i>
                                        گواهی پایان دوره
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="instructor-card">
                        <h4>درباره مدرس</h4>
                        <div class="instructor-info">
                            <?php echo get_avatar($instructor_id, 100); ?>
                            <h5><?php echo $instructor->display_name; ?></h5>
                            <p><?php echo get_user_meta($instructor_id, 'description', true); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
endwhile;

get_footer();