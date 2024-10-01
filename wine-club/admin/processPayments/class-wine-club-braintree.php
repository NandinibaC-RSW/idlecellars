<?php
require_once ABSPATH.'wp-content/plugins/wine-club/Braintree/Braintree.php';

class Wine_Club_Braintree {
    private $order;
    private $userId;
    private $file;
    private $config;

    /**
     * Wine_Club_AuthorizeNet constructor.
     * @param $order
     * @param $userId
     */
    public function __construct($order, $userId, $file)
    {
        $this->order = $order;
        $this->userId = $userId;
        $this->file = $file;
        $this->config = get_option('braintree_payment_settings');
    }

    public function run() {

        if($this->config['sandbox_environment'] == 'yes') {
            $this->config['environment'] = 'sandbox';
            Braintree\Configuration::environment('sandbox');
            Braintree\Configuration::merchantId($this->config['sandbox_merchant_id']);
            Braintree\Configuration::publicKey($this->config['sandbox_public_key']);
            Braintree\Configuration::privateKey($this->config['sandbox_private_key']);
        } else {
            $this->config['environment'] = 'production';
            Braintree\Configuration::environment('production');
            Braintree\Configuration::merchantId($this->config['production_merchant_id']);
            Braintree\Configuration::publicKey($this->config['production_public_key']);
            Braintree\Configuration::privateKey($this->config['production_private_key']);
        }

        $nonceFromTheClient = null;

        $attribs = [
                'amount' => $this->order->get_total(), 
                'taxAmount' => $this->is_wc_3_0_0_or_more() ? wc_round_tax_total( $this->order->get_total_tax() ) : $this->order->get_total_tax(),
                'customerId' => $this->getCustomerId(),
                'orderId' => $this->order->get_id(),
                'paymentMethodToken' => $this->getPaymentMethod(),
                'customer' => [
                    'firstName' => $this->order->get_billing_first_name(),
                    'lastName' => $this->order->get_billing_last_name(),
                    'company' => $this->order->get_billing_company(),
                    'phone' => $this->order->get_billing_phone(),
                    'fax' => $this->order->get_billing_phone(),
                    'email' => $this->order->get_billing_email()
                ],
                'billing' => [
                    'firstName' => $this->order->get_billing_first_name(),
                    'lastName' => $this->order->get_billing_last_name(),
                    'company' => $this->order->get_billing_company(),
                    'streetAddress' => $this->order->get_billing_address_1(),
                    'extendedAddress' => $this->order->get_billing_address_2(),
                    'locality' => $this->order->get_billing_city(),
                    'region' => $this->order->get_billing_state(),
                    'postalCode' => $this->order->get_billing_postcode(),
                    'countryCodeAlpha2' => $this->order->get_billing_country()
                ],
                'shipping' => [
                    'firstName' => $this->order->get_shipping_first_name(),
                    'lastName' => $this->order->get_shipping_last_name(),
                    'company' => $this->order->get_shipping_company(),
                    'streetAddress' => $this->order->get_shipping_address_1(),
                    'extendedAddress' => $this->order->get_shipping_address_2(),
                    'locality' => $this->order->get_shipping_city(),
                    'region' => $this->order->get_shipping_state(),
                    'postalCode' => $this->order->get_shipping_postcode(),
                    'countryCodeAlpha2' => $this->order->get_shipping_country()
                ],
                'options' => [
                    'submitForSettlement' => true
                ] 
        ];

      

        $response = Braintree_Transaction::sale($attribs);

        if($response->success) {
            $success = true;
        } else {
            $success = false;
        }

        $message = $response->transaction->processorResponseText;

        return (object) [
            'success' => $success,
            'message' => $message
        ];

        die();
   }

   private function getCustomerId() {
        $customerId = get_user_meta($this->userId, "braintree_{$this->config['environment']}_vault_id", true);

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
        $paymentMethods = get_user_meta($this->userId, "braintree_{$this->config['environment']}_payment_methods", true);

        if (count($paymentMethods) == 0 || $paymentMethods == '') {
            $txt = '<h3 style="padding: 20px 0;clear:both;">';
            $txt .= get_user_meta($this->userId, 'billing_first_name', true) . ' ' . get_user_meta($this->userId, 'billing_last_name', true) . ' does not have saved credit card';
            $txt .= '</h3>';
            fwrite($this->file, "\n" . $txt);

            $url = admin_url() . 'post.php?post=' . $_GET['orderId'] . '&action=edit&response=' . urlencode($txt);

            wp_redirect( $url );
            exit();
        }

        $paymentMethod = null;
        foreach ($paymentMethods as $id => $paymentMethodLoop) {
            if ($paymentMethodLoop['default'] == true) {
                $paymentMethod =  $id;
                break;
            }
        }

        if ($paymentMethod == null) {
            $keys = array_keys($paymentMethods);
            $paymentMethod = $keys[0];
        }

        return $paymentMethod;
    }

   private function is_wc_3_0_0_or_more()
    {
        return function_exists( 'WC' ) ? version_compare( WC()->version, '3.0.0', '>=' ) : false;
    }
}