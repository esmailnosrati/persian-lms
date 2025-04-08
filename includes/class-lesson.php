<?php
if (!defined('ABSPATH')) {
    exit;
}

class Persian_LMS_Lesson {
    private $id;
    private $course_id;
    private $title;
    private $content;
    private $order;
    private $status;

    public function __construct($lesson_id = 0) {
        if ($lesson_id > 0) {
            $this->load_lesson($lesson_id);
        }
    }

    private function load_lesson($lesson_id) {
        global $wpdb;
        
        $lesson = get_post($lesson_id);
        if ($lesson && $lesson->post_type === 'lms_lesson') {
            $this->id = $lesson_id;
            $this->title = $lesson->post_title;
            $this->content = $lesson->post_content;
            $this->status = $lesson->post_status;
            $this->course_id = get_post_meta($lesson_id, '_course_id', true);
            $this->order = get_post_meta($lesson_id, '_lesson_order', true);
        }
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
            'post_title' => $this->title,
            'post_content' => $this->content,
            'post_status' => $this->status,
            'post_type' => 'lms_lesson'
        );

        $lesson_id = wp_insert_post($post_data);

        if (!is_wp_error($lesson_id)) {
            $this->id = $lesson_id;
            update_post_meta($lesson_id, '_course_id', $this->course_id);
            update_post_meta($lesson_id, '_lesson_order', $this->order);
            return true;
        }

        return false;
    }

    private function update() {
        $post_data = array(
            'ID' => $this->id,
            'post_title' => $this->title,
            'post_content' => $this->content,
            'post_status' => $this->status
        );

        $updated = wp_update_post($post_data);

        if (!is_wp_error($updated)) {
            update_post_meta($this->id, '_course_id', $this->course_id);
            update_post_meta($this->id, '_lesson_order', $this->order);
            return true;
        }

        return false;
    }

    public function delete() {
        if ($this->id > 0) {
            return wp_delete_post($this->id, true);
        }
        return false;
    }

    // Getters
    public function get_id() {
        return $this->id;
    }

    public function get_course_id() {
        return $this->course_id;
    }

    public function get_title() {
        return $this->title;
    }

    public function get_content() {
        return $this->content;
    }

    public function get_order() {
        return $this->order;
    }

    public function get_status() {
        return $this->status;
    }

    // Setters
    public function set_course_id($course_id) {
        $this->course_id = absint($course_id);
    }

    public function set_title($title) {
        $this->title = sanitize_text_field($title);
    }

    public function set_content($content) {
        $this->content = wp_kses_post($content);
    }

    public function set_order($order) {
        $this->order = absint($order);
    }

    public function set_status($status) {
        $this->status = sanitize_text_field($status);
    }

    // Static methods
    public static function get_lessons_by_course($course_id) {
        $args = array(
            'post_type' => 'lms_lesson',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_course_id',
            'meta_value' => $course_id,
            'orderby' => 'meta_value_num',
            'meta_key' => '_lesson_order',
            'order' => 'ASC'
        );

        $lessons = array();
        $posts = get_posts($args);

        foreach ($posts as $post) {
            $lessons[] = new self($post->ID);
        }

        return $lessons;
    }
}