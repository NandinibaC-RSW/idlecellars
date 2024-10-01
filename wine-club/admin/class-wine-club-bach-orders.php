<?php
    require __DIR__.'/../vendor/autoload.php';

    use Square\SquareClient;
    use Square\LocationsApi;
    use Square\Exceptions\ApiException;
    use Square\Http\ApiResponse;
    use Square\Models\ListLocationsResponse;
    use Square\Environment;
    use Square\Models\Money;
    use Square\Models\CreatePaymentRequest;
    use Square\Models\createPaymentResponse;
    use Square\Models\Payment;

    // error_reporting(0);
    require plugin_dir_path(dirname(__FILE__)) . 'admin/class-wine-club-ups.php';

    class Wine_Club_Bach_Orders
    {
        private $plugin_name;
        private $version;

        public function __construct($plugin_name, $version)
        {
            $this->plugin_name = $plugin_name;
            $this->version = $version;
        }

        public function addBachOrdersToMenu()
        {
            add_submenu_page($this->plugin_name . '-settings', 'Batch Order Processing', 'Batch Order Processing', 'manage_woocommerce', $this->plugin_name . '-bach-orders', array(
                $this,
                'initSteps'
            ));
        }

        public function initSteps()
        {
            include_once ('partials/bachOrders/initSteps.php');
        }

        public function runWineClubMember()
        {
          global $wpdb;
            $product = wc_get_product($_POST['productId']);
            $membershipLevel = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "wineClubMembershipLevels WHERE id=%d", get_post_meta($product->id, 'membershipLevelId', true)));

            $order = wc_create_order(['customer_id' => $_POST['userId'], 'customer_note' => $_POST['note']]);
            $membershipLevelId = get_post_meta($_POST['productId'], 'membershipLevelId', true);
            $membershipLevel_obj = MembershipLevels::find($membershipLevelId);

            $p_ids = $_POST['p_ids'];
            $qty = $_POST['qty'];
            $shipping_method = $_POST['orerride_shipping_method']; // new on 30-sep-21

            //echo $shipping_method;

            $product_data = array_combine($p_ids, $qty);
            foreach ($product_data as $key => $value)
            {

                $_product = wc_get_product($key);
                $product_id[] = $_product->id;
                if ($_product->get_sale_price())
                {

                    $priceRegular = $_product->get_sale_price();

                }
                else
                {

                    $priceRegular = $_product->get_regular_price();

                }

                if (Wine_Club_Helpers::ifProductIsInDiscountCategory($membershipLevel_obj, $_product) == false)
                {

                    $wc_price = $priceRegular;

                }
                else
                {

                    $priceRegular = number_format($priceRegular, 2, '.', '');
                    $wc_price = $priceRegular * (100 - $membershipLevel_obj->orderDiscount) / 100;
                    $wc_price = number_format($wc_price, 2, '.', '');

                }

                $order->add_product(wc_get_product($key) , $value, ['subtotal' => $priceRegular * $value, 'total' => $wc_price * $value,

                ]);

            }

            $this->setAddresses($order);
            $order->calculate_totals();
            $total_amount = round($order->get_total(),2);

            /* New logic to override local pickup user */

                if ($membershipLevel && get_the_author_meta('wineClubLocalPickup', $_POST['userId']))   {
                        $shipping = new WC_Shipping_Rate('', 'Local pickup');
                        $order->add_shipping($shipping);
                        $order->calculate_shipping();
                } else {

            // if ($membershipLevel && $membershipLevel->shippingMethod == 'ups')
            if ($shipping_method == 'ups' || $shipping_method == 'flexible_shipping_ups' || $shippingMethod == 'wf_shipping_ups')   // new condition 30-sep-21 | new condition 23-sep-22 -> wf_shipping_ups
                {   
                    $ups_data = get_option('woocommerce_flexible_shipping_ups_settings');
        
                $UserId = $ups_data['user_id']; 
                $Password = $ups_data['password']; 
                $AccessLicenseNumber = $ups_data['access_key']; 
                $ShipperNumber = $ups_data['account_number'];
                $service = $_POST['upsservice'];
                $country_state_parts = explode(':', $ups_data['origin_country']);

        $AddressLine1 = $ups_data['origin_address'];
        $ShipFrom_City = $ups_data['origin_city'];      
        $ShipFrom_StateProvinceCode = $country_state_parts[1];
        $ShipFrom_PostalCode =  (string)$ups_data['origin_postcode'];
        $ShipFrom_CountryCode = $country_state_parts[0];   
        // $ShipTo_AddressLine1 =  get_user_meta($_POST['userId'],'shipping_address_1',true);
        $ShipTo_City =  get_user_meta($_POST['userId'],'shipping_city',true);
        $ShipTo_AddressLine1 =  get_user_meta($_POST['userId'],'shipping_address_1',true) . ' ' .$ShipTo_City;      
        $ShipTo_StateProvinceCode = get_user_meta($_POST['userId'],'shipping_state',true);
        $ShipTo_PostalCode = get_user_meta($_POST['userId'],'shipping_postcode',true);
        $ShipTo_CountryCode = get_user_meta($_POST['userId'],'shipping_country',true);
        $wineclub_flexible_shipping_ups_pickup_type = get_option('_wineclub_flexible_shipping_ups_pickup_type');
        $wineclub_flexible_shipping_ups_fees = get_option('_wineclub_flexible_shipping_ups_fees');
        $wineclub_flexible_shipping_ups_fees_type = get_option('_wineclub_flexible_shipping_ups_fees_type');
        
        if (empty($wineclub_flexible_shipping_ups_fees)) {
            $wineclub_flexible_shipping_ups_fees = 0;
        }
        if (empty($wineclub_flexible_shipping_ups_pickup_type)) {
            $wineclub_flexible_shipping_ups_pickup_type = 01;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_weight = floatval($product->get_weight());
            $quantity = intval($item->get_quantity());
            $weight += $product_weight * $quantity;
        }  
           
         $weight_unit = get_option('woocommerce_weight_unit');
         if ($weight_unit === 'oz') {
                    $ounce_to_pound = $weight / 16;
          } else {
                 $ounce_to_pound = $weight;
          }  
        //  <AddressLine1>$ShipTo_AddressLine1</AddressLine1>                                
            
        $data = "<?xml version=\"1.0\"?>
                    <AccessRequest xml:lang=\"en-US\">
                        <AccessLicenseNumber>$AccessLicenseNumber</AccessLicenseNumber>
                        <UserId>$UserId</UserId>
                        <Password>$Password</Password>
                     </AccessRequest>
                    <?xml version=\"1.0\"?>
                    <RatingServiceSelectionRequest xml:lang=\"en-US\">
                    <Request>
                        <TransactionReference/>
                        <RequestAction>Rate</RequestAction>
                        <RequestOption>Shop</RequestOption>
                    </Request>
                    <PickupType>
                        <Code>$wineclub_flexible_shipping_ups_pickup_type</Code>
                        <Description/>
                    </PickupType>
                    <Shipment>
                        <Service>
                        <Code/>
                        <Description/>
                        </Service>
                        <Shipper>
                        <ShipperNumber>$ShipperNumber</ShipperNumber>
                        <Address>
                            <AddressLine1>$AddressLine1</AddressLine1>
                            <City>$ShipFrom_City</City>
                            <StateProvinceCode>$ShipFrom_StateProvinceCode</StateProvinceCode>
                            <PostalCode>$ShipFrom_PostalCode</PostalCode>
                            <CountryCode>$ShipFrom_CountryCode</CountryCode>
                        </Address>
                        </Shipper>
                        <ShipFrom>
                        <Address>
                            <AddressLine1>$AddressLine1</AddressLine1>
                            <City>$ShipFrom_City</City>
                            <StateProvinceCode>$ShipFrom_StateProvinceCode</StateProvinceCode>
                            <PostalCode>$ShipFrom_PostalCode</PostalCode>
                            <CountryCode>$ShipFrom_CountryCode</CountryCode>
                        </Address>
                        </ShipFrom>
                        <ShipTo>
                        <Name/>
                        <CompanyName/>
                        <AttentionName/>
                        <Address>
                            <City>$ShipTo_AddressLine1</City>
                            <StateProvinceCode>$ShipTo_StateProvinceCode</StateProvinceCode>
                            <PostalCode>$ShipTo_PostalCode</PostalCode>
                            <CountryCode>$ShipTo_CountryCode</CountryCode>
                        </Address>
                        </ShipTo>
                        <RateInformation>
                         <NegotiatedRatesIndicator/>
                        </RateInformation>
                        <Package>
                        <PackagingType>
                            <Code>02</Code>
                            <Description/>
                        </PackagingType>
                        <PackageWeight>
                            <Weight>$ounce_to_pound</Weight>
                            <UnitOfMeasurement>
                            <Code>LBS</Code>
                            <Description/>
                            </UnitOfMeasurement>
                        </PackageWeight>
                          <PackageServiceOptions>
                <DeliveryConfirmation>
                 <DCISType>3</DCISType>
                </DeliveryConfirmation>
               </PackageServiceOptions>
                        </Package>
                        <ShipmentServiceOptions/>
                        <DeliveryTimeInformation>
                        <PackageBillType>03</PackageBillType>
                        </DeliveryTimeInformation>
                        <ShipmentTotalWeight>
                        <UnitOfMeasurement>
                            <Code>LBS</Code>
                            <Description/>
                        </UnitOfMeasurement>
                        <Weight>$ounce_to_pound</Weight>
                        </ShipmentTotalWeight>
                        <InvoiceLineTotal>
                        <CurrencyCode>USD</CurrencyCode>
                        <MonetaryValue>$total_amount</MonetaryValue>
                        </InvoiceLineTotal>
                    </Shipment>
                    </RatingServiceSelectionRequest>";      
                  echo "<!-- Request:  $data -->";   
                $ch = curl_init("https://onlinetools.ups.com/ups.app/xml/Rate");
                curl_setopt($ch, CURLOPT_HEADER, 1);
                curl_setopt($ch,CURLOPT_POST,1);
                curl_setopt($ch,CURLOPT_TIMEOUT, 60);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
                $result=curl_exec ($ch);

                $data = strstr($result, '<?');
                
                // Load the XML string
        $xml = simplexml_load_string($data);

        // Convert XML to array
        $json = json_encode($xml);
        $response = json_decode($json, true);
        $shippingFee = 0;
        if($response['Response']['ResponseStatusDescription'] === 'Success'){
        
            foreach ($response['RatedShipment'] as $RatedShipments) 
                {   
                if($RatedShipments['Service']['Code'] == $service){
                   $shippingFee = $RatedShipments['RatedPackage']['TotalCharges']['MonetaryValue'];
                }
            }
        }

               curl_close($ch);
                    
            if ($wineclub_flexible_shipping_ups_fees_type == "percent") {

            $chargeAmount = ($wineclub_flexible_shipping_ups_fees / 100) * $shippingFee;
            $shippingFee = $shippingFee + $chargeAmount;

            }else{
    
                $shippingFee = $shippingFee + $wineclub_flexible_shipping_ups_fees;
    
            }
            $shippingFee = round($shippingFee, 2);

                       $shipping_discount = $shippingFee * floatval($membershipLevel_obj->shippingDiscount) / 100;
                       $shipping_discount = round($shipping_discount, 2);
                        if ($shipping_discount > 0) {
                            $discount_title = "UPS shipping $".$shippingFee." (Discount - ".$membershipLevel_obj->shippingDiscount."%)";
                            $shippingFee = $shippingFee - $shipping_discount;
                          }else{
                            $discount_title = "UPS shipping";
                          }
                        $shipping = new WC_Shipping_Rate('', $discount_title , $shippingFee);
                        $order->add_shipping($shipping);
                        
                        $order->calculate_shipping();
                        echo "<!-- ship:  $shippingFee -->";


                    } // end of if statement for shipping


                    elseif ($shipping_method == "local_pickup" )
                    {
                        $shipping = new WC_Shipping_Rate('', 'Local pickup');
                        $order->add_shipping($shipping);
                        $order->calculate_shipping();
                    }
                    elseif( $shipping_method == "free_shipping" )
                    {
                        $shipping = new WC_Shipping_Rate('', 'Free shipping');
                        $order->add_shipping($shipping);
                        $order->calculate_shipping();
                    }
                    elseif($shipping_method == "flat_rate")
                    {
                        $custom_flat_rate = $_POST['custom_flat_rate']; // new on 30-sep-21
                        $shipping_discount = $custom_flat_rate * floatval($membershipLevel_obj->shippingDiscount) / 100;
                        if ($shipping_discount > 0) {
                            $discount_title = "Flat rate shipping $".$custom_flat_rate." (Discount - ".$membershipLevel_obj->shippingDiscount."%)";
                            $shippingFee = $custom_flat_rate - $shipping_discount;
                          }else{
                            $discount_title = "Flat rate shipping";
                            $shippingFee = $custom_flat_rate;
                          }
                            $shippingFee =  floatval($shippingFee);
                        $shipping = new WC_Shipping_Rate('', $discount_title , $shippingFee);
                        $order->add_shipping($shipping);
                        $order->calculate_shipping();
                    }
                    else
                    {
                        WC()
                            ->shipping
                            ->load_shipping_methods();
                        $availableMethods = WC()
                            ->shipping
                            ->get_shipping_methods();

                        foreach ($availableMethods as $key => $method)
                        {
                            if (strpos($key, $membershipLevel->shippingMethod) !== false)
                            {

                                $shipping_class = get_term_by('slug', $product->get_shipping_class() , 'product_shipping_class');
                                $class_cost = $method->get_option('class_cost_' . $shipping_class->term_id);
                                $shippingFee = (float) $_POST['custom_flat_rate'];

                                $shipping_discount = $shippingFee * floatval($membershipLevel_obj->shippingDiscount) / 100;
                                if ($shipping_discount > 0) {
                                    $discount_title = $method->method_title." $".$shippingFee." (Discount - ".$membershipLevel_obj->shippingDiscount."%)";
                                    $shippingFee = $shippingFee - $shipping_discount;
                                  }else{
                                      $discount_title = $method->method_title;
                                  }
                                 $shipping = new WC_Shipping_Rate('', $discount_title , $shippingFee);
                                $order->add_shipping($shipping);

                                break;
                            }
                        }
                        $order->calculate_shipping();
                    }

                }      

                    $woo_square_locations = get_option('wc_square_location');

                    $order->calculate_totals();
                    $line_items = [];
                    foreach ($order->get_items() as $item)
                    {

                        if (!$item instanceof \WC_Order_Item_Product)
                        {
                            continue;
                        }

                        $line_item = [];

                        $line_item['quantity'] = (string)$item->get_quantity();
                        $line_item['base_price_money'] = ['amount' => ($order->get_item_subtotal($item) * 100) , 'currency' => $order->get_currency() , ];

                        $square_id = $item->get_meta('_square_item_variation_id');

                        if ($square_id)
                        {
                            $line_item['catalog_object_id'] = $square_id;
                        }
                        else
                        {
                            $line_item['name'] = $item->get_name();
                        }

                        $line_items[] = $line_item;
                    }
                    foreach ($order->get_fees() as $item)
                    {

                        if (!$item instanceof \WC_Order_Item_Fee)
                        {
                            continue;
                        }

                        $line_item = [];

                        $line_item['quantity'] = (string)1;

                        $line_item['name'] = $item->get_name();
                        $line_item['base_price_money'] = ['amount' => ($item->get_total() * 100) , 'currency' => $order->get_currency() , ];

                        $line_items[] = $line_item;
                    }
                    foreach ($order->get_shipping_methods() as $item)
                    {

                        if (!$item instanceof \WC_Order_Item_Shipping)
                        {
                            continue;
                        }

                        $line_item = [];

                        $line_item['quantity'] = (string)1;

                        $line_item['name'] = $item->get_name();
                        $line_item['base_price_money'] = ['amount' => ($item->get_total() * 100) , 'currency' => $order->get_currency() , ];

                        $line_items[] = $line_item;
                    }
                    $taxes = [];
                    foreach ($order->get_taxes() as $tax)
                    {
                        $tax_item = [];
                        $tax_item['uid'] = uniqid();
                        $tax_item['name'] = $tax->get_name();
                        $tax_item['type'] = 'ADDITIVE';
                        $tax_item['scope'] = 'LINE_ITEM';

                        $pre_tax_total = (float)$order->get_total() - (float)$order->get_total_tax();
                        $total_tax = (float)$tax->get_tax_total() + (float)$tax->get_shipping_tax_total();

                        $percentage = ($total_tax / $pre_tax_total) * 100;

                        $tax_item['percentage'] = (string)round($percentage, 4);

                        $taxes[] = $tax_item;
                    }
                    foreach ($line_items as $key => $line_item)
                    {
                        $applied_taxes = [];
                        foreach ($taxes as $tax)
                        {
                            $applied_taxes[] = ['tax_uid' => $tax['uid']];
                        }
                        $line_items[$key]['applied_taxes'] = $applied_taxes;
                    }
                    $discounts = [];
                    if ($order->get_discount_total())
                    {
                        $discounts[] = ['name' => __('Discount', 'woocommerce-square') , 'type' => 'FIXED_AMOUNT', 'amount_money' => ['amount' => (int)($order->get_discount_total() * 100) , 'currency' => $order->get_currency() , ], 'scope' => 'ORDER', ];
                    }
                    
                                     $settings = get_option('wineClubSettings');
                    if ($settings['paymentProcessor'] == 'authorizeNet' && $_POST['order_status'] == 1) {
                            if (file_exists('wineClubDebug.txt')) {
                                $file = fopen('wineClubDebug.txt', 'a');
                            } else {
                                $file = fopen('wineClubDebug.txt', 'w');
                            }

                            // Assuming $order is already defined
                            $userId = get_post_meta($order->get_id(), '_customer_user', true);

                            // Run the Authorize.Net payment process
                            $response = (new Wine_Club_AuthorizeNet($order, $_POST['userId'], $file))->run();

                            if ($response->success == true) {
                                $order->update_status("processing");

                                // Extract details from the response
                                $payment_type = ucfirst(str_replace('_', ' ', $order->payment->type));
                                $last_four = substr($order->payment->account_number, -4); 
                                $card_type = ucfirst($order->payment->card_type);
                                $expiry_month = $order->payment->exp_month;
                                $expiry_year = $order->payment->exp_year;
                                $transaction_id = get_post_meta($order->get_id(), '_transaction_id', true);

                                // Format the message to include dynamic payment type
                                $message = sprintf(
                                    esc_html__('Authorize.Net %s Charge Approved: %s ending in %s  (Transaction ID %s)', 'your-text-domain'),
                                    $payment_type, // Dynamic payment type
                                    $card_type,
                                    $last_four,
                                    $transaction_id
                                );

                                // Add the formatted message as an order note
                                $order->add_order_note($message);

                            } else {
                                $order->update_status("failed");

                                // Check if $response->message is available and set accordingly
                                if ($response->message) {
                                    $message = 'STATUS: ' . $response->message;
                                } else {
                                    $message = 'Order failed';
                                }

                                $txt = '<h3 style="padding: 20px 0;clear:both;">';
                                $txt .= get_user_meta($userId, 'billing_first_name', true) . ' ' . get_user_meta($userId, 'billing_last_name', true) . ' ' . $message;
                                $txt .= '</h3>';

                                // Add failure order note
                                $error_message = sprintf(
                                    esc_html__('Payment Failed. %s', 'your-text-domain'),
                                    $response->message ? $response->message : 'Order failed'
                                );
                                $order->add_order_note($error_message);

                                fwrite($file, "\n". $txt);
                            }
                        }

                        elseif($settings['paymentProcessor'] == 'squareUp' && $_POST['order_status'] == 1) {

                            if (file_exists('wineClubDebug.txt')) {
                              $file = fopen('wineClubDebug.txt', 'a');
                            } else {
                              $file = fopen('wineClubDebug.txt', 'w');
                            }

                            // $order = new WC_Order($_GET['orderId']);
                        
                        $userId = get_post_meta( $order->get_id(), '_customer_user', true );

                        fwrite($file, "\n\n". 'Club connection run on '. date_create('now')->format('Y-m-d H:i:s'));
                        fwrite($file, "\n". 'User ID '. $userId.', Order ID '. $_GET['orderId']);
        
                    
                     $response = (new Wine_Club_Square($order, $_POST['userId'], $file))->run();
                     if($response->success == true) {
                                $order->update_status("processing");         

                            fwrite($file, "\n". $txt);
                        } else {
                            $order->update_status("failed");

                            // Check if $response->message is available and set accordingly
                            if ($response->message) {
                                $message = 'STATUS: ' . $response->message;
                            } else {
                                $message = 'Order failed';
                            }

                            $txt = '<h3 style="padding: 20px 0;clear:both;">';
                            $txt .= get_user_meta($userId, 'billing_first_name', true).' '.get_user_meta($userId, 'billing_last_name', true).' '.$message;
                            $txt .= '</h3>';

                            fwrite($file, "\n". $txt);
                        }
                   
                    }else{

                    $woo_square_locations = get_option('wc_square_location');
                    try
                    {
                        $data = ['idempotency_key' => (string)$order->get_order_number() , 'order' => ['reference_id' => (string)$order->get_order_number() ,'location_id' =>  $woo_square_locations, 'line_items' => $line_items, 'taxes' => $taxes, 'discounts' => $discounts, 'customer_id' => get_user_meta($_POST['userId'], 'wc_square_customer_id', true) ]];

                    $woo_order_data = json_encode($data);
                    $accessToken = get_option('woo_square_access_token');
                    $authorization = "Authorization: Bearer $accessToken";              
                    $ORDER_API_URL = 'https://connect.squareup.com/v2/orders';

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

                    update_post_meta($order->id, '_wc_square_credit_card_square_order_id', $square_order_id);
                    }
                    catch(Exception $e)
                    {
                        echo "LN 500".$e->getMessage();  exit;
                    }
                    if($_POST['order_status'] == 1)
                {
                    $user = get_userdata($_POST['userId']);
                    $settings = get_option('wineClubSettings');
                    $access_token = $settings['sqauared_access_token'];
                    $access_token = get_option('woo_square_access_token'); // Temporory
                    
                    $client = new SquareClient([
                        'accessToken' => $access_token,
                        'environment' => Environment::PRODUCTION,
                    ]);


                    $squareId = get_user_meta($_POST['userId'], 'wc_square_customer_id', true);
                    $api_response = $client->getCustomersApi()->retrieveCustomer($squareId); 

                    if ($api_response->isSuccess()) {
                        $customerResult = $api_response->getResult();   
                    } else {
                        $errors = $api_response->getErrors();
                    }
                    // end new

                      foreach ($customerResult->getCustomer()->getCards() as $card_data) {
                        $card_id = $card_data->getId();
                        if(!empty($card_id)) {
                            break;
                        }
                    }
             
                if(empty($card_id)){ echo 'No saved payment Method'; exit; }

                $customerId = $squareId; // Replace with an existing customer_id
                $customerCardId = $card_id; // Replace with an existing customer_id
                
                // The ID of the location to associate the created transaction with.
                $locationId = get_option('wc_square_location');
                try
                {
                    $order_id = $order->id;
                    $amount = (int)(round($order->calculate_totals() , 2) * 100);
                    // NEW           
                    $billing_address_line_1 = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_address_1 : $order->get_billing_address_1();
                    $billing_address_line_2 = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_address_2 : $order->get_billing_address_2();
                    $billing_locality = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_city : $order->get_billing_city();
                    $billing_administrative_district_level_1 = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_state : $order->get_billing_state();
                    $billing_postal_code = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_postcode : $order->get_billing_postcode();
                    $billing_country = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_country : $order->get_billing_country();

                    $body_sourceId = $customerCardId; //  replace with card id of customer 
                    $body_idempotencyKey = uniqid();
                    $body_amountMoney = new Money;
                    $body_amountMoney->setAmount($amount);
                    $body_amountMoney->setCurrency($order->get_currency());

                    $billing_address = new \Square\Models\Address();
                    $billing_address->setAddressLine1($billing_address_line_1);
                    $billing_address->setAddressLine2($billing_address_line_2);
                    $billing_address->setLocality($billing_locality);
                    $billing_address->setAdministrativeDistrictLevel1($billing_administrative_district_level_1);
                    $billing_address->setPostalCode($billing_postal_code);
                    $billing_address->setCountry($billing_country);


                    $body = new CreatePaymentRequest(
                        $body_sourceId,
                        $body_idempotencyKey,
                        $body_amountMoney
                    );
                    
                    $body->setOrderId($square_order_id); // this  will add product detail in swqaureup
                    $body->setCustomerId($customerId);
                    $body->setReferenceId($data['order']['reference_id']);

                    $body->setBillingAddress($billing_address);

                if ($order->needs_shipping_address())            {

                    $shipping_address_line_1 = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_address_1 : $order->get_shipping_address_1();
                    $shipping_address_line_2 = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_address_2 : $order->get_shipping_address_2();
                    $shipping_locality = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_city : $order->get_shipping_city();
                    $shipping_administrative_district_level_1 = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_state : $order->get_shipping_state();
                    $shipping_postal_code = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_postcode : $order->get_shipping_postcode();
                    $shipping_country = version_compare(WC_VERSION, '3.0.0', '<') ? $order->shipping_country : $order->get_shipping_country();


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
                        $body->setNote('Brief description');
                    */

                    $patmentsApiResponse = $client->getPaymentsApi()->createPayment($body);

                    if ($patmentsApiResponse->isSuccess()) {
                        $createPaymentResponse = $patmentsApiResponse->getResult();
                    } else {
                        $errors = $patmentsApiResponse->getErrors();
                    }
                    // END NEW

                    if(method_exists($createPaymentResponse, 'getPayment')) {
                        update_post_meta( $order_id, '_square_charge_captured', 'yes' );
                        update_post_meta($order_id, '_wc_square_credit_card_authorization_code', $createPaymentResponse->getPayment()->getId());
                        update_post_meta($order_id, '_wc_square_credit_card_trans_id', $createPaymentResponse->getPayment()->getId());
                        update_post_meta($order_id, '_transaction_id', $createPaymentResponse->getPayment()->getId());

                    } else if(method_exists($patmentsApiResponse, 'getPayment')) {

                        echo $createPaymentResponse->getPayment()->getId();
                        update_post_meta( $order_id, '_square_charge_captured', 'yes' );
                        update_post_meta($order_id, '_wc_square_credit_card_authorization_code', $patmentsApiResponse->getPayment()->getId());
                        update_post_meta($order_id, '_wc_square_credit_card_trans_id', $patmentsApiResponse->getPayment()->getId());
                        update_post_meta($order_id, '_transaction_id', $patmentsApiResponse->getPayment()->getId());
                    }else {

                        update_post_meta($order_id, '_wc_square_credit_card_authorization_code', 'not able to get');
                        update_post_meta($order_id, '_wc_square_credit_card_trans_id', 'not able to get' );
                        update_post_meta($order_id, '_transaction_id', 'not able to get');
                    }

                    update_post_meta($order_id, '_squareup_api_response_', $patmentsApiResponse );


                    update_post_meta($order_id, '_wc_square_credit_card_authorization_amount', ($amount/100));
                    update_post_meta($order_id, '_wc_square_credit_card_charge_captured', 'yes');
                    $woo_square_locations = get_option('wc_square_location');
                    update_post_meta($order_id, '_wc_square_credit_card_square_location_id', $woo_square_locations);
                    update_post_meta($order_id, '_wc_square_credit_card_customer_id', $customerId);
                    update_post_meta($order_id, '_wc_square_credit_card_retry_count', 0);
                    update_post_meta($order_id, '_payment_method', 'square_credit_card');
                    update_post_meta($order_id, '_payment_method_title', 'Credit card (Square)');
                    //update_post_meta($order_id, '_wc_square_credit_card_authorization_code', $patmentsApiResponse['transaction']['tenders'][0]['id']);
                    // Payment complete
                  
                    $order->payment_complete($createPaymentResponse->getPayment()->getId());
                    $complete_message = sprintf(__('Square charge complete (Charge ID: %s)', 'woocommerce-square') , $createPaymentResponse->getPayment()->getId());
                    $order->add_order_note($complete_message);


                    // Add order note
                    /* translators: %s - transaction id */
                }   catch(Exception $e) {
                        // echo $e->getMessage();
                        echo 'No saved payment method.';
                        // echo "<pre>";print_r($datap); echo "</pre>";
                        exit;
                }
              }
            }
            global $wpdb;

            $wpdb->update($wpdb->prefix . 'wineClubMembershipLevels', ['lastRun' => date("Y-m-d H:i:s") , ], ['id' => get_post_meta($product->id, 'membershipLevelId', true) ]);

            echo '<span style="color: green" class="dashicons dashicons-yes"></span> ' . get_user_meta($_POST['userId'], 'billing_first_name', true) . ' ' . get_user_meta($_POST['userId'], 'billing_last_name', true) . ' order created successfully';

            die();
        }

        /**
         * @param $order
         */
        private function setAddresses($order)
        {
            $order->set_address(['first_name' => get_user_meta($_POST['userId'], 'billing_first_name', true) , 'last_name' => get_user_meta($_POST['userId'], 'billing_last_name', true) , 'company' => get_user_meta($_POST['userId'], 'billing_company', true) , 'email' => get_user_meta($_POST['userId'], 'billing_email', true) , 'phone' => get_user_meta($_POST['userId'], 'billing_phone', true) , 'address_1' => get_user_meta($_POST['userId'], 'billing_address_1', true) , 'address_2' => get_user_meta($_POST['userId'], 'billing_address_2', true) , 'city' => get_user_meta($_POST['userId'], 'billing_city', true) , 'state' => get_user_meta($_POST['userId'], 'billing_state', true) , 'postcode' => get_user_meta($_POST['userId'], 'billing_postcode', true) , 'country' => get_user_meta($_POST['userId'], 'billing_country', true) , ], 'billing');
            $order->set_address(['first_name' => get_user_meta($_POST['userId'], 'shipping_first_name', true) , 'last_name' => get_user_meta($_POST['userId'], 'shipping_last_name', true) , 'company' => get_user_meta($_POST['userId'], 'shipping_company', true) , 'address_1' => get_user_meta($_POST['userId'], 'shipping_address_1', true) , 'address_2' => get_user_meta($_POST['userId'], 'shipping_address_2', true) , 'city' => get_user_meta($_POST['userId'], 'shipping_city', true) , 'state' => get_user_meta($_POST['userId'], 'shipping_state', true) , 'postcode' => get_user_meta($_POST['userId'], 'shipping_postcode', true) , 'country' => get_user_meta($_POST['userId'], 'shipping_country', true) , ], 'shipping');
        }

        public function wineProductList()
        {

            if (!empty($_POST["project_list_id"]))
            {

                $membershipLevel = MembershipLevels::find($_POST["membershipLevelId"]);

                $project_list_id = $_POST["project_list_id"];
                echo '<table class="wc_product_table">
                <tbody>
                <th class="item_wine" data-sort="string-ins" width="50% !important">Item</th>
                <th class="cost_wine" data-sort="string-ins" width="5%">Cost</th>
                <th data-sort="string-ins" width="5%"></th>
                <th class="qty_wine" data-sort="string-ins" width="5%">Qty</th>
                <th data-sort="string-ins" width="5%">Total</th>
                <th data-sort="string-ins" width="5%"></th>';

                foreach ($project_list_id as $product_id)
                {

                    $_product = wc_get_product($product_id);

                    if ($_product->get_sale_price())
                    {

                        $priceRegular = $_product->get_sale_price();

                    }
                    else
                    {

                        $priceRegular = $_product->get_regular_price();
                    }

                    if (Wine_Club_Helpers::ifProductIsInDiscountCategory($membershipLevel, $_product) == false)
                    {

                        $wc_price = $priceRegular;

                    }
                    else
                    {
                        $priceRegular = floatval($priceRegular);
                        $discountPercentage = floatval($membershipLevel->orderDiscount);

                        $wc_price = $priceRegular * (100 - $discountPercentage) / 100;
                        $discount = $priceRegular - $wc_price;

                    }

                    echo '<tr id = wc_' . $product_id . '><input type="hidden" name="order_productId[]" value="' . $product_id . '"><td width="50%">' . get_the_title($product_id) . '</td>
                        <td width="5%" class="wc_price_' . $product_id . '">' . get_woocommerce_currency_symbol() . '' . $priceRegular;
                    if (!empty($discount))
                    {

                        echo '<div class="wc-order-item-discount">-' . get_woocommerce_currency_symbol() . '<span class="wc_item_discount_' . $product_id . '">' . $discount . '</span></div>';
                    }
                    echo '</td><td width="5%"> <span class="wc_mul">x</span></td>
                        <td width="5%"><input type="number" name="qty[]" class="wc_qty wc_qty_' . $product_id . '" value="1"></td>';
                    if (!empty($discount))
                    {
                        $discount_price = $priceRegular - $discount;
                        echo '<td width="5%">' . get_woocommerce_currency_symbol() . '<span class="wc_total_price_' . $product_id . '">' . $discount_price . '</span></td>';

                    }
                    else
                    {
                        $discount_price = $priceRegular;
                        echo '<td width="5%">' . get_woocommerce_currency_symbol() . '<span class="wc_total_price_' . $product_id . '">' . $priceRegular . '</span></td>';
                    }
                    echo '</td><td width="5%"><img id=wc_img_' . $product_id . ' src="' . plugins_url("wine-club/admin/images/filled-cancel.png") . '" style="width: 20px;height: auto;cursor: pointer;"/></td></tr>'; ?>

                    <script type="text/javascript">$("#wc_img_<?php echo $product_id; ?>").on('click', function(e) {var whichtr = $(this).closest("tr"); if (confirm('Are you sure you want to remove the selected items?') == true) { whichtr.remove();} });
                       $(".wc_qty_<?php echo $product_id; ?>").bind("keyup change", function(e) {
                            
                            var  wc_product_qty = $(".wc_qty_<?php echo $product_id; ?>").val();
                            $(".wc_total_price_<?php echo $product_id; ?>").html(parseFloat((<?php echo $discount_price; ?> * wc_product_qty).toFixed(2)));
                        
                            <?php if (!empty($discount))
                    { ?>
                            $(".wc_total_discount_<?php echo $product_id; ?>").html(parseFloat(<?php echo $discount; ?> * wc_product_qty).toFixed(2));
                            <?php
                    } ?>
                            
                        }) 
                    </script>
                <?php
                }
                echo '</tbody></table>';
            } else        {
                echo 'Please Select Product !';
            }
            wp_die();
    }
    }
