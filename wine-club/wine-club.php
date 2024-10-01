<?php
require 'vendor/autoload.php';

use Square\SquareClient;
use Square\LocationsApi;
use Square\Exceptions\ApiException;
use Square\Http\ApiResponse;
use Square\Models\ListLocationsResponse;
use Square\Environment;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;
error_reporting(0);
/**                                                              	
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://godardcreative.com
 * @since             1.0.7
 * @package           Wine_Club
 *
 * @wordpress-plugin
 * Plugin Name:       Club Connection
 * Plugin URI:        https://wpclubconnect.com/
 * Description:       Club Connection is an easy to use Woocommerce plugin to give your store recurring payment and batch order processing functionality.
 * Version:           5.1.9
 * Author:            Godardcreative
 * Author URI:        http://godardcreative.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wine-club
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if (!in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}
/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wine-club-activator.php
 */
function activate_wine_club() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wine-club-activator.php';
	Wine_Club_Activator::activate();
} 
/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wine-club-deactivator.php
 */
function deactivate_wine_club() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wine-club-deactivator.php';
	Wine_Club_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wine_club' );
register_deactivation_hook( __FILE__, 'deactivate_wine_club' );


// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed.
define( 'WC_STORE_URL', 'https://wpclubconnect.com/' );

// the id of your product in EDD.
define( 'WC_ITEM_ID', 14 );

if ( ! class_exists( 'WC_Plugin_Updater' ) ) {
	// load our custom updater.
	include dirname( __FILE__ ) . '/includes/WC_Plugin_Updater.php';
	include dirname( __FILE__ ) . '/includes/wc-license-error-notices.php';
}

// retrieve our license key from the DB.
$license_key = trim( get_option( 'wc_license_key' ) );
 
$status  = get_option( 'wc_license_status' );

if( $status !== false && $status == 'valid' ) {
	// setup the updater.
	$edd_updater = new WC_Plugin_Updater(
		WC_STORE_URL,
		__FILE__,
		array(
			'version' => '5.1.9', // current version number.
			'license' => $license_key, // license key (used get_option above to retrieve from DB).
			'item_id' => WC_ITEM_ID, // id of this product in EDD.
			'author'  => 'Godardcreative', // author of this plugin.
			'url'     => home_url(),
			'beta'    => false
		)
	); 
}

function wc_register_option() {
	// creates our settings in the options table
	register_setting('wc_license', 'wc_license_key', 'edd_sanitize_license' );
}
add_action('admin_init', 'wc_register_option');
function edd_sanitize_license( $new ) {
	$old = get_option( 'wc_license_key' );
	if( $old && $old != $new ) {
		delete_option( 'wc_license_status' ); // new license has been entered, so must reactivate
	}
	return $new;
}


function wc_activate_license() {
	
	// listen for our activate button to be clicked
	if( isset( $_POST['wc_license_activate'] ) ) {
		
		// run a quick security check
	 	if( ! check_admin_referer( 'wc_nonce', 'wc_nonce' ) )
			return; // get out if we didn't click the Activate button
		// retrieve the license from the database
		$license = trim( get_option( 'wc_license_key' ) );
				
		// data to send in our API request
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_id'    => WC_ITEM_ID, // The ID of the item in EDD
			'url'        => home_url()
		);
		
		// Call the custom API.
		$response = wp_remote_post( WC_STORE_URL, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );
		
		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$message =  ( is_wp_error( $response ) && ! empty( $response->get_error_message() ) ) ? $response->get_error_message() : __( 'An error occurred, please try again.' );
		} else {
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			
			if ( false === $license_data->success ) {
				switch( $license_data->error ) {
					case 'expired' :
						$message = sprintf(
							__( 'Your license key expired on %s.' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
						);
						break;
					case 'revoked' :
						$message = __( 'Your license key has been disabled.' );
						break;
					case 'missing' :
						$message = __( 'Invalid license.' );
						break;
					case 'invalid' :
					case 'site_inactive' :
						$message = __( 'Your license is not active for this URL.' );
						break;
					case 'item_name_mismatch' :
						$message = sprintf( __( 'This appears to be an invalid license key for %s.' ), EDD_SAMPLE_ITEM_NAME );
						break;
					case 'no_activations_left':
						$message = __( 'Your license key has reached its activation limit.' );
						break;
					default :
						$message = __( 'An error occurred, please try again.' );
						break;
				}
			}
		}
		// Check if anything passed on a message constituting a failure
		if ( ! empty( $message ) ) {
			$base_url = admin_url( 'admin.php?page=wine-club-plugin-license');
			$redirect = add_query_arg( array( 'sl_activation' => 'false', 'message' => urlencode( $message ) ), $base_url );
			wp_redirect( $redirect );
			exit();
		}
		// $license_data->license will be either "valid" or "invalid"
		update_option( 'wc_license_status', $license_data->license );
		$message = "License Activated Successfully"; 
		$base_url = admin_url( 'admin.php?page=wine-club-plugin-license');
		$redirect = add_query_arg( array( 'sl_activation' => 'true', 'message' => urlencode( $message ) ), $base_url );
		wp_redirect( $redirect );
		exit();
	}
}
add_action('admin_init', 'wc_activate_license');



function wc_deactivate_license() {
	// listen for our activate button to be clicked
	if( isset( $_POST['wc_license_deactivate'] ) ) {
 
		// run a quick security check
	 	if( ! check_admin_referer( 'wc_nonce', 'wc_nonce' ) )
			return; // get out if we didn't click the Activate button
		// retrieve the license from the database
		$license = trim( get_option( 'wc_license_key' ) );
		// data to send in our API request
		
		// data to send in our API request
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_id' 	 => WC_ITEM_ID, // The ID of the item in EDD
			'url'        => home_url()
		);
		// Send the remote request
		$response = wp_remote_post( WC_STORE_URL, array( 'body' => $api_params, 'timeout' => 15, 'sslverify' => false ) );
		
		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$message =  ( is_wp_error( $response ) && ! empty( $response->get_error_message() ) ) ? $response->get_error_message() : __( 'An error occurred, please try again.' );
		} else {
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		}
		// Check if anything passed on a message constituting a failure
		if ( ! empty( $message ) ) {
			$base_url = admin_url( 'admin.php?page=wine-club-plugin-license');
			$redirect = add_query_arg( array( 'sl_activation' => 'false', 'message' => urlencode( $message ) ), $base_url );
			wp_redirect( $redirect );
			exit();
		}
		// $license_data->license will be either "valid" or "invalid"
		update_option( 'wc_license_status', $license_data->license );
		$message = "License Deactivated Successfully"; 
		$base_url = admin_url( 'admin.php?page=wine-club-plugin-license');
		$redirect = add_query_arg( array( 'sl_activation' => 'true', 'message' => urlencode( $message ) ), $base_url );
		wp_redirect( $redirect );
		exit();
	}
}
add_action('admin_init', 'wc_deactivate_license');

 
/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wine-club.php';

function addMembershipAsProductType() {
	class WC_Product_Membership_Level extends WC_Product_Simple  {
		public function __construct( $product ) {
			$this->product_type = 'membership_level';
			parent::__construct( $product );
		}
	}
}
add_action( 'plugins_loaded', 'addMembershipAsProductType' );

/* Replace add to cart button with login */
add_action('woocommerce_before_shop_loop_item','remove_loop_add_to_cart_button'); 
function remove_loop_add_to_cart_button(){
    global $product;
    if($product->get_meta('membership_check') == 'on')
    {
        remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 ); 
    }
    else
    {
        add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 ); 
    }
}

/*STEP 2 -ADD NEW BUTTON THAT LINKS TO PRODUCT PAGE FOR EACH PRODUCT
*/

add_action('woocommerce_after_shop_loop_item','replace_add_to_cart_with_login'); 
function replace_add_to_cart_with_login() {
    global $product;
    if($product->get_meta('membership_check') == 'on')
    {
        $link = get_permalink(get_page_by_path('my-account')); //change 'my-account' to your login page slug.
        echo '<a href="' . esc_attr($link) . '" class="button product_type_simple add_to_cart_button product_type_simple" data-default_icon="sf-icon-account" style="border:none;background-color: #fff;" data-toggle="tooltip" data-original-title="Login"><i class="sf-icon-account"></i><span>Login</span></a>';
    }
}

add_action( 'woocommerce_single_product_summary', 'login_button_on_product_page', 30 );

function login_button_on_product_page() {
    global $product;
    if($product->get_meta('membership_check') == 'on')
    {
        $link = get_permalink(get_page_by_path('my-account')); //change 'my-account' to your login page slug.
        echo '<button type="button" data-default_text="Login" data-default_icon="sf-icon-account" class="product_type_simple button alt" onclick="window.location=\'' . esc_attr($link) . '\'"><i class="sf-icon-account"></i><span>Login</span></button>';
    }
}

// define the woocommerce_before_main_content callback 
function action_woocommerce_before_main_content( ) { 
    $product = wc_get_product();
    if($product->get_meta('membership_check') == 'on')
    {
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
    }
}; 
         
// add the action 
add_action( 'woocommerce_before_main_content', 'action_woocommerce_before_main_content', 10, 2 );


// Create WP Admin Tabs on-the-fly.
function admin_tabs($tabs, $current=NULL){
	    if(is_null($current)){
	        if(isset($_GET['page'])){
	            $current = $_GET['page'];
	        }
	    }
	    $content = '';
		$content .='<div id="woo-club-header-logo"><img  style="height: 120px" src="' .plugins_url('wine-club/admin/images/clubconnection.png'). '" ></div>';
	    $content .= '<h2 class="nav-tab-wrapper">';
	    foreach($tabs as $location => $tabname){
			
	        if($current == $location){
	            $class = ' nav-tab-active';
	        } else{
	            $class = '';    
	        }
	        $content .= '<a class="nav-tab'.$class.'" href="?page='.$location.'">'.$tabname.'</a>';
	    }
	    $content .= '</h2>';
        return $content;

}


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wine_club() {

	$plugin = new Wine_Club();
	$plugin->run();

}
run_wine_club();
add_action( 'woocommerce_order_after_calculate_totals', "custom_order_after_calculate_totals", 10, 5);
function custom_order_after_calculate_totals($calculate_tax_for, $order ) {
	if( did_action( 'woocommerce_order_after_calculate_totals' ) >= 2 ) {
		return;
	};
	$order_id = $order->get_id();
	$order = new WC_Order($order_id); 
	// Recalculate the order
    $total = $order->calculate_totals();
	$order->save();
	custom_order_call($order);
	
	
}

function custom_order_call($order){	
	if( ! is_admin() || $_POST['action'] == "runWineClubMember" ) return;
	$accessToken = get_option('woo_square_access_token');
    if($accessToken == false){ return; }

    global $wpdb;
    // SquareConnect\Configuration::getDefaultConfiguration()->setAccessToken(get_option('woo_square_access_token'));  // OLD
	// $location_id = get_option('woocommerce_squareconnect_settings')['location']; // string | The ID of the order's associated location.
	$location_id = get_option('wc_square_location'); // NEW

	// $order_id = $order->get_id();

	$parent_id = $order->get_parent_id();
	if($parent_id){
		$order_id = $parent_id;
	} else {
		$order_id = $order->get_id();
	}

	$order = wc_get_order( $order_id );


	$square_order_id = get_post_meta($order_id,'_wc_square_credit_card_square_order_id'); // string | The ID of the order to update.


	$accessToken = get_option('woo_square_access_token');
	$client = new SquareClient([
	    'accessToken' =>  $accessToken,
	    'environment' => Environment::PRODUCTION,
	]);

	$defaultApiConfig = new \SquareConnect\Configuration();
	$defaultApiConfig->setAccessToken($accessToken);
	// $defaultApiConfig->setHost("https://connect.squareupsandbox.com");  // use only if envioronment is sandbox
	$defaultApiClient = new \SquareConnect\ApiClient($defaultApiConfig);
	$api_instance = new SquareConnect\Api\OrdersApi($defaultApiClient);

	if(!empty($square_order_id[0]))
	{

	/* OLD
			$result = $api->batchRetrieveOrders($location_id, ['order_ids' => [$square_order_id]] );
			$squareOrder = $result->getOrders();
			$version = $squareOrder[0]->getVersion();
	*/

		// new  HELP: https://github.com/square/square-php-sdk/blob/master/doc/apis/orders.md#batch-retrieve-orders
		$body = new \Square\Models\BatchRetrieveOrdersRequest(
		    $square_order_id
		);
		$body->setLocationId($location_id);

		$ordersApi = $client->getOrdersApi();
		$apiResponse = $ordersApi->batchRetrieveOrders($body);

		if ($apiResponse->isSuccess()) {
		    $batchRetrieveOrdersResponse = $apiResponse->getResult();
		   	$squareOrder = $batchRetrieveOrdersResponse->getOrders();
			$version = $squareOrder[0]->getVersion();

		} else {
		    $errors = $apiResponse->getErrors();
		}
		// end new
	}
	
	


	$item_data = $order->get_items();
	$item_name = array();
	$line_items = [];
	foreach ($item_data as $item) {
		if (!$item instanceof \WC_Order_Item_Product)	{	continue;	}
		$line_item = [];
		$line_item['quantity'] = (string)$item->get_quantity();
		$line_item['base_price_money'] = ['amount' => ($order->get_item_subtotal($item) * 100) , 'currency' => $order->get_currency() , ];

		$square_id = $item->get_meta('_square_item_variation_id');

		if ($square_id)	{
			$line_item['catalog_object_id'] = $square_id;
		}	else	{
			$line_item['name'] = $item->get_name();
		}
		$line_items[] = $line_item;
	}
	foreach ($order->get_fees() as $item)
	{
	    if (!$item instanceof \WC_Order_Item_Fee)	{	continue;	}

	    $line_item = [];

	    $line_item['quantity'] = (string)1;

	    $square_id = $item->get_meta('_square_item_variation_id');

		if ($square_id)	{
			$line_item['catalog_object_id'] = $square_id;
		}	else	{
			$line_item['name'] = $item->get_name();
		}
	    $line_item['base_price_money'] = ['amount' => ($item->get_total() * 100) , 'currency' => $order->get_currency() , ];

	    $line_items[] = $line_item;
	}
	foreach( $order->get_items( 'shipping' ) as $item_id => $item ){
		if (!$item instanceof \WC_Order_Item_Shipping){ continue; }
		$line_item = [];
		$line_item['quantity'] = (string)$item->get_quantity();
		$line_item['base_price_money'] = ['amount' => ($item->get_total() * 100) , 'currency' => $order->get_currency() , ];

		$square_id = $item->get_meta('_square_item_variation_id');

		if ($square_id)	{
			$line_item['catalog_object_id'] = $square_id;
		} else {
			$line_item['name'] = $item->get_name();
		}
		$line_items[] = $line_item;
	}
	$taxes = [];
	foreach ($order->get_taxes() as $taxkey => $tax)
	{
	    $keyitem = new WC_Order_Item_Product($taxkey);
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
	$border = ['version' => $version,'reference_id' => (string)$order->get_order_number() , 'line_items' => $line_items, 'taxes' => $taxes, 'discounts' => $discounts,'customer_id' => get_user_meta($order->get_user_id(), 'wc_square_customer_id', true)];
	$body = array(
				'fields_to_clear' => ['discounts', 'line_items', 'taxes'],
		    	'order' => $border,
		    	'idempotency_key' => uniqid()
		    	);

	/* OLD
	try {

		if(!empty($square_order_id))
		{
			if(is_array($square_order_id)){ $square_order_id = $square_order_id[0]; }
			$result = $api_instance->updateOrder($location_id, $square_order_id, $body);
		}
		else
		{
			$data = [
						'idempotency_key' => (string)$order->get_order_number() , 
						'order' => [
							'reference_id' => (string)$order->get_order_number() , 
							'line_items' => $line_items, 'taxes' => $taxes, 
							'discounts' => $discounts, 
							'customer_id' => get_user_meta($order->get_user_id(), 'wc_square_customer_id', true)
						]
					];
            $result = $api_instance->createOrder($location_id, $data);
			update_post_meta($order_id, '_wc_square_credit_card_square_order_id', $result->getOrder()->getId());
		}


	} catch (Exception $e) {
		echo 'Exception when calling OrdersApi->updateOrder or create order: ', $e->getMessage(), PHP_EOL;
	} */


	//  NEW
	try {

		if(is_array($square_order_id)){ $square_order_id = $square_order_id[0]; }

		$ORDER_API_URL = 'https://connect.squareup.com/v2/orders/'.$square_order_id;
		// $ORDER_API_URL = 'https://connect.squareupsandbox.com/v2/orders/'.$square_order_id;  // sandbox

		$accessToken = get_option('woo_square_access_token');
		$authorization = "Authorization: Bearer $accessToken";

			if(!empty($square_order_id))
			{
				// $result = $api_instance->updateOrder($location_id, $square_order_id, $body);
	            $ch = curl_init($ORDER_API_URL);

	            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
	            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
	            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	            $apiresult = curl_exec($ch);
	            if (curl_errno($ch)) {
					echo 'if CURL Error:' . curl_error($ch);
				}
	            curl_close($ch);
	            $result  = json_decode($apiresult);

/*	            if(isset($result->errors)){
					print_r($result);
	            }*/

			} else {

				$data = [
							'idempotency_key' => (string)$order->get_order_number() ,
							'order' => [
								'reference_id' => (string)$order->get_order_number() ,
								'line_items' => $line_items, 'taxes' => $taxes, 
								'discounts' => $discounts, 
								'customer_id' => get_user_meta($order->get_user_id(), 'wc_square_customer_id', true),
								'location_id' => $location_id
							]
						];
	        // $result = $api_instance->createOrder($location_id, $data);
			$ch = curl_init($ORDER_API_URL);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));

			$result = curl_exec($ch);

			if (curl_errno($ch)) {
			    echo 'else Curl Error:' . curl_error($ch);
			}
			curl_close($ch);

/*			if(isset($result->errors)){
	            print_r($result);
	        }*/

	        $result = json_decode($result);

			if(!isset($result->errors)){
	        	if(is_a($result, 'stdClass')){
	        		update_post_meta($order_id, '_wc_square_credit_card_square_order_id', $result->order->id);
	        	}else{
					update_post_meta($order_id, '_wc_square_credit_card_square_order_id', $result->getOrder()->getId());
	        	}
	        }
		}

	} catch (Exception $e) {
		echo 'Exception when calling OrdersApi->updateOrder: ', $e->getMessage(), PHP_EOL;
	} 
	// END NEW TRY CATCH
}

add_action('plugins_loaded','alter_wineClubMembershipLevels_tb'); 
function alter_wineClubMembershipLevels_tb(){
	// adds emailSubject disableEmail  columns
	global $wpdb;
	$db_name = $wpdb->dbname;
	$table = $wpdb->prefix."wineClubMembershipLevels";

	$check_column = "SELECT NULL FROM INFORMATION_SCHEMA.COLUMNS 
	WHERE table_name = '$table' AND table_schema = '$db_name' AND column_name = 'emailSubject'";

	if(!$wpdb->query($check_column)){
		$add_subject = "ALTER TABLE `$table` ADD `emailSubject` TEXT NULL AFTER `emailText`";
		$wpdb->query($add_subject);
	}

	$check_disable_column = "SELECT NULL FROM INFORMATION_SCHEMA.COLUMNS 
	WHERE table_name = '$table' AND table_schema = '$db_name' AND column_name = 'disableEmail'";

	if(!$wpdb->query($check_disable_column)){
		$add_disable = "ALTER TABLE `$table` ADD `disableEmail` boolean NOT NULL DEFAULT false AFTER `emailSubject`";
		$wpdb->query($add_disable);
	}

	$check_shippingDiscount = "SELECT NULL FROM INFORMATION_SCHEMA.COLUMNS 
	WHERE table_name = '$table' AND table_schema = '$db_name' AND column_name = 'shippingDiscount'";

	if(!$wpdb->query($check_shippingDiscount)){
		$add_disable = "ALTER TABLE `$table` ADD `shippingDiscount` boolean NOT NULL DEFAULT false AFTER `orderDiscount`";
		$wpdb->query($add_disable);
	}

}



add_action('wp_ajax_update_disableEmail','disableEmail_handler');
function disableEmail_handler()
{
	global $wpdb;
	$wpdb->update(
			$wpdb->prefix . 'wineClubMembershipLevels',

			[	'disableEmail' => $_POST['disableEmail']	],

			['id' => $_POST['where'] ]
		);
	$return = array('msg'=>'success');
	echo json_encode($return);
	wp_die();
}


function add_shipping_discount() {
	
    if (is_user_logged_in() && !WC()->cart->is_empty()) {
	 
	 $user_id = get_current_user_id();
	 $membership_level_id = get_user_meta($user_id, 'wineClubMembershipLevel', true);
	 $membershipLevel_obj = MembershipLevels::find($membership_level_id);
	 if ($membershipLevel_obj) {
	 $shippingFee = WC()->cart->get_shipping_total();
         $shipping_discount = $shippingFee * floatval($membershipLevel_obj->shippingDiscount) / 100;
	 if ($shipping_discount > 0) {	
        	WC()->cart->add_fee('Shipping Discount', -$shipping_discount);
           }
        }
    }
}
add_action('woocommerce_cart_calculate_fees', 'add_shipping_discount');

function auto_select_customer_name() {
    if (isset($_GET['customer_id'])) {
        // $customer_name = sanitize_text_field($_GET['customer_name']);
        $user_id = $_GET['customer_id'];
		$first_name = get_user_meta($user_id, 'first_name', true);
		$last_name = get_user_meta($user_id, 'last_name', true);
		$customer_name = $first_name . ' ' . $last_name;
        $escaped_name = esc_html($customer_name);
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var customerName = "<?php echo $escaped_name; ?>";
                $('.wc-customer-search').select2({
                    placeholder: "<?php esc_attr_e('Guest', 'woocommerce'); ?>",
                });
                $('.wc-customer-search').append(new Option(customerName, customerName, true, true)).trigger('change');
				var user_id = "<?php echo $user_id; ?>";
                if ( ! user_id ) {
					window.alert( woocommerce_admin_meta_boxes.no_customer_selected );
					return false;
				}
				var data = {
					user_id : user_id,
					action  : 'woocommerce_get_customer_details',
					security: woocommerce_admin_meta_boxes.get_customer_details_nonce
				};

				$( this ).closest( 'div.edit_address' ).block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				$.ajax({
					url: woocommerce_admin_meta_boxes.ajax_url,
					data: data,
					type: 'POST',
					success: function( response ) {
						if ( response && response.billing ) {
							$.each( response.billing, function( key, data ) {
								$( ':input#_billing_' + key ).val( data ).trigger( 'change' );
							});
						}
						if (response && response.shipping) { // Add logic for loading shipping details
                            $.each(response.shipping, function(key, data) {
                                $(':input#_shipping_' + key).val(data).trigger('change');
                            });
                        }
						$( 'div.edit_address' ).unblock();
					}
				});
            });
        </script>
        <?php
    }
}
add_action('woocommerce_admin_order_data_after_order_details', 'auto_select_customer_name');