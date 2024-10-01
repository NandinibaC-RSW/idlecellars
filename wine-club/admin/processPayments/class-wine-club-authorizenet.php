<?php


if(!file_exists(ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim')) {
    return;
}



require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/api/interface-sv-wc-api-request.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/api/class-sv-wc-api-base.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/api/abstract-sv-wc-api-xml-request.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/api/interface-sv-wc-api-response.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/api/abstract-sv-wc-api-xml-response.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/class-sv-wc-framework-bootstrap.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/api/interface-sv-wc-payment-gateway-api.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/api/interface-sv-wc-payment-gateway-api-request.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/api/class-sv-wc-payment-gateway-api-response-message-helper.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/api/interface-sv-wc-payment-gateway-api-response.php';

require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/api/interface-sv-wc-payment-gateway-api-authorization-response.php';


 
require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/includes/api/class-wc-authorize-net-cim-api.php';
require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/includes/api/abstract-wc-authorize-net-cim-api-request.php';
require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/includes/api/class-wc-authorize-net-cim-api-response-message-helper.php';
require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/includes/api/abstract-wc-authorize-net-cim-api-response.php';
require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/includes/api/transaction/abstract-wc-authorize-net-cim-api-transaction-request.php';
require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/includes/api/transaction/class-wc-authorize-net-cim-api-profile-transaction-request.php';
require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/includes/api/transaction/abstract-wc-authorize-net-cim-api-transaction-response.php';
require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/includes/api/profile/class-wc-authorize-net-cim-api-profile-response.php';
require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/includes/api/transaction/class-wc-authorize-net-cim-api-profile-transaction-response.php';



class Wine_Club_AuthorizeNet {

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
        $this->config = get_option( 'woocommerce_authorize_net_cim_credit_card_settings' );

    }

    public function run() {

        $this->getCustomerId();
        $this->getPaymentMethod();

        $this->order->payment_total = $this->order->get_total();
        
        if($this->config['environment'] == 'production'){
            
            $environment         = $this->config['environment'];
            $api_login_id        = $this->config['api_login_id'];
            $api_transaction_key = $this->config['api_transaction_key'];
            
        } 
        else {
            
            $environment         = $this->config['environment'];
            $api_login_id        = $this->config['test_api_login_id'];
            $api_transaction_key = $this->config['test_api_transaction_key'];
            
        }
        
        $authorizeNet = new WC_Authorize_Net_CIM_API('authorize_net_cim_credit_card', $environment, $api_login_id, $api_transaction_key);
        $response = $authorizeNet->credit_card_charge($this->order);

        if($response->transaction_approved()) {
             update_post_meta($this->order->get_ID(), '_payment_method', 'authorize_net_cim_credit_card');
            update_post_meta($this->order->get_ID(), '_payment_method_title', 'Credit Card');
              update_post_meta($this->order->get_ID(), '_transaction_id', $response->get_transaction_id());
            update_post_meta($this->order->get_ID(), 'account_four', $response->get_account_last_four());
            $success = true;
        } else {
            $success = false;
        }

        $message = $response->get_transaction_response_reason_text();

        return (object) [
            'success' => $success,
            'message' => $message
        ];
    }

    public function convertPaymentMethod($paymentMethods, $id) {
        return (object) [
            'type' => $paymentMethods[$id]['type'],
            'token' => $id,
            'account_number' => $paymentMethods[$id]['last_four'],
            'last_four' => $paymentMethods[$id]['last_four'],
            'card_type' => $paymentMethods[$id]['card_type'],
            'exp_month' => $paymentMethods[$id]['exp_month'],
            'exp_year' => $paymentMethods[$id]['exp_year'],
            'shipping_address_id' => ''
        ];
    }

    private function getCustomerId()
    {
        $filed = 'wc_authorize_net_cim_customer_profile_id';
        if($this->config['environment'] == 'test') {
            $filed .= '_test';
        }

        $this->order->customer_id = get_user_meta($this->userId, $filed, true);
        if (!$this->order->customer_id) {
            $this->order->customer_id = get_user_meta($this->userId, '_'.$filed , true);
        }
    }


    private function getPaymentMethod()
    {
        $filed = 'wc_authorize_net_cim_credit_card_payment_tokens';
        if($this->config['environment'] == 'test') {
            $filed .= '_test';
        }

        $paymentMethods = get_user_meta($this->userId, $filed, true);

        if (!$paymentMethods) {
            $paymentMethods = get_user_meta($this->userId, '_'.$filed, true);
        }

        if (count($paymentMethods) == 0 || $paymentMethods == '') {
            $txt = '<h3 style="padding: 20px 0;clear:both;">';
            $txt .= get_user_meta($this->userId, 'billing_first_name', true) . ' ' . get_user_meta($this->userId, 'billing_last_name', true) . ' does not have saved credit card';
            $txt .= '</h3>';
            fwrite($this->file, "\n" . $txt);

            $url = admin_url() . 'post.php?post=' . $_GET['orderId'] . '&action=edit&response=' . urlencode($txt);
            echo '<script>window.location.replace("' . $url . '")</script>';
            exit();
        }

        $paymentMethod = null;
        foreach ($paymentMethods as $id => $paymentMethodLoop) {
            if ($paymentMethodLoop['default'] == true) {
                $paymentMethod = $this->convertPaymentMethod($paymentMethods, $id);
                break;
            }
        }

        if ($paymentMethod == null) {
            $keys = array_keys($paymentMethods);
            $paymentMethod = $this->convertPaymentMethod($paymentMethods, $keys[0]);
        }

        $this->order->payment = $paymentMethod;
    }
}
