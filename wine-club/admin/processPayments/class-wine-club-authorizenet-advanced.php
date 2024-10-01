<?php

// using WooCommerce Authorize.Net Gateway version 3.10.2 or grater. 

if(!file_exists(ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim')) {
    return;
}

// Get the plugin version
$plugin_data = get_plugin_data(ABSPATH . 'wp-content/plugins/woocommerce-gateway-authorize-net-cim/woocommerce-gateway-authorize-net-cim.php');
$plugin_version = $plugin_data['Version'];

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

    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/class-wc-authorize-net-cim-api.php';
    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/abstract-wc-authorize-net-cim-api-request.php';
    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/class-wc-authorize-net-cim-api-response-message-helper.php';
    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/abstract-wc-authorize-net-cim-api-response.php';
    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/transaction/abstract-wc-authorize-net-cim-api-transaction-request.php';
    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/transaction/class-wc-authorize-net-cim-api-profile-transaction-request.php';
    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/transaction/abstract-wc-authorize-net-cim-api-transaction-response.php';
    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/profile/class-wc-authorize-net-cim-api-profile-response.php';
    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/transaction/class-wc-authorize-net-cim-api-non-profile-transaction-response.php';
    require_once ABSPATH.'wp-content/plugins/woocommerce-gateway-authorize-net-cim/src/api/transaction/class-wc-authorize-net-cim-api-profile-transaction-response.php';

require __DIR__.'/authorize-net-sdk-module/autoload.php';

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;


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

    if($this->config['environment'] == 'production'){
        
        $environment         = $this->config['environment'];
        $api_login_id        = $this->config['api_login_id'];
        $api_transaction_key = $this->config['api_transaction_key'];
        $is_sandbox = false; // Pass the correct environment
        
    } 
    else {
        
        $environment         = $this->config['environment'];
        $api_login_id        = $this->config['test_api_login_id'];
        $api_transaction_key = $this->config['test_api_transaction_key'];
        $is_sandbox = true; // Pass the correct environment
        
    }

    $this->getCustomerId();
    $this->getPaymentMethod();
    $this->order->payment_total = $this->order->get_total();
    
    $authorizeNet = new WC_Authorize_Net_CIM_API('authorize_net_cim_credit_card', $environment, $api_login_id, $api_transaction_key);
    $response = $authorizeNet->credit_card_charge($this->order);
    if($response->transaction_approved()) {
         update_post_meta($this->order->get_ID(), '_payment_method', 'authorize_net_cim_credit_card');
        update_post_meta($this->order->get_ID(), '_payment_method_title', 'Credit Card');
        update_post_meta($this->order->get_ID(), '_transaction_id', $response->get_transaction_id());
        $last_four = null;
        if (isset($response->transactionResponse->accountNumber)) {
            $account_number = $response->transactionResponse->accountNumber;
            $last_four = substr($account_number, -4); // Extract the last 4 digits
        }
        update_post_meta($this->order->get_ID(), 'account_four', $last_four);
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
        $profileId = 'wc_authorize_net_cim_customer_profile_id';
        if($this->config['environment'] == 'test') {
            $filed .= '_test';
            $profileId .= '_test';
        }

        $paymentMethods = get_user_meta($this->userId, $filed, true);
        if (!$paymentMethods) {
            $paymentMethods = get_user_meta($this->userId, '_'.$filed, true);
        }

        if (!is_array($paymentMethods)) {
            $paymentMethods = [];
        }

        if (count($paymentMethods) == 0) {
            $customerProfileId = get_user_meta($this->userId, $profileId, true);

            global $wpdb;
            $query = $wpdb->prepare("
                SELECT token 
                FROM {$wpdb->prefix}woocommerce_payment_tokens 
                WHERE user_id = %d 
                AND gateway_id = 'authorize_net_cim_credit_card' 
                AND is_default = 1
                LIMIT 1
            ", $this->userId);

            $paymentProfileId = $wpdb->get_var($query);

            if (!$paymentProfileId) {
                $paymentProfileId = null;
            }

            $amount = $this->order->get_total();  // Amount to charge

            // Charge the customer and get the response
            $response = chargeCustomerProfile($customerProfileId, $paymentProfileId, $amount);
            if ($response->success) {
            // If successful, update the order status to processing
            $this->order->update_status('processing');
            
            // Update order meta for payment method and transaction details
            update_post_meta($this->order->get_ID(), '_payment_method', 'authorize_net_cim_credit_card');
            update_post_meta($this->order->get_ID(), '_payment_method_title', 'Credit Card');
            update_post_meta($this->order->get_ID(), '_transaction_id', $response->transaction_id);
            update_post_meta($this->order->get_ID(), 'account_four', $response->last_four);

            // Get the card details and expiration date from the response
            $card_type = ucfirst($response->card_type); 
            $last_four = $response->last_four;
            // $exp_month = $this->order->exp_month; 
            // $exp_year = $this->order->exp_year; 
            $transaction_id = $response->transaction_id;

            // Construct the success message in the desired format
            $order_note = sprintf(
                'Authorize.Net Credit Card Charge Approved: %s ending in %s (Transaction ID %s)',
                $card_type, 
                $last_four, 
                $transaction_id
            );

            // Add the success message as an order note
            $this->order->add_order_note(esc_html($order_note)); // Add note to the order

            // Display success message if not processing payment via GET
            $txt = '<span><span style="color: green" class="dashicons dashicons-yes"></span>' 
                 . get_user_meta($this->userId, 'billing_first_name', true) . ' ' 
                 . get_user_meta($this->userId, 'billing_last_name', true) 
                 . ' order created successfully.</span>';
            
            if ($_GET['action'] != 'processPayment') {
                echo '<span>' . $txt . '</span>';
            }

            // Redirect after success
            if ($_POST['action'] != 'runWineClubMember') {
                $txturl = '<h3 style="padding: 20px 0;clear:both;">'
                        . get_user_meta($this->userId, 'billing_first_name', true) . ' '
                        . get_user_meta($this->userId, 'billing_last_name', true)
                        . ' order created successfully.</h3>';
                
                $orderId = $this->order->get_ID();
                $url = admin_url() . 'post.php?post=' . $orderId . '&action=edit&response=' . urlencode($txturl);
                
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        setTimeout(function() {
                            window.location.replace("' . $url . '")
                        }, 1000);
                    });
                </script>';
            }

            exit;
            
        } else {
            // If payment fails, update order status to pending
            $this->order->update_status('pending');
            
            // Construct the failure message
            $failure_message = get_user_meta($this->userId, 'billing_first_name', true) . ' '
                             . get_user_meta($this->userId, 'billing_last_name', true) 
                             . ' has no saved card available, Order created with Pending payment.';

            // Check existing notes to avoid duplicates
            $existing_notes = $this->order->get_customer_order_notes();
            $note_exists = false;
            
            foreach ($existing_notes as $note) {
                if (strpos($note->comment_content, $failure_message) !== false) {
                    $note_exists = true;
                    break;
                }
            }
            
            // Add failure message to order note if it doesn't already exist
            if (!$note_exists) {
                $this->order->add_order_note(esc_html($failure_message)); // Add note to the order
            }
            
            // Display failure message if not processing payment via GET
            if ($_GET['action'] != 'processPayment') {
                echo '<span>' . $failure_message . '</span>';
            }

            // Redirect after failure
            if ($_POST['action'] != 'runWineClubMember') {
                $txturl = '<h3 style="padding: 20px 0;clear:both;">'
                        . get_user_meta($this->userId, 'billing_first_name', true) . ' '
                        . get_user_meta($this->userId, 'billing_last_name', true)
                        . ' has no saved card available.</h3>';
                
                $orderId = $this->order->get_ID();
                $url = admin_url() . 'post.php?post=' . $orderId . '&action=edit&response=' . urlencode($txturl);
                
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        setTimeout(function() {
                            window.location.replace("' . $url . '")
                        }, 1000);
                    });
                </script>';
            }

            exit();
        }

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





function chargeCustomerProfile($customerProfileId, $paymentProfileId, $amount) {

    $config = get_option('woocommerce_authorize_net_cim_credit_card_settings');

    if ($config['environment'] == 'production') {
        $environment         = $config['environment'];
        $api_login_id        = $config['api_login_id'];
        $api_transaction_key = $config['api_transaction_key'];
        $is_sandbox = false;
    } else {
        $environment         = $config['environment'];
        $api_login_id        = $config['test_api_login_id'];
        $api_transaction_key = $config['test_api_transaction_key'];
        $is_sandbox = true;
    }
    
    // Create a merchant authentication object
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName($api_login_id);
    $merchantAuthentication->setTransactionKey($api_transaction_key);

    // Create the payment object for a payment profile
    $profileToCharge = new AnetAPI\CustomerProfilePaymentType();
    $profileToCharge->setCustomerProfileId($customerProfileId);

    $paymentProfile = new AnetAPI\PaymentProfileType();
    $paymentProfile->setPaymentProfileId($paymentProfileId);
    $profileToCharge->setPaymentProfile($paymentProfile);

    // Create a transaction request
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType("authCaptureTransaction");
    $transactionRequestType->setAmount($amount);
    $transactionRequestType->setProfile($profileToCharge);

    // Create the request
    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setTransactionRequest($transactionRequestType);

    // Create the controller
    $controller = new AnetController\CreateTransactionController($request);
    
    // Run the transaction
    $response = $controller->executeWithApiResponse($is_sandbox ? \net\authorize\api\constants\ANetEnvironment::SANDBOX : \net\authorize\api\constants\ANetEnvironment::PRODUCTION);

    if ($response != null) {
        if ($response->getMessages()->getResultCode() == "Ok") {
            $tresponse = $response->getTransactionResponse();
            if ($tresponse != null && $tresponse->getMessages() != null) {
                // Success, return the last 4 digits of the account number and transaction ID
                $last_four = substr($tresponse->getAccountNumber(), -4);
                $transaction_id = $tresponse->getTransId(); // Get transaction ID

                return (object) [
                    'success' => true,
                    'message' => $tresponse->getMessages()[0]->getDescription(),
                    'last_four' => $last_four,
                    'transaction_id' => $transaction_id // Return transaction ID
                ];

            } else {
                return (object) [
                    'success' => false,
                    'message' => 'Transaction failed!',
                    'last_four' => null,
                    'transaction_id' => null
                ];
            }
        } else {
            return (object) [
                'success' => false,
                'message' => $response->getMessages()->getMessage()[0]->getText(),
                'last_four' => null,
                'transaction_id' => null
            ];
        }
    } else {
        return (object) [
            'success' => false,
            'message' => 'No response returned from the API.',
            'last_four' => null,
            'transaction_id' => null
        ];
    }
}
