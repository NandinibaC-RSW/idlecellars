<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://www.domagojfranc.com
 * @since      1.0.0
 *
 * @package    Wine_Club
 * @subpackage Wine_Club/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wine_Club
 * @subpackage Wine_Club/includes
 * @author     Daedalushouse <andrea@daedalushouse.com>
 */
class Wine_Club {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wine_Club_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->plugin_name = 'wine-club';
		$this->version = '5.1.9';

		$this->load_dependencies();
		$this->set_locale();

		
        if (1) {
			$this->define_admin_hooks();
			$this->define_settings_hooks();
            $this->define_shipping_settings_hooks();
			$this->define_bach_orders_hooks();
        }

		$this->define_public_hooks();
		$this->define_public_shipping_hooks();
        $this->define_notifications_hooks();
    }

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wine_Club_Loader. Orchestrates the hooks of the plugin.
	 * - Wine_Club_i18n. Defines internationalization functionality.
	 * - Wine_Club_Admin. Defines all hooks for the admin area.
	 * - Wine_Club_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for license handler functionality
		 * core plugin.
		 */

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-edd-license-handler.php';
		
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wine-club-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wine-club-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wine-club-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the bach orders area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wine-club-bach-orders.php';

		/**
		 * The class responsible for defining all actions that occur in the bach orders area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wine-club-batch-orders-payments.php';

        /**
         * The class responsible for defining all actions that send notifications to admin
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wine-club-admin-notifications.php';

        /**
         * The class responsible for defining all actions connected to settings
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wine-club-settings.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wine-club-public.php';

		/**
         * The class responsible for defining all actions connected to shipping settings
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wine-club-shipping-settings.php';

        /**
		 * The class responsible for defining all actions that occur in the public-facing shipping
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wine-club-public-shipping.php';


		/**
		* Frontend user role wise and wine category product add to cart condition.
		*/

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wine-club-public-frontend.php';
		
		/**
		* You may also need to add the require_once( ABSPATH . '/wp-admin/includes/plugin.php' ); before you can use is_plugin_active()
		*/
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$this->loader = new Wine_Club_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wine_Club_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Wine_Club_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
	    $pluginAdmin = new Wine_Club_Admin( $this->get_plugin_name(), $this->get_version() );

	    // Add css and js scripts
	    $this->loader->add_action( 'admin_enqueue_scripts', $pluginAdmin, 'enqueue_styles' );
	    $this->loader->add_action( 'admin_enqueue_scripts', $pluginAdmin, 'enqueue_scripts' );
	    
	    if(!is_plugin_active( 'square-customer-sync/square-customer-sync.php' ) ) {
	        if(is_plugin_active( 'woocommerce-square/woocommerce-square.php' ) ) {
	            /* Adding Square add Card form */
	            $this->loader->add_action('edit_user_profile', $pluginAdmin, 'payment_scripts');                     
	            /* Add new square credit card */
	            $this->loader->add_action('edit_user_profile_update', $pluginAdmin, 'payment_scripts_update');
	        }
	    }
	    
	    $status  = get_option( 'wc_license_status' );

	    if( $status !== false && $status == 'valid' ) {
	        // Add menu item to admin panel menu structure
	        $this->loader->add_action( 'admin_menu', $pluginAdmin, 'add_plugin_admin_menu');
	    } else {
	        // Add menu item to admin panel menu structure
	        $this->loader->add_action( 'admin_menu', $pluginAdmin, 'add_specific_plugin_admin_menu');
	    }

	    // Add Settings link to the plugin
	    $plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );
	    $this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $pluginAdmin, 'add_action_links' );

	    // Add membership level to simple product
	    $this->loader->add_action( 'woocommerce_product_options_general_product_data', $pluginAdmin, 'addMembershipLevelToGeneralProductData' );
	    $this->loader->add_action( 'woocommerce_process_product_meta', $pluginAdmin, 'saveMembershipLevelData' );

	    // Adding wineclub membership level to user profile /wp-admin/users.php
	    $this->loader->add_action( 'edit_user_profile', $pluginAdmin, 'addWineClubMembershipToUserProfile' );
	    $this->loader->add_action( 'edit_user_profile_update', $pluginAdmin, 'updateWineClubMembershipToUserProfile' );
	    
	    $wineClubSettings = get_option('wineClubSettings' , true);
	    if($wineClubSettings['paymentProcessor'] == 'stripe'){
	        // Adding stripe save credit card details to user profile /wp-admin/users.php
	        $this->loader->add_action( 'edit_user_profile', $pluginAdmin, 'addStripeCardDetailsToUserProfile' );
	    } elseif($wineClubSettings['paymentProcessor'] == 'squareUp'){
	        if(!is_plugin_active( 'woocommerce-square/woocommerce-square.php' ) ) {
	            // Adding stripe save credit card details to user profile /wp-admin/users.php
	            $this->loader->add_action( 'edit_user_profile', $pluginAdmin, 'addStripeCardDetailsToUserProfile' );
	        }   
	    }

	    // Add JavaScript to toggle Authorize.Net section
	    add_action('admin_footer', function() use ($wineClubSettings) {
	        ?>
	        <script>
	        function toggleAuthorizeNetSection() {
	            var paymentProcessor = '<?php echo esc_js($wineClubSettings['paymentProcessor']); ?>';
	            var authorizeNetSections = document.getElementsByClassName('sv_wc_payment_gateway_user_settings');

	            if (authorizeNetSections.length === 0) {
	                return;
	            }

	            var displayStyle = (paymentProcessor === 'stripe' || paymentProcessor === 'squareUp') ? 'none' : 'block';

	            for (var i = 0; i < authorizeNetSections.length; i++) {
	                authorizeNetSections[i].style.display = displayStyle;
	            }
	        }

	        // Call the function when the page loads
	        if (document.readyState === 'loading') {
	            document.addEventListener('DOMContentLoaded', toggleAuthorizeNetSection);
	        } else {
	            toggleAuthorizeNetSection();
	        }
	        </script>
	        <?php
	    });

	    // Adding club connection membership level to user list /wp-admin/users.php
	    $this->loader->add_filter( 'user_contactmethods', $pluginAdmin, 'addMembershipLevelToContactMethod', 10, 1 );
	    $this->loader->add_filter( 'manage_users_columns', $pluginAdmin, 'addMembershipLevelToUserTable', 10, 1 );
	    $this->loader->add_filter( 'manage_users_custom_column', $pluginAdmin, 'membershipLevelUserRowInUserTable', 10, 3 );

	    // Adding club connection membership level to filter to user list /wp-admin/users.php
	    $this->loader->add_action( 'restrict_manage_users', $pluginAdmin, 'addWineClubMembershipLevelFilter' );
	    $this->loader->add_filter( 'pre_get_users', $pluginAdmin, 'filterUsersByWineClubMembershipLevel' );

	    // Delete membership hook
	    $this->loader->add_action( 'admin_init', $pluginAdmin, 'deleteMembership' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$pluginPublic = new Wine_Club_Public( $this->get_plugin_name(), $this->get_version() );

        // Add css and js scripts
        $this->loader->add_action( 'wp_enqueue_scripts', $pluginPublic, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $pluginPublic, 'enqueue_scripts' );

		/* Add membership club connection level on order complete */
		$this->loader->add_action( 'woocommerce_order_status_completed', $pluginPublic, 'addMembershipWineClubOnPurchase' );

		/* Display discount if user is club connection member  */
		$this->loader->add_filter( 'woocommerce_get_price_html', $pluginPublic, 'wineClubPriceHtml', 100, 2 );
		$this->loader->add_filter('woocommerce_product_get_price', $pluginPublic, 'wineClubPrice', 10, 2);

        /* Display discount in cart review if user is club connection member  */
        $this->loader->add_action( 'woocommerce_cart_totals_before_shipping', $pluginPublic, 'showSavingsInTheCart', 99);
		$this->loader->add_action( 'woocommerce_review_order_before_shipping', $pluginPublic, 'showSavingsInTheCart', 99);

        /* Display discount in order confirmation email */
        $this->loader->add_action( 'woocommerce_email_after_order_table', $pluginPublic, 'showSavingsInOrderEmail', 99);

        /* Display additional notes in order confirmation email */
        $this->loader->add_action( 'woocommerce_email_after_order_table', $pluginPublic, 'showAdditionalNotes', 100);

        /* Display discount on thank you page */
        $this->loader->add_action( 'woocommerce_order_details_after_order_table', $pluginPublic, 'showSavingsOnThankYouPage', 99);
	}

	/**
	 * Define shipping settings hooks
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_shipping_settings_hooks() {

		$shippingSettings = new Wine_Club_Shipping_Settings( $this->get_plugin_name(), $this->get_version() );


        // Add settings to club connection
		$status  = get_option( 'wc_license_status' );
		
		if( $status !== false && $status == 'valid' ) {
			
		$this->loader->add_action( 'admin_menu', $shippingSettings, 'addShippingSettingsToWineClubMenu' );
		
		}
		
        $this->loader->add_action( 'admin_init', $shippingSettings, 'addShippingFlashMessage');
        $this->loader->add_filter( 'sanitize_option_wineClubShippingRoles', $shippingSettings, 'sanitizeWineClubShippingRoles', 10, 2);
	}


	/**
	 * Define public shipping settings hooks
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_shipping_hooks() {

		$shippingSettings = new Wine_Club_Public_Shipping( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_filter( 'woocommerce_package_rates', $shippingSettings, 'getAvailableShippingMethods', 200, 2 );


	}


	

    /**
     *  Register all of the hooks related to the bach orders area.
     *
     * @since    1.0.0
     * @access   private
     */
	private function define_bach_orders_hooks() {
	    /*
	     * Batch orders methods
	     */
		$bachOrders = new Wine_Club_Bach_Orders( $this->get_plugin_name(), $this->get_version() );
 
		$status  = get_option( 'wc_license_status' );
		
		if( $status !== false && $status == 'valid' ) {
		
		$this->loader->add_action( 'admin_menu', $bachOrders, 'addBachOrdersToMenu' );
		
		}
		
		$this->loader->add_action('wp_ajax_runWineClubMember', $bachOrders, 'runWineClubMember' );
		$this->loader->add_action('wp_ajax_wineProductList', $bachOrders, 'wineProductList' );


        /*
         * Batch orders payment processor
        */
		$bachOrdersPaymnets = new Wine_Club_Batch_Orders_Payments( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action('init', $bachOrdersPaymnets, 'runWineClubMemberPayments' );
		$this->loader->add_action( 'woocommerce_admin_order_data_after_order_details', $bachOrdersPaymnets, 'addProcessPaymentToOpenOrder' );
	}

    /**
     *  Register all of the hooks related to the sending notifications to admin.
     *
     * @since    1.0.0
     * @access   private
     */
	private function define_notifications_hooks() {
        $adminNotifications = new Wine_Club_Admin_Notifications( $this->get_plugin_name(), $this->get_version() );

        // Check for notifications on profile update
        $this->loader->add_action('woocommerce_save_account_details', $adminNotifications, 'userChangedEmailSendNotification', 10, 1 );
        $this->loader->add_action('woocommerce_customer_save_address', $adminNotifications, 'userChangedAddressSendNotification', 10, 1 );

        // Save old data when user open profile
        $this->loader->add_action('woocommerce_edit_account_form', $adminNotifications, 'saveOldUserData');
        $this->loader->add_action('woocommerce_before_edit_address_form_billing', $adminNotifications, 'saveOldUserData');
        $this->loader->add_action('woocommerce_before_edit_address_form_shipping', $adminNotifications, 'saveOldUserData');

        // Delete data when user save profile
        $this->loader->add_action('woocommerce_save_account_details', $adminNotifications, 'cleanUpOldData', 1000, 1 );
        $this->loader->add_action('woocommerce_customer_save_address', $adminNotifications, 'cleanUpOldData', 1000, 1 );
    }

    /**
     *  Register all of the hooks related to the sending notifications to admin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_settings_hooks()
    {
        $adminSettings = new Wine_Club_Settings( $this->get_plugin_name(), $this->get_version() );
		
		$status  = get_option( 'wc_license_status' );
		
		if( $status !== false && $status == 'valid' ) {
		// Add settings to wine club
		$this->loader->add_action( 'admin_menu', $adminSettings, 'addSettingsToWineClubMenu' );
		
		}
		
    }


	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wine_Club_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}

