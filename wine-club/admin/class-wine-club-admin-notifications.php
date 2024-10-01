<?php

class Wine_Club_Admin_Notifications {

    private $plugin_name;
    private $version;
    private $adminEmail;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->adminEmail =  get_option( 'admin_email' );
    }

    /**
     * Send email if user change his email
     *
     * @param $userId
     * @param $oldUserData
     * @since 1.0.0
     */
    public function userChangedEmailSendNotification($userId) {

        $oldUserData = get_transient( 'wc_old_user_data' . $userId );
        $user = get_userdata( $userId );

        if($oldUserData->user_email != $user->user_email) {
            $message = sprintf( __( 'Following user updated email address.' ) ) . "<br>";
            $message .= sprintf( __( 'Full name: %s %s, Email: %s' ), $user->first_name, $user->last_name, $user->user_email ). "<br><br>";
            $message .= sprintf( __( 'Old Email: %s' ), $oldUserData->user_email ). "<br>";
            wp_mail( $this->adminEmail, sprintf( __( '[Club Connection] User Profile Update' ), get_option('blogname') ), $message, ['Content-Type: text/html; charset=UTF-8'] );
        }
    }

    /**
     * Send email if address changes
     *
     * @param $userId
     * @param $oldUserData
     * @since 1.0.0
     */
    public function userChangedAddressSendNotification( $userId ) {

        $oldUserData = get_transient( 'wc_old_user_data' . $userId );
        $user = get_userdata( $userId );

        if($this->userUpdatedData('billing', $oldUserData, $user)) {
            $this->userUpdatedDataSendEmail('billing', $oldUserData, $user);
        }

        if($this->userUpdatedData('shipping', $oldUserData, $user)) {
            $this->userUpdatedDataSendEmail('shipping', $oldUserData, $user);
        }
    }

    /**
     * Save old user data and meta for later comparison for non-standard fields (phone, address etc.)
     *
     * @since 1.0.0
     */
    public function saveOldUserData(){

        $userId = get_current_user_id();
        $user_data = get_userdata( $userId );
        $user_meta = get_user_meta( $userId );

        foreach( $user_meta as $key=>$val ){
            $user_data->data->$key = current($val);
        }

        // 1 hour should be sufficient
        set_transient( 'wc_old_user_data' . $userId, $user_data->data, 60 * 60 );
    }

    /**
     * Cleanup when done
     *
     * @since 1.0.0
     */
    public function cleanUpOldData( $userId){
        delete_transient( 'wc_old_user_data' . $userId );
    }

    /**
     * Check if user updated data
     *
     * @since 1.0.0
     */
    private function userUpdatedData($type, $oldUserData, $user) {

        // Only billing has email and phone
        if($type == 'billing' && $oldUserData->{$type.'_email'} != $user->{$type.'_email'} || $oldUserData->{$type.'_phone'} != $user->{$type.'_phone'}) {
            return 1;
        }

        if(
            $oldUserData->{$type.'_first_name'} != $user->{$type.'_first_name'} ||
            $oldUserData->{$type.'_last_name'} != $user->{$type.'_last_name'} ||
            $oldUserData->{$type.'_company'} != $user->{$type.'_company'} ||
            $oldUserData->{$type.'_address_1'} != $user->{$type.'_address_1'} ||
            $oldUserData->{$type.'_address_2'} != $user->{$type.'_address_2'} ||
            $oldUserData->{$type.'_country'} != $user->{$type.'_country'} ||
            $oldUserData->{$type.'_state'} != $user->{$type.'_state'} ||
            $oldUserData->{$type.'_city'} != $user->{$type.'_city'} ||
            $oldUserData->{$type.'_postcode'} != $user->{$type.'_postcode'}
        ) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Send email if user updated data
     *
     * @since 1.0.0
     */
    private function userUpdatedDataSendEmail($type, $oldUserData, $user) {
        $message = sprintf( __( 'Following user updated his %s information\'s.' ), $type ) . "<br>";
        $message .= sprintf( __( 'Full name: %s %s, Email: %s' ), $user->first_name, $user->last_name, $user->user_email ). "<br><br>";

        if($oldUserData->{$type.'_first_name'} != $user->{$type.'_first_name'}) {
            $message .= sprintf( __( 'New %s first name: %s' ), $type, $user->{$type.'_first_name'}). "<br>";
            $message .= sprintf( __( 'Old %s first name: %s' ), $type, $oldUserData->{$type.'_first_name'}). "<br><br>";
        }

        if($oldUserData->{$type.'_last_name'} != $user->{$type.'_last_name'}) {
            $message .= sprintf( __( 'New %s last name: %s' ), $type, $user->{$type.'_last_name'}). "<br>";
            $message .= sprintf( __( 'Old %s last name: %s' ), $type, $oldUserData->{$type.'_last_name'}). "<br><br>";
        }

        if($oldUserData->{$type.'_company'} != $user->{$type.'_company'}) {
            $message .= sprintf( __( 'New %s company: %s' ), $type, $user->{$type.'_company'}). "<br>";
            $message .= sprintf( __( 'Old %s company: %s' ), $type, $oldUserData->{$type.'_company'}). "<br><br>";
        }

        if($type == 'billing' && $oldUserData->{$type.'_email'} != $user->{$type.'_email'}) {
            $message .= sprintf( __( 'New %s email: %s' ), $type, $user->{$type.'_email'}). "<br>";
            $message .= sprintf( __( 'Old %s email: %s' ), $type, $oldUserData->{$type.'_email'}). "<br><br>";
        }

        if($type == 'billing' && $oldUserData->{$type.'_phone'} != $user->{$type.'_phone'}) {
            $message .= sprintf( __( 'New %s phone: %s' ), $type, $user->{$type.'_phone'}). "<br>";
            $message .= sprintf( __( 'Old %s phone: %s' ), $type, $oldUserData->{$type.'_phone'}). "<br><br>";
        }

        if($oldUserData->{$type.'_address_1'} != $user->{$type.'_address_1'}) {
            $message .= sprintf( __( 'New %s address 1: %s' ), $type, $user->{$type.'_address_1'}). "<br>";
            $message .= sprintf( __( 'Old %s address 1: %s' ), $type, $oldUserData->{$type.'_address_1'}). "<br><br>";
        }

        if($oldUserData->{$type.'_address_2'} != $user->{$type.'_address_2'}) {
            $message .= sprintf( __( 'New %s address 2: %s' ), $type, $user->{$type.'_address_2'}). "<br>";
            $message .= sprintf( __( 'Old %s address 2: %s' ), $type, $oldUserData->{$type.'_address_2'}). "<br><br>";
        }

        if($oldUserData->{$type.'_state'} != $user->{$type.'_state'}) {
            $message .= sprintf( __( 'New %s state: %s' ), $type, $user->{$type.'_state'}). "<br>";
            $message .= sprintf( __( 'Old %s state: %s' ), $type, $oldUserData->{$type.'_state'}). "<br><br>";
        }

        if($oldUserData->{$type.'_city'} != $user->{$type.'_city'}) {
            $message .= sprintf( __( 'New %s city: %s' ), $type, $user->{$type.'_city'}). "<br>";
            $message .= sprintf( __( 'Old %s city: %s' ), $type, $oldUserData->{$type.'_city'}). "<br><br>";
        }

        if($oldUserData->{$type.'_country'} != $user->{$type.'_country'}) {
            $message .= sprintf( __( 'New %s country: %s' ), $type, $user->{$type.'_country'}). "<br>";
            $message .= sprintf( __( 'Old %s country: %s' ), $type, $oldUserData->{$type.'_country'}). "<br><br>";
        }

        if($oldUserData->{$type.'_postcode'} != $user->{$type.'_postcode'}) {
            $message .= sprintf( __( 'New %s postcode: %s' ), $type, $user->{$type.'_postcode'}). "<br>";
            $message .= sprintf( __( 'Old %s postcode: %s' ), $type, $oldUserData->{$type.'_postcode'}). "<br><br>";
        }

        wp_mail( $this->adminEmail, sprintf( __( '[Club Connection] User Profile Update' ), get_option('blogname') ), $message, ['Content-Type: text/html; charset=UTF-8'] );
    }
}
