<?php
class Persian_LMS_Certificate {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('lms_generate_certificate', array($this, 'generate_certificate'), 10, 2);
        add_action('admin_menu', array($this, 'add_certificate_menu'));
        add_action('wp_ajax_verify_certificate', array($this, 'verify_certificate'));
        add_action('wp_ajax_nopriv_verify_certificate', array($this, 'verify_certificate'));
    }

    public function init() {
        // ایجاد جدول گواهینامه‌ها اگر وجود نداشته باشد
        $this->create_tables();
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lms_certificates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            certificate_number varchar(50) NOT NULL,
            issue_date datetime DEFAULT CURRENT_TIMESTAMP,
            expiry_date datetime,
            certificate_url varchar(255),
            status varchar(20) DEFAULT 'active',
            metadata text,
            PRIMARY KEY (id),
            UNIQUE KEY certificate_number (certificate_number),
            KEY user_id (user_id),
            KEY course_id (course_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * تولید گواهینامه برای دانشجو
     */
    public function generate_certificate($course_id, $user_id) {
        // بررسی اینکه آیا قبلاً گواهی صادر شده است
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}lms_certificates 
             WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));

        if ($existing) {
            return false;
        }

        // تولید شماره منحصر به فرد گواهی
        $certificate_number = $this->generate_certificate_number();

        // دریافت اطلاعات مورد نیاز
        $user = get_userdata($user_id);
        $course = get_post($course_id);
        $instructor = get_userdata($course->post_author);
        $completion_date = current_time('mysql');

        // تنظیم متادیتای گواهی
        $metadata = array(
            'student_name' => $user->display_name,
            'course_title' => $course->post_title,
            'instructor_name' => $instructor->display_name,
            'completion_date' => $completion_date,
            'course_duration' => get_post_meta($course_id, '_course_duration', true)
        );

        // ایجاد فایل گواهی
        $certificate_url = $this->create_certificate_file($metadata, $certificate_number);

        if (!$certificate_url) {
            return false;
        }

        // ذخیره گواهی در دیتابیس
        $result = $wpdb->insert(
            $wpdb->prefix . 'lms_certificates',
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'certificate_number' => $certificate_number,
                'issue_date' => current_time('mysql'),
                'expiry_date' => date('Y-m-d H:i:s', strtotime('+2 years')), // تاریخ انقضا (اختیاری)
                'certificate_url' => $certificate_url,
                'metadata' => json_encode($metadata)
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            do_action('lms_after_certificate_generation', $course_id, $user_id, $certificate_number);
            return true;
        }

        return false;
    }

    /**
     * تولید شماره منحصر به فرد گواهی
     */
    private function generate_certificate_number() {
        $prefix = 'CERT-';
        $random = strtoupper(substr(uniqid(), -6));
        $date = date('Ymd');
        return $prefix . $date . '-' . $random;
    }

    /**
     * ایجاد فایل PDF گواهی
     */
    private function create_certificate_file($metadata, $certificate_number) {
        require_once(PERSIAN_LMS_PLUGIN_DIR . 'includes/lib/tcpdf/tcpdf.php');

        // ایجاد آبجکت TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // تنظیمات PDF
        $pdf->SetCreator(get_bloginfo('name'));
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('گواهی پایان دوره ' . $metadata['course_title']);

        // حذف هدر و فوتر پیش‌فرض
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // تنظیمات فونت و استایل
        $pdf->SetFont('dejavusans', '', 20);
        $pdf->AddPage('L'); // Landscape orientation

        // افزودن تصویر پس‌زمینه
        $background = PERSIAN_LMS_PLUGIN_DIR . 'assets/images/certificate-template.jpg';
        if (file_exists($background)) {
            $pdf->Image($background, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight());
        }

        // افزودن لوگو
        $logo = PERSIAN_LMS_PLUGIN_DIR . 'assets/images/logo.png';
        if (file_exists($logo)) {
            $pdf->Image($logo, 10, 10, 30);
        }

        // محتوای گواهی
        $pdf->SetY(50);
        $pdf->Cell(0, 15, 'گواهی پایان دوره', 0, 1, 'C');
        
        $pdf->SetFont('dejavusans', '', 14);
        $pdf->Cell(0, 10, 'بدینوسیله گواهی می‌شود:', 0, 1, 'C');
        
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 15, $metadata['student_name'], 0, 1, 'C');
        
        $pdf->SetFont('dejavusans', '', 14);
        $pdf->Cell(0, 10, 'دوره آموزشی:', 0, 1, 'C');
        
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 15, $metadata['course_title'], 0, 1, 'C');
        
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->Cell(0, 10, sprintf('به مدت %s ساعت را با موفقیت به پایان رسانده است.', $metadata['course_duration']), 0, 1, 'C');
        
        $pdf->Cell(0, 10, sprintf('تاریخ صدور: %s', date_i18n('Y/m/d', strtotime($metadata['completion_date']))), 0, 1, 'C');
        $pdf->Cell(0, 10, sprintf('شماره گواهی: %s', $certificate_number), 0, 1, 'C');

        // مدرس دوره
        $pdf->SetY(-60);
        $pdf->Cell(($pdf->getPageWidth() / 2), 10, 'مدرس دوره:', 0, 0, 'C');
        $pdf->Cell(($pdf->getPageWidth() / 2), 10, 'مدیر آموزش:', 0, 1, 'C');
        
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->Cell(($pdf->getPageWidth() / 2), 10, $metadata['instructor_name'], 0, 0, 'C');
        $pdf->Cell(($pdf->getPageWidth() / 2), 10, get_bloginfo('name'), 0, 1, 'C');

        // ذخیره فایل
        $upload_dir = wp_upload_dir();
        $certificates_dir = $upload_dir['basedir'] . '/certificates';
        if (!file_exists($certificates_dir)) {
            wp_mkdir_p($certificates_dir);
        }

        $filename = sprintf('certificate-%s.pdf', $certificate_number);
        $filepath = $certificates_dir . '/' . $filename;
        $pdf->Output($filepath, 'F');

        return $upload_dir['baseurl'] . '/certificates/' . $filename;
    }

    /**
     * افزودن منوی مدیریت گواهینامه‌ها
     */
    public function add_certificate_menu() {
        add_submenu_page(
            'edit.php?post_type=lms_course',
            'گواهینامه‌ها',
            'گواهینامه‌ها',
            'manage_options',
            'lms-certificates',
            array($this, 'render_certificates_page')
        );
    }

    /**
     * صفحه مدیریت گواهینامه‌ها
     */
    public function render_certificates_page() {
        global $wpdb;

        // جستجو و فیلتر
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

        $query = "SELECT c.*, u.display_name as student_name, p.post_title as course_title
                 FROM {$wpdb->prefix}lms_certificates c
                 INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
                 INNER JOIN {$wpdb->posts} p ON c.course_id = p.ID
                 WHERE 1=1";

        if ($search) {
            $query .= $wpdb->prepare(
                " AND (c.certificate_number LIKE %s OR u.display_name LIKE %s OR p.post_title LIKE %s)",
                "%$search%",
                "%$search%",
                "%$search%"
            );
        }

        if ($course_id) {
            $query .= $wpdb->prepare(" AND c.course_id = %d", $course_id);
        }

        $query .= " ORDER BY c.issue_date DESC";

        $certificates = $wpdb->get_results($query);
        ?>
        <div class="wrap">
            <h1>مدیریت گواهینامه‌ها</h1>

            <form method="get">
                <input type="hidden" name="post_type" value="lms_course">
                <input type="hidden" name="page" value="lms-certificates">
                
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="course_id">
                            <option value="">همه دوره‌ها</option>
                            <?php
                            $courses = get_posts(array(
                                'post_type' => 'lms_course',
                                'posts_per_page' => -1
                            ));
                            foreach ($courses as $course) {
                                echo sprintf(
                                    '<option value="%d" %s>%s</option>',
                                    $course->ID,
                                    selected($course_id, $course->ID, false),
                                    $course->post_title
                                );
                            }
                            ?>
                        </select>

                        <input type="submit" class="button" value="فیلتر">
                    </div>

                    <div class="alignright">
                        <p class="search-box">
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" 
                                   placeholder="جستجوی گواهی...">
                            <input type="submit" class="button" value="جستجو">
                        </p>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>شماره گواهی</th>
                            <th>دانشجو</th>
                            <th>دوره</th>
                            <th>تاریخ صدور</th>
                            <th>تاریخ انقضا</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificates as $certificate) : ?>
                            <tr>
                                <td><?php echo $certificate->certificate_number; ?></td>
                                <td><?php echo $certificate->student_name; ?></td>
                                <td><?php echo $certificate->course_title; ?></td>
                                <td><?php echo date_i18n('Y/m/d', strtotime($certificate->issue_date)); ?></td>
                                <td>
                                    <?php 
                                    echo $certificate->expiry_date ? 
                                         date_i18n('Y/m/d', strtotime($certificate->expiry_date)) : 
                                         '---';
                                    ?>
                                </td>
                                <td><?php echo $this->get_status_label($certificate->status); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($certificate->certificate_url); ?>" 
                                       class="button" target="_blank">
                                        دانلود
                                    </a>
                                    <?php if ($certificate->status === 'active') : ?>
                                        <button type="button" class="button revoke-certificate" 
                                                data-id="<?php echo $certificate->id; ?>">
                                            ابطال
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.revoke-certificate').on('click', function() {
                if (confirm('آیا از ابطال این گواهی اطمینان دارید؟')) {
                    var button = $(this);
                    var certificate_id = button.data('id');
                    
                    $.post(ajaxurl, {
                        action: 'revoke_certificate',
                        certificate_id: certificate_id,
                        nonce: '<?php echo wp_create_nonce("revoke_certificate"); ?>'
                    }, function(response) {
                        if (response.success) {
                            button.closest('tr').find('td:eq(5)').text('باطل شده');
                            button.remove();
                        } else {
                            alert('خطا در ابطال گواهی');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * بررسی صحت گواهی
     */
    public function verify_certificate() {
        $certificate_number = sanitize_text_field($_POST['certificate_number']);
        
        global $wpdb;
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, u.display_name as student_name, p.post_title as course_title
             FROM {$wpdb->prefix}lms_certificates c
             INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
             INNER JOIN {$wpdb->posts} p ON c.course_id = p.ID
             WHERE c.certificate_number = %s",
            $certificate_number
        ));

        if ($certificate) {
            wp_send_json_success(array(
                'student_name' => $certificate->student_name,
                'course_title' => $certificate->course_title,
                'issue_date' => date_i18n('Y/m/d', strtotime($certificate->issue_date)),
                'status' => $this->get_status_label($certificate->status)
            ));
        } else {
            wp_send_json_error('گواهی معتبر نیست.');
        }
    }

    private function get_status_label($status) {
        $labels = array(
            'active' => 'معتبر',
            'expired' => 'منقضی شده',
            'revoked' => 'باطل شده'
        );
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * دریافت گواهینامه‌های کاربر
     */
    public static function get_user_certificates($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, p.post_title as course_title
             FROM {$wpdb->prefix}lms_certificates c
             INNER JOIN {$wpdb->posts} p ON c.course_id = p.ID
             WHERE c.user_id = %d
             ORDER BY c.issue_date DESC",
            $user_id
        ));
    }
}

new Persian_LMS_Certificate();