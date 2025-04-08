<?php
class Persian_LMS_Payment {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_payment_menu'));
        add_action('wp_ajax_process_payment', array($this, 'process_payment'));
        add_action('wp_ajax_verify_payment', array($this, 'verify_payment'));
    }

    public function init() {
        $this->create_tables();
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lms_payments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            amount decimal(10,0) NOT NULL,
            payment_method varchar(50) NOT NULL,
            transaction_id varchar(100),
            status varchar(20) DEFAULT 'pending',
            payment_date datetime DEFAULT CURRENT_TIMESTAMP,
            gateway_response text,
            metadata text,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY transaction_id (transaction_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * افزودن منوی مدیریت پرداخت‌ها
     */
    public function add_payment_menu() {
        add_submenu_page(
            'edit.php?post_type=lms_course',
            'پرداخت‌ها',
            'پرداخت‌ها',
            'manage_options',
            'lms-payments',
            array($this, 'render_payments_page')
        );
    }

    /**
     * صفحه مدیریت پرداخت‌ها
     */
    public function render_payments_page() {
        global $wpdb;

        // جستجو و فیلتر
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

        $query = "SELECT p.*, u.display_name as user_name, c.post_title as course_title
                 FROM {$wpdb->prefix}lms_payments p
                 INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
                 INNER JOIN {$wpdb->posts} c ON p.course_id = c.ID
                 WHERE 1=1";

        if ($search) {
            $query .= $wpdb->prepare(
                " AND (u.display_name LIKE %s OR c.post_title LIKE %s OR p.transaction_id LIKE %s)",
                "%$search%",
                "%$search%",
                "%$search%"
            );
        }

        if ($status) {
            $query .= $wpdb->prepare(" AND p.status = %s", $status);
        }

        if ($course_id) {
            $query .= $wpdb->prepare(" AND p.course_id = %d", $course_id);
        }

        $query .= " ORDER BY p.payment_date DESC";

        $payments = $wpdb->get_results($query);
        ?>
        <div class="wrap">
            <h1>مدیریت پرداخت‌ها</h1>

            <form method="get">
                <input type="hidden" name="post_type" value="lms_course">
                <input type="hidden" name="page" value="lms-payments">
                
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="status">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="pending" <?php selected($status, 'pending'); ?>>در انتظار پرداخت</option>
                            <option value="completed" <?php selected($status, 'completed'); ?>>پرداخت شده</option>
                            <option value="failed" <?php selected($status, 'failed'); ?>>ناموفق</option>
                            <option value="refunded" <?php selected($status, 'refunded'); ?>>برگشت داده شده</option>
                        </select>

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
                                   placeholder="جستجوی پرداخت...">
                            <input type="submit" class="button" value="جستجو">
                        </p>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>شماره تراکنش</th>
                            <th>کاربر</th>
                            <th>دوره</th>
                            <th>مبلغ</th>
                            <th>روش پرداخت</th>
                            <th>تاریخ</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment) : ?>
                            <tr>
                                <td><?php echo $payment->transaction_id ?: '---'; ?></td>
                                <td><?php echo $payment->user_name; ?></td>
                                <td><?php echo $payment->course_title; ?></td>
                                <td><?php echo number_format($payment->amount); ?> تومان</td>
                                <td><?php echo $this->get_payment_method_label($payment->payment_method); ?></td>
                                <td><?php echo date_i18n('Y/m/d H:i', strtotime($payment->payment_date)); ?></td>
                                <td><?php echo $this->get_status_label($payment->status); ?></td>
                                <td>
                                    <button type="button" class="button view-payment-details" 
                                            data-id="<?php echo $payment->id; ?>">
                                        جزئیات
                                    </button>
                                    <?php if ($payment->status === 'completed') : ?>
                                        <button type="button" class="button refund-payment" 
                                                data-id="<?php echo $payment->id; ?>">
                                            برگشت وجه
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- مودال جزئیات پرداخت -->
        <div id="payment-details-modal" style="display: none;">
            <div class="payment-details-content"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // نمایش جزئیات پرداخت
            $('.view-payment-details').on('click', function() {
                var payment_id = $(this).data('id');
                
                $.post(ajaxurl, {
                    action: 'get_payment_details',
                    payment_id: payment_id,
                    nonce: '<?php echo wp_create_nonce("payment_details"); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#payment-details-modal .payment-details-content').html(response.data);
                        $('#payment-details-modal').dialog({
                            title: 'جزئیات پرداخت',
                            width: 600,
                            modal: true
                        });
                    }
                });
            });

            // برگشت وجه
            $('.refund-payment').on('click', function() {
                if (confirm('آیا از برگشت وجه اطمینان دارید؟')) {
                    var payment_id = $(this).data('id');
                    
                    $.post(ajaxurl, {
                        action: 'refund_payment',
                        payment_id: payment_id,
                        nonce: '<?php echo wp_create_nonce("refund_payment"); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('خطا در برگشت وجه: ' + response.data);
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * پردازش پرداخت جدید
     */
    public function process_payment() {
        check_ajax_referer('process_payment', 'nonce');

        $course_id = intval($_POST['course_id']);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error('لطفاً ابتدا وارد سایت شوید.');
        }

        // بررسی قیمت دوره
        $price = get_post_meta($course_id, '_course_price', true);
        if (!$price) {
            wp_send_json_error('خطا در دریافت قیمت دوره.');
        }

        // ایجاد تراکنش جدید
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'lms_payments',
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'amount' => $price,
                'payment_method' => 'online',
                'status' => 'pending',
                'payment_date' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );

        if (!$result) {
            wp_send_json_error('خطا در ایجاد تراکنش.');
        }

        $payment_id = $wpdb->insert_id;

        // اتصال به درگاه پرداخت
        $gateway = $this->get_payment_gateway();
        $payment_url = $gateway->get_payment_url(array(
            'amount' => $price,
            'payment_id' => $payment_id,
            'user_id' => $user_id,
            'course_id' => $course_id
        ));

        if ($payment_url) {
            wp_send_json_success($payment_url);
        } else {
            wp_send_json_error('خطا در اتصال به درگاه پرداخت.');
        }
    }

    /**
     * تایید پرداخت
     */
    public function verify_payment() {
        $payment_id = intval($_GET['payment_id']);
        $gateway = $this->get_payment_gateway();
        
        global $wpdb;
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}lms_payments WHERE id = %d",
            $payment_id
        ));

        if (!$payment) {
            wp_die('تراکنش نامعتبر است.');
        }

        // بررسی وضعیت پرداخت در درگاه
        $verification = $gateway->verify_payment($_GET);

        if ($verification['status']) {
            // بروزرسانی وضعیت پرداخت
            $wpdb->update(
                $wpdb->prefix . 'lms_payments',
                array(
                    'status' => 'completed',
                    'transaction_id' => $verification['transaction_id'],
                    'gateway_response' => json_encode($verification)
                ),
                array('id' => $payment_id),
                array('%s', '%s', '%s'),
                array('%d')
            );

            // ثبت‌نام کاربر در دوره
            Persian_LMS_Course::enroll_student($payment->course_id, $payment->user_id, $payment_id);

            // ریدایرکت به صفحه موفقیت
            wp_redirect(add_query_arg('payment_status', 'success', get_permalink($payment->course_id)));
            exit;
        } else {
            // بروزرسانی وضعیت به ناموفق
            $wpdb->update(
                $wpdb->prefix . 'lms_payments',
                array(
                    'status' => 'failed',
                    'gateway_response' => json_encode($verification)
                ),
                array('id' => $payment_id),
                array('%s', '%s'),
                array('%d')
            );

            // ریدایرکت به صفحه خطا
            wp_redirect(add_query_arg('payment_status', 'failed', get_permalink($payment->course_id)));
            exit;
        }
    }

    /**
     * دریافت درگاه پرداخت
     */
    private function get_payment_gateway() {
        // اینجا می‌توانید درگاه پرداخت مورد نظر خود را پیاده‌سازی کنید
        require_once(PERSIAN_LMS_PLUGIN_DIR . 'includes/gateways/class-zarinpal.php');
        return new Persian_LMS_Gateway_Zarinpal();
    }

    private function get_payment_method_label($method) {
        $methods = array(
            'online' => 'پرداخت آنلاین',
            'wallet' => 'کیف پول',
            'manual' => 'پرداخت دستی'
        );
        return isset($methods[$method]) ? $methods[$method] : $method;
    }

    private function get_status_label($status) {
        $statuses = array(
            'pending' => 'در انتظار پرداخت',
            'completed' => 'پرداخت شده',
            'failed' => 'ناموفق',
            'refunded' => 'برگشت داده شده'
        );
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }

    /**
     * دریافت پرداخت‌های کاربر
     */
    public static function get_user_payments($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, c.post_title as course_title
             FROM {$wpdb->prefix}lms_payments p
             INNER JOIN {$wpdb->posts} c ON p.course_id = c.ID
             WHERE p.user_id = %d
             ORDER BY p.payment_date DESC",
            $user_id
        ));
    }

    /**
     * محاسبه درآمد مدرس
     */
    public static function calculate_instructor_earnings($instructor_id, $period = 'all') {
        global $wpdb;
        
        $query = "SELECT SUM(p.amount) as total
                 FROM {$wpdb->prefix}lms_payments p
                 INNER JOIN {$wpdb->posts} c ON p.course_id = c.ID
                 WHERE c.post_author = %d 
                 AND p.status = 'completed'";
        
        if ($period === 'month') {
            $query .= " AND MONTH(p.payment_date) = MONTH(CURRENT_DATE())
                       AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())";
        } elseif ($period === 'year') {
            $query .= " AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())";
        }
        
        return (float) $wpdb->get_var($wpdb->prepare($query, $instructor_id));
    }

    /**
     * ایجاد تسویه حساب جدید
     */
    public static function create_payout($instructor_id, $amount, $method = 'bank') {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'lms_payouts',
            array(
                'instructor_id' => $instructor_id,
                'amount' => $amount,
                'method' => $method,
                'status' => 'pending',
                'request_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }
}

new Persian_LMS_Payment();