<?php
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'models/User.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';

use Square\SquareClient;
use Square\LocationsApi;
use Square\Exceptions\ApiException;
use Square\Http\ApiResponse;
use Square\Models\ListLocationsResponse;
use Square\Environment;
use Square\Models;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;
use Square\Models\CreateCustomerRequest;

/** HELP links
 * https://developer.squareup.com/reference/square/cards-api/create-card
 * https://github.com/square/square-php-sdk/blob/master/doc/apis/cards.md
 * 
 production key square
 sq0idp-qPx-eAGElB2mvkYkkAd9Wg
 EAAAEGdcc3dUpzFbQskDsJKq9bTs_7RLVYO48x_z1CTOBN7DOaJdXpNmVAYgJTE-
 8YZA93RT1JTAY
 * */

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://www.domagojfranc.com
 * @since      1.0.0
 *
 * @package    Wine_Club
 * @subpackage Wine_Club/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wine_Club
 * @subpackage Wine_Club/admin
 * @author     Daedalushouse <andrea@daedalushouse.com>
 */
class Wine_Club_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
	private $logger;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wine-club-admin.css', array(), rand(111,9999), 'all' );
		
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		$current_page = $_GET['page'];
		if($current_page == 'wine-club-bach-orders' || $current_page == 'wine-club' || $current_page == 'wine-club-settings' || $current_page == 'wine-club-shipping-settings') {

			wp_enqueue_script( $this->plugin_name, plugin_dir_url(__FILE__) . 'js/wine-club-admin.js', array( 'jquery' ), $this->version, true );

		}
		if($current_page == 'wine-club-members' && $_GET['action'] == 'edit-member'){
			wp_enqueue_script( $this->plugin_name, plugin_dir_url(__FILE__) . 'js/square-card-add-form.js', array( 'jquery' ), '5.9.1', true );
			wp_localize_script($this->plugin_name, 'square_params', [
			'application_id' => get_option('woo_square_app_id'),
			'environment' => 'production',
			'location_id' => get_option('wc_square_location'),
		]);
		}	
	}

	/**
     * It adds scripts for adding credit card in user profile
     *
     * @since 1.0.0
     */
	public function payment_scripts(WP_User $user)
	{		
		if(isset($_SESSION['card_success'])){
  				echo  $_SESSION['card_success'];
            			 unset($_SESSION['card_success']);
  			}	
		// wp_register_script('square', 'https://js.squareup.com/v2/paymentform', '', '2.0.9', true);
// /* NEW SAND */  wp_enqueue_script( 'square', 'https://sandbox.web.squarecdn.com/v1/square.js', '', '0.0.0', true );
/* NEW */  wp_enqueue_script( 'square', 'https://web.squarecdn.com/v1/square.js', '', '0.0.0', true );
		wp_register_script($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'admin/js/square-customer-sync-secrue-payments.js', '', rand(1,99), true );
		
		wp_localize_script($this->plugin_name, 'square_params', [
			'application_id' => get_option('woo_square_app_id'),
			'environment' => 'production',
			'location_id' => get_option('wc_square_location'),
			'custom_form_trigger_element' => apply_filters('woocommerce_square_payment_form_trigger_element', esc_js('')),
		]);
		
		wp_enqueue_script('square');
		wp_enqueue_script($this->plugin_name);		
	}
	
	private function displaySquareException(\SquareConnect\ApiException $e)
	{
		$errors = '';
		foreach ($e->getResponseBody()->errors as $error) {
			$errors .= $error->detail . '<br>';
		}
		echo '<div id="message" class="updated notice is-dismissible">' . $errors . '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button ></div>';
	}

	/**
     * It adds credit card if needed
     *
     * @since 1.0.0
     */
	public function payment_scripts_update($userId)
	{

	// NEW CODE to save card

	if (isset($_POST['payment_token']) && $_POST['payment_token'] != '') {
  		$settings = get_option('wineClubSettings');
        $access_token = $settings['sqauared_access_token'];

        $client = new SquareClient([
            'accessToken' => $access_token,
            'environment' => Environment::PRODUCTION,
        ]);

         $user = get_userdata($userId);
         $wc_square_customer_id = get_user_meta($userId, 'wc_square_customer_id', true);
        try {
         	if(empty($wc_square_customer_id))
         	{

         		$body = $this->getSquareUserData($user);
				$apiResponse = $client->getCustomersApi()->createCustomer($body);

				if ($apiResponse->isSuccess()) {
				    $createCustomerResponse = $apiResponse->getResult();
				} else {
				    $errors = $apiResponse->getErrors();
				}
				

         		// update_user_meta($userId, 'wc_square_customer_id', $userSquare->getCustomer()->getId());
         		update_user_meta($userId, 'wc_square_customer_id', $createCustomerResponse->getCustomer()->getId());
         		$user = get_userdata($userId);
         		$wc_square_customer_id = get_user_meta($userId, 'wc_square_customer_id', true);
         		$_POST['wc_square_customer_id'] = $wc_square_customer_id;
         	}
              
			$country = ($user->billing_country) ? $user->billing_country : 'US';
			$cardholder_name = $user->first_name . ' ' . $user->last_name;

			$card_data['card']['cardholder_name'] =  $cardholder_name;
			$card_data['card']['customer_id'] = $wc_square_customer_id;
			// $card_data['card']['billing_address']['address_line_1'] = $user->billing_address_1;
			// $card_data['card']['billing_address']['address_line_2'] = $user->billing_address_2;
			// $card_data['card']['billing_address']['locality'] = $user->billing_city;
			// $card_data['card']['billing_address']['administrative_district_level_1'] = $user->billing_state;
			// $card_data['card']['billing_address']['postal_code'] = $user->billing_postcode;
			// $card_data['card']['billing_address']['country'] = $country;
			$card_data['card']['version'] = 1;
			$card_data['idempotency_key'] = uniqid();
			$card_data['source_id'] = $_POST['payment_token'];
			$card_data  = json_encode($card_data);
			$accessToken = get_option('woo_square_access_token');
         	        $authorization = "Authorization: Bearer $accessToken";

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://connect.squareup.com/v2/cards');
			//curl_setopt($ch, CURLOPT_URL, 'https://connect.squareupsandbox.com/v2/cards');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $card_data);

			$headers = array();
			$headers[] = 'Square-Version: 2023-04-19';
			$headers[] = $authorization;
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$response = curl_exec($ch);
			if (curl_errno($ch)) {
			   // echo 'Error:' . curl_error($ch);
			}
			curl_close($ch);
			$api_response = json_decode($response);

			 if ($api_response->card->id) {

               		 $_SESSION['card_success'] = '<div id="message" class="updated notice is-dismissible"><p> Card Aded '. $api_response->card->id .'</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button ></div>';

          		   } else {
	
               		  $_SESSION['card_success'] = '<div id="message" class="updated notice is-dismissible"><p> ' . $api_response->errors[0]->code . ' : '. $api_response->errors[0]->detail .'</p> <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button ></div>';

          		   }
 

        } catch (\SquareConnect\ApiException $e) {
			$this->displaySquareException($e);
		}

	}
// END new

	}

	private function getSquareUserData($user)
    {
 
	//  NEW CODE TIll END of FUNC..   SUCCESS TESTED

            $given_name = ($user->first_name) ? $user->first_name : '';
            $family_name = ($user->last_name) ? $user->last_name : '';
            $email_address = ($user->user_email) ? $user->user_email : '';
            $company_name = ($user->billing_company) ? $user->billing_company : '';

            $country = ($user->billing_country) ? $user->billing_country : 'US';
            $administrative_district_level_1 = ($user->billing_state) ? $user->billing_state : '';
            $postal_code = ($user->billing_postcode) ? $user->billing_postcode : '';
            $locality = ($user->billing_city) ? $user->billing_city : '';
            $address_line_2 = ($user->billing_address_2) ? $user->billing_address_2 : '';
            $address_line_1 = ($user->billing_address_1) ? $user->billing_address_1 : '';

             if ($user->billing_phone) {
            	$phone_number = $user->billing_phone;
        	}

            $body = new CreateCustomerRequest;
			$body->setIdempotencyKey(uniqid());
			$body->setGivenName($given_name);
			$body->setFamilyName($family_name);
			$body->setCompanyName($company_name);
			// $body->setNickname('nickname2');
			$body->setEmailAddress($email_address);

			$body->setAddress(new Models\Address);
			$body->getAddress()->setAddressLine1($address_line_1);
			$body->getAddress()->setAddressLine2($address_line_2);
			// $body->getAddress()->setAddressLine3('address_line_38');
			$body->getAddress()->setLocality($locality);
			// $body->getAddress()->setSublocality('sublocality2');
			// $body->getAddress()->setAdministrativeDistrictLevel1('NY');
			$body->getAddress()->setPostalCode($postal_code);

			//$body->getAddress()->setCountry(Models\Country::$country); 
			$body->getAddress()->setCountry($country);
			$body->setPhoneNumber($phone_number);
			// $body->setReferenceId('YOUR_REFERENCE_ID');
			// $body->setNote('a customer');
			
			return $body;
    }


    /**
     * @param $api
     * @param $squareId
     * @return Exception|\SquareConnect\ApiException
     * @since 1.0.0
     */
    private function checkIfCreditCardNeedsToBeDeleted($api, $squareId)
    {
        if (isset($_GET['action']) && isset($_GET['cardId']) && $_GET['action'] == 'deleteCreditCard') {
            try {
                $response = $api->deleteCustomerCard($squareId, $_GET['cardId']);
            } catch (\SquareConnect\ApiException $e) {
            }

            echo '<div id="message" class="updated notice is-dismissible"> <p><strong>Credit card successfully deleted.</strong></p> <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button ></div>';
        }
    }


	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 * Administration Menus: http://codex.wordpress.org/Administration_Menus
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
				
		
		add_menu_page( 'Woocommerce club connection membership levels', 'Club Connection', 'manage_woocommerce', $this->plugin_name.'-settings', array($this, 'showSettings'), plugins_url('wine-club/admin/images/wine_clubs.png'));

		add_submenu_page($this->plugin_name.'-settings', 'Plugin License', 'General', 'manage_woocommerce', $this->plugin_name.'-plugin-license', array($this, 'wc_license_page'));
						
		add_submenu_page($this->plugin_name.'-settings', 'Club Setup', 'Club Setup', 'manage_woocommerce', $this->plugin_name, array($this, 'adminDisplay'));
		
		$hook = add_submenu_page($this->plugin_name.'-settings', 'Members', 'Members', 'manage_woocommerce', $this->plugin_name.'-members', array($this, 'memberDisplay'));
		
add_submenu_page($this->plugin_name.'-settings', 'Settings', 'Payment Processor Settings', 'manage_woocommerce', $this->plugin_name.'-settings', array($this, 'showSettings'));

		add_action( "load-$hook", array($this, 'wc_add_option' ));

		
		
	}


 
	public function wc_add_option() {
	 
	    $option = 'per_page';
	 
	    $args = array(
	        'label' => 'Members',
	        'default' => 10,
	        'option' => 'members_per_page'
	    );
	    add_screen_option( $option, $args );
	 
	}
 

 
	
	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 * Administration Menus: http://codex.wordpress.org/Administration_Menus
	 *
	 * @since    1.0.0
	 */
	public function add_specific_plugin_admin_menu() {
				
		
		add_menu_page( 'Woocommerce club connection membership levels', 'Club Connection', 'manage_woocommerce', $this->plugin_name.'-plugin-license', array($this, 'showSettings'), plugins_url('wine-club/admin/images/wine_clubs.png'));
		
		
		add_submenu_page($this->plugin_name.'-plugin-license', 'Plugin License', 'Plugin License', 'manage_woocommerce', $this->plugin_name.'-plugin-license', array($this, 'wc_license_page'));
	}
	
	
	 /**
	 * Add settings action link to the plugins page.
    *  Documentation : https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
	 *
	 * @since    1.0.0
	 */
	 public function add_action_links( $links ) {
	 	$settings_link = array(
	 		'<a href="' . admin_url( 'admin.php?page=' . $this->plugin_name ) . "-plugin-license" .'">' . __('Settings', $this->plugin_name) . '</a>',
	 	);
	 	return array_merge(  $settings_link, $links );

	 }

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function adminDisplay() {
		include_once( 'partials/wine-club-admin.php' );
	}

	public function memberDisplay() {
		include_once( 'partials/membership/membership-table/wine-club-members.php' );
	}


	public function addMembershipLevel() {
		include_once( 'partials/membership/addMembershipLevel.php' );
	}
	
	
	/**
	 * Render the plugin license for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function wc_license_page() {
		include_once( 'partials/plugin-license.php' );
	}
	

	/**
	 * Add membership level to general product data
     *
     * @since    1.0.0
	 */
	public function addMembershipLevelToGeneralProductData() {
		global  $post, $wpdb;
		$membershipLevelsObj = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels");

		$membershipLevels[0] = 'Product is not connected to membership level';

		foreach($membershipLevelsObj as $membershipLevel) {
			$membershipLevels[$membershipLevel->id] = $membershipLevel->name;
		}

		?>
		<div class="product_custom_field ">
			<div style="padding-left: 12px;"><h4><?php _e('Club Connection Product Settings'); ?></h4>
			<?php _e('If this product is a membership, select the associated membership level below. You can also choose to make this product available to members only.'); ?></div>
			<div class='options_group' style='max-width:50%'><?php
			woocommerce_wp_select([
				'id'      => 'membershipLevelId',
				'name'    => 'membershipLevelId',
				'class'   => 'membershipLevelId',
				'label'   => __('Membership level:', 'woocommerce'),
				'value'   => get_post_meta( $post->ID, 'membershipLevelId', true ),
				'options' => $membershipLevels
			]);
			?></div>
		</div>
		<div class="product_custom_field ">
			<div class='options_group'><?php 
			$value = get_post_meta( $post->ID, 'membership_check', true );
			?>
			<p class="form-field"><label for="membership_check"><?php _e('This is for members only:'); ?></label>	
				<input type="checkbox" name="membership_check" id="membership_check"  <?php if($value == 'on'){ ?> checked <?php } ?>><span class="description"><?php _e('Check this box to require a membership to purchase this product'); ?></span>
			</p>
			</div>
		</div>  
	<hr style="border-top-color:#eee;border-bottom:0px" />
	<?php
}

	/**
	 * Save the membership level data to product
     *
     * @since 1.0.0
	 */
	public function saveMembershipLevelData( $post_id ) 
	{


		if($_POST['membershipLevelId'] == 0) {
			delete_post_meta($post_id, 'membershipLevelId');
		} else {
			update_post_meta( $post_id, 'membershipLevelId', $_POST['membershipLevelId']);
		}
		if($_POST['membership_check'] == '') {
			delete_post_meta($post_id, 'membership_check');
		} else {
			update_post_meta( $post_id, 'membership_check', $_POST['membership_check']);
		}
	}

	

	/**
	 * Delete membership function
     *
	 * @since 1.0.0
	 */
	public function deleteMembership()
	{
		if(isset($_GET['deleteMembership'])) {
			global $wpdb;

			$membershipLevel = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels WHERE id=%d", $_GET['id']));

			$this->wpse_replace_user_role($membershipLevel->name, 'customer' );

			remove_role( $membershipLevel->name ); 

			$wpdb->get_row($wpdb->prepare("DELETE FROM ".$wpdb->prefix."wineClubMembershipLevels WHERE id=%d", $_GET['id']));

			header('Location: /wp-admin/admin.php?page=wine-club&tab=membershipLevels');
		}
	}


	public function wpse_replace_user_role( $from, $to ) {

	    $args = array( 'role' => $from );
	    $users = new WP_User_Query( $args );
	    if ( !empty( $users->get_results() ) ) {
	        foreach( $users->get_results() as $user ) {
	        	$user_meta=get_userdata($user->ID);
				$user_role = implode(', ', $user_meta->roles);
				update_user_meta($user->ID,'wineClubMembershipLevel',0);
				if($user_role != 'administrator'){
		            $u = new WP_User( $user->ID );
		            $u->remove_role( $from );
		            $u->add_role( $to );
		            unset( $u );
		        }    
	        }
	        unset( $users );
	    }
	}



	/**
	 *  Adding Stripe Saved Credit card details to user profile
     *
	 *  @since 1.0.0
	 */
	public function addStripeCardDetailsToUserProfile() {
		$user_id = (int) $_GET['user_id'];
		$saved_methods = wc_get_customer_saved_methods_list( $user_id );
		$has_methods   = (bool) $saved_methods;
		$types         = wc_get_account_payment_methods_types();

		do_action( 'woocommerce_before_account_payment_methods', $has_methods ); ?>

		<?php if ( $has_methods ) : ?>
			<h3 class="wineClubHeading"><?php _e("Stripe Credit Cards", "blank"); ?></h3>
			<table class="woocommerce-MyAccount-paymentMethods shop_table shop_table_responsive account-payment-methods-table form-table wineClubTable">
				<thead>
					<tr>
						<?php foreach ( wc_get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
							<th class="woocommerce-PaymentMethod woocommerce-PaymentMethod--<?php echo esc_attr( $column_id ); ?> payment-method-<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<?php foreach ( $saved_methods as $type => $methods ) : ?>
					<?php foreach ( $methods as $method ) : ?>
						<tr class="payment-method<?php echo ! empty( $method['is_default'] ) ? ' default-payment-method' : ''; ?>">
							<?php foreach ( wc_get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
								<td class="woocommerce-PaymentMethod woocommerce-PaymentMethod--<?php echo esc_attr( $column_id ); ?> payment-method-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
									<?php
									if ( has_action( 'woocommerce_account_payment_methods_column_' . $column_id ) ) {
										do_action( 'woocommerce_account_payment_methods_column_' . $column_id, $method );
									} elseif ( 'method' === $column_id ) {
										if ( ! empty( $method['method']['last4'] ) ) {
											/* translators: 1: credit card type 2: last 4 digits */
											echo sprintf( __( '%1$s ending in %2$s', 'woocommerce' ), esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) ), esc_html( $method['method']['last4'] ) );
										} else {
											echo esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) );
										}
									} elseif ( 'expires' === $column_id ) {
										echo esc_html( $method['expires'] );
									} elseif ( 'actions' === $column_id ) {
										foreach ( $method['actions'] as $key => $action ) {
											echo '<a href="' . esc_url( $action['url'] ) . '" class="button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>&nbsp;';
										}
									}
									?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</table>

			<?php else : ?>

				<?php endif; ?>

				<?php do_action( 'woocommerce_after_account_payment_methods', $has_methods ); ?>

			<?php 
	}


	/**
	*  Adding club connection membership to user profile
	*
	*  @since 1.0.0
	*/
	public function addWineClubMembershipToUserProfile( $user ) { 
		global $wpdb;
		$membershipLevels = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels");

		User::checkIfUserProcessManullyDateExpired($user->ID);
		?>
		<h3 class="wineClubHeading"><?php _e("Club Connection User Settings", "blank"); ?></h3>
		<table class="form-table wineClubTable">
			<tr>
				<th><label for="wineClubMembershipLevel"><?php _e("Club connection membership level:"); ?></label></th>
				<td>
					<select name="wineClubMembershipLevel" id="wineClubMembershipLevel">
						<option value="0"><?php _e('No membership'); ?></option>
						<?php foreach($membershipLevels as $membershipLevel): ?>
							<option 
							value="<?php echo $membershipLevel->id ?>"
							<?php if(get_the_author_meta( 'wineClubMembershipLevel', $user->ID ) == $membershipLevel->id) {
								echo 'selected';
							}
							?>
							>
							<?php echo $membershipLevel->name ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		</table>
	<?php }

    /**
     *  Update Club Connection Membership in user profile meta
     *
     *  @since 1.0.0
     */
    public function updateWineClubMembershipToUserProfile( $user_id ) {

    	if (!current_user_can( 'edit_user', $user_id ) ) {
    		return;
    	}
    	if(isset($_POST['wineClubMembershipLevel'])) {
    		global $wpdb;
    		$membershipOld = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels WHERE id=%d", get_user_meta( $user_id, 'wineClubMembershipLevel', true)));
    		$val = get_user_meta( $user_id, 'wineClubMembershipLevel', true);
    		update_user_meta( $user_id, 'wineClubMembershipLevel', $_POST['wineClubMembershipLevel'] );

    		$m_id = $_POST['wineClubMembershipLevel'];
    		
    		$m_name = $wpdb->get_row( "SELECT name FROM ".$wpdb->prefix."wineClubMembershipLevels where id = '$m_id'");

    		$user_meta=get_userdata($user_id);
    		$user_role = implode(', ', $user_meta->roles);
			
    		if(!empty($m_name) && $user_role != 'administrator'){

				$_POST['role'] = 'customer';
    		}elseif($m_id != $val && $m_id == 0 && $user_role != 'administrator'){
    			$_POST['role'] = 'customer';
    		}

    		$membership = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels WHERE id=%d", $_POST['wineClubMembershipLevel']));
    		do_action('wineClubMembershipUpdated', $user_id, $membershipOld->name, $membership->name);
    	}

    	if(isset($_POST['wineClubProcesManully'])) {
    		update_user_meta($user_id, 'wineClubProcesManully', $_POST['wineClubProcesManully']);
    	}

    	if(isset($_POST['wineClubLocalPickup'])) {
    		update_user_meta($user_id, 'wineClubLocalPickup', $_POST['wineClubLocalPickup']);
    	}

    	if(isset($_POST['wineClubProcesManullyTillDate'])) {
    		if(DateTime::createFromFormat('Y-m-d', $_POST['wineClubProcesManullyTillDate']) !== FALSE || $_POST['wineClubProcesManullyTillDate'] == '') {
    			update_user_meta($user_id, 'wineClubProcesManullyTillDate', $_POST['wineClubProcesManullyTillDate']);	        		
    		}
    	}

    	if(isset($_POST['wineClubProcesManullyNotes'])) {
    		update_user_meta($user_id, 'wineClubProcesManullyNotes', $_POST['wineClubProcesManullyNotes']);
    	}
    }

    /**
     *  Add Membersip Level To Contact Method /wp-admin/users.php
     *
     *  @since 1.0.0
     */
    function addMembershipLevelToContactMethod( $contactmethods ) {
    	$contactmethods['membershipLevel'] = 'Club connection membership';
    	return $contactmethods;
    }

    /**
     *  Add membership level to user table /wp-admin/users.php
     *
     *  @since 1.0.0
     */
    public function addMembershipLevelToUserTable( $column ) {
    	$column['membershipLevel'] = 'Club connection membership';
    	return $column;
    }

    /**
     *  Add membership level to user row /wp-admin/users.php
     *
     *  @since 1.0.0
     */
    public function membershipLevelUserRowInUserTable($val, $column_name, $user_id) {
    	switch ($column_name) {
    		case 'membershipLevel' :
    		$membershipLevelId = get_the_author_meta( 'wineClubMembershipLevel', $user_id );
    		global $wpdb;
    		if(is_array($membershipLevelId)){
	    		foreach ($membershipLevelId as $id) {

					$membershipLevelsName = $wpdb->get_results($wpdb->prepare("SELECT name FROM ".$wpdb->prefix."wineClubMembershipLevels where id = '$id'"));
					$all_club[] = $membershipLevelsName[0]->name;
				}
				return implode(', ', $all_club);
			}else{
		 		$membershipLevelsName = $wpdb->get_results($wpdb->prepare("SELECT name FROM ".$wpdb->prefix."wineClubMembershipLevels where id = '$membershipLevelId'"));
		 		return $membershipLevelsName[0]->name;
			}



    		// if($membership) {
    		// 	return $membership->name;	            		
    		// } else {
    		// 	if($membershipLevelId != 0) {
    		// 		return 'Deleted membership level';	            			
    		// 	}
    		// }
    		break;
    		default:
    	}
    	return $val;
    }

    /**
     *  Add membership level filter /wp-admin/users.php
     *
     *  @since 1.0.0
     */
    function addWineClubMembershipLevelFilter() {	
    	if(array_key_exists('wineClubMembershipLevel', $_GET) && $_GET[ 'wineClubMembershipLevel' ][ 0 ]) {
    		$membershipLevelId =  $_GET[ 'wineClubMembershipLevel' ][ 0 ];
    	} else {
    		$membershipLevelId =  -1;
    	}
    	echo ' <select name="wineClubMembershipLevel[]" style="float:none;margin-left: 10px;"><option value="">Membership level</option>';

    	global $wpdb;
    	$membershipLevelsObj = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels");

    	foreach($membershipLevelsObj as $membershipLevel) {
    		$selected = $membershipLevel->id == $membershipLevelId ? ' selected="selected"' : '';
    		echo '<option value="' . $membershipLevel->id . '"' . $selected . '>' . $membershipLevel->name . '</option>';
    	}

    	echo '<input type="submit" class="button" value="Filter">';
    }

    /**
     *  Filter users by membership /wp-admin/users.php
     *
     *  @since 1.0.0
     */
    function filterUsersByWineClubMembershipLevel( $query ) {
    	global $pagenow;

    	if ( is_admin() && 'users.php' == $pagenow) {
    		if(array_key_exists('wineClubMembershipLevel', $_GET) && $_GET[ 'wineClubMembershipLevel' ][ 0 ]) {
    			$section =  $_GET[ 'wineClubMembershipLevel' ][ 0 ];
    		} else {
    			$section =  null;
    		}
    		if ( null !== $section ) {
    			$meta_query = array(
    				array(
    					'key' => 'wineClubMembershipLevel',
    					'value' => $section
    				)
    			);
    			$query->set( 'meta_key', 'wineClubMembershipLevel' );
    			$query->set( 'meta_query', $meta_query );
    		}
    	}
    }
    
        public function showSettings() {


        if($_POST) {
            $this->saveSettings();
        }

        //Grab all options
        $settings = get_option('wineClubSettings');
        $settings['woo_square_app_id'] = get_option('woo_square_app_id');
        $settings['woo_square_access_token'] = get_option('woo_square_access_token');
        $settings['wc_square_location'] = get_option('wc_square_location');
        $settings['woo_stripe_api_key'] = get_option('stripe_api_key');

        include_once( 'partials/settings.php' );
    }

	public function saveSettings() {
        
        update_option('wineClubSettings', $_POST);
        if (isset($_POST['sqauared_app_id']) && $_POST['sqauared_app_id'] != null) 
        {
          update_option('woo_square_app_id', $_POST['sqauared_app_id']);  
        }
        if (isset($_POST['sqauared_access_token']) && $_POST['sqauared_access_token'] != null) 
        {
          update_option('woo_square_access_token', $_POST['sqauared_access_token']);  
        }
        if (isset($_POST['stripe_api_key']) && $_POST['stripe_api_key'] != null) 
        {
          update_option('stripe_api_key', $_POST['stripe_api_key']);  
        }
        if (isset($_POST['sqauared_Location']) && $_POST['sqauared_Location'] != null) 
        {
          update_option('wc_square_location', $_POST['sqauared_Location']);  
        }
		if(isset($_POST['admin_email'])  && $_POST['admin_email'] != null)
        {
          update_option('admin_email', $_POST['admin_email']);
        }
    }
}

add_filter('set-screen-option','wc_set_option', 10, 3);
function wc_set_option($status, $option, $value) {
 
    if ( 'members_per_page' == $option ) return $value;
 
    return $status;
 
}



