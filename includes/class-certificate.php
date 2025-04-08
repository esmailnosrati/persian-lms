<?php
if (!defined('ABSPATH')) {
    exit;
}

class Persian_LMS_Certificate {
    private $id;
    private $user_id;
    private $course_id;
    private $certificate_number;
    private $issue_date;
    private $template;
    private $meta;

    public function __construct($certificate_id = 0) {
        if ($certificate_id > 0) {
            $this->load_certificate($certificate_id);
        }
    }

    private function load_certificate($certificate_id) {
        global $wpdb;
        
        $certificate = get_post($certificate_id);
        if ($certificate && $certificate->post_type === 'lms_certificate') {
            $this->id = $certificate_id;
            $this->certificate_number = get_post_meta($certificate_id, '_certificate_number', true);
            $this->user_id = get_post_meta($certificate_id, '_user_id', true);
            $this->course_id = get_post_meta($certificate_id, '_course_id', true);
            $this->issue_date = get_post_meta($certificate_id, '_issue_date', true);
            $this->template = get_post_meta($certificate_id, '_template', true);
            $this->meta = get_post_meta($certificate_id, '_certificate_meta', true);
        }
    }

    public function generate($user_id, $course_id, $template = 'default') {
        $this->user_id = $user_id;
        $this->course_id = $course_id;
        $this->template = $template;
        $this->issue_date = current_time('mysql');
        $this->certificate_number = $this->generate_certificate_number();

        // اضافه کردن متادیتای اضافی
        $this->meta = array(
            'user_name' => get_user_by('id', $user_id)->display_name,
            'course_name' => get_the_title($course_id),
            'completion_date' => $this->issue_date,
            'instructor_name' => get_post_meta($course_id, '_instructor_name', true)
        );

        return $this->save();
    }

    private function generate_certificate_number() {
        $prefix = 'PLMS'; // Persian LMS
        $year = date('Y');
        $random = wp_rand(1000, 9999);
        return sprintf('%s-%s-%d-%d-%d', $prefix, $year, $this->user_id, $this->course_id, $random);
    }

    public function save() {
        if ($this->id > 0) {
            return $this->update();
        } else {
            return $this->create();
        }
    }

    private function create() {
        $post_data = array(
            'post_title' => sprintf(__('Certificate for %s - %s', 'persian-lms'), 
                                  get_user_by('id', $this->user_id)->display_name,
                                  get_the_title($this->course_id)),
            'post_status' => 'publish',
            'post_type' => 'lms_certificate'
        );

        $certificate_id = wp_insert_post($post_data);

        if (!is_wp_error($certificate_id)) {
            $this->id = $certificate_id;
            $this->update_certificate_meta();
            return true;
        }

        return false;
    }

    private function update() {
        $updated = wp_update_post(array(
            'ID' => $this->id,
            'post_title' => sprintf(__('Certificate for %s - %s', 'persian-lms'), 
                                  get_user_by('id', $this->user_id)->display_name,
                                  get_the_title($this->course_id))
        ));

        if (!is_wp_error($updated)) {
            $this->update_certificate_meta();
            return true;
        }

        return false;
    }

    private function update_certificate_meta() {
        update_post_meta($this->id, '_certificate_number', $this->certificate_number);
        update_post_meta($this->id, '_user_id', $this->user_id);
        update_post_meta($this->id, '_course_id', $this->course_id);
        update_post_meta($this->id, '_issue_date', $this->issue_date);
        update_post_meta($this->id, '_template', $this->template);
        update_post_meta($this->id, '_certificate_meta', $this->meta);
    }

    public function render() {
        $template_path = $this->get_template_path();
        if (!file_exists($template_path)) {
            return false;
        }

        ob_start();
        include $template_path;
        $html = ob_get_clean();

        // جایگزینی متغیرها
        $variables = array(
            '{{certificate_number}}' => $this->certificate_number,
            '{{student_name}}' => $this->meta['user_name'],
            '{{course_name}}' => $this->meta['course_name'],
            '{{completion_date}}' => wp_date(get_option('date_format'), strtotime($this->issue_date)),
            '{{instructor_name}}' => $this->meta['instructor_name']
        );

        return str_replace(array_keys($variables), array_values($variables), $html);
    }

    private function get_template_path() {
        $template = $this->template ?: 'default';
        $paths = array(
            get_stylesheet_directory() . '/persian-lms/certificates/' . $template . '.php',
            plugin_dir_path(dirname(__FILE__)) . 'templates/certificates/' . $template . '.php'
        );

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    public function download() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libraries/dompdf/autoload.php';

        $html = $this->render();
        if (!$html) {
            return false;
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $output = $dompdf->output();
        $upload_dir = wp_upload_dir();
        $certificate_path = $upload_dir['path'] . '/' . $this->certificate_number . '.pdf';

        if (file_put_contents($certificate_path, $output)) {
            return array(
                'path' => $certificate_path,
                'url' => $upload_dir['url'] . '/' . $this->certificate_number . '.pdf'
            );
        }

        return false;
    }

    // Getters
    public function get_id() {
        return $this->id;
    }

    public function get_certificate_number() {
        return $this->certificate_number;
    }

    public function get_user_id() {
        return $this->user_id;
    }

    public function get_course_id() {
        return $this->course_id;
    }

    public function get_issue_date() {
        return $this->issue_date;
    }

    public function get_template() {
        return $this->template;
    }

    public function get_meta($key = '') {
        if ($key) {
            return isset($this->meta[$key]) ? $this->meta[$key] : null;
        }
        return $this->meta;
    }

    // Static methods
    public static function get_user_certificates($user_id) {
        $args = array(
            'post_type' => 'lms_certificate',
            'posts_per_page' => -1,
            'meta_key' => '_user_id',
            'meta_value' => $user_id
        );

        $certificates = array();
        $posts = get_posts($args);

        foreach ($posts as $post) {
            $certificates[] = new self($post->ID);
        }

        return $certificates;
    }

    public static function get_course_certificates($course_id) {
        $args = array(
            'post_type' => 'lms_certificate',
            'posts_per_page' => -1,
            'meta_key' => '_course_id',
            'meta_value' => $course_id
        );

        $certificates = array();
        $posts = get_posts($args);

        foreach ($posts as $post) {
            $certificates[] = new self($post->ID);
        }

        return $certificates;
    }

    public static function verify_certificate($certificate_number) {
        $args = array(
            'post_type' => 'lms_certificate',
            'posts_per_page' => 1,
            'meta_key' => '_certificate_number',
            'meta_value' => $certificate_number
        );

        $posts = get_posts($args);
        
        if (!empty($posts)) {
            return new self($posts[0]->ID);
        }

        return false;
    }
}