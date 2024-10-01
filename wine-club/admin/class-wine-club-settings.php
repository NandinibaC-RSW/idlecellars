<?php

class Wine_Club_Settings
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
    public function addSettingsToWineClubMenu()
    {		
        add_submenu_page($this->plugin_name, 'Settings', 'Settings', 'manage_woocommerce', $this->plugin_name.'-settings', [$this, 'showSettings']);
    }

    /**
     *  Show settings html template
     *
     * @since    1.0.0
     * @access   private
     */
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

    /**
     *  Save settings
     *
     * @since    1.0.0
     * @access   private
     */
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

    /**
     *  Validate settings
     *
     * @since    1.0.0
     * @access   private
     */
    public function validate($input) {
        $valid = [];

        if(empty($input['title'])) {
            add_settings_error('wineClubSettings', 'errorTitle', 'Welcome email title is required', 'error');
            die();
        } else {
            $valid['title'] = sanitize_text_field($input['title']);
        }


        return $valid;
    }
}