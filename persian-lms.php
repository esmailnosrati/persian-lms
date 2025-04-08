<?php
/*
Plugin Name: سیستم مدیریت آموزش فارسی
Plugin URI: https://github.com/esmailnosrati/persian-lms
Description: سیستم جامع مدیریت آموزش آنلاین با امکانات کامل
Version: 1.0.0
Author: esmailnosrati
Author URI: https://github.com/esmailnosrati
License: GPL v2 or later
Text Domain: persian-lms
*/

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    die('دسترسی مستقیم مجاز نیست!');
}

// تعریف ثابت‌های پلاگین
define('PERSIAN_LMS_VERSION', '1.0.0');
define('PERSIAN_LMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PERSIAN_LMS_PLUGIN_URL', plugin_dir_url(__FILE__));

class Persian_LMS {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->setup_hooks();
    }

    private function load_dependencies() {
        // Core Classes
        require_once PERSIAN_LMS_PLUGIN_DIR . 'includes/class-course.php';
        require_once PERSIAN_LMS_PLUGIN_DIR . 'includes/class-lesson.php';
        require_once PERSIAN_LMS_PLUGIN_DIR . 'includes/class-quiz.php';
        require_once PERSIAN_LMS_PLUGIN_DIR . 'includes/class-student.php';
        require_once PERSIAN_LMS_PLUGIN_DIR . 'includes/class-instructor.php';
        require_once PERSIAN_LMS_PLUGIN_DIR . 'includes/class-notification.php';
        require_once PERSIAN_LMS_PLUGIN_DIR . 'includes/class-certificate.php';
        require_once PERSIAN_LMS_PLUGIN_DIR . 'includes/class-payment.php';

        // Admin
        if (is_admin()) {
            require_once PERSIAN_LMS_PLUGIN_DIR . 'admin/class-admin.php';
            require_once PERSIAN_LMS_PLUGIN_DIR . 'admin/class-reports.php';
        }
    }

    private function setup_hooks() {
        // Activation Hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Init Hooks
        add_action('init', array($this, 'init'));
        
        // Scripts and Styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function activate() {
        // ایجاد جداول مورد نیاز
        $this->create_tables();
        
        // ایجاد نقش‌های کاربری
        $this->create_roles();
        
        // ایجاد صفحات پیش‌فرض
        $this->create_pages();
        
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // جدول ثبت‌نام دوره‌ها
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lms_enrollments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            enrollment_date datetime DEFAULT CURRENT_TIMESTAMP,
            payment_id bigint(20),
            status varchar(20) DEFAULT 'pending',
            progress int(3) DEFAULT 0,
            completed_at datetime,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY course_id (course_id)
        ) $charset_collate;";

        // جدول پیشرفت درس‌ها
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lms_progress (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            lesson_id bigint(20) NOT NULL,
            completion_date datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'incomplete',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY lesson_id (lesson_id)
        ) $charset_collate;";

        // جدول نتایج آزمون‌ها
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lms_quiz_results (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            score int(3) DEFAULT 0,
            completion_time int(10),
            started_at datetime,
            completed_at datetime,
            status varchar(20) DEFAULT 'incomplete',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function create_roles() {
        // نقش مدرس
        add_role('lms_instructor', 'مدرس', array(
            'read' => true,
            'edit_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'edit_courses' => true,
            'edit_lessons' => true,
            'manage_quizzes' => true
        ));

        // نقش دانشجو
        add_role('lms_student', 'دانشجو', array(
            'read' => true,
            'take_courses' => true,
            'take_quizzes' => true
        ));

        // نقش مدیر آموزش
        add_role('lms_manager', 'مدیر آموزش', array(
            'read' => true,
            'edit_courses' => true,
            'edit_lessons' => true,
            'manage_quizzes' => true,
            'manage_students' => true,
            'manage_instructors' => true,
            'view_reports' => true
        ));
    }

    private function create_pages() {
        // صفحات مورد نیاز
        $pages = array(
            'courses' => array(
                'title' => 'دوره‌های آموزشی',
                'content' => '[persian_lms_courses]'
            ),
            'student-dashboard' => array(
                'title' => 'پنل دانشجو',
                'content' => '[persian_lms_student_dashboard]'
            ),
            'instructor-dashboard' => array(
                'title' => 'پنل مدرس',
                'content' => '[persian_lms_instructor_dashboard]'
            )
        );

        foreach ($pages as $slug => $page) {
            if (!get_page_by_path($slug)) {
                wp_insert_post(array(
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug
                ));
            }
        }
    }

    public function init() {
        // تنظیم مجدد قوانین بازنویسی
        $this->register_post_types();
        $this->register_taxonomies();
    }

    private function register_post_types() {
        // ثبت Post Type دوره
        register_post_type('lms_course', array(
            'labels' => array(
                'name' => 'دوره‌ها',
                'singular_name' => 'دوره',
                'add_new' => 'افزودن دوره جدید',
                'add_new_item' => 'افزودن دوره جدید',
                'edit_item' => 'ویرایش دوره',
                'view_item' => 'مشاهده دوره',
                'search_items' => 'جستجوی دوره‌ها',
                'not_found' => 'دوره‌ای یافت نشد',
                'menu_name' => 'دوره‌ها'
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'menu_icon' => 'dashicons-welcome-learn-more',
            'rewrite' => array('slug' => 'courses')
        ));

        // ثبت Post Type درس
        register_post_type('lms_lesson', array(
            'labels' => array(
                'name' => 'درس‌ها',
                'singular_name' => 'درس',
                'add_new' => 'افزودن درس جدید',
                'add_new_item' => 'افزودن درس جدید',
                'edit_item' => 'ویرایش درس',
                'view_item' => 'مشاهده درس',
                'search_items' => 'جستجوی درس‌ها',
                'not_found' => 'درسی یافت نشد',
                'menu_name' => 'درس‌ها'
            ),
            'public' => true,
            'show_in_menu' => 'edit.php?post_type=lms_course',
            'supports' => array('title', 'editor', 'thumbnail'),
            'rewrite' => array('slug' => 'lessons')
        ));

        // ثبت Post Type آزمون
        register_post_type('lms_quiz', array(
            'labels' => array(
                'name' => 'آزمون‌ها',
                'singular_name' => 'آزمون',
                'add_new' => 'افزودن آزمون جدید',
                'add_new_item' => 'افزودن آزمون جدید',
                'edit_item' => 'ویرایش آزمون',
                'view_item' => 'مشاهده آزمون',
                'search_items' => 'جستجوی آزمون‌ها',
                'not_found' => 'آزمونی یافت نشد',
                'menu_name' => 'آزمون‌ها'
            ),
            'public' => true,
            'show_in_menu' => 'edit.php?post_type=lms_course',
            'supports' => array('title', 'editor'),
            'rewrite' => array('slug' => 'quizzes')
        ));
    }

    private function register_taxonomies() {
        // دسته‌بندی دوره‌ها
        register_taxonomy('course_category', 'lms_course', array(
            'labels' => array(
                'name' => 'دسته‌بندی دوره‌ها',
                'singular_name' => 'دسته‌بندی دوره',
                'search_items' => 'جستجوی دسته‌بندی‌ها',
                'all_items' => 'همه دسته‌بندی‌ها',
                'parent_item' => 'دسته‌بندی والد',
                'parent_item_colon' => 'دسته‌بندی والد:',
                'edit_item' => 'ویرایش دسته‌بندی',
                'update_item' => 'بروزرسانی دسته‌بندی',
                'add_new_item' => 'افزودن دسته‌بندی جدید',
                'new_item_name' => 'نام دسته‌بندی جدید',
                'menu_name' => 'دسته‌بندی‌ها'
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'course-category')
        ));

        // سطح دوره
        register_taxonomy('course_level', 'lms_course', array(
            'labels' => array(
                'name' => 'سطح دوره',
                'singular_name' => 'سطح دوره',
                'search_items' => 'جستجوی سطوح',
                'all_items' => 'همه سطوح',
                'edit_item' => 'ویرایش سطح',
                'update_item' => 'بروزرسانی سطح',
                'add_new_item' => 'افزودن سطح جدید',
                'new_item_name' => 'نام سطح جدید',
                'menu_name' => 'سطوح'
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'course-level')
        ));
    }

    public function enqueue_frontend_assets() {
        // استایل‌های فرانت‌اند
        wp_enqueue_style(
            'persian-lms-style',
            PERSIAN_LMS_PLUGIN_URL . 'public/css/public-style.css',
            array(),
            PERSIAN_LMS_VERSION
        );

        // اسکریپت‌های فرانت‌اند
        wp_enqueue_script(
            'persian-lms-script',
            PERSIAN_LMS_PLUGIN_URL . 'public/js/public-script.js',
            array('jquery'),
            PERSIAN_LMS_VERSION,
            true
        );

        // لوکالایز کردن اسکریپت‌ها
        wp_localize_script('persian-lms-script', 'persianLmsAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('persian_lms_nonce')
        ));
    }

    public function enqueue_admin_assets($hook) {
        // استایل‌های ادمین
        wp_enqueue_style(
            'persian-lms-admin-style',
            PERSIAN_LMS_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            PERSIAN_LMS_VERSION
        );

        // اسکریپت‌های ادمین
        wp_enqueue_script(
            'persian-lms-admin-script',
            PERSIAN_LMS_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery'),
            PERSIAN_LMS_VERSION,
            true
        );
    }
}

// راه‌اندازی پلاگین
function Persian_LMS() {
    return Persian_LMS::get_instance();
}

// شروع پلاگین
add_action('plugins_loaded', 'Persian_LMS');