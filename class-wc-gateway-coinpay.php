<?php

if (!defined('ABSPATH')) exit;

class WC_Gateway_CoinPay extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'coinpay';
        $this->method_title       = 'CoinPay Gateway';
        $this->method_description = 'پرداخت رمزارزی از طریق CoinPay';
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title           = $this->get_option('title');
        $this->api_key         = $this->get_option('api_key');
        $this->callback_secret = $this->get_option('callback_secret');
        $this->test_mode       = $this->get_option('test_mode') === 'yes';

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_wc_gateway_' . $this->id, [$this, 'callback_handler']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'فعال‌سازی',
                'type'    => 'checkbox',
                'label'   => 'فعالسازی درگاه CoinPay',
                'default' => 'yes'
            ],
            'title' => [
                'title'       => 'عنوان درگاه',
                'type'        => 'text',
                'description' => 'عنوان قابل مشاهده برای مشتری',
                'default'     => 'پرداخت با CoinPay',
            ],
            'api_key' => [
                'title'       => 'API Key',
                'type'        => 'text',
                'description' => 'کلید API دریافتی از CoinPay',
                'default'     => '',
            ],
            'callback_secret' => [
                'title'       => 'Callback Secret',
                'type'        => 'text',
                'description' => 'کد امنیتی برای تأیید صحت Callback',
                'default'     => '',
            ],
            'test_mode' => [
                'title'       => 'حالت تست',
                'label'       => 'فعالسازی حالت تست (sandbox)',
                'type'        => 'checkbox',
                'description' => 'اگر فعال شود، پرداخت‌ها به محیط تستی CoinPay ارسال می‌شوند.',
                'default'     => 'yes',
            ],
        ];
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        $api_url = $this->test_mode
            ? 'https://sandbox.coinpay.example.com/api/create-payment'
            : 'https://coinpay.example.com/api/create-payment';

        $body = [
            'api_key'    => $this->api_key,
            'amount'     => $order->get_total(),
            'currency'   => $order->get_currency(),
            'order_id'   => $order->get_id(),
            'callback'   => home_url('/?wc-api=wc_gateway_coinpay'),
            'return_url' => $this->get_return_url($order),
        ];

        $response = wp_remote_post($api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($body),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            wc_add_notice('خطا در اتصال به درگاه CoinPay: ' . $response->get_error_message(), 'error');
            return;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($result['payment_url'])) {
            return [
                'result'   => 'success',
                'redirect' => $result['payment_url']
            ];
        } else {
            wc_add_notice('خطا در پاسخ CoinPay', 'error');
            return;
        }
    }

    public function callback_handler() {
        $raw_body = file_get_contents('php://input');
        $json = json_decode($raw_body, true);

        if (!isset($json['order_id']) || !isset($json['secret']) || $json['secret'] !== $this->callback_secret) {
            status_header(400);
            echo 'Invalid callback';
            exit;
        }

        $order = wc_get_order($json['order_id']);
        if (!$order) {
            status_header(404);
            echo 'Order not found';
            exit;
        }

        if ($json['status'] === 'success') {
            $order->payment_complete();
            $order->add_order_note('پرداخت موفق با CoinPay.');
        } elseif ($json['status'] === 'failed') {
            $order->update_status('failed', 'پرداخت ناموفق از طریق CoinPay.');
        }

        status_header(200);
        echo 'OK';
        exit;
    }
}
