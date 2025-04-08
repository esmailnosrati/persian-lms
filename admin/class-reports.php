<?php
class Persian_LMS_Reports {
    public function __construct() {
        add_action('wp_ajax_get_lms_report', array($this, 'get_report_data'));
        add_action('wp_ajax_export_lms_report', array($this, 'export_report'));
    }

    /**
     * دریافت داده‌های گزارش
     */
    public function get_report_data() {
        check_ajax_referer('get_lms_report', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('شما دسترسی لازم را ندارید.');
        }

        $type = sanitize_text_field($_POST['type']);
        $period = sanitize_text_field($_POST['period']);

        $date_range = $this->get_date_range($period);
        $data = array();

        switch ($type) {
            case 'sales':
                $data = $this->get_sales_report($date_range);
                break;
            case 'enrollments':
                $data = $this->get_enrollments_report($date_range);
                break;
            case 'completions':
                $data = $this->get_completions_report($date_range);
                break;
            case 'certificates':
                $data = $this->get_certificates_report($date_range);
                break;
        }

        wp_send_json_success($data);
    }

    /**
     * دریافت بازه زمانی
     */
    private function get_date_range($period) {
        $end = current_time('mysql');
        $start = '';

        switch ($period) {
            case '7days':
                $start = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30days':
                $start = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '90days':
                $start = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            case 'year':
                $start = date('Y-01-01 00:00:00');
                break;
            default:
                $start = '1970-01-01 00:00:00';
        }

        return array(
            'start' => $start,
            'end' => $end
        );
    }

    /**
     * گزارش فروش
     */
    private function get_sales_report($date_range) {
        global $wpdb;

        // داده‌های خلاصه
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT course_id) as courses_count,
                COUNT(DISTINCT user_id) as students_count,
                SUM(amount) as total_sales
             FROM {$wpdb->prefix}lms_payments
             WHERE status = 'completed'
             AND payment_date BETWEEN %s AND %s",
            $date_range['start'],
            $date_range['end']
        ));

        // داده‌های نمودار
        $chart_data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => 'فروش',
                    'data' => array(),
                    'borderColor' => '#28a745',
                    'fill' => false
                )
            )
        );

        $sales_by_date = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(payment_date) as date,
                SUM(amount) as total
             FROM {$wpdb->prefix}lms_payments
             WHERE status = 'completed'
             AND payment_date BETWEEN %s AND %s
             GROUP BY DATE(payment_date)
             ORDER BY date ASC",
            $date_range['start'],
            $date_range['end']
        ));

        foreach ($sales_by_date as $row) {
            $chart_data['labels'][] = date_i18n('Y/m/d', strtotime($row->date));
            $chart_data['datasets'][0]['data'][] = $row->total;
        }

        // داده‌های جدول
        $table_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                p.*, 
                c.post_title as course_title,
                u.display_name as student_name
             FROM {$wpdb->prefix}lms_payments p
             INNER JOIN {$wpdb->posts} c ON p.course_id = c.ID
             INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.status = 'completed'
             AND p.payment_date BETWEEN %s AND %s
             ORDER BY p.payment_date DESC
             LIMIT 50",
            $date_range['start'],
            $date_range['end']
        ));

        return array(
            'summary' => array(
                'total_sales' => number_format($summary->total_sales),
                'courses_count' => $summary->courses_count,
                'students_count' => $summary->students_count
            ),
            'chart_data' => $chart_data,
            'table_data' => $table_data
        );
    }

    /**
     * گزارش ثبت‌نام‌ها
     */
    private function get_enrollments_report($date_range) {
        global $wpdb;

        // داده‌های خلاصه
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_enrollments,
                COUNT(DISTINCT course_id) as courses_count,
                COUNT(DISTINCT user_id) as students_count
             FROM {$wpdb->prefix}lms_enrollments
             WHERE enrollment_date BETWEEN %s AND %s",
            $date_range['start'],
            $date_range['end']
        ));

        // داده‌های نمودار
        $chart_data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => 'ثبت‌نام‌ها',
                    'data' => array(),
                    'borderColor' => '#007bff',
                    'fill' => false
                )
            )
        );

        $enrollments_by_date = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(enrollment_date) as date,
                COUNT(*) as count
             FROM {$wpdb->prefix}lms_enrollments
             WHERE enrollment_date BETWEEN %s AND %s
             GROUP BY DATE(enrollment_date)
             ORDER BY date ASC",
            $date_range['start'],
            $date_range['end']
        ));

        foreach ($enrollments_by_date as $row) {
            $chart_data['labels'][] = date_i18n('Y/m/d', strtotime($row->date));
            $chart_data['datasets'][0]['data'][] = $row->count;
        }

        // داده‌های جدول
        $table_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                e.*,
                c.post_title as course_title,
                u.display_name as student_name
             FROM {$wpdb->prefix}lms_enrollments e
             INNER JOIN {$wpdb->posts} c ON e.course_id = c.ID
             INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.enrollment_date BETWEEN %s AND %s
             ORDER BY e.enrollment_date DESC
             LIMIT 50",
            $date_range['start'],
            $date_range['end']
        ));

        return array(
            'summary' => array(
                'total_enrollments' => $summary->total_enrollments,
                'courses_count' => $summary->courses_count,
                'students_count' => $summary->students_count
            ),
            'chart_data' => $chart_data,
            'table_data' => $table_data
        );
    }

    /**
     * گزارش تکمیل دوره‌ها
     */
    private function get_completions_report($date_range) {
        global $wpdb;

        // داده‌های خلاصه
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_completions,
                COUNT(DISTINCT course_id) as courses_count,
                COUNT(DISTINCT user_id) as students_count
             FROM {$wpdb->prefix}lms_enrollments
             WHERE status = 'completed'
             AND completed_at BETWEEN %s AND %s",
            $date_range['start'],
            $date_range['end']
        ));

        // محاسبه میانگین نرخ تکمیل
        $completion_rate = $wpdb->get_var(
            "SELECT 
                (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*)) 
             FROM {$wpdb->prefix}lms_enrollments"
        );

        // داده‌های نمودار
        $chart_data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => 'تکمیل دوره‌ها',
                    'data' => array(),
                    'borderColor' => '#17a2b8',
                    'fill' => false
                )
            )
        );

        $completions_by_date = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(completed_at) as date,
                COUNT(*) as count
             FROM {$wpdb->prefix}lms_enrollments
             WHERE status = 'completed'
             AND completed_at BETWEEN %s AND %s
             GROUP BY DATE(completed_at)
             ORDER BY date ASC",
            $date_range['start'],
            $date_range['end']
        ));

        foreach ($completions_by_date as $row) {
            $chart_data['labels'][] = date_i18n('Y/m/d', strtotime($row->date));
            $chart_data['datasets'][0]['data'][] = $row->count;
        }

        return array(
            'summary' => array(
                'total_completions' => $summary->total_completions,
                'courses_count' => $summary->courses_count,
                'students_count' => $summary->students_count,
                'completion_rate' => round($completion_rate, 1)
            ),
            'chart_data' => $chart_data
        );
    }

    /**
     * گزارش گواهینامه‌ها
     */
    private function get_certificates_report($date_range) {
        global $wpdb;

        // داده‌های خلاصه
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_certificates,
                COUNT(DISTINCT course_id) as courses_count,
                COUNT(DISTINCT user_id) as students_count
             FROM {$wpdb->prefix}lms_certificates
             WHERE issue_date BETWEEN %s AND %s",
            $date_range['start'],
            $date_range['end']
        ));

        // داده‌های نمودار
        $chart_data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => 'گواهینامه‌های صادر شده',
                    'data' => array(),
                    'borderColor' => '#ffc107',
                    'fill' => false
                )
            )
        );

        $certificates_by_date = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(issue_date) as date,
                COUNT(*) as count
             FROM {$wpdb->prefix}lms_certificates
             WHERE issue_date BETWEEN %s AND %s
             GROUP BY DATE(issue_date)
             ORDER BY date ASC",
            $date_range['start'],
            $date_range['end']
        ));

        foreach ($certificates_by_date as $row) {
            $chart_data['labels'][] = date_i18n('Y/m/d', strtotime($row->date));
            $chart_data['datasets'][0]['data'][] = $row->count;
        }

        // داده‌های جدول
        $table_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                cert.*,
                c.post_title as course_title,
                u.display_name as student_name
             FROM {$wpdb->prefix}lms_certificates cert
             INNER JOIN {$wpdb->posts} c ON cert.course_id = c.ID
             INNER JOIN {$wpdb->users} u ON cert.user_id = u.ID
             WHERE cert.issue_date BETWEEN %s AND %s
             ORDER BY cert.issue_date DESC
             LIMIT 50",
            $date_range['start'],
            $date_range['end']
        ));

        return array(
            'summary' => array(
                'total_certificates' => $summary->total_certificates,
                'courses_count' => $summary->courses_count,
                'students_count' => $summary->students_count
            ),
            'chart_data' => $chart_data,
            'table_data' => $table_data
        );
    }

    /**
     * خروجی اکسل
     */
    public function export_report() {
        check_ajax_referer('export_lms_report', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('شما دسترسی لازم را ندارید.');
        }

        $type = sanitize_text_field($_GET['type']);
        $period = sanitize_text_field($_GET['period']);
        $date_range = $this->get_date_range($period);

        // دریافت داده‌ها
        switch ($type) {
            case 'sales':
                $data = $this->get_sales_export_data($date_range);
                $filename = 'sales-report';
                break;
            case 'enrollments':
                $data = $this->get_enrollments_export_data($date_range);
                $filename = 'enrollments-report';
                break;
            case 'completions':
                $data = $this->get_completions_export_data($date_range);
                $filename = 'completions-report';
                break;
            case 'certificates':
                $data = $this->get_certificates_export_data($date_range);
                $filename = 'certificates-report';
                break;
            default:
                wp_die('نوع گزارش نامعتبر است.');
        }

        $filename .= '-' . date('Y-m-d') . '.csv';

        // تنظیم هدرها
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        // خروجی CSV
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8

        // هدر ستون‌ها
        fputcsv($output, array_keys($data[0]));

        // داده‌ها
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * داده‌های خروجی گزارش فروش
     */
    private function get_sales_export_data($date_range) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                p.transaction_id as 'شماره تراکنش',
                u.display_name as 'نام دانشجو',
                c.post_title as 'نام دوره',
                p.amount as 'مبلغ',
                p.payment_method as 'روش پرداخت',
                p.status as 'وضعیت',
                p.payment_date as 'تاریخ پرداخت'
             FROM {$wpdb->prefix}lms_payments p
             INNER JOIN {$wpdb->posts} c ON p.course_id = c.ID
             INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.payment_date BETWEEN %s AND %s
             ORDER BY p.payment_date DESC",
            $date_range['start'],
            $date_range['end']
        ), ARRAY_A);
    }

    /**
     * داده‌های خروجی گزارش ثبت‌نام‌ها
     */
    private function get_enrollments_export_data($date_range) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.display_name as 'نام دانشجو',
                c.post_title as 'نام دوره',
                e.status as 'وضعیت',
                e.enrollment_date as 'تاریخ ثبت‌نام',
                e.completed_at as 'تاریخ تکمیل'
             FROM {$wpdb->prefix}lms_enrollments e
             INNER JOIN {$wpdb->posts} c ON e.course_id = c.ID
             INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.enrollment_date BETWEEN %s AND %s
             ORDER BY e.enrollment_date DESC",
            $date_range['start'],
            $date_range['end']
        ), ARRAY_A);
    }

    /**
     * داده‌های خروجی گزارش تکمیل دوره‌ها
     */
    private function get_completions_export_data($date_range) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.display_name as 'نام دانشجو',
                c.post_title as 'نام دوره',
                e.enrollment_date as 'تاریخ ثبت‌نام',
                e.completed_at as 'تاریخ تکمیل',
                DATEDIFF(e.completed_at, e.enrollment_date) as 'مدت زمان تکمیل (روز)'
             FROM {$wpdb->prefix}lms_enrollments e
             INNER JOIN {$wpdb->posts} c ON e.course_id = c.ID
             INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.status = 'completed'
             AND e.completed_at BETWEEN %s AND %s
             ORDER BY e.completed_at DESC",
            $date_range['start'],
            $date_range['end']
        ), ARRAY_A);
    }

    /**
     * داده‌های خروجی گزارش گواهینامه‌ها
     */
    private function get_certificates_export_data($date_range) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.display_name as 'نام دانشجو',
                c.post_title as 'نام دوره',
                cert.certificate_number as 'شماره گواهی',
                cert.issue_date as 'تاریخ صدور',
                cert.expiry_date as 'تاریخ انقضا'
             FROM {$wpdb->prefix}lms_certificates cert
             INNER JOIN {$wpdb->posts} c ON cert.course_id = c.ID
             INNER JOIN {$wpdb->users} u ON cert.user_id = u.ID
             WHERE cert.issue_date BETWEEN %s AND %s
             ORDER BY cert.issue_date DESC",
            $date_range['start'],
            $date_range['end']
        ), ARRAY_A);
    }
}

new Persian_LMS_Reports();