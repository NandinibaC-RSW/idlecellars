<?php

class Wine_Club_Shipping_Settings
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     *  Add settings to club connection menu
     *
     * @since    1.0.0
     * @access   private
     */
    public function addShippingSettingsToWineClubMenu()
    {
        add_submenu_page($this->plugin_name.'-settings', 'Shipping settings', 'Shipping settings', 'manage_woocommerce', $this->plugin_name.'-shipping-settings', [$this, 'showSettings']);
    }

    /**
     *  Show settings html template
     *
     * @since    1.0.0
     * @access   private
     */
    public function showSettings() {
        global $woocommerce;
        global $wp_roles;

        $posted = $this->clean_multidimensional_array($_POST);

        // Check the user capabilities
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'woocommerce-payment_shipping' ) );
        }

       if ( isset( $posted['shipping_is_enabled'] ) ) {
            $uploaded_shipping_options = $posted['shipping_is_enabled'];
            $current_shipping_options = get_option('wineClubShippingRoles');
            if($uploaded_shipping_options != $current_shipping_options) {
                update_option( 'wineClubShippingRoles', $posted['shipping_is_enabled'] );
                $this->clear_shipping_transients();
            }
        }

        // Add ups shipping
        $shippingMethods = WC()->shipping->get_shipping_methods();
        $shippingMethod = array_filter($shippingMethods, function($shippingMethod) {
            return $shippingMethod == 'wf_shipping_ups';
        }, ARRAY_FILTER_USE_KEY);
        $shippingMethod = reset($shippingMethod);

        $shipping_zones = $this->get_shipping_zones();

        array_push($shipping_zones[0]['shipping_methods'], $shippingMethod);
        // Add ups shipping (needs refactoring)

        $methodarray = get_option('wineClubShippingRoles');

        $wineClubs = $this->getWineClubs();

        include_once( 'partials/shipping/shippingSettings.php' );
    }


    public function get_shipping_zones() {
        $zones = array();
        if (class_exists('WC_Shipping_Zone')) {

            // Rest of the World zone
            $zone                                                     = new WC_Shipping_Zone(0);
            $zones[ $zone->get_id() ]                            = $zone->get_data();
            $zones[ $zone->get_id() ]['formatted_zone_location'] = $zone->get_formatted_location();
            $zones[ $zone->get_id() ]['shipping_methods']        = $zone->get_shipping_methods();

            // Add user configured zones
            $zones = array_merge( $zones, WC_Shipping_Zones::get_zones() );
        }

        return $zones;
    }

    function getWineClubs() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels");
    }

    /**
     * Print single Shipping row with all methods, for a specific club connection.
     *
     * @param string $wineClub row to print.
     * @param array $shipping_methods
     * @param array $methodarray stored settings for the role based methods plugin
     */

    function print_shipping_row($wineClub, $shipping_zones, $methodarray) {
        echo '<tr>';

        if(is_string($wineClub)) {
        	echo "<td><b>" . $wineClub . "</b></td>";        	
        } else {
        	echo "<td><b>" . $wineClub->name . "</b></td>";        	
        }

    	foreach ($shipping_zones as $shipping_zone) {
	      foreach($shipping_zone['shipping_methods'] as $s) {

            if($s->id == 'wf_shipping_ups') {
                $id = $s->id;
            } else {
                $id = $s->id . ':' . $s->instance_id;
            }

        	if(is_string($wineClub)) {
        		$wineClubId = 'nonWineClubMember';
        	} else {
	        	$wineClubId = $wineClub->id;
        	}


	        if ( ( isset( $methodarray[ $wineClubId ][ $id ] ) && $methodarray[ $wineClubId ][ $id ] == 'on' ) || $methodarray == false ) {
	            $checked=' checked ';
	        }
	        else {
	            $checked='';
	        }
	        echo "<td><input type='checkbox' name='shipping_is_enabled[$wineClubId][$id]' $checked/></td>";
	      }
     	} 

        echo "</tr>";
    }

    /**
     * Get the title for the method. Title is a user supplied version, method_title is used as a fallback
     *
     * @param obj $method Method
     */

    function get_shipping_method_title($method) {
        if($method->title) {
            return $method->title;
        } elseif($method->method_title) {
            return $method->method_title;
        } else {
            return "";
        }
    }

    /* Based on https://gist.github.com/esthezia/5804445 */
    private function clean_multidimensional_array ($data = array()) {
        if (!is_array($data) || !count($data)) {
            return array();
        }
        foreach ($data as $k => $v) {
            if (!is_array($v) && !is_object($v)) {
                $data[$k] = wc_clean($v);
            }
            if (is_array($v)) {
                $data[$k] = $this->clean_multidimensional_array($v);
            }
        }
        return $data;
    }

    private function clear_shipping_transients() {
        wc_delete_product_transients();
        wc_delete_shop_order_transients();
        WC_Cache_Helper::get_transient_version( 'shipping', true );
    }


    public function addShippingFlashMessage() {
        if(isset($_POST['settings-updated']) && $_POST['settings-updated']) {
            add_action('admin_notices', array($this, 'shippingFlashMessage'));
        }
    }

    public function shippingFlashMessage(){
        include_once( 'partials/shipping/shippingFlashMessage.php' );
    }

    public function sanitizeWineClubShippingRoles($updated_value, $option_name) {
       $validation_passed = true;

        if(!isset($updated_value) || !is_array($updated_value)) {
            $validation_passed = false;
        }

        //Now loop through the array to make sure everything is valid.

        foreach($updated_value as $role => $role_settings) {

            // Validate the key (payment method) first.
            if(!is_string($role) && !is_int($role)) {
                $validation_passed = false;
            }

            //Next, validate the value,

            if(is_array($role_settings)) {
                foreach($role_settings as $method => $setting) {
                    if(!is_scalar($method)) {
                        $validation_passed = false;
                    }

                    if($setting != 'on') {
                        $validation_passed = false;
                    }
                }
            } else {
                $validation_passed = false;
            }
        }

        //If everything checks out, return the new value.
        if($validation_passed) {
            return $updated_value;
        } else {
            $original_option_value = get_option( $option_name );
            return $original_option_value;
        }
    }

}