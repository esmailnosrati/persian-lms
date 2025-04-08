<?php
get_header();

while (have_posts()) : the_post();
    $lesson_id = get_the_ID();
    $course_id = get_post_meta($lesson_id, '_course_id', true);
    $type = get_post_meta($lesson_id, '_lesson_type', true);
    ?>

    <div class="single-lesson-wrapper">
        <div class="lesson-header">
            <div class="container">
                <div class="course-navigation">
                    <a href="<?php echo get_permalink($course_id); ?>" class="back-to-course">
                        <i class="fas fa-arrow-left"></i>
                        برگشت به دوره
                    </a>
                </div>

                <h1 class="lesson-title"><?php the_title(); ?></h1>
            </div>
        </div>

        <div class="lesson-content">
            <div class="container">
                <div class="lesson-main">
                    <?php if ($type === 'video') : ?>
                        <div class="video-wrapper">
                            <?php
                            $video_url = get_post_meta($lesson_id, '_video_url', true);
                            if ($video_url) {
                                // نمایش ویدیو با استفاده از wp_video_shortcode
                                echo wp_video_shortcode(array(
                                    'src' => $video_url,
                                    'width' => '100%',
                                    'height' => 'auto'
                                ));
                            }
                            ?>
                        </div>
                    <?php elseif ($type === 'audio') : ?>
                        <div class="audio-wrapper">
                            <?php
                            $audio_url = get_post_meta($lesson_id, '_audio_url', true);
                            if ($audio_url) {
                                echo wp_audio_shortcode(array(
                                    'src' => $audio_url
                                ));
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="lesson-description">
                        <?php the_content(); ?>
                    </div>

                    <?php if ($type === 'file') : ?>
                        <div class="file-download">
                            <?php
                            $file_url = get_post_meta($lesson_id, '_file_url', true);
                            if ($file_url) {
                                echo '<a href="' . esc_url($file_url) . '" class="button" download>
                                    <i class="fas fa-download"></i> دانلود فایل
                                </a>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // دکمه تکمیل درس
                    $user_id = get_current_user_id();
                    global $wpdb;
                    $completed = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}lms_progress
                         WHERE user_id = %d AND lesson_id = %d AND status = 'completed'",
                        $user_id,
                        $lesson_id
                    ));

                    if (!$completed) :
                        ?>
                        <form method="post" class="complete-lesson-form">
                            <?php wp_nonce_field('complete_lesson', 'complete_nonce'); ?>
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>">
                            <button type="submit" name="complete_lesson" class="button button-primary">
                                تکمیل درس
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="lesson-sidebar">
                    <?php
                    // نمایش لیست درس‌های دوره
                    $curriculum = get_post_meta($course_id, '_course_curriculum', true);
                    if ($curriculum && is_array($curriculum)) :
                        ?>
                        <div class="course-lessons">
                            <h3>درس‌های دوره</h3>
                            <?php
                            foreach ($curriculum as $section) :
                                ?>
                                <div class="section">
                                    <h4><?php echo $section['title']; ?></h4>
                                    <?php
                                    if (isset($section['items']) && is_array($section['items'])) :
                                        ?>
                                        <ul class="lessons-list">
                                            <?php
                                            foreach ($section['items'] as $item) :
                                                $item_completed = $wpdb->get_var($wpdb->prepare(
                                                    "SELECT id FROM {$wpdb->prefix}lms_progress
                                                     WHERE user_id = %d AND lesson_id = %d AND status = 'completed'",
                                                    $user_id,
                                                    $item['id']
                                                ));
                                                ?>
                                                <li class="<?php echo $item_completed ? 'completed' : ''; ?>">
                                                    <a href="<?php echo get_permalink($item['id']); ?>">
                                                        <?php if ($item_completed) : ?>
                                                            <i class="fas fa-check-circle"></i>
                                                        <?php else : ?>
                                                            <i class="fas fa-circle"></i>
                                                        <?php endif; ?>
                                                        <?php echo $item['title']; ?>
                                                    </a>
                                                </li>
                                                <?php
                                            endforeach;
                                            ?>
                                        </ul>
                                        <?php
                                    endif;
                                    ?>
                                </div>
                                <?php
                            endforeach;
                            ?>
                        </div>
                        <?php
                    endif;
                    ?>
                </div>
            </div>
        </div>
    </div>

<?php
endwhile;

get_footer();