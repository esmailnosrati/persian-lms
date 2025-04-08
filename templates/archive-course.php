<?php
get_header();
?>

<div class="lms-courses-archive">
    <div class="container">
        <div class="courses-filters">
            <form id="course-filters" method="get">
                <!-- فیلترهای دوره -->
                <div class="filter-item">
                    <select name="course_category">
                        <option value="">همه دسته‌بندی‌ها</option>
                        <?php
                        $categories = get_terms(array(
                            'taxonomy' => 'course_category',
                            'hide_empty' => true
                        ));
                        foreach ($categories as $category) {
                            echo sprintf(
                                '<option value="%s" %s>%s</option>',
                                $category->slug,
                                selected($_GET['course_category'], $category->slug, false),
                                $category->name
                            );
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-item">
                    <select name="course_level">
                        <option value="">همه سطوح</option>
                        <?php
                        $levels = get_terms(array(
                            'taxonomy' => 'course_level',
                            'hide_empty' => true
                        ));
                        foreach ($levels as $level) {
                            echo sprintf(
                                '<option value="%s" %s>%s</option>',
                                $level->slug,
                                selected($_GET['course_level'], $level->slug, false),
                                $level->name
                            );
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-item">
                    <select name="price_type">
                        <option value="">همه قیمت‌ها</option>
                        <option value="free" <?php selected($_GET['price_type'], 'free'); ?>>رایگان</option>
                        <option value="paid" <?php selected($_GET['price_type'], 'paid'); ?>>غیر رایگان</option>
                    </select>
                </div>

                <button type="submit" class="button">اعمال فیلتر</button>
            </form>
        </div>

        <div class="courses-grid">
            <?php
            if (have_posts()) :
                while (have_posts()) : the_post();
                    ?>
                    <div class="course-card">
                        <div class="course-thumbnail">
                            <?php
                            if (has_post_thumbnail()) {
                                the_post_thumbnail('medium');
                            }
                            ?>
                        </div>
                        <div class="course-content">
                            <h3 class="course-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>
                            <div class="course-meta">
                                <?php
                                $price = get_post_meta(get_the_ID(), '_course_price', true);
                                $duration = get_post_meta(get_the_ID(), '_course_duration', true);
                                $students = Persian_LMS_Course::get_enrolled_students(get_the_ID());
                                ?>
                                <span class="price">
                                    <?php echo $price > 0 ? number_format($price) . ' تومان' : 'رایگان'; ?>
                                </span>
                                <span class="duration">
                                    <i class="fas fa-clock"></i> <?php echo $duration; ?> ساعت
                                </span>
                                <span class="students-count">
                                    <i class="fas fa-users"></i> <?php echo count($students); ?> دانشجو
                                </span>
                            </div>
                            <div class="course-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                endwhile;

                // نمایش pagination
                the_posts_pagination(array(
                    'mid_size' => 2,
                    'prev_text' => '&larr; قبلی',
                    'next_text' => 'بعدی &rarr;'
                ));
            else :
                ?>
                <div class="no-courses">
                    <p>دوره‌ای یافت نشد.</p>
                </div>
                <?php
            endif;
            ?>
        </div>
    </div>
</div>

<?php
get_footer();