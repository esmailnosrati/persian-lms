<?php
class Persian_LMS_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_persian-lms/persian-lms.php', array($this, 'add_settings_link'));
    }

    /**
     * افزودن منوهای مدیریت
     */
    public function add_menu_pages() {
        // منوی تنظیمات کلی
        add_menu_page(
            'سیستم آموزش',
            'سیستم آموزش',
            'manage_options',
            'persian-lms',
            array($this, 'render_settings_page'),
            'dashicons-welcome-learn-more',
            30
        );

        // زیرمنوها
        add_submenu_page(
            'persian-lms',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'persian-lms',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'persian-lms',
            'گزارش‌ها',
            'گزارش‌ها',
            'manage_options',
            'lms-reports',
            array($this, 'render_reports_page')
        );
    }

    /**
     * ثبت تنظیمات
     */
    public function register_settings() {
        // تنظیمات عمومی
        register_setting('persian_lms_general', 'persian_lms_options');

        add_settings_section(
            'persian_lms_general_section',
            'تنظیمات عمومی',
            array($this, 'render_general_section'),
            'persian_lms_general'
        );

        // فیلدهای تنظیمات
        add_settings_field(
            'currency',
            'واحد پول',
            array($this, 'render_currency_field'),
            'persian_lms_general',
            'persian_lms_general_section'
        );

        add_settings_field(
            'payment_gateway',
            'درگاه پرداخت',
            array($this, 'render_payment_gateway_field'),
            'persian_lms_general',
            'persian_lms_general_section'
        );

        add_settings_field(
            'commission_rate',
            'درصد کمیسیون سایت',
            array($this, 'render_commission_rate_field'),
            'persian_lms_general',
            'persian_lms_general_section'
        );

        // تنظیمات ایمیل
        register_setting('persian_lms_email', 'persian_lms_email_options');

        add_settings_section(
            'persian_lms_email_section',
            'تنظیمات ایمیل',
            array($this, 'render_email_section'),
            'persian_lms_email'
        );

        add_settings_field(
            'email_from',
            'ایمیل فرستنده',
            array($this, 'render_email_from_field'),
            'persian_lms_email',
            'persian_lms_email_section'
        );

        add_settings_field(
            'email_templates',
            'قالب‌های ایمیل',
            array($this, 'render_email_templates_field'),
            'persian_lms_email',
            'persian_lms_email_section'
        );
    }

    /**
     * صفحه تنظیمات
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>تنظیمات سیستم آموزش</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=persian-lms&tab=general" 
                   class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                    تنظیمات عمومی
                </a>
                <a href="?page=persian-lms&tab=email" 
                   class="nav-tab <?php echo $active_tab == 'email' ? 'nav-tab-active' : ''; ?>">
                    تنظیمات ایمیل
                </a>
                <a href="?page=persian-lms&tab=appearance" 
                   class="nav-tab <?php echo $active_tab == 'appearance' ? 'nav-tab-active' : ''; ?>">
                    ظاهر
                </a>
                <a href="?page=persian-lms&tab=advanced" 
                   class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">
                    پیشرفته
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php
                if ($active_tab == 'general') {
                    settings_fields('persian_lms_general');
                    do_settings_sections('persian_lms_general');
                } elseif ($active_tab == 'email') {
                    settings_fields('persian_lms_email');
                    do_settings_sections('persian_lms_email');
                } elseif ($active_tab == 'appearance') {
                    $this->render_appearance_settings();
                } elseif ($active_tab == 'advanced') {
                    $this->render_advanced_settings();
                }
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * صفحه گزارش‌ها
     */
    public function render_reports_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $report_type = isset($_GET['type']) ? $_GET['type'] : 'sales';
        $period = isset($_GET['period']) ? $_GET['period'] : '30days';
        ?>
        <div class="wrap">
            <h1>گزارش‌های سیستم آموزش</h1>

            <div class="report-filters">
                <select name="report_type" id="report_type">
                    <option value="sales" <?php selected($report_type, 'sales'); ?>>فروش</option>
                    <option value="enrollments" <?php selected($report_type, 'enrollments'); ?>>ثبت‌نام‌ها</option>
                    <option value="completions" <?php selected($report_type, 'completions'); ?>>تکمیل دوره‌ها</option>
                    <option value="certificates" <?php selected($report_type, 'certificates'); ?>>گواهینامه‌ها</option>
                </select>

                <select name="period" id="period">
                    <option value="7days" <?php selected($period, '7days'); ?>>7 روز گذشته</option>
                    <option value="30days" <?php selected($period, '30days'); ?>>30 روز گذشته</option>
                    <option value="90days" <?php selected($period, '90days'); ?>>90 روز گذشته</option>
                    <option value="year" <?php selected($period, 'year'); ?>>سال جاری</option>
                    <option value="all" <?php selected($period, 'all'); ?>>همه زمان‌ها</option>
                </select>

                <button type="button" class="button" id="generate_report">نمایش گزارش</button>
                <button type="button" class="button" id="export_report">خروجی اکسل</button>
            </div>

            <div class="report-summary">
                <div class="summary-card">
                    <h3>کل فروش</h3>
                    <span class="amount">0 تومان</span>
                </div>
                <div class="summary-card">
                    <h3>تعداد دوره‌ها</h3>
                    <span class="count">0</span>
                </div>
                <div class="summary-card">
                    <h3>تعداد دانشجویان</h3>
                    <span class="count">0</span>
                </div>
                <div class="summary-card">
                    <h3>میانگین تکمیل</h3>
                    <span class="percentage">0%</span>
                </div>
            </div>

            <div class="report-chart">
                <!-- نمودار با Chart.js -->
                <canvas id="reportChart"></canvas>
            </div>

            <div class="report-table">
                <!-- جدول جزئیات -->
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // تابع دریافت داده‌های گزارش
            function fetchReportData() {
                var type = $('#report_type').val();
                var period = $('#period').val();

                $.post(ajaxurl, {
                    action: 'get_lms_report',
                    type: type,
                    period: period,
                    nonce: '<?php echo wp_create_nonce("get_lms_report"); ?>'
                }, function(response) {
                    if (response.success) {
                        updateReportUI(response.data);
                    }
                });
            }

            // بروزرسانی UI
            function updateReportUI(data) {
                // بروزرسانی خلاصه
                $('.summary-card .amount').text(data.total_sales + ' تومان');
                $('.summary-card .count').first().text(data.courses_count);
                $('.summary-card .count').last().text(data.students_count);
                $('.summary-card .percentage').text(data.completion_rate + '%');

                // بروزرسانی نمودار
                updateChart(data.chart_data);

                // بروزرسانی جدول
                updateTable(data.table_data);
            }

            // بروزرسانی نمودار
            function updateChart(chartData) {
                var ctx = document.getElementById('reportChart').getContext('2d');
                if (window.reportChart) {
                    window.reportChart.destroy();
                }
                window.reportChart = new Chart(ctx, {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // رویدادها
            $('#generate_report').on('click', fetchReportData);
            
            $('#export_report').on('click', function() {
                var type = $('#report_type').val();
                var period = $('#period').val();
                
                window.location.href = ajaxurl + '?' + $.param({
                    action: 'export_lms_report',
                    type: type,
                    period: period,
                    nonce: '<?php echo wp_create_nonce("export_lms_report"); ?>'
                });
            });

            // لود اولیه
            fetchReportData();
        });
        </script>
        <?php
    }

    /**
     * بخش تنظیمات عمومی
     */
    public function render_general_section() {
        echo '<p>تنظیمات عمومی سیستم آموزش را از اینجا مدیریت کنید.</p>';
    }

    /**
     * فیلد واحد پول
     */
    public function render_currency_field() {
        $options = get_option('persian_lms_options');
        $currency = isset($options['currency']) ? $options['currency'] : 'IRR';
        ?>
        <select name="persian_lms_options[currency]">
            <option value="IRR" <?php selected($currency, 'IRR'); ?>>ریال</option>
            <option value="TOMAN" <?php selected($currency, 'TOMAN'); ?>>تومان</option>
            <option value="USD" <?php selected($currency, 'USD'); ?>>دلار</option>
        </select>
        <?php
    }

    /**
     * فیلد درگاه پرداخت
     */
    public function render_payment_gateway_field() {
        $options = get_option('persian_lms_options');
        $gateway = isset($options['payment_gateway']) ? $options['payment_gateway'] : 'zarinpal';
        ?>
        <select name="persian_lms_options[payment_gateway]">
            <option value="zarinpal" <?php selected($gateway, 'zarinpal'); ?>>زرین‌پال</option>
            <option value="payir" <?php selected($gateway, 'payir'); ?>>پی</option>
            <option value="idpay" <?php selected($gateway, 'idpay'); ?>>آیدی پی</option>
        </select>
        <?php
    }

    /**
     * فیلد درصد کمیسیون
     */
    public function render_commission_rate_field() {
        $options = get_option('persian_lms_options');
        $rate = isset($options['commission_rate']) ? $options['commission_rate'] : 10;
        ?>
        <input type="number" name="persian_lms_options[commission_rate]" 
               value="<?php echo esc_attr($rate); ?>" min="0" max="100"> %
        <?php
    }

    /**
     * بخش تنظیمات ایمیل
     */
    public function render_email_section() {
        echo '<p>تنظیمات ارسال ایمیل و قالب‌های ایمیل را از اینجا مدیریت کنید.</p>';
    }

    /**
     * فیلد ایمیل فرستنده
     */
    public function render_email_from_field() {
        $options = get_option('persian_lms_email_options');
        $from_email = isset($options['from_email']) ? $options['from_email'] : get_option('admin_email');
        $from_name = isset($options['from_name']) ? $options['from_name'] : get_bloginfo('name');
        ?>
        <input type="email" name="persian_lms_email_options[from_email]" 
               value="<?php echo esc_attr($from_email); ?>" class="regular-text">
        <br>
        <input type="text" name="persian_lms_email_options[from_name]" 
               value="<?php echo esc_attr($from_name); ?>" class="regular-text">
        <p class="description">نام و ایمیل فرستنده برای ایمیل‌های سیستم</p>
        <?php
    }

    /**
     * فیلد قالب‌های ایمیل
     */
    public function render_email_templates_field() {
        $options = get_option('persian_lms_email_options');
        $templates = array(
            'welcome' => 'ایمیل خوش‌آمدگویی',
            'course_complete' => 'تکمیل دوره',
            'certificate' => 'صدور گواهینامه',
            'payment_success' => 'پرداخت موفق',
            'payment_failed' => 'پرداخت ناموفق'
        );

        foreach ($templates as $key => $label) {
            $template = isset($options['templates'][$key]) ? $options['templates'][$key] : '';
            ?>
            <h4><?php echo $label; ?></h4>
            <textarea name="persian_lms_email_options[templates][<?php echo $key; ?>]" 
                      rows="5" class="large-text"><?php echo esc_textarea($template); ?></textarea>
            <p class="description">
                شورتکدهای قابل استفاده: {site_name}, {user_name}, {course_name}, 
                {instructor_name}, {certificate_number}, {amount}
            </p>
            <br>
            <?php
        }
    }

    /**
     * تنظیمات ظاهری
     */
    public function render_appearance_settings() {
        $options = get_option('persian_lms_appearance');
        ?>
        <h2>تنظیمات ظاهری</h2>

        <table class="form-table">
            <tr>
                <th scope="row">طرح رنگی اصلی</th>
                <td>
                    <input type="color" name="persian_lms_appearance[primary_color]" 
                           value="<?php echo esc_attr($options['primary_color'] ?? '#007bff'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">نمایش دوره‌ها</th>
                <td>
                    <select name="persian_lms_appearance[courses_layout]">
                        <option value="grid" <?php selected($options['courses_layout'] ?? 'grid', 'grid'); ?>>شبکه‌ای</option>
                        <option value="list" <?php selected($options['courses_layout'] ?? 'grid', 'list'); ?>>لیستی</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">تعداد دوره در هر صفحه</th>
                <td>
                    <input type="number" name="persian_lms_appearance[courses_per_page]" 
                           value="<?php echo esc_attr($options['courses_per_page'] ?? 12); ?>" 
                           min="1" max="100">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * تنظیمات پیشرفته
     */
    public function render_advanced_settings() {
        $options = get_option('persian_lms_advanced');
        ?>
        <h2>تنظیمات پیشرفته</h2>

        <table class="form-table">
            <tr>
                <th scope="row">حداکثر حجم فایل آپلود (MB)</th>
                <td>
                    <input type="number" name="persian_lms_advanced[max_upload_size]" 
                           value="<?php echo esc_attr($options['max_upload_size'] ?? 64); ?>" 
                           min="1" max="512">
                </td>
            </tr>
            <tr>
                <th scope="row">فرمت‌های مجاز ویدیو</th>
                <td>
                    <?php
                    $allowed_video_formats = $options['allowed_video_formats'] ?? array('mp4', 'webm');
                    $video_formats = array('mp4', 'webm', 'ogg', 'mov');
                    foreach ($video_formats as $format) {
                        ?>
                        <label>
                            <input type="checkbox" name="persian_lms_advanced[allowed_video_formats][]" 
                                   value="<?php echo $format; ?>" 
                                   <?php checked(in_array($format, $allowed_video_formats)); ?>>
                            <?php echo strtoupper($format); ?>
                        </label>
                        <?php
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">کش کردن دوره‌ها</th>
                <td>
                    <label>
                        <input type="checkbox" name="persian_lms_advanced[enable_course_cache]" 
                               value="1" <?php checked($options['enable_course_cache'] ?? 0); ?>>
                        فعال کردن کش برای بهبود سرعت بارگذاری دوره‌ها
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">مدت زمان کش (دقیقه)</th>
                <td>
                    <input type="number" name="persian_lms_advanced[cache_lifetime]" 
                           value="<?php echo esc_attr($options['cache_lifetime'] ?? 60); ?>" 
                           min="1" max="1440">
                </td>
            </tr>
            <tr>
                <th scope="row">حذف داده‌ها</th>
                <td>
                    <label>
                        <input type="checkbox" name="persian_lms_advanced[delete_data]" 
                               value="1" <?php checked($options['delete_data'] ?? 0); ?>>
                        حذف تمام داده‌های افزونه هنگام حذف کامل
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * افزودن فایل‌های CSS و JS مورد نیاز در ادمین
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'persian-lms') === false && 
            strpos($hook, 'lms-reports') === false) {
            return;
        }

        wp_enqueue_style(
            'persian-lms-admin',
            PERSIAN_LMS_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            PERSIAN_LMS_VERSION
        );

        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.7.0'
        );

        wp_enqueue_script(
            'persian-lms-admin',
            PERSIAN_LMS_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery', 'chart-js'),
            PERSIAN_LMS_VERSION,
            true
        );

        wp_localize_script('persian-lms-admin', 'persianLMS', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('persian_lms_admin')
        ));
    }

    /**
     * افزودن لینک تنظیمات به صفحه افزونه‌ها
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=persian-lms'),
            __('تنظیمات', 'persian-lms')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
}

new Persian_LMS_Admin();