<?php
class CoinpayHelperClass {
    private $merchantId;
    private $sandbox;
    private $baseUrl;
    private $redirectUrl;
    private $userAgent;
    private $graphqlUrl;
    private $accessToken;
    public function __construct($merchantId, $sandbox = false, $accessToken = '') {
        $this->merchantId = $merchantId;
        $this->sandbox = $sandbox;
        $this->baseUrl = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/'
            : 'https://payment.zarinpal.com/pg/v4/payment/';
        $this->redirectUrl = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/StartPay/'
            : 'https://payment.zarinpal.com/pg/StartPay/';
        $this->userAgent = 'ZarinPalSdk/v1 WooCommerce Plugin/v.5.0.14' . ' (WooCommerce ' . WC()->version . '; WordPress ' . get_bloginfo('version') . '; PHP ' . PHP_VERSION . ')';
        $this->graphqlUrl = 'https://next.zarinpal.com/api/v4/graphql';
        $this->accessToken = $accessToken;
    }
    public function requestPayment($amount, $callbackUrl, $description, $metadata = array(), $invoices = array(), $referrer_id = null) {
        $data = array(
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'metadata' => $metadata,
            'invoices' => $invoices,
            'referrer_id' => $referrer_id,
        );
        $data = $this->recursive_array_filter($data);
        $response = $this->sendRequest('request.json', $data);
        if (isset($response['data']['code']) && $response['data']['code'] == 100) {
            return $response['data']['authority'];
        } else {
            $errorMessage = $response['errors']['message'] ?? 'خطای ناشناخته';
            throw new Exception($errorMessage);
        }
    }
    public function verifyPayment($authority, $amount) {
        $data = array(
            'merchant_id' => $this->merchantId,
            'authority' => $authority,
            'amount' => $amount,
        );
        $response = $this->sendRequest('verify.json', $data);
        if (isset($response['data']['code']) && ($response['data']['code'] == 100 || $response['data']['code'] == 101)) {
            return $response['data'];
        } else {
            $errorMessage = $response['errors']['message'] ?? 'خطای ناشناخته';
            throw new Exception($errorMessage);
        }
    }
    public function getRedirectUrl($authority) {
        return $this->redirectUrl . $authority;
    }
    public function refundPayment($session_id, $amount, $description = '', $method = 'PAYA', $reason = 'CUSTOMER_REQUEST') {
        $query = [
            'query' => '
                mutation AddRefund($session_id: ID!, $amount: BigInteger!, $description: String, $method: InstantPayoutActionTypeEnum, $reason: RefundReasonEnum) {
                    resource: AddRefund(
                        session_id: $session_id,
                        amount: $amount,
                        description: $description,
                        method: $method,
                        reason: $reason
                    ) {
                        terminal_id,
                        id,
                        amount,
                        timeline {
                            refund_amount,
                            refund_time,
                            refund_status
                        }
                    }
                }
            ',
            'variables' => [
                'session_id' => $session_id,
                'amount' => $amount,
                'description' => $description,
                'method' => $method,
                'reason' => $reason,
            ],
        ];
        $response = $this->sendGraphQLRequest($query);
        if (isset($response['data']['resource'])) {
            return $response['data']['resource'];
        } else {
            $errorMessage = $response['errors'][0]['message'] ?? 'خطای ناشناخته';
            throw new Exception($errorMessage);
        }
    }
    public function getTransactions($authority) {
        $query = [
            'query' => '
            query Sessions(
                $terminal_id: ID,
                $filter: FilterEnum,
                $id: ID,
                $reference_id: String,
                $rrn: String,
                $card_pan: String,
                $email: String,
                $mobile: CellNumber,
                $description: String,
                $limit: Int,
                $offset: Int
            ) {
                Session(
                    terminal_id: $terminal_id,
                    filter: $filter,
                    id: $id,
                    reference_id: $reference_id,
                    rrn: $rrn,
                    card_pan: $card_pan,
                    email: $email,
                    mobile: $mobile,
                    description: $description,
                    limit: $limit,
                    offset: $offset
                ) {
                    id
                    authority
                    amount
                    fee
                    status
                    description
                    created_at
                    reference_id
                    reconciled_at
                    session_tries {
                        card_pan
                        rrn
                        payer_ip
                    }
                }
            }
        ',
            'variables' => [
                'authority' => $authority
            ],
        ];
        $response = $this->sendGraphQLRequest($query);
        if (isset($response['data']['Session'])) {
            return $response['data']['Session'];
        } else {
            $errorMessage = $response['errors'][0]['message'] ?? 'خطای ناشناخته';
            throw new Exception($errorMessage);
        }
    }
    public function unverifiedTransactions() {
        $data = array(
            'merchant_id' => $this->merchantId,
        );
        $response = $this->sendRequest('unVerified.json', $data);
        if (isset($response['data']['code']) && $response['data']['code'] == 100) {
            return $response['data']['authorities'];
        } else {
            $errorMessage = $response['errors']['message'] ?? 'خطای ناشناخته';
            throw new Exception($errorMessage);
        }
    }
    public function reversePayment($authority) {
        $data = array(
            'merchant_id' => $this->merchantId,
            'authority' => $authority,
        );
        $response = $this->sendRequest('reverse.json', $data);
        if (isset($response['data']['code']) && $response['data']['code'] == 100) {
            return true;
        } else {
            $errorMessage = $response['errors']['message'] ?? 'خطای ناشناخته';
            throw new Exception($errorMessage);
        }
    }
    public function inquiryPayment($authority) {
        $data = array(
            'merchant_id' => $this->merchantId,
            'authority' => $authority,
        );
        $response = $this->sendRequest('inquiry.json', $data);
        if (isset($response['data']['code']) && $response['data']['code'] == 100) {
            return $response['data'];
        } else {
            $errorMessage = $response['errors']['message'] ?? 'خطای ناشناخته';
            throw new Exception($errorMessage);
        }
    }
    private function sendGraphQLRequest($query) {
        $url = $this->graphqlUrl;
        $args = array(
            'body' => json_encode($query),
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => $this->userAgent,
                'Authorization' => $this->accessToken,
            ),
            'timeout' => 15,
            'data_format' => 'body',
        );
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            throw new Exception('خطا در ارتباط با سرور: ' . $response->get_error_message());
        }
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);
        if (isset($result['errors'])) {
            throw new Exception($result['errors'][0]['message']);
        }
        return $result;
    }
    private function sendRequest($endpoint, $data) {
        $url = $this->baseUrl . $endpoint;
        $args = array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => $this->userAgent,
            ),
            'timeout' => 15,
            'data_format' => 'body',
        );
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            throw new Exception('خطا در ارتباط با سرور: ' . $response->get_error_message());
        }
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);
        return $result;
    }
    private function recursive_array_filter($array) {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->recursive_array_filter($value);
                if (empty($value)) {
                    unset($array[$key]);
                }
            } else {
                if (is_null($value) || $value === '') {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    }
}
