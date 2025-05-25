<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'CoinpayHelperClass.php';
define('WC_CPAL_TEXT_DOMAIN', 'wc-cpal');

add_action('plugins_loaded', 'load_coinpay_textdomain');
function load_coinpay_textdomain() {
    load_plugin_textdomain(WC_CPAL_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

function Load_CoinPay_Gateway() {
    if (!function_exists('Woocommerce_Add_CoinPay_Gateway') && class_exists('WC_Payment_Gateway') && !class_exists('WC_CPal')) {
        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_CoinPay_Gateway');
        function Woocommerce_Add_CoinPay_Gateway($methods) {
            $methods[] = 'WC_COINPAY';
            return $methods;
        }
//        add_filter('woocommerce_currencies', 'add_IR_currency');
//        if (!function_exists('add_IR_currency')){
//            function add_IR_currency($currencies) {
//                $currencies['IRR'] = __('ریال', WC_CPAL_TEXT_DOMAIN);
//                $currencies['IRT'] = __('تومان', WC_CPAL_TEXT_DOMAIN);
//                $currencies['IRHR'] = __('هزار ریال', WC_CPAL_TEXT_DOMAIN);
//                $currencies['IRHT'] = __('هزار تومان', WC_CPAL_TEXT_DOMAIN);
//                return $currencies;
//            }
//        }
//        add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol', 10, 2);
//        if (!function_exists('add_IR_currency_symbol')){
//            function add_IR_currency_symbol($currency_symbol, $currency) {
//                switch ($currency) {
//                    case 'IRR':
//                        $currency_symbol = 'ریال';
//                        break;
//                    case 'IRT':
//                        $currency_symbol = 'تومان';
//                        break;
//                    case 'IRHR':
//                        $currency_symbol = 'هزار ریال';
//                        break;
//                    case 'IRHT':
//                        $currency_symbol = 'هزار تومان';
//                        break;
//                }
//                return $currency_symbol;
//            }
//        }
        class WC_COINPAY extends WC_Payment_Gateway {
            public $merchantCode;
            public $sandbox;
            public $successMessage;
            public $failedMessage;
            public $trustLogo;
            public $coinpay;
            public $instructions;
            public $accessToken;
            public function __construct() {
                $this->id = 'WC_COINPAY';
                $this->method_title = __('پرداخت امن کوین‌پی', WC_CPAL_TEXT_DOMAIN);
                $this->method_description = __('تنظیمات درگاه پرداخت کوین‌پی برای افزونه فروشگاه ساز ووکامرس', WC_CPAL_TEXT_DOMAIN);
                $this->icon = apply_filters('WC_CPal_logo', plugin_dir_url(__FILE__) . 'assets/images/logo.svg');
                $this->has_fields = true;
                $this->supports = array(
                    'products',
                    'tokenization',
                    'refunds',
                    'subscriptions',
                    'subscription_cancellation',
                    'subscription_suspension',
                    'subscription_reactivation',
                    'subscription_amount_changes',
                    'subscription_date_changes',
                    'subscription_payment_method_change',
                    'pre-orders',
                    'wc-blocks',
                );
                $this->init_form_fields();
                $this->init_settings();
                $this->enabled = $this->get_option('enabled');

                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->merchantCode = $this->get_option('merchantcode');
                $this->sandbox = ($this->get_option('sandbox') === 'yes') ? true : false;
                $this->successMessage = $this->get_option('success_message');
                $this->failedMessage = $this->get_option('failed_message');
                $this->instructions = $this->get_option('instructions');
                $this->trustLogo = $this->get_option('trust_logo');
                $this->accessToken = $this->sanitize_access_token($this->get_option('access_token'));
                $this->order_button_text = __('پرداخت با کوین‌پی', WC_CPAL_TEXT_DOMAIN);
                $this->coinpay = new CoinpayHelperClass($this->merchantCode, $this->sandbox, $this->accessToken);
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_receipt_' . $this->id, array($this, 'Send_to_CoinPay_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'Return_from_CoinPay_Gateway'));
                add_action('woocommerce_email_after_order_table', array($this, 'email_instructions'), 10, 3);
                add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
                add_action('woocommerce_order_status_refunded', array($this, 'process_refund'), 10, 2);
                if (is_admin() && $this->sandbox) {
                    add_action('admin_bar_menu', array($this, 'add_sandbox_notice_to_admin_bar'), 100);
                }
                add_action('admin_notices', array($this, 'admin_notice_missing_merchantcode'));
                add_action('admin_notices', array($this, 'admin_notice_missing_accesstoken'));
            }
            public function init_form_fields() {
                $this->form_fields = apply_filters(
                    'WC_CPal_Config',
                    array(
                        'base_config' => array(
                            'title' => __('تنظیمات پایه ای', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه کوین‌پی', WC_CPAL_TEXT_DOMAIN),
                            'description' => __('برای فعالسازی درگاه پرداخت کوین‌پی باید چک باکس را تیک بزنید', WC_CPAL_TEXT_DOMAIN),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده می‌شود', WC_CPAL_TEXT_DOMAIN),
                            'default' => __('پرداخت امن کوین‌پی', WC_CPAL_TEXT_DOMAIN),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'textarea',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', WC_CPAL_TEXT_DOMAIN),
                            'default' => __('پرداخت امن به وسیله کلیه کارت‌های عضو شتاب از طریق درگاه کوین‌پی', WC_CPAL_TEXT_DOMAIN),
                        ),
                        'account_config' => array(
                            'title' => __('تنظیمات حساب کوین‌پی', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'merchantcode' => array(
                            'title' => __('مرچنت کد', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'text',
                            'description' => __('مرچنت کد درگاه کوین‌پی', WC_CPAL_TEXT_DOMAIN),
                            'default' => '',
                            'desc_tip' => true,
                        ),
                        'access_token' => array(
                            'title' => __('توکن دسترسی (اختیاری ویژه سرویس استرداد وجه)', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'password',
                            'description' => __('توکن دسترسی برای استفاده از API گراف‌کیوال کوین‌پی', WC_CPAL_TEXT_DOMAIN),
                            'default' => '',
                            'desc_tip' => true,
                        ),
                        'sandbox' => array(
                            'title' => __('حالت آزمایشی (Sandbox)', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی حالت آزمایشی', WC_CPAL_TEXT_DOMAIN),
                            'description' => __('برای تست درگاه پرداخت از حالت آزمایشی استفاده کنید.', WC_CPAL_TEXT_DOMAIN),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'payment_config' => array(
                            'title' => __('تنظیمات عملیات پرداخت', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_message' => array(
                            'title' => __('پیام پرداخت موفق', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که می‌خواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید. می‌توانید از شورت کد {transaction_id} برای نمایش کد رهگیری استفاده کنید.', WC_CPAL_TEXT_DOMAIN),
                            'default' => __('با تشکر از شما. سفارش شما با موفقیت پرداخت شد.', WC_CPAL_TEXT_DOMAIN),
                        ),
                        'failed_message' => array(
                            'title' => __('پیام پرداخت ناموفق', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که می‌خواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید. می‌توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده کنید.', WC_CPAL_TEXT_DOMAIN),
                            'default' => __('پرداخت شما ناموفق بوده است. لطفاً مجدداً تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.', WC_CPAL_TEXT_DOMAIN),
                        ),
                        'instructions' => array(
                            'title' => __('توضیحات پس از خرید', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'textarea',
                            'description' => __('دستورالعمل‌هایی که پس از تکمیل پرداخت به مشتری نمایش داده می‌شود.', WC_CPAL_TEXT_DOMAIN),
                            'default' => '',
                            'desc_tip' => true,
                        ),
                        'trust_logo' => array(
                            'title' => __('کد تراست لوگوی کوین‌پی', WC_CPAL_TEXT_DOMAIN),
                            'type' => 'trust_logo',
                            'description' => __('کد تراست لوگوی کوین‌پی را کپی نمایید و در فوتر سایت خود قرار دهید.', WC_CPAL_TEXT_DOMAIN),
                            'default' => '<style>#coinpay{margin:auto} #coinpay img {width: 80px;}</style>
                            <div id="coinpay"><script src="https://www.coinpay.com/webservice/TrustCode" type="text/javascript"></script></div>',
                        ),
                    )
                );
            }
            public function admin_options() {
                echo '<h3>' . __('درگاه پرداخت کوین‌پی', WC_CPAL_TEXT_DOMAIN) . '</h3>';
                echo '<p>' . __('تنظیمات درگاه پرداخت کوین‌پی برای ووکامرس', WC_CPAL_TEXT_DOMAIN) . '</p>';
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            }
            public function get_icon() {
                $icon = '<img src="' . plugin_dir_url(__FILE__) . 'assets/images/logo.svg" alt="کوین‌پی" />';
                return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
            }
            public function payment_fields() {
                if ($this->description) {
                    echo wpautop(wptexturize($this->description));
                }
                echo $this->trustLogo;
            }
            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true),
                );
            }
            public function tokenization_script() {
                if (!$this->supports('tokenization')) {
                    return;
                }
                wp_enqueue_script('wc-credit-card-form');
            }
            public function save_payment_method_checkbox() {
                ?>
                <p class="form-row">
                    <label for="wc-<?php echo esc_attr($this->id); ?>-new-payment-method">
                        <input id="wc-<?php echo esc_attr($this->id); ?>-new-payment-method" name="wc-<?php echo esc_attr($this->id); ?>-new-payment-method" type="checkbox" value="true" style="width:auto;" /> <?php esc_html_e('ذخیره روش پرداخت برای خریدهای بعدی', WC_CPAL_TEXT_DOMAIN); ?>
                    </label>
                </p>
                <?php
            }
            public function Send_to_CoinPay_Gateway($order_id) {
                $order = wc_get_order($order_id);
                $currency = $order->get_currency();
                $amount = intval($order->get_total());
                $currency = strtolower($currency);
                if ($currency === 'irht') {
                    $amount *= 10000;
                } elseif ($currency === 'irhr') {
                    $amount *= 1000;
                } elseif ($currency === 'irt') {
                    $amount *= 10;
                }
                $callback_url = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_CPal'));
                $description = 'خرید به شماره سفارش: ' . $order->get_order_number();
                $description .= ' | خریدار: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $mobile = $order->get_billing_phone();
                $email = $order->get_billing_email();
                $cart_items = array();
                foreach ($order->get_items() as $item_id => $item) {
                    $product_name = $item->get_name();
                    $quantity = $item->get_quantity();
                    $total = $item->get_total();
                    $subtotal = $item->get_subtotal();
                    $unit_price = $subtotal / $quantity;
                    $cart_items[] = array(
                        'product_name' => $product_name,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total' => $total,
                    );
                }
                $discount_total = $order->get_discount_total();
                $total_amount = $order->get_total();
                $cart_data = array(
                    'items' => $cart_items,
                    'discount' => $discount_total,
                    'total' => $total_amount,
                );
                $cart_json = json_encode($cart_data);
                $referrer_id = get_option('wc_cpal_referrer_id', null);
                $metadata = array(
                    'email' => $email,
                    'mobile' => $mobile,
                );
                try {
                    $authority = $this->coinpay->requestPayment(
                        $amount,
                        $callback_url,
                        $description,
                        $metadata,
                        $cart_json,
                        $referrer_id
                    );
                    $order->update_meta_data('_coinpay_authority', $authority);
                    $order->save();
                    $note = sprintf(__('کاربر به درگاه پرداخت هدایت شد. شناسه تراکنش: %s', WC_CPAL_TEXT_DOMAIN), $authority);
                    $order->add_order_note($note);
                    wp_redirect($this->coinpay->getRedirectUrl($authority));
                    exit;
                } catch (Exception $e) {
                    wc_add_notice(__('خطا در اتصال به درگاه پرداخت: ', WC_CPAL_TEXT_DOMAIN) . $e->getMessage(), 'error');
                    return;
                }
            }
            public function Return_from_CoinPay_Gateway() {
                global $woocommerce;
                $order_id = isset($_GET['wc_order']) ? sanitize_text_field($_GET['wc_order']) : 0;
                $order = wc_get_order($order_id);
                if (!$order) {
                    wc_add_notice(__('سفارش پیدا نشد.', WC_CPAL_TEXT_DOMAIN), 'error');
                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
                if (isset($_GET['Status']) && $_GET['Status'] === 'OK') {
                    $authority = sanitize_text_field($_GET['Authority']);
                    $amount = intval($order->get_total());
                    $currency = $order->get_currency();
                    $currency = strtolower($currency);
                    if ($currency === 'irht') {
                        $amount *= 10000;
                    } elseif ($currency === 'irhr') {
                        $amount *= 1000;
                    } elseif ($currency === 'irt') {
                        $amount *= 10;
                    }
                    try {
                        $response = $this->coinpay->verifyPayment($authority, $amount);
                        if ($response['code'] == 100) {
                            $transaction_id = $response['ref_id'];
                            $order->payment_complete($transaction_id);
                            $order->add_order_note(sprintf(__('پرداخت با موفقیت انجام شد. کد رهگیری: %s', WC_CPAL_TEXT_DOMAIN), $transaction_id));
                            wc_add_notice(str_replace('{transaction_id}', $transaction_id, $this->successMessage), 'success');
                            $woocommerce->cart->empty_cart();
                            wp_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            throw new Exception('تراکنش ناموفق بود.');
                        }
                    } catch (Exception $e) {
                        wc_add_notice(str_replace('{fault}', $e->getMessage(), $this->failedMessage), 'error');
                        wp_redirect(wc_get_checkout_url());
                        exit;
                    }
                } else {
                    wc_add_notice(str_replace('{fault}', 'تراکنش توسط کاربر لغو شد.', $this->failedMessage), 'error');
                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
            }
            public function process_refund($order_id, $amount = null, $reason = 'CUSTOMER_REQUEST') {
                $order = wc_get_order($order_id);
                if (!$amount) {
                    return new WP_Error('invalid_amount', __('مبلغ استرداد معتبر نیست.', WC_CPAL_TEXT_DOMAIN));
                }
                $settings = get_option('woocommerce_WC_CPal_settings');
                $accessToken = isset($settings['access_token']) ? $settings['access_token'] : '';
                if (empty($accessToken)) {
                    return new WP_Error('no_access_token', __('برای استفاده از این سرویس باید توکن دسترسی خود را در تنظیمات درگاه کوین پی وارد نمایید.', WC_CPAL_TEXT_DOMAIN));
                }
                $authority = $order->get_meta('_coinpay_authority');
                if (!$authority) {
                    return new WP_Error('no_authority', __('شناسه تراکنش کوین‌پی برای این سفارش یافت نشد.', WC_CPAL_TEXT_DOMAIN));
                }
                try {
                    $transactions = $this->coinpay->getTransactions($authority);
                    if (!empty($transactions)) {
                        $transaction_info = $transactions[0];
                        $transaction_id = $transaction_info['id'];
                        $refund = $this->coinpay->refundPayment($transaction_id, $amount * 10, $reason);
                        $order->add_order_note(sprintf(__('استرداد مبلغ %s انجام شد.', WC_CPAL_TEXT_DOMAIN), wc_price($amount)));
                        return true;
                    } else {
                        return new WP_Error('transaction_not_found', __('تراکنش مرتبط یافت نشد.', WC_CPAL_TEXT_DOMAIN));
                    }
                } catch (Exception $e) {
                    return new WP_Error('refund_error', $e->getMessage());
                }
            }
            public function get_transaction_url($order) {
                $transaction_id = $order->get_meta('_coinpay_authority');
                if ($transaction_id) {
                    $url = add_query_arg(array(
                        'action' => 'cpal_transaction_info',
                        'transaction_id' => $transaction_id,
                        'order_id' => $order->get_id(),
                    ), admin_url('admin-ajax.php'));
                    return $url;
                }
                return '';
            }
            public function email_instructions($order, $sent_to_admin, $plain_text = false) {
                if ($order->get_payment_method() !== $this->id || $sent_to_admin) {
                    return;
                }
                if ($this->instructions) {
                    echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
                }
            }
            public function thankyou_page() {
                if ($this->instructions) {
                    echo wpautop(wptexturize($this->instructions));
                }
            }
            public function add_sandbox_notice_to_admin_bar($wp_admin_bar) {
                if (!current_user_can('manage_options')) {
                    return;
                }
                $message = sprintf(
                    __('درگاه کوین‌پی در حالت آزمایشی (Sandbox) فعال است. پرداخت‌های واقعی انجام نخواهند شد. برای تغییر این حالت، به تنظیمات درگاه <a href="%s">اینجا</a> مراجعه کنید.', WC_CPAL_TEXT_DOMAIN),
                    admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_cpal')
                );
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>' . $message . '</p>';
                echo '</div>';
            }
            public function generate_trust_logo_html($key, $data) {
                $field = wp_parse_args($data, array(
                    'title' => '',
                    'description' => '',
                    'default' => '',
                ));
                ob_start();
                ?>
                <tr valign="top">
                    <th scope="row" class="titledesc"><?php echo esc_html($field['title']); ?></th>
                    <td class="forminp">
                        <textarea readonly style="direction: ltr; white-space: pre-wrap; width: 100%; height: 100px;"><?php echo esc_textarea($field['default']); ?></textarea>
                        <br/><?php echo wp_kses_post($field['description']); ?>
                    </td>
                </tr>
                <?php
                return ob_get_clean();
            }
            public function admin_notice_missing_merchantcode() {
                $merchantcode = $this->get_option('merchantcode');
                if (empty($merchantcode) && 'yes' === $this->get_option('enabled')) {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p>' . __('مرچنت کد درگاه کوین‌پی خالی است. لطفاً آن را در تنظیمات درگاه وارد نمایید.', WC_CPAL_TEXT_DOMAIN) . '</p>';
                    echo '</div>';
                }
            }
            public function admin_notice_missing_accesstoken() {
                $accesstoken = $this->get_option('access_token');
                if (empty($accesstoken) && 'yes' === $this->get_option('enabled')) {
                }
            }
            private function sanitize_access_token($token) {
                if (strpos($token, 'Bearer ') === 0) {
                    return substr($token, 7);
                }
                return $token;
            }
            public function is_available() {
                return true;
            }

        }
    }
}
add_action('plugins_loaded', 'Load_CoinPay_Gateway', 11);

add_action('wp_ajax_cpal_transaction_info', 'cpal_display_transaction_info');
add_action('wp_ajax_nopriv_cpal_transaction_info', 'cpal_display_transaction_info');
function cpal_display_transaction_info() {
    function gregorian_to_jalali($g_y, $g_m, $g_d) {
        $g_days_in_month = [31,28,31,30,31,30,31,31,30,31,30,31];
        $j_days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];
        $gy = $g_y - 1600;
        $gm = $g_m - 1;
        $gd = $g_d - 1;
        $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
        for ($i = 0; $i < $gm; ++$i)
            $g_day_no += $g_days_in_month[$i];
        if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)))
            $g_day_no++;
        $g_day_no += $gd;
        $j_day_no = $g_day_no - 79;
        $j_np = floor($j_day_no / 12053);
        $j_day_no = $j_day_no % 12053;
        $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
        $j_day_no %= 1461;
        if ($j_day_no >= 366) {
            $jy += floor(($j_day_no - 366) / 365);
            $j_day_no = ($j_day_no - 366) % 365;
        }
        for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i)
            $j_day_no -= $j_days_in_month[$i];
        $jm = $i + 1;
        $jd = $j_day_no + 1;
        return [$jy, $jm, $jd];
    }
    function format_jalali_date($date_str) {
        if (empty($date_str)) {
            return '-';
        }
        $date = new DateTime($date_str);
        $g_y = (int)$date->format('Y');
        $g_m = (int)$date->format('m');
        $g_d = (int)$date->format('d');
        list($j_y, $j_m, $j_d) = gregorian_to_jalali($g_y, $g_m, $g_d);
        return sprintf('%04d/%02d/%02d %02d:%02d:%02d', $j_y, $j_m, $j_d, $date->format('H'), $date->format('i'), $date->format('s'));
    }
    $transaction_id = isset($_GET['transaction_id']) ? sanitize_text_field($_GET['transaction_id']) : '';
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    if (!current_user_can('manage_woocommerce') || empty($transaction_id) || empty($order_id)) {
        wp_die(__('شما دسترسی لازم برای مشاهده این اطلاعات را ندارید.', WC_CPAL_TEXT_DOMAIN));
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die(__('سفارش پیدا نشد.', WC_CPAL_TEXT_DOMAIN));
    }
    $settings = get_option('woocommerce_WC_CPal_settings');
    $merchantCode = isset($settings['merchantcode']) ? $settings['merchantcode'] : '';
    $sandbox = (isset($settings['sandbox']) && $settings['sandbox'] === 'yes');
    $accessToken = isset($settings['access_token']) ? $settings['access_token'] : '';
    if (empty($accessToken)) {
        echo '<p style="color:red; text-align:center;">' . __('برای استفاده از این سرویس باید توکن دسترسی خود را در تنظیمات درگاه کوین پی وارد نمایید.', WC_CPAL_TEXT_DOMAIN) . '</p>';
        exit;
    }
    $coinpay = new CoinpayHelperClass($merchantCode, $sandbox, $accessToken);
    try {
        $authority = $order->get_meta('_coinpay_authority');
        if (empty($authority)) {
            wp_die(__('کد آتوریتی برای این سفارش یافت نشد.', WC_CPAL_TEXT_DOMAIN));
        }
        $transactions = $coinpay->getTransactions($authority);
        if ($transactions) {
            $transaction_info = $transactions[0];
            echo '<style>
                .transaction-container {
                    direction: rtl;
                    font-family: Tahoma, Arial, sans-serif;
                    margin: 20px auto;
                    max-width: 800px;
                    padding: 20px;
                    background-color: #f9f9f9;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    border-radius: 8px;
                }
                .transaction-title {
                    text-align: center;
                    margin-bottom: 20px;
                    color: #333;
                    font-size: 24px;
                    border-bottom: 2px solid #ddd;
                    padding-bottom: 10px;
                }
                .transaction-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .transaction-table th, .transaction-table td {
                    padding: 12px;
                    border: 1px solid #ddd;
                    text-align: right;
                }
                .transaction-table th {
                    background-color: #f2f2f2;
                    width: 30%;
                    font-weight: bold;
                }
                .transaction-table tr:nth-child(even) td {
                    background-color: #f5f5f5;
                }
                </style>';
            echo '<div class="transaction-container">';
            echo '<h2 class="transaction-title">' . __('اطلاعات تراکنش', WC_CPAL_TEXT_DOMAIN) . '</h2>';
            echo '<table class="transaction-table">';
            function render_row($title, $value) {
                echo '<tr>';
                echo '<th>' . $title . '</th>';
                echo '<td>' . $value . '</td>';
                echo '</tr>';
            }
            render_row(__('شناسه پیگیری', WC_CPAL_TEXT_DOMAIN), esc_html($transaction_info['id']));
            render_row(__('کد مرجع', WC_CPAL_TEXT_DOMAIN), esc_html($transaction_info['reference_id'] ?? '-'));
            render_row(__('کد آتوریتی', WC_CPAL_TEXT_DOMAIN), esc_html($transaction_info['authority'] ?? '-'));
            render_row(__('وضعیت تراکنش', WC_CPAL_TEXT_DOMAIN), esc_html($transaction_info['status']));
            render_row(__('مبلغ', WC_CPAL_TEXT_DOMAIN), esc_html(number_format($transaction_info['amount']) . ' ریال'));
            render_row(__('کارمزد', WC_CPAL_TEXT_DOMAIN), esc_html(number_format($transaction_info['fee']) . ' ریال'));
            render_row(__('توضیحات', WC_CPAL_TEXT_DOMAIN), esc_html($transaction_info['description']));
            $shamsi_date_created = !empty($transaction_info['created_at']) ? format_jalali_date($transaction_info['created_at']) : '-';
            render_row(__('تاریخ ایجاد', WC_CPAL_TEXT_DOMAIN), esc_html($shamsi_date_created));
            $shamsi_date_reconciled = !empty($transaction_info['reconciled_at']) ? format_jalali_date($transaction_info['reconciled_at']) : '-';
            render_row(__('تاریخ تسویه', WC_CPAL_TEXT_DOMAIN), esc_html($shamsi_date_reconciled));
            $session_tries = $transaction_info['session_tries'];
            if (!empty($session_tries)) {
                $first_try = $session_tries[0];
                $card_pan = isset($first_try['card_pan']) ? $first_try['card_pan'] : '-';
                $rrn = isset($first_try['rrn']) ? $first_try['rrn'] : '-';
                $payer_ip = isset($first_try['payer_ip']) ? $first_try['payer_ip'] : '-';
            } else {
                $card_pan = '-';
                $rrn = '-';
                $payer_ip = '-';
            }
            render_row(__('شماره کارت', WC_CPAL_TEXT_DOMAIN), esc_html($card_pan));
            render_row(__('RRN', WC_CPAL_TEXT_DOMAIN), esc_html($rrn));
            render_row(__('آیپی پرداخت کننده', WC_CPAL_TEXT_DOMAIN), esc_html($payer_ip));
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p>' . __('اطلاعاتی برای این تراکنش یافت نشد.', WC_CPAL_TEXT_DOMAIN) . '</p>';
        }
    } catch (Exception $e) {
        echo '<p>' . __('خطا در دریافت اطلاعات تراکنش: ', WC_CPAL_TEXT_DOMAIN) . esc_html($e->getMessage()) . '</p>';
    }
    exit;
}

add_action('wp_ajax_cpal_manual_verify', 'cpal_manual_verify_transaction');
function cpal_manual_verify_transaction() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('شما دسترسی لازم برای انجام این عملیات را ندارید.', WC_CPAL_TEXT_DOMAIN));
    }
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_die(__('سفارش یافت نشد.', WC_CPAL_TEXT_DOMAIN));
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die(__('سفارش یافت نشد.', WC_CPAL_TEXT_DOMAIN));
    }
    $settings = get_option('woocommerce_WC_CPal_settings');
    $merchantCode = isset($settings['merchantcode']) ? $settings['merchantcode'] : '';
    $sandbox = (isset($settings['sandbox']) && $settings['sandbox'] === 'yes');
    $accessToken = isset($settings['access_token']) ? $settings['access_token'] : '';
    $coinpay = new CoinpayHelperClass($merchantCode, $sandbox, $accessToken);
    $authority = $order->get_meta('_coinpay_authority');
    if (empty($authority)) {
        wp_die(__('کد آتوریتی برای این سفارش یافت نشد.', WC_CPAL_TEXT_DOMAIN));
    }
    $amount = intval($order->get_total());
    $currency = strtolower($order->get_currency());
    if ($currency === 'irht') {
        $amount *= 10000;
    } elseif ($currency === 'irhr') {
        $amount *= 1000;
    } elseif ($currency === 'irt') {
        $amount *= 10;
    }
    try {
        $response = $coinpay->verifyPayment($authority, $amount);
        if ($response['code'] == 100) {
            $transaction_id = $response['ref_id'];
            if (!$order->is_paid()) {
                $order->payment_complete($transaction_id);
            }
            $order->add_order_note(sprintf(__('پرداخت با موفقیت انجام شد. کد رهگیری: %s', WC_CPAL_TEXT_DOMAIN), $transaction_id));
            $message = sprintf(__('پرداخت با موفقیت انجام شد. کد رهگیری: %s', WC_CPAL_TEXT_DOMAIN), $transaction_id);
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        } elseif ($response['code'] == 101) {
            $message = __('تراکنش قبلا وریفای شده است.', WC_CPAL_TEXT_DOMAIN);
            echo '<div class="notice notice-info is-dismissible"><p>' . $message . '</p></div>';
        } else {
            throw new Exception('تراکنش ناموفق بود.');
        }
    } catch (Exception $e) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('خطا: ', WC_CPAL_TEXT_DOMAIN) . esc_html($e->getMessage()) . '</p></div>';
    }
    wp_die();
}

add_action('woocommerce_admin_order_data_after_order_details', 'cpal_manual_verify_button');
function cpal_manual_verify_button($order) {
    if ($order->get_payment_method() !== 'WC_CPal') {
        return;
    }
    $order_id = $order->get_id();
    ?>
    <p style="margin-top:20px;">
        <a href="#" id="cpal-manual-verify-btn" class="button button-primary" style="margin-top:20px;">
            <?php _e('اعتبارسنجی مجدد تراکنش', WC_CPAL_TEXT_DOMAIN); ?>
        </a>
    </p>
    <div id="cpal-manual-verify-result"></div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#cpal-manual-verify-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                btn.prop('disabled', true);
                $('#cpal-manual-verify-result').html('<div class="notice notice-info is-dismissible"><p><?php echo esc_js(__('در حال بررسی تراکنش...', WC_CPAL_TEXT_DOMAIN)); ?></p></div>');
                $.post(ajaxurl, { action: 'cpal_manual_verify', order_id: <?php echo intval($order_id); ?> }, function(response) {
                    $('#cpal-manual-verify-result').html(response);
                    btn.prop('disabled', false);
                });
            });
        });
    </script>
    <?php
}
?>
