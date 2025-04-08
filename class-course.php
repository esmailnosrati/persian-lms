<?php
/**
 * Course Management Class
 */
class Persian_LMS_Course {
    // Constructor
    public function __construct() {
        // اضافه کردن ستون‌های سفارشی به لیست دوره‌ها
        add_filter('manage_course_posts_columns', array($this, 'add_course_columns'));
        add_action('manage_course_posts_custom_column', array($this, 'manage_course_columns'), 10, 2);
    }

    // متد‌های موجود در کلاس...
    public function add_course_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['instructor'] = 'مدرس';
                $new_columns['featured'] = 'ویژه';
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }

    // سایر متدهای کلاس بدون تغییر...
    // [بقیه کد شما بدون تغییر]
}

// Initialize the class
new Persian_LMS_Course();