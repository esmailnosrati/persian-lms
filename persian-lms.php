<?php
/**
 * Plugin Name: Persian LMS
 * Plugin URI: https://github.com/esmailnosrati/persian-lms
 * Description: سیستم جامع مدیریت یادگیری (LMS) برای وبسایت‌های فارسی
 * Version: 1.0.0
 * Author: Esmail Nosrati
 * Author URI: https://github.com/esmailnosrati
 * Text Domain: persian-lms
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های پلاگین
define('PERSIAN_LMS_VERSION', '1.0.0');
define('PERSIAN_LMS_FILE', __FILE__);
define('PERSIAN_LMS_PATH', plugin_dir_path(__FILE__));
define('PERSIAN_LMS_URL', plugin_dir_url(__FILE__));

// کلاس اصلی پلاگین
class Persian_LMS_Plugin {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->include_files();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
    }
    
    private function include_files() {
        // Core files
        require_once PERSIAN_LMS_PATH . 'includes/class-course.php';
        require_once PERSIAN_LMS_PATH . 'includes/class-lesson.php';
        require_once PERSIAN_LMS_PATH . 'includes/class-student.php';
        require_once PERSIAN_LMS_PATH . 'includes/class-certificate.php';
    }
    
    public function activate() {
        // ایجاد جداول دیتابیس
        $this->create_tables();
        
        // افزودن نقش‌های کاربری
        add_role('lms_student', 'دانشجو', array(
            'read' => true,
            'upload_files' => true
        ));
        
        // ایجاد صفحات پیش‌فرض
        $this->create_pages();
        
        // تنظیم مجوزها
        $this->setup_permissions();
        
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = file_get_contents(PERSIAN_LMS_PATH . 'includes/db/schema.sql');
        
        // جایگزینی پیشوند جداول و کاراکترست
        $sql = str_replace(
            array('{prefix}', '{charset_collate}'),
            array($wpdb->prefix, $charset_collate),
            $sql
        );
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_pages() {
        $pages = array(
            'student-dashboard' => array(
                'title' => 'پنل دانشجو',
                'content' => '[persian_lms_student_dashboard]'
            ),
            'courses' => array(
                'title' => 'دوره‌های آموزشی',
                'content' => '[persian_lms_courses]'
            ),
            'certificates' => array(
                'title' => 'گواهینامه‌ها',
                'content' => '[persian_lms_certificates]'
            )
        );
        
        foreach ($pages as $slug => $page) {
            if (null === get_page_by_path($slug)) {
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
    
    private function setup_permissions() {
        $admin = get_role('administrator');
        $capabilities = array(
            'manage_lms',
            'edit_courses',
            'edit_others_courses',
            'publish_courses',
            'read_private_courses',
            'edit_lessons',
            'edit_others_lessons',
            'publish_lessons',
            'read_private_lessons'
        );
        
        foreach ($capabilities as $cap) {
            $admin->add_cap($cap);
        }
    }
    
    public function deactivate() {
        // پاکسازی نقش‌های کاربری اضافه شده
        remove_role('lms_student');
        
        flush_rewrite_rules();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'persian-lms',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    public function init() {
        // Register post types
        $this->register_post_types();
        
        // Register taxonomies
        $this->register_taxonomies();
        
        // Register shortcodes
        $this->register_shortcodes();
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    private function register_post_types() {
        // Course post type
        register_post_type('course', array(
            'labels' => array(
                'name' => __('Courses', 'persian-lms'),
                'singular_name' => __('Course', 'persian-lms'),
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'menu_icon' => 'dashicons-welcome-learn-more',
            'rewrite' => array('slug' => 'course')
        ));
        
        // Lesson post type
        register_post_type('lesson', array(
            'labels' => array(
                'name' => __('Lessons', 'persian-lms'),
                'singular_name' => __('Lesson', 'persian-lms'),
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail'),
            'menu_icon' => 'dashicons-book',
            'rewrite' => array('slug' => 'lesson')
        ));
    }
    
    private function register_taxonomies() {
        // Course categories
        register_taxonomy('course_cat', 'course', array(
            'label' => __('Course Categories', 'persian-lms'),
            'hierarchical' => true,
            'rewrite' => array('slug' => 'course-category')
        ));
        
        // Course tags
        register_taxonomy('course_tag', 'course', array(
            'label' => __('Course Tags', 'persian-lms'),
            'hierarchical' => false,
            'rewrite' => array('slug' => 'course-tag')
        ));
    }
    
    private function register_shortcodes() {
        add_shortcode('persian_lms_student_dashboard', array($this, 'student_dashboard_shortcode'));
        add_shortcode('persian_lms_courses', array($this, 'courses_shortcode'));
        add_shortcode('persian_lms_certificates', array($this, 'certificates_shortcode'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style(
            'persian-lms',
            PERSIAN_LMS_URL . 'assets/css/persian-lms.css',
            array(),
            PERSIAN_LMS_VERSION
        );
        
        wp_enqueue_script(
            'persian-lms',
            PERSIAN_LMS_URL . 'assets/js/persian-lms.js',
            array('jquery'),
            PERSIAN_LMS_VERSION,
            true
        );
        
        wp_localize_script('persian-lms', 'persianLmsVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('persian_lms_nonce')
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        wp_enqueue_style(
            'persian-lms-admin',
            PERSIAN_LMS_URL . 'assets/css/persian-lms-admin.css',
            array(),
            PERSIAN_LMS_VERSION
        );
        
        wp_enqueue_script(
            'persian-lms-admin',
            PERSIAN_LMS_URL . 'assets/js/persian-lms-admin.js',
            array('jquery'),
            PERSIAN_LMS_VERSION,
            true
        );
    }
    
    // Shortcode callbacks
    public function student_dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please login to access your dashboard.', 'persian-lms') . '</p>';
        }
        
        ob_start();
        include PERSIAN_LMS_PATH . 'templates/student-dashboard.php';
        return ob_get_clean();
    }
    
    public function courses_shortcode($atts) {
        ob_start();
        include PERSIAN_LMS_PATH . 'templates/courses.php';
        return ob_get_clean();
    }
    
    public function certificates_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please login to view your certificates.', 'persian-lms') . '</p>';
        }
        
        ob_start();
        include PERSIAN_LMS_PATH . 'templates/certificates.php';
        return ob_get_clean();
    }
}

// Initialize plugin
Persian_LMS_Plugin::get_instance();