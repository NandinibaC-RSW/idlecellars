<?php

// This file called when editing member from club comnnection and also in batchorder and also  while process payment from edit order
require __DIR__.'/../../vendor/autoload.php';

use Square\SquareClient;
use Square\LocationsApi;
use Square\Exceptions\ApiException;
use Square\Http\ApiResponse;
use Square\Models\ListLocationsResponse;
use Square\Environment;
use Square\Models\CreateCardRequest;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;
use Square\Models\createPaymentResponse;
use Square\Models\Payment;

class Wine_Club_Square {
    private $order;
    private $userId;
    private $file;

    /**
     * Wine_Club_Square constructor.
     * @param $order
     * @param $userId
     * @param $file
     */
    public function __construct($order, $userId, $file)
    {
        $this->order = $order;
        $this->userId = $userId;
        $this->file = $file;
    }

    public function run() {
        $first_name = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->billing_first_name : $this->order->get_billing_first_name();
        $last_name = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->billing_last_name : $this->order->get_billing_last_name();
        if(empty($first_name) and empty($last_name)){
            $first_name = $last_name = null;
        }

        $currency = $this->order->get_order_currency();
        $amount = round( $this->order->get_total(), 2 ) * 100;
        $line_items = [];
        foreach ( $this->order->get_items() as $item ) {

            if ( ! $item instanceof \WC_Order_Item_Product ) {
                continue;
            }

            $line_item = [];

            $line_item['quantity'] = (string) $item->get_quantity();
            $line_item['base_price_money'] = [
                'amount'   => (int)($this->order->get_item_subtotal( $item )*100),
                'currency' => $this->order->get_currency(),
            ];

            $square_id = $item->get_meta( '_square_item_variation_id' );

            if ( $square_id ) {
                $line_item['catalog_object_id'] = $square_id;
            } else {
                $line_item['name'] = $item->get_name();
            }

            $line_items[] = $line_item;
        }
        $discounts = [];
        foreach ( $this->order->get_fees() as $item ) {

            if ( ! $item instanceof \WC_Order_Item_Fee ) {
                continue;
            }

            if($item->get_total() <= 0)
            {
                $discounts[] = [
                    'name'         => __( $item->get_name(), 'woocommerce-square' ),
                    'amount_money' => [
                            'amount'   => abs((int)($item->get_total()*100)),
                            'currency' => $this->order->get_currency(),
                        ],
                    'scope'        => 'ORDER',
                ];
            }
            else
            {

                $line_item = [];

                $line_item['quantity'] = (string) 1;

                $line_item['name'] = $item->get_name();
                $line_item['base_price_money'] = [
                    'amount'   => (int)($item->get_total()*100),
                    'currency' => $this->order->get_currency(),
                ];

                $line_items[] = $line_item;
            }
        }
        
        foreach ( $this->order->get_shipping_methods() as $item ) {

            if ( ! $item instanceof \WC_Order_Item_Shipping ) {
                continue;
            }

            $line_item = [];

            $line_item['quantity'] = (string) 1;

            $line_item['name'] = $item->get_name();
            $line_item['base_price_money'] = [
                'amount'   => (int)($item->get_total()*100),
                'currency' => $this->order->get_currency(),
            ];

            $line_items[] = $line_item;
        }
        $taxes = [];
        foreach ( $this->order->get_taxes() as $tax ) {
            $tax_item = [];
            $tax_item['uid'] = uniqid();
            $tax_item['name'] = $tax->get_name();
            $tax_item['type'] = 'ADDITIVE';
            $tax_item['scope'] = 'LINE_ITEM';

            $pre_tax_total = (float) $this->order->get_total() - (float) $this->order->get_total_tax();
            $total_tax     = (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();

            $percentage = ( $total_tax / $pre_tax_total ) * 100;

            $tax_item['percentage']= (string)round($percentage, 4);

            $taxes[] = $tax_item;
        }
        foreach($line_items as $key => $line_item)
        {
            $applied_taxes = [];
            foreach ( $taxes as $tax ) {
                $applied_taxes[] = ['tax_uid' => $tax['uid']];
            }
            $line_items[$key]['applied_taxes'] = $applied_taxes;
        }
        
        if ( $this->order->get_discount_total() ) {
            $discounts[] = [
                'name'         => __( 'Discount', 'woocommerce-square' ),
                'type'         => 'FIXED_AMOUNT',
                'amount_money' => [
                        'amount'   => (int)($this->order->get_discount_total()*100),
                        'currency' => $this->order->get_currency(),
                    ],
                'scope'        => 'ORDER',
            ];
        }



        try {
            $woo_square_locations = get_option('wc_square_location');
            
            $orderdata = [
                        'idempotency_key' => (string) $this->order->get_order_number(),
                        'order' => [
                            'reference_id' => (string) $this->order->get_order_number(),
                            'location_id' =>  $woo_square_locations,  // NEW
                            'line_items' => $line_items,
                            'taxes' => $taxes,
                            'discounts' => $discounts,
                            'customer_id' => $this->getCustomerId()
                        ]
                    ];


                //  NEW CURL                    

                    $woo_order_data = json_encode($orderdata);

                    $accessToken = get_option('woo_square_access_token');

                    $authorization = "Authorization: Bearer $accessToken";

                   //    $ORDER_API_URL = 'https://connect.squareupsandbox.com/v2/orders';
                  $ORDER_API_URL =  'https://connect.squareup.com/v2/orders';

                    $ch = curl_init($ORDER_API_URL);

                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS,$woo_order_data);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    $result = curl_exec($ch);
                    curl_close($ch);
                    $response_order  = json_decode($result);
                    $square_order_id = $response_order->order->id;

                // NEW CURL END

            // update_post_meta($this->order->id, '_wc_square_credit_card_square_order_id', $result->getOrder()->getId());
            update_post_meta($this->order->id, '_wc_square_credit_card_square_order_id', $square_order_id);

        // NEW  https://developer.squareup.com/explorer/square/orders-api/batch-retrieve-orders


            $client = new SquareClient([
                'accessToken' => get_option('woo_square_access_token'),
                'environment' => Environment::PRODUCTION,
            ]);

            $order_ids = [$square_order_id];
            $body = new \Square\Models\BatchRetrieveOrdersRequest($order_ids);
            $body->setLocationId($woo_square_locations);
            $api_response = $client->getOrdersApi()->batchRetrieveOrders($body);
            // $squareOrder = $api_response->getOrders();

            if ($api_response->isSuccess()) {
                $result = $api_response->getResult();
                $squareOrder = $result->getOrders();
            } else {
                $errors = $api_response->getErrors();
            }

            // END NEW


            if ( empty( $squareOrder ) || !isset($squareOrder[0]) ) {
                return (object) [
                    'success' => false,
                    'message' => 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.'
                ];
            }
		
            $amount     = $squareOrder[0]->getTotalMoney()->getAmount();
            $order_id   = $square_order_id;
            $idempotency_key = uniqid();
            $source_id  = $this->getPaymentMethod();
            
              if ($source_id == 'null') {
                return (object) [
                    'success' => false,
                    'message' => 'Error: payment or card not found.'
                ];
            }

            $customer_id = $this->getCustomerId();
                
            $address_line_1 = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->billing_address_1 : $this->order->get_billing_address_1();
            $address_line_2 = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->billing_address_2 : $this->order->get_billing_address_2();
            $locality       = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->billing_city      : $this->order->get_billing_city();
            $postal_code    = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->billing_postcode  : $this->order->get_billing_postcode();
            $country        = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->billing_country   : $this->order->get_billing_country();
            $administrative_district_level_1 = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->billing_state : $this->order->get_billing_state();
                
            // 'amount_money' => ['amount'   => $amount,    'currency' => 'USD'    ]

/* OLD
            $api = new \SquareConnect\Api\PaymentsApi();
            $result = $api->createPayment($datap);
*/

// NEW

                $billing_address = new \Square\Models\Address();
                $billing_address->setAddressLine1($address_line_1);
                $billing_address->setAddressLine2($address_line_2);
                $billing_address->setLocality($locality);
                $billing_address->setAdministrativeDistrictLevel1($administrative_district_level_1);
                $billing_address->setPostalCode($postal_code);
                $billing_address->setCountry($country);

                $body_sourceId = $source_id; //  replace with card id of customer 
                $body_idempotencyKey = uniqid();
                $currencys = $this->order->get_currency();
                $amount_money = new \Square\Models\Money();
                $amount_money->setAmount($amount);
                $amount_money->setCurrency($currencys);

                $body = new \Square\Models\CreatePaymentRequest($body_sourceId, $body_idempotencyKey);
                $body->setAmountMoney($amount_money);
                $body->setAutocomplete(true);
                $body->setCustomerId($customer_id);
                $body->setReferenceId($orderdata['order']['reference_id']);
                $body->setOrderId($square_order_id);
                $body->setBillingAddress($billing_address);

        if ( $this->order->needs_shipping_address() ) {
            
            $shipping_address_line_1  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->shipping_address_1 : $this->order->get_shipping_address_1();
            $shipping_address_line_2  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->shipping_address_2 : $this->order->get_shipping_address_2();
            $shipping_locality        = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->shipping_city : $this->order->get_shipping_city();
            $shipping_postal_code    = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->shipping_postcode : $this->order->get_shipping_postcode();
            $shipping_country        = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->shipping_country : $this->order->get_shipping_country();
            $shipping_administrative_district_level_1 = version_compare( WC_VERSION, '3.0.0', '<' ) ? $this->order->shipping_state : $this->order->get_shipping_state();

            $shipping_address = new \Square\Models\Address();
            $shipping_address->setAddressLine1($shipping_address_line_1);
            $shipping_address->setAddressLine2($shipping_address_line_2);
            $shipping_address->setLocality($shipping_locality);
            $shipping_address->setAdministrativeDistrictLevel1($shipping_administrative_district_level_1);
            $shipping_address->setPostalCode($shipping_postal_code);
            $shipping_address->setCountry($shipping_country);

            $body->setShippingAddress($shipping_address);
    
        }

            /* Maybe Optional
                $body->setTipMoney(new Models\Money);
                $body->getTipMoney()->setAmount($amount);
                $body->getTipMoney()->setCurrency(Models\Currency::CHF);
                $body->setAppFeeMoney(new Models\Money);
                $body->getAppFeeMoney()->setAmount(10);
                $body->getAppFeeMoney()->setCurrency(Models\Currency::USD);
                $body->setDelayDuration('delay_duration6');
                $body->setAutocomplete(true);
                $body->setOrderId('order_id0');
                $body->setLocationId('L88917AVBK2S5');
                $body->setReferenceId('123456');
                $body->setNote('Brief description');
            */

            $patmentsApiResponse = $client->getPaymentsApi()->createPayment($body);

            if ($patmentsApiResponse->isSuccess()) {
                $createPaymentResponse = $patmentsApiResponse->getResult();
                $result = $createPaymentResponse;
            } else {
                $errors = $patmentsApiResponse->getErrors();
            }
// END NEW


            if ( is_wp_error( $result ) ) {
                return (object) [
                    'success' => false,
                    'message' => 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.'
                ];
            }

            if ( ! empty( $result->errors ) ) {
                if ( 'INVALID_REQUEST_ERROR' === $result->errors[0]->category ) {
                    return (object) [
                        'success' => false,
                        'message' => 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.'
                    ];
                }

                if ( 'PAYMENT_METHOD_ERROR' === $result->errors[0]->category || 'VALIDATION_ERROR' === $result->errors[0]->category ) {
                    // format errors for display
                    $error_html = __( 'Payment Error: ', 'woocommerce-square' );
                    $error_html .= '<br />';
                    $error_html .= '<ul>';

                    foreach( $result->errors as $error ) {
                        $error_html .= '<li>' . $error->detail . '</li>';
                    }

                    $error_html .= '</ul>';
                }

                return (object) [
                    'success' => false,
                    'message' => $error_html
                ];
            }

            if ( empty( $result ) ) {
                return (object) [
                    'success' => false,
                    'message' => 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.'
                ];
            }
            if(method_exists($result, 'getPayment')){ 
            
            $card_date = $result->getPayment()->getcardDetails()->getcard()->getexpYear().'-'.$result->getPayment()->getcardDetails()->getcard()->getexpMonth(); 
            	
                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_authorization_code', $result->getPayment()->getId());
                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_trans_id', $result->getPayment()->getId());
                update_post_meta($this->order->get_ID(), '_transaction_id', $result->getPayment()->getId());
                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_account_four', $result->getPayment()->getcardDetails()->getcard()->getlast4());
                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_square_order_id', $result->getPayment()->getorderId());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_square_location_id', $result->getPayment()->getlocationId());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_customer_id', $result->getPayment()->getcustomerId());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_payment_token', $result->getPayment()->getversionToken());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_card_type', $result->getPayment()->getcardDetails()->getcard()->getcardType());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_trans_date', $result->getPayment()->getcreatedAt());
		  update_post_meta($this->order->get_ID(), '_wc_square_credit_card_square_version', '3.3.0');
		   update_post_meta($this->order->get_ID(), '_wc_square_credit_card_card_expiry_date', $card_date);

            }else if(method_exists($patmentsApiResponse, 'getPayment')) {
			
		$card_date = $patmentsApiResponse->getPayment()->getcardDetails()->getcard()->getexpYear().'-'.$patmentsApiResponse->getPayment()->getcardDetails()->getcard()->getexpMonth();
               
                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_authorization_code', $patmentsApiResponse->getPayment()->getId());
                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_trans_id', $patmentsApiResponse->getPayment()->getId());
                update_post_meta($this->order->get_ID(), '_transaction_id', $patmentsApiResponse->getPayment()->getId());
                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_account_four', $patmentsApiResponse->getPayment()->getcardDetails()->getcard()->getlast4());
                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_square_order_id', $patmentsApiResponse->getPayment()->getorderId());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_square_location_id', $patmentsApiResponse->getPayment()->getlocationId());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_customer_id', $patmentsApiResponse->getPayment()->getcustomerId());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_payment_token', $patmentsApiResponse->getPayment()->getversionToken());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_card_type', $patmentsApiResponse->getPayment()->getcardDetails()->getcard()->getcardType());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_trans_date', $patmentsApiResponse->getPayment()->getcreatedAt());
		 update_post_meta($this->order->get_ID(), '_wc_square_credit_card_square_version', '3.3.0');
		   update_post_meta($this->order->get_ID(), '_wc_square_credit_card_card_expiry_date', $card_date);

            } else {

                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_authorization_code', 'not able to get');
                update_post_meta($this->order->get_ID(), '_wc_square_credit_card_trans_id', 'not able to get' );
                update_post_meta($this->order->get_ID(), '_transaction_id', 'not able to get');
	        	//$this->order->update_status( 'failed');
                if($errors){
                $this->order->add_order_note(json_encode($errors));
                }
		return (object) [
                'success' => false,
                'message' => 'Transaction Declined.'
            ];
            }
		
            update_post_meta($this->order->get_ID(), '_wc_square_credit_card_authorization_amount', ($amount/100));
            update_post_meta($this->order->get_ID(), '_wc_square_credit_card_charge_captured', 'yes');
            $woo_square_locations = get_option('wc_square_location');
            update_post_meta($this->order->get_ID(), '_wc_square_credit_card_square_location_id', $woo_square_locations);
            update_post_meta($this->order->get_ID(), '_wc_square_credit_card_customer_id', $this->getCustomerId());
            update_post_meta($this->order->get_ID(), '_wc_square_credit_card_retry_count', 0);
            update_post_meta($this->order->get_ID(), '_payment_method', 'square_credit_card');
            update_post_meta($this->order->get_ID(), '_payment_method_title', 'Credit card (Square)');
            return (object) [
                'success' => true,
                'message' => 'Transaction success.'
            ];
        } catch (\SquareConnect\ApiException $e) {
            $errors = '';
            foreach ($e->getResponseBody()->errors as $error) {
                $errors .= $error->detail . '<br>';
            }
            return (object) [
                'success' => false,
                'message' => $errors
            ];
        } catch ( Exception $e ) {
            $this->order->update_status( 'failed', $e->getMessage() );

            return (object) [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
   }

   private function getCustomerId() {
       $customerId = get_user_meta($this->userId, 'wc_square_customer_id', true);

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

    private function getPaymentMethod()          // It wrok like this ->  getCardId()
    {

        // new
        $access_token = get_option('woo_square_access_token');
        $client = new SquareClient([
            'accessToken' => $access_token,
            'environment' => Environment::PRODUCTION,
        ]);

        $squareId = get_user_meta($this->userId, 'wc_square_customer_id', true);
        $api_response = $client->getCustomersApi()->retrieveCustomer($squareId); 

        if ($api_response->isSuccess()) {
            $customerResult = $api_response->getResult();
            
              foreach ($customerResult->getCustomer()->getCards() as $card_data) {
            	$card_id = $card_data->getId();
           	 if(!empty($card_id)) {    break;    }
        }
        return $card_id;   
        } else {
            $errors = $api_response->getErrors();
          	 return 'null';
            //echo "Error: ".print_r($errors); exit;
        }

      
        // end new
    }
    private function is_wc_3_0_0_or_more()
    {
        return function_exists( 'WC' ) ? version_compare( WC()->version, '3.0.0', '>=' ) : false;
    }
}
