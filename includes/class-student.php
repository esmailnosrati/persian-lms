<?php
if (!defined('ABSPATH')) {
    exit;
}

class Persian_LMS_Student {
    private $id;
    private $user_id;
    private $enrolled_courses = array();
    private $completed_lessons = array();

    public function __construct($user_id = 0) {
        if ($user_id > 0) {
            $this->user_id = $user_id;
            $this->load_student_data();
        }
    }

    private function load_student_data() {
        global $wpdb;

        // Load enrolled courses
        $enrolled = $wpdb->get_results($wpdb->prepare(
            "SELECT course_id, status, enrollment_date 
            FROM {$wpdb->prefix}lms_enrollments 
            WHERE user_id = %d",
            $this->user_id
        ));

        foreach ($enrolled as $course) {
            $this->enrolled_courses[$course->course_id] = array(
                'status' => $course->status,
                'date' => $course->enrollment_date
            );
        }

        // Load completed lessons
        $completed = $wpdb->get_results($wpdb->prepare(
            "SELECT lesson_id, completion_date 
            FROM {$wpdb->prefix}lms_progress 
            WHERE user_id = %d AND status = 'completed'",
            $this->user_id
        ));

        foreach ($completed as $lesson) {
            $this->completed_lessons[$lesson->lesson_id] = $lesson->completion_date;
        }
    }

    public function enroll_in_course($course_id) {
        global $wpdb;

        // Check if already enrolled
        if (isset($this->enrolled_courses[$course_id])) {
            return false;
        }

        $enrolled = $wpdb->insert(
            $wpdb->prefix . 'lms_enrollments',
            array(
                'user_id' => $this->user_id,
                'course_id' => $course_id,
                'status' => 'active',
                'enrollment_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s')
        );

        if ($enrolled) {
            $this->enrolled_courses[$course_id] = array(
                'status' => 'active',
                'date' => current_time('mysql')
            );
            return true;
        }

        return false;
    }

    public function complete_lesson($lesson_id, $course_id) {
        global $wpdb;

        // Check if enrolled in the course
        if (!isset($this->enrolled_courses[$course_id])) {
            return false;
        }

        // Check if already completed
        if (isset($this->completed_lessons[$lesson_id])) {
            return true;
        }

        $completed = $wpdb->insert(
            $wpdb->prefix . 'lms_progress',
            array(
                'user_id' => $this->user_id,
                'course_id' => $course_id,
                'lesson_id' => $lesson_id,
                'status' => 'completed',
                'completion_date' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );

        if ($completed) {
            $this->completed_lessons[$lesson_id] = current_time('mysql');
            $this->check_course_completion($course_id);
            return true;
        }

        return false;
    }

    private function check_course_completion($course_id) {
        $course_lessons = Persian_LMS_Lesson::get_lessons_by_course($course_id);
        $completed_count = 0;

        foreach ($course_lessons as $lesson) {
            if (isset($this->completed_lessons[$lesson->get_id()])) {
                $completed_count++;
            }
        }

        if ($completed_count === count($course_lessons)) {
            global $wpdb;
            
            $wpdb->update(
                $wpdb->prefix . 'lms_enrollments',
                array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ),
                array(
                    'user_id' => $this->user_id,
                    'course_id' => $course_id
                ),
                array('%s', '%s'),
                array('%d', '%d')
            );

            $this->enrolled_courses[$course_id]['status'] = 'completed';
            
            // Generate certificate if enabled
            do_action('persian_lms_course_completed', $this->user_id, $course_id);
        }
    }

    public function get_course_progress($course_id) {
        $course_lessons = Persian_LMS_Lesson::get_lessons_by_course($course_id);
        
        if (empty($course_lessons)) {
            return 0;
        }

        $completed_count = 0;
        foreach ($course_lessons as $lesson) {
            if (isset($this->completed_lessons[$lesson->get_id()])) {
                $completed_count++;
            }
        }

        return ($completed_count / count($course_lessons)) * 100;
    }

    // Getters
    public function get_user_id() {
        return $this->user_id;
    }

    public function get_enrolled_courses() {
        return $this->enrolled_courses;
    }

    public function get_completed_lessons() {
        return $this->completed_lessons;
    }

    public function is_enrolled_in_course($course_id) {
        return isset($this->enrolled_courses[$course_id]);
    }

    public function has_completed_lesson($lesson_id) {
        return isset($this->completed_lessons[$lesson_id]);
    }

    public function has_completed_course($course_id) {
        return isset($this->enrolled_courses[$course_id]) && 
               $this->enrolled_courses[$course_id]['status'] === 'completed';
    }

    // Static methods
    public static function get_course_students($course_id) {
        global $wpdb;
        
        $students = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id 
            FROM {$wpdb->prefix}lms_enrollments 
            WHERE course_id = %d",
            $course_id
        ));

        $student_objects = array();
        foreach ($students as $student_id) {
            $student_objects[] = new self($student_id);
        }

        return $student_objects;
    }
}