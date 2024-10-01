<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://www.domagojfranc.com
 * @since      1.0.0
 *
 * @package    Wine_Club
 * @subpackage Wine_Club/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wine_Club
 * @subpackage Wine_Club/public
 * @author     Daedalushouse <andrea@daedalushouse.com>
 */
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/helpers/class-wine-club-helpers.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'models/MembershipLevels.php';


class Wine_Club_Public {

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

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wine_Club_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wine_Club_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wine-club-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wine_Club_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wine_Club_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wine-club-public.js', array( 'jquery' ), $this->version, false );

	}

    /**
     * Add membership club connection level on order complete
     *
     * @since    1.0.0
     */
	public function addMembershipWineClubOnPurchase( $order_id ) {
	    $order = wc_get_order( $order_id );
	    $items = $order->get_items();
        $adminEmail =  get_option( 'admin_email' );

	    foreach ( $items as $item ) {
	        if ( $order->user_id > 0 && get_post_meta( $item['product_id'], 'membershipLevelId', true)) {
	        	$membershipLevelId = get_post_meta( $item['product_id'], 'membershipLevelId', true );

    			  if($membershipLevelId) {
    			    // If member doesn't have club connection or he changed one
    			    if(get_user_meta($order->user_id, 'wineClubMembershipLevel', true) != $membershipLevelId) 
    			    {
    					$membershipLevel = MembershipLevels::find($membershipLevelId);

                        if( ! $membershipLevel->disableEmail ) {

                            ob_start();
    				        include plugin_dir_path( dirname( __FILE__ ) ) . 'public/partials/wineClubWelcomeEmail.php';
                            $message = ob_get_clean();

                            wp_mail($order->billing_email, $membershipLevel->emailSubject, $message, ['Content-Type: text/html; charset=UTF-8', 'From: Club Connection <'. $adminEmail .'>'] );
                        }
              }

              global $wpdb;
              $oldMembership = $wpdb->get_row($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wineClubMembershipLevels WHERE id=%d", get_user_meta( $order->user_id, 'wineClubMembershipLevel', true)));

                $club_meta = get_user_meta( $order->user_id, 'wineClubMembershipLevel', true);
                
                    if(is_array($club_meta)){
                        array_push($club_meta,$membershipLevelId);
                        update_user_meta( $order->user_id, 'wineClubMembershipLevel', $club_meta);
                    }else{
                        if($club_meta){
                            $club_meta1[] = $club_meta;
                        }    
                        $club_meta1[] = $membershipLevelId;
                        update_user_meta( $order->user_id, 'wineClubMembershipLevel', $club_meta1);
                    }
                

                //$user_meta=get_userdata($order->user_id);
                //$user_role = implode(', ', $user_meta->roles);

              $m_name = $wpdb->get_row( "SELECT name FROM ".$wpdb->prefix."wineClubMembershipLevels where id = '$membershipLevelId'");

                if($user_role != 'administrator'){

                    $wp_user_object = new WP_User($order->user_id);
                    $wp_user_object->set_role('customer');
                
                }

              $membership = $wpdb->get_row($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wineClubMembershipLevels WHERE id=%d", $_POST['wineClubMembershipLevel']));
              do_action('wineClubMembershipUpdated', $order->user_id, $oldMembership->name, $membership->name);
            }
            break;
	    	}
	    }
	}

    /**
     * Display discount if user is club connection member html
     *
     * @since    1.0.0
     */
	public function wineClubPriceHtml( $price, $product ) {

        // Hide discount prices in wp-admin or user not logged in
        if(is_admin() || get_current_user_id() == 0) {
            return $price;
        }

		$membershipLevelId = get_the_author_meta( 'wineClubMembershipLevel', get_current_user_id() );

		if($membershipLevelId) {
				global $wpdb;
				$membershipLevel = MembershipLevels::find($membershipLevelId);


				if($membershipLevel && $membershipLevel->orderDiscount > 0) {

					if(Wine_Club_Helpers::ifProductIsInDiscountCategory($membershipLevel, $product) == false) {
						return $price;
					}

				  if($product->get_sale_price()) {
				  	$priceRegular =  json_decode(json_encode($product->get_sale_price()));
				  } else {
				  	$priceRegular =  json_decode(json_encode($product->get_regular_price()));
				  }
				  $priceRegular = number_format($priceRegular, 2, '.', '');
				  $priceWithDiscount = $priceRegular * (100 -$membershipLevel->orderDiscount) / 100;
				  $priceWithDiscount = number_format($priceWithDiscount, 2, '.', '');

				  $price = '<del><span>'.get_woocommerce_currency_symbol().''.$priceRegular.'</span></del><ins><span class="woocommerce-Price-amount amount">'.get_woocommerce_currency_symbol().''.$priceWithDiscount.'</span></ins>';
				  $price .= '<br>';
				  $price .= '<span class="membershipDiscountLabel">MEMBERSHIP DISCOUNT</span>';
			}
		}

	    return $price;
	}

    /**
     * Display discount if user is club connection member
     *
     * @since    1.0.0
     */
	function wineClubPrice($price, $product) {

        // Hide discount prices in wp-admin or user not logged in
        if(is_admin() || get_current_user_id() == 0) {
            return $price;
        }

		$membershipLevelId = get_the_author_meta( 'wineClubMembershipLevel', get_current_user_id() );
		if($membershipLevelId) {
			global $wpdb;
			$membershipLevel = MembershipLevels::find($membershipLevelId);

			if(Wine_Club_Helpers::ifProductIsInDiscountCategory($membershipLevel, $product) == false) {
				return $price;
			}

			if($membershipLevel && $membershipLevel->orderDiscount > 0) {
				$price = $price * (100 -$membershipLevel->orderDiscount) / 100;
			}
		}

	    return $price;
	}

    /**
     * Display discount in cart review if user is club connection member
     *
     * @since    1.0.0
     */
	public function showSavingsInTheCart() {
	    global $woocommerce;
	    global $wpdb;
		$discount_total = 0;
		$userId = get_current_user_id();

		if($userId == 0) {
		    return;
        }

		$membershipLevelId = get_the_author_meta( 'wineClubMembershipLevel', $userId );

		if($membershipLevelId) {
			$membershipLevel = MembershipLevels::find($membershipLevelId);
			if($membershipLevel && $membershipLevel->orderDiscount > 0) {
                $discount_total = $this->clubMemberDiscount($woocommerce, $membershipLevel, $discount_total);
            } else {
				$discount_total = $this->regularUserCartDiscount($woocommerce, $discount_total);
			}
		} else {
			$discount_total = $this->regularUserCartDiscount($woocommerce, $discount_total);
		}
	             
	    if ( $discount_total > 0 ) {
	    echo '<tr class="cart-discount">
	    <th>'. __( 'Discount', 'woocommerce' ) .'</th>
	    <td data-title=" '. __( 'You Saved', 'woocommerce' ) .' ">'
	    . wc_price( $discount_total + $woocommerce->cart->discount_cart ) .'</td>
	    </tr>';
	    }
	 
	}

    /**
     * Display discount in order email
     *
     * @since    1.0.0
     */
    public function showSavingsInOrderEmail($order) {
        global $woocommerce;
        global $wpdb;
        $userId = get_current_user_id();

        if($userId == 0) {
            return;
        }

        if($woocommerce->cart == null) {
            return;
        }


        $membershipLevelId = get_the_author_meta( 'wineClubMembershipLevel', $userId );

        if($membershipLevelId) {
            $membershipLevel = MembershipLevels::find($membershipLevelId);
            if($membershipLevel && $membershipLevel->orderDiscount > 0) {
                $discount_total = 0;
                $discount_total = $this->clubMemberDiscount($woocommerce, $membershipLevel, $discount_total);
                echo '<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; color: #737373; border: 1px solid #e4e4e4;margin-bottom: 30px;" border="1">';
                    echo '<tbody>';
                        echo '<tr>';
                            echo '<td class="td" style="text-align: left; vertical-align: middle; border: 1px solid #eee; word-wrap: break-word; color: #737373; padding: 12px;font-weight: bold;width:66%;">Club connection membership discount</td>';
                            echo '<td class="td" style="text-align: left; vertical-align: middle; border: 1px solid #eee; word-wrap: break-word; color: #737373; padding: 12px;">'. wc_price($discount_total).'</td>';
                        echo '<tr>';
                    echo '</tbody>';
                echo '</table>';
            }
        }
    }

    /**
     * Display additional notes in order confirmation email
     *
     * @since    1.0.0
     */
    public function showAdditionalNotes($order) {
        if($order->get_customer_note()) {
            echo '<h2>Additional notes:</h2>';
            echo '<p>'. $order->get_customer_note().'</p>';
        }
    }

    /**
     * Display discount on thankyou page
     *
     * @since    1.0.0
     */
    public function showSavingsOnThankYouPage($order)
    {
        //        User cart is empty at this point so this code is not working right now, we have to find work around...
        return;

        global $woocommerce;
        global $wpdb;
        $userId = get_current_user_id();

        if($userId == 0) {
            return;
        }

        $membershipLevelId = get_the_author_meta( 'wineClubMembershipLevel', $userId );

        if($membershipLevelId) {
            $membershipLevel = MembershipLevels::find($membershipLevelId);
            if($membershipLevel && $membershipLevel->orderDiscount > 0) {
                $discount_total = 0;
                $discount_total = $this->clubMemberDiscount($woocommerce, $membershipLevel, $discount_total);
                echo '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">';
                echo '<tbody>';
                echo '<tr>';
                echo '<th class="woocommerce-table__product-name product-name" style="width: 60.5%">Club connection membership discount</th>';
                echo '<th class="woocommerce-table__product-name product-name">'. wc_price($discount_total).'</th>';
                echo '<tr>';
                echo '</tbody>';
                echo '</table>';
            }
        }
    }


    /**
     * Helper method used in showSavingsInTheCart()
     *
     * @since 1.0.0
     * @param $woocommerce
     * @param $membershipLevel
     * @param $discount_total
     * @return int
     */
    public function clubMemberDiscount($woocommerce, $membershipLevel, $discount_total)
    {
        foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) {
            $_product = $values['data'];
            $discount = ((int)($_product->get_regular_price() - (int)$_product->get_sale_price()) * ($membershipLevel->orderDiscount / 100)) * $values['quantity'];
            $discount_total += $discount;
        }
        return $discount_total;
    }

    /**
     * Helper method used in showSavingsInTheCart()
     *
     * @since    1.0.0
     * @param $woocommerce
     * @param $discount_total
     * @return int
     */
    public function regularUserCartDiscount($woocommerce, $discount_total) {
        foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values) {
            $_product = $values['data'];
            if ($_product->is_on_sale()) {
                $discount = ($_product->get_regular_price() - $_product->get_sale_price()) * $values['quantity'];
                $discount_total += $discount;
            }
        }

        return $discount_total;
    }
}

/**
 * Methods that add wine club membership to my account page
 * Still has to be added in the class...
 *
 * @since    1.0.0
 */
function addMembershipTabToMyAccount( $items ) {
    $items['wineClubMembership'] = __( 'Manage Club Membership', 'membershipLevel' );
    // $items['pauseWineClubMembership'] = __( 'Pause Membership', 'pasuemembershipLevel' );
    return $items;
}
add_filter( 'woocommerce_account_menu_items', 'addMembershipTabToMyAccount', 10, 1 );

function addMembershipEndPoint() {
    add_rewrite_endpoint( 'wineClubMembership', EP_PAGES );
    add_rewrite_endpoint( 'pauseWineClubMembership', EP_PAGES );
}
add_action( 'init', 'addMembershipEndPoint' );

function addMembershipEndPointContent() {
	include plugin_dir_path( dirname( __FILE__ ) ) . 'public/partials/wineClubMembershipInMyAccount.php';
}
add_action( 'woocommerce_account_wineClubMembership_endpoint', 'addMembershipEndPointContent' );

function addPauseMembershipEndPointContent() {
    include plugin_dir_path( dirname( __FILE__ ) ) . 'public/partials/pauseWineClubMembershipInMyAccount.php';
}
add_action( 'woocommerce_account_pauseWineClubMembership_endpoint', 'addPauseMembershipEndPointContent' );

