<?php


require_once plugin_dir_path( dirname( __FILE__ ) ) . '../vendor/autoload.php';
//echo plugin_dir_path(dirname( __FILE__ ));
require_once plugin_dir_path( dirname( __FILE__ ) ) . '../vendor/stripe/stripe-php/init.php';

class Wine_Club_Stripe {
    private $order;
    private $userId;
    private $file;
    private $apiKey;

    /**
     * Wine_Club_Stripe constructor.
     * @param $order
     * @param $userId
     * @param $file
     */
    public function __construct($order, $userId, $file)
    {
        $settings = get_option('wineClubSettings');
        $this->order = $order;
        $this->userId = $userId;
        $this->file = $file;
        $this->$apiKey = $settings['stripe_api_key'];
        
    }

    /**
     * Process the payment
     *
     * @since 1.0.0
     * @since 4.1.0 Add 4th parameter to track previous error.
     * @param int  $order_id Reference.
     * @param bool $retry Should we retry on fail.
     * @param bool $force_save_source Force save the payment source.
     * @param mix  $previous_error Any error message from previous request.
     *
     * @throws Exception If payment will not be accepted.
     * @return array|void
     */
    public function run() {
        $input = $this->order->get_total();
        $dollars = str_replace('$', '', $input);
        $cents = bcmul($dollars, 100);
        
        $this->getPaymentMethod();
        \Stripe\Stripe::setApiKey($this->$apiKey);
        $charge = \Stripe\Charge::create(['amount' => $cents, 'currency' => strtolower($this->order->data['currency']), 'source' => $this->getPaymentMethod(), 'customer' => $this->getCustomerId()]);
        $charge->getLastResponse()->headers['Request-Id'];
        return (object) [
            'success' => true,
            'message' => 'Transaction success.'
        ];
    }


    private function getCustomerId() {
     $customerId = get_user_meta($this->userId, '_stripe_customer_id', true);
     if (!$customerId) {
        $txt = '<h3 style="padding: 20px 0;clear:both;">';
        $txt .= get_user_meta($this->userId, 'billing_first_name', true) . ' ' . get_user_meta($this->userId, 'billing_last_name', true) . ' does not have customer id.';
        $txt .= '</h3>';
        fwrite($this->file, "\n" . $txt);

        $url = admin_url() . 'post.php?post=' . $_GET['orderId'] . '&action=edit&response=' . urlencode($txt);

        wp_redirect($url);
        exit();
    }
    return $customerId;
}

private function getPaymentMethod()
{
    //$apiKey = $settings['woo_stripe_api_key'];
    \Stripe\Stripe::setApiKey($this->$apiKey);
    $ch = \Stripe\Customer::retrieve( $this->getCustomerId() );
    $paymentMethods = $ch;
    if (count($paymentMethods) == 0 || $paymentMethods == '') {
        $txt = '<h3 style="padding: 20px 0;clear:both;">';
        $txt .= get_user_meta($this->userId, 'billing_first_name', true) . ' ' . get_user_meta($this->userId, 'billing_last_name', true) . ' does not have saved Stripe credit card';
        $txt .= '</h3>';
        fwrite($this->file, "\n" . $txt);

        $url = admin_url() . 'post.php?post=' . $_GET['orderId'] . '&action=edit&response=' . urlencode($txt);

        wp_redirect( $url );
        exit();
    }
    return $ch['sources']->data[0]->id;
}
}

